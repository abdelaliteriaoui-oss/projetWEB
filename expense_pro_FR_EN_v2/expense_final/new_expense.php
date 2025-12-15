<?php
session_start();
require_once '../i18n/translations.php';
require_once '../ocr/ReceiptOCR.php';

// Gérer le changement de langue
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Initialiser le traducteur
$currentLang = $_SESSION['lang'] ?? 'fr';
$translator = new Translation($currentLang);

// Gérer l'upload OCR
$ocrResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipt_ocr'])) {
    $uploadDir = '../uploads/receipts/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = uniqid() . '_' . $_FILES['receipt_ocr']['name'];
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['receipt_ocr']['tmp_name'], $filePath)) {
        $ocr = new ReceiptOCR();
        $ocrResult = $ocr->scanReceipt($filePath);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $currentLang === 'ar' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('new_expense') ?> - ExpensePro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #006233;
            --secondary-color: #C1272D;
            --accent-color: #FFD700;
        }
        
        body {
            font-family: <?= $currentLang === 'ar' ? "'Cairo', 'Segoe UI', sans-serif" : "'Segoe UI', 'Cairo', sans-serif" ?>;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .lang-selector {
            display: flex;
            gap: 10px;
        }
        
        .lang-btn {
            padding: 5px 15px;
            border-radius: 20px;
            border: 2px solid white;
            background: transparent;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .lang-btn:hover, .lang-btn.active {
            background: white;
            color: var(--primary-color);
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border: none;
        }
        
        .ocr-upload-zone {
            border: 3px dashed var(--primary-color);
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .ocr-upload-zone:hover {
            background: #e9ecef;
            border-color: var(--secondary-color);
        }
        
        .ocr-upload-zone i {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .extracted-data {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .confidence-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: var(--secondary-color);
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 98, 51, 0.25);
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .processing {
            animation: pulse 1.5s infinite;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-receipt"></i> ExpensePro
            </a>
            
            <div class="lang-selector">
                <button class="lang-btn <?= $currentLang === 'fr' ? 'active' : '' ?>" 
                        onclick="location.href='?lang=fr'">FR</button>
                <button class="lang-btn <?= $currentLang === 'ar' ? 'active' : '' ?>" 
                        onclick="location.href='?lang=ar'">AR</button>
                <button class="lang-btn <?= $currentLang === 'en' ? 'active' : '' ?>" 
                        onclick="location.href='?lang=en'">EN</button>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-body p-5">
                        <h2 class="mb-4">
                            <i class="fas fa-plus-circle text-success"></i> 
                            <?= __('new_expense') ?>
                        </h2>
                        
                        <!-- Zone OCR -->
                        <div class="mb-4">
                            <h5><?= __('scan_receipt') ?> <i class="fas fa-magic text-warning"></i></h5>
                            <form id="ocrForm" method="POST" enctype="multipart/form-data">
                                <div class="ocr-upload-zone" onclick="document.getElementById('receipt_ocr').click()">
                                    <i class="fas fa-camera"></i>
                                    <h4><?= __('upload_photo') ?></h4>
                                    <p class="text-muted"><?= __('auto_extracted') ?></p>
                                    <input type="file" id="receipt_ocr" name="receipt_ocr" 
                                           accept="image/*" style="display: none;" 
                                           onchange="document.getElementById('ocrForm').submit()">
                                </div>
                            </form>
                            
                            <?php if ($ocrResult && $ocrResult['success']): ?>
                                <div class="extracted-data position-relative">
                                    <div class="confidence-badge bg-success">
                                        <?= $ocrResult['extracted_data']['confidence'] ?>% confiance
                                    </div>
                                    <h5><i class="fas fa-check-circle"></i> <?= __('ocr_success') ?></h5>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <strong><?= __('amount') ?>:</strong> 
                                            <?= $ocrResult['extracted_data']['amount'] ?? 'N/A' ?> <?= __('currency') ?>
                                        </div>
                                        <div class="col-md-6">
                                            <strong><?= __('date') ?>:</strong> 
                                            <?= $ocrResult['extracted_data']['date'] ?? 'N/A' ?>
                                        </div>
                                        <div class="col-md-6 mt-2">
                                            <strong><?= __('category') ?>:</strong> 
                                            <?= __($ocrResult['extracted_data']['category'] ?? 'other') ?>
                                        </div>
                                        <div class="col-md-6 mt-2">
                                            <strong>Commerçant:</strong> 
                                            <?= $ocrResult['extracted_data']['merchant'] ?? 'N/A' ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <hr>
                        
                        <!-- Formulaire de note de frais -->
                        <form method="POST" action="submit_expense.php" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= __('amount') ?> *</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" class="form-control" name="amount" 
                                               value="<?= $ocrResult['extracted_data']['amount'] ?? '' ?>" required>
                                        <span class="input-group-text"><?= __('currency') ?></span>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= __('date') ?> *</label>
                                    <input type="date" class="form-control" name="date" 
                                           value="<?= $ocrResult['extracted_data']['date'] ?? date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= __('category') ?> *</label>
                                    <select class="form-select" name="category" required>
                                        <option value="">-- <?= __('category') ?> --</option>
                                        <option value="transport" <?= ($ocrResult['extracted_data']['category'] ?? '') === 'transport' ? 'selected' : '' ?>>
                                            <?= __('transport') ?>
                                        </option>
                                        <option value="meal" <?= ($ocrResult['extracted_data']['category'] ?? '') === 'meal' ? 'selected' : '' ?>>
                                            <?= __('meal') ?>
                                        </option>
                                        <option value="accommodation" <?= ($ocrResult['extracted_data']['category'] ?? '') === 'accommodation' ? 'selected' : '' ?>>
                                            <?= __('accommodation') ?>
                                        </option>
                                        <option value="office_supplies" <?= ($ocrResult['extracted_data']['category'] ?? '') === 'office_supplies' ? 'selected' : '' ?>>
                                            <?= __('office_supplies') ?>
                                        </option>
                                        <option value="other" <?= ($ocrResult['extracted_data']['category'] ?? '') === 'other' ? 'selected' : '' ?>>
                                            <?= __('other') ?>
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= __('receipt') ?></label>
                                    <input type="file" class="form-control" name="receipt" accept="image/*,application/pdf">
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label class="form-label"><?= __('description') ?></label>
                                    <textarea class="form-control" name="description" rows="3" 
                                              placeholder="<?= __('description') ?>..."><?= $ocrResult['extracted_data']['merchant'] ?? '' ?></textarea>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end gap-3 mt-4">
                                <button type="button" class="btn btn-secondary" onclick="history.back()">
                                    <i class="fas fa-times"></i> <?= __('cancel') ?>
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> <?= __('submit') ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation lors de l'upload
        document.getElementById('receipt_ocr').addEventListener('change', function() {
            const uploadZone = document.querySelector('.ocr-upload-zone');
            uploadZone.innerHTML = '<i class="fas fa-spinner fa-spin processing"></i><h4><?= __('ocr_processing') ?></h4>';
        });
    </script>
</body>
</html>