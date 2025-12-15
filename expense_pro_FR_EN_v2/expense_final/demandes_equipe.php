<?php
require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

if ($_SESSION['role'] !== 'manager') {
    flashMessage('error', 'Accès non autorisé');
    header('Location: dashboard.php');
    exit;
}

$pageTitle = __('nav_team_requests');
$managerId = $_SESSION['user_id'];

$statut = $_GET['statut'] ?? '';
$where = "d.manager_id = ?";
$params = [$managerId];

if ($statut) {
    $where .= " AND d.statut = ?";
    $params[] = $statut;
}

$demandes = $pdo->prepare("
    SELECT d.*, u.nom, u.prenom, u.email
    FROM demandes d
    JOIN users u ON d.user_id = u.id
    WHERE $where
    ORDER BY d.created_at DESC
");
$demandes->execute($params);
$demandesList = $demandes->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h2>Demandes de mon équipe</h2>
        <p>Toutes les demandes de remboursement de votre équipe</p>
    </div>
</div>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-body">
        <form method="GET" style="display: flex; gap: 16px; align-items: flex-end;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-control">
                    <option value="">Tous</option>
                    <option value="soumise" <?= $statut === 'soumise' ? 'selected' : '' ?>>Soumise</option>
                    <option value="validee_manager" <?= $statut === 'validee_manager' ? 'selected' : '' ?>>Validée</option>
                    <option value="approuvee_admin" <?= $statut === 'approuvee_admin' ? 'selected' : '' ?>>Approuvée</option>
                    <option value="rejetee_manager" <?= $statut === 'rejetee_manager' ? 'selected' : '' ?>>Rejetée</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="demandes_equipe.php" class="btn btn-ghost">Réinitialiser</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list"></i> <?= count($demandesList) ?> demande(s)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($demandesList)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-folder-open"></i></div>
            <h4>Aucune demande</h4>
        </div>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Employé</th><th>Réf.</th><th>Objet</th><th>Montant</th><th>Date</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($demandesList as $d): ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width:36px;height:36px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:600;font-size:12px;">
                                <?= strtoupper(substr($d['prenom'], 0, 1) . substr($d['nom'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;"><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></div>
                                <div style="font-size:12px;color:var(--gray-500);"><?= htmlspecialchars($d['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span style="font-weight: 600; color: var(--primary);">#<?= str_pad($d['id'], 5, '0', STR_PAD_LEFT) ?></span></td>
                    <td><?= htmlspecialchars($d['objet']) ?></td>
                    <td style="font-weight: 600;"><?= formatMoney($d['montant_total']) ?></td>
                    <td><?= formatDate($d['date_depense']) ?></td>
                    <td>
                        <?php
                        $statusConfig = ['brouillon' => 'secondary', 'soumise' => 'warning', 'validee_manager' => 'info', 'approuvee_admin' => 'success', 'rejetee_manager' => 'danger', 'rejetee_admin' => 'danger'];
                        ?>
                        <span class="badge badge-<?= $statusConfig[$d['statut']] ?? 'secondary' ?>"><?= $d['statut'] ?></span>
                    </td>
                    <td>
                        <a href="voir_demande.php?id=<?= $d['id'] ?>" class="btn btn-ghost btn-icon"><i class="fas fa-eye"></i></a>
                        <?php if ($d['statut'] === 'soumise'): ?>
                        <a href="traiter_demande.php?action=valider&id=<?= $d['id'] ?>" class="btn btn-success btn-icon" onclick="return confirm('Valider cette demande ?')"><i class="fas fa-check"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
