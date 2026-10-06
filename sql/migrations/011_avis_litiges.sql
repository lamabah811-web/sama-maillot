-- =============================================================================
-- SAMA MAILLOT — migration 011 : avis sur les vendeurs (§20) et réclamations / litiges (§16)
-- À importer UNE fois sur une base créée avant cette étape.
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- avis : un avis par commande « Reçue », sur la boutique (pas sur le maillot).
-- Modifiable une fois dans les 7 jours ; réponse publique du vendeur ; masquage par l'admin.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS avis (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    commande_id     INT UNSIGNED NOT NULL,
    boutique_id     INT UNSIGNED NOT NULL,
    client_id       INT UNSIGNED NOT NULL,
    note            TINYINT UNSIGNED NOT NULL COMMENT '1 à 5',
    commentaire     VARCHAR(500) NULL,
    modifications   TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Une seule modification autorisée',
    reponse         VARCHAR(500) NULL COMMENT 'Réponse publique du vendeur',
    reponse_le      DATETIME     NULL,
    visible         TINYINT(1)   NOT NULL DEFAULT 1,
    motif_masquage  VARCHAR(255) NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_avis_commande (commande_id),
    KEY idx_avis_boutique (boutique_id, visible, cree_le),
    CONSTRAINT fk_avis_commande FOREIGN KEY (commande_id) REFERENCES commandes (id) ON DELETE CASCADE,
    CONSTRAINT fk_avis_boutique FOREIGN KEY (boutique_id) REFERENCES boutiques (id) ON DELETE CASCADE,
    CONSTRAINT fk_avis_client FOREIGN KEY (client_id) REFERENCES utilisateurs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- litiges : une réclamation par commande. Paiement à la livraison : médiation (§16.3) ;
-- une réclamation jugée fondée compte comme un signalement du vendeur (§21).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS litiges (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    commande_id         INT UNSIGNED NOT NULL,
    client_id           INT UNSIGNED NOT NULL,
    boutique_id         INT UNSIGNED NOT NULL,
    motif               VARCHAR(40)  NOT NULL,
    statut              ENUM('ouvert', 'clos_amiable', 'decide') NOT NULL DEFAULT 'ouvert',
    statut_avant        VARCHAR(30)  NOT NULL COMMENT 'Statut de la commande avant le litige',
    reponse_limite_le   DATETIME     NOT NULL COMMENT 'Le vendeur a 48 h pour répondre',
    vendeur_a_repondu   TINYINT(1)   NOT NULL DEFAULT 0,
    decision            ENUM('fondee', 'non_fondee') NULL,
    decision_motif      VARCHAR(500) NULL,
    decide_par          INT UNSIGNED NULL,
    clos_le             DATETIME     NULL,
    cree_le             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_litiges_commande (commande_id),
    KEY idx_litiges_statut (statut, cree_le),
    KEY idx_litiges_boutique (boutique_id),
    CONSTRAINT fk_litiges_commande FOREIGN KEY (commande_id) REFERENCES commandes (id) ON DELETE CASCADE,
    CONSTRAINT fk_litiges_client FOREIGN KEY (client_id) REFERENCES utilisateurs (id) ON DELETE CASCADE,
    CONSTRAINT fk_litiges_boutique FOREIGN KEY (boutique_id) REFERENCES boutiques (id) ON DELETE CASCADE,
    CONSTRAINT fk_litiges_admin FOREIGN KEY (decide_par) REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages du litige (client, vendeur, administration), avec jusqu'à 3 photos privées chacun
CREATE TABLE IF NOT EXISTS litige_messages (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    litige_id       INT UNSIGNED NOT NULL,
    auteur_id       INT UNSIGNED NULL,
    auteur_role     ENUM('client', 'vendeur', 'admin') NOT NULL,
    message         TEXT         NOT NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_litige_messages (litige_id, cree_le),
    CONSTRAINT fk_litige_messages_litige FOREIGN KEY (litige_id) REFERENCES litiges (id) ON DELETE CASCADE,
    CONSTRAINT fk_litige_messages_auteur FOREIGN KEY (auteur_id) REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS litige_photos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id      INT UNSIGNED NOT NULL,
    fichier         VARCHAR(100) NOT NULL COMMENT 'stockage/litiges/ (hors du site public)',
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_litige_photos (message_id),
    CONSTRAINT fk_litige_photos_message FOREIGN KEY (message_id) REFERENCES litige_messages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
