-- =============================================================================
-- SAMA MAILLOT — utilisateur MySQL de l'APPLICATION en production (§24 : moindre privilège)
--
-- Le site n'a besoin que de lire et d'écrire des lignes : pas de DROP, pas d'ALTER, pas de GRANT.
-- Les migrations (sql/migrations/) et l'import initial se font avec le compte d'administration de la
-- base (phpMyAdmin d'Infomaniak), jamais avec celui-ci.
--
-- Chez Infomaniak, les utilisateurs se créent dans le Manager (Hébergement → Bases de données →
-- Utilisateurs) en cochant uniquement : SELECT, INSERT, UPDATE, DELETE. Ce script sert si vous avez
-- un accès SQL complet ; remplacez le nom de la base et le mot de passe avant de l'exécuter.
-- =============================================================================

CREATE USER IF NOT EXISTS 'sama_app'@'%' IDENTIFIED BY 'REMPLACER_PAR_UN_MOT_DE_PASSE_LONG_ET_ALEATOIRE';

GRANT SELECT, INSERT, UPDATE, DELETE ON `NOM_DE_LA_BASE`.* TO 'sama_app'@'%';

FLUSH PRIVILEGES;

-- Vérification : doit afficher uniquement SELECT, INSERT, UPDATE, DELETE
-- SHOW GRANTS FOR 'sama_app'@'%';
