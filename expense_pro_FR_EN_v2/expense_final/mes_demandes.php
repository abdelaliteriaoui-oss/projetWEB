<?php
/**
 * ExpensePro - Mes Demandes
 * Employee expense requests list with filters
 */

require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

$pageTitle = __('nav_my_requests');
$userId = $_SESSION['user_id'];

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterMonth = $_GET['month'] ?? '';
$filterSearch = $_GET['search'] ?? '';

// Build query
$where = ["d.user_id = ?"];
$params = [$userId];

if ($filterStatus) {
    $where[] = "d.statut = ?";
    $params[] = $filterStatus;
}

if ($filterMonth) {
    $where[] = "DATE_FORMAT(d.date_depense, '%Y-%m') = ?";
    $params[] = $filterMonth;
}

if ($filterSearch) {
    $where[] = "(d.objet LIKE ? OR d.lieu LIKE ?)";
    $params[] = "%$filterSearch%";
    $params[] = "%$filterSearch%";
}

$whereClause = implode(' AND ', $where);

// Get requests with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$countQuery = $pdo->prepare("SELECT COUNT(*) FROM demandes d WHERE $whereClause");
$countQuery->execute($params);
$totalItems = $countQuery->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

$query = $pdo->prepare("
    SELECT d.*, 
           GROUP_CONCAT(DISTINCT cf.nom SEPARATOR ', ') as categories,
           COUNT(df.id) as nb_depenses
    FROM demandes d
    LEFT JOIN details_frais df ON d.id = df.demande_id
    LEFT JOIN categories_frais cf ON df.categorie_id = cf.id
    WHERE $whereClause
    GROUP BY d.id
    ORDER BY d.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$query->execute($params);
$demandes = $query->fetchAll();

// Stats
$statsQuery = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut IN ('soumise', 'validee_manager') THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN statut = 'approuvee_admin' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN statut IN ('rejetee_manager', 'rejetee_admin') THEN 1 ELSE 0 END) as rejected,
        COALESCE(SUM(CASE WHEN statut = 'approuvee_admin' THEN montant_total ELSE 0 END), 0) as total_approved
    FROM demandes
    WHERE user_id = ?
");
$statsQuery->execute([$userId]);
$stats = $statsQuery->fetch();

include 'includes/header.php';
?>

