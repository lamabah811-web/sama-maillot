-- =============================================================================
-- SAMA MAILLOT — migration 007 : notifications dans le site (§22.1)
--
-- Cloche avec compteur dans l'en-tête, page « Notifications », marquer comme lu.
-- À importer UNE fois sur une base créée avant cette étape.
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id  INT UNSIGNED NOT NULL,
    type            VARCHAR(40)  NOT NULL COMMENT 'Ex. : commande_nouvelle, commande_confirmee…',
    titre           VARCHAR(120) NOT NULL,
    message         VARCHAR(255) NOT NULL,
    lien            VARCHAR(255) NULL COMMENT 'Chemin interne à ouvrir (ex. vendeur/commande.php?id=12)',
    lue_le          DATETIME     NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_utilisateur (utilisateur_id, lue_le, cree_le),
    CONSTRAINT fk_notifications_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
