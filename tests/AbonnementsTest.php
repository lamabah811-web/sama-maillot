<?php
/**
 * Abonnements vendeurs (§18) : essai, rappel J-2, grâce, masquage, renouvellement sans jour perdu,
 * identifiant Wave à usage unique. L'état est toujours calculé à partir des dates en base.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AbonnementsTest extends TestCase
{
    /** Déplace la fin de l'essai du vendeur (en jours, négatif = dans le passé). */
    private function finEssai(array $vendeur, int $jours): void
    {
        bdd()->prepare("UPDATE essais_gratuits SET fin_le = NOW() + INTERVAL $jours DAY WHERE utilisateur_id = :id")->execute(['id' => $vendeur['id']]);
    }

    public function testEssaiEnCours(): void
    {
        [, $boutique] = creer_vendeur_test(14);
        $etat = calculer_etat_abonnement($boutique);

        self::assertSame('essai', $etat['etat']);
        self::assertTrue($etat['peut_ajouter']);
        self::assertTrue($etat['visible']);
    }

    public function testGraceApresLaFinPuisMasquage(): void
    {
        [$vendeur, $boutique] = creer_vendeur_test();
        $grace = (int) parametre('abonnement_grace_jours', '3');

        $this->finEssai($vendeur, -1);
        $etat = calculer_etat_abonnement($boutique);
        self::assertSame('grace', $etat['etat']);
        self::assertFalse($etat['peut_ajouter'], 'Ajout de maillots bloqué pendant la grâce.');
        self::assertTrue($etat['visible'], 'Maillots encore visibles pendant la grâce.');
        self::assertSame('grace', synchroniser_statut_boutique($boutique));

        $this->finEssai($vendeur, -($grace + 1));
        $etat = calculer_etat_abonnement($boutique);
        self::assertSame('expire', $etat['etat']);
        self::assertFalse($etat['visible']);
        self::assertSame('masquee', synchroniser_statut_boutique($boutique), 'Boutique masquée à J+' . ($grace + 1) . '.');
    }

    public function testBoutiqueMasqueeInvisibleAuCatalogue(): void
    {
        [$vendeur, $boutique] = creer_vendeur_test();
        $maillot = creer_maillot_test((int) $boutique['id']);
        $this->finEssai($vendeur, -30);
        synchroniser_statut_boutique($boutique);

        self::assertNull(maillot_par_id($maillot), 'Maillot d\'une boutique masquée introuvable.');
        $this->expectException(ErreurCommande::class);
        creer_commandes(creer_utilisateur_test(), [article_test($maillot)], adresse_test(), []);
    }

    public function testRappelJMoins2EnvoyeUneSeuleFois(): void
    {
        [$vendeur, $boutique] = creer_vendeur_test();
        $this->finEssai($vendeur, 1);

        traiter_abonnements();
        traiter_abonnements();

        $requete = bdd()->prepare('SELECT COUNT(*) FROM rappels_abonnement WHERE boutique_id = :id AND cle LIKE :cle');
        $requete->execute(['id' => $boutique['id'], 'cle' => 'j-%']);
        self::assertSame(1, (int) $requete->fetchColumn(), 'Un seul rappel J-2, même si la tâche tourne plusieurs fois.');
    }

    public function testRenouvellementPendantLaGraceSansJourPerdu(): void
    {
        [$vendeur, $boutique] = creer_vendeur_test();
        $this->finEssai($vendeur, -1);
        $finEssai = periodes_boutique($boutique)['fin'];

        bdd()->prepare(
            "INSERT INTO paiements_abonnement (boutique_id, utilisateur_id, mois, montant, reference_wave, capture, statut)
             VALUES (:b, :u, 1, :montant, :ref, NULL, 'en_verification')"
        )->execute(['b' => $boutique['id'], 'u' => $vendeur['id'], 'montant' => prix_abonnement(), 'ref' => 'T_' . uniqid()]);
        $paiementId = (int) bdd()->lastInsertId();
        self::assertSame('verification', calculer_etat_abonnement($boutique)['etat'], 'Boutique visible pendant la vérification du paiement.');

        self::assertNull(valider_paiement_abonnement(creer_utilisateur_test('admin'), $paiementId));

        $etat = calculer_etat_abonnement($boutique);
        self::assertSame('actif', $etat['etat']);
        self::assertSame(strtotime($finEssai) + 30 * 86400, strtotime($etat['fin']), 'Les 30 jours partent de la fin précédente.');
        self::assertSame('active', synchroniser_statut_boutique(boutique_par_id_test((int) $boutique['id'])));
    }

    public function testIdentifiantWaveUtilisableUneSeuleFois(): void
    {
        [$vendeur, $boutique] = creer_vendeur_test();
        $reference = 'T_' . strtoupper(uniqid());
        bdd()->prepare(
            "INSERT INTO paiements_abonnement (boutique_id, utilisateur_id, mois, montant, reference_wave, statut)
             VALUES (:b, :u, 1, 3000, :ref, 'refuse')"
        )->execute(['b' => $boutique['id'], 'u' => $vendeur['id'], 'ref' => $reference]);

        $erreurs = soumettre_paiement_abonnement($vendeur, $boutique, 1, $reference, null);
        self::assertArrayHasKey('reference', $erreurs);
    }

    public function testLienWaveNormalise(): void
    {
        // Le lien collé par l'admin peut contenir un ancien montant : il est retiré, puis remplacé par le bon.
        self::assertSame('https://pay.wave.com/m/M_sn_TEST/c/sn/', normaliser_lien_wave('https://pay.wave.com/m/M_sn_TEST/c/sn/?amount=3000'));
        self::assertNull(normaliser_lien_wave('https://pirate.example/pay.wave.com'), 'Seul un lien pay.wave.com est accepté.');
        self::assertNull(normaliser_lien_wave('http://pay.wave.com/m/M_sn_TEST/'), 'HTTPS obligatoire.');
        self::assertNull(lien_paiement_wave(3000), 'Aucun lien tant qu\'il n\'est pas configuré.');
    }
}
