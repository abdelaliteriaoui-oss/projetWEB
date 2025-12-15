-- =====================================================
-- ExpensePro - Tables pour GPS Tracking
-- =====================================================

-- Table des véhicules
CREATE TABLE IF NOT EXISTS vehicules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('voiture', 'moto') DEFAULT 'voiture',
    marque VARCHAR(100) NOT NULL,
    modele VARCHAR(100) NOT NULL,
    immatriculation VARCHAR(20) NOT NULL,
    puissance_fiscale INT NOT NULL DEFAULT 5,
    energie ENUM('essence', 'diesel', 'electrique', 'hybride') DEFAULT 'essence',
    annee INT,
    favori BOOLEAN DEFAULT FALSE,
    actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des trajets
CREATE TABLE IF NOT EXISTS trajets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicule_id INT,
    demande_id INT,
    
    -- Informations du trajet
    date_trajet DATE NOT NULL,
    adresse_depart TEXT,
    adresse_arrivee TEXT,
    depart_ville VARCHAR(100),
    arrivee_ville VARCHAR(100),
    depart_lat DECIMAL(10, 8),
    depart_lng DECIMAL(11, 8),
    arrivee_lat DECIMAL(10, 8),
    arrivee_lng DECIMAL(11, 8),
    
    -- Étapes intermédiaires (JSON)
    etapes JSON,
    
    -- Distances et calculs
    distance_km DECIMAL(10, 2) NOT NULL,
    duree_secondes INT,
    montant_remboursement DECIMAL(10, 2) NOT NULL,
    taux_km DECIMAL(5, 3),
    
    -- Tracking GPS (si utilisé)
    positions_gps JSON,
    tracking_mode ENUM('manual', 'calculated', 'gps') DEFAULT 'calculated',
    
    -- Environnement
    emission_co2 INT, -- en grammes
    
    -- Classification
    type_trajet ENUM('professionnel', 'personnel', 'medical', 'autre') DEFAULT 'professionnel',
    motif VARCHAR(255),
    client_id INT,
    projet_id INT,
    
    -- Métadonnées
    aller_retour BOOLEAN DEFAULT FALSE,
    nb_trajets INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicule_id) REFERENCES vehicules(id) ON DELETE SET NULL,
    FOREIGN KEY (demande_id) REFERENCES demandes(id) ON DELETE SET NULL,
    INDEX idx_user_date (user_id, date_trajet),
    INDEX idx_demande (demande_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des trajets favoris
CREATE TABLE IF NOT EXISTS trajets_favoris (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    adresse_depart TEXT NOT NULL,
    adresse_arrivee TEXT NOT NULL,
    depart_ville VARCHAR(100),
    arrivee_ville VARCHAR(100),
    distance_km DECIMAL(10, 2),
    nb_utilisations INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des barèmes kilométriques
CREATE TABLE IF NOT EXISTS baremes_km (
    id INT AUTO_INCREMENT PRIMARY KEY,
    annee INT NOT NULL,
    type_vehicule ENUM('voiture', 'moto') DEFAULT 'voiture',
    puissance_fiscale VARCHAR(10) NOT NULL,
    tranche VARCHAR(20) NOT NULL, -- 'moins_5000', '5001_20000', 'plus_20000'
    taux DECIMAL(5, 3) NOT NULL,
    prime DECIMAL(10, 2) DEFAULT 0,
    actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_annee_type (annee, type_vehicule)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insérer les barèmes 2024
INSERT INTO baremes_km (annee, type_vehicule, puissance_fiscale, tranche, taux, prime) VALUES
-- Voiture
(2024, 'voiture', '3cv', 'moins_5000', 0.529, 0),
(2024, 'voiture', '3cv', '5001_20000', 0.316, 1065),
(2024, 'voiture', '3cv', 'plus_20000', 0.370, 0),
(2024, 'voiture', '4cv', 'moins_5000', 0.606, 0),
(2024, 'voiture', '4cv', '5001_20000', 0.340, 1330),
(2024, 'voiture', '4cv', 'plus_20000', 0.407, 0),
(2024, 'voiture', '5cv', 'moins_5000', 0.636, 0),
(2024, 'voiture', '5cv', '5001_20000', 0.357, 1395),
(2024, 'voiture', '5cv', 'plus_20000', 0.427, 0),
(2024, 'voiture', '6cv', 'moins_5000', 0.665, 0),
(2024, 'voiture', '6cv', '5001_20000', 0.374, 1457),
(2024, 'voiture', '6cv', 'plus_20000', 0.447, 0),
(2024, 'voiture', '7cv_plus', 'moins_5000', 0.697, 0),
(2024, 'voiture', '7cv_plus', '5001_20000', 0.394, 1515),
(2024, 'voiture', '7cv_plus', 'plus_20000', 0.470, 0),
-- Moto (>50cc)
(2024, 'moto', '1_2cv', 'moins_3000', 0.395, 0),
(2024, 'moto', '1_2cv', '3001_6000', 0.099, 891),
(2024, 'moto', '1_2cv', 'plus_6000', 0.248, 0),
(2024, 'moto', '3_5cv', 'moins_3000', 0.468, 0),
(2024, 'moto', '3_5cv', '3001_6000', 0.082, 1158),
(2024, 'moto', '3_5cv', 'plus_6000', 0.275, 0),
(2024, 'moto', '5cv_plus', 'moins_3000', 0.606, 0),
(2024, 'moto', '5cv_plus', '3001_6000', 0.079, 1583),
(2024, 'moto', '5cv_plus', 'plus_6000', 0.343, 0);

-- Vue pour les statistiques de trajets par utilisateur
CREATE OR REPLACE VIEW v_stats_trajets_user AS
SELECT 
    user_id,
    YEAR(date_trajet) as annee,
    MONTH(date_trajet) as mois,
    COUNT(*) as nb_trajets,
    SUM(distance_km) as total_km,
    SUM(montant_remboursement) as total_remboursement,
    SUM(emission_co2) as total_co2,
    AVG(distance_km) as moyenne_km_trajet
FROM trajets
GROUP BY user_id, YEAR(date_trajet), MONTH(date_trajet);

-- Vue pour le classement des trajets favoris
CREATE OR REPLACE VIEW v_trajets_favoris_populaires AS
SELECT 
    tf.*,
    u.nom as user_nom,
    u.prenom as user_prenom
FROM trajets_favoris tf
JOIN users u ON tf.user_id = u.id
ORDER BY tf.nb_utilisations DESC;
