<?php
/**
 * SAMA MAILLOT — panier (§11).
 *
 * - Visiteur : panier invité repéré par le cookie « sama_panier » (jeton aléatoire, 7 jours).
 *   Seule l'empreinte SHA-256 du jeton est stockée en base.
 * - Compte connecté : un panier par compte. À la connexion, le panier invité y est fusionné.
 * - Un maillot + une taille + un flocage = une ligne.
 * - Le prix n'est JAMAIS lu depuis le navigateur : il est recalculé ici à chaque affichage.
 * - Un vendeur peut acheter, sauf dans sa propre boutique (§5.1). Un administrateur n'achète pas.
 */

declare(strict_types=1);

const COOKIE_PANIER = 'sama_panier';
const PANIER_INVITE_JOURS = 7;
const QUANTITE_MAX_PAR_LIGNE = 10;

/* -------------------------------------------------------------------------
   Panier courant
   ------------------------------------------------------------------------- */

/** Vrai si ce compte (ou un visiteur, null) a le droit d'acheter (§5.1 : l'admin non). */
function peut_acheter(?array $utilisateur): bool
{
    return $utilisateur === null || in_array($utilisateur['role'], ['client', 'vendeur'], true);
}

/** Empreinte du jeton invité lu dans le cookie (null si absent ou mal formé). */
function jeton_panier_invite(): ?string
{
    $jeton = $_COOKIE[COOKIE_PANIER] ?? '';

    return is_string($jeton) && preg_match('/^[a-f0-9]{64}$/', $jeton) === 1 ? hash('sha256', $jeton) : null;
}

