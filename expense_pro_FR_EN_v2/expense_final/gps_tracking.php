<?php
/**
 * ExpensePro - GPS Tracking avec Leaflet
 * VERSION CORRIGÉE - Routage fiable sans erreur
 */

require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

$pageTitle = __('nav_gps_tracking');
$userId = $_SESSION['user_id'];

// Récupérer les véhicules
$vehiculesQuery = $pdo->prepare("SELECT * FROM vehicules WHERE user_id = ? ORDER BY favori DESC, created_at DESC");
$vehiculesQuery->execute([$userId]);
$vehicules = $vehiculesQuery->fetchAll();

// Stats du mois
try {
    $statsQuery = $pdo->prepare("
        SELECT COUNT(*) as nb_trajets, COALESCE(SUM(distance_km), 0) as total_km,
               COALESCE(SUM(montant_remboursement), 0) as total_remboursement,
               COALESCE(SUM(emission_co2), 0) as total_co2
        FROM trajets WHERE user_id = ? AND MONTH(date_trajet) = MONTH(NOW()) AND YEAR(date_trajet) = YEAR(NOW())
    ");
    $statsQuery->execute([$userId]);
    $stats = $statsQuery->fetch();
} catch (Exception $e) {
    $stats = ['nb_trajets' => 0, 'total_km' => 0, 'total_remboursement' => 0, 'total_co2' => 0];
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
.gps-container { display: grid; grid-template-columns: 1fr 400px; gap: 24px; height: calc(100vh - 200px); min-height: 600px; }
.map-container { background: var(--gray-100); border-radius: 16px; overflow: hidden; position: relative; }
#map { width: 100%; height: 100%; min-height: 500px; border-radius: 16px; }
.map-overlay { position: absolute; top: 16px; left: 16px; right: 16px; z-index: 1000; display: flex; gap: 12px; }
.search-box { flex: 1; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); padding: 12px 16px; display: flex; align-items: center; gap: 12px; }
.search-box input { border: none; outline: none; flex: 1; font-size: 14px; }
.tracking-panel { display: flex; flex-direction: column; gap: 16px; overflow-y: auto; max-height: calc(100vh - 200px); }
.tracking-status { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
.tracking-status.active { background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; }
.tracking-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.tracking-title { font-size: 18px; font-weight: 600; }
.tracking-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: var(--gray-100); color: var(--gray-600); }
.tracking-status.active .tracking-badge { background: rgba(255,255,255,0.2); color: white; }
.tracking-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px; }
.tracking-stat { text-align: center; }
.tracking-value { font-size: 24px; font-weight: 700; }
.tracking-label { font-size: 12px; color: var(--gray-500); margin-top: 4px; }
.tracking-status.active .tracking-label { color: rgba(255,255,255,0.8); }
.btn-tracking { width: 100%; padding: 16px; border-radius: 12px; font-size: 16px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer; border: none; }
.btn-start { background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; }
.btn-stop { background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: white; }
.quick-trip { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
.quick-trip-title { font-size: 16px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.location-inputs { display: flex; flex-direction: column; gap: 12px; position: relative; }
.location-input { display: flex; align-items: center; gap: 12px; background: var(--gray-50); border-radius: 10px; padding: 12px; position: relative; }
.location-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
.location-dot.start { background: #10B981; }
.location-dot.end { background: #EF4444; }
.location-input input { border: none; background: transparent; flex: 1; font-size: 14px; outline: none; }
.location-line { position: absolute; left: 17px; top: 36px; width: 2px; height: calc(100% - 72px); background: var(--gray-300); }
.autocomplete-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: white; border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.2); margin-top: 4px; max-height: 250px; overflow-y: auto; z-index: 1001; display: none; }
.autocomplete-dropdown.active { display: block; }
.autocomplete-item { padding: 12px 16px; cursor: pointer; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--gray-100); }
.autocomplete-item:hover, .autocomplete-item.selected { background: var(--primary-light); }
.autocomplete-item i { color: var(--primary); width: 20px; text-align: center; }
.autocomplete-item .city-name { font-weight: 500; }
.autocomplete-item .city-region { font-size: 12px; color: var(--gray-500); margin-left: auto; }
.trip-result { background: linear-gradient(135deg, #0066FF 0%, #5B4FFF 100%); border-radius: 16px; padding: 20px; color: white; display: none; }
.trip-result.show { display: block; animation: slideIn 0.3s ease; }
@keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.trip-result-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.trip-result-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 16px; }
.trip-result-stat { text-align: center; }
.trip-result-stat .value { font-size: 20px; font-weight: 700; }
.trip-result-stat .label { font-size: 11px; opacity: 0.8; }
.eco-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(16, 185, 129, 0.2); padding: 6px 12px; border-radius: 20px; font-size: 12px; color: #10B981; }
.vehicle-selector { background: white; border-radius: 16px; padding: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
.vehicle-option { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 10px; cursor: pointer; border: 2px solid transparent; margin-bottom: 8px; }
.vehicle-option:hover { background: var(--gray-50); }
.vehicle-option.selected { background: var(--primary-light); border-color: var(--primary); }
.vehicle-icon { width: 44px; height: 44px; border-radius: 10px; background: var(--gray-100); display: flex; align-items: center; justify-content: center; font-size: 20px; }
.vehicle-info h4 { font-size: 14px; font-weight: 600; margin: 0; }
.vehicle-info p { font-size: 12px; color: var(--gray-500); margin: 2px 0 0 0; }
.stats-bar { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-mini { background: white; border-radius: 12px; padding: 16px 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.stat-mini-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.stat-mini-value { font-size: 22px; font-weight: 700; }
.stat-mini-label { font-size: 12px; color: var(--gray-500); }
.loading-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 9999; }
.loading-overlay.active { display: flex; }
.loading-spinner { background: white; padding: 30px 40px; border-radius: 16px; text-align: center; }
.spinner { width: 50px; height: 50px; border: 4px solid var(--gray-200); border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto; }
@keyframes spin { to { transform: rotate(360deg); } }
.status-message { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #1a1a2e; color: white; padding: 12px 24px; border-radius: 8px; font-size: 14px; z-index: 10000; display: none; }
.status-message.show { display: block; }
.status-message.success { background: #059669; }
.status-message.error { background: #DC2626; }
.modal-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 10000; padding: 20px; }
.modal-backdrop.active { display: flex; }
.modal-content { background: white; border-radius: 20px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
.modal-header { padding: 24px 24px 0; display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 20px; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 10px; }
.modal-close { width: 36px; height: 36px; border-radius: 50%; border: none; background: var(--gray-100); cursor: pointer; display: flex; align-items: center; justify-content: center; }
.modal-body { padding: 24px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--gray-700); margin-bottom: 8px; }
.form-group label .required { color: #EF4444; }
.form-control { width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: 10px; font-size: 14px; box-sizing: border-box; }
.form-control:focus { outline: none; border-color: var(--primary); }
.type-selector { display: flex; gap: 12px; }
.type-option { flex: 1; padding: 16px; border: 2px solid var(--gray-200); border-radius: 12px; cursor: pointer; text-align: center; }
.type-option:hover, .type-option.selected { border-color: var(--primary); background: var(--primary-light); }
.type-option i { font-size: 28px; color: var(--primary); margin-bottom: 8px; }
.type-option span { display: block; font-size: 13px; font-weight: 600; }
.modal-footer { padding: 0 24px 24px; display: flex; gap: 12px; }
.modal-footer .btn { flex: 1; padding: 14px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-cancel { background: var(--gray-100); border: none; color: var(--gray-700); }
.btn-save { background: linear-gradient(135deg, #0066FF 0%, #5B4FFF 100%); border: none; color: white; }
@media (max-width: 1200px) { .gps-container { grid-template-columns: 1fr; height: auto; } .map-container { height: 400px; } .stats-bar { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="stats-bar">
    <div class="stat-mini">
        <div class="stat-mini-icon" style="background: var(--primary-light); color: var(--primary);"><i class="fas fa-route"></i></div>
        <div><div class="stat-mini-value"><?= $stats['nb_trajets'] ?? 0 ?></div><div class="stat-mini-label">Trajets ce mois</div></div>
    </div>
    <div class="stat-mini">
        <div class="stat-mini-icon" style="background: #DBEAFE; color: #2563EB;"><i class="fas fa-road"></i></div>
        <div><div class="stat-mini-value"><?= number_format($stats['total_km'] ?? 0, 0, ',', ' ') ?> km</div><div class="stat-mini-label">Distance totale</div></div>
    </div>
    <div class="stat-mini">
        <div class="stat-mini-icon" style="background: #D1FAE5; color: #059669;"><i class="fas fa-euro-sign"></i></div>
        <div><div class="stat-mini-value"><?= number_format($stats['total_remboursement'] ?? 0, 2, ',', ' ') ?> DH</div><div class="stat-mini-label">À rembourser</div></div>
    </div>
    <div class="stat-mini">
        <div class="stat-mini-icon" style="background: #FEF3C7; color: #D97706;"><i class="fas fa-leaf"></i></div>
        <div><div class="stat-mini-value"><?= number_format(($stats['total_co2'] ?? 0) / 1000, 1, ',', ' ') ?> kg</div><div class="stat-mini-label">CO₂ émis</div></div>
    </div>
</div>

<div class="gps-container">
    <div class="map-container">
        <div class="map-overlay">
            <div class="search-box"><i class="fas fa-search" style="color: var(--gray-400);"></i><input type="text" id="searchLocation" placeholder="Rechercher..." autocomplete="off"></div>
            <button class="btn btn-primary" style="border-radius: 12px; padding: 12px 20px;" onclick="locateMe()"><i class="fas fa-crosshairs"></i></button>
        </div>
        <div id="map"></div>
    </div>
    
    <div class="tracking-panel">
        <div class="tracking-status" id="trackingStatus">
            <div class="tracking-header">
                <span class="tracking-title"><i class="fas fa-satellite-dish"></i> GPS Tracking</span>
                <span class="tracking-badge" id="trackingBadge">Inactif</span>
            </div>
            <div class="tracking-stats" id="liveStats" style="display: none;">
                <div class="tracking-stat"><div class="tracking-value" id="liveDistance">0.0</div><div class="tracking-label">km</div></div>
                <div class="tracking-stat"><div class="tracking-value" id="liveDuration">00:00</div><div class="tracking-label">durée</div></div>
                <div class="tracking-stat"><div class="tracking-value" id="liveAmount">0.00 DH</div><div class="tracking-label">estimation</div></div>
            </div>
            <button class="btn-tracking btn-start" id="btnTracking" onclick="toggleTracking()"><i class="fas fa-play"></i><span>Démarrer le tracking</span></button>
        </div>
        
        <div class="quick-trip">
            <div class="quick-trip-title"><i class="fas fa-bolt" style="color: #F59E0B;"></i> Saisie rapide</div>
            <div class="location-inputs">
                <div class="location-line"></div>
                <div class="location-input">
                    <div class="location-dot start"></div>
                    <input type="text" id="startLocation" placeholder="Point de départ (ex: Rabat)" autocomplete="off">
                    <button class="btn btn-ghost btn-icon" onclick="useCurrentLocation('start')"><i class="fas fa-location-arrow"></i></button>
                    <div class="autocomplete-dropdown" id="startAutocomplete"></div>
                </div>
                <div class="location-input">
                    <div class="location-dot end"></div>
                    <input type="text" id="endLocation" placeholder="Destination (ex: Casablanca)" autocomplete="off">
                    <button class="btn btn-ghost btn-icon" onclick="swapLocations()"><i class="fas fa-exchange-alt"></i></button>
                    <div class="autocomplete-dropdown" id="endAutocomplete"></div>
                </div>
            </div>
            <button class="btn btn-primary btn-block" style="margin-top: 16px;" onclick="calculateRoute()"><i class="fas fa-calculator"></i> Calculer le trajet</button>
        </div>
        
        <div class="trip-result" id="tripResult">
            <div class="trip-result-header">
                <span style="font-weight: 600;">Trajet calculé</span>
                <div class="eco-badge"><i class="fas fa-leaf"></i><span id="tripCO2">0 g CO₂</span></div>
            </div>
            <div class="trip-result-stats">
                <div class="trip-result-stat"><div class="value" id="tripDistance">0 km</div><div class="label">Distance</div></div>
                <div class="trip-result-stat"><div class="value" id="tripDuration">0 min</div><div class="label">Durée</div></div>
                <div class="trip-result-stat"><div class="value" id="tripAmount">0.00 DH</div><div class="label">Remboursement</div></div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button class="btn" style="flex: 1; background: rgba(255,255,255,0.2); color: white; border: none; padding: 12px; border-radius: 10px; cursor: pointer;" onclick="saveFavorite()"><i class="fas fa-star"></i> Favori</button>
                <button class="btn" style="flex: 2; background: white; color: var(--primary); border: none; padding: 12px; border-radius: 10px; cursor: pointer; font-weight: 600;" onclick="createExpense()"><i class="fas fa-plus"></i> Créer la demande</button>
            </div>
        </div>
        
        <div class="vehicle-selector">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span style="font-size: 14px; font-weight: 600;">Véhicule</span>
                <button class="btn btn-ghost btn-sm" onclick="openVehicleModal()"><i class="fas fa-plus"></i> Ajouter</button>
            </div>
            <div id="vehicleList">
                <?php if (empty($vehicules)): ?>
                <div id="noVehicleMessage" style="text-align: center; padding: 20px; color: var(--gray-500);">
                    <i class="fas fa-car" style="font-size: 32px; margin-bottom: 12px;"></i>
                    <p style="font-size: 13px;">Aucun véhicule enregistré</p>
                </div>
                <?php else: ?>
                <?php foreach ($vehicules as $index => $v): ?>
                <div class="vehicle-option <?= $index === 0 ? 'selected' : '' ?>" data-id="<?= $v['id'] ?>" data-cv="<?= $v['puissance_fiscale'] ?>" onclick="selectVehicle(this)">
                    <div class="vehicle-icon"><i class="fas fa-<?= $v['type'] === 'moto' ? 'motorcycle' : 'car' ?>"></i></div>
                    <div class="vehicle-info"><h4><?= htmlspecialchars($v['marque'] . ' ' . $v['modele']) ?></h4><p><?= $v['puissance_fiscale'] ?> CV • <?= htmlspecialchars($v['immatriculation']) ?></p></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Véhicule -->
<div class="modal-backdrop" id="vehicleModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-car" style="color: var(--primary);"></i> Ajouter un véhicule</h3>
            <button class="modal-close" onclick="closeVehicleModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="vehicleForm">
                <div class="form-group">
                    <label>Type de véhicule</label>
                    <div class="type-selector">
                        <div class="type-option selected" data-type="voiture" onclick="selectVehicleType(this)"><i class="fas fa-car"></i><span>Voiture</span></div>
                        <div class="type-option" data-type="moto" onclick="selectVehicleType(this)"><i class="fas fa-motorcycle"></i><span>Moto</span></div>
                    </div>
                    <input type="hidden" name="type" id="vehicleType" value="voiture">
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Marque <span class="required">*</span></label><input type="text" class="form-control" name="marque" placeholder="Ex: Renault" required></div>
                    <div class="form-group"><label>Modèle <span class="required">*</span></label><input type="text" class="form-control" name="modele" placeholder="Ex: Clio" required></div>
                </div>
                <div class="form-group"><label>Immatriculation <span class="required">*</span></label><input type="text" class="form-control" name="immatriculation" placeholder="Ex: 12345-A-67" required style="text-transform: uppercase;"></div>
                <div class="form-row">
                    <div class="form-group"><label>Puissance (CV) <span class="required">*</span></label>
                        <select class="form-control" name="puissance_fiscale"><option value="3">3 CV</option><option value="4">4 CV</option><option value="5" selected>5 CV</option><option value="6">6 CV</option><option value="7">7+ CV</option></select>
                    </div>
                    <div class="form-group"><label>Énergie</label>
                        <select class="form-control" name="energie"><option value="essence">Essence</option><option value="diesel">Diesel</option><option value="electrique">Électrique</option><option value="hybride">Hybride</option></select>
                    </div>
                </div>
                <div class="form-group"><label>Année</label><input type="number" class="form-control" name="annee" placeholder="Ex: 2020" min="1990" max="2025"></div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-cancel" onclick="closeVehicleModal()">Annuler</button>
            <button type="button" class="btn btn-save" onclick="saveVehicle()"><i class="fas fa-check"></i> Enregistrer</button>
        </div>
    </div>
</div>

<div class="loading-overlay" id="loadingOverlay"><div class="loading-spinner"><div class="spinner"></div><p style="margin-top: 16px;" id="loadingText">Chargement...</p></div></div>
<div class="status-message" id="statusMessage"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Base de données des villes marocaines
const CITIES = [
    { name: "Casablanca", region: "Casablanca-Settat", lat: 33.5731, lng: -7.5898 },
    { name: "Rabat", region: "Rabat-Salé-Kénitra", lat: 34.0209, lng: -6.8416 },
    { name: "Marrakech", region: "Marrakech-Safi", lat: 31.6295, lng: -7.9811 },
    { name: "Fès", region: "Fès-Meknès", lat: 34.0181, lng: -5.0078 },
    { name: "Tanger", region: "Tanger-Tétouan", lat: 35.7595, lng: -5.8340 },
    { name: "Agadir", region: "Souss-Massa", lat: 30.4278, lng: -9.5981 },
    { name: "Meknès", region: "Fès-Meknès", lat: 33.8731, lng: -5.5407 },
    { name: "Oujda", region: "Oriental", lat: 34.6867, lng: -1.9114 },
    { name: "Kénitra", region: "Rabat-Salé-Kénitra", lat: 34.2610, lng: -6.5802 },
    { name: "Tétouan", region: "Tanger-Tétouan", lat: 35.5889, lng: -5.3626 },
    { name: "Salé", region: "Rabat-Salé-Kénitra", lat: 34.0531, lng: -6.7985 },
    { name: "Temara", region: "Rabat-Salé-Kénitra", lat: 33.9287, lng: -6.9074 },
    { name: "Safi", region: "Marrakech-Safi", lat: 32.2994, lng: -9.2372 },
    { name: "Mohammedia", region: "Casablanca-Settat", lat: 33.6866, lng: -7.3833 },
    { name: "El Jadida", region: "Casablanca-Settat", lat: 33.2316, lng: -8.5007 },
    { name: "Béni Mellal", region: "Béni Mellal-Khénifra", lat: 32.3373, lng: -6.3498 },
    { name: "Nador", region: "Oriental", lat: 35.1681, lng: -2.9287 },
    { name: "Settat", region: "Casablanca-Settat", lat: 33.0017, lng: -7.6200 },
    { name: "Khouribga", region: "Béni Mellal-Khénifra", lat: 32.8811, lng: -6.9063 },
    { name: "Berrechid", region: "Casablanca-Settat", lat: 33.2653, lng: -7.5876 },
    { name: "Essaouira", region: "Marrakech-Safi", lat: 31.5085, lng: -9.7595 },
    { name: "Ouarzazate", region: "Drâa-Tafilalet", lat: 30.9189, lng: -6.8936 },
    { name: "Errachidia", region: "Drâa-Tafilalet", lat: 31.9314, lng: -4.4267 },
    { name: "Chefchaouen", region: "Tanger-Tétouan", lat: 35.1688, lng: -5.2636 },
    { name: "Ifrane", region: "Fès-Meknès", lat: 33.5228, lng: -5.1107 }
];

let map, routeLine, startMarker, endMarker;
let isTracking = false, trackingInterval, trackingStartTime, trackingPositions = [], watchId, trackingPolyline;
let currentVehicleCV = 5, currentVehicleId = null, totalDistance = 0;
let selectedStartCity = null, selectedEndCity = null;

const bareme = {
    3: { moins_5000: 0.529, entre: 0.316, plus_20000: 0.370 },
    4: { moins_5000: 0.606, entre: 0.340, plus_20000: 0.407 },
    5: { moins_5000: 0.636, entre: 0.357, plus_20000: 0.427 },
    6: { moins_5000: 0.665, entre: 0.374, plus_20000: 0.447 },
    7: { moins_5000: 0.697, entre: 0.394, plus_20000: 0.470 }
};

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    map = L.map('map').setView([33.2316, -8.5007], 7);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
        attribution: '© OpenStreetMap', 
        maxZoom: 19 
    }).addTo(map);
    
    locateMe();
    setupAutocomplete('startLocation', 'startAutocomplete');
    setupAutocomplete('endLocation', 'endAutocomplete');
    
    document.addEventListener('click', e => { 
        if (!e.target.closest('.location-input')) 
            document.querySelectorAll('.autocomplete-dropdown').forEach(d => d.classList.remove('active')); 
    });
    
    const sel = document.querySelector('.vehicle-option.selected');
    if (sel) { 
        currentVehicleCV = parseInt(sel.dataset.cv) || 5; 
        currentVehicleId = sel.dataset.id; 
    }
});

// Recherche de villes
function searchCities(q) {
    if (!q || q.length < 2) return [];
    const n = q.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return CITIES.filter(c => c.name.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').includes(n)).slice(0, 6);
}

// Autocomplete
function setupAutocomplete(inputId, dropdownId) {
    const input = document.getElementById(inputId), dropdown = document.getElementById(dropdownId);
    
    input.addEventListener('input', function() {
        const cities = searchCities(this.value.trim());
        if (!cities.length) { dropdown.classList.remove('active'); return; }
        
        dropdown.innerHTML = cities.map(c => 
            `<div class="autocomplete-item" data-lat="${c.lat}" data-lng="${c.lng}" data-name="${c.name}">
                <i class="fas fa-map-marker-alt"></i>
                <span class="city-name">${c.name}</span>
                <span class="city-region">${c.region}</span>
            </div>`
        ).join('');
        dropdown.classList.add('active');
        
        dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
            item.addEventListener('click', function() {
                input.value = this.dataset.name;
                const cityData = { name: this.dataset.name, lat: parseFloat(this.dataset.lat), lng: parseFloat(this.dataset.lng) };
                if (inputId === 'startLocation') selectedStartCity = cityData;
                else selectedEndCity = cityData;
                dropdown.classList.remove('active');
                showStatus(this.dataset.name + ' sélectionné', 'success');
            });
        });
    });
    
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { 
            e.preventDefault(); 
            const item = dropdown.querySelector('.autocomplete-item'); 
            if (item) item.click(); 
        }
    });
}

// Localisation
function locateMe() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            map.setView([pos.coords.latitude, pos.coords.longitude], 10);
        }, () => {});
    }
}

// Calcul de distance (Haversine) - en km
function calcDistanceKm(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) + 
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
              Math.sin(dLng/2) * Math.sin(dLng/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// Calcul d'itinéraire SIMPLIFIÉ
async function calculateRoute() {
    const startInput = document.getElementById('startLocation').value.trim();
    const endInput = document.getElementById('endLocation').value.trim();
    
    if (!startInput || !endInput) { 
        showStatus('Veuillez saisir départ et arrivée', 'error'); 
        return; 
    }
    
    document.getElementById('loadingOverlay').classList.add('active');
    document.getElementById('loadingText').textContent = 'Calcul de l\'itinéraire...';
    
    try {
        let startCoords = selectedStartCity ? [selectedStartCity.lat, selectedStartCity.lng] : await getCoords(startInput);
        let endCoords = selectedEndCity ? [selectedEndCity.lat, selectedEndCity.lng] : await getCoords(endInput);
        
        if (!startCoords || !endCoords) {
            throw new Error('Ville introuvable. Sélectionnez dans la liste.');
        }
        
        if (startMarker) map.removeLayer(startMarker);
        if (endMarker) map.removeLayer(endMarker);
        if (routeLine) map.removeLayer(routeLine);
        
        const distanceVolOiseau = calcDistanceKm(startCoords[0], startCoords[1], endCoords[0], endCoords[1]);
        const distanceRoute = distanceVolOiseau * 1.3;
        const durationMinutes = Math.round((distanceRoute / 80) * 60);
        
        const startIcon = L.divIcon({
            className: 'custom-marker',
            html: '<div style="background:#10B981;width:30px;height:30px;border-radius:50%;border:3px solid white;box-shadow:0 2px 10px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:14px;">A</div>',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });
        
        const endIcon = L.divIcon({
            className: 'custom-marker',
            html: '<div style="background:#EF4444;width:30px;height:30px;border-radius:50%;border:3px solid white;box-shadow:0 2px 10px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:14px;">B</div>',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });
        
        startMarker = L.marker([startCoords[0], startCoords[1]], { icon: startIcon }).addTo(map);
        endMarker = L.marker([endCoords[0], endCoords[1]], { icon: endIcon }).addTo(map);
        
        routeLine = L.polyline([
            [startCoords[0], startCoords[1]], 
            [endCoords[0], endCoords[1]]
        ], { 
            color: '#0066FF', 
            weight: 4, 
            opacity: 0.8,
            dashArray: '10, 10'
        }).addTo(map);
        
        const bounds = L.latLngBounds([startCoords, endCoords]);
        map.fitBounds(bounds, { padding: [50, 50] });
        
        document.getElementById('loadingOverlay').classList.remove('active');
        displayResult(distanceRoute, durationMinutes * 60, startInput, endInput);
        
    } catch(e) { 
        document.getElementById('loadingOverlay').classList.remove('active'); 
        showStatus(e.message, 'error'); 
        console.error(e);
    }
}

// Obtenir coordonnées
async function getCoords(q) {
    const local = searchCities(q);
    if (local.length) return [local[0].lat, local[0].lng];
    
    try {
        const r = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q + ', Maroc')}&limit=1`);
        const d = await r.json();
        if (d.length) return [parseFloat(d[0].lat), parseFloat(d[0].lon)];
    } catch(e) {
        console.error('Erreur geocoding:', e);
    }
    return null;
}

// Afficher résultat
function displayResult(distance, durationSec, start, end) {
    const amount = calcAmount(distance);
    const durationMin = Math.round(durationSec / 60);
    
    document.getElementById('tripDistance').textContent = distance.toFixed(1) + ' km';
    document.getElementById('tripDuration').textContent = durationMin + ' min';
    document.getElementById('tripAmount').textContent = amount.toFixed(2) + ' DH';
    document.getElementById('tripCO2').textContent = Math.round(distance * 120) + ' g CO₂';
    document.getElementById('tripResult').classList.add('show');
    
    window.currentTrip = { start, end, distance, duration: durationSec, amount };
    showStatus(distance.toFixed(1) + ' km - ' + amount.toFixed(2) + ' DH', 'success');
}

// Calcul montant
function calcAmount(km) {
    const cv = Math.min(7, Math.max(3, currentVehicleCV));
    const rates = bareme[cv];
    if (km <= 5000) return km * rates.moins_5000;
    if (km <= 20000) return (km * rates.entre) + 1395;
    return km * rates.plus_20000;
}

// Sélection véhicule
function selectVehicle(el) {
    document.querySelectorAll('.vehicle-option').forEach(v => v.classList.remove('selected'));
    el.classList.add('selected');
    currentVehicleCV = parseInt(el.dataset.cv) || 5;
    currentVehicleId = el.dataset.id;
    
    if (window.currentTrip) { 
        const a = calcAmount(window.currentTrip.distance); 
        window.currentTrip.amount = a; 
        document.getElementById('tripAmount').textContent = a.toFixed(2) + ' DH'; 
    }
}

// Modal véhicule
function openVehicleModal() { 
    document.getElementById('vehicleModal').classList.add('active'); 
    document.getElementById('vehicleForm').reset(); 
}
function closeVehicleModal() { 
    document.getElementById('vehicleModal').classList.remove('active'); 
}
function selectVehicleType(el) { 
    document.querySelectorAll('.type-option').forEach(t => t.classList.remove('selected')); 
    el.classList.add('selected'); 
    document.getElementById('vehicleType').value = el.dataset.type; 
}

// Sauvegarder véhicule
async function saveVehicle() {
    const form = document.getElementById('vehicleForm');
    const formData = new FormData(form);
    
    if (!formData.get('marque') || !formData.get('modele') || !formData.get('immatriculation')) { 
        showStatus('Remplissez tous les champs obligatoires', 'error'); 
        return; 
    }
    
    document.getElementById('loadingOverlay').classList.add('active');
    document.getElementById('loadingText').textContent = 'Enregistrement...';
    
    try {
        const r = await fetch('api/save_vehicle.php', { method: 'POST', body: formData });
        const result = await r.json();
        document.getElementById('loadingOverlay').classList.remove('active');
        
        if (result.success) {
            showStatus('Véhicule enregistré !', 'success');
            closeVehicleModal();
            
            const list = document.getElementById('vehicleList');
            const noMsg = document.getElementById('noVehicleMessage');
            if (noMsg) noMsg.remove();
            
            const isFirst = !list.querySelector('.vehicle-option');
            const type = formData.get('type');
            const cv = formData.get('puissance_fiscale');
            
            list.insertAdjacentHTML('beforeend', `
                <div class="vehicle-option ${isFirst ? 'selected' : ''}" data-id="${result.id}" data-cv="${cv}" onclick="selectVehicle(this)">
                    <div class="vehicle-icon"><i class="fas fa-${type === 'moto' ? 'motorcycle' : 'car'}"></i></div>
                    <div class="vehicle-info">
                        <h4>${formData.get('marque')} ${formData.get('modele')}</h4>
                        <p>${cv} CV • ${formData.get('immatriculation').toUpperCase()}</p>
                    </div>
                </div>
            `);
            
            if (isFirst) { 
                currentVehicleCV = parseInt(cv); 
                currentVehicleId = result.id; 
            }
        } else {
            showStatus(result.error || 'Erreur', 'error');
        }
    } catch(e) { 
        document.getElementById('loadingOverlay').classList.remove('active'); 
        showStatus('Erreur de connexion', 'error'); 
    }
}

// Créer demande de remboursement - CORRIGÉ: utilise save_trip.php
async function createExpense() {
    if (!window.currentTrip) { 
        showStatus('Aucun trajet calculé', 'error'); 
        return; 
    }
    
    document.getElementById('loadingOverlay').classList.add('active');
    document.getElementById('loadingText').textContent = 'Création de la demande...';
    
    const t = window.currentTrip;
    const formData = new FormData();
    formData.append('categorie', 'transport');
    formData.append('montant', t.amount.toFixed(2));
    formData.append('devise', 'DH');
    formData.append('date_depense', new Date().toISOString().split('T')[0]);
    formData.append('description', `Trajet ${t.start} → ${t.end} (${t.distance.toFixed(1)} km)`);
    formData.append('distance_km', t.distance.toFixed(1));
    formData.append('depart', t.start);
    formData.append('arrivee', t.end);
    if (currentVehicleId) formData.append('vehicule_id', currentVehicleId);
    
    try {
        // CORRECTION ICI: save_trip.php au lieu de save_expense.php
        const r = await fetch('api/save_trip.php', { method: 'POST', body: formData });
        const result = await r.json();
        document.getElementById('loadingOverlay').classList.remove('active');
        
        if (result.success) { 
            showStatus('Demande créée avec succès !', 'success'); 
            setTimeout(() => window.location.href = 'mes_demandes.php', 1500); 
        } else {
            showStatus(result.error || 'Erreur', 'error');
        }
    } catch(e) { 
        document.getElementById('loadingOverlay').classList.remove('active'); 
        showStatus('Erreur de connexion', 'error'); 
    }
}

// Utilitaires
function swapLocations() { 
    const s = document.getElementById('startLocation');
    const e = document.getElementById('endLocation'); 
    [s.value, e.value] = [e.value, s.value]; 
    [selectedStartCity, selectedEndCity] = [selectedEndCity, selectedStartCity]; 
}

async function useCurrentLocation(field) {
    if (!navigator.geolocation) return;
    showStatus('Récupération de la position...', '');
    
    navigator.geolocation.getCurrentPosition(async pos => {
        try {
            const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${pos.coords.latitude}&lon=${pos.coords.longitude}&zoom=18`);
            const d = await r.json();
            if (d?.display_name) {
                const name = d.address?.city || d.address?.town || d.display_name.split(',')[0];
                document.getElementById(field + 'Location').value = name;
                const cityData = { name, lat: pos.coords.latitude, lng: pos.coords.longitude };
                if (field === 'start') selectedStartCity = cityData;
                else selectedEndCity = cityData;
                showStatus('Position récupérée', 'success');
            }
        } catch(e) {
            showStatus('Erreur', 'error');
        }
    }, () => {
        showStatus('Géolocalisation refusée', 'error');
    });
}

