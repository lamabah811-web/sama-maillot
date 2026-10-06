-- =============================================================================
-- SAMA MAILLOT — JEU DE DONNÉES DE TEST (développement et recette UNIQUEMENT)
--
-- ⚠ Ne JAMAIS importer en production. Toutes les données sont clairement identifiées :
--   - comptes en « @test.sama », mot de passe : Test2026
--   - boutiques dont le nom se termine par « (TEST) »
--   - maillots dont la description commence par « [Donnée de test] »
--
-- Pour tout supprimer : sql/supprimer_donnees_test.sql
-- Prérequis : sql/base_de_donnees.sql déjà importé.
-- Les photos ne sont pas fournies (envoi par les vendeurs au lot 3) : les cartes
-- affichent « Photo à venir ».
-- =============================================================================

USE sama_maillot;

-- -----------------------------------------------------------------------------
-- 2 vendeurs de test (téléphone vérifié) et leurs boutiques
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO utilisateurs (role, prenom, nom, email, telephone, telephone_verifie_le, mot_de_passe_hache, cgu_acceptees_le)
VALUES
    ('vendeur', 'Moussa', 'Test', 'vendeur1@test.sama', '+221770000001', NOW(),
     '$2y$10$XcedcKNOOU2QzNl4AVEyn.7e5aqg1Ae9.rVa65eUSrwjFgAHZELKS', NOW()),
    ('vendeur', 'Awa', 'Test', 'vendeur2@test.sama', '+221780000002', NOW(),
     '$2y$10$XcedcKNOOU2QzNl4AVEyn.7e5aqg1Ae9.rVa65eUSrwjFgAHZELKS', NOW()),
    ('client', 'Client', 'Test', 'client@test.sama', NULL, NULL,
     '$2y$10$XcedcKNOOU2QzNl4AVEyn.7e5aqg1Ae9.rVa65eUSrwjFgAHZELKS', NOW());

INSERT IGNORE INTO boutiques (utilisateur_id, nom, slug, ville, description, numero_whatsapp, statut)
VALUES
    ((SELECT id FROM utilisateurs WHERE email = 'vendeur1@test.sama'), 'Dakar Foot Store (TEST)', 'dakar-foot-store-test', 'Dakar',
     '[Donnée de test] Boutique de démonstration : maillots des sélections africaines et des grands clubs européens.', '+221770000001', 'active'),
    ((SELECT id FROM utilisateurs WHERE email = 'vendeur2@test.sama'), 'Thiès Maillots Rétro (TEST)', 'thies-maillots-retro-test', 'Thiès',
     '[Donnée de test] Boutique de démonstration spécialisée dans les maillots rétro et vintage.', '+221780000002', 'active');

-- Abonnements de test : vendeur 1 en essai (14 jours), vendeur 2 en période de grâce
-- (essai terminé depuis 1 jour) pour tester le blocage de l'ajout de maillots (§9.5).
INSERT IGNORE INTO essais_gratuits (telephone, utilisateur_id, debut_le, fin_le)
VALUES
    ('+221770000001', (SELECT id FROM utilisateurs WHERE email = 'vendeur1@test.sama'), NOW(), NOW() + INTERVAL 14 DAY),
    ('+221780000002', (SELECT id FROM utilisateurs WHERE email = 'vendeur2@test.sama'), NOW() - INTERVAL 15 DAY, NOW() - INTERVAL 1 DAY);

-- -----------------------------------------------------------------------------
-- Maillots de test (publiés). Raccourcis : B1 / B2 = boutiques, C = catégorie, E = équipe.
-- -----------------------------------------------------------------------------
SET @b1 = (SELECT id FROM boutiques WHERE slug = 'dakar-foot-store-test');
SET @b2 = (SELECT id FROM boutiques WHERE slug = 'thies-maillots-retro-test');
SET @sel = (SELECT id FROM categories WHERE slug = 'equipes-nationales');
SET @club = (SELECT id FROM categories WHERE slug = 'clubs');
SET @retro = (SELECT id FROM categories WHERE slug = 'retro');

INSERT INTO maillots (boutique_id, categorie_id, equipe_id, titre, slug, saison, type, authenticite, etat, prix,
                      flocage_propose, flocage_supplement, description, statut, publie_le)