<!-- Stats Bar -->
<div class="stats-grid" style="grid-template-columns: repeat(5, 1fr); margin-bottom: 24px;">
    <div class="stat-card" style="padding: 16px;">
        <div class="stat-value" style="font-size: 24px;"><?= $stats['total'] ?></div>
        <div class="stat-label">Total</div>
    </div>
    <div class="stat-card warning" style="padding: 16px;">
        <div class="stat-value" style="font-size: 24px;"><?= $stats['pending'] ?></div>
        <div class="stat-label">En attente</div>
    </div>
    <div class="stat-card success" style="padding: 16px;">
        <div class="stat-value" style="font-size: 24px;"><?= $stats['approved'] ?></div>
        <div class="stat-label">Approuvées</div>
    </div>
    <div class="stat-card danger" style="padding: 16px;">
        <div class="stat-value" style="font-size: 24px;"><?= $stats['rejected'] ?></div>
        <div class="stat-label">Rejetées</div>
    </div>
    <div class="stat-card" style="padding: 16px;">
        <div class="stat-value" style="font-size: 20px;"><?= formatMoney($stats['total_approved']) ?></div>
        <div class="stat-label">Remboursé</div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 16px;">
        <h3 class="card-title">
            <i class="fas fa-list"></i>
            Mes demandes de frais
        </h3>
        
        <form method="GET" style="display: flex; gap: 12px; flex: 1; justify-content: flex-end; flex-wrap: wrap;">
            <input type="text" name="search" class="form-control" placeholder="Rechercher..." value="<?= htmlspecialchars($filterSearch) ?>" style="width: 200px;">
            
            <select name="status" class="form-control form-select" style="width: 160px;">
                <option value="">Tous les statuts</option>
                <option value="brouillon" <?= $filterStatus === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                <option value="soumise" <?= $filterStatus === 'soumise' ? 'selected' : '' ?>>En attente</option>
                <option value="validee_manager" <?= $filterStatus === 'validee_manager' ? 'selected' : '' ?>>Validée Manager</option>
                <option value="approuvee_admin" <?= $filterStatus === 'approuvee_admin' ? 'selected' : '' ?>>Approuvée</option>
                <option value="rejetee_manager" <?= $filterStatus === 'rejetee_manager' ? 'selected' : '' ?>>Rejetée</option>
            </select>
            
            <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($filterMonth) ?>" style="width: 160px;">
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filtrer
            </button>
            
            <?php if ($filterStatus || $filterMonth || $filterSearch): ?>
            <a href="mes_demandes.php" class="btn btn-secondary">
                <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <?php if (empty($demandes)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-file-invoice"></i>
            </div>
            <h4 class="empty-state-title">Aucune demande trouvée</h4>
            <p class="empty-state-text">
                <?php if ($filterStatus || $filterMonth || $filterSearch): ?>
                Modifiez vos filtres pour voir plus de résultats
                <?php else: ?>
                Vous n'avez pas encore créé de demande de frais
                <?php endif; ?>
            </p>
            <a href="nouvelle_demande.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Créer une demande
            </a>
        </div>
        <?php else: ?>
        <div class="expense-list" style="padding: 16px;">
            <?php foreach ($demandes as $demande): ?>
            <div class="expense-item" onclick="window.location='voir_demande.php?id=<?= $demande['id'] ?>'">
                <div class="expense-icon" style="<?= getIconStyle($demande['statut']) ?>">
                    <i class="fas fa-<?= getStatusIcon($demande['statut']) ?>"></i>
                </div>
                <div class="expense-info">
                    <div class="expense-title">
                        <?= htmlspecialchars($demande['objet']) ?>
                        <span style="font-weight: normal; color: var(--gray-500); font-size: 13px;">
                            #<?= $demande['id'] ?>
                        </span>
                    </div>
                    <div class="expense-meta">
                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($demande['lieu']) ?></span>
                        <span><i class="fas fa-calendar"></i> <?= formatDate($demande['date_depense']) ?></span>
                        <span><i class="fas fa-receipt"></i> <?= $demande['nb_depenses'] ?> dépense(s)</span>
                        <?php if ($demande['categories']): ?>
                        <span><i class="fas fa-tags"></i> <?= htmlspecialchars($demande['categories']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div class="expense-amount"><?= formatMoney($demande['montant_total']) ?></div>
                    <?= getStatusBadge($demande['statut']) ?>
                </div>
                <div style="margin-left: 16px;">
                    <?php if ($demande['statut'] === 'brouillon'): ?>
                    <a href="modifier_demande.php?id=<?= $demande['id'] ?>" class="btn btn-ghost btn-icon" onclick="event.stopPropagation();" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </a>
                    <button class="btn btn-ghost btn-icon" onclick="event.stopPropagation(); deleteDemande(<?= $demande['id'] ?>);" title="Supprimer">
                        <i class="fas fa-trash" style="color: var(--danger);"></i>
                    </button>
                    <?php else: ?>
                    <a href="voir_demande.php?id=<?= $demande['id'] ?>" class="btn btn-ghost btn-icon" onclick="event.stopPropagation();" title="Voir">
                        <i class="fas fa-eye"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer" style="display: flex; justify-content: center; gap: 8px;">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&status=<?= $filterStatus ?>&month=<?= $filterMonth ?>&search=<?= urlencode($filterSearch) ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="?page=<?= $i ?>&status=<?= $filterStatus ?>&month=<?= $filterMonth ?>&search=<?= urlencode($filterSearch) ?>" 
               class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&status=<?= $filterStatus ?>&month=<?= $filterMonth ?>&search=<?= urlencode($filterSearch) ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function deleteDemande(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette demande ?')) {
        window.location = 'supprimer_demande.php?id=' + id;
    }
}
</script>

<?php
function getIconStyle($status) {
    $styles = [
        'brouillon' => 'background: var(--gray-200); color: var(--gray-600);',
        'soumise' => 'background: rgba(59, 130, 246, 0.1); color: var(--info);',
        'validee_manager' => 'background: rgba(245, 158, 11, 0.1); color: var(--warning);',
        'approuvee_admin' => 'background: rgba(16, 185, 129, 0.1); color: var(--success);',
        'rejetee_manager' => 'background: rgba(239, 68, 68, 0.1); color: var(--danger);',
        'rejetee_admin' => 'background: rgba(239, 68, 68, 0.1); color: var(--danger);',
        'payee' => 'background: var(--primary-light); color: var(--primary);'
    ];
    return $styles[$status] ?? 'background: var(--gray-100); color: var(--gray-600);';
}

function getStatusIcon($status) {
    $icons = [
        'brouillon' => 'file',
        'soumise' => 'clock',
        'validee_manager' => 'user-check',
        'approuvee_admin' => 'check-circle',
        'rejetee_manager' => 'times-circle',
        'rejetee_admin' => 'times-circle',
        'payee' => 'coins'
    ];
    return $icons[$status] ?? 'file';
}

include 'includes/footer.php';
?>
