<?php
/**
 * SAMA MAILLOT — codes de vérification SMS et liens de réinitialisation par e-mail (§8.5, §24).
 *
 * Règles :
 *   - code SMS à 6 chiffres, valable 10 minutes, 3 essais maximum ;
 *   - 3 demandes de code maximum par heure et par numéro (coût des SMS, abus) ;
 *   - lien e-mail à usage unique, valable 1 heure ;
 *   - codes et jetons stockés uniquement sous forme hachée.
 */

declare(strict_types=1);

const CODE_SMS_DUREE_MINUTES = 10;
const CODE_SMS_ESSAIS_MAX = 3;
const CODE_SMS_DEMANDES_PAR_HEURE = 3;
const LIEN_EMAIL_DUREE_MINUTES = 60;

/** Vrai si le numéro a déjà reçu 3 codes (tous types confondus) dans la dernière heure. */
function trop_de_codes_demandes(string $telephone): bool
{
    $requete = bdd()->prepare(
        "SELECT COUNT(*) FROM codes_verification
         WHERE destination = :destination
           AND type IN ('verification_telephone', 'reinitialisation_sms')
           AND cree_le > NOW() - INTERVAL 1 HOUR"
    );
    $requete->execute(['destination' => $telephone]);

    return (int) $requete->fetchColumn() >= CODE_SMS_DEMANDES_PAR_HEURE;
}

/**
 * Crée un code SMS à 6 chiffres et renvoie le code en clair (à envoyer par SMS),
 * ou null si la limite de 3 demandes par heure est atteinte.
 * Les anciens codes encore valides du même type pour ce numéro sont annulés.
 *
 * @param string $type 'verification_telephone' ou 'reinitialisation_sms'
 */
function creer_code_sms(string $type, string $telephone, ?int $utilisateurId): ?string
{
    if (trop_de_codes_demandes($telephone)) {
        return null;
    }

    // Un seul code valable à la fois : les précédents ne fonctionnent plus.
    bdd()->prepare(
        'UPDATE codes_verification SET expire_le = NOW()
         WHERE destination = :destination AND type = :type AND utilise_le IS NULL AND expire_le > NOW()'
    )->execute(['destination' => $telephone, 'type' => $type]);

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    bdd()->prepare(
        'INSERT INTO codes_verification (utilisateur_id, type, destination, code_hache, expire_le)
         VALUES (:utilisateur_id, :type, :destination, :code_hache, NOW() + INTERVAL ' . CODE_SMS_DUREE_MINUTES . ' MINUTE)'
    )->execute([
        'utilisateur_id' => $utilisateurId,
        'type' => $type,
        'destination' => $telephone,
        'code_hache' => password_hash($code, PASSWORD_DEFAULT),
    ]);

    return $code;
}

/**
 * Vérifie un code SMS saisi.
 *
 * @return array{ok: bool, message: string, utilisateur_id: int|null}
 */
