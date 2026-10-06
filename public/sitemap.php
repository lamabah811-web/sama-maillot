<?php
/**
 * SAMA MAILLOT — plan du site pour les moteurs de recherche (§30), servi à l'adresse /sitemap.xml.
 *
 * Pages publiques seulement : accueil, catalogue, catégories et équipes qui ont des maillots,
 * fiches des maillots visibles, boutiques visibles, pages d'information. Jamais les espaces privés.
 */
require __DIR__ . '/../includes/config.php';

$pdo = bdd();
$adresses = [];

$ajouter = static function (string $adresse, ?string $modifie = null, string $frequence = 'weekly', string $priorite = '0.5') use (&$adresses): void {
    $adresses[] = ['loc' => $adresse, 'lastmod' => $modifie !== null ? date('Y-m-d', strtotime($modifie)) : null,
        'changefreq' => $frequence, 'priority' => $priorite];
};

$ajouter(url(), null, 'daily', '1.0');
$ajouter(url_catalogue(), null, 'daily', '0.9');

foreach ($pdo->query(
    'SELECT DISTINCT c.slug FROM maillots m JOIN boutiques b ON b.id = m.boutique_id JOIN categories c ON c.id = m.categorie_id
     WHERE c.actif = 1 AND ' . SQL_MAILLOT_VISIBLE
)->fetchAll(PDO::FETCH_COLUMN) as $categorie) {
    $ajouter(url_catalogue(['categorie' => $categorie]), null, 'daily', '0.8');
}

foreach ($pdo->query(
    'SELECT DISTINCT e.slug FROM maillots m JOIN boutiques b ON b.id = m.boutique_id JOIN equipes e ON e.id = m.equipe_id
     WHERE e.actif = 1 AND ' . SQL_MAILLOT_VISIBLE
)->fetchAll(PDO::FETCH_COLUMN) as $equipe) {
    $ajouter(url_catalogue(['equipe' => $equipe]), null, 'weekly', '0.7');
}

foreach ($pdo->query(
    'SELECT m.id, m.slug, m.modifie_le FROM maillots m JOIN boutiques b ON b.id = m.boutique_id
     WHERE ' . SQL_MAILLOT_VISIBLE . ' ORDER BY m.id DESC LIMIT 45000'
)->fetchAll() as $maillot) {
    $ajouter(url_maillot($maillot), $maillot['modifie_le'], 'weekly', '0.8');
}

foreach ($pdo->query(
    "SELECT b.slug, b.modifie_le FROM boutiques b WHERE b.statut IN ('active', 'grace') AND b.supprime_le IS NULL
     AND EXISTS (SELECT 1 FROM maillots m WHERE m.boutique_id = b.id AND m.statut = 'publie' AND m.supprime_le IS NULL)"
)->fetchAll() as $boutique) {
    $ajouter(url_boutique($boutique['slug']), $boutique['modifie_le'], 'weekly', '0.6');
}

foreach (['a-propos', 'comment-ca-marche', 'devenir-vendeur', 'faq', 'contact', 'cgu', 'cgv', 'confidentialite', 'mentions-legales', 'politique-vendeur'] as $page) {
    if (page_existe($page)) {
        $ajouter(url_page($page), null, 'monthly', '0.3');
    }
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (array_unique($adresses, SORT_REGULAR) as $a) {
    echo '  <url><loc>' . htmlspecialchars($a['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>'
        . ($a['lastmod'] !== null ? '<lastmod>' . $a['lastmod'] . '</lastmod>' : '')
        . '<changefreq>' . $a['changefreq'] . '</changefreq><priority>' . $a['priority'] . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
