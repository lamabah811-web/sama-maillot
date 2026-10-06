-- =============================================================================
-- SAMA MAILLOT — migration 002 : rôle écrit en clair dans utilisateurs
--
-- Avant : utilisateurs.role_id (numéro : 1, 2, 3) → roles.id
-- Après : utilisateurs.role (texte : 'client', 'vendeur', 'admin') → roles.code
--
-- La table roles est conservée : la clé étrangère garantit qu'on ne peut écrire
-- qu'un rôle existant (une faute de frappe comme « vendeurr » est refusée).
--
-- À exécuter UNE SEULE FOIS, après 001_table_roles.sql, sur une base existante.
-- Une base neuve n'en a pas besoin : base_de_donnees.sql contient déjà la nouvelle structure.
-- Aucune donnée n'est perdue : le rôle de chaque compte est recopié avant de retirer role_id.
-- =============================================================================

USE sama_maillot;

-- 1. Nouvelle colonne texte, remplie à partir du numéro
ALTER TABLE utilisateurs ADD COLUMN role VARCHAR(20) NULL AFTER id;

UPDATE utilisateurs u
JOIN roles r ON r.id = u.role_id
SET u.role = r.code;

ALTER TABLE utilisateurs MODIFY role VARCHAR(20) NOT NULL COMMENT 'client, vendeur ou admin (voir table roles)';

-- 2. Remplacement des index et de la clé étrangère, puis suppression de role_id
--    (MariaDB exige de retirer l'ancienne clé étrangère dans une instruction séparée,
--     car la nouvelle porte le même nom)
ALTER TABLE utilisateurs DROP FOREIGN KEY fk_utilisateurs_role;

ALTER TABLE utilisateurs
    DROP INDEX uq_utilisateurs_telephone_role,
    DROP INDEX idx_utilisateurs_role_statut,
    DROP COLUMN role_id,
    ADD UNIQUE KEY uq_utilisateurs_telephone_role (telephone, role),
    ADD KEY idx_utilisateurs_role_statut (role, statut),
    ADD CONSTRAINT fk_utilisateurs_role FOREIGN KEY (role) REFERENCES roles (code)
        ON UPDATE CASCADE ON DELETE RESTRICT;
