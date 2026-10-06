-- =============================================================================
-- SAMA MAILLOT — migration 004 : panier et commandes (lot 4 — §11, §12, §14, §17)
--
-- À importer UNE fois dans phpMyAdmin (onglet Importer) sur une base créée avant le lot 4.
-- Une nouvelle installation n'en a pas besoin : base_de_donnees.sql contient déjà ces tables.
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- utilisateurs : nombre de refus à la livraison (§12.5). Au 3e refus, le paiement à la
-- livraison est désactivé pour ce client.
-- -----------------------------------------------------------------------------
ALTER TABLE utilisateurs
    ADD COLUMN IF NOT EXISTS refus_livraison TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Refus ou client injoignable à la livraison (§12.5)' AFTER statut;

-- -----------------------------------------------------------------------------
-- paniers : un panier par compte, ou un panier invité repéré par un jeton (cookie
-- « sama_panier », 7 jours). Seule l'empreinte SHA-256 du jeton est stockée.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS paniers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id  INT UNSIGNED NULL,
    jeton_hache     CHAR(64)     NULL COMMENT 'SHA-256 du jeton du cookie (panier invité)',
    expire_le       DATETIME     NULL COMMENT 'Panier invité : supprimé après 7 jours',
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_paniers_utilisateur (utilisateur_id),
    UNIQUE KEY uq_paniers_jeton (jeton_hache),
    CONSTRAINT fk_paniers_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- panier_lignes : un maillot + une taille + un flocage = une ligne (§11).
