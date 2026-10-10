<?php
/**
 * SAMA MAILLOT — commander sans créer de compte (décision du porteur du projet).
 *
 * Le visiteur donne son nom complet, son téléphone, son adresse et, s'il le veut, son e-mail.
 *   - Un « compte invité » est créé pour rattacher la commande (est_invite = 1, sans mot de passe :
 *     personne ne peut s'y connecter). Avec un e-mail déjà utilisé par un compte invité, ce compte
 *     est réutilisé ; avec l'e-mail d'un VRAI compte, le visiteur est invité à se connecter.
 *   - Le suivi se fait par un lien secret (jeton de 64 caractères, chiffré en base pour pouvoir
 *     le renvoyer par e-mail) : commande/suivi.php?c=<id>&j=<jeton>.
 *   - Anti-abus : 3 commandes à la livraison en cours par numéro de téléphone (comme un compte),
 *     refus à la livraison comptés par numéro, et au plus 5 commandes sans compte par heure et par
 *     adresse IP.
 *   - Pour noter le vendeur ou ouvrir une réclamation, il faut un compte : le visiteur crée son mot
 *     de passe avec « Mot de passe oublié » (l'e-mail prouve qu'il en est le propriétaire), son
 *     compte invité devient alors un compte normal avec ses commandes.
 */

declare(strict_types=1);

const INVITE_COMMANDES_PAR_HEURE = 5;

/** Vrai pour une adresse technique (compte invité sans e-mail, compte supprimé) : aucun e-mail n'y part. */
function email_technique(?string $email): bool
{
    return $email === null || $email === '' || str_ends_with($email, '.invalid');
}

/** Trop de commandes sans compte depuis cette adresse IP dans la dernière heure ? */
function trop_de_commandes_invite(): bool
{
    $requete = bdd()->prepare(
        "SELECT COUNT(*) FROM journal_actions j JOIN utilisateurs u ON u.id = j.utilisateur_id
         WHERE j.action = 'commande_creee' AND u.est_invite = 1 AND j.adresse_ip = :ip AND j.cree_le > NOW() - INTERVAL 1 HOUR"
    );
    $requete->execute(['ip' => adresse_ip()]);

    return (int) $requete->fetchColumn() >= INVITE_COMMANDES_PAR_HEURE;
}

/**
 * Lit et contrôle le nom complet et l'e-mail (facultatif) saisis à la commande.
 *
 * @return array{0: array{nom_complet: string, email: string}, 1: array<string, string>} [valeurs, erreurs]
 */
function lire_identite_invite(): array
{
    $valeurs = ['nom_complet' => trim(preg_replace('/\s+/u', ' ', champ_post('nom_complet')) ?? ''), 'email' => trim(champ_post('email'))];
    $erreurs = [];

    if ($valeurs['nom_complet'] === '') {
        $erreurs['nom_complet'] = 'Indiquez votre nom complet : le vendeur en a besoin pour la livraison.';
    } elseif (mb_strlen($valeurs['nom_complet']) > 100 || preg_match("/^[\p{L}][\p{L} '’-]*$/u", $valeurs['nom_complet']) !== 1) {
        $erreurs['nom_complet'] = 'Le nom ne peut contenir que des lettres, des espaces, des tirets et des apostrophes (100 caractères maximum).';
    } elseif (!str_contains($valeurs['nom_complet'], ' ')) {
        $erreurs['nom_complet'] = 'Indiquez votre prénom et votre nom.';
    }

    if ($valeurs['email'] !== '') {
        $email = normaliser_email($valeurs['email']);
        if ($email === null) {
            $erreurs['email'] = 'Adresse e-mail invalide (ou laissez vide).';
        } else {
            $valeurs['email'] = $email;
            $requete = bdd()->prepare('SELECT est_invite FROM utilisateurs WHERE email = :email AND supprime_le IS NULL');
            $requete->execute(['email' => $email]);
            $existant = $requete->fetchColumn();
            if ($existant !== false && (int) $existant === 0) {
                $erreurs['email'] = 'Un compte existe déjà avec cet e-mail : connectez-vous pour suivre vos commandes dans votre compte.';
            }
        }
    }

    return [$valeurs, $erreurs];
}

