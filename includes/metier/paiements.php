<?php
/**
 * SAMA MAILLOT — paiement en ligne via un agrégateur (§13.3, §13.4).
 *
 * Fournisseur choisi dans .env : PAIEMENT_FOURNISSEUR=simulateur | (paytech, paydunya… à brancher).
 * Vide = paiement en ligne « bientôt » (seul le paiement à la livraison est proposé).
 *
 * Règles de sécurité (§13.4) :
 *   - la redirection du navigateur n'est JAMAIS une preuve : le statut vient d'une notification
 *     signée (vérifiée) de l'agrégateur, PUIS d'un appel à son API (paiement_consulter) ;
 *   - référence unique par transaction ; une notification reçue deux fois n'est traitée qu'une fois
 *     (verrou de ligne + traitee_le) ;
 *   - le montant payé est comparé au montant attendu : une différence bloque les commandes et
 *     alerte l'administration ;
 *   - statuts : initie, reussi, echoue, annule, expire, inconnu (revérifié par tâche planifiée) ;
 *   - chaque événement est journalisé (evenements_paiement), sans donnée sensible.
 *
 * Un fournisseur = 3 fonctions : paiement_initier_X(), paiement_notification_X(), paiement_consulter_X().
 *
 * SIMULATEUR DE TEST : imite un agrégateur (page de paiement, notification signée, API de
 * consultation). Clairement marqué « TEST », il est REFUSÉ en production : jamais de faux paiement
 * présenté comme réel (§13.3).
 */

declare(strict_types=1);

const FOURNISSEURS_PAIEMENT = ['simulateur'];

/**
 * Domaines des pages de paiement des agrégateurs, autorisés dans la CSP « form-action » : le navigateur
 * y est redirigé juste après le formulaire de commande. À compléter en branchant un agrégateur réel
 * (ex. ' https://paytech.sn'). Le simulateur est sur le site lui-même : rien à ajouter.
 */
const DOMAINES_PAIEMENT_CSP = '';

const STATUTS_TRANSACTION = [
    'initie' => ['En cours', 'attention'],
    'reussi' => ['Réussi', 'succes'],
    'echoue' => ['Échoué', 'erreur'],
    'annule' => ['Annulé', 'erreur'],
    'expire' => ['Expiré', 'erreur'],
    'inconnu' => ['À vérifier', 'attention'],
    'montant_incorrect' => ['Montant incorrect', 'erreur'],
];

const MOYENS_PAIEMENT = [
    'wave' => 'Wave',
    'orange_money' => 'Orange Money',
    'free_money' => 'Free Money',
    'carte' => 'Carte bancaire',
];

/** Fournisseur configuré, ou '' si le paiement en ligne est désactivé. */
function fournisseur_paiement(): string
{
    $fournisseur = strtolower(trim((string) env('PAIEMENT_FOURNISSEUR', '')));

    if (!in_array($fournisseur, FOURNISSEURS_PAIEMENT, true)) {
        return '';
    }
    // Le simulateur n'existe jamais en production (autorisé en local et en recette, §33.1).
    if ($fournisseur === 'simulateur' && APP_ENV === 'production') {
        return '';
    }

    return $fournisseur;
}

function paiement_en_ligne_actif(): bool
{
    return fournisseur_paiement() !== '';
}

function paiement_mode_test(): bool
{
    return fournisseur_paiement() === 'simulateur';
}

function journaliser_paiement(?int $transactionId, string $type, string $detail = ''): void
{
    bdd()->prepare('INSERT INTO evenements_paiement (transaction_id, type, detail) VALUES (:transaction, :type, :detail)')
        ->execute(['transaction' => $transactionId, 'type' => $type, 'detail' => mb_substr($detail, 0, 255)]);
}

function transaction_par_reference(string $reference): ?array
{
    $requete = bdd()->prepare('SELECT * FROM transactions_paiement WHERE reference = :reference');
    $requete->execute(['reference' => $reference]);
    $transaction = $requete->fetch();

    return $transaction === false ? null : $transaction;
}

/** Commandes couvertes par un paiement. */
function commandes_de_la_transaction(int $transactionId): array
{
    $requete = bdd()->prepare(SQL_COMMANDE . ' WHERE c.transaction_id = :id ORDER BY c.id');
    $requete->execute(['id' => $transactionId]);

    return $requete->fetchAll();
}

