<?php
/**
 * ExpensePro - Validation Manager
 * Page for managers to validate team expense requests
 */

require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

// Vérifier le rôle manager
if ($_SESSION['role'] !== 'manager') {
    flashMessage('error', 'Accès non autorisé');
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Validation des demandes';
$managerId = $_SESSION['user_id'];

// Récupérer les demandes à valider
try {
    $pendingQuery = $pdo->prepare("
        SELECT d.*, u.nom, u.prenom, u.email, u.photo_profil,
               GROUP_CONCAT(DISTINCT cf.nom SEPARATOR ', ') as categories
        FROM demandes d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN details_frais df ON d.id = df.demande_id
        LEFT JOIN categories_frais cf ON df.categorie_id = cf.id
        WHERE d.manager_id = ? AND d.statut = 'soumise'
        GROUP BY d.id
        ORDER BY d.created_at ASC
    ");
    $pendingQuery->execute([$managerId]);
    $pendingDemandes = $pendingQuery->fetchAll(PDO::FETCH_ASSOC);
    $pendingCount = count($pendingDemandes);
} catch (PDOException $e) {
    $pendingDemandes = [];
    $pendingCount = 0;
    error_log("Erreur lors de la récupération des demandes : " . $e->getMessage());
}

// Statistiques
$stats = ['pending' => $pendingCount];

// Validated today
try {
    $stmtValidated = $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE manager_id = ? AND statut = 'validee_manager' AND DATE(date_validation_manager) = CURDATE()");
    $stmtValidated->execute([$managerId]);
    $stats['validated_today'] = $stmtValidated->fetchColumn();
} catch (PDOException $e) {
    $stats['validated_today'] = 0;
    error_log("Erreur validated_today : " . $e->getMessage());
}

// Rejected today
try {
    $stmtRejected = $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE manager_id = ? AND statut = 'rejetee_manager' AND DATE(date_validation_manager) = CURDATE()");
    $stmtRejected->execute([$managerId]);
    $stats['rejected_today'] = $stmtRejected->fetchColumn();
} catch (PDOException $e) {
    $stats['rejected_today'] = 0;
    error_log("Erreur rejected_today : " . $e->getMessage());
}

// Total month
try {
    $stmtMonth = $pdo->prepare("SELECT COALESCE(SUM(montant_total), 0) FROM demandes WHERE manager_id = ? AND statut IN ('validee_manager', 'approuvee_admin') AND MONTH(date_validation_manager) = MONTH(NOW())");
    $stmtMonth->execute([$managerId]);
    $stats['total_month'] = $stmtMonth->fetchColumn();
} catch (PDOException $e) {
    $stats['total_month'] = 0;
    error_log("Erreur total_month : " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h2>Validation des demandes</h2>
        <p>Demandes de votre équipe en attente de validation</p>
    </div>
</div>

<!-- Statistiques -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: #FEF3C7; color: #D97706;">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['pending'] ?></div>
            <div class="stat-label">En attente</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #D1FAE5; color: #059669;">
            <i class="fas fa-check"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['validated_today'] ?></div>
            <div class="stat-label">Validées aujourd'hui</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #FEE2E2; color: #DC2626;">
            <i class="fas fa-times"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['rejected_today'] ?></div>
            <div class="stat-label">Rejetées aujourd'hui</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
            <i class="fas fa-euro-sign"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= formatMoney($stats['total_month']) ?></div>
            <div class="stat-label">Validé ce mois</div>
        </div>
    </div>
</div>

<!-- Liste des demandes -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-list"></i>
            Demandes en attente (<?= $pendingCount ?>)
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($pendingDemandes)): ?>
        <div class="empty-state">
            <div class="empty-state-icon" style="background: #D1FAE5; color: #059669;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h4 class="empty-state-title">Tout est à jour !</h4>
            <p class="empty-state-text">Aucune demande en attente de validation</p>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employé</th>
                        <th>Réf.</th>
                        <th>Objet</th>
                        <th>Catégories</th>
                        <th>Montant</th>
                        <th>Date dépense</th>
                        <th>Soumise le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingDemandes as $demande): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width:40px;height:40px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:600;font-size:13px;">
                                    <?= strtoupper(substr($demande['prenom'], 0, 1) . substr($demande['nom'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:600;"><?= htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']) ?></div>
                                    <div style="font-size:12px;color:var(--gray-500);"><?= htmlspecialchars($demande['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="font-weight: 600; color: var(--primary);">
                                #<?= str_pad($demande['id'], 5, '0', STR_PAD_LEFT) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($demande['objet']) ?></td>
                        <td>
                            <span style="font-size: 13px; color: var(--gray-500);">
                                <?= htmlspecialchars($demande['categories'] ?? 'N/A') ?>
                            </span>
                        </td>
                        <td style="font-weight: 600; font-size: 16px;"><?= formatMoney($demande['montant_total']) ?></td>
                        <td><?= formatDate($demande['date_depense']) ?></td>
                        <td>
                            <span title="<?= date('d/m/Y H:i', strtotime($demande['created_at'])) ?>">
                                <?= timeAgo($demande['created_at']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="voir_demande.php?id=<?= $demande['id'] ?>" class="btn btn-ghost btn-icon" title="Voir les détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn btn-success btn-icon" onclick="validerDemande(<?= $demande['id'] ?>)" title="Valider">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-danger btn-icon" onclick="rejeterDemande(<?= $demande['id'] ?>)" title="Rejeter">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Validation -->
<div class="modal" id="validationModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Valider la demande</h3>
            <button class="modal-close" onclick="closeModal('validationModal')">&times;</button>
        </div>
        <form action="traiter_demande.php" method="POST">
            <input type="hidden" name="demande_id" id="validation_demande_id">
            <input type="hidden" name="action" value="valider">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Commentaire (optionnel)</label>
                    <textarea name="commentaire" class="form-control" rows="3" placeholder="Ajouter un commentaire..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('validationModal')">Annuler</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Valider
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Rejet -->
<div class="modal" id="rejetModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Rejeter la demande</h3>
            <button class="modal-close" onclick="closeModal('rejetModal')">&times;</button>
        </div>
        <form action="traiter_demande.php" method="POST">
            <input type="hidden" name="demande_id" id="rejet_demande_id">
            <input type="hidden" name="action" value="rejeter">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Motif du rejet <span style="color: var(--danger);">*</span></label>
                    <textarea name="commentaire" class="form-control" rows="3" placeholder="Expliquez la raison du rejet..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('rejetModal')">Annuler</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-times"></i> Rejeter
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function validerDemande(id) {
    document.getElementById('validation_demande_id').value = id;
    document.getElementById('validationModal').classList.add('active');
}

function rejeterDemande(id) {
    document.getElementById('rejet_demande_id').value = id;
    document.getElementById('rejetModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Fermer modal en cliquant dehors
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<style>
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal.active {
    display: flex;
}
.modal-content {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--gray-200);
}
.modal-title {
    font-size: 18px;
    font-weight: 600;
}
.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--gray-500);
}
.modal-body {
    padding: 24px;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 24px;
    border-top: 1px solid var(--gray-200);
}
</style>

<?php include 'includes/footer.php'; ?>