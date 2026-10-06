<?php
/**
 * SAMA MAILLOT — comptes connectés, connexion, déconnexion et contrôle d'accès (§8, §5.1, §24).
 *
 * Fonctions principales :
 *   utilisateur_connecte()        l'utilisateur de la session (ou null)
 *   exiger_connexion('vendeur')   à mettre en haut d'une page privée
 *   connecter_utilisateur($u)     ouvre la session après vérification du mot de passe
 *   deconnecter()                 ferme la session et oublie « Rester connecté »
 *   fermer_toutes_les_sessions()  après un changement de mot de passe (§8.5)
 */

declare(strict_types=1);

const CONNEXION_ECHECS_MAX = 5;              // 5 échecs...
const CONNEXION_FENETRE_MINUTES = 15;        // ... en 15 minutes = blocage 15 minutes (§8.4)
const CONNEXION_ECHECS_MAX_PAR_IP = 20;      // protection supplémentaire par adresse IP
const SOUVENIR_DUREE_JOURS = 30;             // « Rester connecté » : 30 jours maximum
const COOKIE_SOUVENIR = 'sama_souvenir';

/* -------------------------------------------------------------------------
   Utilisateur connecté
   ------------------------------------------------------------------------- */

/**
 * Renvoie l'utilisateur connecté (ligne de la table utilisateurs), ou null.
 *
 * Le compte est relu en base à chaque page : un compte suspendu, supprimé, ou dont
 * le mot de passe a été changé ailleurs (version_session différente) est déconnecté
 * immédiatement. Le résultat est gardé en mémoire pour le reste de la page.
 */
function utilisateur_connecte(): ?array
{
    static $dejaCharge = false;
    static $utilisateur = null;

    if ($dejaCharge) {
        return $utilisateur;
    }
    $dejaCharge = true;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    $id = (int) ($_SESSION['utilisateur_id'] ?? 0);

    if ($id > 0) {
        $compte = trouver_utilisateur($id);

        if ($compte !== null && compte_utilisable($compte)
            && (int) $compte['version_session'] === (int) ($_SESSION['version_session'] ?? 0)) {
            $utilisateur = $compte;

            return $utilisateur;
        }

        // Session plus valable : on repart d'une session vide.
        vider_session();
    }

    // Pas de session : peut-être un cookie « Rester connecté ».
    $utilisateur = connexion_par_cookie_souvenir();

    return $utilisateur;
}

/*
 * Lecture d'un compte avec son rôle :
 *   $compte['role']         → rôle écrit en clair dans utilisateurs : 'client', 'vendeur' ou 'admin'
 *   $compte['role_libelle'] → libellé affiché, lu dans la table roles : « Client », « Vendeur »…
 */
const SQL_UTILISATEUR = 'SELECT u.*, r.libelle AS role_libelle
                         FROM utilisateurs u
                         JOIN roles r ON r.code = u.role';

/** Une ligne de la table utilisateurs par son id (comptes supprimés exclus). */
function trouver_utilisateur(int $id): ?array
{
    $requete = bdd()->prepare(SQL_UTILISATEUR . ' WHERE u.id = :id AND u.supprime_le IS NULL');
    $requete->execute(['id' => $id]);
    $compte = $requete->fetch();

    return $compte === false ? null : $compte;
}

/** Vrai si le compte peut se connecter (ni désactivé, ni suspendu, ni supprimé). */
function compte_utilisable(array $compte): bool
{
    return $compte['statut'] === 'actif' && $compte['supprime_le'] === null;
}

/**
 * Comptes correspondant à un identifiant de connexion (§8.4) :
 *   - une adresse e-mail → 0 ou 1 compte ;
 *   - un numéro de téléphone VÉRIFIÉ → jusqu'à 2 comptes (un client et un vendeur).
 *
 * @return list<array>
 */
function trouver_comptes_par_identifiant(string $identifiant): array
{
    $email = normaliser_email($identifiant);

    if ($email !== null) {
        $requete = bdd()->prepare(SQL_UTILISATEUR . ' WHERE u.email = :email AND u.supprime_le IS NULL');
        $requete->execute(['email' => $email]);

        return $requete->fetchAll();
    }

    $telephone = normaliser_telephone($identifiant);

    if ($telephone !== null) {
        $requete = bdd()->prepare(
            SQL_UTILISATEUR . '
             WHERE u.telephone = :telephone AND u.telephone_verifie_le IS NOT NULL AND u.supprime_le IS NULL
             ORDER BY u.role'
        );
        $requete->execute(['telephone' => $telephone]);

        return $requete->fetchAll();
    }

    return [];
}

