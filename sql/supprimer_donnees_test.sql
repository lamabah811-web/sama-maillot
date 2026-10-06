-- =============================================================================
-- SAMA MAILLOT — suppression du jeu de données de test
--
-- Supprime UNIQUEMENT ce qu'a créé sql/donnees_test.sql : comptes « @test.sama »,
-- leurs boutiques « (TEST) » et leurs maillots (tailles, photos, favoris liés).
-- Les vrais comptes, boutiques et maillots ne sont pas touchés.
-- =============================================================================

USE sama_maillot;

START TRANSACTION;

-- Commandes passées PAR ou CHEZ un compte de test (lot 4) : lignes et historique suivent (ON DELETE CASCADE)
DELETE c FROM commandes c
JOIN boutiques b ON b.id = c.boutique_id
JOIN utilisateurs u ON u.id = b.utilisateur_id
WHERE u.email LIKE '%@test.sama';
DELETE FROM commandes WHERE client_id IN (SELECT id FROM utilisateurs WHERE email LIKE '%@test.sama');

-- Maillots des boutiques de test (tailles, images et favoris suivent : ON DELETE CASCADE)
DELETE m FROM maillots m
JOIN boutiques b ON b.id = m.boutique_id
JOIN utilisateurs u ON u.id = b.utilisateur_id
WHERE u.email LIKE '%@test.sama';

-- Boutiques de test
DELETE b FROM boutiques b
JOIN utilisateurs u ON u.id = b.utilisateur_id
WHERE u.email LIKE '%@test.sama';

-- Traces liées aux comptes de test
DELETE FROM journal_actions WHERE utilisateur_id IN (SELECT id FROM utilisateurs WHERE email LIKE '%@test.sama');
DELETE FROM essais_gratuits WHERE utilisateur_id IN (SELECT id FROM utilisateurs WHERE email LIKE '%@test.sama');
DELETE FROM tentatives_connexion WHERE identifiant LIKE '%@test.sama'
    OR identifiant IN ('+221770000001', '+221780000002');

-- Comptes de test (codes, jetons et favoris suivent : ON DELETE CASCADE)
DELETE FROM utilisateurs WHERE email LIKE '%@test.sama';

COMMIT;
