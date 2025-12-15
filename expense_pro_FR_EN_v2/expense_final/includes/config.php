<?php
/**
 * ExpensePro - Configuration
 * Système de Gestion des Frais de Déplacement Professionnel
 */

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestion_frais');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuration de l'application
define('APP_NAME', 'ExpensePro');
define('APP_VERSION', '2.0');
define('APP_URL', 'http://localhost/expense_pro');
define('CURRENCY', 'DH');
define('CURRENCY_SYMBOL', 'DH');

// Configuration des uploads
define('UPLOAD_PATH', 'uploads/justificatifs/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// Configuration des sessions
define('SESSION_TIMEOUT', 3600); // 1 heure

// Plafonds par catégorie (en DH)
define('EXPENSE_LIMITS', [
    'Transport' => 2000,
    'Hébergement' => 1500,
    'Restauration' => 500,
    'Carburant' => 800,
    'Péage' => 300,
    'Fournitures' => 500,
    'Téléphone' => 200,
    'Autre' => 1000
]);

// Taux kilométrique
define('MILEAGE_RATE', 2.5); // DH par km

// Couleurs des statuts
define('STATUS_COLORS', [
    'brouillon' => '#6c757d',
    'soumise' => '#0dcaf0',
    'validee_manager' => '#ffc107',
    'approuvee_admin' => '#198754',
    'rejetee_manager' => '#dc3545',
    'rejetee_admin' => '#dc3545',
    'payee' => '#0d6efd'
]);

// Labels des statuts
define('STATUS_LABELS', [
    'brouillon' => 'Brouillon',
    'soumise' => 'En attente',
    'validee_manager' => 'Validée Manager',
    'approuvee_admin' => 'Approuvée',
    'rejetee_manager' => 'Rejetée',
    'rejetee_admin' => 'Rejetée',
    'payee' => 'Payée'
]);

// Icônes des catégories
define('CATEGORY_ICONS', [
    'Transport' => 'fa-plane',
    'Hébergement' => 'fa-hotel',
    'Restauration' => 'fa-utensils',
    'Carburant' => 'fa-gas-pump',
    'Péage' => 'fa-road',
    'Fournitures' => 'fa-box',
    'Téléphone' => 'fa-phone',
    'Autre' => 'fa-receipt'
]);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fuseau horaire
date_default_timezone_set('Africa/Casablanca');

// Connexion PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Fonctions utilitaires
function formatMoney($amount) {
    return number_format($amount, 2, ',', ' ') . ' ' . CURRENCY;
}

function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

function getStatusBadge($status) {
    $colors = STATUS_COLORS;
    $labels = STATUS_LABELS;
    $color = $colors[$status] ?? '#6c757d';
    $label = $labels[$status] ?? $status;
    return "<span class='status-badge' style='background-color: {$color}'>{$label}</span>";
}

function getCategoryIcon($category) {
    $icons = CATEGORY_ICONS;
    return $icons[$category] ?? 'fa-receipt';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: unauthorized.php');
        exit;
    }
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function flashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'À l\'instant';
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . ' h';
    if ($diff < 604800) return floor($diff / 86400) . ' j';
    return formatDate($datetime);
}
?>
