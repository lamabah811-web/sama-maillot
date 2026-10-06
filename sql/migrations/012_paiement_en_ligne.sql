-- =============================================================================
-- SAMA MAILLOT — migration 012 : paiement en ligne, séquestre, portefeuille vendeur,
-- versements et remboursements (§13, §15).
-- À importer UNE fois sur une base créée avant cette étape.
-- =============================================================================

SET NAMES utf8mb4;

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

-- Commandes payées en ligne : lien vers le paiement, frais de transaction et part du vendeur
ALTER TABLE commandes
    ADD COLUMN transaction_id    INT UNSIGNED NULL AFTER mode_paiement,
    ADD COLUMN frais_transaction INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'FCFA retenus (frais de l''agrégateur)' AFTER total,
    ADD COLUMN montant_vendeur   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'FCFA reversés au vendeur (paiement en ligne)' AFTER frais_transaction,
    ADD COLUMN payee_le          DATETIME NULL AFTER cree_le,
    ADD KEY idx_commandes_transaction (transaction_id),
    ADD CONSTRAINT fk_commandes_transaction FOREIGN KEY (transaction_id) REFERENCES transactions_paiement (id) ON DELETE SET NULL;

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

-- Lien de paiement Wave Business de la plateforme (abonnements) : le montant est ajouté automatiquement
INSERT IGNORE INTO parametres (cle, valeur, description) VALUES
    ('lien_paiement_wave', '', 'Lien de paiement Wave Business (pay.wave.com) — montant ajouté automatiquement');
