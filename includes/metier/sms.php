<?php
/**
 * SAMA MAILLOT — envoi des SMS (§22) avec file d'attente (table envois_sms).
 *
 * - Chaque SMS est enregistré (suivi des coûts, ~15 à 25 FCFA l'unité).
 * - Urgent (code, commande…) : tentative d'envoi immédiate. Non urgent (rappels) : jamais entre
 *   22h et 7h, programmé à 7h. La tâche taches/envoyer_sms.php envoie la file et réessaie.
 * - Le texte est effacé après l'envoi : il peut contenir un code (§31). Jamais dans les journaux.
 *
 * Fournisseur (fichier .env) :
 *   SMS_FOURNISSEUR=          → aucun : SMS SIMULÉ en local (affiché à l'écran), échec en production
 *   SMS_FOURNISSEUR=orange    → Orange SMS API (ORANGE_AUTORISATION, ORANGE_EXPEDITEUR)
 *     ⚠ Connecteur écrit d'après la documentation Orange, à tester avec un vrai compte.
 */

declare(strict_types=1);

const SMS_ESSAIS_MAX = 5;

/** Heure de programmation d'un SMS non urgent : maintenant, ou 7h si on est entre 22h et 7h. */
function heure_envoi_sms(bool $urgent): string
{
    $heure = (int) date('G');
    if ($urgent || ($heure >= 7 && $heure < 22)) {
        return date('Y-m-d H:i:s');
    }

    return date('Y-m-d 07:00:00', $heure >= 22 ? strtotime('+1 day') : time());
}

/**
 * Envoie (ou met en file) un SMS vers un numéro +221XXXXXXXXX.
 *
 * $afficherEnLocal = false : SMS destiné à une AUTRE personne que celle qui navigue (vendeur
 * prévenu d'une commande, tâche automatique…). En local, il n'est alors pas affiché à l'écran.
 * $urgent = false : rappel (abonnement…) : jamais envoyé entre 22h et 7h.
 *
 * @return bool true si le SMS est parti, simulé ou bien mis en file
 */
function envoyer_sms(string $telephone, string $message, bool $afficherEnLocal = true, bool $urgent = true): bool
{
    // SMS désactivés (SMS_ACTIF=0) : rien n'est envoyé ni mis en file. Les mêmes informations
    // partent par e-mail, en copie des notifications (metier/notifications.php).
    if (!SMS_ACTIF) {
        return true;
    }

    $message = mb_substr($message, 0, 480);
    bdd()->prepare(
        'INSERT INTO envois_sms (telephone, message, urgent, programme_le) VALUES (:telephone, :message, :urgent, :programme)'
    )->execute(['telephone' => $telephone, 'message' => $message, 'urgent' => $urgent ? 1 : 0, 'programme' => heure_envoi_sms($urgent)]);
    $id = (int) bdd()->lastInsertId();

    // Aucun fournisseur en local : simulation (affichée si le SMS est pour la personne qui navigue).
    if (fournisseur_sms() === '' && !EN_PRODUCTION) {
        bdd()->prepare("UPDATE envois_sms SET statut = 'simule', message = '', envoye_le = NOW() WHERE id = :id")->execute(['id' => $id]);
        if ($afficherEnLocal && session_status() === PHP_SESSION_ACTIVE) {
            message_flash('dev', 'SMS simulé pour ' . formater_telephone($telephone) . ' : « ' . $message . ' »');
            // Gardé en session 10 minutes (durée d'un code) pour rester visible sur la page de saisie.
            $_SESSION['sms_simules'][$telephone] = ['message' => $message, 'expire' => time() + 600];
        }

        return true;
    }

    return !$urgent || traiter_sms($id);
}

/** Fournisseur configuré dans .env ('' si aucun). */
function fournisseur_sms(): string
{
    return strtolower(trim((string) env('SMS_FOURNISSEUR', '')));
}

/** Envoie un SMS de la file. Vrai si parti ; sinon nouvel essai plus tard (5 au maximum). */
function traiter_sms(int $id): bool
{
    $requete = bdd()->prepare("SELECT * FROM envois_sms WHERE id = :id AND statut = 'en_attente'");
    $requete->execute(['id' => $id]);
    $sms = $requete->fetch();
    if ($sms === false) {
        return false;
    }

    [$ok, $erreur] = match (fournisseur_sms()) {
        'orange' => envoyer_sms_orange($sms['telephone'], $sms['message']),
        default => [false, 'Aucun fournisseur SMS configuré (SMS_FOURNISSEUR dans .env).'],
    };

    if ($ok) {
        bdd()->prepare("UPDATE envois_sms SET statut = 'envoye', message = '', essais = essais + 1, erreur = NULL, envoye_le = NOW() WHERE id = :id")
            ->execute(['id' => $id]);

        return true;
    }

    $essais = (int) $sms['essais'] + 1;
    $fin = $essais >= SMS_ESSAIS_MAX;
    bdd()->prepare(
        'UPDATE envois_sms SET essais = :essais, erreur = :erreur, statut = :statut,
                               message = ' . ($fin ? "''" : 'message') . ', programme_le = NOW() + INTERVAL ' . (2 ** $essais) . ' MINUTE
         WHERE id = :id'
    )->execute(['essais' => $essais, 'erreur' => mb_substr((string) $erreur, 0, 255), 'statut' => $fin ? 'echec' : 'en_attente', 'id' => $id]);
    error_log('SMS n° ' . $id . ' non envoyé à ' . masquer_telephone($sms['telephone']) . ' (essai ' . $essais . ') : ' . $erreur);

    return false;
}

