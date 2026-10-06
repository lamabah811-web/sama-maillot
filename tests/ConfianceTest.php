<?php
/**
 * Confiance (§20, §21) : signalements réservés aux clients ayant commandé, suspension automatique à
 * 3 clients différents, levée par l'admin ; avis uniquement après réception, un par commande.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ConfianceTest extends TestCase
{
    /** Client ayant une commande (non annulée) chez cette boutique. */
    private function clientAyantCommande(array $boutique): array
    {
        $client = creer_utilisateur_test();
        creer_commandes($client, [article_test(creer_maillot_test((int) $boutique['id']))], adresse_test(), []);

        return $client;
    }

    public function testSignalementReserveAuxClientsAyantCommande(): void
    {
        [, $boutique] = creer_vendeur_test();
        self::assertNotNull(refus_signalement_vendeur(creer_utilisateur_test(), (int) $boutique['id']));
        self::assertNull(refus_signalement_vendeur($this->clientAyantCommande($boutique), (int) $boutique['id']));
    }

    public function testSuspensionAutomatiqueATroisClientsDifferents(): void
    {
        [, $boutique] = creer_vendeur_test();
        $seuil = (int) parametre('signalements_avant_suspension', '3');
        $boutiqueId = (int) $boutique['id'];

        $premier = $this->clientAyantCommande($boutique);
        creer_signalement($premier, 'vendeur', $boutiqueId, null, 'arnaque', 'Description du problème rencontré.');
        self::assertNotNull(refus_signalement_vendeur($premier, $boutiqueId), 'Un même client ne signale qu\'une fois sur la période.');

        for ($i = 1; $i < $seuil - 1; $i++) {
            creer_signalement($this->clientAyantCommande($boutique), 'vendeur', $boutiqueId, null, 'autre', 'Description du problème rencontré.');
        }
        self::assertSame('active', boutique_par_id_test($boutiqueId)['statut'], 'Pas de suspension avant le seuil.');

        creer_signalement($this->clientAyantCommande($boutique), 'vendeur', $boutiqueId, null, 'autre', 'Description du problème rencontré.');
        self::assertSame('suspendue', boutique_par_id_test($boutiqueId)['statut'], 'Suspension automatique au seuil.');

        $admin = creer_utilisateur_test('admin');
        self::assertNull(changer_statut_boutique($admin, $boutiqueId, false, 'Vérification faite avec le vendeur'));
        self::assertNotSame('suspendue', boutique_par_id_test($boutiqueId)['statut'], 'Levée par l\'administrateur.');
    }

    public function testSignalementClasseNeComptePlus(): void
    {
        [, $boutique] = creer_vendeur_test();
        $id = creer_signalement($this->clientAyantCommande($boutique), 'vendeur', (int) $boutique['id'], null, 'autre', 'Description du problème rencontré.');
        self::assertSame(1, signaleurs_distincts((int) $boutique['id']));

        traiter_signalement(creer_utilisateur_test('admin'), $id, 'classe', 'Signalement infondé');
        self::assertSame(0, signaleurs_distincts((int) $boutique['id']));
    }

    public function testAvisUniquementApresReceptionEtModifiableUneFois(): void
    {
        $client = creer_utilisateur_test();
        [$vendeur, $boutique] = creer_vendeur_test();
        $id = creer_commandes($client, [article_test(creer_maillot_test((int) $boutique['id']))], adresse_test(), [])[0]['id'];

        self::assertNotNull(refus_avis(commande_test($id), null), 'Pas d\'avis avant la réception.');

        changer_statut_commande($id, 'confirmee', 'vendeur', (int) $vendeur['id']);
        changer_statut_commande($id, 'expediee', 'vendeur', (int) $vendeur['id']);
        changer_statut_commande($id, 'recue', 'client', (int) $client['id']);
        self::assertNull(refus_avis(commande_test($id), null));

        enregistrer_avis($client, commande_test($id), null, 4, 'Très bien');
        $avis = avis_de_la_commande($id);
        self::assertNull(refus_avis(commande_test($id), $avis), 'Une modification possible.');

        enregistrer_avis($client, commande_test($id), $avis, 5, 'Parfait');
        self::assertNotNull(refus_avis(commande_test($id), avis_de_la_commande($id)), 'Pas de deuxième modification.');
        self::assertSame(5, (int) avis_de_la_commande($id)['note']);
    }

    public function testNoteDuVendeur(): void
    {
        [$vendeur, $boutique] = creer_vendeur_test();
        $notes = [5, 4, 3];
        foreach ($notes as $i => $note) {
            $client = creer_utilisateur_test();
            $id = creer_commandes($client, [article_test(creer_maillot_test((int) $boutique['id']))], adresse_test(), [])[0]['id'];
            changer_statut_commande($id, 'confirmee', 'vendeur', (int) $vendeur['id']);
            changer_statut_commande($id, 'expediee', 'vendeur', (int) $vendeur['id']);
            changer_statut_commande($id, 'recue', 'client', (int) $client['id']);
            enregistrer_avis($client, commande_test($id), null, $note, '');
            if ($i === 1) {
                // note_vendeur() garde un cache par page : on lit directement ici la règle « moins de 3 avis ».
                self::assertSame(2, (int) bdd()->query('SELECT COUNT(*) FROM avis WHERE boutique_id = ' . (int) $boutique['id'])->fetchColumn());
            }
        }

        $note = note_vendeur((int) $boutique['id']);
        self::assertSame(3, $note['nombre']);
        self::assertSame(4.0, $note['moyenne']);
        self::assertSame('★ 4,0 (3 avis)', $note['libelle']);
    }
}