/** Page d'accueil de l'espace d'un utilisateur, selon son rôle. */
function chemin_espace(array $utilisateur): string
{
    return match ($utilisateur['role']) {
        'vendeur' => 'vendeur/',
        'client' => 'compte/',
        // Espace administrateur (lot 5) : tant que la page n'existe pas, l'accueil.
        'admin' => page_existe('admin') ? 'admin/' : '',
        default => '',
    };
}

/**
 * Page d'arrivée après la connexion : l'accueil pour un client, son espace de travail pour
 * un vendeur (espace vendeur) ou un administrateur (administration).
 */
function page_d_arrivee(array $utilisateur): string
{
    return $utilisateur['role'] === 'client' ? '' : chemin_espace($utilisateur);
}

/* -------------------------------------------------------------------------
   Connexion et déconnexion
   ------------------------------------------------------------------------- */

/**
 * Ouvre la session d'un utilisateur dont le mot de passe vient d'être vérifié.
 * L'identifiant de session est régénéré (protection contre le vol de session, §8.4).
 */
function connecter_utilisateur(array $utilisateur, bool $resterConnecte = false): void
{
    session_regenerate_id(true);

    $_SESSION['utilisateur_id'] = (int) $utilisateur['id'];

    // Le panier rempli avant la connexion rejoint celui du compte (§11).
    fusionner_panier_invite((int) $utilisateur['id']);
    $_SESSION['version_session'] = (int) $utilisateur['version_session'];

    bdd()->prepare('UPDATE utilisateurs SET derniere_connexion_le = NOW() WHERE id = :id')
        ->execute(['id' => $utilisateur['id']]);

    if ($resterConnecte) {
        creer_cookie_souvenir((int) $utilisateur['id']);
    }
}

/** Déconnexion : session vidée, cookie « Rester connecté » supprimé et oublié en base. */
function deconnecter(): void
{
    $cookie = lire_cookie_souvenir();

    if ($cookie !== null) {
        bdd()->prepare('DELETE FROM jetons_connexion WHERE selecteur = :selecteur')
            ->execute(['selecteur' => $cookie['selecteur']]);
    }

    supprimer_cookie_souvenir();
    vider_session();
}

/** Vide la session et lui donne un nouvel identifiant (le panier invité sera géré au lot 4). */
function vider_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = ['derniere_activite' => time()];
    session_regenerate_id(true);
}

/**
 * Ferme TOUTES les sessions d'un utilisateur, sur tous ses appareils (§8.5) :
 * la version de session change, donc les autres sessions ne correspondent plus,
 * et tous les cookies « Rester connecté » sont révoqués.
 */
function fermer_toutes_les_sessions(int $utilisateurId): void
{
    bdd()->prepare('UPDATE utilisateurs SET version_session = version_session + 1 WHERE id = :id')
        ->execute(['id' => $utilisateurId]);
    bdd()->prepare('DELETE FROM jetons_connexion WHERE utilisateur_id = :id')
        ->execute(['id' => $utilisateurId]);
}

/* -------------------------------------------------------------------------
   « Rester connecté » (30 jours)
   Cookie « selecteur:validateur ». Le sélecteur retrouve la ligne en base,
   le validateur est comparé à son empreinte SHA-256 (jamais stocké en clair).
   ------------------------------------------------------------------------- */

function creer_cookie_souvenir(int $utilisateurId): void
{
    $selecteur = bin2hex(random_bytes(12));
    $validateur = bin2hex(random_bytes(32));
    $expiration = time() + SOUVENIR_DUREE_JOURS * 86400;

    bdd()->prepare(
        'INSERT INTO jetons_connexion (utilisateur_id, selecteur, validateur_hache, expire_le)
         VALUES (:utilisateur_id, :selecteur, :validateur_hache, :expire_le)'
    )->execute([
        'utilisateur_id' => $utilisateurId,
        'selecteur' => $selecteur,
        'validateur_hache' => hash('sha256', $validateur),
        'expire_le' => date('Y-m-d H:i:s', $expiration),
    ]);

    ecrire_cookie_souvenir($selecteur . ':' . $validateur, $expiration);
}

