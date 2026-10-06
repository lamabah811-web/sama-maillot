<?php
/**
 * SAMA MAILLOT — notes et avis sur les vendeurs (§20).
 *
 * - L'avis porte sur la boutique, pas sur le maillot.
 * - Seul le client d'une commande « Reçue » peut noter, dans les 30 jours (paramètre avis_delai_jours).
 * - Un avis par commande : note de 1 à 5 (obligatoire), commentaire facultatif (500 caractères).
 * - Modifiable une seule fois, dans les 7 jours.
 * - Le vendeur publie une réponse publique par avis ; l'administrateur masque un avis abusif
 *   (motif journalisé). La note moyenne ne compte que les avis visibles.
 */

declare(strict_types=1);

const AVIS_MODIFICATION_JOURS = 7;

/** Avis existant pour une commande, ou null. */
function avis_de_la_commande(int $commandeId): ?array
{
    $requete = bdd()->prepare('SELECT * FROM avis WHERE commande_id = :id');
    $requete->execute(['id' => $commandeId]);
    $avis = $requete->fetch();

    return $avis === false ? null : $avis;
}

/**
 * Le client peut-il noter (ou modifier sa note) pour cette commande ? null = oui, sinon le motif.
 */
function refus_avis(array $commande, ?array $avis): ?string
{
    if ($commande['statut'] !== 'recue' || $commande['recue_le'] === null) {
        return 'Vous pourrez noter le vendeur une fois la commande reçue.';
    }

    if ($avis === null) {
        $jours = max(1, (int) parametre('avis_delai_jours', '30'));
        if (strtotime($commande['recue_le']) + $jours * 86400 < time()) {
            return 'Le délai pour donner un avis (' . $jours . ' jours après la réception) est dépassé.';
        }

        return null;
    }

    if ((int) $avis['modifications'] >= 1) {
        return 'Vous avez déjà modifié votre avis : il ne peut plus être changé.';
    }
    if (strtotime($avis['cree_le']) + AVIS_MODIFICATION_JOURS * 86400 < time()) {
        return 'Un avis ne peut être modifié que dans les ' . AVIS_MODIFICATION_JOURS . ' jours qui suivent.';
    }

    return null;
}

/** Enregistre ou modifie l'avis du client pour une commande (contrôles faits par l'appelant). */
function enregistrer_avis(array $client, array $commande, ?array $avis, int $note, string $commentaire): void
{
    $commentaire = trim($commentaire);
    $commentaire = $commentaire === '' ? null : mb_substr($commentaire, 0, 500);

    if ($avis === null) {
        bdd()->prepare(
            'INSERT INTO avis (commande_id, boutique_id, client_id, note, commentaire) VALUES (:commande, :boutique, :client, :note, :commentaire)'
        )->execute([
            'commande' => $commande['id'], 'boutique' => $commande['boutique_id'], 'client' => $client['id'],
            'note' => $note, 'commentaire' => $commentaire,
        ]);
        $vendeurId = (int) boutique_proprietaire((int) $commande['boutique_id']);
        notifier($vendeurId, 'avis_nouveau', 'Nouvel avis : ' . str_repeat('★', $note) . str_repeat('☆', 5 - $note),
            $commentaire !== null ? '« ' . mb_substr($commentaire, 0, 120) . ' »' : 'Un client a noté votre boutique.', 'vendeur/avis.php');
        journaliser_action('avis_publie', (int) $client['id'], 'commande', (int) $commande['id'], null, (string) $note);

        return;
    }

    bdd()->prepare('UPDATE avis SET note = :note, commentaire = :commentaire, modifications = modifications + 1 WHERE id = :id')
        ->execute(['note' => $note, 'commentaire' => $commentaire, 'id' => $avis['id']]);
    journaliser_action('avis_modifie', (int) $client['id'], 'commande', (int) $commande['id'], (string) $avis['note'], (string) $note);
}

/** Réponse publique du vendeur (une par avis, modifiable). */
function repondre_avis(int $boutiqueId, int $avisId, string $reponse): ?string
{
    $reponse = trim($reponse);
    if (mb_strlen($reponse) < 2 || mb_strlen($reponse) > 500) {
        return 'Votre réponse doit contenir entre 2 et 500 caractères.';
    }

    $requete = bdd()->prepare('UPDATE avis SET reponse = :reponse, reponse_le = NOW() WHERE id = :id AND boutique_id = :boutique');
    $requete->execute(['reponse' => $reponse, 'id' => $avisId, 'boutique' => $boutiqueId]);

    return $requete->rowCount() === 1 ? null : 'Avis introuvable.';
}

/** Masquer (abusif) ou réafficher un avis — administrateur, motif journalisé. */
function moderer_avis(array $admin, int $avisId, bool $visible, string $motif): ?string
{
    $requete = bdd()->prepare('UPDATE avis SET visible = :visible, motif_masquage = :motif WHERE id = :id');
    $requete->execute(['visible' => $visible ? 1 : 0, 'motif' => $visible ? null : $motif, 'id' => $avisId]);
    if ($requete->rowCount() !== 1) {
        return 'Aucun changement.';
    }

    journaliser_action($visible ? 'admin_avis_reaffiche' : 'admin_avis_masque', (int) $admin['id'], 'avis', $avisId, null, $motif);

    return null;
}

/** Avis visibles d'une boutique (page publique), les plus récents d'abord. */
function avis_de_boutique(int $boutiqueId, int $limite = 10): array
{
    $requete = bdd()->prepare(
        'SELECT a.*, u.prenom, LEFT(u.nom, 1) AS initiale
         FROM avis a JOIN utilisateurs u ON u.id = a.client_id
         WHERE a.boutique_id = :id AND a.visible = 1 ORDER BY a.cree_le DESC LIMIT ' . max(1, $limite)
    );
    $requete->execute(['id' => $boutiqueId]);

    return $requete->fetchAll();
}

/**
 * Répartition des notes visibles d'une boutique : [5 => n, 4 => n, …, 1 => n].
 *
 * @return array<int, int>
 */
function repartition_notes(int $boutiqueId): array
{
    $requete = bdd()->prepare('SELECT note, COUNT(*) FROM avis WHERE boutique_id = :id AND visible = 1 GROUP BY note');
    $requete->execute(['id' => $boutiqueId]);
    $comptes = $requete->fetchAll(PDO::FETCH_KEY_PAIR);

    $repartition = [];
    for ($n = 5; $n >= 1; $n--) {
        $repartition[$n] = (int) ($comptes[$n] ?? 0);
    }

    return $repartition;
}

/** Étoiles accessibles : « ★★★★☆ » avec le texte « 4 sur 5 » pour les lecteurs d'écran. */
function etoiles(int $note): string
{
    return '<span class="sm-etoiles" role="img" aria-label="' . e($note) . ' sur 5">'
        . str_repeat('<i class="bi bi-star-fill" aria-hidden="true"></i>', $note)
        . str_repeat('<i class="bi bi-star" aria-hidden="true"></i>', 5 - $note) . '</span>';
}
