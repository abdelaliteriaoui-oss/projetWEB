<?php
/**
 * ExpensePro - Modifier Demande
 * Edit expense request in draft status
 */

require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

$demandeId = intval($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];

// Get the request
$query = $pdo->prepare("SELECT * FROM demandes WHERE id = ? AND user_id = ?");
$query->execute([$demandeId, $userId]);
$demande = $query->fetch();

if (!$demande) {
    flashMessage('error', 'Demande introuvable');
    header('Location: mes_demandes.php');
    exit;
}

// Only drafts can be edited
if ($demande['statut'] !== 'brouillon') {
    flashMessage('error', 'Seuls les brouillons peuvent être modifiés');
    header('Location: voir_demande.php?id=' . $demandeId);
    exit;
}

// Get existing expense details
$detailsQuery = $pdo->prepare("
    SELECT df.*, cf.nom as categorie_nom
    FROM details_frais df
    JOIN categories_frais cf ON df.categorie_id = cf.id
    WHERE df.demande_id = ?
");
$detailsQuery->execute([$demandeId]);
$existingDetails = $detailsQuery->fetchAll();

// Get categories
$categoriesQuery = $pdo->query("SELECT * FROM categories_frais WHERE actif = 1 GROUP BY nom ORDER BY nom");
$categories = $categoriesQuery->fetchAll();

$pageTitle = 'Modifier la demande #' . $demandeId;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $objet = sanitize($_POST['objet']);
        $lieu = sanitize($_POST['lieu']);
        $dateMission = $_POST['date_mission'];
        $montantTotal = 0;
        $statut = isset($_POST['save_draft']) ? 'brouillon' : 'soumise';
        
        // Calculate total
        if (isset($_POST['depenses']) && is_array($_POST['depenses'])) {
            foreach ($_POST['depenses'] as $depense) {
                $montantTotal += floatval($depense['montant']);
            }
        }
        
        // Update demand
        $stmt = $pdo->prepare("
            UPDATE demandes 
            SET date_depense = ?, montant_total = ?, lieu = ?, objet = ?, statut = ?
            WHERE id = ?
        ");
        $stmt->execute([$dateMission, $montantTotal, $lieu, $objet, $statut, $demandeId]);
        
        // Delete old expense details
        $deleteStmt = $pdo->prepare("DELETE FROM details_frais WHERE demande_id = ?");
        $deleteStmt->execute([$demandeId]);
        
        // Insert new expense details
        if (isset($_POST['depenses']) && is_array($_POST['depenses'])) {
            $stmtDetail = $pdo->prepare("
                INSERT INTO details_frais (demande_id, categorie_id, date_depense, montant, description, justificatif)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['depenses'] as $index => $depense) {
                $justificatif = null;
                
                // Check if keeping existing file
                if (isset($depense['keep_justificatif']) && !empty($depense['keep_justificatif'])) {
                    $justificatif = $depense['keep_justificatif'];
                }
                // Handle new file upload
                elseif (isset($_FILES['justificatifs']['name'][$index]) && $_FILES['justificatifs']['error'][$index] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['justificatifs']['tmp_name'][$index];
                    $fileName = bin2hex(random_bytes(8)) . '_' . time() . '.' . pathinfo($_FILES['justificatifs']['name'][$index], PATHINFO_EXTENSION);
                    $destination = UPLOAD_PATH . $fileName;
                    
                    if (move_uploaded_file($tmpName, $destination)) {
                        $justificatif = 'uploads/justificatifs/' . $fileName;
                    }
                }
                
                $stmtDetail->execute([
                    $demandeId,
                    $depense['categorie'],
                    $depense['date'],
                    $depense['montant'],
                    $depense['description'],
                    $justificatif
                ]);
            }
        }
        
        // Add history entry if submitted
        if ($statut === 'soumise') {
            $stmtHist = $pdo->prepare("INSERT INTO historique_demandes (demande_id, statut, commentaire, user_id) VALUES (?, ?, ?, ?)");
            $stmtHist->execute([$demandeId, $statut, 'Demande soumise après modification', $userId]);
            
            // Send notification to manager
            $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, demande_id, message) VALUES (?, ?, ?)");
            $stmtNotif->execute([
                $demande['manager_id'],
                $demandeId,
                "Demande modifiée et soumise #{$demandeId} de {$_SESSION['prenom']} {$_SESSION['nom']} - Montant: " . formatMoney($montantTotal)
            ]);
        }
        
        $pdo->commit();
        flashMessage('success', $statut === 'brouillon' ? 'Brouillon mis à jour avec succès' : 'Demande soumise avec succès');
        header('Location: mes_demandes.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        flashMessage('error', 'Erreur lors de la modification : ' . $e->getMessage());
    }
}

include 'includes/header.php';
?>

