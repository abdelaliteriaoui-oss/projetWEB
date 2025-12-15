<?php
require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    flashMessage('error', 'Accès non autorisé');
    header('Location: dashboard.php');
    exit;
}

$pageTitle = __('nav_reports');

$mois = $_GET['mois'] ?? date('m');
$annee = $_GET['annee'] ?? date('Y');

// Export CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=rapport_' . $annee . '_' . $mois . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($output, ['ID', 'Employé', 'Objet', 'Montant', 'Statut', 'Date'], ';');
    
    $query = $pdo->prepare("SELECT d.*, u.nom, u.prenom FROM demandes d JOIN users u ON d.user_id = u.id WHERE MONTH(d.date_depense) = ? AND YEAR(d.date_depense) = ? ORDER BY d.date_depense");
    $query->execute([$mois, $annee]);
    while ($row = $query->fetch()) {
        fputcsv($output, [$row['id'], $row['prenom'].' '.$row['nom'], $row['objet'], $row['montant_total'], $row['statut'], $row['date_depense']], ';');
    }
    fclose($output);
    exit;
}

// Statistiques
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'approuvee_admin' THEN montant_total ELSE 0 END) as approuve,
        SUM(CASE WHEN statut IN ('rejetee_manager', 'rejetee_admin') THEN montant_total ELSE 0 END) as rejete
    FROM demandes WHERE MONTH(date_depense) = ? AND YEAR(date_depense) = ?
");
$stats->execute([$mois, $annee]);
$rapportStats = $stats->fetch();

// Demandes du mois
$demandesQuery = $pdo->prepare("
    SELECT d.*, u.nom, u.prenom 
    FROM demandes d JOIN users u ON d.user_id = u.id 
    WHERE MONTH(d.date_depense) = ? AND YEAR(d.date_depense) = ?
    ORDER BY d.date_depense DESC
");
$demandesQuery->execute([$mois, $annee]);
$demandes = $demandesQuery->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h2>Rapports</h2>
        <p>Générez et exportez vos rapports de frais</p>
    </div>
</div>

<!-- Filtres -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body">
        <form method="GET" style="display: flex; gap: 16px; align-items: flex-end;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Mois</label>
                <select name="mois" class="form-control">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $mois == $m ? 'selected' : '' ?>>
                        <?= strftime('%B', mktime(0, 0, 0, $m, 1)) ?>
                    </option>
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
            <a href="rapports.php?mois=<?= $mois ?>&annee=<?= $annee ?>&export=1" class="btn btn-success"><i class="fas fa-download"></i> Exporter CSV</a>
        </form>
    </div>
</div>

<!-- Stats -->
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
            <div class="stat-label">Montant approuvé</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #FEE2E2; color: #DC2626;"><i class="fas fa-times"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= formatMoney($rapportStats['rejete'] ?? 0) ?></div>
            <div class="stat-label">Montant rejeté</div>
        </div>
    </div>
</div>

<!-- Liste -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list"></i> Demandes du mois</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($demandes)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-folder-open"></i></div>
            <h4>Aucune demande</h4>
            <p>Pas de demande pour cette période</p>
        </div>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Réf.</th><th>Employé</th><th>Objet</th><th>Montant</th><th>Date</th><th>Statut</th></tr></thead>
            <tbody>
                <?php foreach ($demandes as $d): ?>
                <tr>
                    <td><a href="voir_demande.php?id=<?= $d['id'] ?>" style="font-weight: 600; color: var(--primary);">#<?= str_pad($d['id'], 5, '0', STR_PAD_LEFT) ?></a></td>
                    <td><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></td>
                    <td><?= htmlspecialchars($d['objet']) ?></td>
                    <td style="font-weight: 600;"><?= formatMoney($d['montant_total']) ?></td>
                    <td><?= formatDate($d['date_depense']) ?></td>
                    <td>
                        <?php
                        $statusConfig = ['brouillon' => 'secondary', 'soumise' => 'warning', 'validee_manager' => 'info', 'approuvee_admin' => 'success', 'rejetee_manager' => 'danger', 'rejetee_admin' => 'danger'];
                        ?>
                        <span class="badge badge-<?= $statusConfig[$d['statut']] ?? 'secondary' ?>"><?= $d['statut'] ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
