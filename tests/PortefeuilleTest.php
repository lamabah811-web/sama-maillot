<?php
/**
 * Paiement en ligne et séquestre (§13, §15) : vérification serveur, une seule prise en compte,
 * montant contrôlé, argent en attente → disponible / bloqué, remboursements, versements.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PortefeuilleTest extends TestCase
{
    /** Commande payée en ligne et confirmée par l'agrégateur. @return array{0: array, 1: array, 2: array, 3: array} [vendeur, boutique, client, commande] */
    private function commandePayee(int $prix = 20000): array
    {
        $client = creer_utilisateur_test();
        [$vendeur, $boutique] = creer_vendeur_test();
        $creees = creer_commandes($client, [article_test(creer_maillot_test((int) $boutique['id'], $prix))], adresse_test(), [], 'site', 'en_ligne');
        creer_paiement($client, $creees);
        $commande = commande_test($creees[0]['id']);
        appliquer_resultat_paiement((int) $commande['transaction_id'], ['statut' => 'reussi', 'montant' => (int) $commande['total'], 'moyen' => 'wave']);

        return [$vendeur, $boutique, $client, commande_test((int) $commande['id'])];
    }

    public function testCommandeEnLigneAttendLePaiement(): void
    {
        $client = creer_utilisateur_test();
        [, $boutique] = creer_vendeur_test();
        $commande = commande_test(creer_commandes($client, [article_test(creer_maillot_test((int) $boutique['id']))], adresse_test(), [], 'site', 'en_ligne')[0]['id']);

        self::assertSame('attente_paiement', $commande['statut']);
        self::assertNotNull($commande['code_livraison_chiffre'], 'Code de livraison créé pour le paiement en ligne.');
        self::assertMatchesRegularExpression('/^\d{4}$/', (string) code_livraison_client($commande));
        self::assertNull(commande_de_la_boutique((int) $commande['id'], (int) $boutique['id']), 'Le vendeur ne voit pas une commande non payée.');
    }

    public function testPaiementReussiCrediteEnAttenteUneSeuleFois(): void
    {
        [, $boutique, , $commande] = $this->commandePayee(20000);

        self::assertSame('attente_confirmation', $commande['statut']);
        self::assertNotNull($commande['payee_le']);
        self::assertSame((int) $commande['montant_vendeur'], soldes_portefeuille((int) $boutique['id'])['en_attente']);

        // Notification reçue une deuxième fois : aucune double prise en compte
        appliquer_resultat_paiement((int) $commande['transaction_id'], ['statut' => 'reussi', 'montant' => (int) $commande['total']]);
        self::assertSame((int) $commande['montant_vendeur'], soldes_portefeuille((int) $boutique['id'])['en_attente']);
    }

    public function testMontantIncorrectBloqueLaCommande(): void
    {
        $client = creer_utilisateur_test();
        [, $boutique] = creer_vendeur_test();
        $creees = creer_commandes($client, [article_test(creer_maillot_test((int) $boutique['id'], 15000))], adresse_test(), [], 'site', 'en_ligne');
        creer_paiement($client, $creees);
        $commande = commande_test($creees[0]['id']);

        $statut = appliquer_resultat_paiement((int) $commande['transaction_id'], ['statut' => 'reussi', 'montant' => (int) $commande['total'] - 500]);

        self::assertSame('montant_incorrect', $statut);
        self::assertSame('attente_paiement', commande_test((int) $commande['id'])['statut']);
        self::assertSame(0, soldes_portefeuille((int) $boutique['id'])['en_attente'], 'Rien n\'est crédité au vendeur.');
    }

    public function testPaiementEchoueAnnuleEtRestitueLeStock(): void
    {
        $client = creer_utilisateur_test();
        [, $boutique] = creer_vendeur_test();
        $maillot = creer_maillot_test((int) $boutique['id'], 10000, ['M' => 1]);
        $creees = creer_commandes($client, [article_test($maillot)], adresse_test(), [], 'site', 'en_ligne');
        creer_paiement($client, $creees);
        self::assertSame(0, stock_test($maillot, 'M'), 'Stock réservé pendant le paiement.');

        appliquer_resultat_paiement((int) commande_test($creees[0]['id'])['transaction_id'], ['statut' => 'echoue']);

        self::assertSame('annulee', commande_test($creees[0]['id'])['statut']);
        self::assertSame(1, stock_test($maillot, 'M'));
    }

    public function testReceptionDebloqueLesFonds(): void
    {
        [$vendeur, $boutique, $client, $commande] = $this->commandePayee();
        $id = (int) $commande['id'];
        changer_statut_commande($id, 'confirmee', 'vendeur', (int) $vendeur['id']);
        changer_statut_commande($id, 'expediee', 'vendeur', (int) $vendeur['id']);
        changer_statut_commande($id, 'recue', 'client', (int) $client['id']);

        $soldes = soldes_portefeuille((int) $boutique['id']);
        self::assertSame(0, $soldes['en_attente']);
        self::assertSame((int) $commande['montant_vendeur'], $soldes['disponible']);
    }

    public function testLivraisonAvecCodeDebloqueApres24Heures(): void
    {
        [$vendeur, $boutique, , $commande] = $this->commandePayee();
        $id = (int) $commande['id'];
        changer_statut_commande($id, 'confirmee', 'vendeur', (int) $vendeur['id']);
        changer_statut_commande($id, 'expediee', 'vendeur', (int) $vendeur['id']);
        $resultat = verifier_code_livraison(commande_test($id), (string) code_livraison_client(commande_test($id)), (int) $vendeur['id']);
        self::assertTrue($resultat['ok']);

        traiter_commandes_echues();
        self::assertSame('livree', commande_test($id)['statut'], 'Pas de déblocage avant 24 h.');

        bdd()->prepare('UPDATE commandes SET livree_le = NOW() - INTERVAL 25 HOUR WHERE id = :id')->execute(['id' => $id]);
        traiter_commandes_echues();
        self::assertSame('recue', commande_test($id)['statut']);
        self::assertSame((int) $commande['montant_vendeur'], soldes_portefeuille((int) $boutique['id'])['disponible']);
    }

    public function testCodeDeLivraisonBloqueApresCinqEssais(): void
    {
        [$vendeur, , , $commande] = $this->commandePayee();
        $id = (int) $commande['id'];
        changer_statut_commande($id, 'confirmee', 'vendeur', (int) $vendeur['id']);
        changer_statut_commande($id, 'expediee', 'vendeur', (int) $vendeur['id']);
        $vrai = (string) code_livraison_client(commande_test($id));
        $faux = str_pad((string) (((int) $vrai + 1) % 10000), 4, '0', STR_PAD_LEFT);

        for ($i = 0; $i < 5; $i++) {
            self::assertFalse(verifier_code_livraison(commande_test($id), $faux, (int) $vendeur['id'])['ok']);
        }
        self::assertFalse(verifier_code_livraison(commande_test($id), $vrai, (int) $vendeur['id'])['ok'], 'Bon code refusé après 5 essais.');
        self::assertSame('expediee', commande_test($id)['statut']);
    }

    public function testReclamationBloquePuisDecisionFondeeRembourse(): void
    {
        [$vendeur, $boutique, $client, $commande] = $this->commandePayee();
        $id = (int) $commande['id'];
        changer_statut_commande($id, 'confirmee', 'vendeur', (int) $vendeur['id']);
        changer_statut_commande($id, 'expediee', 'vendeur', (int) $vendeur['id']);
        changer_statut_commande($id, 'livree', 'vendeur', (int) $vendeur['id'], null, ['avec_code' => false]);

        $resultat = ouvrir_litige($client, commande_test($id), 'non_recu', 'Je n\'ai jamais reçu ce maillot, personne n\'est venu.', []);
        self::assertSame([], $resultat['erreurs']);
        self::assertSame((int) $commande['montant_vendeur'], soldes_portefeuille((int) $boutique['id'])['bloque']);

        $admin = creer_utilisateur_test('admin');
        self::assertNull(decider_litige($admin, litige_complet((int) $resultat['id']), 'fondee', 'Livraison non prouvée.'));

        $soldes = soldes_portefeuille((int) $boutique['id']);
        self::assertSame(0, $soldes['bloque'] + $soldes['disponible'] + $soldes['en_attente'], 'Le vendeur ne touche rien.');
        self::assertSame('remboursee', commande_test($id)['statut']);
        self::assertSame(1, (int) bdd()->query("SELECT COUNT(*) FROM remboursements WHERE commande_id = $id AND statut = 'a_rembourser'")->fetchColumn());
    }

    public function testAnnulationDUneCommandePayeeCreeUnRemboursement(): void
    {
        [$vendeur, $boutique, , $commande] = $this->commandePayee();
        $id = (int) $commande['id'];

        changer_statut_commande($id, 'annulee', 'vendeur', (int) $vendeur['id'], 'Rupture');

        self::assertSame(0, soldes_portefeuille((int) $boutique['id'])['en_attente']);
        $remboursement = bdd()->query("SELECT * FROM remboursements WHERE commande_id = $id")->fetch();
        self::assertSame((int) $commande['total'], (int) $remboursement['montant'], 'Le client récupère le total payé.');

        $admin = creer_utilisateur_test('admin');
        self::assertNull(marquer_rembourse($admin, (int) $remboursement['id'], 'RMB-0001'));
        self::assertSame('remboursee', commande_test($id)['statut']);
        self::assertNotNull(marquer_rembourse($admin, (int) $remboursement['id'], 'RMB-0002'), 'Pas de double remboursement.');
    }

    public function testVersementVideLeDisponibleUneSeuleFois(): void
    {
        [$vendeur, $boutique, $client, $commande] = $this->commandePayee(30000);
        $id = (int) $commande['id'];
        changer_statut_commande($id, 'confirmee', 'vendeur', (int) $vendeur['id']);
        changer_statut_commande($id, 'expediee', 'vendeur', (int) $vendeur['id']);
        changer_statut_commande($id, 'recue', 'client', (int) $client['id']);
        $admin = creer_utilisateur_test('admin');

        self::assertNull(enregistrer_versement($admin, (int) $boutique['id'], 'WAVE-TEST-' . $id));
        $soldes = soldes_portefeuille((int) $boutique['id']);
        self::assertSame(0, $soldes['disponible']);
        self::assertSame((int) $commande['montant_vendeur'], $soldes['verse']);

        self::assertNotNull(enregistrer_versement($admin, (int) $boutique['id'], 'WAVE-TEST-BIS-' . $id), 'Rien à verser une deuxième fois.');
    }

    public function testRegistreEquilibre(): void
    {
        // Chaque transfert est une paire (−x, +x) : la somme des transferts d'une commande est nulle,
        // seuls les crédits et remboursements changent le total.
        $sommes = bdd()->query(
            "SELECT commande_id, SUM(montant) AS somme FROM mouvements_portefeuille
             WHERE operation IN ('deblocage', 'blocage', 'liberation') GROUP BY commande_id HAVING somme <> 0"
        )->fetchAll();
        self::assertSame([], $sommes);
    }
}