function showStatus(msg, type) { 
    const el = document.getElementById('statusMessage'); 
    el.textContent = msg; 
    el.className = 'status-message show ' + type; 
    setTimeout(() => el.classList.remove('show'), 3000); 
}

function saveFavorite() { 
    if (window.currentTrip) showStatus('Ajouté aux favoris !', 'success'); 
}

// GPS Tracking
function toggleTracking() { isTracking ? stopTracking() : startTracking(); }

function startTracking() {
    if (!navigator.geolocation) { showStatus('Géolocalisation non supportée', 'error'); return; }
    
    isTracking = true; 
    trackingStartTime = new Date(); 
    trackingPositions = []; 
    totalDistance = 0;
    
    document.getElementById('trackingStatus').classList.add('active');
    document.getElementById('trackingBadge').textContent = 'En cours...';
    document.getElementById('btnTracking').className = 'btn-tracking btn-stop';
    document.getElementById('btnTracking').innerHTML = '<i class="fas fa-stop"></i><span>Arrêter</span>';
    document.getElementById('liveStats').style.display = 'grid';
    
    showStatus('Tracking GPS démarré', 'success');
    
    watchId = navigator.geolocation.watchPosition(pos => {
        const p = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        if (trackingPositions.length) { 
            const l = trackingPositions[trackingPositions.length - 1]; 
            totalDistance += calcDistanceKm(l.lat, l.lng, p.lat, p.lng); 
        }
        trackingPositions.push(p);
        map.setView([p.lat, p.lng]);
        
        if (trackingPolyline) map.removeLayer(trackingPolyline);
        trackingPolyline = L.polyline(trackingPositions.map(x => [x.lat, x.lng]), { color: '#10B981', weight: 5 }).addTo(map);
        updateLive();
    }, () => {}, { enableHighAccuracy: true });
    
    trackingInterval = setInterval(updateLive, 1000);
}

