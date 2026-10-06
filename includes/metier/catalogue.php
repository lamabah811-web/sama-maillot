<?php
/**
 * SAMA MAILLOT — catalogue : recherche, filtres, fiches, boutiques, favoris (§10, §7.4).
 *
 * Règle de visibilité (§10.1) : seuls les maillots PUBLIÉS des boutiques ACTIVES ou EN GRÂCE
 * sont affichés. Toutes les requêtes publiques passent par SQL_MAILLOT_VISIBLE.
 */

declare(strict_types=1);

/* -------------------------------------------------------------------------
   Libellés (§9.4)
   ------------------------------------------------------------------------- */

/** Tailles proposées, dans l'ordre d'affichage (pas de tailles enfants par âge : décision du projet). */
const TAILLES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

const LIBELLES_TYPE = [
    'domicile' => 'Domicile',
    'exterieur' => 'Extérieur',
    'third' => 'Third',
    'gardien' => 'Gardien',
    'entrainement' => 'Entraînement',
    'autre' => 'Autre',
];

const LIBELLES_AUTHENTICITE = [
    'officiel' => 'Officiel',
    'replique' => 'Réplique',
    'version_fan' => 'Version fan',
];

const LIBELLES_ETAT = [
    'neuf_etiquette' => 'Neuf avec étiquette',
    'neuf_sans_etiquette' => 'Neuf sans étiquette',
    'occasion_tres_bon' => 'Occasion, très bon état',
    'occasion_bon' => 'Occasion, bon état',
];

const TRIS = [
    'recent' => 'Plus récents',
    'prix_asc' => 'Prix croissant',
    'prix_desc' => 'Prix décroissant',
];

const MAILLOTS_PAR_PAGE = 24;   // §10.1

/** Condition SQL commune : maillot publié d'une boutique active ou en grâce. */
const SQL_MAILLOT_VISIBLE = "m.statut = 'publie' AND m.supprime_le IS NULL
    AND b.statut IN ('active', 'grace') AND b.supprime_le IS NULL";

/** Colonnes et jointures utilisées pour afficher une carte de maillot. */
const SQL_CARTE_MAILLOT = 'SELECT m.id, m.titre, m.slug, m.prix, m.authenticite, m.saison, m.type, m.publie_le, m.boutique_id,
        b.nom AS boutique_nom, b.slug AS boutique_slug,
        e.nom AS equipe_nom, e.slug AS equipe_slug,
        c.nom AS categorie_nom, c.slug AS categorie_slug,
        (SELECT COALESCE(SUM(t.stock), 0) FROM maillot_tailles t WHERE t.maillot_id = m.id) AS stock_total,
        (SELECT i.miniature FROM maillot_images i WHERE i.maillot_id = m.id ORDER BY i.ordre, i.id LIMIT 1) AS miniature
    FROM maillots m
    JOIN boutiques b ON b.id = m.boutique_id
    JOIN equipes e ON e.id = m.equipe_id
    JOIN categories c ON c.id = m.categorie_id';

/* -------------------------------------------------------------------------
   Adresses lisibles (§30) : /maillot/<slug>-<id>, /boutique/<slug>, /catalogue/<catégorie>
   ------------------------------------------------------------------------- */

function url_maillot(array $maillot): string
{
    return url('maillot/' . $maillot['slug'] . '-' . (int) $maillot['id']);
}

function url_boutique(string $slug): string
{
    return url('boutique/' . $slug);
}

/**
 * Adresse du catalogue avec ses filtres. La catégorie est placée dans le chemin
 * (/catalogue/clubs), les autres filtres dans la requête (?equipe=psg&tri=prix_asc).
 */
function url_catalogue(array $parametres = []): string
{
    $chemin = 'catalogue/';

    if (!empty($parametres['categorie'])) {
        $chemin .= rawurlencode((string) $parametres['categorie']);
    }
    unset($parametres['categorie']);

    // On n'écrit que les filtres utiles (pas de « ?q=&prix_min= » vides).
    $parametres = array_filter($parametres, static fn ($valeur): bool => $valeur !== null && $valeur !== '' && $valeur !== 0);
    if (($parametres['tri'] ?? '') === 'recent') {
        unset($parametres['tri']);
    }
    if (($parametres['page'] ?? 1) === 1) {
        unset($parametres['page']);
    }

    return url($chemin) . ($parametres !== [] ? '?' . http_build_query($parametres) : '');
}

/** Adresse d'une photo de maillot envoyée par un vendeur (public/uploads/maillots/). */
function url_image_maillot(string $fichier): string
{
    return url('uploads/maillots/' . rawurlencode(basename($fichier)));
}