/** Tâche planifiée : envoie les SMS programmés (§27.3). Renvoie [envoyés, en échec]. */
function traiter_file_sms(int $limite = 50): array
{
    $ids = bdd()->query(
        "SELECT id FROM envois_sms WHERE statut = 'en_attente' AND programme_le <= NOW() ORDER BY urgent DESC, programme_le, id LIMIT " . max(1, $limite)
    )->fetchAll(PDO::FETCH_COLUMN);

    $envoyes = 0;
    foreach ($ids as $id) {
        $envoyes += traiter_sms((int) $id) ? 1 : 0;
    }

    return [$envoyes, count($ids) - $envoyes];
}

/**
 * Orange SMS API (Sénégal). .env : ORANGE_AUTORISATION (en-tête « Basic … » fourni par Orange)
 * et ORANGE_EXPEDITEUR (numéro +221… du compte). Jeton OAuth gardé 50 minutes dans stockage/.
 * ⚠ À tester avec un vrai compte Orange Developer avant la mise en production.
 *
 * @return array{0: bool, 1: ?string}
 */
function envoyer_sms_orange(string $telephone, string $message): array
{
    $autorisation = (string) env('ORANGE_AUTORISATION', '');
    $expediteur = (string) env('ORANGE_EXPEDITEUR', '');
    if ($autorisation === '' || $expediteur === '') {
        return [false, 'ORANGE_AUTORISATION ou ORANGE_EXPEDITEUR manquant dans .env.'];
    }

    $cache = DOSSIER_RACINE . '/stockage/orange_jeton.json';
    $jeton = is_file($cache) ? json_decode((string) file_get_contents($cache), true) : null;

    if (!is_array($jeton) || ($jeton['expire'] ?? 0) < time()) {
        [$code, $reponse] = requete_http('https://api.orange.com/oauth/v3/token',
            ['Authorization: ' . $autorisation, 'Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            'grant_type=client_credentials');
        $donnees = json_decode((string) $reponse, true);
        if ($code !== 200 || empty($donnees['access_token'])) {
            return [false, 'Jeton Orange refusé (HTTP ' . $code . ').'];
        }
        $jeton = ['valeur' => $donnees['access_token'], 'expire' => time() + 3000];
        file_put_contents($cache, json_encode($jeton), LOCK_EX);
    }

    $corps = json_encode(['outboundSMSMessageRequest' => [
        'address' => 'tel:' . $telephone,
        'senderAddress' => 'tel:' . $expediteur,
        'senderName' => NOM_SITE,
        'outboundSMSTextMessage' => ['message' => $message],
    ]]);
    [$code] = requete_http('https://api.orange.com/smsmessaging/v1/outbound/' . rawurlencode('tel:' . $expediteur) . '/requests',
        ['Authorization: Bearer ' . $jeton['valeur'], 'Content-Type: application/json'], (string) $corps);

    return $code === 201 ? [true, null] : [false, 'Orange a refusé l\'envoi (HTTP ' . $code . ').'];
}

/** Requête HTTPS POST (cURL). @return array{0: int, 1: string|false} [code HTTP, réponse] */
function requete_http(string $adresse, array $entetes, string $corps): array
{
    $curl = curl_init($adresse);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $corps,
        CURLOPT_HTTPHEADER => $entetes,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $reponse = curl_exec($curl);
    $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    return [$code, $reponse];
}

/** En local uniquement : dernier SMS simulé pour ce numéro, s'il n'a pas expiré (sinon null). */
function dernier_sms_simule(?string $telephone): ?string
{
    $sms = $telephone !== null ? ($_SESSION['sms_simules'][$telephone] ?? null) : null;

    if (EN_PRODUCTION || !is_array($sms) || $sms['expire'] < time()) {
        return null;
    }

    return $sms['message'];
}

/** Encadré « Mode développement » rappelant le dernier SMS simulé (chaîne vide sinon). */
function rappel_sms_simule(?string $telephone): string
{
    $message = dernier_sms_simule($telephone);

    if ($message === null) {
        return '';
    }

    return '<div class="alert sm-alerte-dev small" role="note">'
        . '<i class="bi bi-cone-striped" aria-hidden="true"></i> '
        . '<strong>Mode développement</strong> — aucun vrai SMS n\'est envoyé en local. Dernier SMS simulé : « '
        . e($message) . ' »</div>';
}
