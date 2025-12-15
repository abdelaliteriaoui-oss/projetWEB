<?php
/**
 * ExpensePro - Login Page Style Webexpenses
 * Page de connexion avec effet neige et panneau login glissant (SANS PANNEAU GAUCHE)
 * + Grandes étoiles rotatives en bas de page
 */

require_once 'includes/config.php';
require_once 'includes/languages.php';

// Si déjà connecté, rediriger vers dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$showLogin = isset($_GET['login']) || isset($_POST['email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $showLogin = true;

    if (empty($email) || empty($password)) {
        $error = $current_lang === 'fr' ? 'Veuillez remplir tous les champs.' : 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND actif = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenom'] = $user['prenom'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['photo_profil'] = $user['photo_profil'];
            $_SESSION['theme'] = $user['theme'] ?? 'light';
            $_SESSION['manager_id'] = $user['manager_id'];
            
            // Charger la langue de l'utilisateur
            if (!empty($user['langue'])) {
                $_SESSION['lang'] = $user['langue'];
            }

            header('Location: dashboard.php');
            exit;
        } else {
            $error = $current_lang === 'fr' ? 'Email ou mot de passe incorrect.' : 'Invalid email or password.';
        }
    }
}

// Traductions pour la page
$texts = [
    'tagline' => [
        'fr' => 'Automatisez vos dépenses. Appliquez vos politiques. Contrôlez vos coûts.',
        'en' => 'Automate expenses. Enforce policies. Control costs.'
    ],
    'main_title' => [
        'fr' => 'Logiciel de Gestion des Frais',
        'en' => 'Expense Management Software'
    ],
    'description' => [
        'fr' => "Approuvé par plus de 2 000 équipes financières, le système de gestion des dépenses facile à utiliser d'ExpensePro améliore l'efficacité des dépenses, contrôle les dépenses des employés et génère des économies significatives.",
        'en' => "Trusted by 2,000+ finance teams, ExpensePro's easy-to-use expense management system improves expense efficiency, controls employee spend, and generates significant cost savings."
    ],
    'login_btn' => ['fr' => 'Se connecter', 'en' => 'Login'],
    'welcome' => ['fr' => 'Bienvenue', 'en' => 'Welcome'],
    'connect_account' => ['fr' => 'Connectez-vous à votre compte', 'en' => 'Connect to your account'],
    'email_label' => ['fr' => 'Adresse email', 'en' => 'Email address'],
    'password_label' => ['fr' => 'Mot de passe', 'en' => 'Password'],
    'remember_me' => ['fr' => 'Se souvenir de moi', 'en' => 'Remember me'],
    'forgot_password' => ['fr' => 'Mot de passe oublié ?', 'en' => 'Forgot password?'],
    'demo_accounts' => ['fr' => 'Comptes de démonstration', 'en' => 'Demo accounts'],
    'expense_cards' => ['fr' => 'Cartes de dépenses', 'en' => 'Expense Cards'],
    'reporting_dashboard' => ['fr' => 'Tableau de bord', 'en' => 'Reporting Dashboard'],
    'personal_spend' => ['fr' => 'Dépenses personnelles', 'en' => 'Personal Spend'],
    'top_approvers' => ['fr' => 'Top Approbateurs', 'en' => 'Top Approvers'],
];

