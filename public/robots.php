<?php
/**
 * SAMA MAILLOT — robots.txt (§30), servi à l'adresse /robots.txt.
 *
 * Production : les espaces privés (compte, vendeur, admin, panier, paiement…) ne sont pas explorés,
 * et le plan du site est indiqué. Local et recette : rien n'est exploré (§33.1).
 */
require __DIR__ . '/../includes/config.php';

header('Content-Type: text/plain; charset=UTF-8');

if (APP_ENV !== 'production') {
    echo "User-agent: *\nDisallow: /\n";
    exit;
}

$base = (string) parse_url(APP_URL, PHP_URL_PATH);
$base = rtrim($base, '/');

$prives = ['admin/', 'compte/', 'vendeur/', 'panier/', 'paiement/', 'webhooks/', 'connexion/',
    'mot-de-passe/', 'signalement/', 'litige/', 'commande/', 'favoris/', 'facebook/', 'whatsapp.php', 'pages/bientot.php'];

echo "User-agent: *\n";
foreach ($prives as $chemin) {
    echo 'Disallow: ' . $base . '/' . $chemin . "\n";
}
echo "\nSitemap: " . url('sitemap.xml') . "\n";