SELECT * FROM (
    SELECT @b1 AS b, @sel AS c, (SELECT id FROM equipes WHERE slug = 'senegal') AS e,
           'Maillot Sénégal domicile 2025/2026' AS t, 'maillot-senegal-domicile-2025-2026' AS s, '2025/2026' AS sa,
           'domicile' AS ty, 'officiel' AS au, 'neuf_etiquette' AS et, 35000 AS p, 1 AS fl, 3000 AS fs,
           '[Donnée de test] Maillot domicile blanc des Lions de la Teranga. Coupe ajustée, tissu respirant.' AS d,
           'publie' AS st, NOW() - INTERVAL 1 HOUR AS pu
    UNION ALL SELECT @b1, @sel, (SELECT id FROM equipes WHERE slug = 'senegal'), 'Maillot Sénégal extérieur 2025/2026',
           'maillot-senegal-exterieur-2025-2026', '2025/2026', 'exterieur', 'replique', 'neuf_sans_etiquette', 12000, 1, 2000,
           '[Donnée de test] Réplique du maillot extérieur vert. Vendu comme réplique.', 'publie', NOW() - INTERVAL 2 HOUR
    UNION ALL SELECT @b1, @sel, (SELECT id FROM equipes WHERE slug = 'maroc'), 'Maillot Maroc domicile 2024/2025',
           'maillot-maroc-domicile-2024-2025', '2024/2025', 'domicile', 'version_fan', 'neuf_etiquette', 25000, 0, 0,
           '[Donnée de test] Version fan du maillot rouge des Lions de l''Atlas.', 'publie', NOW() - INTERVAL 3 HOUR
    UNION ALL SELECT @b1, @sel, (SELECT id FROM equipes WHERE slug = 'cote-d-ivoire'), 'Maillot Côte d''Ivoire domicile 2024',
           'maillot-cote-d-ivoire-domicile-2024', '2024', 'domicile', 'officiel', 'neuf_sans_etiquette', 30000, 1, 3000,
           '[Donnée de test] Maillot orange des Éléphants.', 'publie', NOW() - INTERVAL 4 HOUR
    UNION ALL SELECT @b1, @sel, (SELECT id FROM equipes WHERE slug = 'bresil'), 'Maillot Brésil domicile 2024/2025',
           'maillot-bresil-domicile-2024-2025', '2024/2025', 'domicile', 'replique', 'neuf_etiquette', 10000, 1, 2000,
           '[Donnée de test] Réplique du maillot jaune de la Seleção.', 'publie', NOW() - INTERVAL 5 HOUR
    UNION ALL SELECT @b1, @sel, (SELECT id FROM equipes WHERE slug = 'france'), 'Maillot France domicile 2024/2025',
           'maillot-france-domicile-2024-2025', '2024/2025', 'domicile', 'officiel', 'occasion_tres_bon', 22000, 0, 0,
           '[Donnée de test] Porté deux fois, comme neuf.', 'publie', NOW() - INTERVAL 6 HOUR
    UNION ALL SELECT @b1, @sel, (SELECT id FROM equipes WHERE slug = 'argentine'), 'Maillot Argentine domicile 2024',
           'maillot-argentine-domicile-2024', '2024', 'domicile', 'version_fan', 'neuf_etiquette', 27000, 1, 3000,
           '[Donnée de test] Rayures ciel et blanc.', 'publie', NOW() - INTERVAL 7 HOUR
    UNION ALL SELECT @b1, @club, (SELECT id FROM equipes WHERE slug = 'real-madrid'), 'Maillot Real Madrid domicile 2025/2026',
           'maillot-real-madrid-domicile-2025-2026', '2025/2026', 'domicile', 'officiel', 'neuf_etiquette', 45000, 1, 4000,
           '[Donnée de test] Maillot blanc domicile.', 'publie', NOW() - INTERVAL 8 HOUR
    UNION ALL SELECT @b1, @club, (SELECT id FROM equipes WHERE slug = 'fc-barcelone'), 'Maillot FC Barcelone domicile 2025/2026',
           'maillot-fc-barcelone-domicile-2025-2026', '2025/2026', 'domicile', 'replique', 'neuf_etiquette', 13000, 1, 2000,
           '[Donnée de test] Réplique blaugrana.', 'publie', NOW() - INTERVAL 9 HOUR
    UNION ALL SELECT @b1, @club, (SELECT id FROM equipes WHERE slug = 'psg'), 'Maillot PSG domicile 2025/2026',
           'maillot-psg-domicile-2025-2026', '2025/2026', 'domicile', 'officiel', 'neuf_etiquette', 42000, 1, 4000,
           '[Donnée de test] Bleu marine, bande centrale rouge.', 'publie', NOW() - INTERVAL 10 HOUR
    UNION ALL SELECT @b1, @club, (SELECT id FROM equipes WHERE slug = 'liverpool'), 'Maillot Liverpool domicile 2024/2025',
           'maillot-liverpool-domicile-2024-2025', '2024/2025', 'domicile', 'version_fan', 'neuf_sans_etiquette', 24000, 0, 0,
           '[Donnée de test] Rouge, version supporter.', 'publie', NOW() - INTERVAL 11 HOUR
    UNION ALL SELECT @b1, @club, (SELECT id FROM equipes WHERE slug = 'manchester-city'), 'Maillot Manchester City domicile 2024/2025',
           'maillot-manchester-city-domicile-2024-2025', '2024/2025', 'domicile', 'replique', 'neuf_etiquette', 11000, 0, 0,
           '[Donnée de test] Réplique bleu ciel.', 'publie', NOW() - INTERVAL 12 HOUR
    UNION ALL SELECT @b1, @club, (SELECT id FROM equipes WHERE slug = 'al-nassr'), 'Maillot Al-Nassr domicile 2024/2025',
           'maillot-al-nassr-domicile-2024-2025', '2024/2025', 'domicile', 'replique', 'neuf_etiquette', 12500, 1, 2000,
           '[Donnée de test] Jaune et bleu.', 'publie', NOW() - INTERVAL 13 HOUR
    UNION ALL SELECT @b1, @club, (SELECT id FROM equipes WHERE slug = 'jaraaf'), 'Maillot ASC Jaraaf domicile 2024/2025',
           'maillot-asc-jaraaf-domicile-2024-2025', '2024/2025', 'domicile', 'officiel', 'neuf_etiquette', 15000, 1, 2000,
           '[Donnée de test] Le club de la Médina.', 'publie', NOW() - INTERVAL 14 HOUR
    UNION ALL SELECT @b2, @retro, (SELECT id FROM equipes WHERE slug = 'senegal'), 'Maillot Sénégal 2002 rétro',
           'maillot-senegal-2002-retro', '2002', 'domicile', 'replique', 'neuf_etiquette', 18000, 1, 2500,
           '[Donnée de test] L''épopée de 2002, réédition rétro.', 'publie', NOW() - INTERVAL 15 HOUR
    UNION ALL SELECT @b2, @retro, (SELECT id FROM equipes WHERE slug = 'cameroun'), 'Maillot Cameroun 2002 rétro sans manches',
           'maillot-cameroun-2002-retro-sans-manches', '2002', 'domicile', 'replique', 'neuf_etiquette', 17000, 0, 0,
           '[Donnée de test] Le célèbre maillot sans manches.', 'publie', NOW() - INTERVAL 16 HOUR
    UNION ALL SELECT @b2, @retro, (SELECT id FROM equipes WHERE slug = 'pays-bas'), 'Maillot Pays-Bas 1988 rétro',
           'maillot-pays-bas-1988-retro', '1988', 'domicile', 'replique', 'neuf_etiquette', 16000, 0, 0,
           '[Donnée de test] Orange et motifs géométriques.', 'publie', NOW() - INTERVAL 17 HOUR
    UNION ALL SELECT @b2, @retro, (SELECT id FROM equipes WHERE slug = 'ac-milan'), 'Maillot AC Milan 1989/1990 rétro',
           'maillot-ac-milan-1989-1990-retro', '1989/1990', 'domicile', 'officiel', 'occasion_bon', 65000, 0, 0,
           '[Donnée de test] Maillot d''époque, bon état.', 'publie', NOW() - INTERVAL 18 HOUR
    UNION ALL SELECT @b2, @retro, (SELECT id FROM equipes WHERE slug = 'bresil'), 'Maillot Brésil 1970 rétro',
           'maillot-bresil-1970-retro', '1970', 'domicile', 'replique', 'neuf_etiquette', 15000, 1, 2000,
           '[Donnée de test] Le Brésil de 1970, réédition.', 'publie', NOW() - INTERVAL 19 HOUR
    UNION ALL SELECT @b2, @retro, (SELECT id FROM equipes WHERE slug = 'france'), 'Maillot France 1998 rétro',
           'maillot-france-1998-retro', '1998', 'domicile', 'replique', 'neuf_sans_etiquette', 16500, 1, 2000,
           '[Donnée de test] Réédition du maillot de 1998.', 'publie', NOW() - INTERVAL 20 HOUR
) AS donnees
WHERE NOT EXISTS (SELECT 1 FROM maillots m WHERE m.boutique_id IN (@b1, @b2));

