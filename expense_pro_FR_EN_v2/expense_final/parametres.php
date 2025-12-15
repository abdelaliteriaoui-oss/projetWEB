<?php
/**
 * ExpensePro - Paramètres
 * System settings and configuration
 */

require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    flashMessage('error', 'Accès non autorisé');
    header('Location: dashboard.php');
    exit;
}

$pageTitle = __('nav_settings');

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'general':
                // Mise à jour des paramètres généraux
                // Dans un vrai projet, ces paramètres seraient stockés en BDD
                flashMessage('success', 'Paramètres généraux mis à jour');
                break;
                
            case 'notifications':
                flashMessage('success', 'Paramètres de notifications mis à jour');
                break;
                
            case 'baremes':
                flashMessage('success', 'Barèmes kilométriques mis à jour');
                break;
        }
    } catch (Exception $e) {
        flashMessage('error', $e->getMessage());
    }
    
    header('Location: parametres.php');
    exit;
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h2>Paramètres</h2>
        <p>Configuration du système ExpensePro</p>
    </div>
</div>

<div class="settings-container">
    <!-- Navigation des paramètres -->
    <div class="settings-nav">
        <a href="#general" class="settings-nav-item active" data-tab="general">
            <i class="fas fa-cog"></i>
            Général
        </a>
        <a href="#notifications" class="settings-nav-item" data-tab="notifications">
            <i class="fas fa-bell"></i>
            Notifications
        </a>
        <a href="#baremes" class="settings-nav-item" data-tab="baremes">
            <i class="fas fa-car"></i>
            Barèmes km
        </a>
        <a href="#workflow" class="settings-nav-item" data-tab="workflow">
            <i class="fas fa-project-diagram"></i>
            Workflow
        </a>
        <a href="#securite" class="settings-nav-item" data-tab="securite">
            <i class="fas fa-shield-alt"></i>
            Sécurité
        </a>
    </div>
    
    <!-- Contenu des paramètres -->
    <div class="settings-content">
        <!-- Paramètres généraux -->
        <div class="settings-panel active" id="general">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Paramètres généraux</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="form">
                        <input type="hidden" name="action" value="general">
                        
                        <div class="form-group">
                            <label class="form-label">Nom de l'entreprise</label>
                            <input type="text" name="company_name" class="form-control" value="ExpensePro Corp">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Devise</label>
                                <select name="currency" class="form-control">
                                    <option value="EUR" selected>Euro (€)</option>
                                    <option value="USD">Dollar ($)</option>
                                    <option value="GBP">Livre (£)</option>
                                    <option value="MAD">Dirham (DH)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Langue</label>
                                <select name="language" class="form-control">
                                    <option value="fr" selected>Français</option>
                                    <option value="en">English</option>
                                    <option value="ar">العربية</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Exercice fiscal</label>
                            <div class="form-row">
                                <select name="fiscal_start" class="form-control">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m === 1 ? 'selected' : '' ?>>
                                        <?= strftime('%B', mktime(0, 0, 0, $m, 1)) ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                                <span style="align-self: center;">à</span>
                                <select name="fiscal_end" class="form-control">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m === 12 ? 'selected' : '' ?>>
                                        <?= strftime('%B', mktime(0, 0, 0, $m, 1)) ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Montant maximum par demande</label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="number" name="max_amount" class="form-control" value="5000" style="width: 150px;">
                                <span>€</span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Notifications -->
        <div class="settings-panel" id="notifications">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Paramètres des notifications</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="form">
                        <input type="hidden" name="action" value="notifications">
                        
                        <div class="form-group">
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_new_request" checked>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">Email lors d'une nouvelle demande</span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_validation" checked>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">Email lors de la validation/rejet</span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_reminder" checked>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">Rappels automatiques</span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Délai de rappel (jours)</label>
                            <input type="number" name="reminder_days" class="form-control" value="3" style="width: 100px;">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Barèmes -->
        <div class="settings-panel" id="baremes">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Barèmes kilométriques</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="form">
                        <input type="hidden" name="action" value="baremes">
                        
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Distance</th>
                                        <th>3 CV</th>
                                        <th>4 CV</th>
                                        <th>5 CV</th>
                                        <th>6 CV</th>
                                        <th>7 CV+</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>≤ 5 000 km</td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.529"></td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.606"></td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.636"></td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.665"></td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.697"></td>
                                    </tr>
                                    <tr>
                                        <td>5 001 - 20 000 km</td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.316"></td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.340"></td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.357"></td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.374"></td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.394"></td>
                                    </tr>
                                    <tr>
                                        <td>> 20 000 km</td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.370"></td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.407"></td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.427"></td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.447"></td>
                                        <td><input type="number" step="0.001" class="form-control form-control-sm" value="0.470"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <p style="margin: 16px 0; color: var(--gray-500); font-size: 13px;">
                            <i class="fas fa-info-circle"></i> Valeurs en €/km selon le barème fiscal en vigueur
                        </p>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Workflow -->
        <div class="settings-panel" id="workflow">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Configuration du workflow</h3>
                </div>
                <div class="card-body">
                    <div class="workflow-diagram">
                        <div class="workflow-step">
                            <div class="workflow-icon"><i class="fas fa-edit"></i></div>
                            <div class="workflow-label">Brouillon</div>
                        </div>
                        <div class="workflow-arrow"><i class="fas fa-arrow-right"></i></div>
                        <div class="workflow-step">
                            <div class="workflow-icon"><i class="fas fa-paper-plane"></i></div>
                            <div class="workflow-label">Soumise</div>
                        </div>
                        <div class="workflow-arrow"><i class="fas fa-arrow-right"></i></div>
                        <div class="workflow-step">
                            <div class="workflow-icon"><i class="fas fa-user-tie"></i></div>
                            <div class="workflow-label">Validation Manager</div>
                        </div>
                        <div class="workflow-arrow"><i class="fas fa-arrow-right"></i></div>
                        <div class="workflow-step">
                            <div class="workflow-icon"><i class="fas fa-user-shield"></i></div>
                            <div class="workflow-label">Approbation Admin</div>
                        </div>
                        <div class="workflow-arrow"><i class="fas fa-arrow-right"></i></div>
                        <div class="workflow-step success">
                            <div class="workflow-icon"><i class="fas fa-check-double"></i></div>
                            <div class="workflow-label">Approuvée</div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 24px;">
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                            <span class="toggle-label">Validation manager obligatoire</span>
                        </label>
                    </div>
                    
                    <div style="margin-top: 16px;">
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                            <span class="toggle-label">Approbation admin pour montants > 1000€</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sécurité -->
        <div class="settings-panel" id="securite">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Sécurité</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                            <span class="toggle-label">Authentification à deux facteurs (2FA)</span>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Durée de session (minutes)</label>
                        <input type="number" class="form-control" value="60" style="width: 100px;">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tentatives de connexion max</label>
                        <input type="number" class="form-control" value="5" style="width: 100px;">
                    </div>
                    
                    <div class="form-group">
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                            <span class="toggle-label">Journaliser les actions</span>
                        </label>
                    </div>
                    
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-container {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 24px;
}
.settings-nav {
    background: white;
    border-radius: 12px;
    padding: 12px;
    height: fit-content;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.settings-nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 8px;
    color: var(--gray-600);
    text-decoration: none;
    transition: all 0.2s;
}
.settings-nav-item:hover {
    background: var(--gray-100);
}
.settings-nav-item.active {
    background: var(--primary-light);
    color: var(--primary);
    font-weight: 500;
}
.settings-panel {
    display: none;
}
.settings-panel.active {
    display: block;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.toggle-switch {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
}
.toggle-slider {
    width: 44px;
    height: 24px;
    background: var(--gray-300);
    border-radius: 12px;
    position: relative;
    transition: all 0.2s;
}
.toggle-slider::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    top: 2px;
    left: 2px;
    transition: all 0.2s;
}
.toggle-switch input:checked + .toggle-slider {
    background: var(--primary);
}
.toggle-switch input:checked + .toggle-slider::after {
    left: 22px;
}
.toggle-switch input {
    display: none;
}
.workflow-diagram {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 24px;
    background: var(--gray-50);
    border-radius: 12px;
    flex-wrap: wrap;
}
.workflow-step {
    text-align: center;
}
.workflow-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
}
.workflow-step.success .workflow-icon {
    background: #D1FAE5;
    color: #059669;
}
.workflow-label {
    font-size: 12px;
    color: var(--gray-600);
}
.workflow-arrow {
    color: var(--gray-400);
}
.form-control-sm {
    padding: 6px 10px;
    font-size: 13px;
}
</style>

<script>
document.querySelectorAll('.settings-nav-item').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        const tab = this.dataset.tab;
        
        document.querySelectorAll('.settings-nav-item').forEach(i => i.classList.remove('active'));
        document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
        
        this.classList.add('active');
        document.getElementById(tab).classList.add('active');
    });
});
</script>

<?php include 'includes/footer.php'; ?>