/* -------------------------------------------------------------------------
   Données de référence
   ------------------------------------------------------------------------- */

/** @return list<array{id: int, slug: string, nom: string, description: string}> */
function categories_actives(): array
{
    static $categories = null;

    if ($categories === null) {
        $categories = bdd()->query('SELECT id, slug, nom, description FROM categories WHERE actif = 1 ORDER BY ordre, nom')->fetchAll();
    }

    return $categories;
}

function categorie_par_slug(string $slug): ?array
{
    foreach (categories_actives() as $categorie) {
        if ($categorie['slug'] === $slug) {
            return $categorie;
        }
    }

    return null;
}

/** Équipes actives, groupées : ['selection' => [...], 'club' => [...]] */
function equipes_par_type(): array
{
    $groupes = ['selection' => [], 'club' => []];
    $lignes = bdd()->query('SELECT id, slug, nom, type FROM equipes WHERE actif = 1 ORDER BY nom')->fetchAll();

    foreach ($lignes as $equipe) {
        $groupes[$equipe['type']][] = $equipe;
    }

    return $groupes;
}

/**
 * Équipes ayant le plus de maillots visibles (section « Équipes populaires », §7.1) :
 * données réelles uniquement ; liste vide tant qu'aucun maillot n'est publié.
 */
function equipes_populaires(int $nombre = 8): array
{
    $requete = bdd()->prepare(
        'SELECT e.slug, e.nom, COUNT(*) AS nombre
         FROM maillots m
         JOIN boutiques b ON b.id = m.boutique_id
         JOIN equipes e ON e.id = m.equipe_id
         WHERE ' . SQL_MAILLOT_VISIBLE . '
         GROUP BY e.id, e.slug, e.nom
         ORDER BY nombre DESC, e.nom
         LIMIT ' . max(1, $nombre)
    );
    $requete->execute();

    return $requete->fetchAll();
}

/* -------------------------------------------------------------------------
   Recherche et filtres (§10.2, §10.3)
   ------------------------------------------------------------------------- */

/**
 * Lit et nettoie les filtres reçus dans l'adresse : toute valeur inconnue est ignorée.
 *
 * @return array{q: string, categorie: string, equipe: string, prix_min: int, prix_max: int,
 *               taille: string, tri: string, page: int, boutique_id: int}
 */
function lire_filtres(array $source): array
{
    $texte = static fn (string $cle): string => is_string($source[$cle] ?? null) ? trim($source[$cle]) : '';
    $entier = static fn (string $cle): int => max(0, (int) ($source[$cle] ?? 0));

    $filtres = [
        'q' => mb_substr($texte('q'), 0, 100),
        'categorie' => $texte('categorie'),
        'equipe' => preg_match('/^[a-z0-9-]{1,80}$/', $texte('equipe')) === 1 ? $texte('equipe') : '',
        'prix_min' => min($entier('prix_min'), 500000),
        'prix_max' => min($entier('prix_max'), 500000),
        'taille' => in_array($texte('taille'), TAILLES, true) ? $texte('taille') : '',
        'tri' => isset(TRIS[$texte('tri')]) ? $texte('tri') : 'recent',
        'page' => max(1, $entier('page')),
        'boutique_id' => 0,
    ];

    // Fourchette inversée : on remet dans l'ordre plutôt que de ne rien trouver.
    if ($filtres['prix_min'] > 0 && $filtres['prix_max'] > 0 && $filtres['prix_min'] > $filtres['prix_max']) {
        [$filtres['prix_min'], $filtres['prix_max']] = [$filtres['prix_max'], $filtres['prix_min']];
    }

    return $filtres;
}

/** Vrai si au moins un filtre (hors tri et page) est actif. */
function filtres_actifs(array $filtres): bool
{
    return $filtres['q'] !== '' || $filtres['equipe'] !== '' || $filtres['taille'] !== ''
        || $filtres['prix_min'] > 0 || $filtres['prix_max'] > 0;
}

/**
 * Recherche des maillots visibles selon les filtres.
 *
 * Recherche texte (titre, équipe, saison) : index FULLTEXT, insensible aux accents et à la
 * casse grâce à la collation utf8mb4_unicode_ci ; complétée par LIKE (indispensable pour les
 * recherches de moins de 3 caractères et les saisons comme « 2025/2026 »).
 *
 * @return array{maillots: list<array>, total: int, pages: int, page: int}
 */
