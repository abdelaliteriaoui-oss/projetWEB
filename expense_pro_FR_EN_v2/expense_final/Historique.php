<?php
require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

$pageTitle = __('nav_history');
$userId = $_SESSION['user_id'];

$statut = $_GET['statut'] ?? '';
$annee = $_GET['annee'] ?? date('Y');

$where = ["d.user_id = ?", "YEAR(d.date_depense) = ?"];
$params = [$userId, $annee];

if ($statut) {
    $where[] = "d.statut = ?";
    $params[] = $statut;
}

$whereClause = implode(' AND ', $where);

$demandes = $pdo->prepare("
    SELECT d.*, GROUP_CONCAT(DISTINCT cf.nom SEPARATOR ', ') as categories
    FROM demandes d
    LEFT JOIN details_frais df ON d.id = df.demande_id
    LEFT JOIN categories_frais cf ON df.categorie_id = cf.id
    WHERE $whereClause
    GROUP BY d.id
    ORDER BY d.date_depense DESC
");
$demandes->execute($params);
$demandesList = $demandes->fetchAll();

// Stats
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'approuvee_admin' THEN montant_total ELSE 0 END) as approuve,
        SUM(CASE WHEN statut IN ('soumise', 'validee_manager') THEN montant_total ELSE 0 END) as en_attente
    FROM demandes WHERE user_id = ? AND YEAR(date_depense) = ?
");
$stats->execute([$userId, $annee]);
$rapportStats = $stats->fetch();

// Années disponibles
$years = $pdo->prepare("SELECT DISTINCT YEAR(date_depense) as year FROM demandes WHERE user_id = ? ORDER BY year DESC");
$years->execute([$userId]);
$yearsAvailable = $years->fetchAll(PDO::FETCH_COLUMN);
if (empty($yearsAvailable)) $yearsAvailable = [date('Y')];

include 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h2>Historique des demandes</h2>
        <p>Consultez l'ensemble de vos demandes</p>
    </div>
</div>

<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= $rapportStats['total'] ?? 0 ?></div>
            <div class="stat-label">Demandes en <?= $annee ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #D1FAE5; color: #059669;"><i class="fas fa-check-circle"></i></div>
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

<div class="card" style="margin-bottom: 24px;">
    <div class="card-body">
        <form method="GET" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Année</label>
                <select name="annee" class="form-control">
                    <?php foreach ($yearsAvailable as $y): ?>
                    <option value="<?= $y ?>" <?= $annee == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-control">
                    <option value="">Tous</option>
                    <option value="brouillon" <?= $statut === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                    <option value="soumise" <?= $statut === 'soumise' ? 'selected' : '' ?>>Soumise</option>
                    <option value="validee_manager" <?= $statut === 'validee_manager' ? 'selected' : '' ?>>Validée</option>
                    <option value="approuvee_admin" <?= $statut === 'approuvee_admin' ? 'selected' : '' ?>>Approuvée</option>
                    <option value="rejetee_manager" <?= $statut === 'rejetee_manager' ? 'selected' : '' ?>>Rejetée</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="historique.php" class="btn btn-ghost">Réinitialiser</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history"></i> <?= count($demandesList) ?> demande(s)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($demandesList)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-history"></i></div>
            <h4>Aucune demande</h4>
            <p>Pas de demande pour cette période</p>
            <a href="nouvelle_demande.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvelle demande</a>
        </div>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Réf.</th><th>Date</th><th>Objet</th><th>Catégories</th><th>Montant</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($demandesList as $d): ?>
                <tr>
                    <td><span style="font-weight: 600; color: var(--primary);">#<?= str_pad($d['id'], 5, '0', STR_PAD_LEFT) ?></span></td>
                    <td><?= formatDate($d['date_depense']) ?></td>
                    <td style="font-weight: 500;"><?= htmlspecialchars($d['objet']) ?></td>
                    <td style="font-size: 13px; color: var(--gray-500);"><?= htmlspecialchars($d['categories'] ?? 'N/A') ?></td>
                    <td style="font-weight: 600;"><?= formatMoney($d['montant_total']) ?></td>
                    <td>
                        <?php
                        $statusConfig = ['brouillon' => ['secondary', 'Brouillon'], 'soumise' => ['warning', 'Soumise'], 'validee_manager' => ['info', 'Validée'], 'approuvee_admin' => ['success', 'Approuvée'], 'rejetee_manager' => ['danger', 'Rejetée'], 'rejetee_admin' => ['danger', 'Rejetée']];
                        $s = $statusConfig[$d['statut']] ?? ['secondary', $d['statut']];
                        ?>
                        <span class="badge badge-<?= $s[0] ?>"><?= $s[1] ?></span>
                    </td>
                    <td><a href="voir_demande.php?id=<?= $d['id'] ?>" class="btn btn-ghost btn-icon"><i class="fas fa-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
