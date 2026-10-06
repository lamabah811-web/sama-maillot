-- =============================================================================
-- SAMA MAILLOT — migration 003 : espace vendeur (lot 3)
--
-- Ajoute : logo, bannière et retrait sur place aux boutiques (§9.2, §9.3),
--          les zones de livraison (liste gérée par l'admin) et les tarifs par boutique.
--
-- À exécuter UNE SEULE FOIS sur une base créée avant cette migration.
-- Une base neuve n'en a pas besoin : base_de_donnees.sql contient déjà ces éléments.
-- =============================================================================

USE sama_maillot;

ALTER TABLE boutiques
    ADD COLUMN logo VARCHAR(100) NULL COMMENT 'Fichier dans public/uploads/boutiques/' AFTER description,
    ADD COLUMN banniere VARCHAR(100) NULL COMMENT 'Fichier dans public/uploads/boutiques/' AFTER logo,
    ADD COLUMN retrait_sur_place TINYINT(1) NOT NULL DEFAULT 0 AFTER numero_wave,
    ADD COLUMN adresse_retrait VARCHAR(255) NULL AFTER retrait_sur_place;

CREATE TABLE IF NOT EXISTS zones_livraison (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom          VARCHAR(60)  NOT NULL,
    description  VARCHAR(255) NOT NULL COMMENT 'Exemples de quartiers ou de villes',
    ordre        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    actif        TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_zones_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tarifs_livraison (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    boutique_id  INT UNSIGNED NOT NULL,
    zone_id      INT UNSIGNED NOT NULL,
    tarif        INT UNSIGNED NOT NULL COMMENT 'FCFA, payé par le client',
    delai        VARCHAR(40)  NOT NULL COMMENT 'Délai indicatif, ex. 24-48h',
    PRIMARY KEY (id),
    UNIQUE KEY uq_tarifs_boutique_zone (boutique_id, zone_id),
    CONSTRAINT fk_tarifs_boutique FOREIGN KEY (boutique_id) REFERENCES boutiques (id) ON DELETE CASCADE,
    CONSTRAINT fk_tarifs_zone FOREIGN KEY (zone_id) REFERENCES zones_livraison (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO zones_livraison (id, nom, description, ordre) VALUES
    (1, 'Dakar',           'Plateau, Médina, Almadies, Parcelles, Grand Yoff…', 1),
    (2, 'Banlieue',        'Pikine, Guédiawaye, Keur Massar, Rufisque…', 2),
    (3, 'Thiès, Mbour, Saly', 'Thiès, Mbour, Saly et environs', 3),
    (4, 'Autres régions',  'Saint-Louis, Touba, Kaolack, Ziguinchor…', 4);