function rechercher_maillots(array $filtres, ?int $limite = null): array
{
    $conditions = [SQL_MAILLOT_VISIBLE];
    $parametres = [];

    if ($filtres['categorie'] !== '') {
        $conditions[] = 'c.slug = :categorie';
        $parametres['categorie'] = $filtres['categorie'];
    }
    if ($filtres['equipe'] !== '') {
        $conditions[] = 'e.slug = :equipe';
        $parametres['equipe'] = $filtres['equipe'];
    }
    if ($filtres['prix_min'] > 0) {
        $conditions[] = 'm.prix >= :prix_min';
        $parametres['prix_min'] = $filtres['prix_min'];
    }
    if ($filtres['prix_max'] > 0) {
        $conditions[] = 'm.prix <= :prix_max';
        $parametres['prix_max'] = $filtres['prix_max'];
    }
    if ($filtres['taille'] !== '') {
        $conditions[] = 'EXISTS (SELECT 1 FROM maillot_tailles tf WHERE tf.maillot_id = m.id AND tf.taille = :taille AND tf.stock > 0)';
        $parametres['taille'] = $filtres['taille'];
    }
    if (!empty($filtres['boutique_id'])) {
        $conditions[] = 'm.boutique_id = :boutique_id';
        $parametres['boutique_id'] = (int) $filtres['boutique_id'];
    }

    if ($filtres['q'] !== '') {
        // Échappement des caractères spéciaux de LIKE (% et _)
        $parametres['like'] = '%' . addcslashes($filtres['q'], '%_\\') . '%';
        $condition = 'm.texte_recherche LIKE :like';

        // Mots de 3 lettres ou plus → recherche FULLTEXT (chaque mot obligatoire, préfixe accepté)
        $mots = preg_split('/[^\p{L}\p{N}]+/u', $filtres['q'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $mots = array_values(array_filter($mots, static fn (string $mot): bool => mb_strlen($mot) >= 3));

        if ($mots !== []) {
            $parametres['plein_texte'] = implode(' ', array_map(static fn (string $mot): string => '+' . $mot . '*', $mots));
            $condition = '(MATCH(m.texte_recherche) AGAINST (:plein_texte IN BOOLEAN MODE) OR ' . $condition . ')';
        }

        $conditions[] = $condition;
    }

    $where = ' WHERE ' . implode(' AND ', $conditions);
    $depuis = ' FROM maillots m
        JOIN boutiques b ON b.id = m.boutique_id
        JOIN equipes e ON e.id = m.equipe_id
        JOIN categories c ON c.id = m.categorie_id';

    // Nombre total (pour la pagination)
    $requete = bdd()->prepare('SELECT COUNT(*)' . $depuis . $where);
    $requete->execute($parametres);
    $total = (int) $requete->fetchColumn();

    $parPage = $limite ?? MAILLOTS_PAR_PAGE;
    $pages = max(1, (int) ceil($total / $parPage));
    $page = min(max(1, (int) $filtres['page']), $pages);

    $ordre = match ($filtres['tri']) {
        'prix_asc' => 'm.prix ASC, m.id DESC',
        'prix_desc' => 'm.prix DESC, m.id DESC',
        default => 'm.publie_le DESC, m.id DESC',
    };

    $requete = bdd()->prepare(
        SQL_CARTE_MAILLOT . $where . ' ORDER BY ' . $ordre
        . ' LIMIT ' . (int) $parPage . ' OFFSET ' . (int) (($page - 1) * $parPage)
    );
    $requete->execute($parametres);

    return ['maillots' => $requete->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
}

/** Les derniers maillots publiés (accueil). */
function derniers_maillots(int $nombre = 8): array
{
    $filtres = lire_filtres([]);

    return rechercher_maillots($filtres, $nombre)['maillots'];
}

/* -------------------------------------------------------------------------
   Fiche maillot (§10.4)
   ------------------------------------------------------------------------- */

/** Maillot visible avec sa boutique, son équipe et sa catégorie, ou null. */
function maillot_par_id(int $id): ?array
{
    $requete = bdd()->prepare(
        'SELECT m.*, b.id AS boutique_id, b.nom AS boutique_nom, b.slug AS boutique_slug, b.ville AS boutique_ville,
                b.cree_le AS boutique_cree_le, b.numero_whatsapp AS boutique_whatsapp, b.logo AS boutique_logo,
                b.retrait_sur_place AS boutique_retrait, b.adresse_retrait AS boutique_adresse_retrait,
                e.nom AS equipe_nom, e.slug AS equipe_slug, c.nom AS categorie_nom, c.slug AS categorie_slug
         FROM maillots m
         JOIN boutiques b ON b.id = m.boutique_id
         JOIN equipes e ON e.id = m.equipe_id
         JOIN categories c ON c.id = m.categorie_id
         WHERE m.id = :id AND ' . SQL_MAILLOT_VISIBLE
    );
    $requete->execute(['id' => $id]);
    $maillot = $requete->fetch();

    return $maillot === false ? null : $maillot;
}

/**
 * Stock par taille, dans l'ordre des tailles (seules les tailles proposées par le vendeur).
 *
 * @return array<string, int>
 */
function tailles_du_maillot(int $maillotId): array
{
    $requete = bdd()->prepare('SELECT taille, stock FROM maillot_tailles WHERE maillot_id = :id');
    $requete->execute(['id' => $maillotId]);
    $stocks = $requete->fetchAll(PDO::FETCH_KEY_PAIR);

    $resultat = [];
    foreach (TAILLES as $taille) {
        if (array_key_exists($taille, $stocks)) {
            $resultat[$taille] = (int) $stocks[$taille];
        }
    }

    return $resultat;
}

/** Photos du maillot, la principale en premier. */
function images_du_maillot(int $maillotId): array
{
    $requete = bdd()->prepare('SELECT fichier, miniature FROM maillot_images WHERE maillot_id = :id ORDER BY ordre, id LIMIT 5');
    $requete->execute(['id' => $maillotId]);

    return $requete->fetchAll();
}

/* -------------------------------------------------------------------------
   Boutique publique (§7.4)
   ------------------------------------------------------------------------- */

/** Boutique visible (active ou en grâce) par son adresse, ou null. */
function boutique_par_slug(string $slug): ?array
{
    $requete = bdd()->prepare(
        "SELECT id, nom, slug, ville, description, logo, banniere, numero_whatsapp, retrait_sur_place, adresse_retrait, cree_le
         FROM boutiques WHERE slug = :slug AND statut IN ('active', 'grace') AND supprime_le IS NULL"
    );
    $requete->execute(['slug' => $slug]);
    $boutique = $requete->fetch();

    return $boutique === false ? null : $boutique;
}

/**
 * Note du vendeur (§20) : moyenne des avis VISIBLES et nombre d'avis.
 * « Nouveau vendeur » tant qu'il y a moins de 3 avis. Résultat gardé en mémoire pour la page.
 *
 * @return array{nombre: int, moyenne: float|null, libelle: string}
 */
function note_vendeur(int $boutiqueId): array
{
    static $cache = [];

    if (!isset($cache[$boutiqueId])) {
        $requete = bdd()->prepare('SELECT COUNT(*) AS nombre, AVG(note) AS moyenne FROM avis WHERE boutique_id = :id AND visible = 1');
        $requete->execute(['id' => $boutiqueId]);
        $ligne = $requete->fetch();
        $nombre = (int) $ligne['nombre'];
        $moyenne = $nombre > 0 ? round((float) $ligne['moyenne'], 1) : null;

        $cache[$boutiqueId] = [
            'nombre' => $nombre,
            'moyenne' => $moyenne,
            'libelle' => $nombre < 3 ? 'Nouveau vendeur'
                : '★ ' . number_format((float) $moyenne, 1, ',', '') . ' (' . $nombre . ' avis)',
        ];
    }

    return $cache[$boutiqueId];
}

/* -------------------------------------------------------------------------
   Favoris
   ------------------------------------------------------------------------- */

/** Identifiants des maillots favoris d'un utilisateur (pour afficher les cœurs). */
function ids_favoris(int $utilisateurId): array
{
    $requete = bdd()->prepare('SELECT maillot_id FROM favoris WHERE utilisateur_id = :id');
    $requete->execute(['id' => $utilisateurId]);

    return array_map('intval', $requete->fetchAll(PDO::FETCH_COLUMN));
}

/** Ajoute ou retire un favori. Renvoie true si le maillot est maintenant en favori. */
function basculer_favori(int $utilisateurId, int $maillotId): bool
{
    $suppression = bdd()->prepare('DELETE FROM favoris WHERE utilisateur_id = :u AND maillot_id = :m');
    $suppression->execute(['u' => $utilisateurId, 'm' => $maillotId]);

    if ($suppression->rowCount() > 0) {
        return false;
    }

    bdd()->prepare('INSERT IGNORE INTO favoris (utilisateur_id, maillot_id) VALUES (:u, :m)')
        ->execute(['u' => $utilisateurId, 'm' => $maillotId]);

    return true;
}

/* -------------------------------------------------------------------------
   Affichage : carte de maillot et pagination
   ------------------------------------------------------------------------- */

/**
 * Carte d'un maillot (§10.1) : photo principale, titre, prix en FCFA (texte principal gras),
 * boutique et sa note, badge d'authenticité, mention « Rupture » sans stock.
 */
function carte_maillot(array $maillot): string
{
    $rupture = (int) $maillot['stock_total'] <= 0;
    $authenticite = LIBELLES_AUTHENTICITE[$maillot['authenticite']] ?? '';
    $note = note_vendeur((int) ($maillot['boutique_id'] ?? 0));

    $photo = !empty($maillot['miniature'])
        ? '<img src="' . e(url_image_maillot($maillot['miniature'])) . '" alt="' . e($maillot['titre']) . '"'
            . ' width="400" height="400" loading="lazy" decoding="async">'
        : '<span class="sm-carte-maillot__vide" aria-hidden="true"><i class="bi bi-image"></i><span>Photo à venir</span></span>';

    return '<article class="sm-carte-maillot' . ($rupture ? ' sm-carte-maillot--rupture' : '') . '">'
        . '<a class="sm-carte-maillot__lien" href="' . e(url_maillot($maillot)) . '">'
        . '<span class="sm-carte-maillot__photo">' . $photo
        . '<span class="sm-badge-authenticite sm-badge-authenticite--' . e($maillot['authenticite']) . '">' . e($authenticite) . '</span>'
        . ($rupture ? '<span class="sm-carte-maillot__rupture">Rupture</span>' : '')
        . '</span>'
        . '<span class="sm-carte-maillot__corps">'
        . '<span class="sm-carte-maillot__equipe">' . e($maillot['equipe_nom']) . ' · ' . e($maillot['saison']) . '</span>'
        . '<span class="sm-carte-maillot__titre">' . e($maillot['titre']) . '</span>'
        . '<span class="sm-carte-maillot__prix">' . e(format_fcfa((int) $maillot['prix'])) . '</span>'
        . '<span class="sm-carte-maillot__boutique"><i class="bi bi-shop" aria-hidden="true"></i> '
        . e($maillot['boutique_nom']) . ' <span class="sm-carte-maillot__note">· '
        . e($note['nombre'] < 3 ? 'Nouveau' : '★ ' . number_format((float) $note['moyenne'], 1, ',', '')) . '</span></span>'
        . '</span>'
        . '</a>'
        . '</article>';
}

/**
 * Pagination accessible (Bootstrap). $adresse reçoit un numéro de page et renvoie l'adresse.
 */
function pagination(int $page, int $pages, callable $adresse): string
{
    if ($pages <= 1) {
        return '';
    }

    $lien = static function (int $numero, string $texte, bool $actif = false, bool $desactive = false, string $aria = '') use ($adresse): string {
        $classes = 'page-item' . ($actif ? ' active' : '') . ($desactive ? ' disabled' : '');
        if ($desactive) {
            return '<li class="' . $classes . '"><span class="page-link">' . $texte . '</span></li>';
        }

        return '<li class="' . $classes . '"><a class="page-link" href="' . e($adresse($numero)) . '"'
            . ($actif ? ' aria-current="page"' : '') . ($aria !== '' ? ' aria-label="' . e($aria) . '"' : '') . '>' . $texte . '</a></li>';
    };

    $html = '<nav class="sm-pagination" aria-label="Pages de résultats"><ul class="pagination justify-content-center flex-wrap">';
    $html .= $lien($page - 1, '<i class="bi bi-chevron-left" aria-hidden="true"></i>', false, $page <= 1, 'Page précédente');

    // Pages autour de la page actuelle, avec la première et la dernière
    $numeros = array_unique(array_filter(
        [1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages],
        static fn (int $n): bool => $n >= 1 && $n <= $pages
    ));
    sort($numeros);

    $precedent = 0;
    foreach ($numeros as $numero) {
        if ($precedent > 0 && $numero > $precedent + 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $html .= $lien($numero, (string) $numero, $numero === $page);
        $precedent = $numero;
    }

    $html .= $lien($page + 1, '<i class="bi bi-chevron-right" aria-hidden="true"></i>', false, $page >= $pages, 'Page suivante');

    return $html . '</ul></nav>';
}

/**
 * Chemin interne sûr pour revenir à une page après une action (favori…), sinon $defaut.
 * Jamais d'adresse externe.
 */
function chemin_retour(string $valeur, string $defaut = ''): string
{
    if ($valeur !== '' && preg_match('#^[a-z0-9/_.\-]+(\?[^\s<>"]*)?$#i', $valeur) === 1
        && !str_starts_with($valeur, '/') && !str_contains($valeur, '..')) {
        return $valeur;
    }

    return $defaut;
}
