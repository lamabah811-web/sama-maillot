-- =============================================================================
-- SAMA MAILLOT — migration 006 : livraison « frais à convenir avec le vendeur »
--
-- Quand une boutique n'a pas de tarif pour la zone du client, la commande reste possible :
-- les frais de livraison sont fixés ensuite entre le client et le vendeur.
-- À importer UNE fois sur une base créée avant cette étape.
-- =============================================================================

ALTER TABLE commandes
    ADD COLUMN IF NOT EXISTS frais_a_convenir TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = pas de tarif pour cette zone : frais fixés avec le vendeur' AFTER frais_livraison;
