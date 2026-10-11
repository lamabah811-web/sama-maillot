-- =============================================================================
-- SAMA MAILLOT — migration 015 : connexion avec Google et Facebook
-- À importer UNE fois sur une base créée avant cette étape.
--
-- Un compte est TOUJOURS retrouvé par (fournisseur + identifiant chez le fournisseur),
-- jamais par son e-mail seul. Un compte créé via Google ou Facebook peut ne pas avoir
-- de mot de passe (mot_de_passe_hache NULL, déjà autorisé).
-- Aucun jeton d'accès Google ou Facebook n'est conservé.
-- =============================================================================

SET NAMES utf8mb4;

-- E-mail prouvé (Google « email_verified », e-mail Facebook, code ou lien reçu par e-mail)
ALTER TABLE utilisateurs
    ADD COLUMN email_verifie TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = adresse e-mail prouvée' AFTER email;

-- Nouveaux usages des codes : liaison d'un compte existant, e-mail saisi après Facebook
ALTER TABLE codes_verification
    MODIFY type ENUM('verification_telephone', 'reinitialisation_sms', 'reinitialisation_email',
                     'liaison_sociale_email', 'liaison_sociale_sms', 'email_connexion_sociale') NOT NULL;

-- Comptes Google / Facebook liés à un compte SAMA MAILLOT (un seul par fournisseur et par compte)
CREATE TABLE IF NOT EXISTS connexions_sociales (
    id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id           INT UNSIGNED NOT NULL,
    fournisseur              ENUM('google', 'facebook') NOT NULL,
    identifiant_fournisseur  VARCHAR(255) NOT NULL COMMENT '« sub » Google ou « id » Facebook (propre à l''application)',
    email_fournisseur        VARCHAR(255) NULL,
    cree_le                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion_le    DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sociales_fournisseur_identifiant (fournisseur, identifiant_fournisseur),
    UNIQUE KEY uq_sociales_utilisateur_fournisseur (utilisateur_id, fournisseur),
    CONSTRAINT fk_sociales_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demandes de suppression envoyées par Meta (rappel « suppression des données »)
CREATE TABLE IF NOT EXISTS demandes_suppression_facebook (
    id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifiant_fournisseur  VARCHAR(255) NOT NULL,
    utilisateur_id           INT UNSIGNED NULL COMMENT 'Compte concerné (anonymisation différée si commandes en cours)',
    code_confirmation        CHAR(20)     NOT NULL,
    statut                   ENUM('recue', 'traitee') NOT NULL DEFAULT 'recue',
    recue_le                 DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    traitee_le               DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_suppression_code (code_confirmation),
    KEY idx_suppression_statut (statut),
    CONSTRAINT fk_suppression_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limitation : 10 connexions Google / Facebook démarrées par adresse IP et par 15 minutes
CREATE TABLE IF NOT EXISTS tentatives_connexion_sociale (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fournisseur  ENUM('google', 'facebook') NOT NULL,
    adresse_ip   VARCHAR(45)  NOT NULL,
    cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tentatives_sociales_ip (adresse_ip, cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
