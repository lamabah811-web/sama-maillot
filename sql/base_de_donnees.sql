-- =============================================================================
-- SAMA MAILLOT — structure de la base de données
-- MySQL 8 / MariaDB 10.6+, moteur InnoDB, utf8mb4 (§26)
--
-- Import : phpMyAdmin → onglet « Importer » → choisir ce fichier → « Importer ».
-- Le script peut être relancé sans risque : il ne crée que ce qui manque
-- (CREATE TABLE IF NOT EXISTS, INSERT IGNORE) et ne supprime jamais de données.
--
-- Règles (§26.1) :
--   - noms de tables et de colonnes en français, minuscules, sans accents ;
--   - clé primaire « id » entière non signée auto-incrémentée sur chaque table ;
--   - montants en INT UNSIGNED (FCFA) ; dates en DATETIME à l'heure de Dakar (UTC+0) ;
--   - suppression logique (supprime_le) pour les utilisateurs et les boutiques.
--
-- Étape 2 : comptes, codes de vérification, connexion, boutiques, essai gratuit,
-- paramètres et journal d'audit.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS sama_maillot
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sama_maillot;

-- -----------------------------------------------------------------------------
-- roles : les 3 rôles du site (§5). Un compte n'a qu'un seul rôle (§5.1).
-- Le « code » est utilisé dans le programme, le « libellé » est affiché à l'écran.
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- utilisateurs : un compte = un rôle (§5.1), écrit en clair : client, vendeur ou admin.
-- La clé étrangère vers roles.code refuse tout rôle inexistant.
-- Un même numéro de téléphone peut servir à 2 comptes maximum : un client et un
-- vendeur (§8.3) → clé unique (telephone, role). Plusieurs NULL sont autorisés.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS utilisateurs (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role                  VARCHAR(20)  NOT NULL COMMENT 'client, vendeur ou admin (voir table roles)',
    est_invite            TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = compte créé par une commande sans compte',
    prenom                VARCHAR(50)  NOT NULL,
    nom                   VARCHAR(50)  NOT NULL,
    email                 VARCHAR(190) NOT NULL,
    email_verifie         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = adresse e-mail prouvée',
    telephone             VARCHAR(13)  NULL COMMENT 'Format +221XXXXXXXXX',
    telephone_verifie_le  DATETIME     NULL,
    mot_de_passe_hache    VARCHAR(255) NULL COMMENT 'password_hash() ; NULL : compte Google / Facebook ou invité',
    google_id             VARCHAR(64)  NULL,
    statut                ENUM('actif', 'desactive', 'suspendu') NOT NULL DEFAULT 'actif',
    refus_livraison       TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Refus ou client injoignable à la livraison (§12.5)',
    version_session       INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '+1 = toutes les sessions sont fermées',
    cgu_acceptees_le      DATETIME     NOT NULL,
    derniere_connexion_le DATETIME     NULL,
    cree_le               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    supprime_le           DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_utilisateurs_email (email),
    UNIQUE KEY uq_utilisateurs_telephone_role (telephone, role),
    UNIQUE KEY uq_utilisateurs_google_id (google_id),
    KEY idx_utilisateurs_role_statut (role, statut),
    CONSTRAINT fk_utilisateurs_role FOREIGN KEY (role)
        REFERENCES roles (code) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- codes_verification : codes SMS (6 chiffres) et jetons e-mail, toujours hachés (§24).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS codes_verification (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id  INT UNSIGNED NULL,
    type            ENUM('verification_telephone', 'reinitialisation_sms', 'reinitialisation_email',
                         'liaison_sociale_email', 'liaison_sociale_sms', 'email_connexion_sociale') NOT NULL,
    destination     VARCHAR(190) NOT NULL COMMENT 'Téléphone ou e-mail',
    code_hache      VARCHAR(255) NOT NULL,
    essais          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expire_le       DATETIME NOT NULL,
    utilise_le      DATETIME NULL,
    cree_le         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_codes_destination (destination, type, cree_le),
    CONSTRAINT fk_codes_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- tentatives_connexion : limitation des essais (5 échecs en 15 min, §8.4).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tentatives_connexion (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifiant  VARCHAR(190) NOT NULL,
    adresse_ip   VARCHAR(45)  NOT NULL,
    reussie      TINYINT(1)   NOT NULL DEFAULT 0,
    cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tentatives_identifiant (identifiant, cree_le),
    KEY idx_tentatives_ip (adresse_ip, cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- jetons_connexion : option « Rester connecté » (30 jours maximum, §8.4).
-- Le cookie contient « selecteur:validateur » ; seul le hachage du validateur est stocké.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jetons_connexion (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id    INT UNSIGNED NOT NULL,
    selecteur         CHAR(24)     NOT NULL,
    validateur_hache  CHAR(64)     NOT NULL,
    expire_le         DATETIME     NOT NULL,
    cree_le           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jetons_selecteur (selecteur),
    CONSTRAINT fk_jetons_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- boutiques : une boutique par vendeur (§9.2). Nom et adresse (slug) uniques.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS boutiques (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id   INT UNSIGNED NOT NULL,
    nom              VARCHAR(60)  NOT NULL,
    slug             VARCHAR(80)  NOT NULL,
    ville            VARCHAR(60)  NOT NULL,
    description      TEXT         NULL,
    logo             VARCHAR(100) NULL COMMENT 'Fichier dans public/uploads/boutiques/',
    banniere         VARCHAR(100) NULL COMMENT 'Fichier dans public/uploads/boutiques/',
    numero_whatsapp  VARCHAR(13)  NULL,
    numero_wave      VARCHAR(13)  NULL COMMENT 'Obligatoire avant le premier versement',
    retrait_sur_place TINYINT(1)  NOT NULL DEFAULT 0,
    adresse_retrait  VARCHAR(255) NULL,
    statut           ENUM('active', 'grace', 'masquee', 'suspendue') NOT NULL DEFAULT 'active',
    suspension_auto_le DATETIME   NULL COMMENT 'Date de la suspension automatique (signalements)',
    contestation     TEXT         NULL COMMENT 'Contestation envoyée par le vendeur suspendu',
    contestation_le  DATETIME     NULL,
    cree_le          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    supprime_le      DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_boutiques_utilisateur (utilisateur_id),
    UNIQUE KEY uq_boutiques_nom (nom),
    UNIQUE KEY uq_boutiques_slug (slug),
    CONSTRAINT fk_boutiques_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- zones_livraison : liste gérée par l'admin (§9.3). Chaque vendeur active les zones
-- qu'il dessert et fixe son tarif (tarifs_livraison).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS zones_livraison (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom          VARCHAR(60)  NOT NULL,
    description  VARCHAR(255) NOT NULL COMMENT 'Exemples de quartiers ou de villes',
    ordre        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    actif        TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_zones_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO zones_livraison (id, nom, description, ordre) VALUES
    (1, 'Dakar',              'Plateau, Médina, Almadies, Parcelles, Grand Yoff…', 1),
    (2, 'Banlieue',           'Pikine, Guédiawaye, Keur Massar, Rufisque…', 2),
    (3, 'Thiès, Mbour, Saly', 'Thiès, Mbour, Saly et environs', 3),
    (4, 'Autres régions',     'Saint-Louis, Touba, Kaolack, Ziguinchor…', 4);

-- -----------------------------------------------------------------------------
-- tarifs_livraison : tarif (payé par le client) et délai indicatif, par boutique et par zone
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- essais_gratuits : l'essai de 14 jours n'est accordé qu'UNE fois par numéro,
-- même après suppression du compte (§8.3) → clé unique sur le téléphone, et
-- la ligne est conservée si le compte disparaît (ON DELETE SET NULL).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS essais_gratuits (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    telephone       VARCHAR(13)  NOT NULL,
    utilisateur_id  INT UNSIGNED NULL,
    debut_le        DATETIME     NOT NULL,
    fin_le          DATETIME     NOT NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_essais_telephone (telephone),
    CONSTRAINT fk_essais_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- parametres : valeurs modifiables par l'administrateur (Annexe A).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS parametres (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cle          VARCHAR(60)  NOT NULL,
    valeur       VARCHAR(255) NOT NULL,
    description  VARCHAR(255) NOT NULL,
    modifie_le   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_parametres_cle (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- journal_actions : journal d'audit des actions sensibles (§31).
-- Jamais de mot de passe, de code ni de donnée de paiement complète.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS journal_actions (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id   INT UNSIGNED NULL COMMENT 'Auteur de l''action (NULL = système)',
    action           VARCHAR(60)  NOT NULL,
    objet_type       VARCHAR(40)  NULL,
    objet_id         INT UNSIGNED NULL,
    ancienne_valeur  TEXT         NULL,
    nouvelle_valeur  TEXT         NULL,
    adresse_ip       VARCHAR(45)  NULL,
    cree_le          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_journal_action (action, cree_le),
    KEY idx_journal_objet (objet_type, objet_id),
    CONSTRAINT fk_journal_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- CATALOGUE (étape 3, lot 2 — §9.4, §10, §26)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- categories : Équipes nationales, Clubs, Rétro (§7.1). Le slug sert dans l'adresse
-- /catalogue/<slug>.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug         VARCHAR(40)  NOT NULL,
    nom          VARCHAR(60)  NOT NULL,
    description  VARCHAR(255) NOT NULL,
    ordre        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    actif        TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- equipes : sélections et clubs (liste gérée par l'admin, §9.4).
-- Un maillot rétro garde son équipe : la catégorie est portée par le maillot.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS equipes (
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug    VARCHAR(80)  NOT NULL,
    nom     VARCHAR(80)  NOT NULL,
    pays    VARCHAR(60)  NOT NULL,
    type    ENUM('selection', 'club') NOT NULL,
    actif   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_equipes_slug (slug),
    KEY idx_equipes_type_nom (type, nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- maillots : annonces des vendeurs (§9.4). Prix en FCFA entiers.
-- texte_recherche = titre + équipe + saison, indexé en FULLTEXT pour la recherche
-- insensible aux accents et à la casse (collation utf8mb4_unicode_ci, §10.2).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS maillots (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    boutique_id         INT UNSIGNED NOT NULL,
    categorie_id        INT UNSIGNED NOT NULL,
    equipe_id           INT UNSIGNED NOT NULL,
    titre               VARCHAR(100) NOT NULL,
    slug                VARCHAR(120) NOT NULL,
    saison              VARCHAR(9)   NOT NULL COMMENT 'AAAA/AAAA ou AAAA (rétro)',
    type                ENUM('domicile', 'exterieur', 'third', 'gardien', 'entrainement', 'autre') NOT NULL,
    authenticite        ENUM('officiel', 'replique', 'version_fan') NOT NULL,
    etat                ENUM('neuf_etiquette', 'neuf_sans_etiquette', 'occasion_tres_bon', 'occasion_bon') NOT NULL,
    prix                INT UNSIGNED NOT NULL COMMENT 'FCFA, de 1 000 à 500 000',
    flocage_propose     TINYINT(1)   NOT NULL DEFAULT 0,
    flocage_supplement  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'FCFA',
    description         TEXT         NULL,
    statut              ENUM('brouillon', 'publie', 'masque_vendeur', 'masque_admin', 'supprime') NOT NULL DEFAULT 'brouillon',
    texte_recherche     VARCHAR(255) NOT NULL DEFAULT '',
    publie_le           DATETIME     NULL,
    cree_le             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    supprime_le         DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_maillots_statut_publie (statut, publie_le),
    KEY idx_maillots_categorie (categorie_id),
    KEY idx_maillots_equipe (equipe_id),
    KEY idx_maillots_prix (prix),
    KEY idx_maillots_boutique (boutique_id),
    FULLTEXT KEY ft_maillots_recherche (texte_recherche),
    CONSTRAINT fk_maillots_boutique FOREIGN KEY (boutique_id) REFERENCES boutiques (id) ON DELETE RESTRICT,
    CONSTRAINT fk_maillots_categorie FOREIGN KEY (categorie_id) REFERENCES categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_maillots_equipe FOREIGN KEY (equipe_id) REFERENCES equipes (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- maillot_tailles : une quantité en stock par taille, de XS à XXXL (§9.4, sans tailles enfants)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS maillot_tailles (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    maillot_id  INT UNSIGNED NOT NULL,
    taille      VARCHAR(12)  NOT NULL COMMENT 'XS, S, M, L, XL, XXL ou XXXL',
    stock       INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_maillot_tailles (maillot_id, taille),
    KEY idx_maillot_tailles_taille (taille, stock),
    CONSTRAINT fk_tailles_maillot FOREIGN KEY (maillot_id) REFERENCES maillots (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- maillot_images : 1 à 8 photos, la première (ordre 0) est la photo principale (§29.4)
-- Fichiers dans public/uploads/maillots/ (envoi par le vendeur : lot 3).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS maillot_images (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    maillot_id  INT UNSIGNED NOT NULL,
    fichier     VARCHAR(100) NOT NULL COMMENT 'Grande version 1200 px, WebP',
    miniature   VARCHAR(100) NOT NULL COMMENT 'Miniature 400 px, WebP',
    ordre       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    cree_le     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_images_maillot (maillot_id, ordre),
    CONSTRAINT fk_images_maillot FOREIGN KEY (maillot_id) REFERENCES maillots (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- favoris : maillots mis de côté par les utilisateurs connectés
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS favoris (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id  INT UNSIGNED NOT NULL,
    maillot_id      INT UNSIGNED NOT NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_favoris (utilisateur_id, maillot_id),
    CONSTRAINT fk_favoris_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id) ON DELETE CASCADE,
    CONSTRAINT fk_favoris_maillot FOREIGN KEY (maillot_id) REFERENCES maillots (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Données de référence : catégories et équipes (modifiables par l'admin au lot 5)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO categories (id, slug, nom, description, ordre) VALUES
    (1, 'equipes-nationales', 'Équipes nationales', 'Sénégal, Maroc, France, Brésil… les maillots des sélections.', 1),
    (2, 'clubs',              'Clubs',              'Les clubs d''Europe, d''Afrique et d''ailleurs.', 2),
    (3, 'retro',              'Rétro',              'Les maillots qui ont marqué l''histoire du football.', 3);

INSERT IGNORE INTO equipes (slug, nom, pays, type) VALUES
    -- Sélections africaines
    ('senegal', 'Sénégal', 'Sénégal', 'selection'),
    ('cote-d-ivoire', 'Côte d''Ivoire', 'Côte d''Ivoire', 'selection'),
    ('maroc', 'Maroc', 'Maroc', 'selection'),
    ('algerie', 'Algérie', 'Algérie', 'selection'),
    ('tunisie', 'Tunisie', 'Tunisie', 'selection'),
    ('egypte', 'Égypte', 'Égypte', 'selection'),
    ('nigeria', 'Nigeria', 'Nigeria', 'selection'),
    ('cameroun', 'Cameroun', 'Cameroun', 'selection'),
    ('ghana', 'Ghana', 'Ghana', 'selection'),
    ('mali', 'Mali', 'Mali', 'selection'),
    ('guinee', 'Guinée', 'Guinée', 'selection'),
    ('burkina-faso', 'Burkina Faso', 'Burkina Faso', 'selection'),
    ('gambie', 'Gambie', 'Gambie', 'selection'),
    ('mauritanie', 'Mauritanie', 'Mauritanie', 'selection'),
    ('cap-vert', 'Cap-Vert', 'Cap-Vert', 'selection'),
    ('rd-congo', 'RD Congo', 'RD Congo', 'selection'),
    ('afrique-du-sud', 'Afrique du Sud', 'Afrique du Sud', 'selection'),
    -- Autres sélections
    ('france', 'France', 'France', 'selection'),
    ('espagne', 'Espagne', 'Espagne', 'selection'),
    ('portugal', 'Portugal', 'Portugal', 'selection'),
    ('allemagne', 'Allemagne', 'Allemagne', 'selection'),
    ('italie', 'Italie', 'Italie', 'selection'),
    ('angleterre', 'Angleterre', 'Angleterre', 'selection'),
    ('pays-bas', 'Pays-Bas', 'Pays-Bas', 'selection'),
    ('belgique', 'Belgique', 'Belgique', 'selection'),
    ('croatie', 'Croatie', 'Croatie', 'selection'),
    ('bresil', 'Brésil', 'Brésil', 'selection'),
    ('argentine', 'Argentine', 'Argentine', 'selection'),
    ('uruguay', 'Uruguay', 'Uruguay', 'selection'),
    ('mexique', 'Mexique', 'Mexique', 'selection'),
    ('etats-unis', 'États-Unis', 'États-Unis', 'selection'),
    ('japon', 'Japon', 'Japon', 'selection'),
    -- Clubs du Sénégal et d'Afrique
    ('jaraaf', 'ASC Jaraaf', 'Sénégal', 'club'),
    ('casa-sports', 'Casa Sports', 'Sénégal', 'club'),
    ('generation-foot', 'Génération Foot', 'Sénégal', 'club'),
    ('teungueth-fc', 'Teungueth FC', 'Sénégal', 'club'),
    ('as-pikine', 'AS Pikine', 'Sénégal', 'club'),
    ('al-ahly', 'Al Ahly', 'Égypte', 'club'),
    ('wydad', 'Wydad AC', 'Maroc', 'club'),
    ('raja', 'Raja CA', 'Maroc', 'club'),
    ('esperance-tunis', 'Espérance de Tunis', 'Tunisie', 'club'),
    ('mamelodi-sundowns', 'Mamelodi Sundowns', 'Afrique du Sud', 'club'),
    -- Clubs d'Europe et d'ailleurs
    ('psg', 'Paris Saint-Germain', 'France', 'club'),
    ('olympique-de-marseille', 'Olympique de Marseille', 'France', 'club'),
    ('olympique-lyonnais', 'Olympique lyonnais', 'France', 'club'),
    ('as-monaco', 'AS Monaco', 'France', 'club'),
    ('losc', 'LOSC Lille', 'France', 'club'),
    ('real-madrid', 'Real Madrid', 'Espagne', 'club'),
    ('fc-barcelone', 'FC Barcelone', 'Espagne', 'club'),
    ('atletico-madrid', 'Atlético de Madrid', 'Espagne', 'club'),
    ('manchester-united', 'Manchester United', 'Angleterre', 'club'),
    ('manchester-city', 'Manchester City', 'Angleterre', 'club'),
    ('liverpool', 'Liverpool', 'Angleterre', 'club'),
    ('chelsea', 'Chelsea', 'Angleterre', 'club'),
    ('arsenal', 'Arsenal', 'Angleterre', 'club'),
    ('tottenham', 'Tottenham', 'Angleterre', 'club'),
    ('bayern-munich', 'Bayern Munich', 'Allemagne', 'club'),
    ('borussia-dortmund', 'Borussia Dortmund', 'Allemagne', 'club'),
    ('juventus', 'Juventus', 'Italie', 'club'),
    ('ac-milan', 'AC Milan', 'Italie', 'club'),
    ('inter-milan', 'Inter Milan', 'Italie', 'club'),
    ('naples', 'SSC Naples', 'Italie', 'club'),
    ('as-rome', 'AS Rome', 'Italie', 'club'),
    ('al-nassr', 'Al-Nassr', 'Arabie saoudite', 'club'),
    ('al-hilal', 'Al-Hilal', 'Arabie saoudite', 'club'),
    ('inter-miami', 'Inter Miami', 'États-Unis', 'club');

-- =============================================================================
-- PANIER ET COMMANDES (lot 4 — §11, §12, §14, §17)
-- =============================================================================

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
-- transactions_paiement : un paiement du client à SAMA MAILLOT via l'agrégateur.
-- Un paiement peut couvrir plusieurs commandes (une par boutique) du même panier.
-- La redirection du navigateur n'est jamais une preuve : le statut vient de la notification
-- signée de l'agrégateur, contrôlée par un appel à son API (§13.4).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions_paiement (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference               VARCHAR(40)  NOT NULL COMMENT 'Référence unique envoyée à l''agrégateur',
    fournisseur             VARCHAR(20)  NOT NULL COMMENT 'simulateur, paytech, paydunya…',
    client_id               INT UNSIGNED NOT NULL,
    montant                 INT UNSIGNED NOT NULL COMMENT 'FCFA attendus',
    montant_recu            INT UNSIGNED NULL     COMMENT 'FCFA confirmés par l''agrégateur',
    statut                  ENUM('initie', 'reussi', 'echoue', 'annule', 'expire', 'inconnu', 'montant_incorrect')
                            NOT NULL DEFAULT 'initie',
    moyen                   VARCHAR(30)  NULL     COMMENT 'wave, orange_money, free_money, carte',
    reference_fournisseur   VARCHAR(100) NULL,
    url_paiement            VARCHAR(500) NULL,
    simulateur_etat         VARCHAR(20)  NULL     COMMENT 'Simulateur de test uniquement : état côté « agrégateur »',
    expire_le               DATETIME     NOT NULL,
    traitee_le              DATETIME     NULL     COMMENT 'Statut final appliqué aux commandes (une seule fois)',
    cree_le                 DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transactions_reference (reference),
    KEY idx_transactions_statut (statut, expire_le),
    KEY idx_transactions_client (client_id),
    CONSTRAINT fk_transactions_client FOREIGN KEY (client_id) REFERENCES utilisateurs (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Événements de paiement (notifications reçues, vérifications) : journal technique sans donnée sensible
CREATE TABLE IF NOT EXISTS evenements_paiement (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id  INT UNSIGNED NULL,
    type            VARCHAR(40)  NOT NULL COMMENT 'initiation, notification, verification, signature_invalide…',
    detail          VARCHAR(255) NOT NULL DEFAULT '',
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_evenements_transaction (transaction_id, cree_le),
    CONSTRAINT fk_evenements_transaction FOREIGN KEY (transaction_id) REFERENCES transactions_paiement (id) ON DELETE CASCADE
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
    transaction_id          INT UNSIGNED NULL,
    statut                  ENUM('attente_paiement', 'attente_confirmation', 'confirmee', 'expediee',
                                 'livree', 'recue', 'en_litige', 'annulee', 'remboursee')
                                 NOT NULL DEFAULT 'attente_confirmation',
    sous_total              INT UNSIGNED NOT NULL COMMENT 'FCFA, maillots + flocages',
    frais_livraison         INT UNSIGNED NOT NULL DEFAULT 0,
    frais_a_convenir        TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = pas de tarif pour cette zone : frais fixés avec le vendeur',
    total                   INT UNSIGNED NOT NULL,
    frais_transaction       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'FCFA retenus (frais de l''agrégateur)',
    montant_vendeur         INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'FCFA reversés au vendeur (paiement en ligne)',
    mode_livraison         ENUM('livraison', 'retrait') NOT NULL DEFAULT 'livraison',
    zone_id                 INT UNSIGNED NULL,
    zone_nom                VARCHAR(60)  NOT NULL DEFAULT '',
    delai_livraison         VARCHAR(40)  NOT NULL DEFAULT '',
    client_nom              VARCHAR(101) NOT NULL,
    telephone_client        VARCHAR(13)  NOT NULL,
    email_client            VARCHAR(190) NULL COMMENT 'E-mail donné à la commande (facultatif, commande sans compte)',
    jeton_suivi_chiffre     VARCHAR(255) NULL COMMENT 'Lien de suivi d''une commande sans compte (chiffré)',
    adresse_ville           VARCHAR(60)  NOT NULL DEFAULT '',
    adresse_quartier        VARCHAR(80)  NOT NULL DEFAULT '',
    adresse_repere          VARCHAR(160) NOT NULL DEFAULT '',
    adresse_retrait         VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Copie de l''adresse de retrait de la boutique',
    code_livraison_chiffre  VARCHAR(255) NULL COMMENT 'Paiement en ligne uniquement (§14.1) ; NULL en paiement à la livraison',
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
    payee_le                DATETIME     NULL,
    modifie_le              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_commandes_numero (numero),
    KEY idx_commandes_client (client_id, cree_le),
    KEY idx_commandes_boutique (boutique_id, statut, cree_le),
    KEY idx_commandes_statut (statut, cree_le),
    KEY idx_commandes_transaction (transaction_id),
    CONSTRAINT fk_commandes_client FOREIGN KEY (client_id) REFERENCES utilisateurs (id) ON DELETE RESTRICT,
    CONSTRAINT fk_commandes_boutique FOREIGN KEY (boutique_id) REFERENCES boutiques (id) ON DELETE RESTRICT,
    CONSTRAINT fk_commandes_zone FOREIGN KEY (zone_id) REFERENCES zones_livraison (id) ON DELETE SET NULL,
    CONSTRAINT fk_commandes_transaction FOREIGN KEY (transaction_id) REFERENCES transactions_paiement (id) ON DELETE SET NULL
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


-- =============================================================================
-- CONTACT (§7.3)
-- =============================================================================

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

-- =============================================================================
-- NOTIFICATIONS DANS LE SITE (§22.1)
-- =============================================================================

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

-- =============================================================================
-- SIGNALEMENTS (§21) — seul un client ayant commandé chez ce vendeur peut signaler
-- =============================================================================

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

-- =============================================================================
-- ABONNEMENTS ET SMS (lot 6 — §18, §22)
-- =============================================================================

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

-- =============================================================================
-- AVIS ET LITIGES (lot 7 — §16, §20)
-- =============================================================================

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

-- -----------------------------------------------------------------------------
-- versements : virements Wave de la plateforme au vendeur (mardi et vendredi, §15.2).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS versements (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    boutique_id     INT UNSIGNED NOT NULL,
    montant         INT UNSIGNED NOT NULL,
    numero_wave     VARCHAR(13)  NOT NULL COMMENT 'Copie du numéro Wave au moment du versement',
    reference_wave  VARCHAR(60)  NOT NULL,
    admin_id        INT UNSIGNED NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_versements_reference (reference_wave),
    KEY idx_versements_boutique (boutique_id, cree_le),
    CONSTRAINT fk_versements_boutique FOREIGN KEY (boutique_id) REFERENCES boutiques (id),
    CONSTRAINT fk_versements_admin FOREIGN KEY (admin_id) REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- mouvements_portefeuille : REGISTRE NON MODIFIABLE (§15.2). On n'y fait que des INSERT :
-- le solde est toujours calculé à partir de ce registre (SUM(montant) par compartiment).
-- Un déblocage = deux lignes (−x en attente, +x disponible) ; un remboursement = une ligne négative.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mouvements_portefeuille (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    boutique_id     INT UNSIGNED NOT NULL,
    commande_id     INT UNSIGNED NULL,
    versement_id    INT UNSIGNED NULL,
    operation       ENUM('credit', 'deblocage', 'blocage', 'liberation', 'remboursement', 'versement') NOT NULL,
    compartiment    ENUM('en_attente', 'disponible', 'bloque', 'verse') NOT NULL,
    montant         INT          NOT NULL COMMENT 'FCFA, positif ou négatif',
    libelle         VARCHAR(160) NOT NULL DEFAULT '',
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mouvements_boutique (boutique_id, compartiment),
    KEY idx_mouvements_commande (commande_id),
    CONSTRAINT fk_mouvements_boutique FOREIGN KEY (boutique_id) REFERENCES boutiques (id),
    CONSTRAINT fk_mouvements_commande FOREIGN KEY (commande_id) REFERENCES commandes (id),
    CONSTRAINT fk_mouvements_versement FOREIGN KEY (versement_id) REFERENCES versements (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- remboursements : commande payée en ligne annulée ou réclamation fondée. V1 : l'administration
-- rembourse le client (Wave / agrégateur) puis saisit la référence.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS remboursements (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    commande_id     INT UNSIGNED NOT NULL,
    client_id       INT UNSIGNED NOT NULL,
    montant         INT UNSIGNED NOT NULL,
    motif           VARCHAR(255) NOT NULL,
    statut          ENUM('a_rembourser', 'rembourse') NOT NULL DEFAULT 'a_rembourser',
    reference       VARCHAR(60)  NULL,
    admin_id        INT UNSIGNED NULL,
    rembourse_le    DATETIME     NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_remboursements_commande (commande_id),
    KEY idx_remboursements_statut (statut, cree_le),
    CONSTRAINT fk_remboursements_commande FOREIGN KEY (commande_id) REFERENCES commandes (id),
    CONSTRAINT fk_remboursements_client FOREIGN KEY (client_id) REFERENCES utilisateurs (id),
    CONSTRAINT fk_remboursements_admin FOREIGN KEY (admin_id) REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Connexion avec Google et Facebook (migration 015)
-- Un compte est retrouvé par (fournisseur + identifiant), jamais par son e-mail seul.
-- Aucun jeton d'accès Google ou Facebook n'est conservé.
-- =============================================================================
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

CREATE TABLE IF NOT EXISTS tentatives_connexion_sociale (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fournisseur  ENUM('google', 'facebook') NOT NULL,
    adresse_ip   VARCHAR(45)  NOT NULL,
    cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tentatives_sociales_ip (adresse_ip, cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Données de référence : paramètres par défaut (Annexe A du cahier des charges)
-- INSERT IGNORE : une valeur déjà modifiée par l'administrateur n'est pas écrasée.
-- =============================================================================
INSERT IGNORE INTO parametres (cle, valeur, description) VALUES
    ('essai_gratuit_jours',              '14',    'Durée de l''essai gratuit (jours)'),
    ('abonnement_prix_fcfa',             '3000',  'Prix de l''abonnement mensuel (FCFA)'),
    ('abonnement_rappel_jours',          '2',     'Rappel avant fin d''abonnement (jours)'),
    ('abonnement_grace_jours',           '3',     'Période de grâce avant masquage (jours)'),
    ('deblocage_avec_code_heures',       '24',    'Délai de déblocage des fonds avec code de livraison (heures)'),
    ('deblocage_sans_code_heures',       '48',    'Délai de déblocage des fonds sans code (heures)'),
    ('confirmation_vendeur_heures',      '24',    'Délai de confirmation d''une commande par le vendeur (heures)'),
    ('paiement_en_ligne_minutes',        '30',    'Délai de paiement en ligne (minutes)'),
    ('litige_reponse_vendeur_heures',    '48',    'Délai de réponse du vendeur à un litige (heures)'),
    ('frais_transaction_pourcentage',    '0',     'Frais de transaction déduits (%) — taux réel de l''agrégateur, à renseigner'),
    ('commission_pourcentage',           '0',     'Commission sur les ventes (%)'),
    ('versement_jours',                  'mardi,vendredi', 'Jours de versement aux vendeurs'),
    ('versement_minimum_fcfa',           '1000',  'Montant minimum de versement (FCFA)'),
    ('signalements_avant_suspension',    '3',     'Signalements de clients différents avant suspension'),
    ('signalements_periode_jours',       '90',    'Période de comptage des signalements (jours)'),
    ('avis_delai_jours',                 '30',    'Délai pour laisser un avis (jours)'),
    ('commandes_livraison_simultanees',  '3',     'Commandes à la livraison simultanées par client'),
    ('refus_livraison_avant_blocage',    '3',     'Refus de livraison avant blocage du paiement à la livraison'),
    ('photos_par_maillot',               '8',     'Nombre maximum de photos par maillot'),
    ('photo_taille_max_mo',              '5',     'Taille maximale d''une photo (Mo)'),
    ('mise_en_avant_7_jours_fcfa',       '1000',  'Tarif de mise en avant 7 jours (FCFA)'),
    ('mise_en_avant_30_jours_fcfa',      '3000',  'Tarif de mise en avant 30 jours (FCFA)'),
    ('numero_wave_plateforme',           '',      'Numéro Wave de la plateforme — à renseigner'),
    ('lien_paiement_wave',               '',      'Lien de paiement Wave Business (pay.wave.com) — montant ajouté automatiquement'),
    ('numero_whatsapp_support',          '',      'Numéro WhatsApp du support — à renseigner'),
    ('email_support',                    '',      'Adresse e-mail du support (copie des messages de contact) — à renseigner'),
    ('entreprise_raison_sociale',        '',      'Raison sociale de l''éditeur (ex. SAMA MAILLOT SUARL)'),
    ('entreprise_forme_juridique',       '',      'Forme juridique (entreprise individuelle, SUARL…) et capital'),
    ('entreprise_adresse',               '',      'Adresse du siège'),
    ('entreprise_ninea',                 '',      'NINEA'),
    ('entreprise_rccm',                  '',      'Numéro RCCM'),
    ('entreprise_responsable',           '',      'Directeur de la publication'),
    ('cdp_declaration',                  '',      'Numéro de récépissé de déclaration à la CDP');
