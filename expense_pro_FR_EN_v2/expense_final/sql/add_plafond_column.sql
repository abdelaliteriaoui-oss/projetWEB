-- Script pour ajouter la colonne plafond à la table categories_frais
-- Exécutez ce script si vous voulez utiliser la fonctionnalité de plafond par catégorie

-- Vérifier si la colonne existe déjà avant de l'ajouter
SET @dbname = DATABASE();
SET @tablename = 'categories_frais';
SET @columnname = 'plafond';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND COLUMN_NAME = @columnname
  ) > 0,
  'SELECT "La colonne plafond existe déjà"',
  'ALTER TABLE categories_frais ADD COLUMN plafond DECIMAL(10,2) DEFAULT 0 AFTER description'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Alternative simple (si le script ci-dessus ne fonctionne pas):
-- ALTER TABLE categories_frais ADD COLUMN plafond DECIMAL(10,2) DEFAULT 0 AFTER description;
