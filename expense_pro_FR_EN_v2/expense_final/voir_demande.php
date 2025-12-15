<?php
/**
 * ExpensePro - Voir Demande
 * Detailed view of an expense request with timeline
 */

require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

$demandeId = intval($_GET['id'] ?? 0);

$query = $pdo->prepare("
    SELECT d.*, 
           u.nom as user_nom, u.prenom as user_prenom, u.email as user_email,
           m.nom as manager_nom, m.prenom as manager_prenom
    FROM demandes d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN users m ON d.manager_id = m.id
    WHERE d.id = ?
");
$query->execute([$demandeId]);
$demande = $query->fetch();

if (!$demande) {
    flashMessage('error', 'Demande introuvable');
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
if ($userRole === 'employe' && $demande['user_id'] !== $userId) {
    flashMessage('error', 'Accès non autorisé');
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Demande #' . $demandeId;

$detailsQuery = $pdo->prepare("
    SELECT df.*, cf.nom as categorie_nom
    FROM details_frais df
    JOIN categories_frais cf ON df.categorie_id = cf.id
    WHERE df.demande_id = ?
");
$detailsQuery->execute([$demandeId]);
$details = $detailsQuery->fetchAll();

$historyQuery = $pdo->prepare("
    SELECT h.*, u.nom, u.prenom
    FROM historique_demandes h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE h.demande_id = ?
    ORDER BY h.created_at ASC
");
$historyQuery->execute([$demandeId]);
$history = $historyQuery->fetchAll();

include 'includes/header.php';
?>

<div style="display: flex; gap: 12px; margin-bottom: 24px;">
    <a href="javascript:history.back()" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Retour
    </a>
    
    <?php if ($demande['statut'] === 'brouillon' && $demande['user_id'] === $userId): ?>
    <a href="modifier_demande.php?id=<?= $demandeId ?>" class="btn btn-primary">
        <i class="fas fa-edit"></i> Modifier
    </a>
    <?php endif; ?>
    
    <?php if ($userRole === 'manager' && $demande['statut'] === 'soumise' && $demande['manager_id'] === $userId): ?>
    <button onclick="showValidationModal('valider')" class="btn btn-success">
        <i class="fas fa-check"></i> Valider
    </button>
    <button onclick="showValidationModal('rejeter')" class="btn btn-danger">
        <i class="fas fa-times"></i> Rejeter
    </button>
    <?php endif; ?>
    
    <?php if ($userRole === 'admin' && $demande['statut'] === 'validee_manager'): ?>
    <button onclick="showValidationModal('approuver')" class="btn btn-success">
        <i class="fas fa-stamp"></i> Approuver
    </button>
    <button onclick="showValidationModal('rejeter')" class="btn btn-danger">
        <i class="fas fa-times"></i> Rejeter
    </button>
    <?php endif; ?>
    
    <button onclick="window.print()" class="btn btn-outline" style="margin-left: auto;">
        <i class="fas fa-print"></i> Imprimer
    </button>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <div>
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <div>
                    <h3 class="card-title" style="margin-bottom: 4px;">
                        <?= htmlspecialchars($demande['objet']) ?>
                    </h3>
                    <span style="color: var(--gray-500); font-size: 13px;">
                        Demande #<?= $demandeId ?> • Créée le <?= formatDate($demande['created_at'], 'd/m/Y à H:i') ?>
                    </span>
                </div>
                <?= getStatusBadge($demande['statut']) ?>
            </div>
            
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 24px;">
                    <div>
                        <div style="font-size: 12px; color: var(--gray-500); text-transform: uppercase; margin-bottom: 4px;">Demandeur</div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width:40px;height:40px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:600;">
                                <?= strtoupper(substr($demande['user_prenom'], 0, 1) . substr($demande['user_nom'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight: 600;"><?= htmlspecialchars($demande['user_prenom'] . ' ' . $demande['user_nom']) ?></div>
                                <div style="font-size: 12px; color: var(--gray-500);"><?= htmlspecialchars($demande['user_email']) ?></div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: var(--gray-500); text-transform: uppercase; margin-bottom: 4px;">Lieu</div>
                        <div style="font-size: 16px; font-weight: 500;">
                            <i class="fas fa-map-marker-alt" style="color: var(--primary); margin-right: 8px;"></i>
                            <?= htmlspecialchars($demande['lieu']) ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: var(--gray-500); text-transform: uppercase; margin-bottom: 4px;">Date mission</div>
                        <div style="font-size: 16px; font-weight: 500;">
                            <i class="fas fa-calendar" style="color: var(--primary); margin-right: 8px;"></i>
                            <?= formatDate($demande['date_depense']) ?>
                        </div>
                    </div>
                </div>
                
                <div style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius: var(--radius-md); padding: 24px; color: white; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 14px; opacity: 0.8;">Montant total</div>
                        <div style="font-size: 36px; font-weight: 700;"><?= formatMoney($demande['montant_total']) ?></div>
                    </div>
                    <div style="font-size: 48px; opacity: 0.3;"><i class="fas fa-receipt"></i></div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list-ul"></i> Détails des dépenses</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Catégorie</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Justificatif</th>
                            <th style="text-align: right;">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($details as $detail): ?>
                        <tr>
                            <td>
                                <span style="display: inline-flex; align-items: center; gap: 8px;">
                                    <span style="width:32px;height:32px;border-radius:var(--radius);background:var(--primary-light);display:flex;align-items:center;justify-content:center;color:var(--primary);">
                                        <i class="fas <?= getCategoryIcon($detail['categorie_nom']) ?>"></i>
                                    </span>
                                    <?= htmlspecialchars($detail['categorie_nom']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($detail['description']) ?></td>
                            <td><?= formatDate($detail['date_depense']) ?></td>
                            <td>
                                <?php if ($detail['justificatif']): ?>
                                <a href="<?= htmlspecialchars($detail['justificatif']) ?>" target="_blank" class="btn btn-ghost btn-sm">
                                    <i class="fas fa-eye"></i> Voir
                                </a>
                                <?php else: ?>
                                <span style="color: var(--gray-400);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-weight: 600;"><?= formatMoney($detail['montant']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--gray-50);">
                            <td colspan="4" style="text-align: right; font-weight: 600;">Total</td>
                            <td style="text-align: right; font-weight: 700; font-size: 18px; color: var(--primary);">
                                <?= formatMoney($demande['montant_total']) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <div>
        <?php if ($demande['commentaire_manager'] || $demande['commentaire_admin']): ?>
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-comments"></i> Commentaires</h3>
            </div>
            <div class="card-body">
                <?php if ($demande['commentaire_manager']): ?>
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 12px; color: var(--gray-500); margin-bottom: 4px;">Manager</div>
                    <div style="background: var(--gray-50); padding: 12px; border-radius: var(--radius);">
                        <?= nl2br(htmlspecialchars($demande['commentaire_manager'])) ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($demande['commentaire_admin']): ?>
                <div>
                    <div style="font-size: 12px; color: var(--gray-500); margin-bottom: 4px;">Administration</div>
                    <div style="background: var(--gray-50); padding: 12px; border-radius: var(--radius);">
                        <?= nl2br(htmlspecialchars($demande['commentaire_admin'])) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"></i> Historique</h3>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($history as $event): 
                        $dotClass = '';
                        if (strpos($event['statut'], 'approuvee') !== false || strpos($event['statut'], 'validee') !== false) {
                            $dotClass = 'success';
                        } elseif (strpos($event['statut'], 'rejetee') !== false) {
                            $dotClass = 'danger';
                        } elseif ($event['statut'] === 'soumise') {
                            $dotClass = 'warning';
                        }
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?= $dotClass ?>"></div>
                        <div class="timeline-content">
                            <div class="timeline-date"><?= formatDate($event['created_at'], 'd/m/Y H:i') ?></div>
                            <div class="timeline-title"><?= STATUS_LABELS[$event['statut']] ?? ucfirst($event['statut']) ?></div>
                            <?php if ($event['commentaire']): ?>
                            <div class="timeline-text"><?= htmlspecialchars($event['commentaire']) ?></div>
                            <?php endif; ?>
                            <?php if ($event['nom']): ?>
                            <div style="font-size: 12px; color: var(--gray-400); margin-top: 4px;">
                                Par <?= htmlspecialchars($event['prenom'] . ' ' . $event['nom']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="validation-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-title">Valider la demande</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="traiter_demande.php">
            <input type="hidden" name="demande_id" value="<?= $demandeId ?>">
            <input type="hidden" name="action" id="modal-action" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Commentaire (optionnel)</label>
                    <textarea name="commentaire" class="form-control" rows="4" placeholder="Ajoutez un commentaire..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                <button type="submit" class="btn" id="modal-submit-btn">Confirmer</button>
            </div>
        </form>
    </div>
</div>

<script>
function showValidationModal(action) {
    const modal = document.getElementById('validation-modal');
    const title = document.getElementById('modal-title');
    const actionInput = document.getElementById('modal-action');
    const submitBtn = document.getElementById('modal-submit-btn');
    
    actionInput.value = action;
    
    if (action === 'valider' || action === 'approuver') {
        title.textContent = action === 'valider' ? 'Valider la demande' : 'Approuver la demande';
        submitBtn.className = 'btn btn-success';
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Confirmer';
    } else {
        title.textContent = 'Rejeter la demande';
        submitBtn.className = 'btn btn-danger';
        submitBtn.innerHTML = '<i class="fas fa-times"></i> Rejeter';
    }
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('validation-modal').classList.remove('active');
}
</script>

<?php include 'includes/footer.php'; ?>