function poser_cookie_panier(string $jeton, int $expire): void
{
    if (headers_sent()) {
        return;
    }
    setcookie(COOKIE_PANIER, $jeton, [
        'expires' => $expire,
        'path' => '/',
        'secure' => est_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Identifiant du panier de la personne qui navigue.
 * $creer = false : null s'il n'existe pas encore (affichage) ; true : il est créé (ajout).
 */
function panier_courant(bool $creer = false): ?int
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $utilisateur = utilisateur_connecte();
    $pdo = bdd();

    if ($utilisateur !== null) {
        $requete = $pdo->prepare('SELECT id FROM paniers WHERE utilisateur_id = :id');
        $requete->execute(['id' => $utilisateur['id']]);
        $id = $requete->fetchColumn();

        if ($id === false && $creer) {
            $pdo->prepare('INSERT INTO paniers (utilisateur_id) VALUES (:id)')->execute(['id' => $utilisateur['id']]);
            $id = $pdo->lastInsertId();
        }

        return $cache = ($id === false ? null : (int) $id);
    }

    $empreinte = jeton_panier_invite();
    if ($empreinte !== null) {
        $requete = $pdo->prepare('SELECT id FROM paniers WHERE jeton_hache = :jeton AND expire_le > NOW()');
        $requete->execute(['jeton' => $empreinte]);
        $id = $requete->fetchColumn();
        if ($id !== false) {
            return $cache = (int) $id;
        }
    }

    if (!$creer) {
        return null;
    }

    // Nouveau panier invité : jeton aléatoire dans le cookie, empreinte en base.
    $jeton = bin2hex(random_bytes(32));
    $pdo->prepare(
        'INSERT INTO paniers (jeton_hache, expire_le) VALUES (:jeton, NOW() + INTERVAL ' . PANIER_INVITE_JOURS . ' DAY)'
    )->execute(['jeton' => hash('sha256', $jeton)]);
    poser_cookie_panier($jeton, time() + PANIER_INVITE_JOURS * 86400);

    return $cache = (int) $pdo->lastInsertId();
}

/** Prolonge le panier invité de 7 jours à chaque modification. */
function prolonger_panier_invite(int $panierId): void
{
    bdd()->prepare(
        'UPDATE paniers SET expire_le = NOW() + INTERVAL ' . PANIER_INVITE_JOURS . ' DAY WHERE id = :id AND utilisateur_id IS NULL'
    )->execute(['id' => $panierId]);

    $jeton = $_COOKIE[COOKIE_PANIER] ?? '';
    if (is_string($jeton) && preg_match('/^[a-f0-9]{64}$/', $jeton) === 1) {
        poser_cookie_panier($jeton, time() + PANIER_INVITE_JOURS * 86400);
    }
}

/**
 * À la connexion : les lignes du panier invité rejoignent le panier du compte (§11).
 * Une ligne déjà présente voit ses quantités additionnées (plafonnées à 10).
 */
function fusionner_panier_invite(int $utilisateurId): void
{
    $empreinte = jeton_panier_invite();
    if ($empreinte === null) {
        return;
    }

    $pdo = bdd();
    $requete = $pdo->prepare('SELECT id FROM paniers WHERE jeton_hache = :jeton AND expire_le > NOW()');
    $requete->execute(['jeton' => $empreinte]);
    $invite = $requete->fetchColumn();

    if ($invite !== false) {
        $pdo->prepare('INSERT IGNORE INTO paniers (utilisateur_id) VALUES (:id)')->execute(['id' => $utilisateurId]);
        $requete = $pdo->prepare('SELECT id FROM paniers WHERE utilisateur_id = :id');
        $requete->execute(['id' => $utilisateurId]);
        $compte = (int) $requete->fetchColumn();

        $pdo->prepare(
            'INSERT INTO panier_lignes (panier_id, maillot_id, taille, flocage_nom, flocage_numero, quantite, prix_vu)
             SELECT :compte, source.maillot_id, source.taille, source.flocage_nom, source.flocage_numero, source.quantite, source.prix_vu
             FROM panier_lignes AS source WHERE source.panier_id = :invite
             ON DUPLICATE KEY UPDATE quantite = LEAST(panier_lignes.quantite + VALUES(quantite), ' . QUANTITE_MAX_PAR_LIGNE . ')'
        )->execute(['compte' => $compte, 'invite' => (int) $invite]);

        $pdo->prepare('DELETE FROM paniers WHERE id = :id')->execute(['id' => (int) $invite]);
    }

    unset($_COOKIE[COOKIE_PANIER]);
    poser_cookie_panier('', time() - 3600);
}

/* -------------------------------------------------------------------------
   Lecture du panier (prix et stock recalculés)
   ------------------------------------------------------------------------- */

/**
 * Lignes du panier avec les données ACTUELLES du maillot et un diagnostic :
 *   probleme : null, 'indisponible', 'epuise', 'stock', 'propre_boutique' ou 'flocage'
 *   prix_change : vrai si le prix a changé depuis l'ajout
 *
 * @return list<array>
 */
function lignes_du_panier(?int $panierId = null): array
{
    $panierId ??= panier_courant();
    if ($panierId === null) {
        return [];
    }

    $requete = bdd()->prepare(
        "SELECT l.id, l.maillot_id, l.taille, l.flocage_nom, l.flocage_numero, l.quantite, l.prix_vu,
                m.titre, m.slug, m.prix, m.flocage_propose, m.flocage_supplement,
                (m.statut = 'publie' AND m.supprime_le IS NULL AND b.statut IN ('active', 'grace') AND b.supprime_le IS NULL) AS visible,
                b.id AS boutique_id, b.nom AS boutique_nom, b.slug AS boutique_slug, b.utilisateur_id AS vendeur_id,
                b.retrait_sur_place, b.adresse_retrait,
                t.stock,
                (SELECT i.miniature FROM maillot_images i WHERE i.maillot_id = m.id ORDER BY i.ordre, i.id LIMIT 1) AS miniature
         FROM panier_lignes l
         JOIN maillots m ON m.id = l.maillot_id
         JOIN boutiques b ON b.id = m.boutique_id
         LEFT JOIN maillot_tailles t ON t.maillot_id = l.maillot_id AND t.taille = l.taille
         WHERE l.panier_id = :panier
         ORDER BY b.nom, l.ajoute_le, l.id"
    );
    $requete->execute(['panier' => $panierId]);

    $utilisateur = utilisateur_connecte();
    $lignes = [];

    foreach ($requete->fetchAll() as $ligne) {
        $avecFlocage = $ligne['flocage_nom'] !== '' || $ligne['flocage_numero'] !== '';
        $ligne['prix_unitaire'] = (int) $ligne['prix'] + ($avecFlocage ? (int) $ligne['flocage_supplement'] : 0);
        $ligne['total_ligne'] = $ligne['prix_unitaire'] * (int) $ligne['quantite'];
        $ligne['stock'] = $ligne['stock'] === null ? 0 : (int) $ligne['stock'];
        $ligne['prix_change'] = (int) $ligne['prix_vu'] !== $ligne['prix_unitaire'];

        $ligne['probleme'] = match (true) {
            (int) $ligne['visible'] !== 1 => 'indisponible',
            $utilisateur !== null && (int) $ligne['vendeur_id'] === (int) $utilisateur['id'] => 'propre_boutique',
            $ligne['stock'] <= 0 => 'epuise',
            (int) $ligne['quantite'] > $ligne['stock'] => 'stock',
            $avecFlocage && (int) $ligne['flocage_propose'] !== 1 => 'flocage',
            default => null,
        };
        $lignes[] = $ligne;
    }

    return $lignes;
}

/** Message affiché sous une ligne qui pose problème. */
function message_probleme_ligne(array $ligne): string
{
    return match ($ligne['probleme']) {
        'indisponible' => 'Ce maillot n\'est plus en vente.',
        'propre_boutique' => 'C\'est un maillot de votre boutique : vous ne pouvez pas l\'acheter.',
        'epuise' => 'Cette taille est épuisée.',
        'stock' => 'Plus que ' . $ligne['stock'] . ' en stock : réduisez la quantité.',
        'flocage' => 'Le vendeur ne propose plus le flocage : retirez cette ligne et ajoutez le maillot sans flocage.',
        default => '',
    };
}

/**
 * Lignes regroupées par boutique : une boutique = une future commande (§11).
 *
 * @return array<int, array{boutique: array, lignes: list<array>, sous_total: int, commandable: bool}>
 */
function panier_par_boutique(array $lignes): array
{
    $groupes = [];

    foreach ($lignes as $ligne) {
        $id = (int) $ligne['boutique_id'];
        $groupes[$id] ??= [
            'boutique' => [
                'id' => $id,
                'nom' => $ligne['boutique_nom'],
                'slug' => $ligne['boutique_slug'],
                'retrait_sur_place' => (int) $ligne['retrait_sur_place'] === 1,
                'adresse_retrait' => (string) $ligne['adresse_retrait'],
            ],
            'lignes' => [],
            'sous_total' => 0,
            'commandable' => true,
        ];
        $groupes[$id]['lignes'][] = $ligne;

        if ($ligne['probleme'] === null) {
            $groupes[$id]['sous_total'] += $ligne['total_ligne'];
        } else {
            $groupes[$id]['commandable'] = false;
        }
    }

    return $groupes;
}

/** Nombre d'articles (somme des quantités) pour le badge de l'en-tête. */
function nombre_articles_panier(): int
{
    $panierId = panier_courant();
    if ($panierId === null) {
        return 0;
    }

    $requete = bdd()->prepare('SELECT COALESCE(SUM(quantite), 0) FROM panier_lignes WHERE panier_id = :id');
    $requete->execute(['id' => $panierId]);

    return (int) $requete->fetchColumn();
}

/** Le client a vu les nouveaux prix : on les retient pour ne plus les signaler. */
function marquer_prix_vus(array $lignes): void
{
    $requete = bdd()->prepare('UPDATE panier_lignes SET prix_vu = :prix WHERE id = :id');
    foreach ($lignes as $ligne) {
        if ($ligne['prix_change']) {
            $requete->execute(['prix' => $ligne['prix_unitaire'], 'id' => $ligne['id']]);
        }
    }
}

/* -------------------------------------------------------------------------
   Modification du panier
   ------------------------------------------------------------------------- */

/**
 * Normalise et contrôle un flocage saisi (nom : lettres, 12 caractères ; numéro : 0 à 99).
 *
 * @return array{0: string, 1: string, 2: ?string} [nom, numéro, message d'erreur]
 */
function lire_flocage(string $nom, string $numero): array
{
    if (!mb_check_encoding($nom, 'UTF-8')) {
        return ['', $numero, 'Nom du flocage : caractères non reconnus. Utilisez des lettres simples.'];
    }
    $nom = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $nom) ?? ''));
    $numero = trim($numero);

    if ($nom === '' && $numero === '') {
        return ['', '', null];
    }
    if ($nom === '' || $numero === '') {
        return [$nom, $numero, 'Pour le flocage, indiquez le nom ET le numéro.'];
    }
    if (preg_match('/^[\p{L}][\p{L} \'.-]{0,11}$/u', $nom) !== 1) {
        return [$nom, $numero, 'Nom du flocage : 12 lettres maximum (espaces, tiret et apostrophe autorisés).'];
    }
    if (preg_match('/^\d{1,2}$/', $numero) !== 1) {
        return [$nom, $numero, 'Numéro du flocage : de 0 à 99.'];
    }

    return [$nom, (string) (int) $numero, null];
}

