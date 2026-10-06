-- =============================================================================
-- SAMA MAILLOT — migration 005 : messages du formulaire de contact (§7.3)
--
-- À importer UNE fois dans phpMyAdmin (onglet Importer) sur une base créée avant cette étape.
-- Une nouvelle installation n'en a pas besoin : base_de_donnees.sql contient déjà cette table.
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- messages_contact : messages envoyés depuis la page Contact. Ils sont lus et traités
-- par l'administration (lot 5) ; une copie part par e-mail au support si email_support
-- est renseigné dans les paramètres.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages_contact (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id  INT UNSIGNED NULL COMMENT 'Compte connecté au moment de l''envoi',
    nom             VARCHAR(101) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    telephone       VARCHAR(13)  NULL,
    sujet           ENUM('commande', 'vendeur', 'compte', 'signalement', 'partenariat', 'autre') NOT NULL,
    numero_commande VARCHAR(20)  NULL,
    message         TEXT         NOT NULL,
    adresse_ip      VARCHAR(45)  NULL,
    statut          ENUM('nouveau', 'traite') NOT NULL DEFAULT 'nouveau',
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_statut (statut, cree_le),
    KEY idx_messages_ip (adresse_ip, cree_le),
    CONSTRAINT fk_messages_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO parametres (cle, valeur, description) VALUES
    ('email_support', '', 'Adresse e-mail du support (copie des messages de contact) — à renseigner');
