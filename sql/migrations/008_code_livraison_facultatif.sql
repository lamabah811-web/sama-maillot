-- =============================================================================
-- SAMA MAILLOT — migration 008 : code de livraison seulement pour le paiement en ligne
--
-- Décision du porteur du projet : en paiement à la livraison, le client paie le vendeur à la
-- remise, ce qui prouve la livraison. Le code n'est utile que lorsque la plateforme détient
-- l'argent (paiement en ligne, séquestre — lot 8). La colonne devient donc facultative.
-- À importer UNE fois sur une base créée avant cette étape.
-- =============================================================================

ALTER TABLE commandes
    MODIFY code_livraison_chiffre VARCHAR(255) NULL COMMENT 'Paiement en ligne uniquement (§14.1) ; NULL en paiement à la livraison';
