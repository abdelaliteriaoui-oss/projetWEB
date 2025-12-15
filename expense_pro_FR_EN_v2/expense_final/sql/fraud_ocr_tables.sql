-- ==========================================
-- ExpensePro - Tables pour OCR et Détection de Fraude
-- ==========================================

-- Table pour les scans OCR
CREATE TABLE IF NOT EXISTS ocr_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    vendor VARCHAR(255) DEFAULT '',
    date DATE DEFAULT NULL,
    amount DECIMAL(10,2) DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'MAD',
    invoice_number VARCHAR(100) DEFAULT '',
    description TEXT,
    confidence INT DEFAULT 0,
    raw_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les alertes de fraude
CREATE TABLE IF NOT EXISTS fraud_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    demande_id INT NOT NULL,
    user_id INT NOT NULL,
    manager_id INT NOT NULL,
    alert_type ENUM('duplicate', 'outlier', 'weekend', 'over_limit', 'frequency', 'no_receipt', 'round_amount') NOT NULL,
    severity ENUM('low', 'medium', 'high') DEFAULT 'medium',
    description TEXT,
    status ENUM('pending', 'reviewed', 'dismissed', 'confirmed') DEFAULT 'pending',
    reviewed_at DATETIME DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (demande_id) REFERENCES demandes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index pour améliorer les performances
CREATE INDEX idx_ocr_scans_user ON ocr_scans(user_id);
CREATE INDEX idx_ocr_scans_date ON ocr_scans(created_at);
CREATE INDEX idx_fraud_alerts_manager ON fraud_alerts(manager_id);
CREATE INDEX idx_fraud_alerts_status ON fraud_alerts(status);
CREATE INDEX idx_fraud_alerts_type ON fraud_alerts(alert_type);

-- Vue pour les statistiques de fraude par manager
CREATE OR REPLACE VIEW v_fraud_stats AS
SELECT 
    manager_id,
    COUNT(*) as total_alerts,
    SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_severity,
    SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium_severity,
    SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low_severity,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_review,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_fraud
FROM fraud_alerts
GROUP BY manager_id;
