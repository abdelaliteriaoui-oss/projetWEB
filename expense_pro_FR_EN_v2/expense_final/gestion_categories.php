<?php
require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    flashMessage('error', __('unauthorized_access'));
    header('Location: dashboard.php');
    exit;
}

$pageTitle = __('nav_categories');

// Vérifier si la colonne plafond existe
$hasPlafond = false;
try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM categories_frais LIKE 'plafond'");
    $hasPlafond = $checkCol->rowCount() > 0;
} catch (Exception $e) {
    $hasPlafond = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            if ($hasPlafond) {
                $stmt = $pdo->prepare("INSERT INTO categories_frais (nom, description, plafond) VALUES (?, ?, ?)");
                $stmt->execute([sanitize($_POST['nom']), sanitize($_POST['description']), floatval($_POST['plafond'] ?? 0)]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories_frais (nom, description) VALUES (?, ?)");
                $stmt->execute([sanitize($_POST['nom']), sanitize($_POST['description'])]);
            }
            flashMessage('success', __('category_added'));
        } elseif ($action === 'edit') {
            if ($hasPlafond) {
                $stmt = $pdo->prepare("UPDATE categories_frais SET nom = ?, description = ?, plafond = ?, actif = ? WHERE id = ?");
                $stmt->execute([sanitize($_POST['nom']), sanitize($_POST['description']), floatval($_POST['plafond'] ?? 0), isset($_POST['actif']) ? 1 : 0, intval($_POST['id'])]);
            } else {
                $stmt = $pdo->prepare("UPDATE categories_frais SET nom = ?, description = ?, actif = ? WHERE id = ?");
                $stmt->execute([sanitize($_POST['nom']), sanitize($_POST['description']), isset($_POST['actif']) ? 1 : 0, intval($_POST['id'])]);
            }
            flashMessage('success', __('category_updated'));
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM categories_frais WHERE id = ?");
            $stmt->execute([intval($_POST['id'])]);
            flashMessage('success', __('category_deleted'));
        }
    } catch (Exception $e) {
        flashMessage('error', $e->getMessage());
    }
    header('Location: gestion_categories.php');
    exit;
}

$categories = $pdo->query("SELECT cf.*, COUNT(df.id) as nb_utilisations FROM categories_frais cf LEFT JOIN details_frais df ON cf.id = df.categorie_id GROUP BY cf.id ORDER BY cf.nom")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h2><?= __('category_management') ?></h2>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">
        <i class="fas fa-plus"></i> <?= __('new_category') ?>
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <table class="table">
            <thead>
                <tr>
                    <th><?= __('name') ?></th>
                    <th><?= __('description') ?></th>
                    <?php if ($hasPlafond): ?><th><?= __('ceiling') ?></th><?php endif; ?>
                    <th><?= __('usages') ?></th>
                    <th><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td style="font-weight: 600;"><?= htmlspecialchars($cat['nom']) ?></td>
                    <td><?= htmlspecialchars($cat['description'] ?? '-') ?></td>
                    <?php if ($hasPlafond): ?>
                    <td><?= (isset($cat['plafond']) && $cat['plafond'] > 0) ? formatMoney($cat['plafond']) : __('unlimited') ?></td>
                    <?php endif; ?>
                    <td><?= $cat['nb_utilisations'] ?></td>
                    <td>
                        <button class="btn btn-ghost btn-icon" onclick="editCat(<?= htmlspecialchars(json_encode($cat)) ?>)"><i class="fas fa-edit"></i></button>
                        <?php if ($cat['nb_utilisations'] == 0): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-icon" onclick="return confirm('<?= __('confirm_delete') ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajouter -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?= __('new_category') ?></h3>
            <button onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><?= __('name') ?> *</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('description') ?></label>
                    <textarea name="description" class="form-control"></textarea>
                </div>
                <?php if ($hasPlafond): ?>
                <div class="form-group">
                    <label class="form-label"><?= __('ceiling') ?> (€)</label>
                    <input type="number" name="plafond" class="form-control" step="0.01" value="0">
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('addModal').classList.remove('active')"><?= __('btn_cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= __('btn_add') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Modifier -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?= __('edit_category') ?></h3>
            <button onclick="document.getElementById('editModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><?= __('name') ?> *</label>
                    <input type="text" name="nom" id="edit_nom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('description') ?></label>
                    <textarea name="description" id="edit_description" class="form-control"></textarea>
                </div>
                <?php if ($hasPlafond): ?>
                <div class="form-group">
                    <label class="form-label"><?= __('ceiling') ?> (€)</label>
                    <input type="number" name="plafond" id="edit_plafond" class="form-control" step="0.01">
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label><input type="checkbox" name="actif" id="edit_actif"> <?= __('active') ?></label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('editModal').classList.remove('active')"><?= __('btn_cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= __('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<style>
.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:white;border-radius:16px;width:100%;max-width:500px}
.modal-header{display:flex;justify-content:space-between;padding:20px 24px;border-bottom:1px solid var(--gray-200)}
.modal-header button{background:none;border:none;font-size:24px;cursor:pointer}
.modal-body{padding:24px}
.modal-footer{display:flex;justify-content:flex-end;gap:12px;padding:16px 24px;border-top:1px solid var(--gray-200)}
</style>

<script>
function editCat(c) {
    document.getElementById('edit_id').value = c.id;
    document.getElementById('edit_nom').value = c.nom;
    document.getElementById('edit_description').value = c.description || '';
    <?php if ($hasPlafond): ?>
    document.getElementById('edit_plafond').value = c.plafond || 0;
    <?php endif; ?>
    document.getElementById('edit_actif').checked = c.actif == 1;
    document.getElementById('editModal').classList.add('active');
}

document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => {
    if (e.target === m) m.classList.remove('active');
}));
</script>

<?php include 'includes/footer.php'; ?>
