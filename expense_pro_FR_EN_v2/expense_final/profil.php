<?php
/**
 * ExpensePro - Profil Utilisateur
 */

require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

$pageTitle = __('profile');
$userId = $_SESSION['user_id'];

// Get user info
$userQuery = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userQuery->execute([$userId]);
$user = $userQuery->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_profile') {
            $nom = sanitize($_POST['nom']);
            $prenom = sanitize($_POST['prenom']);
            $email = sanitize($_POST['email']);
            
            $stmt = $pdo->prepare("UPDATE users SET nom = ?, prenom = ?, email = ? WHERE id = ?");
            $stmt->execute([$nom, $prenom, $email, $userId]);
            
            $_SESSION['nom'] = $nom;
            $_SESSION['prenom'] = $prenom;
            $_SESSION['email'] = $email;
            
            flashMessage('success', 'Profil mis à jour avec succès');
            
        } elseif ($action === 'update_password') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if (!password_verify($currentPassword, $user['password'])) {
                throw new Exception('Mot de passe actuel incorrect');
            }
            
            if ($newPassword !== $confirmPassword) {
                throw new Exception('Les mots de passe ne correspondent pas');
            }
            
            if (strlen($newPassword) < 6) {
                throw new Exception('Le mot de passe doit faire au moins 6 caractères');
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            
            flashMessage('success', 'Mot de passe modifié avec succès');
            
        } elseif ($action === 'update_photo') {
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($_FILES['photo']['type'], $allowedTypes)) {
                    throw new Exception('Type de fichier non autorisé');
                }
                
                $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $fileName = 'user_' . $userId . '_' . time() . '.' . $extension;
                $destination = 'uploads/profiles/' . $fileName;
                
                if (!is_dir('uploads/profiles')) {
                    mkdir('uploads/profiles', 0755, true);
                }
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                    $stmt = $pdo->prepare("UPDATE users SET photo_profil = ? WHERE id = ?");
                    $stmt->execute([$fileName, $userId]);
                    $_SESSION['photo_profil'] = $fileName;
                    flashMessage('success', 'Photo de profil mise à jour');
                }
            }
        }
        
    } catch (Exception $e) {
        flashMessage('error', $e->getMessage());
    }
    
    header('Location: profil.php');
    exit;
}

// Get user stats
$statsQuery = $pdo->prepare("
    SELECT 
        COUNT(*) as total_demandes,
        SUM(CASE WHEN statut = 'approuvee_admin' THEN 1 ELSE 0 END) as approuvees,
        COALESCE(SUM(CASE WHEN statut = 'approuvee_admin' THEN montant_total ELSE 0 END), 0) as total_rembourse
    FROM demandes WHERE user_id = ?
");
$statsQuery->execute([$userId]);
$stats = $statsQuery->fetch();

include 'includes/header.php';
?>

<div style="display: grid; grid-template-columns: 300px 1fr; gap: 24px;">
    <!-- Left Column - Profile Card -->
    <div>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 32px;">
                <div style="width: 120px; height: 120px; border-radius: 50%; margin: 0 auto 20px; overflow: hidden; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 40px; font-weight: 600;">
                    <?php if ($user['photo_profil']): ?>
                    <img src="uploads/profiles/<?= htmlspecialchars($user['photo_profil']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                    <?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                
                <h2 style="font-size: 20px; margin-bottom: 4px;"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h2>
                <p style="color: var(--gray-500); margin-bottom: 16px;"><?= htmlspecialchars($user['email']) ?></p>
                
                <span class="status-badge" style="background: <?= $user['role'] === 'admin' ? 'var(--danger)' : ($user['role'] === 'manager' ? 'var(--warning)' : 'var(--primary)') ?>">
                    <?= ucfirst($user['role']) ?>
                </span>
                
                <form method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="update_photo">
                    <label class="btn btn-outline btn-sm" style="cursor: pointer;">
                        <i class="fas fa-camera"></i> Changer la photo
                        <input type="file" name="photo" accept="image/*" style="display: none;" onchange="this.form.submit()">
                    </label>
                </form>
            </div>
            
            <div class="card-footer" style="display: grid; grid-template-columns: repeat(3, 1fr); text-align: center; gap: 16px;">
                <div>
                    <div style="font-size: 24px; font-weight: 700; color: var(--primary);"><?= $stats['total_demandes'] ?></div>
                    <div style="font-size: 12px; color: var(--gray-500);">Demandes</div>
                </div>
                <div>
                    <div style="font-size: 24px; font-weight: 700; color: var(--success);"><?= $stats['approuvees'] ?></div>
                    <div style="font-size: 12px; color: var(--gray-500);">Approuvées</div>
                </div>
                <div>
                    <div style="font-size: 18px; font-weight: 700; color: var(--gray-900);"><?= formatMoney($stats['total_rembourse']) ?></div>
                    <div style="font-size: 12px; color: var(--gray-500);">Remboursé</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right Column - Settings -->
    <div>
        <!-- Profile Info -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user"></i> Informations personnelles</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Prénom</label>
                            <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($user['prenom']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nom</label>
                            <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($user['nom']) ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Password -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-lock"></i> Changer le mot de passe</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_password">
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Mot de passe actuel</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirmer</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i> Modifier le mot de passe
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>