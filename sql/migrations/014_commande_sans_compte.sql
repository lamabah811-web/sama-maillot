-- =============================================================================
-- SAMA MAILLOT — migration 014 : commander sans créer de compte
-- À importer UNE fois sur une base créée avant cette étape.
--
-- Un visiteur peut commander en donnant son nom complet, son téléphone et (facultatif) son
-- e-mail. Un « compte invité » est créé automatiquement (sans mot de passe : impossible de s'y
-- connecter) pour rattacher la commande ; le visiteur suit sa commande par un lien secret.
-- Il peut plus tard en faire un vrai compte avec « Mot de passe oublié » (e-mail vérifié).
-- =============================================================================

SET NAMES utf8mb4;

ALTER TABLE utilisateurs
    ADD COLUMN est_invite TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = compte créé par une commande sans compte' AFTER role;

ALTER TABLE commandes
    ADD COLUMN email_client       VARCHAR(190) NULL COMMENT 'E-mail donné à la commande (facultatif, commande sans compte)' AFTER telephone_client,
    ADD COLUMN jeton_suivi_chiffre VARCHAR(255) NULL COMMENT 'Lien de suivi d''une commande sans compte (chiffré)' AFTER email_client;