function t($key) {
    global $texts, $current_lang;
    return $texts[$key][$current_lang] ?? $texts[$key]['fr'] ?? $key;
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExpensePro - <?= t('main_title') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0066FF;
            --primary-dark: #0052CC;
            --secondary: #00D4AA;
            --orange: #F5A623;
            --dark-bg: #1a1a2e;
            --darker-bg: #0f0f1a;
            --gray-100: #F3F4F6;
            --gray-300: #D1D5DB;
            --gray-500: #6B7280;
            --gray-700: #374151;
            --gray-900: #111827;
            --danger: #EF4444;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: var(--dark-bg);
            overflow-x: hidden;
        }
        
        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(15, 15, 26, 0.8);
            backdrop-filter: blur(10px);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        
        .logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
            color: white;
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: white;
        }
        
        .logo-text span { color: var(--secondary); }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        
        .lang-switch {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            color: white;
            font-size: 14px;
            text-decoration: none;
        }
        
        .lang-switch:hover { background: rgba(255,255,255,0.2); }
        
        .btn-login-header {
            background: var(--orange);
            color: #000;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-login-header:hover {
            background: #e09500;
            transform: translateY(-2px);
        }
        
        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 120px 40px 60px;
            position: relative;
            background: linear-gradient(180deg, var(--darker-bg) 0%, var(--dark-bg) 50%, #16213e 100%);
            overflow: hidden;
        }
        
        /* Étoiles */
        .stars {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
        }
        
        .star {
            position: absolute;
            width: 2px;
            height: 2px;
            background: white;
            border-radius: 50%;
            opacity: 0.5;
            animation: twinkle 3s infinite;
        }
        
        @keyframes twinkle {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 1; }
        }
        
        /* Flocons de neige */
        .snowflakes {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            overflow: hidden;
        }
        
        .snowflake {
            position: absolute;
            top: -10px;
            color: white;
            font-size: 1em;
            opacity: 0.8;
            animation: fall linear infinite;
        }
        
        @keyframes fall {
            0% { transform: translateY(-10px) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(360deg); opacity: 0.3; }
        }
        
        /* Nuages/formes décoratives en bas */
        .cloud-decoration {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 250px;
            pointer-events: none;
        }
        
        .cloud {
            position: absolute;
            bottom: -80px;
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 50%;
            opacity: 0.5;
            animation: float 6s ease-in-out infinite;
        }
        
        .cloud::before, .cloud::after {
            content: '';
            position: absolute;
            background: inherit;
            border-radius: 50%;
        }
        
        .cloud::before { width: 180px; height: 180px; top: -70px; left: 30px; }
        .cloud::after { width: 140px; height: 140px; top: -50px; right: 30px; }
        
        .cloud-1 { left: -120px; animation-delay: 0s; }
        .cloud-2 { right: -120px; animation-delay: 3s; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        /* Cercles décoratifs */
        .circle-decoration {
            position: absolute;
            border-radius: 50%;
            border: 2px solid rgba(59, 130, 246, 0.3);
            animation: pulse-circle 4s ease-in-out infinite;
        }
        
        .circle-1 { width: 350px; height: 350px; bottom: -180px; left: 8%; }
        .circle-2 { width: 250px; height: 250px; bottom: -130px; right: 12%; animation-delay: 2s; }
        .circle-3 { width: 180px; height: 180px; bottom: -80px; left: 35%; animation-delay: 1s; }
        
        @keyframes pulse-circle {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.05); }
        }
        
        /* ============================================
           GRANDES ÉTOILES ROTATIVES - Style Webexpenses
           ============================================ */
        .rotating-stars {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 450px;
            pointer-events: none;
            overflow: hidden;
            z-index: 6;
        }
        
        .big-star {
            position: absolute;
            bottom: -200px;
            width: 450px;
            height: 450px;
        }
        
        .big-star-left {
            left: -120px;
            animation: rotateStar 25s linear infinite;
        }
        
        .big-star-right {
            right: -120px;
            animation: rotateStar 30s linear infinite reverse;
        }
        
        .big-star svg {
            width: 100%;
            height: 100%;
            filter: drop-shadow(0 0 20px rgba(59, 130, 246, 0.3));
        }
        
        @keyframes rotateStar {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .tagline {
            color: var(--orange);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 24px;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 10;
        }
        
        .hero h1 {
            color: white;
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 24px;
            line-height: 1.1;
            max-width: 800px;
            position: relative;
            z-index: 10;
        }
        
        .hero-description {
            color: rgba(255,255,255,0.7);
            font-size: 18px;
            max-width: 700px;
            line-height: 1.7;
            margin-bottom: 40px;
            position: relative;
            z-index: 10;
        }
        
        .hero-description .highlight { color: var(--secondary); }
        .hero-description .highlight-orange { color: var(--orange); }
        
        /* Features Cards at bottom */
        .features-preview {
            display: flex;
            gap: 20px;
            margin-top: 60px;
            flex-wrap: wrap;
            justify-content: center;
            position: relative;
            z-index: 10;
        }
        
        .feature-card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px;
            width: 200px;
            text-align: left;
            border: 1px solid rgba(255,255,255,0.1);
            transition: transform 0.3s;
        }
        
        .feature-card:hover { transform: translateY(-5px); }
        
        .feature-card h4 {
            color: white;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .feature-card-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            color: white;
        }
        
        /* Login Panel - SANS PANNEAU GAUCHE */
        .login-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 200;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .login-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .login-panel {
            position: fixed;
            top: 0;
            right: -500px;
            width: 100%;
            max-width: 500px;
            height: 100vh;
            background: white;
            z-index: 201;
            transition: right 0.4s ease;
            display: flex;
            overflow: hidden;
            box-shadow: -10px 0 50px rgba(0,0,0,0.3);
        }
        
        .login-panel.active { right: 0; }
        
        .login-panel-right {
            width: 100%;
            padding: 40px 35px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
        }
        
        .close-login {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--gray-500);
            z-index: 10;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.2s;
        }
        
        .close-login:hover { background: var(--gray-100); }
        
        .login-form h1 {
            font-size: 26px;
            color: var(--gray-900);
            margin-bottom: 8px;
        }
        
        .login-form .subtitle {
            color: var(--gray-500);
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group { margin-bottom: 20px; }
        
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 6px;
        }
        
        .input-wrapper { position: relative; }
        
        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 14px 14px 44px;
            font-size: 14px;
            border: 2px solid var(--gray-300);
            border-radius: 10px;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0,102,255,0.1);
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .remember input { width: 16px; height: 16px; accent-color: var(--primary); }
        .remember span { font-size: 13px; color: var(--gray-700); }
        
        .forgot-link {
            font-size: 13px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .forgot-link:hover { text-decoration: underline; }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,102,255,0.3);
        }
        
        .error-message {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: var(--danger);
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header { padding: 12px 20px; }
            .hero { padding: 100px 20px 40px; }
            .hero h1 { font-size: 32px; }
            .hero-description { font-size: 14px; }
            .login-panel { max-width: 100%; }
            .login-panel-right { padding: 30px 20px; }
            .features-preview { flex-direction: column; align-items: center; }
            .feature-card { width: 100%; max-width: 280px; }
            
            /* Responsive étoiles rotatives */
            .big-star {
                width: 280px;
                height: 280px;
                bottom: -140px;
            }
            .big-star-left { left: -100px; }
            .big-star-right { right: -100px; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <a href="login.php" class="logo">
            <div class="logo-icon">E</div>
            <div class="logo-text">Expense<span>Pro</span></div>
        </a>
        
        <div class="header-right">
            <a href="?lang=<?= $current_lang === 'fr' ? 'en' : 'fr' ?>" class="lang-switch">
                <?= $current_lang === 'fr' ? '🇫🇷 FR' : '🇬🇧 EN' ?>
                <i class="fas fa-exchange-alt"></i>
            </a>
            
            <button class="btn-login-header" onclick="openLogin()">
                <?= t('login_btn') ?>
            </button>
        </div>
    </header>
    
    <!-- Hero Section -->
    <section class="hero">
        <!-- Stars -->
        <div class="stars" id="stars"></div>
        
        <!-- Snowflakes -->
        <div class="snowflakes" id="snowflakes"></div>
        
        <!-- Cloud decorations -->
        <div class="cloud-decoration">
            <div class="cloud cloud-1"></div>
            <div class="cloud cloud-2"></div>
            <div class="circle-decoration circle-1"></div>
            <div class="circle-decoration circle-2"></div>
            <div class="circle-decoration circle-3"></div>
        </div>
        
        <!-- GRANDES ÉTOILES ROTATIVES - Style Webexpenses -->
        <div class="rotating-stars">
            <!-- Étoile gauche (bleue) -->
            <div class="big-star big-star-left">
                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="starGradientBlue" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#3b82f6;stop-opacity:0.9" />
                            <stop offset="50%" style="stop-color:#1d4ed8;stop-opacity:0.7" />
                            <stop offset="100%" style="stop-color:#1e40af;stop-opacity:0.5" />
                        </linearGradient>
                    </defs>
                    <!-- Étoile à 8 branches style Webexpenses -->
                    <path d="M100,0 L115,70 L100,50 L85,70 Z" fill="url(#starGradientBlue)"/>
                    <path d="M200,100 L130,115 L150,100 L130,85 Z" fill="url(#starGradientBlue)"/>
                    <path d="M100,200 L85,130 L100,150 L115,130 Z" fill="url(#starGradientBlue)"/>
                    <path d="M0,100 L70,85 L50,100 L70,115 Z" fill="url(#starGradientBlue)"/>
                    <!-- Branches diagonales -->
                    <path d="M170.7,29.3 L125,90 L140,75 L110,75 Z" fill="url(#starGradientBlue)"/>
                    <path d="M170.7,170.7 L110,125 L125,140 L125,110 Z" fill="url(#starGradientBlue)"/>
                    <path d="M29.3,170.7 L75,110 L60,125 L90,125 Z" fill="url(#starGradientBlue)"/>
                    <path d="M29.3,29.3 L90,75 L75,60 L75,90 Z" fill="url(#starGradientBlue)"/>
                    <!-- Centre -->
                    <circle cx="100" cy="100" r="25" fill="url(#starGradientBlue)" opacity="0.8"/>
                    <circle cx="100" cy="100" r="15" fill="#3b82f6" opacity="0.9"/>
                </svg>
            </div>
            
            <!-- Étoile droite (bleue/cyan) -->
            <div class="big-star big-star-right">
                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="starGradientCyan" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#06b6d4;stop-opacity:0.9" />
                            <stop offset="50%" style="stop-color:#0891b2;stop-opacity:0.7" />
                            <stop offset="100%" style="stop-color:#0e7490;stop-opacity:0.5" />
                        </linearGradient>
                    </defs>
                    <!-- Étoile à 8 branches style Webexpenses -->
                    <path d="M100,0 L115,70 L100,50 L85,70 Z" fill="url(#starGradientCyan)"/>
                    <path d="M200,100 L130,115 L150,100 L130,85 Z" fill="url(#starGradientCyan)"/>
                    <path d="M100,200 L85,130 L100,150 L115,130 Z" fill="url(#starGradientCyan)"/>
                    <path d="M0,100 L70,85 L50,100 L70,115 Z" fill="url(#starGradientCyan)"/>
                    <!-- Branches diagonales -->
                    <path d="M170.7,29.3 L125,90 L140,75 L110,75 Z" fill="url(#starGradientCyan)"/>
                    <path d="M170.7,170.7 L110,125 L125,140 L125,110 Z" fill="url(#starGradientCyan)"/>
                    <path d="M29.3,170.7 L75,110 L60,125 L90,125 Z" fill="url(#starGradientCyan)"/>
                    <path d="M29.3,29.3 L90,75 L75,60 L75,90 Z" fill="url(#starGradientCyan)"/>
                    <!-- Centre -->
                    <circle cx="100" cy="100" r="25" fill="url(#starGradientCyan)" opacity="0.8"/>
                    <circle cx="100" cy="100" r="15" fill="#06b6d4" opacity="0.9"/>
                </svg>
            </div>
        </div>
        
        <p class="tagline"><?= t('tagline') ?></p>
        <h1><?= t('main_title') ?></h1>
        <p class="hero-description">
            <?= str_replace(
                ['efficiency', 'controls', 'cost savings', 'efficacité', 'contrôle', 'économies'],
                ['<span class="highlight">efficiency</span>', '<span class="highlight-orange">controls</span>', '<span class="highlight-orange">cost savings</span>', '<span class="highlight">efficacité</span>', '<span class="highlight-orange">contrôle</span>', '<span class="highlight-orange">économies</span>'],
                t('description')
            ) ?>
        </p>
        
        <button class="btn-login-header" onclick="openLogin()" style="font-size: 16px; padding: 16px 36px;">
            <?= t('login_btn') ?>
        </button>
        
        <!-- Feature cards -->
        <div class="features-preview">
            <div class="feature-card">
                <div class="feature-card-icon"><i class="fas fa-credit-card"></i></div>
                <h4><?= t('expense_cards') ?></h4>
            </div>
            <div class="feature-card">
                <div class="feature-card-icon"><i class="fas fa-chart-bar"></i></div>
                <h4><?= t('reporting_dashboard') ?></h4>
            </div>
            <div class="feature-card">
                <div class="feature-card-icon"><i class="fas fa-chart-pie"></i></div>
                <h4><?= t('personal_spend') ?></h4>
            </div>
            <div class="feature-card">
                <div class="feature-card-icon"><i class="fas fa-users"></i></div>
                <h4><?= t('top_approvers') ?></h4>
            </div>
        </div>
    </section>
    
    <!-- Login Overlay -->
    <div class="login-overlay <?= $showLogin ? 'active' : '' ?>" onclick="closeLogin()"></div>
    
    <!-- Login Panel - SANS PANNEAU GAUCHE -->
    <div class="login-panel <?= $showLogin ? 'active' : '' ?>" id="loginPanel">
        <button class="close-login" onclick="closeLogin()">&times;</button>
        
        <div class="login-panel-right">
            <div class="login-form">
                <h1><?= t('welcome') ?> 👋</h1>
                <p class="subtitle"><?= t('connect_account') ?></p>
                
                <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                    <div class="form-group">
                        <label class="form-label"><?= t('email_label') ?></label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" class="form-control" placeholder="votre@email.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><?= t('password_label') ?></label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <label class="remember">
                            <input type="checkbox" name="remember">
                            <span><?= t('remember_me') ?></span>
                        </label>
                        <a href="#" class="forgot-link"><?= t('forgot_password') ?></a>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <span><?= t('login_btn') ?></span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Generate stars
        function createStars() {
            const starsContainer = document.getElementById('stars');
            for (let i = 0; i < 150; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.animationDelay = Math.random() * 3 + 's';
                star.style.width = (Math.random() * 2 + 1) + 'px';
                star.style.height = star.style.width;
                starsContainer.appendChild(star);
            }
        }
        
        // Generate snowflakes
        function createSnowflakes() {
            const snowflakesContainer = document.getElementById('snowflakes');
            const snowflakeSymbols = ['❄', '❅', '❆', '•'];
            
            for (let i = 0; i < 50; i++) {
                const snowflake = document.createElement('div');
                snowflake.className = 'snowflake';
                snowflake.innerHTML = snowflakeSymbols[Math.floor(Math.random() * snowflakeSymbols.length)];
                snowflake.style.left = Math.random() * 100 + '%';
                snowflake.style.fontSize = (Math.random() * 10 + 8) + 'px';
                snowflake.style.opacity = Math.random() * 0.6 + 0.2;
                snowflake.style.animationDuration = (Math.random() * 10 + 10) + 's';
                snowflake.style.animationDelay = Math.random() * 10 + 's';
                snowflakesContainer.appendChild(snowflake);
            }
        }
        
        // Login panel functions
        function openLogin() {
            document.querySelector('.login-overlay').classList.add('active');
            document.getElementById('loginPanel').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeLogin() {
            document.querySelector('.login-overlay').classList.remove('active');
            document.getElementById('loginPanel').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Prevent closing when clicking inside panel
        document.getElementById('loginPanel').addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeLogin();
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            createStars();
            createSnowflakes();
            
            <?php if ($showLogin): ?>
            openLogin();
            <?php endif; ?>
        });
    </script>
</body>
</html>