<?php
/**
 * ExpensePro - Scanner OCR
 * Scan receipts and invoices using OCR technology
 */

require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

// Accessible uniquement aux employés
if ($_SESSION['role'] !== 'employe') {
    flashMessage('error', 'Accès non autorisé');
    header('Location: dashboard.php');
    exit;
}

$pageTitle = __('ocr_scanner');

// Récupérer l'historique des scans récents
try {
    $historyQuery = $pdo->prepare("
        SELECT os.*, u.prenom, u.nom 
        FROM ocr_scans os
        JOIN users u ON os.user_id = u.id
        WHERE os.user_id = ?
        ORDER BY os.created_at DESC
        LIMIT 10
    ");
    $historyQuery->execute([$_SESSION['user_id']]);
    $scanHistory = $historyQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $scanHistory = [];
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h2><i class="fas fa-camera"></i> Scanner OCR</h2>
        <p>Numérisez vos reçus et factures automatiquement</p>
    </div>
    <?php if ($_SESSION['role'] === 'employe'): ?>
    <div class="page-header-actions">
        <a href="nouvelle_demande.php" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="row" style="gap: 24px;">
    <!-- Scanner principal -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-scan"></i> Scanner un document
                </h3>
            </div>
            <div class="card-body">
                <!-- Zone de téléchargement -->
                <div id="dropzone" class="ocr-dropzone">
                    <div class="ocr-dropzone-content">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h3>Glissez votre document ici</h3>
                        <p>ou cliquez pour parcourir</p>
                        <input type="file" id="fileInput" accept="image/*,.pdf" style="display: none;">
                        <div class="ocr-supported-formats">
                            <span><i class="fas fa-check"></i> JPG</span>
                            <span><i class="fas fa-check"></i> PNG</span>
                            <span><i class="fas fa-check"></i> PDF</span>
                            <span><i class="fas fa-check"></i> HEIC</span>
                        </div>
                    </div>
                </div>

                <!-- Preview & Camera -->
                <div id="previewSection" style="display: none;">
                    <div class="ocr-preview-container">
                        <img id="imagePreview" src="" alt="Preview">
                        <button class="btn-remove-preview" onclick="removePreview()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div style="margin-top: 16px; display: flex; gap: 12px;">
                        <button class="btn btn-primary" onclick="processOCR()" id="processBtn">
                            <i class="fas fa-magic"></i> Extraire les données
                        </button>
                        <button class="btn btn-ghost" onclick="removePreview()">
                            <i class="fas fa-redo"></i> Réessayer
                        </button>
                    </div>
                </div>

                <!-- Option Caméra -->
                <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color);">
                    <button class="btn btn-outline" onclick="openCamera()" id="cameraBtn">
                        <i class="fas fa-camera"></i> Prendre une photo
                    </button>
                </div>

                <!-- Camera Modal -->
                <div id="cameraModal" class="ocr-camera-modal" style="display: none;">
                    <div class="ocr-camera-container">
                        <div class="ocr-camera-header">
                            <h3>Prendre une photo</h3>
                            <button onclick="closeCamera()"><i class="fas fa-times"></i></button>
                        </div>
                        <video id="cameraStream" autoplay playsinline></video>
                        <canvas id="cameraCanvas" style="display: none;"></canvas>
                        <div class="ocr-camera-controls">
                            <button class="btn btn-primary btn-lg" onclick="capturePhoto()">
                                <i class="fas fa-camera"></i> Capturer
                            </button>
                            <button class="btn btn-ghost" onclick="closeCamera()">Annuler</button>
                        </div>
                    </div>
                </div>

                <!-- Loading -->
                <div id="loadingSection" style="display: none;">
                    <div class="ocr-loading">
                        <div class="ocr-loader"></div>
                        <h3>Analyse en cours...</h3>
                        <p>Extraction des données du document</p>
                    </div>
                </div>

                <!-- Résultats -->
                <div id="resultsSection" style="display: none;">
                    <div class="ocr-results">
                        <div class="ocr-results-header">
                            <h3><i class="fas fa-check-circle" style="color: var(--success);"></i> Données extraites</h3>
                            <span class="ocr-confidence" id="confidenceScore"></span>
                        </div>
                        
                        <form id="ocrResultsForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Fournisseur</label>
                                        <input type="text" id="ocr_vendor" class="form-control" placeholder="Nom du fournisseur">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Date</label>
                                        <input type="date" id="ocr_date" class="form-control">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Montant Total</label>
                                        <input type="number" id="ocr_amount" class="form-control" step="0.01" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Devise</label>
                                        <input type="text" id="ocr_currency" class="form-control" value="EUR">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Numéro de facture</label>
                                <input type="text" id="ocr_invoice" class="form-control" placeholder="N° facture">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Description / Articles</label>
                                <textarea id="ocr_description" class="form-control" rows="4" placeholder="Description des articles..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Catégorie</label>
                                <select id="ocr_category" class="form-control">
                                    <option value="">-- Sélectionner --</option>
                                    <?php
                                    $cats = $pdo->query("SELECT * FROM categories_frais ORDER BY nom")->fetchAll();
                                    foreach ($cats as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="display: flex; gap: 12px; margin-top: 24px;">
                                <button type="button" class="btn btn-success" onclick="createExpenseFromOCR()" style="flex: 1;">
                                    <i class="fas fa-arrow-right"></i> Continuer vers la demande
                                </button>
                                <button type="button" class="btn btn-primary" onclick="saveOCRScan()">
                                    <i class="fas fa-save"></i> Sauvegarder
                                </button>
                                <button type="button" class="btn btn-ghost" onclick="resetOCR()">
                                    <i class="fas fa-redo"></i> Nouveau
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar - Historique & Astuces -->
    <div class="col-lg-4">
        <!-- Astuces -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-lightbulb"></i> Astuces</h3>
            </div>
            <div class="card-body">
                <div class="ocr-tips">
                    <div class="ocr-tip">
                        <i class="fas fa-sun"></i>
                        <span>Assurez un bon éclairage</span>
                    </div>
                    <div class="ocr-tip">
                        <i class="fas fa-align-center"></i>
                        <span>Centrez le document</span>
                    </div>
                    <div class="ocr-tip">
                        <i class="fas fa-image"></i>
                        <span>Évitez les reflets</span>
                    </div>
                    <div class="ocr-tip">
                        <i class="fas fa-expand"></i>
                        <span>Capturez l'intégralité</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historique récent -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"></i> Scans récents</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($scanHistory)): ?>
                <div style="padding: 24px; text-align: center; color: var(--gray-500);">
                    <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 8px;"></i>
                    <p>Aucun scan récent</p>
                </div>
                <?php else: ?>
                <div class="ocr-history-list">
                    <?php foreach ($scanHistory as $scan): ?>
                    <div class="ocr-history-item">
                        <div class="ocr-history-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="ocr-history-info">
                            <div class="ocr-history-vendor"><?= htmlspecialchars($scan['vendor'] ?? 'N/A') ?></div>
                            <div class="ocr-history-meta">
                                <?= formatMoney($scan['amount'] ?? 0) ?> • <?= timeAgo($scan['created_at']) ?>
                            </div>
                        </div>
                        <button class="btn btn-ghost btn-icon" onclick="loadScan(<?= $scan['id'] ?>)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.ocr-dropzone {
    border: 3px dashed var(--border-color);
    border-radius: 16px;
    padding: 60px 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    background: var(--bg-secondary);
}
.ocr-dropzone:hover, .ocr-dropzone.dragover {
    border-color: var(--primary);
    background: var(--primary-light);
}
.ocr-dropzone-content i {
    font-size: 64px;
    color: var(--primary);
    margin-bottom: 16px;
}
.ocr-dropzone-content h3 {
    font-size: 20px;
    margin-bottom: 8px;
}
.ocr-dropzone-content p {
    color: var(--gray-500);
}
.ocr-supported-formats {
    display: flex;
    gap: 16px;
    justify-content: center;
    margin-top: 24px;
}
.ocr-supported-formats span {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--gray-600);
}
.ocr-supported-formats i {
    color: var(--success);
    font-size: 12px;
}
.ocr-preview-container {
    position: relative;
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid var(--border-color);
}
.ocr-preview-container img {
    width: 100%;
    display: block;
}
.btn-remove-preview {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--danger);
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.ocr-camera-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.95);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ocr-camera-container {
    width: 90%;
    max-width: 800px;
}
.ocr-camera-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
    margin-bottom: 16px;
}
.ocr-camera-header button {
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
}
#cameraStream {
    width: 100%;
    border-radius: 12px;
}
.ocr-camera-controls {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 16px;
}
.ocr-loading {
    text-align: center;
    padding: 60px 24px;
}
.ocr-loader {
    width: 60px;
    height: 60px;
    border: 4px solid var(--primary-light);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 24px;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.ocr-results {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 24px;
    margin-top: 24px;
}
.ocr-results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--border-color);
}
.ocr-confidence {
    background: var(--success-light);
    color: var(--success);
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
}
.ocr-tips {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.ocr-tip {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--bg-secondary);
    border-radius: 8px;
}
.ocr-tip i {
    color: var(--primary);
    font-size: 18px;
}
.ocr-history-list {
    display: flex;
    flex-direction: column;
}
.ocr-history-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
}
.ocr-history-item:last-child {
    border-bottom: none;
}
.ocr-history-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.ocr-history-info {
    flex: 1;
}
.ocr-history-vendor {
    font-weight: 600;
    font-size: 14px;
}
.ocr-history-meta {
    font-size: 12px;
    color: var(--gray-500);
    margin-top: 2px;
}
</style>

<script src="assets/js/ocr-scanner.js"></script>

<?php include 'includes/footer.php'; ?>