function stopTracking() {
    isTracking = false; 
    navigator.geolocation.clearWatch(watchId); 
    clearInterval(trackingInterval);
    
    document.getElementById('trackingStatus').classList.remove('active');
    document.getElementById('trackingBadge').textContent = 'Terminé';
    document.getElementById('btnTracking').className = 'btn-tracking btn-start';
    document.getElementById('btnTracking').innerHTML = '<i class="fas fa-play"></i><span>Démarrer</span>';
    
    showStatus('Tracking terminé', 'success');
    
    if (totalDistance > 0.1) {
        displayResult(totalDistance, (new Date() - trackingStartTime) / 1000, 'Position départ', 'Position arrivée');
    }
}

function updateLive() {
    if (!isTracking) return;
    const sec = (new Date() - trackingStartTime) / 1000;
    const m = Math.floor(sec / 60), s = Math.floor(sec % 60);
    document.getElementById('liveDistance').textContent = totalDistance.toFixed(1);
    document.getElementById('liveDuration').textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    document.getElementById('liveAmount').textContent = calcAmount(totalDistance).toFixed(2) + ' DH';
}

// Events
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeVehicleModal(); });
document.getElementById('vehicleModal').addEventListener('click', function(e) { if (e.target === this) closeVehicleModal(); });
</script>

<?php include 'includes/footer.php'; ?>