-- =============================================================================
-- SAMA MAILLOT — migration 013 : informations légales de l'éditeur (mentions légales, §25)
-- À importer UNE fois sur une base créée avant cette étape. Modifiables dans
-- Administration → Paramètres → « Informations légales ».
-- =============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO parametres (cle, valeur, description) VALUES
    ('entreprise_raison_sociale',  '', 'Raison sociale de l''éditeur (ex. SAMA MAILLOT SUARL)'),
    ('entreprise_forme_juridique', '', 'Forme juridique (entreprise individuelle, SUARL…) et capital'),
    ('entreprise_adresse',         '', 'Adresse du siège'),
    ('entreprise_ninea',           '', 'NINEA'),
    ('entreprise_rccm',            '', 'Numéro RCCM'),
    ('entreprise_responsable',     '', 'Directeur de la publication'),
    ('cdp_declaration',            '', 'Numéro de récépissé de déclaration à la CDP');