/* -------------------------------------------------------------------------
   Initiation
   ------------------------------------------------------------------------- */

/**
 * Crée le paiement pour les commandes « en attente de paiement » qui viennent d'être créées,
 * puis demande à l'agrégateur l'adresse de sa page de paiement.
 *
 * @param list<array{id: int, total: int}> $commandes
 * @return string adresse vers laquelle rediriger le client
 */
function creer_paiement(array $client, array $commandes): string
{
    $fournisseur = fournisseur_paiement();
    if ($fournisseur === '') {
        throw new ErreurCommande('Le paiement en ligne n\'est pas disponible pour le moment.');
    }

    $montant = array_sum(array_column($commandes, 'total'));
    $minutes = max(5, (int) parametre('paiement_en_ligne_minutes', '30'));
    $reference = 'PAY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));

    $pdo = bdd();
    $pdo->prepare(
        "INSERT INTO transactions_paiement (reference, fournisseur, client_id, montant, expire_le)
         VALUES (:reference, :fournisseur, :client, :montant, NOW() + INTERVAL $minutes MINUTE)"
    )->execute(['reference' => $reference, 'fournisseur' => $fournisseur, 'client' => $client['id'], 'montant' => $montant]);
    $transactionId = (int) $pdo->lastInsertId();

    $lien = $pdo->prepare("UPDATE commandes SET transaction_id = :transaction WHERE id = :id AND statut = 'attente_paiement'");
    foreach ($commandes as $commande) {
        $lien->execute(['transaction' => $transactionId, 'id' => $commande['id']]);
    }

    $transaction = transaction_par_reference($reference);
    $adresse = ('paiement_initier_' . $fournisseur)($transaction);
    $pdo->prepare('UPDATE transactions_paiement SET url_paiement = :url WHERE id = :id')->execute(['url' => $adresse, 'id' => $transactionId]);
    journaliser_paiement($transactionId, 'initiation', $fournisseur . ' · ' . $montant . ' FCFA');

    return $adresse;
}

/** Envoie le navigateur vers la page de paiement de l'agrégateur (adresse fournie par lui, en https). */
function aller_vers_paiement(string $adresse): never
{
    if (preg_match('#^https://#i', $adresse) !== 1 && !str_starts_with($adresse, APP_URL . '/')) {
        throw new RuntimeException('Adresse de paiement refusée.');
    }
    header('Location: ' . $adresse, true, 303);
    exit;
}

/** Adresse de retour du client après la page de l'agrégateur (n'est pas une preuve de paiement). */
function url_retour_paiement(string $reference): string
{
    return url('paiement/retour.php?ref=' . rawurlencode($reference));
}

/** Adresse de notification serveur à serveur (webhook). */
function url_notification_paiement(string $fournisseur): string
{
    return url('webhooks/paiement.php?fournisseur=' . rawurlencode($fournisseur));
}

/* -------------------------------------------------------------------------
   Vérification et application du résultat
   ------------------------------------------------------------------------- */

/**
 * Notification reçue de l'agrégateur (webhook). Vérifie la signature, puis contrôle le statut par
 * l'API du fournisseur avant d'agir. Renvoie le code HTTP à répondre.
 */
function recevoir_notification_paiement(string $fournisseur, string $corps, array $entetes): int
{
    if ($fournisseur === '' || $fournisseur !== fournisseur_paiement()) {
        return 404;
    }

    $reference = ('paiement_notification_' . $fournisseur)($corps, $entetes);
    if ($reference === null) {
        journaliser_paiement(null, 'signature_invalide', $fournisseur);

        return 403;
    }

    $transaction = transaction_par_reference($reference);
    if ($transaction === null) {
        journaliser_paiement(null, 'reference_inconnue', mb_substr($reference, 0, 60));

        return 404;
    }

    journaliser_paiement((int) $transaction['id'], 'notification', $fournisseur);
    verifier_paiement($transaction);

    return 200;
}

/** Interroge l'API du fournisseur et applique le résultat (idempotent). Renvoie le statut final connu. */
function verifier_paiement(array $transaction): string
{
    try {
        $resultat = ('paiement_consulter_' . $transaction['fournisseur'])($transaction);
    } catch (Throwable $exception) {
        journaliser_paiement((int) $transaction['id'], 'verification_impossible', $exception->getMessage());
        $resultat = ['statut' => 'inconnu'];
    }
    journaliser_paiement((int) $transaction['id'], 'verification', (string) $resultat['statut']);

    return appliquer_resultat_paiement((int) $transaction['id'], $resultat);
}

