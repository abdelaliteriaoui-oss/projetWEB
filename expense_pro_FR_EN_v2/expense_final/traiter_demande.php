<?php
/**
 * ExpensePro - Traiter Demande
 * Handle validation, approval and rejection of expense requests
 */

require_once 'includes/config.php';
requireLogin();

$demandeId = intval($_POST['demande_id'] ?? $_GET['id'] ?? 0);
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$commentaire = sanitize($_POST['commentaire'] ?? $_GET['motif'] ?? '');

if (!$demandeId || !$action) {
    flashMessage('error', 'Paramètres manquants');
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Get the request
$query = $pdo->prepare("SELECT * FROM demandes WHERE id = ?");
$query->execute([$demandeId]);
$demande = $query->fetch();

if (!$demande) {
    flashMessage('error', 'Demande introuvable');
    header('Location: dashboard.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    $newStatus = '';
    $notifMessage = '';
    $notifUserId = $demande['user_id'];
    
    switch ($action) {
        case 'valider':
            // Manager validates
            if ($userRole !== 'manager' || $demande['manager_id'] !== $userId) {
                throw new Exception('Accès non autorisé');
            }
            if ($demande['statut'] !== 'soumise') {
                throw new Exception('Cette demande ne peut pas être validée');
            }
            
            $newStatus = 'validee_manager';
            $stmt = $pdo->prepare("UPDATE demandes SET statut = ?, commentaire_manager = ?, date_validation_manager = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $commentaire, $demandeId]);
            
            $notifMessage = "Bonne nouvelle ! Votre demande #{$demandeId} a été VALIDÉE par votre manager pour un montant de " . formatMoney($demande['montant_total']);
            
            // Notify admin
            $adminNotif = $pdo->prepare("INSERT INTO notifications (user_id, demande_id, message) SELECT id, ?, ? FROM users WHERE role = 'admin'");
            $adminNotif->execute([$demandeId, "Nouvelle demande à approuver : #{$demandeId} de {$_SESSION['prenom']} {$_SESSION['nom']} - Montant: " . formatMoney($demande['montant_total'])]);
            break;
            
        case 'approuver':
            // Admin approves
            if ($userRole !== 'admin') {
                throw new Exception('Accès non autorisé');
            }
            if ($demande['statut'] !== 'validee_manager') {
                throw new Exception('Cette demande ne peut pas être approuvée');
            }
            
            $newStatus = 'approuvee_admin';
            $stmt = $pdo->prepare("UPDATE demandes SET statut = ?, commentaire_admin = ?, date_validation_admin = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $commentaire, $demandeId]);
            
            $notifMessage = "Excellente nouvelle ! Votre demande #{$demandeId} a été APPROUVÉE par l'administration pour un montant de " . formatMoney($demande['montant_total']);
            break;
            
        case 'rejeter':
            // Manager or Admin rejects
            if ($userRole === 'manager' && $demande['manager_id'] === $userId && $demande['statut'] === 'soumise') {
                $newStatus = 'rejetee_manager';
                $stmt = $pdo->prepare("UPDATE demandes SET statut = ?, commentaire_manager = ?, date_validation_manager = NOW() WHERE id = ?");
                $stmt->execute([$newStatus, $commentaire, $demandeId]);
                $notifMessage = "Votre demande #{$demandeId} a été REJETÉE par votre manager. Motif : " . ($commentaire ?: 'Non spécifié');
                
            } elseif ($userRole === 'admin' && $demande['statut'] === 'validee_manager') {
                $newStatus = 'rejetee_admin';
                $stmt = $pdo->prepare("UPDATE demandes SET statut = ?, commentaire_admin = ?, date_validation_admin = NOW() WHERE id = ?");
                $stmt->execute([$newStatus, $commentaire, $demandeId]);
                $notifMessage = "Votre demande #{$demandeId} a été REJETÉE par l'administration. Motif : " . ($commentaire ?: 'Non spécifié');
                
            } else {
                throw new Exception('Accès non autorisé');
            }
            break;
            
        default:
            throw new Exception('Action non reconnue');
    }
    
    // Add history entry
    $histStmt = $pdo->prepare("INSERT INTO historique_demandes (demande_id, statut, commentaire, user_id) VALUES (?, ?, ?, ?)");
    $histStmt->execute([$demandeId, $newStatus, $commentaire, $userId]);
    
    // Send notification to employee
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, demande_id, message) VALUES (?, ?, ?)");
    $notifStmt->execute([$notifUserId, $demandeId, $notifMessage]);
    
    $pdo->commit();
    
    $actionLabels = [
        'valider' => 'validée',
        'approuver' => 'approuvée',
        'rejeter' => 'rejetée'
    ];
    flashMessage('success', 'Demande ' . ($actionLabels[$action] ?? 'traitée') . ' avec succès');
    
} catch (Exception $e) {
    $pdo->rollBack();
    flashMessage('error', $e->getMessage());
}

// Redirect back
$redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: $redirectUrl");
exit;
