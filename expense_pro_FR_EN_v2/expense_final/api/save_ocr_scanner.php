<?php
/**
 * save_ocr_scan.php - Sauvegarder un scan OCR dans la base
 * À placer dans : api/save_ocr_scan.php
 */

require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['vendor']) || !isset($input['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO ocr_scans 
        (user_id, vendor, date, amount, currency, invoice_number, description, category_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $input['vendor'],
        $input['date'] ?? null,
        $input['amount'],
        $input['currency'] ?? 'EUR',
        $input['invoice_number'] ?? '',
        $input['description'] ?? '',
        $input['category_id'] ?? null
    ]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ============================================================
// get_ocr_scan.php - Récupérer un scan OCR par ID
// Créer un fichier séparé : api/get_ocr_scan.php
// ============================================================

/*
<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$scanId = intval($_GET['id'] ?? 0);

if (!$scanId) {
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM ocr_scans WHERE id = ? AND user_id = ?");
    $stmt->execute([$scanId, $_SESSION['user_id']]);
    $scan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$scan) {
        echo json_encode(['success' => false, 'message' => 'Scan introuvable']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'vendor' => $scan['vendor'],
            'date' => $scan['date'],
            'amount' => $scan['amount'],
            'currency' => $scan['currency'],
            'invoice_number' => $scan['invoice_number'],
            'description' => $scan['description'],
            'confidence' => $scan['confidence'] ?? 85
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
*/
?>