/**
 * Ajoute un maillot au panier. Renvoie null si tout va bien, sinon le message d'erreur.
 */
function ajouter_au_panier(int $maillotId, string $taille, int $quantite, string $flocageNom = '', string $flocageNumero = ''): ?string
{
    $utilisateur = utilisateur_connecte();
    if (!peut_acheter($utilisateur)) {
        return 'Un compte administrateur ne peut pas passer de commande.';
    }

    $maillot = maillot_par_id($maillotId);
    if ($maillot === null) {
        return 'Ce maillot n\'est plus disponible.';
    }
    if ($utilisateur !== null && (int) boutique_proprietaire((int) $maillot['boutique_id']) === (int) $utilisateur['id']) {
        return 'C\'est un maillot de votre boutique : vous ne pouvez pas l\'acheter.';
    }

    $tailles = tailles_du_maillot($maillotId);
    if (!isset($tailles[$taille])) {
        return 'Choisissez une taille.';
    }
    if ($tailles[$taille] <= 0) {
        return 'Cette taille est épuisée.';
    }

    [$flocageNom, $flocageNumero, $erreurFlocage] = lire_flocage($flocageNom, $flocageNumero);
    if ($erreurFlocage !== null) {
        return $erreurFlocage;
    }
    $avecFlocage = $flocageNom !== '';
    if ($avecFlocage && (int) $maillot['flocage_propose'] !== 1) {
        return 'Le vendeur ne propose pas le flocage pour ce maillot.';
    }

    $quantite = max(1, min(QUANTITE_MAX_PAR_LIGNE, $quantite));
    $panierId = panier_courant(true);

    // Quantité déjà dans le panier pour cette taille (toutes lignes confondues) : on ne dépasse pas le stock.
    $requete = bdd()->prepare(
        'SELECT COALESCE(SUM(quantite), 0) FROM panier_lignes WHERE panier_id = :panier AND maillot_id = :maillot AND taille = :taille'
    );
    $requete->execute(['panier' => $panierId, 'maillot' => $maillotId, 'taille' => $taille]);
    $dejaDansLePanier = (int) $requete->fetchColumn();

    if ($dejaDansLePanier + $quantite > $tailles[$taille]) {
        $reste = $tailles[$taille] - $dejaDansLePanier;

        return $reste > 0
            ? 'Il ne reste que ' . $tailles[$taille] . ' maillot(s) en taille ' . $taille . ' : vous pouvez en ajouter ' . $reste . ' au maximum.'
            : 'Vous avez déjà tout le stock disponible en taille ' . $taille . ' dans votre panier.';
    }

    $prix = (int) $maillot['prix'] + ($avecFlocage ? (int) $maillot['flocage_supplement'] : 0);

    bdd()->prepare(
        'INSERT INTO panier_lignes (panier_id, maillot_id, taille, flocage_nom, flocage_numero, quantite, prix_vu)
         VALUES (:panier, :maillot, :taille, :nom, :numero, :quantite, :prix)
         ON DUPLICATE KEY UPDATE quantite = LEAST(quantite + VALUES(quantite), ' . QUANTITE_MAX_PAR_LIGNE . '), prix_vu = VALUES(prix_vu)'
    )->execute([
        'panier' => $panierId, 'maillot' => $maillotId, 'taille' => $taille,
        'nom' => $flocageNom, 'numero' => $flocageNumero, 'quantite' => $quantite, 'prix' => $prix,
    ]);
    prolonger_panier_invite($panierId);

    return null;
}