/** @return array{selecteur: string, validateur: string}|null */
function lire_cookie_souvenir(): ?array
{
    $valeur = $_COOKIE[COOKIE_SOUVENIR] ?? '';

    if (!is_string($valeur) || preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $valeur, $m) !== 1) {
        return null;
    }

    return ['selecteur' => $m[1], 'validateur' => $m[2]];
}

function ecrire_cookie_souvenir(string $valeur, int $expiration): void
{
    if (headers_sent()) {
        return;
    }

    setcookie(COOKIE_SOUVENIR, $valeur, [
        'expires' => $expiration,
        'path' => '/',
        'secure' => est_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function supprimer_cookie_souvenir(): void
{
    if (isset($_COOKIE[COOKIE_SOUVENIR])) {
        ecrire_cookie_souvenir('', time() - 3600);
        unset($_COOKIE[COOKIE_SOUVENIR]);
    }
}

/**
 * Reconnecte automatiquement grâce au cookie « Rester connecté ».
 * Le jeton est remplacé à chaque utilisation. Un validateur faux pour un sélecteur
 * connu signale un vol de cookie probable : tous les jetons du compte sont révoqués.
 */
function connexion_par_cookie_souvenir(): ?array
{
    $cookie = lire_cookie_souvenir();

    if ($cookie === null) {
        if (isset($_COOKIE[COOKIE_SOUVENIR])) {
            supprimer_cookie_souvenir();   // cookie abîmé ou falsifié
        }

        return null;
    }

    $requete = bdd()->prepare('SELECT * FROM jetons_connexion WHERE selecteur = :selecteur');
    $requete->execute(['selecteur' => $cookie['selecteur']]);
    $jeton = $requete->fetch();

    if ($jeton === false || strtotime($jeton['expire_le']) < time()) {
        supprimer_cookie_souvenir();

        return null;
    }

    if (!hash_equals($jeton['validateur_hache'], hash('sha256', $cookie['validateur']))) {
        bdd()->prepare('DELETE FROM jetons_connexion WHERE utilisateur_id = :id')
            ->execute(['id' => $jeton['utilisateur_id']]);
        supprimer_cookie_souvenir();

        return null;
    }

    $compte = trouver_utilisateur((int) $jeton['utilisateur_id']);

    // Le jeton utilisé est toujours supprimé ; un nouveau est créé si le compte est valable.
    bdd()->prepare('DELETE FROM jetons_connexion WHERE id = :id')->execute(['id' => $jeton['id']]);

    if ($compte === null || !compte_utilisable($compte)) {
        supprimer_cookie_souvenir();

        return null;
    }

    connecter_utilisateur($compte, true);

    return $compte;
}

/* -------------------------------------------------------------------------
   Limitation des tentatives de connexion (§8.4, §24)
   ------------------------------------------------------------------------- */

/** Adresse IP du visiteur (celle vue par le serveur, non falsifiable par un en-tête). */
function adresse_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** Identifiant ramené à une forme unique pour compter les essais (e-mail ou téléphone). */
function identifiant_normalise(string $identifiant): string
{
    return normaliser_email($identifiant) ?? normaliser_telephone($identifiant) ?? mb_strtolower(mb_substr($identifiant, 0, 190));
}

/**
 * Vrai si la connexion est bloquée : 5 échecs pour cet identifiant (depuis la dernière
 * connexion réussie) ou 20 échecs pour cette adresse IP, dans les 15 dernières minutes.
 */
function connexion_bloquee(string $identifiant): bool
{
    $requete = bdd()->prepare(
        'SELECT COUNT(*) FROM tentatives_connexion
         WHERE identifiant = :identifiant AND reussie = 0
           AND cree_le > NOW() - INTERVAL ' . CONNEXION_FENETRE_MINUTES . ' MINUTE
           AND cree_le > COALESCE(
               (SELECT MAX(t.cree_le) FROM tentatives_connexion t WHERE t.identifiant = :identifiant2 AND t.reussie = 1),
               \'1970-01-01\')'
    );
    $requete->execute(['identifiant' => $identifiant, 'identifiant2' => $identifiant]);

    if ((int) $requete->fetchColumn() >= CONNEXION_ECHECS_MAX) {
        return true;
    }

    $requete = bdd()->prepare(
        'SELECT COUNT(*) FROM tentatives_connexion
         WHERE adresse_ip = :ip AND reussie = 0
           AND cree_le > NOW() - INTERVAL ' . CONNEXION_FENETRE_MINUTES . ' MINUTE'
    );
    $requete->execute(['ip' => adresse_ip()]);

    return (int) $requete->fetchColumn() >= CONNEXION_ECHECS_MAX_PAR_IP;
}

function enregistrer_tentative_connexion(string $identifiant, bool $reussie): void
{
    bdd()->prepare(
        'INSERT INTO tentatives_connexion (identifiant, adresse_ip, reussie) VALUES (:identifiant, :ip, :reussie)'
    )->execute(['identifiant' => $identifiant, 'ip' => adresse_ip(), 'reussie' => $reussie ? 1 : 0]);
}

/* -------------------------------------------------------------------------
   Contrôle d'accès aux pages (§5.1) — toujours vérifié côté serveur.
   ------------------------------------------------------------------------- */

/**
 * À appeler en haut de chaque page privée. Sans connexion : retour à la page de connexion,
 * puis à cette page une fois connecté. Mauvais rôle : erreur 403.
 *
 * @param string|null $role 'client', 'vendeur' ou 'admin' ; null = tout compte connecté
 */
function exiger_connexion(?string $role = null): array
{
    $utilisateur = utilisateur_connecte();

    if ($utilisateur === null) {
        $_SESSION['retour_apres_connexion'] = chemin_relatif_actuel();
        message_flash('erreur', 'Connectez-vous pour accéder à cette page.');
        rediriger('connexion/');
    }

    if ($role !== null && $utilisateur['role'] !== $role) {
        afficher_erreur(403);
    }

    return $utilisateur;
}

/** Pour les pages réservées aux visiteurs (connexion, inscription) : un connecté va à sa page d'arrivée. */
function rediriger_si_connecte(): void
{
    $utilisateur = utilisateur_connecte();

    if ($utilisateur !== null) {
        rediriger(page_d_arrivee($utilisateur));
    }
}

/**
 * Où aller après la connexion : la page demandée avant (si elle existe), sinon la page
 * d'arrivée du rôle (accueil pour un client, espace vendeur, administration).
 */
function chemin_apres_connexion(array $utilisateur): string
{
    $retour = $_SESSION['retour_apres_connexion'] ?? '';
    unset($_SESSION['retour_apres_connexion']);

    // Uniquement un chemin interne simple (jamais une adresse externe).
    if (is_string($retour) && $retour !== '' && preg_match('#^[a-z0-9/_.\-]+(\?[^\s]*)?$#i', $retour) === 1
        && !str_starts_with($retour, '/')) {
        return $retour;
    }

    return page_d_arrivee($utilisateur);
}

/* -------------------------------------------------------------------------
   Journal d'audit (§31)
   ------------------------------------------------------------------------- */

/**
 * Enregistre une action sensible. Ne JAMAIS y mettre de mot de passe, de code ni de jeton.
 */
function journaliser_action(
    string $action,
    ?int $auteurId = null,
    ?string $objetType = null,
    ?int $objetId = null,
    ?string $ancienneValeur = null,
    ?string $nouvelleValeur = null
): void {
    bdd()->prepare(
        'INSERT INTO journal_actions (utilisateur_id, action, objet_type, objet_id, ancienne_valeur, nouvelle_valeur, adresse_ip)
         VALUES (:auteur, :action, :objet_type, :objet_id, :ancienne, :nouvelle, :ip)'
    )->execute([
        'auteur' => $auteurId,
        'action' => $action,
        'objet_type' => $objetType,
        'objet_id' => $objetId,
        'ancienne' => $ancienneValeur,
        'nouvelle' => $nouvelleValeur,
        'ip' => adresse_ip(),
    ]);
}

/* -------------------------------------------------------------------------
   Paramètres configurables (table parametres, Annexe A)
   ------------------------------------------------------------------------- */

/** Valeur d'un paramètre, ou $defaut s'il n'existe pas. Lus une seule fois par page. */
function parametre(string $cle, string $defaut = ''): string
{
    static $parametres = null;

    if ($parametres === null) {
        $parametres = bdd()->query('SELECT cle, valeur FROM parametres')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    return isset($parametres[$cle]) ? (string) $parametres[$cle] : $defaut;
}
