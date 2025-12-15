-- ============================================
-- ExpensePro - Ajout du support multilingue
-- Script de migration pour la base de données
-- ============================================

-- Ajouter la colonne langue à la table users
ALTER TABLE `users` 
ADD COLUMN `langue` VARCHAR(5) DEFAULT 'fr' AFTER `theme`;

-- Mettre à jour les utilisateurs existants avec la langue par défaut
UPDATE `users` SET `langue` = 'fr' WHERE `langue` IS NULL;

-- Message de confirmation
SELECT 'Migration multilingue terminée avec succès!' AS message;
