<?php
/**
 * get_ocr_scan.php - Récupérer un scan OCR
 * À placer dans : api/get_ocr_scan.php
 */

require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

// Activer les logs d'erreurs
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$scanId = intval($_GET['id'] ?? 0);

if (!$scanId) {
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
    exit;
}

try {
    // Vérifier que la table existe
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'ocr_scans'")->fetch();
    if (!$tableCheck) {
        echo json_encode([
            'success' => false, 
            'message' => 'La table ocr_scans n\'existe pas. Veuillez exécuter le script SQL de création.'
        ]);
        exit;
    }
    
    // Récupérer le scan
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
            'vendor' => $scan['vendor'] ?? '',
            'date' => $scan['date'] ?? '',
            'amount' => $scan['amount'] ?? 0,
            'currency' => $scan['currency'] ?? 'EUR',
            'invoice_number' => $scan['invoice_number'] ?? '',
            'description' => $scan['description'] ?? '',
            'confidence' => $scan['confidence'] ?? 85
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur base de données: ' . $e->getMessage()
    ]);
}
?>