/** Change la quantité d'une ligne du panier COURANT (0 = suppression). */
function modifier_quantite_ligne(int $ligneId, int $quantite): void
{
    $panierId = panier_courant();
    if ($panierId === null) {
        return;
    }

    if ($quantite <= 0) {
        supprimer_ligne_panier($ligneId);

        return;
    }

    bdd()->prepare('UPDATE panier_lignes SET quantite = :quantite WHERE id = :id AND panier_id = :panier')
        ->execute(['quantite' => min(QUANTITE_MAX_PAR_LIGNE, $quantite), 'id' => $ligneId, 'panier' => $panierId]);
    prolonger_panier_invite($panierId);
}

/** Supprime une ligne du panier COURANT (jamais celle d'un autre panier). */
function supprimer_ligne_panier(int $ligneId): void
{
    $panierId = panier_courant();
    if ($panierId !== null) {
        bdd()->prepare('DELETE FROM panier_lignes WHERE id = :id AND panier_id = :panier')
            ->execute(['id' => $ligneId, 'panier' => $panierId]);
    }
}

/** Propriétaire (utilisateur) d'une boutique. */
function boutique_proprietaire(int $boutiqueId): ?int
{
    $requete = bdd()->prepare('SELECT utilisateur_id FROM boutiques WHERE id = :id');
    $requete->execute(['id' => $boutiqueId]);
    $id = $requete->fetchColumn();

    return $id === false ? null : (int) $id;
}