-- prix_vu : prix au moment de l'ajout, pour signaler un changement de prix.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS panier_lignes (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    panier_id       INT UNSIGNED NOT NULL,
    maillot_id      INT UNSIGNED NOT NULL,
    taille          VARCHAR(12)  NOT NULL,
    flocage_nom     VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'Vide = sans flocage',
    flocage_numero  VARCHAR(2)   NOT NULL DEFAULT '',
    quantite        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    prix_vu         INT UNSIGNED NOT NULL COMMENT 'Prix unitaire affiché à l''ajout (FCFA)',
    ajoute_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_panier_lignes (panier_id, maillot_id, taille, flocage_nom, flocage_numero),
    CONSTRAINT fk_panier_lignes_panier FOREIGN KEY (panier_id) REFERENCES paniers (id) ON DELETE CASCADE,
    CONSTRAINT fk_panier_lignes_maillot FOREIGN KEY (maillot_id) REFERENCES maillots (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- adresses : adresses de livraison enregistrées par les clients (§8.7, §12.1)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS adresses (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id  INT UNSIGNED NOT NULL,
    zone_id         INT UNSIGNED NOT NULL,
    ville           VARCHAR(60)  NOT NULL,
    quartier        VARCHAR(80)  NOT NULL,
    repere          VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'Point de repère (près de la pharmacie…)',
    telephone       VARCHAR(13)  NOT NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    utilisee_le     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_adresses_utilisateur (utilisateur_id, utilisee_le),
    CONSTRAINT fk_adresses_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id) ON DELETE CASCADE,
    CONSTRAINT fk_adresses_zone FOREIGN KEY (zone_id) REFERENCES zones_livraison (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- commandes : UNE commande par boutique (§11, §12). Les informations importantes sont
-- COPIÉES (adresse, frais, zone) : elles ne changent plus si la boutique les modifie.
-- Code de livraison : chiffré (AES-256-GCM, clé dans .env), jamais montré au vendeur (§14.1).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS commandes (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero                  VARCHAR(20)  NOT NULL COMMENT 'CMD-2026-000142',
    client_id               INT UNSIGNED NOT NULL,
    boutique_id             INT UNSIGNED NOT NULL,
    origine                 ENUM('site', 'whatsapp') NOT NULL DEFAULT 'site',
    mode_paiement           ENUM('livraison', 'en_ligne') NOT NULL DEFAULT 'livraison',
    statut                  ENUM('attente_paiement', 'attente_confirmation', 'confirmee', 'expediee',
                                 'livree', 'recue', 'en_litige', 'annulee', 'remboursee')
                                 NOT NULL DEFAULT 'attente_confirmation',
    sous_total              INT UNSIGNED NOT NULL COMMENT 'FCFA, maillots + flocages',
    frais_livraison         INT UNSIGNED NOT NULL DEFAULT 0,
    total                   INT UNSIGNED NOT NULL,
    mode_livraison          ENUM('livraison', 'retrait') NOT NULL DEFAULT 'livraison',
    zone_id                 INT UNSIGNED NULL,
    zone_nom                VARCHAR(60)  NOT NULL DEFAULT '',
    delai_livraison         VARCHAR(40)  NOT NULL DEFAULT '',
    client_nom              VARCHAR(101) NOT NULL,
    telephone_client        VARCHAR(13)  NOT NULL,
    adresse_ville           VARCHAR(60)  NOT NULL DEFAULT '',
    adresse_quartier        VARCHAR(80)  NOT NULL DEFAULT '',
    adresse_repere          VARCHAR(160) NOT NULL DEFAULT '',
    adresse_retrait         VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Copie de l''adresse de retrait de la boutique',
    code_livraison_chiffre  VARCHAR(255) NOT NULL,
    code_essais             TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '5 essais maximum (§14.1)',
    livree_avec_code        TINYINT(1)   NULL,
    motif_annulation        VARCHAR(255) NULL,
    rappel_envoye_le        DATETIME     NULL COMMENT 'Rappel au vendeur : 12 h restantes',
    confirmee_le            DATETIME     NULL,
    expediee_le             DATETIME     NULL,
    livree_le               DATETIME     NULL,
    recue_le                DATETIME     NULL,
    annulee_le              DATETIME     NULL,
    cree_le                 DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_commandes_numero (numero),
    KEY idx_commandes_client (client_id, cree_le),
    KEY idx_commandes_boutique (boutique_id, statut, cree_le),
    KEY idx_commandes_statut (statut, cree_le),
    CONSTRAINT fk_commandes_client FOREIGN KEY (client_id) REFERENCES utilisateurs (id) ON DELETE RESTRICT,
    CONSTRAINT fk_commandes_boutique FOREIGN KEY (boutique_id) REFERENCES boutiques (id) ON DELETE RESTRICT,
    CONSTRAINT fk_commandes_zone FOREIGN KEY (zone_id) REFERENCES zones_livraison (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- commande_lignes : copie du maillot commandé (titre, taille, flocage, prix) (§12.1)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS commande_lignes (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    commande_id         INT UNSIGNED NOT NULL,
    maillot_id          INT UNSIGNED NULL COMMENT 'NULL si le maillot n''existe plus',
    titre               VARCHAR(100) NOT NULL,
    image               VARCHAR(100) NULL COMMENT 'Miniature au moment de la commande',
    taille              VARCHAR(12)  NOT NULL,
    flocage_nom         VARCHAR(12)  NOT NULL DEFAULT '',
    flocage_numero      VARCHAR(2)   NOT NULL DEFAULT '',
    flocage_supplement  INT UNSIGNED NOT NULL DEFAULT 0,
    prix_unitaire       INT UNSIGNED NOT NULL COMMENT 'Prix du maillot + flocage (FCFA)',
    quantite            TINYINT UNSIGNED NOT NULL,
    total_ligne         INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_commande_lignes_commande (commande_id),
    CONSTRAINT fk_commande_lignes_commande FOREIGN KEY (commande_id) REFERENCES commandes (id) ON DELETE CASCADE,
    CONSTRAINT fk_commande_lignes_maillot FOREIGN KEY (maillot_id) REFERENCES maillots (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- commande_historique : chaque changement de statut (ancien, nouveau, auteur, motif) (§12.4)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS commande_historique (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    commande_id     INT UNSIGNED NOT NULL,
    ancien_statut   VARCHAR(30)  NULL,
    nouveau_statut  VARCHAR(30)  NOT NULL,
    auteur_id       INT UNSIGNED NULL COMMENT 'NULL = système',
    auteur_role     ENUM('client', 'vendeur', 'admin', 'systeme') NOT NULL,
    motif           VARCHAR(255) NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_historique_commande (commande_id, cree_le),
    CONSTRAINT fk_historique_commande FOREIGN KEY (commande_id) REFERENCES commandes (id) ON DELETE CASCADE,
    CONSTRAINT fk_historique_auteur FOREIGN KEY (auteur_id) REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paramètre aligné sur le site (photos par maillot portées à 8)
UPDATE parametres SET valeur = '8' WHERE cle = 'photos_par_maillot' AND valeur = '5';