/**
 * Compte invité pour une commande sans compte : réutilisé si l'e-mail appartient déjà à un compte
 * invité, sinon créé (e-mail technique en « .invalid » quand aucun e-mail n'est donné).
 */
function compte_invite(string $nomComplet, string $email): array
{
    [$prenom, $nom] = array_pad(explode(' ', $nomComplet, 2), 2, '');

    if ($email !== '') {
        $requete = bdd()->prepare("SELECT id FROM utilisateurs WHERE email = :email AND est_invite = 1 AND supprime_le IS NULL");
        $requete->execute(['email' => $email]);
        $id = (int) $requete->fetchColumn();
        if ($id > 0) {
            bdd()->prepare('UPDATE utilisateurs SET prenom = :prenom, nom = :nom WHERE id = :id')->execute(['prenom' => $prenom, 'nom' => $nom, 'id' => $id]);

            return trouver_utilisateur($id);
        }
    }

    bdd()->prepare(
        "INSERT INTO utilisateurs (role, est_invite, prenom, nom, email, statut, cgu_acceptees_le)
         VALUES ('client', 1, :prenom, :nom, :email, 'actif', NOW())"
    )->execute([
        'prenom' => mb_substr($prenom, 0, 50), 'nom' => mb_substr($nom, 0, 50),
        'email' => $email !== '' ? $email : 'invite-' . bin2hex(random_bytes(10)) . '@invite.invalid',
    ]);

    return trouver_utilisateur((int) bdd()->lastInsertId());
}

/** Nouveau jeton de suivi (64 caractères hexadécimaux), commun aux commandes d'un même panier. */
function nouveau_jeton_suivi(): string
{
    return bin2hex(random_bytes(32));
}

/** Chemin de la page d'une commande pour son client : compte, ou lien secret pour une commande sans compte. */
function lien_commande_client(array $commande): string
{
    if (!empty($commande['jeton_suivi_chiffre'])) {
        $jeton = dechiffrer((string) $commande['jeton_suivi_chiffre']);
        if ($jeton !== null) {
            return 'commande/suivi.php?c=' . (int) $commande['id'] . '&j=' . $jeton;
        }
    }

    return 'compte/commande.php?id=' . (int) $commande['id'];
}

/** Commande sans compte ouverte par son lien secret (contrôle du jeton à temps constant), ou null. */
function commande_par_suivi(int $id, string $jeton): ?array
{
    if ($id <= 0 || preg_match('/^[a-f0-9]{64}$/', $jeton) !== 1) {
        return null;
    }

    $requete = bdd()->prepare(SQL_COMMANDE . ' WHERE c.id = :id AND c.jeton_suivi_chiffre IS NOT NULL');
    $requete->execute(['id' => $id]);
    $commande = $requete->fetch();
    if ($commande === false) {
        return null;
    }

    $attendu = dechiffrer((string) $commande['jeton_suivi_chiffre']);

    return $attendu !== null && hash_equals($attendu, $jeton) ? $commande : null;
}

/** Autres commandes du même panier (même compte invité, même jeton) : une par boutique. */
function commandes_du_meme_panier(array $commande, string $jeton): array
{
    $requete = bdd()->prepare(SQL_COMMANDE . ' WHERE c.client_id = :client AND c.id <> :id AND c.jeton_suivi_chiffre IS NOT NULL ORDER BY c.id');
    $requete->execute(['client' => $commande['client_id'], 'id' => $commande['id']]);

    return array_values(array_filter($requete->fetchAll(), static function (array $autre) use ($jeton): bool {
        $attendu = dechiffrer((string) $autre['jeton_suivi_chiffre']);

        return $attendu !== null && hash_equals($attendu, $jeton);
    }));
}