/* -------------------------------------------------------------------------
   Livraison
   ------------------------------------------------------------------------- */

/** Zones de livraison actives (choix du client). */
function zones_actives(): array
{
    return bdd()->query('SELECT id, nom, description FROM zones_livraison WHERE actif = 1 ORDER BY ordre, nom')->fetchAll();
}

/** Tarif et délai d'une boutique pour une zone, ou null si la boutique ne livre pas cette zone. */
function tarif_boutique_zone(int $boutiqueId, int $zoneId): ?array
{
    $requete = bdd()->prepare(
        'SELECT t.tarif, t.delai, z.nom AS zone_nom
         FROM tarifs_livraison t JOIN zones_livraison z ON z.id = t.zone_id
         WHERE t.boutique_id = :boutique AND t.zone_id = :zone AND z.actif = 1'
    );
    $requete->execute(['boutique' => $boutiqueId, 'zone' => $zoneId]);
    $tarif = $requete->fetch();

    return $tarif === false ? null : ['tarif' => (int) $tarif['tarif'], 'delai' => $tarif['delai'], 'zone_nom' => $tarif['zone_nom']];
}

/** Nom d'une zone de livraison (chaîne vide si inconnue). */
function nom_zone(int $zoneId): string
{
    $requete = bdd()->prepare('SELECT nom FROM zones_livraison WHERE id = :id');
    $requete->execute(['id' => $zoneId]);

    return (string) ($requete->fetchColumn() ?: '');
}

/** Zone choisie dans le panier (mémorisée en session pour estimer les frais). */
function zone_choisie(): ?int
{
    $zone = (int) ($_SESSION['zone_livraison'] ?? 0);

    return $zone > 0 ? $zone : null;
}

/* -------------------------------------------------------------------------
   Adresse de livraison (§12.1) — partagée par la commande et la commande WhatsApp
   ------------------------------------------------------------------------- */

/** Adresses enregistrées du client, la plus récemment utilisée en premier. */
function adresses_du_client(int $utilisateurId): array
{
    $requete = bdd()->prepare(
        'SELECT a.*, z.nom AS zone_nom FROM adresses a JOIN zones_livraison z ON z.id = a.zone_id
         WHERE a.utilisateur_id = :id ORDER BY a.utilisee_le DESC, a.id DESC LIMIT 10'
    );
    $requete->execute(['id' => $utilisateurId]);

    return $requete->fetchAll();
}

/**
 * Lit l'adresse envoyée par le formulaire : une adresse enregistrée (adresse_id) ou une nouvelle.
 * Le téléphone est TOUJOURS saisi (obligatoire) : c'est le numéro que le vendeur appellera.
 *
 * @return array{0: array, 1: array<string, string>} [adresse, erreurs]
 */
