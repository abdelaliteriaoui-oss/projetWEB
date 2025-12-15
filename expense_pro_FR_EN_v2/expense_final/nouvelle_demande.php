<?php
/**
 * ExpensePro - Nouvelle Demande avec support OCR
 * Modern expense submission form with OCR integration
 */

require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

$pageTitle = __('nav_new_request');
$userId = $_SESSION['user_id'];
$managerId = $_SESSION['manager_id'];

// Get categories
$categoriesQuery = $pdo->query("SELECT * FROM categories_frais WHERE actif = 1 GROUP BY nom ORDER BY nom");
$categories = $categoriesQuery->fetchAll();

// Données pré-remplies depuis OCR
$ocrData = null;
if (isset($_GET['ocr']) && $_GET['ocr'] == '1') {
    $ocrData = [
        'vendor' => $_GET['vendor'] ?? '',
        'date' => $_GET['date'] ?? '',
        'amount' => $_GET['amount'] ?? '',
        'description' => $_GET['description'] ?? '',
        'category_id' => $_GET['category_id'] ?? ''
    ];
}

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
        
        // Insert demand
        $stmt = $pdo->prepare("
            INSERT INTO demandes (user_id, manager_id, type_depense, date_depense, montant_total, lieu, objet, statut)
            VALUES (?, ?, 'mission', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $managerId, $dateMission, $montantTotal, $lieu, $objet, $statut]);
        $demandeId = $pdo->lastInsertId();
        
        // Insert expense details
        if (isset($_POST['depenses']) && is_array($_POST['depenses'])) {
            $stmtDetail = $pdo->prepare("
                INSERT INTO details_frais (demande_id, categorie_id, date_depense, montant, description, justificatif)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['depenses'] as $index => $depense) {
                $justificatif = null;
                
                // Handle file upload
                if (isset($_FILES['justificatifs']['name'][$index]) && $_FILES['justificatifs']['error'][$index] === UPLOAD_ERR_OK) {
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
        
        // Add history entry
        $stmtHist = $pdo->prepare("INSERT INTO historique_demandes (demande_id, statut, commentaire, user_id) VALUES (?, ?, ?, ?)");
        $stmtHist->execute([$demandeId, $statut, 'Demande créée', $userId]);
        
        // Send notification to manager if submitted
        if ($statut === 'soumise') {
            $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, demande_id, message) VALUES (?, ?, ?)");
            $stmtNotif->execute([
                $managerId,
                $demandeId,
                "Nouvelle demande #{$demandeId} de {$_SESSION['prenom']} {$_SESSION['nom']} - Montant: " . formatMoney($montantTotal)
            ]);
        }
        
        $pdo->commit();
        flashMessage('success', $statut === 'brouillon' ? 'Brouillon enregistré avec succès' : 'Demande soumise avec succès');
        header('Location: mes_demandes.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        flashMessage('error', 'Erreur lors de la création : ' . $e->getMessage());
    }
}

include 'includes/header.php';
?>

<!-- Alert OCR -->
<?php if ($ocrData): ?>
<div class="alert alert-info" style="margin-bottom: 24px; display: flex; align-items: center; gap: 16px; background: linear-gradient(135deg, #DBEAFE, #BFDBFE); border: none; padding: 20px; border-radius: 12px;">
    <div style="width: 48px; height: 48px; background: #3B82F6; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
        <i class="fas fa-magic"></i>
    </div>
    <div style="flex: 1;">
        <div style="font-weight: 700; font-size: 16px; color: #1E40AF; margin-bottom: 4px;">
            Données extraites par OCR !
        </div>
        <div style="color: #1E3A8A; font-size: 14px;">
            Les informations du reçu scanné ont été automatiquement pré-remplies. Vérifiez et complétez si nécessaire.
        </div>
    </div>
    <a href="scanner_ocr.php" class="btn btn-ghost btn-sm" style="color: #3B82F6;">
        <i class="fas fa-camera"></i> Scanner un autre
    </a>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-plus-circle"></i>
            Créer une nouvelle demande de frais
        </h3>
        <div class="card-header-actions">
            <a href="scanner_ocr.php" class="btn btn-outline btn-sm">
                <i class="fas fa-camera"></i> Scanner un reçu
            </a>
        </div>
    </div>
    
    <form method="POST" enctype="multipart/form-data" id="expense-form" data-validate>
        <div class="card-body">
            <!-- Mission Info -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 32px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required">Objet de la mission</label>
                    <input type="text" name="objet" class="form-control" 
                           value="<?= htmlspecialchars($ocrData['vendor'] ?? '') ?>"
                           placeholder="Ex: Réunion client Paris" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required">Lieu du déplacement</label>
                    <div class="input-group">
                        <span class="input-group-icon"><i class="fas fa-map-marker-alt"></i></span>
                        <input type="text" name="lieu" class="form-control" placeholder="Ex: Paris, France" required>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label required">Date de la mission</label>
                    <input type="date" name="date_mission" class="form-control" 
                           value="<?= $ocrData['date'] ?? date('Y-m-d') ?>" required>
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
                    <!-- Initial expense line (pré-remplie par OCR si disponible) -->
                    <div class="expense-line" style="background: var(--gray-50); border-radius: var(--radius-md); padding: 20px; margin-bottom: 16px; position: relative;">
                        <button type="button" class="remove-expense-line" style="position: absolute; top: 12px; right: 12px; width: 28px; height: 28px; border: none; background: var(--danger); color: white; border-radius: var(--radius-full); cursor: pointer; display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                        
                        <div style="display: grid; grid-template-columns: 200px 1fr 150px 150px; gap: 16px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Catégorie</label>
                                <select name="depenses[0][categorie]" class="form-control form-select" required>
                                    <option value="">Sélectionner...</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" 
                                        <?= ($ocrData && $ocrData['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nom']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Description</label>
                                <input type="text" name="depenses[0][description]" class="form-control" 
                                       value="<?= htmlspecialchars($ocrData['description'] ?? '') ?>"
                                       placeholder="Description de la dépense" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Date</label>
                                <input type="date" name="depenses[0][date]" class="form-control" 
                                       value="<?= $ocrData['date'] ?? date('Y-m-d') ?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Montant (DH)</label>
                                <input type="number" name="depenses[0][montant]" class="form-control expense-amount" 
                                       value="<?= htmlspecialchars($ocrData['amount'] ?? '') ?>"
                                       step="0.01" min="0" placeholder="0.00" required>
                            </div>
                        </div>
                        
                        <div style="margin-top: 16px;">
                            <label class="form-label">Justificatif</label>
                            <div class="file-upload" style="padding: 16px;">
                                <input type="file" name="justificatifs[0]" accept=".jpg,.jpeg,.png,.pdf" style="display: none;">
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
                </div>
            </div>
            
            <!-- Total -->
            <div style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius: var(--radius-md); padding: 24px; color: white; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 14px; opacity: 0.8;">Montant total de la demande</div>
                    <div class="expense-total-value" style="font-size: 32px; font-weight: 700;">
                        <?= $ocrData ? number_format($ocrData['amount'], 2, ',', ' ') : '0,00' ?> DH
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 14px; opacity: 0.8;">Statut</div>
                    <div style="font-size: 18px; font-weight: 600;">
                        <i class="fas fa-file-alt"></i> Nouvelle demande
                    </div>
                </div>
            </div>
            <input type="hidden" name="montant_total" value="<?= $ocrData['amount'] ?? '0' ?>">
        </div>
        
        <div class="card-footer" style="display: flex; justify-content: space-between;">
            <a href="dashboard.php" class="btn btn-secondary">
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

<style>
.alert-info {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card-header-actions {
    display: flex;
    gap: 8px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('expense-lines');
    const template = document.getElementById('expense-line-template');
    const addBtn = document.querySelector('.add-expense-line');
    let lineIndex = 1;
    
    // Add new line
    addBtn.addEventListener('click', function() {
        const clone = template.content.cloneNode(true);
        const html = clone.querySelector('.expense-line').outerHTML.replace(/INDEX/g, lineIndex);
        container.insertAdjacentHTML('beforeend', html);
        lineIndex++;
        updateTotal();
        initFileUploads();
        updateRemoveButtons();
    });
    
    // Remove line
    container.addEventListener('click', function(e) {
        if (e.target.closest('.remove-expense-line')) {
            e.target.closest('.expense-line').remove();
            updateTotal();
            updateRemoveButtons();
        }
    });
    
    // Update total on amount change
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
    
    // Calculer le total initial si données OCR
    <?php if ($ocrData): ?>
    updateTotal();
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>