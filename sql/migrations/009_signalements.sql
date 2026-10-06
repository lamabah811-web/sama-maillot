-- =============================================================================
-- SAMA MAILLOT — migration 009 : signalements et suspension automatique (§21)
--
-- Règle du porteur du projet : seul un client ayant passé une commande non annulée
-- CHEZ CE VENDEUR peut signaler ce vendeur ou l'un de ses maillots.
-- 3 signalements de vendeur par 3 clients différents sur 90 jours → suspension automatique.
-- À importer UNE fois sur une base créée avant cette étape.
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS signalements (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    auteur_id       INT UNSIGNED NOT NULL COMMENT 'Client qui signale',
    type            ENUM('vendeur', 'maillot') NOT NULL,
    boutique_id     INT UNSIGNED NOT NULL,
    maillot_id      INT UNSIGNED NULL COMMENT 'Signalement de maillot uniquement',
    motif           VARCHAR(40)  NOT NULL,
    description     TEXT         NOT NULL,
    statut          ENUM('ouvert', 'retenu', 'classe') NOT NULL DEFAULT 'ouvert'
                        COMMENT 'classe = jugé infondé : ne compte plus pour la suspension',
    decision        VARCHAR(255) NULL COMMENT 'Motif de la décision de l''administrateur',
    traite_par      INT UNSIGNED NULL,
    traite_le       DATETIME     NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_signalements_statut (statut, cree_le),
    KEY idx_signalements_boutique (boutique_id, type, cree_le),
    KEY idx_signalements_auteur (auteur_id, boutique_id, cree_le),
    CONSTRAINT fk_signalements_auteur FOREIGN KEY (auteur_id) REFERENCES utilisateurs (id) ON DELETE CASCADE,
    CONSTRAINT fk_signalements_boutique FOREIGN KEY (boutique_id) REFERENCES boutiques (id) ON DELETE CASCADE,
    CONSTRAINT fk_signalements_maillot FOREIGN KEY (maillot_id) REFERENCES maillots (id) ON DELETE SET NULL,
    CONSTRAINT fk_signalements_admin FOREIGN KEY (traite_par) REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suspension automatique et contestation du vendeur (§21.2)
ALTER TABLE boutiques
    ADD COLUMN IF NOT EXISTS suspension_auto_le DATETIME NULL COMMENT 'Date de la suspension automatique (signalements)' AFTER statut,
    ADD COLUMN IF NOT EXISTS contestation TEXT NULL COMMENT 'Contestation envoyée par le vendeur suspendu' AFTER suspension_auto_le,
    ADD COLUMN IF NOT EXISTS contestation_le DATETIME NULL AFTER contestation;