<div style="display: flex; gap: 12px; margin-bottom: 24px;">
    <a href="mes_demandes.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Retour
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-edit"></i>
            Modifier la demande #<?= $demandeId ?>
        </h3>
    </div>
    
    <form method="POST" enctype="multipart/form-data" id="expense-form" data-validate>
        <div class="card-body">
            <!-- Mission Info -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 32px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required">Objet de la mission</label>
                    <input type="text" name="objet" class="form-control" placeholder="Ex: Réunion client Paris" value="<?= htmlspecialchars($demande['objet']) ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required">Lieu du déplacement</label>
                    <div class="input-group">
                        <span class="input-group-icon"><i class="fas fa-map-marker-alt"></i></span>
                        <input type="text" name="lieu" class="form-control" placeholder="Ex: Paris, France" value="<?= htmlspecialchars($demande['lieu']) ?>" required>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required">Date de la mission</label>
                    <input type="date" name="date_mission" class="form-control" value="<?= $demande['date_depense'] ?>" required>
                </div>
            </div>
            
            <!-- Expense Lines -->
            <div style="margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h4 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">
                        <i class="fas fa-receipt" style="color: var(--primary); margin-right: 8px;"></i>
                        Détails des dépenses
                    </h4>
                    <button type="button" class="btn btn-primary btn-sm add-expense-line">
                        <i class="fas fa-plus"></i> Ajouter une ligne
                    </button>
                </div>
                
                <div class="expense-lines" id="expense-lines">
                    <?php foreach ($existingDetails as $index => $detail): ?>
                    <div class="expense-line" style="background: var(--gray-50); border-radius: var(--radius-md); padding: 20px; margin-bottom: 16px; position: relative;">
                        <button type="button" class="remove-expense-line" style="position: absolute; top: 12px; right: 12px; width: 28px; height: 28px; border: none; background: var(--danger); color: white; border-radius: var(--radius-full); cursor: pointer; <?= count($existingDetails) === 1 ? 'display: none;' : '' ?>">
                            <i class="fas fa-times"></i>
                        </button>
                        
                        <div style="display: grid; grid-template-columns: 200px 1fr 150px 150px; gap: 16px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Catégorie</label>
                                <select name="depenses[<?= $index ?>][categorie]" class="form-control form-select" required>
                                    <option value="">Sélectionner...</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $detail['categorie_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Description</label>
                                <input type="text" name="depenses[<?= $index ?>][description]" class="form-control" placeholder="Description de la dépense" value="<?= htmlspecialchars($detail['description']) ?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Date</label>
                                <input type="date" name="depenses[<?= $index ?>][date]" class="form-control" value="<?= $detail['date_depense'] ?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Montant (DH)</label>
                                <input type="number" name="depenses[<?= $index ?>][montant]" class="form-control expense-amount" step="0.01" min="0" placeholder="0.00" value="<?= $detail['montant'] ?>" required>
                            </div>
                        </div>
                        
                        <div style="margin-top: 16px;">
                            <label class="form-label">Justificatif</label>
                            <?php if ($detail['justificatif']): ?>
                            <div style="margin-bottom: 12px; padding: 12px; background: var(--gray-100); border-radius: var(--radius); display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-file" style="color: var(--primary);"></i>
                                <span style="flex: 1; font-size: 14px;">Fichier existant</span>
                                <a href="<?= htmlspecialchars($detail['justificatif']) ?>" target="_blank" class="btn btn-ghost btn-sm">
                                    <i class="fas fa-eye"></i> Voir
                                </a>
                                <button type="button" class="btn btn-ghost btn-sm" onclick="this.closest('.expense-line').querySelector('.file-upload').style.display='block'; this.parentElement.style.display='none';">
                                    <i class="fas fa-exchange-alt"></i> Changer
                                </button>
                                <input type="hidden" name="depenses[<?= $index ?>][keep_justificatif]" value="<?= htmlspecialchars($detail['justificatif']) ?>">
                            </div>
                            <?php endif; ?>
                            <div class="file-upload" style="padding: 16px; <?= $detail['justificatif'] ? 'display: none;' : '' ?>">
                                <input type="file" name="justificatifs[<?= $index ?>]" accept=".jpg,.jpeg,.png,.pdf" style="display: none;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div class="file-upload-icon" style="width: 40px; height: 40px; font-size: 16px;">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div class="file-upload-text">
                                        <span>Cliquez pour télécharger</span> ou glissez-déposez
                                        <br><small style="color: var(--gray-400);">JPG, PNG ou PDF (max. 5MB)</small>
                                    </div>
                                </div>
                                <div class="file-preview"></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Total -->
            <div style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius: var(--radius-md); padding: 24px; color: white; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 14px; opacity: 0.8;">Montant total de la demande</div>
                    <div class="expense-total-value" style="font-size: 32px; font-weight: 700;"><?= formatMoney($demande['montant_total']) ?></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 14px; opacity: 0.8;">Statut</div>
                    <div style="font-size: 18px; font-weight: 600;">
                        <i class="fas fa-edit"></i> Modification
                    </div>
                </div>
            </div>
            <input type="hidden" name="montant_total" value="<?= $demande['montant_total'] ?>">
        </div>
        
        <div class="card-footer" style="display: flex; justify-content: space-between;">
            <a href="mes_demandes.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Annuler
            </a>
            <div style="display: flex; gap: 12px;">
                <button type="submit" name="save_draft" class="btn btn-outline">
                    <i class="fas fa-save"></i> Enregistrer brouillon
                </button>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane"></i> Soumettre la demande
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Template for new expense line -->
<template id="expense-line-template">
    <div class="expense-line" style="background: var(--gray-50); border-radius: var(--radius-md); padding: 20px; margin-bottom: 16px; position: relative;">
        <button type="button" class="remove-expense-line" style="position: absolute; top: 12px; right: 12px; width: 28px; height: 28px; border: none; background: var(--danger); color: white; border-radius: var(--radius-full); cursor: pointer;">
            <i class="fas fa-times"></i>
        </button>
        
        <div style="display: grid; grid-template-columns: 200px 1fr 150px 150px; gap: 16px; align-items: end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Catégorie</label>
                <select name="depenses[INDEX][categorie]" class="form-control form-select" required>
                    <option value="">Sélectionner...</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Description</label>
                <input type="text" name="depenses[INDEX][description]" class="form-control" placeholder="Description de la dépense" required>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Date</label>
                <input type="date" name="depenses[INDEX][date]" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Montant (DH)</label>
                <input type="number" name="depenses[INDEX][montant]" class="form-control expense-amount" step="0.01" min="0" placeholder="0.00" required>
            </div>
        </div>
        
        <div style="margin-top: 16px;">
            <label class="form-label">Justificatif</label>
            <div class="file-upload" style="padding: 16px;">
                <input type="file" name="justificatifs[INDEX]" accept=".jpg,.jpeg,.png,.pdf" style="display: none;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div class="file-upload-icon" style="width: 40px; height: 40px; font-size: 16px;">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="file-upload-text">
                        <span>Cliquez pour télécharger</span> ou glissez-déposez
                        <br><small style="color: var(--gray-400);">JPG, PNG ou PDF (max. 5MB)</small>
                    </div>
                </div>
                <div class="file-preview"></div>
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('expense-lines');
    const template = document.getElementById('expense-line-template');
    const addBtn = document.querySelector('.add-expense-line');
    let lineIndex = <?= count($existingDetails) ?>;
    
    addBtn.addEventListener('click', function() {
        const clone = template.content.cloneNode(true);
        const html = clone.querySelector('.expense-line').outerHTML.replace(/INDEX/g, lineIndex);
        container.insertAdjacentHTML('beforeend', html);
        lineIndex++;
        updateTotal();
        initFileUploads();
        updateRemoveButtons();
    });
    
    container.addEventListener('click', function(e) {
        if (e.target.closest('.remove-expense-line')) {
            e.target.closest('.expense-line').remove();
            updateTotal();
            updateRemoveButtons();
        }
    });
    
    container.addEventListener('input', function(e) {
        if (e.target.classList.contains('expense-amount')) {
            updateTotal();
        }
    });
    
    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.expense-amount').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        document.querySelector('.expense-total-value').textContent = 
            new Intl.NumberFormat('fr-MA', { minimumFractionDigits: 2 }).format(total) + ' DH';
        document.querySelector('input[name="montant_total"]').value = total;
    }
    
    function updateRemoveButtons() {
        const lines = document.querySelectorAll('.expense-line');
        lines.forEach((line, index) => {
            const btn = line.querySelector('.remove-expense-line');
            btn.style.display = lines.length > 1 ? 'flex' : 'none';
        });
    }
    
    function initFileUploads() {
        document.querySelectorAll('.file-upload').forEach(upload => {
            const input = upload.querySelector('input[type="file"]');
            if (input && !input.dataset.initialized) {
                input.dataset.initialized = true;
                upload.addEventListener('click', (e) => {
                    if (e.target.tagName !== 'INPUT') input.click();
                });
                input.addEventListener('change', function() {
                    const preview = upload.querySelector('.file-preview');
                    preview.innerHTML = '';
                    if (this.files[0]) {
                        const file = this.files[0];
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                preview.innerHTML = `<div class="file-preview-item"><img src="${e.target.result}"></div>`;
                            };
                            reader.readAsDataURL(file);
                        } else {
                            preview.innerHTML = `<div class="file-preview-item" style="display:flex;align-items:center;justify-content:center;background:var(--gray-100);"><i class="fas fa-file-pdf" style="font-size:24px;color:#ef4444;"></i></div>`;
                        }
                    }
                });
            }
        });
    }
    
    initFileUploads();
    updateRemoveButtons();
    updateTotal();
});
</script>

<?php include 'includes/footer.php'; ?>