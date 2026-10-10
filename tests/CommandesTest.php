<?php
/**
 * Commandes (§12) : totaux recalculés, une commande par boutique, stock, dernière pièce, statuts.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CommandesTest extends TestCase
{
    public function testTotalRecalculeEtUneCommandeParBoutique(): void
    {
        $client = creer_utilisateur_test();
        [, $boutiqueA] = creer_vendeur_test();
        [, $boutiqueB] = creer_vendeur_test();
        $maillotA = creer_maillot_test((int) $boutiqueA['id'], 12000, ['M' => 3]);
        $maillotB = creer_maillot_test((int) $boutiqueB['id'], 8000, ['L' => 2]);

        $creees = creer_commandes($client, [article_test($maillotA, 'M', 2), article_test($maillotB, 'L')], adresse_test(), []);

        self::assertCount(2, $creees, 'Un panier de deux boutiques donne deux commandes.');
        $a = commande_test($creees[0]['id']);
        self::assertSame(24000, (int) $a['sous_total']);
        self::assertSame(1500, (int) $a['frais_livraison']);
        self::assertSame(25500, (int) $a['total']);
        self::assertSame('attente_confirmation', $a['statut']);
        self::assertMatchesRegularExpression('/^CMD-\d{4}-\d{6}$/', $a['numero']);
        self::assertNull($a['code_livraison_chiffre'], 'Pas de code de livraison en paiement à la livraison.');
        self::assertSame(1, stock_test($maillotA, 'M'), 'Stock décrémenté à la commande.');
        self::assertSame(1, stock_test($maillotB, 'L'));
    }

    public function testAnnulationRestitueLeStock(): void
    {
        $client = creer_utilisateur_test();
        [$vendeur, $boutique] = creer_vendeur_test();
        $maillot = creer_maillot_test((int) $boutique['id'], 10000, ['M' => 2]);

        $id = creer_commandes($client, [article_test($maillot, 'M', 2)], adresse_test(), [])[0]['id'];
        self::assertSame(0, stock_test($maillot, 'M'));

        changer_statut_commande($id, 'annulee', 'vendeur', (int) $vendeur['id'], 'Rupture chez le fournisseur');
        self::assertSame(2, stock_test($maillot, 'M'), 'Stock restitué à l\'annulation.');
        self::assertSame('annulee', commande_test($id)['statut']);
    }

    public function testLePremierClientObtientLaDernierePiece(): void
    {
        [, $boutique] = creer_vendeur_test();
        $maillot = creer_maillot_test((int) $boutique['id'], 10000, ['XL' => 1]);

        creer_commandes(creer_utilisateur_test(), [article_test($maillot, 'XL')], adresse_test(), []);

        $this->expectException(ErreurCommande::class);
        creer_commandes(creer_utilisateur_test(), [article_test($maillot, 'XL')], adresse_test(), []);
    }

    public function testQuantiteSuperieureAuStockRefusee(): void
    {
        [, $boutique] = creer_vendeur_test();
        $maillot = creer_maillot_test((int) $boutique['id'], 10000, ['M' => 2]);

        try {
            creer_commandes(creer_utilisateur_test(), [article_test($maillot, 'M', 3)], adresse_test(), []);
            self::fail('Une quantité supérieure au stock doit être refusée.');
        } catch (ErreurCommande) {
            self::assertSame(2, stock_test($maillot, 'M'), 'Rien n\'est réservé quand la commande est refusée (transaction annulée).');
        }
    }

    public function testUnVendeurNePeutPasAcheterDansSaBoutique(): void
    {
        [$vendeur, $boutique] = creer_vendeur_test();
        $maillot = creer_maillot_test((int) $boutique['id']);

        $this->expectException(ErreurCommande::class);
        creer_commandes($vendeur, [article_test($maillot)], adresse_test(), []);
    }

    public function testLimiteDesCommandesALaLivraison(): void
    {
        $client = creer_utilisateur_test();
        [, $boutique] = creer_vendeur_test();
        $maillot = creer_maillot_test((int) $boutique['id'], 10000, ['M' => 10]);
        $maximum = (int) parametre('commandes_livraison_simultanees', '3');

        for ($i = 0; $i < $maximum; $i++) {
            creer_commandes($client, [article_test($maillot)], adresse_test(), []);
        }

        $this->expectException(ErreurCommande::class);
        creer_commandes($client, [article_test($maillot)], adresse_test(), []);
    }

    public function testCommandeSansCompteEtLienDeSuivi(): void
    {
        [, $boutique] = creer_vendeur_test();
        $client = compte_invite('Awa Diop', '');
        $adresse = adresse_test();
        $adresse['telephone'] = '+221771230001';

        self::assertSame(1, (int) $client['est_invite']);
        self::assertNull($client['mot_de_passe_hache'], 'Un compte invité ne peut pas se connecter.');
        self::assertTrue(email_technique($client['email']), 'Sans e-mail donné, aucune notification e-mail.');

        $creee = creer_commandes($client, [article_test(creer_maillot_test((int) $boutique['id']))], $adresse, [])[0];
        $commande = commande_test($creee['id']);
        self::assertNotNull($commande['jeton_suivi_chiffre']);
        self::assertStringStartsWith('commande/suivi.php?c=' . $creee['id'] . '&j=', $creee['lien']);

        $jeton = substr($creee['lien'], strpos($creee['lien'], '&j=') + 3);
        self::assertNotNull(commande_par_suivi((int) $creee['id'], $jeton), 'Le lien de suivi ouvre la commande.');
        self::assertNull(commande_par_suivi((int) $creee['id'], str_repeat('0', 64)), 'Un autre jeton est refusé.');
        self::assertSame($creee['lien'], lien_commande_client($commande), 'Les notifications pointent vers le suivi.');
    }

    public function testLimitesSansCompteParTelephone(): void
    {
        [, $boutique] = creer_vendeur_test();
        $maillot = creer_maillot_test((int) $boutique['id'], 10000, ['M' => 10]);
        $adresse = adresse_test();
        $adresse['telephone'] = '+221771230002';
        $maximum = (int) parametre('commandes_livraison_simultanees', '3');

        // Chaque commande crée un compte invité différent : la limite porte sur le numéro de téléphone.
        for ($i = 0; $i < $maximum; $i++) {
            creer_commandes(compte_invite('Client Repete', ''), [article_test($maillot)], $adresse, []);
        }

        $this->expectException(ErreurCommande::class);
        creer_commandes(compte_invite('Client Repete', ''), [article_test($maillot)], $adresse, []);
    }

    public function testMatriceDesStatuts(): void
    {
        // Autorisées (§12.4)
        self::assertTrue(transition_autorisee('attente_confirmation', 'confirmee', 'vendeur'));
        self::assertTrue(transition_autorisee('confirmee', 'expediee', 'vendeur'));
        self::assertTrue(transition_autorisee('livree', 'recue', 'client'));
        self::assertTrue(transition_autorisee('livree', 'recue', 'systeme'));
        self::assertTrue(transition_autorisee('attente_paiement', 'attente_confirmation', 'systeme'));
        // Refusées
        self::assertFalse(transition_autorisee('attente_confirmation', 'confirmee', 'client'), 'Le client ne confirme pas sa propre commande.');
        self::assertFalse(transition_autorisee('expediee', 'annulee', 'client'), 'Le client n\'annule plus une commande expédiée.');
        self::assertFalse(transition_autorisee('recue', 'annulee', 'vendeur'));
        self::assertFalse(transition_autorisee('annulee', 'confirmee', 'vendeur'), 'Une commande annulée ne revit pas.');
        self::assertFalse(transition_autorisee('attente_paiement', 'attente_confirmation', 'client'), 'Seul le système confirme un paiement.');
        self::assertFalse(transition_autorisee('livree', 'recue', 'vendeur'), 'Le vendeur ne déclare pas lui-même la réception.');
    }

    public function testTransitionRefuseeLeveUneErreur(): void
    {
        [$vendeur, $boutique] = creer_vendeur_test();
        $id = creer_commandes(creer_utilisateur_test(), [article_test(creer_maillot_test((int) $boutique['id']))], adresse_test(), [])[0]['id'];

        $this->expectException(ErreurCommande::class);
        changer_statut_commande($id, 'livree', 'vendeur', (int) $vendeur['id']);   // pas encore confirmée ni expédiée
    }

    public function testAnnulationVendeurSansMotifRefusee(): void
    {
        [$vendeur, $boutique] = creer_vendeur_test();
        $id = creer_commandes(creer_utilisateur_test(), [article_test(creer_maillot_test((int) $boutique['id']))], adresse_test(), [])[0]['id'];

        $this->expectException(ErreurCommande::class);
        changer_statut_commande($id, 'annulee', 'vendeur', (int) $vendeur['id'], '');
    }
}
