<?php
/**
 * ExpensePro - Header avec sélecteur de langue FR/EN et Scanner OCR
 */

// IMPORTANT: Charger le système de langues EN PREMIER
// languages.php déjà inclus dans la page

if (!isset($pageTitle)) $pageTitle = __('nav_dashboard');

// Notifications
$notifQuery = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lu = 0");
$notifQuery->execute([$_SESSION['user_id']]);
$notifCount = $notifQuery->fetchColumn();

$notifListQuery = $pdo->prepare("SELECT n.*, d.objet FROM notifications n LEFT JOIN demandes d ON n.demande_id = d.id WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT 5");
$notifListQuery->execute([$_SESSION['user_id']]);
$notifications = $notifListQuery->fetchAll();

// Badge manager
$pendingBadgeCount = 0;
if ($_SESSION['role'] === 'manager') {
    try {
        $badgeStmt = $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE manager_id = ? AND statut = 'soumise'");
        $badgeStmt->execute([$_SESSION['user_id']]);
        $pendingBadgeCount = (int)$badgeStmt->fetchColumn();
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" data-theme="<?= $_SESSION['theme'] ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
    .language-selector{position:relative;display:inline-block}
    .language-btn{display:flex;align-items:center;gap:6px;padding:8px 12px;background:var(--bg-secondary,#f5f5f5);border:1px solid var(--border-color,#e0e0e0);border-radius:8px;cursor:pointer;font-size:14px;font-weight:500;color:var(--text-primary,#333);transition:all .2s}
    .language-btn:hover{background:var(--bg-hover,#eee);border-color:var(--primary,#f97316)}
    .language-btn .current-flag{font-size:18px;line-height:1}
    .language-btn .current-lang{font-weight:600;color:var(--primary,#f97316)}
    .language-btn i{font-size:10px;color:var(--text-secondary,#666);transition:transform .2s}
    .language-selector.open .language-btn i{transform:rotate(180deg)}
    .language-dropdown{position:absolute;top:calc(100% + 8px);right:0;min-width:160px;background:var(--bg-primary,#fff);border:1px solid var(--border-color,#e0e0e0);border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.15);opacity:0;visibility:hidden;transform:translateY(-10px);transition:all .2s;z-index:1000;overflow:hidden}
    .language-selector.open .language-dropdown{opacity:1;visibility:visible;transform:translateY(0)}
    .language-option{display:flex;align-items:center;gap:12px;padding:12px 16px;text-decoration:none;color:var(--text-primary,#333);font-size:14px;transition:background .2s}
    .language-option:first-child{border-bottom:1px solid var(--border-color,#f0f0f0)}
    .language-option:hover{background:var(--bg-hover,#f5f5f5)}
    .language-option.active{background:linear-gradient(135deg,rgba(249,115,22,.1),rgba(234,88,12,.1));color:var(--primary,#f97316)}
    .language-option .lang-flag{font-size:20px;line-height:1}
    .language-option .lang-name{font-weight:500}
    
    .header-profile{display:flex;align-items:center;gap:8px;padding:6px 12px 6px 6px;background:var(--bg-secondary,#f5f5f5);border:1px solid var(--border-color,#e0e0e0);border-radius:8px;cursor:pointer;transition:all .2s}
    .header-profile:hover{background:var(--bg-hover,#eee);border-color:var(--primary,#f97316)}
    .header-profile-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#f97316,#ea580c);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:13px;overflow:hidden}
    .header-profile-avatar img{width:100%;height:100%;object-fit:cover}
    .header-profile-name{font-size:14px;font-weight:600;color:var(--text-primary,#333)}
    </style>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💰</text></svg>">
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">E</div>
            <div class="sidebar-brand">Expense<span>Pro</span></div>
        </div>
        
        <nav class="sidebar-nav">
            <?php if ($_SESSION['role'] === 'employe'): ?>
            <div class="nav-section">
                <div class="nav-section-title"><?= __('section_main') ?></div>
                <a href="dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i><span><?= __('nav_dashboard') ?></span>
                </a>
                <a href="mes_demandes.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'mes_demandes.php' ? 'active' : '' ?>">
                    <i class="fas fa-file-invoice-dollar"></i><span><?= __('nav_my_requests') ?></span>
                </a>
                <a href="nouvelle_demande.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'nouvelle_demande.php' ? 'active' : '' ?>">
                    <i class="fas fa-plus-circle"></i><span><?= __('nav_new_request') ?></span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title"><?= __('section_tools') ?></div>
                <a href="scanner_ocr.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'scanner_ocr.php' ? 'active' : '' ?>">
                    <i class="fas fa-camera"></i><span>Scanner OCR</span>
                    <span style="background:linear-gradient(135deg,#8B5CF6,#7C3AED);color:#fff;font-size:9px;padding:2px 6px;border-radius:10px;margin-left:auto">OCR</span>
                </a>
                <a href="gps_tracking.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'gps_tracking.php' ? 'active' : '' ?>">
                    <i class="fas fa-satellite-dish"></i><span><?= __('nav_gps_tracking') ?></span>
                    <span style="background:linear-gradient(135deg,#10B981,#059669);color:#fff;font-size:9px;padding:2px 6px;border-radius:10px;margin-left:auto">NEW</span>
                </a>
                <a href="historique.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'historique.php' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i><span><?= __('nav_history') ?></span>
                </a>
            </div>
            
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
            <div class="nav-section">
                <div class="nav-section-title"><?= __('section_main') ?></div>
                <a href="dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i><span><?= __('nav_dashboard') ?></span>
                </a>
                <a href="demandes_equipe.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'demandes_equipe.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i><span><?= __('nav_team_requests') ?></span>
                    <?php if ($pendingBadgeCount > 0): ?><span class="nav-badge"><?= $pendingBadgeCount ?></span><?php endif; ?>
                </a>
                <a href="validation.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'validation.php' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i><span><?= __('nav_to_validate') ?></span>
                    <?php if ($pendingBadgeCount > 0): ?><span class="nav-badge"><?= $pendingBadgeCount ?></span><?php endif; ?>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title"><?= __('section_tools') ?></div>
                <a href="fraud_detection.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'fraud_detection.php' ? 'active' : '' ?>">
                    <i class="fas fa-shield-alt"></i><span><?= __('nav_fraud_detection') ?></span>
                    <span style="background:linear-gradient(135deg,#EF4444,#DC2626);color:#fff;font-size:9px;padding:2px 6px;border-radius:10px;margin-left:auto">AI</span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title"><?= __('section_reports') ?></div>
                <a href="rapport_equipe.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'rapport_equipe.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i><span><?= __('nav_team_report') ?></span>
                </a>
                <a href="statistiques.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'statistiques.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i><span><?= __('nav_statistics') ?></span>
                </a>
            </div>
            
            <?php elseif ($_SESSION['role'] === 'admin'): ?>
            <div class="nav-section">
                <div class="nav-section-title"><?= __('section_main') ?></div>
                <a href="dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i><span><?= __('nav_dashboard') ?></span>
                </a>
                <a href="toutes_demandes.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'toutes_demandes.php' ? 'active' : '' ?>">
                    <i class="fas fa-folder-open"></i><span><?= __('nav_all_requests') ?></span>
                </a>
                <a href="approbation.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'approbation.php' ? 'active' : '' ?>">
                    <i class="fas fa-stamp"></i><span><?= __('nav_approval') ?></span>
                    <?php $waitingCount = $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut = 'validee_manager'")->fetchColumn();
                    if ($waitingCount > 0): ?><span class="nav-badge"><?= $waitingCount ?></span><?php endif; ?>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title"><?= __('section_admin') ?></div>
                <a href="gestion_utilisateurs.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'gestion_utilisateurs.php' ? 'active' : '' ?>">
                    <i class="fas fa-users-cog"></i><span><?= __('nav_users') ?></span>
                </a>
                <a href="gestion_categories.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'gestion_categories.php' ? 'active' : '' ?>">
                    <i class="fas fa-tags"></i><span><?= __('nav_categories') ?></span>
                </a>
                <a href="parametres.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'parametres.php' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i><span><?= __('nav_settings') ?></span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title"><?= __('section_reports') ?></div>
                <a href="rapports.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'rapports.php' ? 'active' : '' ?>">
                    <i class="fas fa-file-export"></i><span><?= __('nav_reports') ?></span>
                </a>
                <a href="analytics.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i><span><?= __('nav_analytics') ?></span>
                </a>
            </div>
            <?php endif; ?>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?php if (!empty($_SESSION['photo_profil'])): ?>
                    <img src="uploads/profiles/<?= $_SESSION['photo_profil'] ?>" alt="Avatar">
                    <?php else: ?>
                    <?= strtoupper(substr($_SESSION['prenom'], 0, 1) . substr($_SESSION['nom'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= $_SESSION['prenom'] ?> <?= $_SESSION['nom'] ?></div>
                    <div class="sidebar-user-role"><?= ucfirst($_SESSION['role']) ?></div>
                </div>
            </div>
        </div>
    </aside>

    <div class="main-wrapper">
        <header class="top-header">
            <div class="header-left">
                <button class="toggle-sidebar" type="button"><i class="fas fa-bars"></i></button>
                <h1 class="page-title"><?= $pageTitle ?></h1>
            </div>
            
            <div class="header-right">
                <button class="header-btn" data-toggle-theme title="<?= __('change_theme') ?>">
                    <i class="fas fa-moon"></i>
                </button>
                
                <!-- LANGUAGE SELECTOR -->
                <?= renderLanguageSelector() ?>
                
                <div style="position:relative">
                    <button class="header-btn" data-dropdown="#notifications-dropdown">
                        <i class="fas fa-bell"></i>
                        <?php if ($notifCount > 0): ?><span class="badge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span><?php endif; ?>
                    </button>
                    
                    <div class="notifications-dropdown" id="notifications-dropdown">
                        <div class="notifications-header">
                            <span class="notifications-title"><?= __('notifications') ?></span>
                            <a href="notifications.php" style="font-size:12px;color:var(--primary)"><?= __('view_all') ?></a>
                        </div>
                        <div class="notifications-list">
                            <?php if (empty($notifications)): ?>
                            <div style="padding:40px;text-align:center;color:var(--gray-500)">
                                <i class="fas fa-bell-slash" style="font-size:32px;margin-bottom:12px"></i>
                                <p><?= __('no_notifications') ?></p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item <?= $notif['lu'] ? '' : 'unread' ?>">
                                <div class="notification-content">
                                    <div class="notification-icon <?= strpos($notif['message'], 'APPROUVÉE') !== false ? 'success' : (strpos($notif['message'], 'REJETÉE') !== false ? 'danger' : 'warning') ?>">
                                        <i class="fas fa-<?= strpos($notif['message'], 'APPROUVÉE') !== false ? 'check' : (strpos($notif['message'], 'REJETÉE') !== false ? 'times' : 'clock') ?>"></i>
                                    </div>
                                    <div class="notification-text">
                                        <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                                        <div class="notification-time"><?= timeAgo($notif['created_at']) ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- HEADER PROFILE WITH PHOTO -->
                <a href="profil.php" class="header-profile" title="<?= __('profile') ?>">
                    <div class="header-profile-avatar">
                        <?php if (!empty($_SESSION['photo_profil'])): ?>
                        <img src="uploads/profiles/<?= $_SESSION['photo_profil'] ?>" alt="<?= htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']) ?>">
                        <?php else: ?>
                        <?= strtoupper(substr($_SESSION['prenom'], 0, 1) . substr($_SESSION['nom'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <span class="header-profile-name"><?= htmlspecialchars($_SESSION['prenom']) ?></span>
                </a>
                
                <a href="logout.php" class="header-btn" title="<?= __('logout') ?>" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

        <main class="main-content">
            <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="toast-container">
                <div class="toast <?= $flash['type'] ?>">
                    <div class="toast-icon"><i class="fas fa-<?= $flash['type'] === 'success' ? 'check' : 'exclamation' ?>"></i></div>
                    <span class="toast-message"><?= $flash['message'] ?></span>
                </div>
            </div>
            <?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var langSelector = document.querySelector('.language-selector');
    var langBtn = document.getElementById('langBtn');
    if (langBtn && langSelector) {
        langBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            langSelector.classList.toggle('open');
        });
        document.addEventListener('click', function(e) {
            if (!langSelector.contains(e.target)) {
                langSelector.classList.remove('open');
            }
        });
    }
});
</script>