function lire_adresse_post(array $utilisateur): array
{
    $adresseId = (int) champ_post('adresse_id');
    $telephoneSaisi = champ_post('telephone');
    $telephone = normaliser_telephone($telephoneSaisi);
    $erreurTelephone = $telephone === null
        ? ($telephoneSaisi === '' ? 'Indiquez votre numéro : le vendeur vous appellera pour la livraison.'
            : 'Saisissez un numéro sénégalais de 9 chiffres commençant par 70, 75, 76, 77 ou 78.')
        : null;

    if ($adresseId > 0) {
        foreach (adresses_du_client((int) $utilisateur['id']) as $adresse) {
            if ((int) $adresse['id'] === $adresseId) {
                return [[
                    'id' => $adresseId, 'adresse_id' => (string) $adresseId, 'zone_id' => (int) $adresse['zone_id'],
                    'ville' => $adresse['ville'], 'quartier' => $adresse['quartier'], 'repere' => $adresse['repere'],
                    'telephone' => $telephone ?? '', 'telephone_saisi' => $telephoneSaisi,
                ], $erreurTelephone !== null ? ['telephone' => $erreurTelephone] : []];
            }
        }

        return [['adresse_id' => '0', 'telephone_saisi' => $telephoneSaisi], ['adresse_id' => 'Choisissez une adresse.']];
    }

    $adresse = [
        'id' => 0,
        'adresse_id' => '0',
        'zone_id' => (int) champ_post('zone_id'),
        'ville' => champ_post('ville'),
        'quartier' => champ_post('quartier'),
        'repere' => champ_post('repere'),
        'telephone' => $telephone ?? '',
        'telephone_saisi' => $telephoneSaisi,
    ];
    $erreurs = [];

    if (!in_array($adresse['zone_id'], array_map('intval', array_column(zones_actives(), 'id')), true)) {
        $erreurs['zone_id'] = 'Choisissez votre zone de livraison.';
    }
    if (mb_strlen($adresse['ville']) < 2 || mb_strlen($adresse['ville']) > 60) {
        $erreurs['ville'] = 'Indiquez votre ville.';
    }
    if (mb_strlen($adresse['quartier']) < 2 || mb_strlen($adresse['quartier']) > 80) {
        $erreurs['quartier'] = 'Indiquez votre quartier.';
    }
    if (mb_strlen($adresse['repere']) > 160) {
        $erreurs['repere'] = 'Le point de repère est trop long (160 caractères maximum).';
    }
    if ($erreurTelephone !== null) {
        $erreurs['telephone'] = $erreurTelephone;
    }

    return [$adresse, $erreurs];
}

/** Enregistre une nouvelle adresse (ou rafraîchit la date d'une adresse déjà enregistrée). */
function memoriser_adresse(int $utilisateurId, array $adresse): void
{
    if ((int) $adresse['id'] > 0) {
        bdd()->prepare('UPDATE adresses SET utilisee_le = NOW(), telephone = :telephone WHERE id = :id AND utilisateur_id = :client')
            ->execute(['id' => $adresse['id'], 'client' => $utilisateurId, 'telephone' => $adresse['telephone']]);

        return;
    }

    bdd()->prepare(
        'INSERT INTO adresses (utilisateur_id, zone_id, ville, quartier, repere, telephone)
         VALUES (:client, :zone, :ville, :quartier, :repere, :telephone)'
    )->execute([
        'client' => $utilisateurId, 'zone' => $adresse['zone_id'], 'ville' => $adresse['ville'],
        'quartier' => $adresse['quartier'], 'repere' => $adresse['repere'], 'telephone' => $adresse['telephone'],
    ]);
}

