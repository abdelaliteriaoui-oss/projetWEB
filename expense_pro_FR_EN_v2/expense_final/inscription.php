<?php
/**
 * ExpensePro - Inscription
 * User registration page with multi-step form
 */

require_once 'includes/config.php';

// Rediriger si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Inscription';
$errors = [];
$success = false;

// Récupérer la liste des managers pour l'affectation
$managers = $pdo->query("SELECT id, nom, prenom, email FROM users WHERE role = 'manager' ORDER BY nom, prenom")->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = sanitize($_POST['nom'] ?? '');
    $prenom = sanitize($_POST['prenom'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $telephone = sanitize($_POST['telephone'] ?? '');
    $departement = sanitize($_POST['departement'] ?? '');
    $poste = sanitize($_POST['poste'] ?? '');
    $manager_id = intval($_POST['manager_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validation
    if (empty($nom)) $errors[] = "Le nom est obligatoire";
    if (empty($prenom)) $errors[] = "Le prénom est obligatoire";
    if (empty($email)) $errors[] = "L'email est obligatoire";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "L'email n'est pas valide";
    if (empty($password)) $errors[] = "Le mot de passe est obligatoire";
    if (strlen($password) < 8) $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
    if ($password !== $password_confirm) $errors[] = "Les mots de passe ne correspondent pas";
    
    // Vérifier si l'email existe déjà
    $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) {
        $errors[] = "Cet email est déjà utilisé";
    }
    
    // Si pas d'erreurs, créer le compte
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (nom, prenom, email, telephone, departement, poste, manager_id, password, role, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'employe', NOW())
            ");
            $stmt->execute([$nom, $prenom, $email, $telephone, $departement, $poste, $manager_id ?: null, $hashedPassword]);
            
            $success = true;
            
            // Envoyer notification au manager
            if ($manager_id) {
                $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notifStmt->execute([$manager_id, "Nouvel employé inscrit : $prenom $nom"]);
            }
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors de l'inscription. Veuillez réessayer.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - ExpensePro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .register-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        .register-left {
            flex: 1;
            background: linear-gradient(135deg, #0066FF 0%, #5B4FFF 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .register-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .register-left-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 400px;
        }
        .register-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 700;
            color: #0066FF;
            margin: 0 auto 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .register-left h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .register-left p {
            font-size: 16px;
            opacity: 0.9;
            line-height: 1.6;
        }
        .features-list {
            margin-top: 40px;
            text-align: left;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            padding: 16px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        .feature-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .feature-text h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .feature-text p {
            font-size: 12px;
            opacity: 0.8;
            margin: 0;
        }
        .register-right {
            flex: 1;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            overflow-y: auto;
        }
        .register-form-container {
            width: 100%;
            max-width: 500px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .register-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
        }
        .register-header p {
            color: var(--gray-500);
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 32px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            background: var(--gray-100);
            color: var(--gray-400);
            transition: all 0.3s;
        }
        .step.active {
            background: var(--primary);
            color: white;
        }
        .step.completed {
            background: #10B981;
            color: white;
        }
        .step-line {
            width: 40px;
            height: 2px;
            background: var(--gray-200);
            align-self: center;
        }
        .step-line.completed {
            background: #10B981;
        }
        .form-step {
            display: none;
        }
        .form-step.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            background: var(--gray-200);
            margin-top: 8px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0;
            border-radius: 2px;
            transition: all 0.3s;
        }
        .password-strength-bar.weak { width: 33%; background: #EF4444; }
        .password-strength-bar.medium { width: 66%; background: #F59E0B; }
        .password-strength-bar.strong { width: 100%; background: #10B981; }
        .password-requirements {
            margin-top: 12px;
            font-size: 12px;
            color: var(--gray-500);
        }
        .password-requirements li {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        .password-requirements li.valid {
            color: #10B981;
        }
        .password-requirements li i {
            width: 14px;
        }
        .btn-navigation {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .btn-navigation .btn {
            flex: 1;
        }
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .alert-error {
            background: #FEE2E2;
            color: #DC2626;
        }
        .alert-success {
            background: #D1FAE5;
            color: #059669;
        }
        .alert i {
            font-size: 20px;
            margin-top: 2px;
        }
        .alert ul {
            margin: 0;
            padding-left: 20px;
        }
        .login-link {
            text-align: center;
            margin-top: 24px;
            color: var(--gray-500);
        }
        .login-link a {
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
        }
        .success-animation {
            text-align: center;
            padding: 40px;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #D1FAE5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 40px;
            color: #059669;
            animation: scaleIn 0.5s ease;
        }
        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        @media (max-width: 900px) {
            .register-left {
                display: none;
            }
            .register-right {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Left Side - Branding -->
        <div class="register-left">
            <div class="register-left-content">
                <div class="register-logo">E</div>
                <h1>ExpensePro</h1>
                <p>La solution moderne de gestion des notes de frais pour les entreprises</p>
                
                <div class="features-list">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-camera"></i></div>
                        <div class="feature-text">
                            <h4>Scan intelligent OCR</h4>
                            <p>Photographiez vos reçus, l'IA extrait les données</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-route"></i></div>
                        <div class="feature-text">
                            <h4>Calcul kilométrique GPS</h4>
                            <p>Tracking automatique de vos déplacements</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-credit-card"></i></div>
                        <div class="feature-text">
                            <h4>Carte corporate intégrée</h4>
                            <p>Synchronisation temps réel des transactions</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="feature-text">
                            <h4>Analytics avancés</h4>
                            <p>Tableaux de bord et rapports personnalisés</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Side - Form -->
        <div class="register-right">
            <div class="register-form-container">
                <?php if ($success): ?>
                <div class="success-animation">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2 style="margin-bottom: 12px;">Inscription réussie !</h2>
                    <p style="color: var(--gray-500); margin-bottom: 24px;">
                        Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.
                    </p>
                    <a href="login.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt"></i> Se connecter
                    </a>
                </div>
                <?php else: ?>
                
                <div class="register-header">
                    <h2>Créer un compte</h2>
                    <p>Rejoignez ExpensePro en quelques étapes</p>
                </div>
                
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step active" id="step-1">1</div>
                    <div class="step-line" id="line-1"></div>
                    <div class="step" id="step-2">2</div>
                    <div class="step-line" id="line-2"></div>
                    <div class="step" id="step-3">3</div>
                </div>
                
                <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Erreur(s) :</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="registerForm">
                    <!-- Étape 1: Informations personnelles -->
                    <div class="form-step active" id="form-step-1">
                        <h3 style="margin-bottom: 20px; font-size: 18px;">Informations personnelles</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Prénom *</label>
                                <input type="text" name="prenom" class="form-control" required 
                                       value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>"
                                       placeholder="Votre prénom">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Nom *</label>
                                <input type="text" name="nom" class="form-control" required 
                                       value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                                       placeholder="Votre nom">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email professionnel *</label>
                            <input type="email" name="email" class="form-control" required 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   placeholder="prenom.nom@entreprise.com">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" name="telephone" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>"
                                   placeholder="+33 6 12 34 56 78">
                        </div>
                        
                        <div class="btn-navigation">
                            <button type="button" class="btn btn-primary" onclick="nextStep(1)">
                                Continuer <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Étape 2: Informations professionnelles -->
                    <div class="form-step" id="form-step-2">
                        <h3 style="margin-bottom: 20px; font-size: 18px;">Informations professionnelles</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Département</label>
                            <select name="departement" class="form-control">
                                <option value="">Sélectionner...</option>
                                <option value="Commercial" <?= ($_POST['departement'] ?? '') === 'Commercial' ? 'selected' : '' ?>>Commercial</option>
                                <option value="Marketing" <?= ($_POST['departement'] ?? '') === 'Marketing' ? 'selected' : '' ?>>Marketing</option>
                                <option value="Technique" <?= ($_POST['departement'] ?? '') === 'Technique' ? 'selected' : '' ?>>Technique</option>
                                <option value="Finance" <?= ($_POST['departement'] ?? '') === 'Finance' ? 'selected' : '' ?>>Finance</option>
                                <option value="RH" <?= ($_POST['departement'] ?? '') === 'RH' ? 'selected' : '' ?>>Ressources Humaines</option>
                                <option value="Direction" <?= ($_POST['departement'] ?? '') === 'Direction' ? 'selected' : '' ?>>Direction</option>
                                <option value="Autre" <?= ($_POST['departement'] ?? '') === 'Autre' ? 'selected' : '' ?>>Autre</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Poste / Fonction</label>
                            <input type="text" name="poste" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['poste'] ?? '') ?>"
                                   placeholder="Ex: Chef de projet, Consultant...">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Manager / Responsable</label>
                            <select name="manager_id" class="form-control">
                                <option value="">Sélectionner votre manager...</option>
                                <?php foreach ($managers as $manager): ?>
                                <option value="<?= $manager['id'] ?>" <?= ($_POST['manager_id'] ?? '') == $manager['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($manager['prenom'] . ' ' . $manager['nom']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-hint">Votre manager validera vos demandes de remboursement</span>
                        </div>
                        
                        <div class="btn-navigation">
                            <button type="button" class="btn btn-ghost" onclick="prevStep(2)">
                                <i class="fas fa-arrow-left"></i> Retour
                            </button>
                            <button type="button" class="btn btn-primary" onclick="nextStep(2)">
                                Continuer <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Étape 3: Sécurité -->
                    <div class="form-step" id="form-step-3">
                        <h3 style="margin-bottom: 20px; font-size: 18px;">Créer votre mot de passe</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Mot de passe *</label>
                            <div style="position: relative;">
                                <input type="password" name="password" id="password" class="form-control" required 
                                       placeholder="Minimum 8 caractères"
                                       oninput="checkPasswordStrength(this.value)">
                                <button type="button" class="btn btn-ghost btn-icon" onclick="togglePassword('password')" 
                                        style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%);">
                                    <i class="fas fa-eye" id="password-icon"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="password-strength-bar"></div>
                            </div>
                            <ul class="password-requirements">
                                <li id="req-length"><i class="fas fa-circle"></i> Au moins 8 caractères</li>
                                <li id="req-upper"><i class="fas fa-circle"></i> Une lettre majuscule</li>
                                <li id="req-lower"><i class="fas fa-circle"></i> Une lettre minuscule</li>
                                <li id="req-number"><i class="fas fa-circle"></i> Un chiffre</li>
                            </ul>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirmer le mot de passe *</label>
                            <div style="position: relative;">
                                <input type="password" name="password_confirm" id="password_confirm" class="form-control" required 
                                       placeholder="Retapez votre mot de passe">
                                <button type="button" class="btn btn-ghost btn-icon" onclick="togglePassword('password_confirm')" 
                                        style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%);">
                                    <i class="fas fa-eye" id="password_confirm-icon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                                <input type="checkbox" name="terms" required style="margin-top: 4px;">
                                <span style="font-size: 13px; color: var(--gray-600);">
                                    J'accepte les <a href="#" style="color: var(--primary);">conditions d'utilisation</a> 
                                    et la <a href="#" style="color: var(--primary);">politique de confidentialité</a>
                                </span>
                            </label>
                        </div>
                        
                        <div class="btn-navigation">
                            <button type="button" class="btn btn-ghost" onclick="prevStep(3)">
                                <i class="fas fa-arrow-left"></i> Retour
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Créer mon compte
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="login-link">
                    Déjà inscrit ? <a href="login.php">Se connecter</a>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        let currentStep = 1;
        
        function nextStep(step) {
            // Validation basique avant de passer à l'étape suivante
            const currentForm = document.getElementById('form-step-' + step);
            const inputs = currentForm.querySelectorAll('input[required]');
            let valid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.style.borderColor = 'var(--danger)';
                    valid = false;
                } else {
                    input.style.borderColor = '';
                }
            });
            
            if (!valid) return;
            
            // Passer à l'étape suivante
            document.getElementById('form-step-' + step).classList.remove('active');
            document.getElementById('form-step-' + (step + 1)).classList.add('active');
            
            // Mettre à jour l'indicateur
            document.getElementById('step-' + step).classList.remove('active');
            document.getElementById('step-' + step).classList.add('completed');
            document.getElementById('step-' + step).innerHTML = '<i class="fas fa-check"></i>';
            document.getElementById('line-' + step).classList.add('completed');
            document.getElementById('step-' + (step + 1)).classList.add('active');
            
            currentStep = step + 1;
        }
        
        function prevStep(step) {
            document.getElementById('form-step-' + step).classList.remove('active');
            document.getElementById('form-step-' + (step - 1)).classList.add('active');
            
            document.getElementById('step-' + step).classList.remove('active');
            document.getElementById('step-' + (step - 1)).classList.remove('completed');
            document.getElementById('step-' + (step - 1)).classList.add('active');
            document.getElementById('step-' + (step - 1)).innerHTML = (step - 1);
            document.getElementById('line-' + (step - 1)).classList.remove('completed');
            
            currentStep = step - 1;
        }
        
        function checkPasswordStrength(password) {
            let strength = 0;
            const bar = document.getElementById('password-strength-bar');
            
            // Vérifier les critères
            const hasLength = password.length >= 8;
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            
            // Mettre à jour les indicateurs
            updateRequirement('req-length', hasLength);
            updateRequirement('req-upper', hasUpper);
            updateRequirement('req-lower', hasLower);
            updateRequirement('req-number', hasNumber);
            
            // Calculer la force
            if (hasLength) strength++;
            if (hasUpper) strength++;
            if (hasLower) strength++;
            if (hasNumber) strength++;
            
            // Mettre à jour la barre
            bar.className = 'password-strength-bar';
            if (strength >= 4) {
                bar.classList.add('strong');
            } else if (strength >= 2) {
                bar.classList.add('medium');
            } else if (strength >= 1) {
                bar.classList.add('weak');
            }
        }
        
        function updateRequirement(id, valid) {
            const el = document.getElementById(id);
            if (valid) {
                el.classList.add('valid');
                el.querySelector('i').className = 'fas fa-check-circle';
            } else {
                el.classList.remove('valid');
                el.querySelector('i').className = 'fas fa-circle';
            }
        }
        
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = document.getElementById(id + '-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
    </script>
</body>
</html>
