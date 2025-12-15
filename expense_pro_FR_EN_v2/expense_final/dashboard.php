<?php
/**
 * ExpensePro - Dashboard
 * Main dashboard with statistics and quick actions
 */

require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

$pageTitle = __('nav_dashboard');
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Statistics based on role
if ($userRole === 'employe') {
    // Employee stats
    $stats = [
        'total' => $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE user_id = ?"),
        'pending' => $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE user_id = ? AND statut IN ('soumise', 'validee_manager')"),
        'approved' => $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE user_id = ? AND statut = 'approuvee_admin'"),
        'amount' => $pdo->prepare("SELECT COALESCE(SUM(montant_total), 0) FROM demandes WHERE user_id = ? AND statut = 'approuvee_admin'")
    ];
    foreach ($stats as $key => $stmt) {
        $stmt->execute([$userId]);
        $stats[$key] = $stmt->fetchColumn();
    }
    
    // Recent requests
    $recentQuery = $pdo->prepare("
        SELECT d.*, GROUP_CONCAT(DISTINCT cf.nom) as categories
        FROM demandes d
        LEFT JOIN details_frais df ON d.id = df.demande_id
        LEFT JOIN categories_frais cf ON df.categorie_id = cf.id
        WHERE d.user_id = ?
        GROUP BY d.id
        ORDER BY d.created_at DESC
        LIMIT 5
    ");
    $recentQuery->execute([$userId]);
    $recentDemandes = $recentQuery->fetchAll();
    
    // Monthly trend
    $trendQuery = $pdo->prepare("
        SELECT DATE_FORMAT(date_depense, '%Y-%m') as month, SUM(montant_total) as total
        FROM demandes
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ");
    $trendQuery->execute([$userId]);
    $monthlyTrend = $trendQuery->fetchAll();
    
} elseif ($userRole === 'manager') {
    // Manager stats
    $stats = [
        'team' => $pdo->prepare("SELECT COUNT(*) FROM users WHERE manager_id = ?"),
        'pending' => $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE manager_id = ? AND statut = 'soumise'"),
        'validated' => $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE manager_id = ? AND statut IN ('validee_manager', 'approuvee_admin')"),
        'amount' => $pdo->prepare("SELECT COALESCE(SUM(montant_total), 0) FROM demandes WHERE manager_id = ? AND MONTH(created_at) = MONTH(NOW())")
    ];
    foreach ($stats as $key => $stmt) {
        $stmt->execute([$userId]);
        $stats[$key] = $stmt->fetchColumn();
    }
    
    // Pending requests to validate
    $pendingQuery = $pdo->prepare("
        SELECT d.*, u.nom, u.prenom, u.photo_profil
        FROM demandes d
        JOIN users u ON d.user_id = u.id
        WHERE d.manager_id = ? AND d.statut = 'soumise'
        ORDER BY d.created_at DESC
        LIMIT 10
    ");
    $pendingQuery->execute([$userId]);
    $pendingDemandes = $pendingQuery->fetchAll();
    
} else {
    // Admin stats
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM demandes")->fetchColumn(),
        'pending' => $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut = 'validee_manager'")->fetchColumn(),
        'approved' => $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut = 'approuvee_admin'")->fetchColumn(),
        'amount' => $pdo->query("SELECT COALESCE(SUM(montant_total), 0) FROM demandes WHERE statut = 'approuvee_admin' AND MONTH(created_at) = MONTH(NOW())")->fetchColumn()
    ];
    
    // All pending for approval
    $pendingQuery = $pdo->query("
        SELECT d.*, u.nom, u.prenom, m.nom as manager_nom, m.prenom as manager_prenom
        FROM demandes d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN users m ON d.manager_id = m.id
        WHERE d.statut = 'validee_manager'
        ORDER BY d.created_at DESC
        LIMIT 10
    ");
    $pendingDemandes = $pendingQuery->fetchAll();
    
    // Category breakdown
    $categoryQuery = $pdo->query("
        SELECT cf.nom, SUM(df.montant) as total
        FROM details_frais df
        JOIN categories_frais cf ON df.categorie_id = cf.id
        JOIN demandes d ON df.demande_id = d.id
        WHERE d.statut = 'approuvee_admin' AND MONTH(d.created_at) = MONTH(NOW())
        GROUP BY cf.nom
        ORDER BY total DESC
    ");
    $categoryBreakdown = $categoryQuery->fetchAll();
}

include 'includes/header.php';
?>

<!-- Quick Actions -->
<div class="quick-actions">
    <?php if ($userRole === 'employe'): ?>
    <a href="nouvelle_demande.php" class="quick-action">
        <div class="quick-action-icon">
            <i class="fas fa-plus"></i>
        </div>
        <span class="quick-action-text"><?= __('new_request') ?></span>
    </a>
    <a href="mes_demandes.php" class="quick-action">
        <div class="quick-action-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
            <i class="fas fa-list"></i>
        </div>
        <span class="quick-action-text"><?= __('nav_my_requests') ?></span>
    </a>
    <!-- <a href="calculateur_km.php" class="quick-action">
        <div class="quick-action-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
            <i class="fas fa-car"></i>
        </div>
        <span class="quick-action-text"><?= __('mileage_calc') ?></span>
    </a> -->
    <a href="historique.php" class="quick-action">
        <div class="quick-action-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
            <i class="fas fa-history"></i>
        </div>
        <span class="quick-action-text"><?= __('history') ?></span>
    </a>
    <?php elseif ($userRole === 'manager'): ?>
    <a href="demandes_equipe.php" class="quick-action">
        <div class="quick-action-icon">
            <i class="fas fa-users"></i>
        </div>
        <span class="quick-action-text"><?= __('my_team') ?></span>
    </a>
    <a href="validation.php" class="quick-action">
        <div class="quick-action-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
            <i class="fas fa-check-double"></i>
        </div>
        <span class="quick-action-text"><?= __('nav_to_validate') ?> (<?= $stats['pending'] ?>)</span>
    </a>
    <a href="rapport_equipe.php" class="quick-action">
        <div class="quick-action-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
            <i class="fas fa-chart-bar"></i>
        </div>
        <span class="quick-action-text"><?= __('nav_reports') ?></span>
    </a>
    <?php else: ?>
    <a href="approbation.php" class="quick-action">
        <div class="quick-action-icon">
            <i class="fas fa-stamp"></i>
        </div>
        <span class="quick-action-text"><?= __('approvals') ?> (<?= $stats['pending'] ?>)</span>
    </a>
    <a href="gestion_utilisateurs.php" class="quick-action">
        <div class="quick-action-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
            <i class="fas fa-users-cog"></i>
        </div>
        <span class="quick-action-text"><?= __('nav_users') ?></span>
    </a>
    <a href="rapports.php" class="quick-action">
        <div class="quick-action-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
            <i class="fas fa-file-export"></i>
        </div>
        <span class="quick-action-text"><?= __('nav_reports') ?></span>
    </a>
    <a href="analytics.php" class="quick-action">
        <div class="quick-action-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
            <i class="fas fa-chart-line"></i>
        </div>
        <span class="quick-action-text"><?= __('nav_analytics') ?></span>
    </a>
    <?php endif; ?>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <?php if ($userRole === 'employe'): ?>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon">
                <i class="fas fa-file-invoice"></i>
            </div>
        </div>
        <div class="stat-value"><?= $stats['total'] ?></div>
        <div class="stat-label"><?= __('total_requests') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div class="stat-value"><?= $stats['pending'] ?></div>
        <div class="stat-label"><?= __('pending_requests') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="stat-value"><?= $stats['approved'] ?></div>
        <div class="stat-label"><?= __('approved_requests') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
               <span style="font-weight:700;">د.م</span>

            </div>
        </div>
        <div class="stat-value"><?= formatMoney($stats['amount']) ?></div>
        <div class="stat-label"><?= __('total_amount') ?></div>
    </div>
    
    <?php elseif ($userRole === 'manager'): ?>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="stat-value"><?= $stats['team'] ?></div>
        <div class="stat-label"><?= __('my_team') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                <i class="fas fa-hourglass-half"></i>
            </div>
        </div>
        <div class="stat-value"><?= $stats['pending'] ?></div>
        <div class="stat-label"><?= __('pending_requests') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                <i class="fas fa-check-double"></i>
            </div>
        </div>
        <div class="stat-value"><?= $stats['validated'] ?></div>
        <div class="stat-label"><?= __('validated') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
               <span style="font-weight:700;">د.م</span>

            </div>
        </div>
        <div class="stat-value"><?= formatMoney($stats['amount']) ?></div>
        <div class="stat-label"><?= __('this_month') ?></div>
    </div>
    
    <?php else: ?>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
        </div>
        <div class="stat-value"><?= $stats['total'] ?></div>
        <div class="stat-label"><?= __('total_requests') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                <i class="fas fa-clipboard-check"></i>
            </div>
        </div>
        <div class="stat-value"><?= $stats['pending'] ?></div>
        <div class="stat-label"><?= __('pending_requests') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="stat-value"><?= $stats['approved'] ?></div>
        <div class="stat-label"><?= __('approved_requests') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
               <span style="font-weight:700;">د.م</span>

            </div>
        </div>
        <div class="stat-value"><?= formatMoney($stats['amount']) ?></div>
        <div class="stat-label"><?= __('this_month') ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- Main Content Grid -->
<div class="dashboard-grid">
    <!-- Main Content -->
    <div>
        <?php if ($userRole === 'employe'): ?>
        <!-- Recent Requests for Employee -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock"></i>
                    <?= __('recent_requests') ?>
                </h3>
                <a href="mes_demandes.php" class="btn btn-ghost btn-sm">
                    <?= __('view_all') ?> <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($recentDemandes)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h4 class="empty-state-title"><?= __('no_requests') ?></h4>
                    <p class="empty-state-text"><?= __('no_pending_requests') ?></p>
                    <a href="nouvelle_demande.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?= __('new_request') ?>
                    </a>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('subject') ?></th>
                                <th><?= __('amount') ?></th>
                                <th><?= __('date') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentDemandes as $demande): ?>
                            <tr>
                                <td style="font-weight:600;"><?= htmlspecialchars($demande['objet']) ?></td>
                                <td><?= formatMoney($demande['montant_total']) ?></td>
                                <td><?= formatDate($demande['created_at']) ?></td>
                                <td><?= getStatusBadge($demande['statut']) ?></td>
                                <td>
                                    <a href="voir_demande.php?id=<?= $demande['id'] ?>" class="btn btn-ghost btn-icon" title="<?= __('btn_view') ?>">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Pending Requests for Manager/Admin -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock"></i>
                    <?= $userRole === 'manager' ? __('requests_to_validate') : __('requests_to_approve') ?>
                </h3>
                <a href="<?= $userRole === 'manager' ? 'validation.php' : 'approbation.php' ?>" class="btn btn-ghost btn-sm">
                    <?= __('view_all') ?> <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($pendingDemandes)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h4 class="empty-state-title"><?= __('all_up_to_date') ?></h4>
                    <p class="empty-state-text"><?= __('no_pending_requests') ?></p>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('employee') ?></th>
                                <th><?= __('subject') ?></th>
                                <th><?= __('amount') ?></th>
                                <th><?= __('date') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingDemandes as $demande): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width:36px;height:36px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:600;font-size:12px;">
                                            <?= strtoupper(substr($demande['prenom'], 0, 1) . substr($demande['nom'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:600;"><?= htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($demande['objet']) ?></td>
                                <td style="font-weight:600;"><?= formatMoney($demande['montant_total']) ?></td>
                                <td><?= formatDate($demande['created_at']) ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a href="voir_demande.php?id=<?= $demande['id'] ?>" class="btn btn-ghost btn-icon" title="<?= __('btn_view') ?>">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-success btn-icon" onclick="quickApprove(<?= $demande['id'] ?>)" title="<?= __('btn_validate') ?>">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-danger btn-icon" onclick="quickReject(<?= $demande['id'] ?>)" title="<?= __('btn_reject') ?>">
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
        <?php endif; ?>
    </div>
    
    <!-- Sidebar Content -->
    <div>
        <!-- Chart -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-pie"></i>
                    <?= __('category_breakdown') ?>
                </h3>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 250px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-bell"></i>
                    <?= __('recent_activity') ?>
                </h3>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php
                    $activityQuery = $pdo->prepare("
                        SELECT n.*, d.objet 
                        FROM notifications n 
                        LEFT JOIN demandes d ON n.demande_id = d.id 
                        WHERE n.user_id = ? 
                        ORDER BY n.created_at DESC 
                        LIMIT 5
                    ");
                    $activityQuery->execute([$userId]);
                    $activities = $activityQuery->fetchAll();
                    
                    foreach ($activities as $activity):
                        $dotClass = strpos($activity['message'], 'APPROUVÉE') !== false ? 'success' : 
                                   (strpos($activity['message'], 'REJETÉE') !== false ? 'danger' : 'warning');
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?= $dotClass ?>"></div>
                        <div class="timeline-content">
                            <div class="timeline-date"><?= timeAgo($activity['created_at']) ?></div>
                            <div class="timeline-text"><?= htmlspecialchars($activity['message']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($activities)): ?>
                    <p style="text-align:center;color:var(--gray-500);padding:20px;">
                        <?= __('no_recent_activity') ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Category Chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('categoryChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Transport', '<?= __('cat_accommodation') ?>', '<?= __('cat_food') ?>', '<?= __('cat_fuel') ?>', '<?= __('cat_other') ?>'],
                datasets: [{
                    data: [35, 25, 20, 12, 8],
                    backgroundColor: [
                        '#0066FF',
                        '#00D4AA',
                        '#FF6B35',
                        '#F59E0B',
                        '#8B5CF6'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 16,
                            usePointStyle: true,
                            font: { size: 12 }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }
});

// Quick actions
function quickApprove(id) {
    <?php if ($userRole === 'admin'): ?>
    if (confirm('<?= __('confirm_approve') ?>')) {
        window.location = 'traiter_demande.php?action=approuver&id=' + id;
    }
    <?php else: ?>
    if (confirm('<?= __('confirm_validate') ?>')) {
        window.location = 'traiter_demande.php?action=valider&id=' + id;
    }
    <?php endif; ?>
}

function quickReject(id) {
    const reason = prompt('<?= __('reject_reason') ?>');
    if (reason) {
        window.location = 'traiter_demande.php?action=rejeter&id=' + id + '&motif=' + encodeURIComponent(reason);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