/**
 * Applique le résultat vérifié UNE SEULE FOIS : verrou de la ligne, puis traitee_le.
 *
 * @param array{statut: string, montant?: int, moyen?: ?string, reference_fournisseur?: ?string} $resultat
 */
function appliquer_resultat_paiement(int $transactionId, array $resultat): string
{
    $pdo = bdd();
    $pdo->beginTransaction();
    $lecture = $pdo->prepare('SELECT * FROM transactions_paiement WHERE id = :id FOR UPDATE');
    $lecture->execute(['id' => $transactionId]);
    $transaction = $lecture->fetch();

    if ($transaction === false || $transaction['traitee_le'] !== null) {
        $pdo->commit();

        return $transaction['statut'] ?? 'inconnu';   // déjà traitée : notification en double ignorée
    }

    $statut = $resultat['statut'];
    $final = in_array($statut, ['reussi', 'echoue', 'annule', 'expire'], true);
    $montantRecu = isset($resultat['montant']) ? (int) $resultat['montant'] : null;

    if ($statut === 'reussi' && $montantRecu !== (int) $transaction['montant']) {
        $statut = 'montant_incorrect';   // §13.4 : bloque les commandes et alerte l'administration
    }

    $pdo->prepare(
        'UPDATE transactions_paiement SET statut = :statut, montant_recu = :recu, moyen = COALESCE(:moyen, moyen),
                reference_fournisseur = COALESCE(:ref, reference_fournisseur), traitee_le = :traitee WHERE id = :id'
    )->execute([
        'statut' => $statut, 'recu' => $montantRecu, 'moyen' => $resultat['moyen'] ?? null,
        'ref' => $resultat['reference_fournisseur'] ?? null,
        'traitee' => $final || $statut === 'montant_incorrect' ? date('Y-m-d H:i:s') : null, 'id' => $transactionId,
    ]);
    $pdo->commit();

    if ($statut === 'reussi') {
        confirmer_commandes_payees($transaction);
    } elseif (in_array($statut, ['echoue', 'annule', 'expire'], true)) {
        $motifs = ['echoue' => 'Paiement refusé', 'annule' => 'Paiement annulé', 'expire' => 'Paiement non effectué à temps'];
        foreach (commandes_de_la_transaction($transactionId) as $commande) {
            try {
                changer_statut_commande((int) $commande['id'], 'annulee', 'systeme', null, $motifs[$statut]);
            } catch (ErreurCommande) {
                // Déjà annulée par le client entre-temps.
            }
        }
    } elseif ($statut === 'montant_incorrect') {
        journaliser_action('paiement_montant_incorrect', null, 'transaction', $transactionId, (string) $transaction['montant'], (string) $montantRecu);
        notifier_admins('paiement_anomalie', 'Paiement à vérifier : montant incorrect',
            'Paiement ' . $transaction['reference'] . ' : ' . format_fcfa((int) $montantRecu) . ' reçus pour '
            . format_fcfa((int) $transaction['montant']) . ' attendus. Commandes bloquées.', 'admin/paiements.php?onglet=transactions');
    }

    return $statut;
}

/** Paiement confirmé : chaque commande passe « à confirmer », le vendeur est prévenu, l'argent est « en attente ». */
function confirmer_commandes_payees(array $transaction): void
{
    foreach (commandes_de_la_transaction((int) $transaction['id']) as $commande) {
        if ($commande['statut'] !== 'attente_paiement') {
            continue;
        }
        bdd()->prepare('UPDATE commandes SET payee_le = NOW() WHERE id = :id AND payee_le IS NULL')->execute(['id' => $commande['id']]);
        try {
            changer_statut_commande((int) $commande['id'], 'attente_confirmation', 'systeme', null, 'Paiement en ligne reçu');
        } catch (ErreurCommande) {
            continue;
        }

        $numero = $commande['numero'];
        $vendeurId = (int) $commande['vendeur_id'];
        notifier($vendeurId, 'commande_nouvelle', "Nouvelle commande $numero (payée en ligne)",
            $commande['client_nom'] . ' a payé ' . format_fcfa((int) $commande['total']) . '. À confirmer sous '
            . max(1, (int) parametre('confirmation_vendeur_heures', '24')) . ' h. Demandez le code de livraison à la remise.',
            'vendeur/commande.php?id=' . (int) $commande['id']);
        notifier((int) $commande['client_id'], 'paiement_recu', "Paiement reçu : commande $numero",
            format_fcfa((int) $commande['total']) . ' payés en ligne. Votre code de livraison est dans la commande : donnez-le seulement à la réception.',
            'compte/commande.php?id=' . (int) $commande['id']);
    }
}

