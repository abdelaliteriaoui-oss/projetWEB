<?php
require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

if ($_SESSION['role'] !== 'manager') {
    flashMessage('error', 'Accès non autorisé');
    header('Location: dashboard.php');
    exit;
}

$pageTitle = __('nav_team_report');
$managerId = $_SESSION['user_id'];

$mois = $_GET['mois'] ?? date('m');
$annee = $_GET['annee'] ?? date('Y');

// Stats équipe
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'approuvee_admin' THEN montant_total ELSE 0 END) as approuve,
        SUM(CASE WHEN statut IN ('soumise', 'validee_manager') THEN montant_total ELSE 0 END) as en_attente
    FROM demandes WHERE manager_id = ? AND MONTH(date_depense) = ? AND YEAR(date_depense) = ?
");
$stats->execute([$managerId, $mois, $annee]);
$rapportStats = $stats->fetch();

// Par employé
$parEmploye = $pdo->prepare("
    SELECT u.id, u.nom, u.prenom, COUNT(d.id) as nb_demandes, SUM(d.montant_total) as total
    FROM users u
    LEFT JOIN demandes d ON u.id = d.user_id AND MONTH(d.date_depense) = ? AND YEAR(d.date_depense) = ?
    WHERE u.manager_id = ?
    GROUP BY u.id
    ORDER BY total DESC
");
$parEmploye->execute([$mois, $annee, $managerId]);
$employes = $parEmploye->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h2>Rapport équipe</h2>
        <p>Synthèse des frais de votre équipe</p>
    </div>
</div>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-body">
        <form method="GET" style="display: flex; gap: 16px; align-items: flex-end;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Mois</label>
                <select name="mois" class="form-control">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $mois == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Année</label>
                <select name="annee" class="form-control">
                    <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                    <option value="<?= $y ?>" <?= $annee == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
        </form>
    </div>
</div>

<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= $rapportStats['total'] ?? 0 ?></div>
            <div class="stat-label">Demandes</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #D1FAE5; color: #059669;"><i class="fas fa-check"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= formatMoney($rapportStats['approuve'] ?? 0) ?></div>
            <div class="stat-label">Approuvé</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #FEF3C7; color: #D97706;"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= formatMoney($rapportStats['en_attente'] ?? 0) ?></div>
            <div class="stat-label">En attente</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-users"></i> Par employé</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="table">
            <thead><tr><th>Employé</th><th>Demandes</th><th>Montant total</th></tr></thead>
            <tbody>
                <?php foreach ($employes as $emp): ?>
                <tr>
                    <td style="font-weight: 500;"><?= htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']) ?></td>
                    <td><?= $emp['nb_demandes'] ?? 0 ?></td>
                    <td style="font-weight: 600;"><?= formatMoney($emp['total'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($employes)): ?>
                <tr><td colspan="3" style="text-align: center;">Aucun employé dans votre équipe</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
