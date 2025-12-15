<?php
/**
 * process_ocr.php - OCR Avancé pour reçus marocains
 */
require_once '../includes/config.php';
requireLogin();
header('Content-Type: application/json');

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu']);
    exit;
}

$file = $_FILES['file'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
$maxSize = 10 * 1024 * 1024;

if (!in_array($file['type'], $allowedTypes) || $file['size'] > $maxSize || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Fichier invalide']);
    exit;
}

try {
    $uploadDir = __DIR__ . '/../uploads/ocr/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'ocr_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Erreur sauvegarde fichier');
    }
    
    // OCR Processing
    $ocrData = performOCR($filepath);
    
    // Sauvegarder en base
    $stmt = $pdo->prepare("INSERT INTO ocr_scans (user_id, filename, vendor, date, amount, currency, invoice_number, description, confidence, raw_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'], $filename, $ocrData['vendor'], $ocrData['date'],
        $ocrData['amount'], $ocrData['currency'], $ocrData['invoice_number'],
        $ocrData['description'], $ocrData['confidence'], $ocrData['raw_text']
    ]);
    
    echo json_encode(['success' => true, 'scan_id' => $pdo->lastInsertId(), 'data' => $ocrData]);
    
} catch (Exception $e) {
    if (isset($filepath) && file_exists($filepath)) @unlink($filepath);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function performOCR($filepath) {
    $rawText = '';
    
    // Tesseract avec configs multiples
    if (commandExists('tesseract')) {
        foreach (['-l fra+ara --psm 6', '-l fra --psm 4', '-l fra --psm 3'] as $cfg) {
            $out = sys_get_temp_dir() . '/ocr_' . uniqid();
            exec("tesseract " . escapeshellarg($filepath) . " $out $cfg 2>&1");
            if (file_exists("$out.txt")) {
                $rawText = file_get_contents("$out.txt");
                @unlink("$out.txt");
                if (strlen(trim($rawText)) > 30) break;
            }
        }
    }
    
    return parseReceipt($rawText);
}

function parseReceipt($text) {
    $data = ['vendor' => '', 'date' => null, 'amount' => 0, 'currency' => 'MAD', 
             'invoice_number' => '', 'description' => '', 'confidence' => 0, 'raw_text' => $text];
    
    if (empty(trim($text))) {
        $data['description'] = 'Extraction impossible - saisie manuelle requise';
        return $data;
    }
    
    $text = mb_convert_encoding($text, 'UTF-8', 'auto');
    $lines = array_filter(array_map('trim', preg_split('/[\r\n]+/', $text)));
    $textLower = mb_strtolower($text);
    
    // === MONTANT (priorité haute) ===
    $amountPatterns = [
        '/montant\s*(?:principal|total|ttc|de\s*la\s*tsava)?\s*:?\s*([0-9\s]+[,\.][0-9]{2})/ui',
        '/total\s*(?:ttc|ht|général)?\s*:?\s*([0-9\s]+[,\.][0-9]{2})/ui',
        '/(?:net\s*[àa]\s*payer|a\s*payer)\s*:?\s*([0-9\s]+[,\.][0-9]{2})/ui',
        '/([0-9\s]+[,\.][0-9]{2})\s*(?:dh|mad|dhs|درهم)/ui',
        '/(?:dh|mad)\s*:?\s*([0-9\s]+[,\.][0-9]{2})/ui',
        '/([0-9]+[,\.][0-9]{2})\s*€/u',
        '/€\s*([0-9]+[,\.][0-9]{2})/u',
    ];
    
    $amounts = [];
    foreach ($amountPatterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $m) {
                $clean = str_replace([' ', ','], ['', '.'], $m);
                $val = floatval($clean);
                if ($val > 0 && $val < 1000000) $amounts[] = $val;
            }
        }
    }
    
    // Chercher aussi les montants simples en fin de ligne
    if (preg_match_all('/:\s*([0-9]+[,\.][0-9]{2})\s*$/m', $text, $matches)) {
        foreach ($matches[1] as $m) {
            $val = floatval(str_replace(',', '.', $m));
            if ($val > 0) $amounts[] = $val;
        }
    }
    
    if (!empty($amounts)) {
        $data['amount'] = max($amounts);
        $data['confidence'] += 25;
    }
    
    // === DEVISE ===
    if (preg_match('/dh|mad|درهم|dirhams?/ui', $text)) $data['currency'] = 'MAD';
    elseif (preg_match('/€|eur/ui', $text)) $data['currency'] = 'EUR';
    
    // === DATE ===
    $datePatterns = [
        '/date\s*(?:de\s*paiement|d[\'e]\s*[ée]dition)?\s*:?\s*(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})/ui',
        '/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})/',
        '/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2})/',
    ];
    
    foreach ($datePatterns as $i => $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
            if (checkdate((int)$month, (int)$day, (int)$year)) {
                $data['date'] = "$year-$month-$day";
                $data['confidence'] += 15;
                break;
            }
        }
    }
    
    // === FOURNISSEUR ===
    $vendors = [
        'ministère' => 'Ministère de l\'Économie et des Finances',
        'direction générale des impôts' => 'Direction Générale des Impôts',
        'marjane' => 'Marjane', 'carrefour' => 'Carrefour', 'acima' => 'Acima',
        'label vie' => 'Label\'Vie', 'bim' => 'BIM', 'total' => 'Total Maroc',
        'shell' => 'Shell', 'afriquia' => 'Afriquia', 'oncf' => 'ONCF',
        'iam' => 'Maroc Telecom', 'inwi' => 'Inwi', 'orange' => 'Orange',
    ];
    
    foreach ($vendors as $key => $name) {
        if (stripos($textLower, $key) !== false) {
            $data['vendor'] = $name;
            $data['confidence'] += 15;
            break;
        }
    }
    
    // Si pas trouvé, prendre première ligne significative
    if (empty($data['vendor'])) {
        foreach (array_slice($lines, 0, 5) as $line) {
            if (strlen($line) > 3 && strlen($line) < 50 && !preg_match('/^\d|^date|^total|^n°/i', $line)) {
                $data['vendor'] = ucwords(strtolower(trim($line)));
                $data['confidence'] += 5;
                break;
            }
        }
    }
    
    // === NUMÉRO FACTURE ===
    if (preg_match('/(?:n°|num[ée]ro|facture|ticket|immatriculation)\s*:?\s*([A-Z0-9\-\/]+)/ui', $text, $m)) {
        $data['invoice_number'] = strtoupper(trim($m[1]));
        $data['confidence'] += 10;
    }
    
    // === DESCRIPTION ===
    $descKeywords = ['attestation', 'taxe', 'tsava', 'paiement', 'véhicule', 'diesel', 'essence'];
    $descParts = [];
    foreach ($descKeywords as $kw) {
        if (stripos($text, $kw) !== false) $descParts[] = ucfirst($kw);
    }
    $data['description'] = !empty($descParts) ? implode(' - ', $descParts) : substr(implode(' ', array_slice($lines, 2, 3)), 0, 200);
    
    $data['confidence'] = min(95, max(10, $data['confidence']));
    
    return $data;
}

function commandExists($cmd) {
    exec("which $cmd 2>&1", $o, $r);
    return $r === 0;
}
?>
