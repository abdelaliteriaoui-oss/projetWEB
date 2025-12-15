<?php
/**
 * API - Créer une demande de remboursement depuis GPS Tracking
 * VERSION CORRIGÉE - Colonnes exactes de la base de données
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
    // Récupérer les données du formulaire
    $montant = floatval($_POST['montant'] ?? 0);
    $dateDepense = isset($_POST['date_depense']) ? $_POST['date_depense'] : date('Y-m-d');
    $distanceKm = floatval($_POST['distance_km'] ?? 0);
    $depart = isset($_POST['depart']) ? trim($_POST['depart']) : '';
    $arrivee = isset($_POST['arrivee']) ? trim($_POST['arrivee']) : '';
    $vehiculeId = !empty($_POST['vehicule_id']) ? intval($_POST['vehicule_id']) : null;
    
    // Validation
    if ($montant <= 0) {
        throw new Exception('Le montant doit être supérieur à 0');
    }
    
    // Construire l'objet (description)
    $objet = "Trajet {$depart} → {$arrivee}";
    if ($distanceKm > 0) {
        $objet .= " ({$distanceKm} km)";
    }
    
    // Construire le lieu
    $lieu = "{$depart} - {$arrivee}";
    
    // Insérer la demande dans la table demandes
    $stmt = $pdo->prepare("
        INSERT INTO demandes (
            user_id, 
            type_depense,
            date_depense, 
            montant_total, 
            lieu,
            objet,
            statut
        ) VALUES (?, 'transport', ?, ?, ?, ?, 'soumise')
    ");
    
    $stmt->execute([
        $userId,
        $dateDepense,
        $montant,
        $lieu,
        $objet
    ]);
    
    $demandeId = $pdo->lastInsertId();
    
    // Enregistrer le trajet dans la table trajets
    if ($distanceKm > 0) {
        // Calculer l'émission CO2 (environ 120g/km en moyenne)
        $emissionCO2 = round($distanceKm * 120);
        
        // Récupérer le taux km si véhicule spécifié
        $tauxKm = 0.636; // Taux par défaut 5CV
        
        $trajetStmt = $pdo->prepare("
            INSERT INTO trajets (
                user_id,
                vehicule_id,
                demande_id,
                date_trajet,
                adresse_depart,
                adresse_arrivee,
                depart_ville,
                arrivee_ville,
                distance_km,
                montant_remboursement,
                taux_km,
                emission_co2,
                tracking_mode,
                type_trajet
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'calculated', 'professionnel')
        ");
        
        $trajetStmt->execute([
            $userId,
            $vehiculeId,
            $demandeId,
            $dateDepense,
            $depart,
            $arrivee,
            $depart,
            $arrivee,
            $distanceKm,
            $montant,
            $tauxKm,
            $emissionCO2
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'id' => $demandeId,
        'message' => 'Demande créée avec succès'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}