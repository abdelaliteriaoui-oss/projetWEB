<?php
/**
 * ExpensePro - Gestion des Utilisateurs
 * Admin user management page
 */

require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = __('nav_users');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $nom = sanitize($_POST['nom']);
            $prenom = sanitize($_POST['prenom']);
            $email = sanitize($_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = $_POST['role'];
            $managerId = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
            
            $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email, password, role, manager_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $prenom, $email, $password, $role, $managerId]);
            flashMessage('success', 'Utilisateur créé avec succès');
            
        } elseif ($action === 'update') {
            $id = intval($_POST['user_id']);
            $nom = sanitize($_POST['nom']);
            $prenom = sanitize($_POST['prenom']);
            $email = sanitize($_POST['email']);
            $role = $_POST['role'];
            $managerId = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
            $actif = isset($_POST['actif']) ? 1 : 0;
            
            $sql = "UPDATE users SET nom = ?, prenom = ?, email = ?, role = ?, manager_id = ?, actif = ?";
            $params = [$nom, $prenom, $email, $role, $managerId, $actif];
            
            if (!empty($_POST['password'])) {
                $sql .= ", password = ?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            flashMessage('success', 'Utilisateur mis à jour avec succès');
            
        } elseif ($action === 'delete') {
            $id = intval($_POST['user_id']);
            if ($id !== $_SESSION['user_id']) {
                $stmt = $pdo->prepare("UPDATE users SET actif = 0 WHERE id = ?");
                $stmt->execute([$id]);
                flashMessage('success', 'Utilisateur désactivé');
            }
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            flashMessage('error', 'Cet email est déjà utilisé');
        } else {
            flashMessage('error', 'Erreur: ' . $e->getMessage());
        }
    }
    
    header('Location: gestion_utilisateurs.php');
    exit;
}

// Get all users
$users = $pdo->query("
    SELECT u.*, m.nom as manager_nom, m.prenom as manager_prenom,
           (SELECT COUNT(*) FROM demandes WHERE user_id = u.id) as nb_demandes
    FROM users u
    LEFT JOIN users m ON u.manager_id = m.id
    ORDER BY u.role DESC, u.nom ASC
")->fetchAll();

// Get managers for dropdown
$managers = $pdo->query("SELECT id, nom, prenom FROM users WHERE role = 'manager' AND actif = 1 ORDER BY nom")->fetchAll();

// Stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM users WHERE actif = 1")->fetchColumn(),
    'admins' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND actif = 1")->fetchColumn(),
    'managers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager' AND actif = 1")->fetchColumn(),
    'employes' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employe' AND actif = 1")->fetchColumn(),
];

include 'includes/header.php';
?>

<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 24px;">
    <div class="stat-card" style="padding: 16px;">
        <div class="stat-value" style="font-size: 24px;"><?= $stats['total'] ?></div>
        <div class="stat-label">Total utilisateurs</div>
    </div>
    <div class="stat-card" style="padding: 16px;">
        <div class="stat-value" style="font-size: 24px;"><?= $stats['admins'] ?></div>
        <div class="stat-label">Administrateurs</div>
    </div>
    <div class="stat-card" style="padding: 16px;">
        <div class="stat-value" style="font-size: 24px;"><?= $stats['managers'] ?></div>
        <div class="stat-label">Managers</div>
    </div>
    <div class="stat-card" style="padding: 16px;">
        <div class="stat-value" style="font-size: 24px;"><?= $stats['employes'] ?></div>
        <div class="stat-label">Employés</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-users"></i>
            Liste des utilisateurs
        </h3>
        <button class="btn btn-primary" onclick="showModal('create')">
            <i class="fas fa-plus"></i> Nouvel utilisateur
        </button>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Manager</th>
                    <th>Demandes</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr style="<?= !$user['actif'] ? 'opacity: 0.5;' : '' ?>">
                    <td>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width:40px;height:40px;border-radius:50%;background:<?= $user['role'] === 'admin' ? 'var(--danger)' : ($user['role'] === 'manager' ? 'var(--warning)' : 'var(--primary)') ?>;display:flex;align-items:center;justify-content:center;color:white;font-weight:600;font-size:14px;">
                                <?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight: 600;"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
                                <div style="font-size: 12px; color: var(--gray-500);">ID: <?= $user['id'] ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td>
                        <span class="status-badge" style="background: <?= $user['role'] === 'admin' ? 'var(--danger)' : ($user['role'] === 'manager' ? 'var(--warning)' : 'var(--primary)') ?>">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($user['manager_nom']): ?>
                        <?= htmlspecialchars($user['manager_prenom'] . ' ' . $user['manager_nom']) ?>
                        <?php else: ?>
                        <span style="color: var(--gray-400);">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $user['nb_demandes'] ?></td>
                    <td>
                        <?php if ($user['actif']): ?>
                        <span style="color: var(--success);"><i class="fas fa-check-circle"></i> Actif</span>
                        <?php else: ?>
                        <span style="color: var(--danger);"><i class="fas fa-times-circle"></i> Inactif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="table-actions">
                            <button class="btn btn-ghost btn-icon" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                            <button class="btn btn-ghost btn-icon" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>')" title="Désactiver">
                                <i class="fas fa-trash" style="color: var(--danger);"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- User Modal -->
