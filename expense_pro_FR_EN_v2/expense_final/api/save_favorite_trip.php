<?php
/**
 * API - Sauvegarder un trajet favori
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
$data = json_decode(file_get_contents('php://input'), true);

try {
    $nom = sanitize($data['nom'] ?? '');
    $depart = sanitize($data['depart'] ?? '');
    $arrivee = sanitize($data['arrivee'] ?? '');
    $distance = floatval($data['distance'] ?? 0);
    
    if (empty($nom) || empty($depart) || empty($arrivee)) {
        throw new Exception('Tous les champs sont obligatoires');
    }
    
    // Extraire les villes des adresses
    $departVille = extractCity($depart);
    $arriveeVille = extractCity($arrivee);
    
    // Vérifier si le trajet existe déjà
    $checkQuery = $pdo->prepare("
        SELECT id FROM trajets_favoris 
        WHERE user_id = ? AND adresse_depart = ? AND adresse_arrivee = ?
    ");
    $checkQuery->execute([$userId, $depart, $arrivee]);
    
    if ($checkQuery->fetch()) {
        // Mettre à jour le compteur d'utilisation
        $updateQuery = $pdo->prepare("
            UPDATE trajets_favoris 
            SET nb_utilisations = nb_utilisations + 1, last_used_at = NOW()
            WHERE user_id = ? AND adresse_depart = ? AND adresse_arrivee = ?
        ");
        $updateQuery->execute([$userId, $depart, $arrivee]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Trajet déjà en favori, compteur mis à jour'
        ]);
    } else {
        // Créer un nouveau favori
        $stmt = $pdo->prepare("
            INSERT INTO trajets_favoris (user_id, nom, adresse_depart, adresse_arrivee, depart_ville, arrivee_ville, distance_km, nb_utilisations)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([
            $userId,
            $nom,
            $depart,
            $arrivee,
            $departVille,
            $arriveeVille,
            $distance
        ]);
        
        echo json_encode([
            'success' => true,
            'id' => $pdo->lastInsertId(),
            'message' => 'Trajet ajouté aux favoris'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Extraire le nom de la ville d'une adresse
 */
function extractCity($address) {
    // Pattern simple pour extraire la ville (généralement avant le code postal)
    if (preg_match('/(\d{5})\s+([^,]+)/i', $address, $matches)) {
        return trim($matches[2]);
    }
    // Ou prendre le premier élément significatif
    $parts = explode(',', $address);
    return trim($parts[0] ?? '');
}
