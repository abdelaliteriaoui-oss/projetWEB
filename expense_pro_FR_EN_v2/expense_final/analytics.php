<?php
/**
 * ExpensePro - Analytics Dashboard
 * Advanced analytics and statistics for admins
 */

require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    flashMessage('error', 'Accès non autorisé');
    header('Location: dashboard.php');
    exit;
}

$pageTitle = __('nav_analytics');

// Période sélectionnée
$periode = $_GET['periode'] ?? 'mois';
$annee = $_GET['annee'] ?? date('Y');

// Statistiques globales
$globalStats = [
    'total_demandes' => $pdo->query("SELECT COUNT(*) FROM demandes WHERE YEAR(created_at) = $annee")->fetchColumn(),
    'montant_total' => $pdo->query("SELECT COALESCE(SUM(montant_total), 0) FROM demandes WHERE statut = 'approuvee_admin' AND YEAR(created_at) = $annee")->fetchColumn(),
    'taux_approbation' => 0,
    'delai_moyen' => $pdo->query("SELECT AVG(DATEDIFF(date_validation_admin, created_at)) FROM demandes WHERE statut = 'approuvee_admin' AND YEAR(created_at) = $annee")->fetchColumn()
];

// Calcul du taux d'approbation
$approved = $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut = 'approuvee_admin' AND YEAR(created_at) = $annee")->fetchColumn();
$total = $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut NOT IN ('brouillon', 'soumise', 'validee_manager') AND YEAR(created_at) = $annee")->fetchColumn();
$globalStats['taux_approbation'] = $total > 0 ? round(($approved / $total) * 100) : 0;

// Évolution mensuelle
$monthlyQuery = $pdo->query("
    SELECT 
        MONTH(created_at) as mois,
        COUNT(*) as nb_demandes,
        SUM(CASE WHEN statut = 'approuvee_admin' THEN montant_total ELSE 0 END) as montant_approuve
    FROM demandes 
    WHERE YEAR(created_at) = $annee
    GROUP BY MONTH(created_at)
    ORDER BY mois
");
$monthlyData = $monthlyQuery->fetchAll();

// Par catégorie
$categoryQuery = $pdo->query("
    SELECT cf.nom, SUM(df.montant) as total, COUNT(DISTINCT d.id) as nb
    FROM details_frais df
    JOIN categories_frais cf ON df.categorie_id = cf.id
    JOIN demandes d ON df.demande_id = d.id
    WHERE d.statut = 'approuvee_admin' AND YEAR(d.created_at) = $annee
    GROUP BY cf.id
    ORDER BY total DESC
");
$categoryData = $categoryQuery->fetchAll();

// Par département/manager
$deptQuery = $pdo->query("
    SELECT m.nom, m.prenom, COUNT(d.id) as nb_demandes, SUM(d.montant_total) as total
    FROM demandes d
    JOIN users m ON d.manager_id = m.id
    WHERE d.statut = 'approuvee_admin' AND YEAR(d.created_at) = $annee
    GROUP BY d.manager_id
    ORDER BY total DESC
    LIMIT 10
");
$deptData = $deptQuery->fetchAll();

// Top employés
$topEmployees = $pdo->query("
    SELECT u.nom, u.prenom, COUNT(d.id) as nb_demandes, SUM(d.montant_total) as total
    FROM demandes d
    JOIN users u ON d.user_id = u.id
    WHERE d.statut = 'approuvee_admin' AND YEAR(d.created_at) = $annee
    GROUP BY d.user_id
    ORDER BY total DESC
    LIMIT 5
")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h2>Analytics</h2>
        <p>Tableaux de bord et analyses avancées</p>
    </div>
    <div class="page-header-actions">
        <select class="form-control" onchange="window.location='?annee='+this.value" style="width: auto;">
            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
            <option value="<?= $y ?>" <?= $annee == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
</div>

<!-- KPIs -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($globalStats['total_demandes']) ?></div>
            <div class="stat-label">Demandes totales</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #D1FAE5; color: #059669;">
           <span style="font-weight:700;">د.م</span>



        </div>
        <div class="stat-content">
            <div class="stat-value"><?= formatMoney($globalStats['montant_total']) ?></div>
            <div class="stat-label">Montant approuvé</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #DBEAFE; color: #2563EB;">
            <i class="fas fa-percentage"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $globalStats['taux_approbation'] ?>%</div>
            <div class="stat-label">Taux d'approbation</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #FEF3C7; color: #D97706;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= round($globalStats['delai_moyen'] ?? 0, 1) ?> j</div>
            <div class="stat-label">Délai moyen</div>
        </div>
    </div>
</div>

<div class="row" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Graphique évolution -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-line"></i>
                Évolution mensuelle
            </h3>
        </div>
        <div class="card-body">
            <div class="chart-container" style="height: 300px;">
                <canvas id="evolutionChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Répartition par catégorie -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-pie"></i>
                Par catégorie
            </h3>
        </div>
        <div class="card-body">
            <div class="chart-container" style="height: 300px;">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Par équipe/manager -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-users"></i>
                Par équipe (Manager)
            </h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Manager</th>
                            <th>Demandes</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deptData as $dept): ?>
                        <tr>
                            <td style="font-weight: 500;"><?= htmlspecialchars($dept['prenom'] . ' ' . $dept['nom']) ?></td>
                            <td><?= $dept['nb_demandes'] ?></td>
                            <td style="font-weight: 600;"><?= formatMoney($dept['total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($deptData)): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--gray-500);">Aucune donnée</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Top employés -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-trophy"></i>
                Top 5 Employés
            </h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employé</th>
                            <th>Demandes</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topEmployees as $i => $emp): ?>
                        <tr>
                            <td>
                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: <?= $i === 0 ? '#FEF3C7' : ($i === 1 ? '#E5E7EB' : ($i === 2 ? '#FED7AA' : 'var(--gray-100)')) ?>; font-size: 12px; font-weight: 600;">
                                    <?= $i + 1 ?>
                                </span>
                            </td>
                            <td style="font-weight: 500;"><?= htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']) ?></td>
                            <td><?= $emp['nb_demandes'] ?></td>
                            <td style="font-weight: 600;"><?= formatMoney($emp['total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topEmployees)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--gray-500);">Aucune donnée</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Données pour le graphique d'évolution
    const monthlyData = <?= json_encode($monthlyData) ?>;
    const months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
    
    const evolutionData = new Array(12).fill(0);
    const amountData = new Array(12).fill(0);
    monthlyData.forEach(m => {
        evolutionData[m.mois - 1] = parseInt(m.nb_demandes);
        amountData[m.mois - 1] = parseFloat(m.montant_approuve);
    });
    
    new Chart(document.getElementById('evolutionChart'), {
        type: 'bar',
        data: {
            labels: months,
            datasets: [{
                label: 'Nombre de demandes',
                data: evolutionData,
                backgroundColor: 'rgba(0, 102, 255, 0.8)',
                borderRadius: 6,
                yAxisID: 'y'
            }, {
                label: 'Montant approuvé (DH)',
                data: amountData,
                type: 'line',
                borderColor: '#10B981',
                backgroundColor: 'transparent',
                tension: 0.4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                y: {
                    type: 'linear',
                    position: 'left',
                    title: { display: true, text: 'Demandes' }
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    title: { display: true, text: 'Montant (DH)' },
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
    
    // Graphique par catégorie
    const categoryData = <?= json_encode($categoryData) ?>;
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: categoryData.map(c => c.nom),
            datasets: [{
                data: categoryData.map(c => parseFloat(c.total)),
                backgroundColor: ['#0066FF', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#EC4899'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            cutout: '60%'
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