<div class="modal-overlay" id="user-modal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-title">Nouvel utilisateur</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="user-form">
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="user_id" id="form-user-id" value="">
            
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label required">Prénom</label>
                        <input type="text" name="prenom" id="form-prenom" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Nom</label>
                        <input type="text" name="nom" id="form-nom" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Email</label>
                    <input type="email" name="email" id="form-email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" id="password-label">Mot de passe</label>
                    <input type="password" name="password" id="form-password" class="form-control">
                    <div class="form-text" id="password-help">Minimum 6 caractères</div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label required">Rôle</label>
                        <select name="role" id="form-role" class="form-control form-select" required onchange="toggleManagerField()">
                            <option value="employe">Employé</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                    <div class="form-group" id="manager-field">
                        <label class="form-label">Manager</label>
                        <select name="manager_id" id="form-manager" class="form-control form-select">
                            <option value="">— Aucun —</option>
                            <?php foreach ($managers as $manager): ?>
                            <option value="<?= $manager['id'] ?>"><?= htmlspecialchars($manager['prenom'] . ' ' . $manager['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group" id="actif-field" style="display: none;">
                    <label class="remember">
                        <input type="checkbox" name="actif" id="form-actif" checked>
                        <span>Compte actif</span>
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="delete-form" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="delete-user-id">
</form>

<script>
function showModal(mode) {
    document.getElementById('modal-title').textContent = mode === 'create' ? 'Nouvel utilisateur' : 'Modifier utilisateur';
    document.getElementById('form-action').value = mode;
    document.getElementById('password-label').classList.toggle('required', mode === 'create');
    document.getElementById('form-password').required = mode === 'create';
    document.getElementById('password-help').textContent = mode === 'create' ? 'Minimum 6 caractères' : 'Laisser vide pour conserver l\'actuel';
    document.getElementById('actif-field').style.display = mode === 'update' ? 'block' : 'none';
    document.getElementById('user-modal').classList.add('active');
}

function closeModal() {
    document.getElementById('user-modal').classList.remove('active');
    document.getElementById('user-form').reset();
}

function editUser(user) {
    document.getElementById('form-user-id').value = user.id;
    document.getElementById('form-prenom').value = user.prenom;
    document.getElementById('form-nom').value = user.nom;
    document.getElementById('form-email').value = user.email;
    document.getElementById('form-role').value = user.role;
    document.getElementById('form-manager').value = user.manager_id || '';
    document.getElementById('form-actif').checked = user.actif == 1;
    showModal('update');
    toggleManagerField();
}

function deleteUser(id, name) {
    if (confirm('Désactiver l\'utilisateur ' + name + ' ?')) {
        document.getElementById('delete-user-id').value = id;
        document.getElementById('delete-form').submit();
    }
}

function toggleManagerField() {
    const role = document.getElementById('form-role').value;
    document.getElementById('manager-field').style.display = role === 'employe' ? 'block' : 'none';
}
</script>

<?php include 'includes/footer.php'; ?>