/** Tâche planifiée : paiements restés « en cours » au-delà du délai, ou « à vérifier ». */
function traiter_paiements_en_attente(): array
{
    $bilan = ['verifies' => 0, 'expires' => 0];
    $transactions = bdd()->query(
        "SELECT * FROM transactions_paiement WHERE traitee_le IS NULL
         AND ((statut = 'initie' AND expire_le <= NOW()) OR statut = 'inconnu') LIMIT 100"
    )->fetchAll();

    foreach ($transactions as $transaction) {
        $statut = verifier_paiement($transaction);
        $bilan['verifies']++;
        // Délai dépassé sans paiement (ou agrégateur toujours injoignable 1 h après) : expiré,
        // la commande est annulée et le stock revient.
        $expire = strtotime($transaction['expire_le']);
        if (($statut === 'initie' && $expire <= time()) || ($statut === 'inconnu' && $expire + 3600 <= time())) {
            appliquer_resultat_paiement((int) $transaction['id'], ['statut' => 'expire']);
            $bilan['expires']++;
        }
    }

    return $bilan;
}

/* -------------------------------------------------------------------------
   Fournisseur « simulateur » (développement et recette UNIQUEMENT)
   ------------------------------------------------------------------------- */

function secret_simulateur(): string
{
    return hash_hmac('sha256', 'simulateur-paiement', cle_chiffrement());
}

function paiement_initier_simulateur(array $transaction): string
{
    return url('paiement/simulateur.php?ref=' . rawurlencode($transaction['reference']));
}

/** Vérifie la signature HMAC de la notification ; renvoie la référence, ou null si invalide. */
function paiement_notification_simulateur(string $corps, array $entetes): ?string
{
    $signature = (string) ($entetes['x-signature'] ?? '');
    if ($signature === '' || !hash_equals(hash_hmac('sha256', $corps, secret_simulateur()), $signature)) {
        return null;
    }
    $donnees = json_decode($corps, true);

    return is_array($donnees) && is_string($donnees['reference'] ?? null) ? $donnees['reference'] : null;
}

/** « API » du simulateur : l'état choisi sur sa page de paiement. */
function paiement_consulter_simulateur(array $transaction): array
{
    $requete = bdd()->prepare('SELECT simulateur_etat, moyen, montant FROM transactions_paiement WHERE id = :id');
    $requete->execute(['id' => $transaction['id']]);
    $ligne = $requete->fetch();
    $etat = (string) ($ligne['simulateur_etat'] ?? '');

    return match ($etat) {
        'reussi' => ['statut' => 'reussi', 'montant' => (int) $ligne['montant'], 'moyen' => $ligne['moyen'], 'reference_fournisseur' => 'SIM-' . $transaction['id']],
        'montant_faux' => ['statut' => 'reussi', 'montant' => (int) $ligne['montant'] - 500, 'moyen' => $ligne['moyen'], 'reference_fournisseur' => 'SIM-' . $transaction['id']],
        'echoue', 'annule' => ['statut' => $etat],
        default => ['statut' => 'initie'],
    };
}

/** Le simulateur envoie sa notification signée au webhook du site, comme le ferait un vrai agrégateur. */
function simulateur_envoyer_notification(array $transaction): void
{
    $corps = json_encode(['reference' => $transaction['reference'], 'evenement' => 'paiement']);
    try {
        requete_http(url_notification_paiement('simulateur'), ['Content-Type: application/json', 'X-Signature: ' . hash_hmac('sha256', $corps, secret_simulateur())], $corps);
    } catch (Throwable $exception) {
        // Pas grave : la page de retour et la tâche planifiée vérifient aussi le statut.
        error_log('Simulateur de paiement : notification non envoyée (' . $exception->getMessage() . ')');
    }
}
