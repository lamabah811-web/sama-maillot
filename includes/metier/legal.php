<?php
/**
 * SAMA MAILLOT — informations légales et suppression de compte (§7.3, §8.7, §25).
 *
 * Informations de l'éditeur : paramètres « entreprise_* » (Administration → Paramètres).
 * Tant qu'une information manque, la page l'indique clairement « à compléter » : à renseigner
 * AVANT la mise en production (structure légale avec NINEA et RCCM, §25).
 *
 * Suppression de compte (§8.7, loi n° 2008-12) : les données personnelles sont ANONYMISÉES ;
 * l'historique des commandes est conservé pour la comptabilité.
 */

declare(strict_types=1);

/** Date de dernière mise à jour des documents légaux (CGU, CGV, confidentialité, politique vendeur). */
const DATE_DOCUMENTS_LEGAUX = '6 octobre 2026';

/** Hébergeur (§33.1). */
const HEBERGEUR = [
    'nom' => 'Infomaniak Network SA',
    'adresse' => 'Rue Eugène-Marziano 25, 1227 Les Acacias (GE), Suisse',
    'site' => 'https://www.infomaniak.com',
];

/** Information légale de l'éditeur, échappée ; « à compléter » bien visible si elle manque. */
function info_legale(string $cle, string $libelle): string
{
    $valeur = trim(parametre($cle));

    return $valeur !== ''
        ? e($valeur)
        : '<span class="sm-a-completer">[' . e($libelle) . ' à compléter]</span>';
}

/** Nom affiché de l'éditeur (raison sociale, sinon le nom du site). */
function nom_editeur(): string
{
    $raison = trim(parametre('entreprise_raison_sociale'));

    return $raison !== '' ? $raison : NOM_SITE;
}

/* -------------------------------------------------------------------------
   Suppression de compte
   ------------------------------------------------------------------------- */

/** Motif empêchant la suppression (commandes en cours, argent dans le portefeuille…), ou null. */
function refus_suppression_compte(array $utilisateur): ?string
{
    if ($utilisateur['role'] === 'admin') {
        return 'Un compte administrateur ne peut pas être supprimé depuis cette page.';
    }

    $enCours = ['attente_paiement', 'attente_confirmation', 'confirmee', 'expediee', 'livree', 'en_litige'];
    $place = implode(',', array_fill(0, count($enCours), '?'));

    $requete = bdd()->prepare("SELECT COUNT(*) FROM commandes WHERE client_id = ? AND statut IN ($place)");
    $requete->execute([$utilisateur['id'], ...$enCours]);
    if ((int) $requete->fetchColumn() > 0) {
        return 'Vous avez des commandes en cours. Attendez leur réception (ou leur annulation) avant de supprimer votre compte.';
    }

    $boutique = boutique_du_vendeur((int) $utilisateur['id']);
    if ($boutique !== null) {
        $requete = bdd()->prepare("SELECT COUNT(*) FROM commandes WHERE boutique_id = ? AND statut IN ($place)");
        $requete->execute([$boutique['id'], ...$enCours]);
        if ((int) $requete->fetchColumn() > 0) {
            return 'Votre boutique a des commandes en cours : livrez-les ou annulez-les avant de supprimer votre compte.';
        }
        $soldes = soldes_portefeuille((int) $boutique['id']);
        if ($soldes['en_attente'] + $soldes['disponible'] + $soldes['bloque'] > 0) {
            return 'Votre portefeuille contient encore de l\'argent : attendez le prochain versement avant de supprimer votre compte.';
        }
    }

    $requete = bdd()->prepare("SELECT COUNT(*) FROM remboursements WHERE client_id = ? AND statut = 'a_rembourser'");
    $requete->execute([$utilisateur['id']]);
    if ((int) $requete->fetchColumn() > 0) {
        return 'Un remboursement est en cours pour vous : attendez qu\'il soit effectué avant de supprimer votre compte.';
    }

    return null;
}

/**
 * Anonymise le compte (transaction) : identité, e-mail, téléphone et mot de passe effacés ;
 * adresses, favoris, panier, notifications et jetons supprimés ; boutique et maillots retirés.
 * Les commandes restent (comptabilité), les avis restent signés « Utilisateur S. ».
 * Les liaisons Google / Facebook sont supprimées. Aucun jeton Facebook n'étant conservé, l'appel
 * DELETE /{id}/permissions est impossible : la suppression est locale (l'utilisateur peut retirer
 * l'application dans les paramètres de son compte Facebook).
 */
function supprimer_compte(array $utilisateur): void
{
    $id = (int) $utilisateur['id'];
    $pdo = bdd();
    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            "UPDATE utilisateurs SET prenom = 'Utilisateur', nom = 'Supprimé', email = :email, telephone = NULL,
                    telephone_verifie_le = NULL, mot_de_passe_hache = NULL, google_id = NULL, email_verifie = 0,
                    version_session = version_session + 1, supprime_le = NOW()
             WHERE id = :id"
        )->execute(['email' => 'supprime-' . $id . '@compte-supprime.invalid', 'id' => $id]);

        foreach (['adresses', 'favoris', 'notifications', 'jetons_connexion', 'connexions_sociales'] as $table) {
            $pdo->prepare("DELETE FROM $table WHERE utilisateur_id = :id")->execute(['id' => $id]);
        }
        $pdo->prepare('DELETE FROM paniers WHERE utilisateur_id = :id')->execute(['id' => $id]);

        $boutique = boutique_du_vendeur($id);
        if ($boutique !== null) {
            $pdo->prepare("UPDATE boutiques SET statut = 'masquee', numero_whatsapp = NULL, numero_wave = NULL, supprime_le = NOW() WHERE id = :id")
                ->execute(['id' => $boutique['id']]);
            $pdo->prepare("UPDATE maillots SET statut = 'supprime', supprime_le = NOW() WHERE boutique_id = :id AND supprime_le IS NULL")
                ->execute(['id' => $boutique['id']]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    journaliser_action('compte_supprime', $id, 'utilisateur', $id);
}
