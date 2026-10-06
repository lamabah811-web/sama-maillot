-- =============================================================================
-- SAMA MAILLOT — migration 010 : abonnements, paiements Wave, rappels, file des SMS (lot 6)
-- §18 (abonnements), §22 (SMS en file d'attente), §27.3 (tâches planifiées)
-- À importer UNE fois sur une base créée avant cette étape.
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- paiements_abonnement : renouvellement manuel par Wave (§18.4). La référence de
-- transaction Wave est UNIQUE : elle ne peut servir qu'une fois. La capture d'écran est
-- stockée HORS du dossier public (stockage/paiements/), lisible seulement par l'admin.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS paiements_abonnement (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    boutique_id     INT UNSIGNED NOT NULL,
    utilisateur_id  INT UNSIGNED NOT NULL,
    mois            TINYINT UNSIGNED NOT NULL COMMENT '1, 3 ou 6 mois',
    montant         INT UNSIGNED NOT NULL COMMENT 'FCFA',
    reference_wave  VARCHAR(40)  NOT NULL,
    capture         VARCHAR(100) NULL COMMENT 'Fichier dans stockage/paiements/ (hors web)',
    statut          ENUM('en_verification', 'valide', 'refuse') NOT NULL DEFAULT 'en_verification',
    motif_refus     VARCHAR(255) NULL,
    traite_par      INT UNSIGNED NULL,
    traite_le       DATETIME     NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_paiements_reference (reference_wave),
    KEY idx_paiements_statut (statut, cree_le),
    KEY idx_paiements_boutique (boutique_id, cree_le),
    CONSTRAINT fk_paiements_boutique FOREIGN KEY (boutique_id) REFERENCES boutiques (id) ON DELETE RESTRICT,
    CONSTRAINT fk_paiements_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id) ON DELETE RESTRICT,
    CONSTRAINT fk_paiements_admin FOREIGN KEY (traite_par) REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- abonnements : périodes payées (début, fin). L'essai gratuit reste dans essais_gratuits.
-- Le statut est toujours calculé à partir de ces dates (§18.1).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS abonnements (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    boutique_id     INT UNSIGNED NOT NULL,
    paiement_id     INT UNSIGNED NULL,
    debut_le        DATETIME     NOT NULL,
    fin_le          DATETIME     NOT NULL,
    mois            TINYINT UNSIGNED NOT NULL,
    montant         INT UNSIGNED NOT NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_abonnements_boutique (boutique_id, fin_le),
    CONSTRAINT fk_abonnements_boutique FOREIGN KEY (boutique_id) REFERENCES boutiques (id) ON DELETE CASCADE,
    CONSTRAINT fk_abonnements_paiement FOREIGN KEY (paiement_id) REFERENCES paiements_abonnement (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- rappels_abonnement : chaque rappel (J-2, grâce, expiration, hebdomadaire) n'est envoyé
-- qu'une fois : clé unique par boutique.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rappels_abonnement (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    boutique_id     INT UNSIGNED NOT NULL,
    cle             VARCHAR(40)  NOT NULL COMMENT 'Ex. j-2:2026-10-19, grace:2026-10-20, hebdo:2026-43',
    envoye_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rappels (boutique_id, cle),
    CONSTRAINT fk_rappels_boutique FOREIGN KEY (boutique_id) REFERENCES boutiques (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- envois_sms : file d'attente et historique des SMS (§22). Envoyés par la tâche
-- taches/envoyer_sms.php (nouvel essai en cas d'échec). Aucun SMS non urgent entre 22h et 7h.
-- Le texte est effacé après l'envoi (il peut contenir un code) ; seul le suivi des coûts reste.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS envois_sms (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    telephone       VARCHAR(13)  NOT NULL,
    message         VARCHAR(480) NOT NULL,
    urgent          TINYINT(1)   NOT NULL DEFAULT 1,
    statut          ENUM('en_attente', 'envoye', 'simule', 'echec') NOT NULL DEFAULT 'en_attente',
    essais          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    erreur          VARCHAR(255) NULL,
    programme_le    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    envoye_le       DATETIME     NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_envois_statut (statut, programme_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
