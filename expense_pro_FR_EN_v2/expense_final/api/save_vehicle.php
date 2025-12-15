<?php
/**
 * API - Sauvegarder un véhicule
 */

require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $type = sanitize($_POST['type'] ?? 'voiture');
    $marque = sanitize($_POST['marque'] ?? '');
    $modele = sanitize($_POST['modele'] ?? '');
    $immatriculation = strtoupper(sanitize($_POST['immatriculation'] ?? ''));
    $puissance = intval($_POST['puissance_fiscale'] ?? 5);
    $energie = sanitize($_POST['energie'] ?? 'essence');
    $annee = intval($_POST['annee'] ?? 0) ?: null;
    
    // Validation
    if (empty($marque)) {
        throw new Exception('La marque est obligatoire');
    }
    
    if (empty($modele)) {
        throw new Exception('Le modèle est obligatoire');
    }
    
    if (empty($immatriculation)) {
        throw new Exception('L\'immatriculation est obligatoire');
    }
    
    // Vérifier si l'immatriculation existe déjà pour cet utilisateur
    $checkQuery = $pdo->prepare("SELECT id FROM vehicules WHERE user_id = ? AND immatriculation = ?");
    $checkQuery->execute([$userId, $immatriculation]);
    if ($checkQuery->fetch()) {
        throw new Exception('Ce véhicule est déjà enregistré');
    }
    
    // Vérifier si c'est le premier véhicule (le mettre en favori)
    $countQuery = $pdo->prepare("SELECT COUNT(*) FROM vehicules WHERE user_id = ?");
    $countQuery->execute([$userId]);
    $isFirst = $countQuery->fetchColumn() == 0;
    
    // Insérer le véhicule
    $stmt = $pdo->prepare("
        INSERT INTO vehicules (
            user_id, 
            type, 
            marque, 
            modele, 
            immatriculation, 
            puissance_fiscale, 
            energie, 
            annee, 
            favori,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $userId,
        $type,
        $marque,
        $modele,
        $immatriculation,
        $puissance,
        $energie,
        $annee,
        $isFirst ? 1 : 0
    ]);
    
    $vehiculeId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'id' => $vehiculeId,
        'message' => 'Véhicule enregistré avec succès',
        'data' => [
            'id' => $vehiculeId,
            'type' => $type,
            'marque' => $marque,
            'modele' => $modele,
            'immatriculation' => $immatriculation,
            'puissance_fiscale' => $puissance,
            'energie' => $energie,
            'favori' => $isFirst
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}