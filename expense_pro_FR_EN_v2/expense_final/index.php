<?php
/**
 * ExpensePro - Landing Page Style Webexpenses
 * Page d'accueil avec effet neige et formulaire login
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

// Traductions pour la landing page
$landing_texts = [
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
    'login_btn' => [
        'fr' => 'Se connecter',
        'en' => 'Login'
    ],
    'welcome' => [
        'fr' => 'Bienvenue',
        'en' => 'Welcome'
    ],
    'connect_account' => [
        'fr' => 'Connectez-vous à votre compte',
        'en' => 'Connect to your account'
    ],
    'email_label' => [
        'fr' => 'Adresse email',
        'en' => 'Email address'
    ],
    'password_label' => [
        'fr' => 'Mot de passe',
        'en' => 'Password'
    ],
    'remember_me' => [
        'fr' => 'Se souvenir de moi',
        'en' => 'Remember me'
    ],
    'forgot_password' => [
        'fr' => 'Mot de passe oublié ?',
        'en' => 'Forgot password?'
    ],
    'demo_accounts' => [
        'fr' => 'Comptes de démonstration',
        'en' => 'Demo accounts'
    ],
    'feature1_title' => [
        'fr' => 'Soumission rapide',
        'en' => 'Fast Submission'
    ],
    'feature1_desc' => [
        'fr' => 'Créez vos demandes en quelques clics',
        'en' => 'Create your requests in a few clicks'
    ],
    'feature2_title' => [
        'fr' => 'Scan intelligent',
        'en' => 'Smart Scan'
    ],
    'feature2_desc' => [
        'fr' => 'Capturez vos justificatifs facilement',
        'en' => 'Capture your receipts easily'
    ],
    'feature3_title' => [
        'fr' => 'Suivi en temps réel',
        'en' => 'Real-time Tracking'
    ],
    'feature3_desc' => [
        'fr' => "Visualisez l'état de vos demandes",
        'en' => 'View the status of your requests'
    ],
    'expense_cards' => [
        'fr' => 'Cartes de dépenses',
        'en' => 'Expense Cards'
    ],
    'reporting_dashboard' => [
        'fr' => 'Tableau de bord',
        'en' => 'Reporting Dashboard'
    ],
    'personal_spend' => [
        'fr' => 'Dépenses personnelles',
        'en' => 'Personal Spend'
    ],
    'top_approvers' => [
        'fr' => 'Top Approbateurs',
        'en' => 'Top Approvers'
    ],
    'manage_subtitle' => [
        'fr' => 'Gérez vos frais de déplacement intelligemment',
        'en' => 'Manage your travel expenses intelligently'
    ],
    'manage_desc' => [
        'fr' => 'Une solution moderne et intuitive pour soumettre, valider et suivre vos notes de frais professionnelles.',
        'en' => 'A modern and intuitive solution to submit, validate and track your professional expense reports.'
    ]
];

function lt($key) {
    global $landing_texts, $current_lang;
    return $landing_texts[$key][$current_lang] ?? $landing_texts[$key]['fr'] ?? $key;
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExpensePro - <?= lt('main_title') ?></title>
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
            background: transparent;
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
        
        .logo-text span {
            color: var(--secondary);
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        
        .header-nav {
            display: flex;
            gap: 32px;
        }
        
        .header-nav a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .header-nav a:hover {
            color: white;
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
        }
        
        .lang-switch:hover {
            background: rgba(255,255,255,0.2);
        }
        
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
            0% {
                transform: translateY(-10px) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0.3;
            }
        }
        
        /* Nuages/formes décoratives en bas */
        .cloud-decoration {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 200px;
            pointer-events: none;
        }
        
        .cloud {
            position: absolute;
            bottom: -50px;
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 50%;
            opacity: 0.6;
            animation: float 6s ease-in-out infinite;
        }
        
        .cloud::before,
        .cloud::after {
            content: '';
            position: absolute;
            background: inherit;
            border-radius: 50%;
        }
        
        .cloud::before {
            width: 150px;
            height: 150px;
            top: -60px;
            left: 20px;
        }
        
        .cloud::after {
            width: 120px;
            height: 120px;
            top: -40px;
            right: 20px;
        }
        
        .cloud-1 {
            left: -100px;
            animation-delay: 0s;
        }
        
        .cloud-2 {
            right: -100px;
            animation-delay: 3s;
        }
        
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
        
        .circle-1 {
            width: 300px;
            height: 300px;
            bottom: -150px;
            left: 10%;
        }
        
        .circle-2 {
            width: 200px;
            height: 200px;
            bottom: -100px;
            right: 15%;
            animation-delay: 2s;
        }
        
        .circle-3 {
            width: 150px;
            height: 150px;
            bottom: -50px;
            left: 40%;
            animation-delay: 1s;
        }
        
        @keyframes pulse-circle {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.05); }
        }
        
        .tagline {
            color: var(--orange);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 24px;
            letter-spacing: 0.5px;
        }
        
        .hero h1 {
            color: white;
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 24px;
            line-height: 1.1;
            max-width: 800px;
        }
        
        .hero-description {
            color: rgba(255,255,255,0.7);
            font-size: 18px;
            max-width: 700px;
            line-height: 1.7;
            margin-bottom: 40px;
        }
        
        .hero-description .highlight {
            color: var(--secondary);
        }
        
        .hero-description .highlight-orange {
            color: var(--orange);
        }
        
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
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
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
        
        /* Login Panel - Slide in from right */
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
            right: -550px;
            width: 100%;
            max-width: 550px;
            height: 100vh;
            background: white;
            z-index: 201;
            transition: right 0.4s ease;
            display: flex;
            overflow: hidden;
        }
        
        .login-panel.active {
            right: 0;
        }
        
        .login-panel-left {
            width: 50%;
            background: linear-gradient(135deg, #1F2937 0%, #374151 100%);
            padding: 40px 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .login-panel-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0,102,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(10%, 10%); }
        }
        
        .login-panel-left-content {
            position: relative;
            z-index: 1;
        }
        
        .login-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
        }
        
        .login-brand-logo {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
        }
        
        .login-brand-name {
            font-size: 24px;
            font-weight: 700;
        }
        
        .login-brand-name span {
            color: var(--secondary);
        }
        
        .login-panel-left h2 {
            font-size: 20px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .login-panel-left p {
            font-size: 13px;
            color: rgba(255,255,255,0.7);
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .features-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(0,102,255,0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .feature-text h4 {
            font-size: 13px;
            margin-bottom: 2px;
        }
        
        .feature-text p {
            font-size: 11px;
            margin: 0;
            color: rgba(255,255,255,0.6);
        }
        
        .login-panel-right {
            width: 50%;
            padding: 40px 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
        }
        
        .close-login {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray-500);
            z-index: 10;
        }
        
        .login-form h1 {
            font-size: 24px;
            color: var(--gray-900);
            margin-bottom: 8px;
        }
        
        .login-form .subtitle {
            color: var(--gray-500);
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 6px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
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
            padding: 12px 12px 12px 42px;
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
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .remember input {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
        }
        
        .remember span {
            font-size: 13px;
            color: var(--gray-700);
        }
        
        .forgot-link {
            font-size: 13px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .forgot-link:hover {
            text-decoration: underline;
        }
        
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
        
        .demo-accounts {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-100);
        }
        
        .demo-accounts h4 {
            font-size: 11px;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        
        .demo-account {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background: var(--gray-100);
            border-radius: 8px;
            margin-bottom: 6px;
            font-size: 11px;
        }
        
        .demo-account strong {
            color: var(--gray-900);
        }
        
        .demo-account span {
            color: var(--gray-500);
        }
        
        /* Language selector in header */
        .lang-dropdown {
            position: relative;
        }
        
        .lang-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            min-width: 150px;
            display: none;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .lang-dropdown:hover .lang-menu {
            display: block;
        }
        
        .lang-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: var(--gray-700);
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
        }
        
        .lang-menu a:hover {
            background: var(--gray-100);
        }
        
        .lang-menu a.active {
            background: rgba(0,102,255,0.1);
            color: var(--primary);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-nav {
                display: none;
            }
            
            .hero h1 {
                font-size: 36px;
            }
            
            .login-panel {
                max-width: 100%;
            }
            
            .login-panel-left {
                display: none;
            }
            
            .login-panel-right {
                width: 100%;
            }
            
            .features-preview {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <a href="index.php" class="logo">
            <div class="logo-icon">E</div>
            <div class="logo-text">Expense<span>Pro</span></div>
        </a>
        
        <div class="header-right">
            <nav class="header-nav">
                <a href="#features">Features</a>
                <a href="#pricing">Pricing</a>
                <a href="#about">About</a>
            </nav>
            
            <div class="lang-dropdown">
                <button class="lang-switch">
                    <?= $current_lang === 'fr' ? '🇫🇷 FR' : '🇬🇧 EN' ?>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="lang-menu">
                    <a href="?lang=fr" class="<?= $current_lang === 'fr' ? 'active' : '' ?>">🇫🇷 Français</a>
                    <a href="?lang=en" class="<?= $current_lang === 'en' ? 'active' : '' ?>">🇬🇧 English</a>
                </div>
            </div>
            
            <button class="btn-login-header" onclick="openLogin()"><?= lt('login_btn') ?></button>
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
        
        <p class="tagline"><?= lt('tagline') ?></p>
        <h1><?= lt('main_title') ?></h1>
        <p class="hero-description">
            <?= str_replace(
                ['efficiency', 'controls', 'cost savings', 'efficacité', 'contrôle', 'économies'],
                ['<span class="highlight">efficiency</span>', '<span class="highlight-orange">controls</span>', '<span class="highlight-orange">cost savings</span>', '<span class="highlight">efficacité</span>', '<span class="highlight-orange">contrôle</span>', '<span class="highlight-orange">économies</span>'],
                lt('description')
            ) ?>
        </p>
        
        <button class="btn-login-header" onclick="openLogin()" style="font-size: 16px; padding: 16px 32px;">
            <?= lt('login_btn') ?>
        </button>
        
        <!-- Feature cards -->
        <div class="features-preview">
            <div class="feature-card">
                <div class="feature-card-icon"><i class="fas fa-credit-card"></i></div>
                <h4><?= lt('expense_cards') ?></h4>
            </div>
            <div class="feature-card">
                <div class="feature-card-icon"><i class="fas fa-chart-bar"></i></div>
                <h4><?= lt('reporting_dashboard') ?></h4>
            </div>
            <div class="feature-card">
                <div class="feature-card-icon"><i class="fas fa-chart-pie"></i></div>
                <h4><?= lt('personal_spend') ?></h4>
            </div>
            <div class="feature-card">
                <div class="feature-card-icon"><i class="fas fa-users"></i></div>
                <h4><?= lt('top_approvers') ?></h4>
            </div>
        </div>
    </section>
    
    <!-- Login Overlay -->
    <div class="login-overlay <?= $showLogin ? 'active' : '' ?>" onclick="closeLogin()"></div>
    
    <!-- Login Panel -->
    <div class="login-panel <?= $showLogin ? 'active' : '' ?>" id="loginPanel">
        <button class="close-login" onclick="closeLogin()">&times;</button>
        
        <div class="login-panel-left">
            <div class="login-panel-left-content">
                <div class="login-brand">
                    <div class="login-brand-logo">E</div>
                    <div class="login-brand-name">Expense<span>Pro</span></div>
                </div>
                
                <h2><?= lt('manage_subtitle') ?></h2>
                <p><?= lt('manage_desc') ?></p>
                
                <div class="features-list">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-rocket"></i></div>
                        <div class="feature-text">
                            <h4><?= lt('feature1_title') ?></h4>
                            <p><?= lt('feature1_desc') ?></p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-camera"></i></div>
                        <div class="feature-text">
                            <h4><?= lt('feature2_title') ?></h4>
                            <p><?= lt('feature2_desc') ?></p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="feature-text">
                            <h4><?= lt('feature3_title') ?></h4>
                            <p><?= lt('feature3_desc') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="login-panel-right">
            <div class="login-form">
                <h1><?= lt('welcome') ?> 👋</h1>
                <p class="subtitle"><?= lt('connect_account') ?></p>
                
                <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="index.php">
                    <div class="form-group">
                        <label class="form-label"><?= lt('email_label') ?></label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" class="form-control" placeholder="votre@email.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><?= lt('password_label') ?></label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <label class="remember">
                            <input type="checkbox" name="remember">
                            <span><?= lt('remember_me') ?></span>
                        </label>
                        <a href="#" class="forgot-link"><?= lt('forgot_password') ?></a>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <span><?= lt('login_btn') ?></span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
                
                <div class="demo-accounts">
                    <h4><?= lt('demo_accounts') ?></h4>
                    <div class="demo-account">
                        <strong>Admin</strong>
                        <span>admin@societe.com / admin123</span>
                    </div>
                    <div class="demo-account">
                        <strong>Manager</strong>
                        <span>youssef.benali@societe.com / manager123</span>
                    </div>
                    <div class="demo-account">
                        <strong><?= $current_lang === 'fr' ? 'Employé' : 'Employee' ?></strong>
                        <span>fatima.idrissi@societe.com / employe123</span>
                    </div>
                </div>
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
        
        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLogin();
            }
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            createStars();
            createSnowflakes();
            
            <?php if ($showLogin): ?>
            // Keep login panel open if there's an error or form was submitted
            openLogin();
            <?php endif; ?>
        });
    </script>
</body>
</html>