function verifier_code_sms(string $type, string $telephone, string $saisie): array
{
    $echec = ['ok' => false, 'message' => 'Code incorrect ou expiré. Demandez un nouveau code.', 'utilisateur_id' => null];

    $requete = bdd()->prepare(
        'SELECT id, utilisateur_id, code_hache, essais FROM codes_verification
         WHERE destination = :destination AND type = :type
           AND utilise_le IS NULL AND expire_le > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $requete->execute(['destination' => $telephone, 'type' => $type]);
    $code = $requete->fetch();

    if ($code === false || (int) $code['essais'] >= CODE_SMS_ESSAIS_MAX) {
        return $echec;
    }

    // L'essai est compté AVANT la comparaison : impossible de dépasser 3 essais.
    bdd()->prepare('UPDATE codes_verification SET essais = essais + 1 WHERE id = :id')
        ->execute(['id' => $code['id']]);

    $saisie = preg_replace('/\D+/', '', $saisie) ?? '';

    if (!password_verify($saisie, $code['code_hache'])) {
        $restants = CODE_SMS_ESSAIS_MAX - ((int) $code['essais'] + 1);

        if ($restants > 0) {
            $echec['message'] = 'Code incorrect. Il vous reste ' . $restants . ' essai' . ($restants > 1 ? 's' : '') . '.';
        }

        return $echec;
    }

    // Code correct : il ne pourra plus jamais servir.
    bdd()->prepare('UPDATE codes_verification SET utilise_le = NOW() WHERE id = :id')
        ->execute(['id' => $code['id']]);

    return [
        'ok' => true,
        'message' => '',
        'utilisateur_id' => $code['utilisateur_id'] !== null ? (int) $code['utilisateur_id'] : null,
    ];
}

/**
 * Crée un lien de réinitialisation par e-mail (usage unique, 1 heure).
 * Le jeton en clair n'existe que dans le lien envoyé ; la base n'en garde que l'empreinte.
 *
 * @return string adresse complète du lien, ou chaîne vide si la limite de 3 liens par heure est atteinte
 */
function creer_lien_reinitialisation(int $utilisateurId, string $email): string
{
    // Anti-inondation : au plus 3 liens par compte et par heure. Au-delà, aucun e-mail ne part
    // (l'appelant affiche le même message neutre : rien n'est révélé au demandeur).
    if (trop_de_liens_demandes($utilisateurId)) {
        return '';
    }

    // Les anciens liens de ce compte ne fonctionnent plus.
    bdd()->prepare(
        "UPDATE codes_verification SET expire_le = NOW()
         WHERE utilisateur_id = :id AND type = 'reinitialisation_email' AND utilise_le IS NULL AND expire_le > NOW()"
    )->execute(['id' => $utilisateurId]);

    $jeton = bin2hex(random_bytes(32));

    bdd()->prepare(
        "INSERT INTO codes_verification (utilisateur_id, type, destination, code_hache, expire_le)
         VALUES (:utilisateur_id, 'reinitialisation_email', :destination, :code_hache,
                 NOW() + INTERVAL " . LIEN_EMAIL_DUREE_MINUTES . ' MINUTE)'
    )->execute([
        'utilisateur_id' => $utilisateurId,
        'destination' => $email,
        'code_hache' => hash('sha256', $jeton),
    ]);

    $id = (int) bdd()->lastInsertId();

    return url('mot-de-passe/reinitialiser.php?id=' . $id . '&jeton=' . $jeton);
}

/** Vrai si ce compte a déjà reçu 3 liens de réinitialisation par e-mail dans la dernière heure. */
function trop_de_liens_demandes(int $utilisateurId): bool
{
    $requete = bdd()->prepare(
        "SELECT COUNT(*) FROM codes_verification
         WHERE utilisateur_id = :id AND type = 'reinitialisation_email' AND cree_le > NOW() - INTERVAL 1 HOUR"
    );
    $requete->execute(['id' => $utilisateurId]);

    return (int) $requete->fetchColumn() >= 3;
}

/**
 * Vérifie un lien de réinitialisation. Renvoie l'id de l'utilisateur, ou null si le lien
 * est inconnu, expiré ou déjà utilisé.
 */
function verifier_lien_reinitialisation(int $id, string $jeton): ?int
{
    if ($id <= 0 || preg_match('/^[a-f0-9]{64}$/', $jeton) !== 1) {
        return null;
    }

    $requete = bdd()->prepare(
        "SELECT utilisateur_id, code_hache FROM codes_verification
         WHERE id = :id AND type = 'reinitialisation_email' AND utilise_le IS NULL AND expire_le > NOW()"
    );
    $requete->execute(['id' => $id]);
    $ligne = $requete->fetch();

    if ($ligne === false || !hash_equals($ligne['code_hache'], hash('sha256', $jeton))) {
        return null;
    }

    return (int) $ligne['utilisateur_id'];
}

/** Marque un lien de réinitialisation comme utilisé (il ne fonctionnera plus). */
function utiliser_lien_reinitialisation(int $id): void
{
    bdd()->prepare('UPDATE codes_verification SET utilise_le = NOW() WHERE id = :id')->execute(['id' => $id]);
}
