-- ============================================
-- ExpensePro - Migration Multilingue FR/EN
-- Exécuter dans phpMyAdmin
-- ============================================

-- Ajouter la colonne langue à la table users
ALTER TABLE `users` ADD COLUMN `langue` VARCHAR(5) DEFAULT 'fr' AFTER `theme`;

-- Mettre à jour les utilisateurs existants
UPDATE `users` SET `langue` = 'fr' WHERE `langue` IS NULL OR `langue` = '';

-- Vérification
SELECT id, nom, prenom, langue FROM users;

-- ============================================
-- FAIT !
-- Langues disponibles: fr (Français), en (English)
-- ============================================
