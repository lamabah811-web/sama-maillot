-- =============================================================================
-- SAMA MAILLOT — migration 001 : rôles dans une table séparée
--
-- Avant : colonne utilisateurs.role (ENUM 'client', 'vendeur', 'admin').
-- Après : table roles + colonne utilisateurs.role_id (clé étrangère).
--
-- À exécuter UNE SEULE FOIS sur une base créée avant cette migration
-- (phpMyAdmin → base sama_maillot → onglet « Importer »).
-- Une base neuve n'en a pas besoin : base_de_donnees.sql contient déjà la nouvelle structure.
-- Aucune donnée n'est perdue : le rôle de chaque compte est recopié avant de retirer
-- l'ancienne colonne.
-- =============================================================================

USE sama_maillot;

-- 1. Table des rôles (un compte = un seul rôle, §5.1)
CREATE TABLE IF NOT EXISTS roles (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code         VARCHAR(20)  NOT NULL COMMENT 'Utilisé dans le code : client, vendeur, admin',
    libelle      VARCHAR(50)  NOT NULL COMMENT 'Affiché à l''écran',
    description  VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (id, code, libelle, description) VALUES
    (1, 'client',  'Client',         'Commande, paie, suit ses commandes, confirme la réception, note et signale les vendeurs.'),
    (2, 'vendeur', 'Vendeur',        'Gère sa boutique, ses maillots, son stock, ses commandes, son abonnement et son portefeuille.'),
    (3, 'admin',   'Administrateur', 'Modère, arbitre les litiges, valide les abonnements, effectue les versements.');

-- 2. Nouvelle colonne, remplie à partir de l'ancienne
ALTER TABLE utilisateurs ADD COLUMN role_id INT UNSIGNED NULL AFTER id;

UPDATE utilisateurs u
JOIN roles r ON r.code = u.role
SET u.role_id = r.id;

ALTER TABLE utilisateurs MODIFY role_id INT UNSIGNED NOT NULL;

-- 3. Index et contraintes : un même numéro pour 2 comptes maximum (un client + un vendeur, §8.3)
ALTER TABLE utilisateurs
    DROP INDEX uq_utilisateurs_telephone_role,
    DROP INDEX idx_utilisateurs_role_statut,
    DROP COLUMN role,
    ADD UNIQUE KEY uq_utilisateurs_telephone_role (telephone, role_id),
    ADD KEY idx_utilisateurs_role_statut (role_id, statut),
    ADD CONSTRAINT fk_utilisateurs_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT;