-- Texte de recherche (titre + équipe + saison), comme le fera l'espace vendeur au lot 3
UPDATE maillots m
JOIN equipes e ON e.id = m.equipe_id
SET m.texte_recherche = CONCAT_WS(' ', m.titre, e.nom, m.saison)
WHERE m.boutique_id IN (@b1, @b2);

-- -----------------------------------------------------------------------------
-- Tailles et stock (quelques tailles épuisées, et deux maillots en rupture totale)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO maillot_tailles (maillot_id, taille, stock)
SELECT m.id, t.taille,
       CASE
           WHEN m.slug IN ('maillot-france-domicile-2024-2025', 'maillot-ac-milan-1989-1990-retro') THEN 0  -- rupture
           WHEN t.taille IN ('XS', 'XXXL') THEN 0                                                          -- épuisées
           ELSE 1 + (m.id + t.rang) % 6
       END
FROM maillots m
JOIN (
    SELECT 'S' AS taille, 1 AS rang UNION ALL SELECT 'M', 2 UNION ALL SELECT 'L', 3 UNION ALL SELECT 'XL', 4
    UNION ALL SELECT 'XXL', 5 UNION ALL SELECT 'XS', 6 UNION ALL SELECT 'XXXL', 7
) AS t
WHERE m.boutique_id IN (@b1, @b2);