/** Champs HTML de l'adresse : adresses enregistrées (boutons radio) + nouvelle adresse. */
function champs_adresse(array $utilisateur, array $adresses, array $valeurs, array $erreurs): string
{
    $zones = zones_actives();
    $choix = (string) ($valeurs['adresse_id'] ?? ($adresses !== [] ? (string) $adresses[0]['id'] : '0'));
    // Numéro proposé : le dernier utilisé pour une livraison, sinon celui du compte.
    $telephoneDefaut = $adresses !== [] ? formater_telephone($adresses[0]['telephone'])
        : ($utilisateur['telephone'] !== null ? formater_telephone($utilisateur['telephone']) : '');
    $zoneDefaut = (int) ($valeurs['zone_id'] ?? zone_choisie() ?? 0);
    ob_start();
    ?>
    <?php if ($adresses !== []): ?>
        <fieldset class="mb-3">
            <legend class="form-label fw-semibold fs-6">Livrer à</legend>
            <?php foreach ($adresses as $adresse): ?>
                <div class="form-check sm-choix-adresse">
                    <input class="form-check-input" type="radio" name="adresse_id" id="adresse-<?= e($adresse['id']) ?>"
                           value="<?= e($adresse['id']) ?>"<?= $choix === (string) $adresse['id'] ? ' checked' : '' ?>>
                    <label class="form-check-label" for="adresse-<?= e($adresse['id']) ?>">
                        <strong><?= e($adresse['quartier']) ?>, <?= e($adresse['ville']) ?></strong>
                        <span class="d-block small text-body-secondary">
                            <?= e($adresse['zone_nom']) ?><?= $adresse['repere'] !== '' ? ' · ' . e($adresse['repere']) : '' ?>
                        </span>
                    </label>
                </div>
            <?php endforeach; ?>
            <div class="form-check sm-choix-adresse">
                <input class="form-check-input" type="radio" name="adresse_id" id="adresse-0" value="0"<?= $choix === '0' ? ' checked' : '' ?>>
                <label class="form-check-label" for="adresse-0"><strong>Une nouvelle adresse</strong> (remplir ci-dessous)</label>
            </div>
            <?= message_erreur($erreurs, 'adresse_id') ?>
        </fieldset>
    <?php else: ?>
        <input type="hidden" name="adresse_id" value="0">
    <?php endif; ?>

    <div class="sm-nouvelle-adresse">
        <div class="row g-3">
            <div class="col-12 col-sm-6">
                <label class="form-label" for="zone_id">Zone de livraison</label>
                <select class="form-select<?= classe_erreur($erreurs, 'zone_id') ?>" id="zone_id" name="zone_id" <?= aria_erreur($erreurs, 'zone_id') ?>>
                    <option value="">Choisir…</option>
                    <?php foreach ($zones as $zone): ?>
                        <option value="<?= e($zone['id']) ?>"<?= $zoneDefaut === (int) $zone['id'] ? ' selected' : '' ?>>
                            <?= e($zone['nom']) ?> — <?= e($zone['description']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= message_erreur($erreurs, 'zone_id') ?>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="ville">Ville</label>
                <input class="form-control<?= classe_erreur($erreurs, 'ville') ?>" type="text" id="ville" name="ville" maxlength="60"
                       value="<?= e($valeurs['ville'] ?? '') ?>" autocomplete="address-level2" <?= aria_erreur($erreurs, 'ville') ?>>
                <?= message_erreur($erreurs, 'ville') ?>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="quartier">Quartier</label>
                <input class="form-control<?= classe_erreur($erreurs, 'quartier') ?>" type="text" id="quartier" name="quartier" maxlength="80"
                       value="<?= e($valeurs['quartier'] ?? '') ?>" placeholder="Ex. : Sacré-Cœur 3" autocomplete="address-level3" <?= aria_erreur($erreurs, 'quartier') ?>>
                <?= message_erreur($erreurs, 'quartier') ?>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="repere">Point de repère <span class="text-body-secondary fw-normal">(facultatif)</span></label>
                <input class="form-control<?= classe_erreur($erreurs, 'repere') ?>" type="text" id="repere" name="repere" maxlength="160"
                       value="<?= e($valeurs['repere'] ?? '') ?>" placeholder="Ex. : derrière la pharmacie, portail bleu" <?= aria_erreur($erreurs, 'repere') ?>>
                <?= message_erreur($erreurs, 'repere') ?>
            </div>
        </div>
    </div>

    <!-- Téléphone : toujours demandé, c'est le numéro que le vendeur appellera (§12.1) -->
    <div class="sm-champ-telephone-livraison mt-4">
        <label class="form-label fw-semibold" for="telephone">
            <i class="bi bi-telephone" aria-hidden="true"></i> Votre numéro de téléphone
        </label>
        <div class="input-group input-group-lg">
            <span class="input-group-text" aria-hidden="true">+221</span>
            <input class="form-control<?= classe_erreur($erreurs, 'telephone') ?>" type="tel" id="telephone" name="telephone" maxlength="20"
                   value="<?= e($valeurs['telephone_saisi'] ?? $telephoneDefaut) ?>" inputmode="numeric" placeholder="77 123 45 67"
                   autocomplete="tel-national" required
                   aria-describedby="aide-telephone-livraison<?= isset($erreurs['telephone']) ? ' erreur-telephone' : '' ?>"
                   <?= isset($erreurs['telephone']) ? 'aria-invalid="true"' : '' ?>>
        </div>
        <div class="form-text" id="aide-telephone-livraison">Le vendeur vous appellera à ce numéro pour organiser la livraison.</div>
        <?= message_erreur($erreurs, 'telephone') ?>
    </div>
    <?php

    return (string) ob_get_clean();
}
