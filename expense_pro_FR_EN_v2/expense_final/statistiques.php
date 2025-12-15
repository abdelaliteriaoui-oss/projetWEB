<?php
require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

if ($_SESSION['role'] !== 'manager') {
    flashMessage('error', 'Accès non autorisé');
    header('Location: dashboard.php');
    exit;
}

$pageTitle = __('nav_statistics');
$managerId = $_SESSION['user_id'];

// Stats globales
$totalTeam = $pdo->prepare("SELECT COUNT(*) FROM users WHERE manager_id = ?");
$totalTeam->execute([$managerId]);
$nbEmployes = $totalTeam->fetchColumn();

$totalDemandes = $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE manager_id = ?");
$totalDemandes->execute([$managerId]);
$nbDemandes = $totalDemandes->fetchColumn();

$montantTotal = $pdo->prepare("SELECT COALESCE(SUM(montant_total), 0) FROM demandes WHERE manager_id = ? AND statut = 'approuvee_admin'");
$montantTotal->execute([$managerId]);
$totalApprouve = $montantTotal->fetchColumn();

// Par catégorie
$parCategorie = $pdo->prepare("
    SELECT cf.nom, SUM(df.montant) as total
    FROM details_frais df
    JOIN categories_frais cf ON df.categorie_id = cf.id
    JOIN demandes d ON df.demande_id = d.id
    WHERE d.manager_id = ? AND d.statut = 'approuvee_admin'
    GROUP BY cf.id ORDER BY total DESC
");
$parCategorie->execute([$managerId]);
$categories = $parCategorie->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h2>Statistiques</h2>
        <p>Vue d'ensemble des dépenses de votre équipe</p>
    </div>
</div>

<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);"><i class="fas fa-users"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= $nbEmployes ?></div>
            <div class="stat-label">Employés</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #DBEAFE; color: #2563EB;"><i class="fas fa-file-alt"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= $nbDemandes ?></div>
            <div class="stat-label">Demandes totales</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #D1FAE5; color: #059669;"><i class="fas fa-euro-sign"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= formatMoney($totalApprouve) ?></div>
            <div class="stat-label">Total approuvé</div>
        </div>
    </div>
</div>

<div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-pie"></i> Par catégorie</h3>
        </div>
        <div class="card-body">
            <div class="chart-container" style="height: 300px;">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list"></i> Détail par catégorie</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="table">
                <thead><tr><th>Catégorie</th><th>Montant</th></tr></thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td style="font-weight: 500;"><?= htmlspecialchars($cat['nom']) ?></td>
                        <td style="font-weight: 600;"><?= formatMoney($cat['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                    <tr><td colspan="2" style="text-align: center;">Aucune donnée</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const data = <?= json_encode($categories) ?>;
    if (data.length > 0) {
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: data.map(c => c.nom),
                datasets: [{
                    data: data.map(c => parseFloat(c.total)),
                    backgroundColor: ['#0066FF', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                cutout: '60%'
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
