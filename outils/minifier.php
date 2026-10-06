<?php
/**
 * SAMA MAILLOT — minification du CSS et du JavaScript du site (§29.3).
 *
 *   php outils/minifier.php
 *
 * Produit public/css/sama.min.css et public/js/sama.min.js à partir des fichiers sources, qui restent
 * lisibles et sont les seuls à modifier. Le site sert automatiquement la version minifiée tant qu'elle
 * est plus récente que la source (fonction ressource()) : relancer ce script après chaque modification.
 *
 * Minification volontairement PRUDENTE (sans outil externe) : retrait des commentaires, des retraits et
 * des espaces superflus ; le code lui-même n'est jamais réécrit. En JavaScript, les retours à la ligne
 * sont conservés (insertion automatique des points-virgules).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$public = dirname(__DIR__) . '/public';

/** CSS : commentaires retirés, espaces réduits autour de { } ; , et après « : » dans les déclarations. */
function minifier_css(string $css): string
{
    $css = preg_replace('#/\*.*?\*/#s', '', $css);                 // commentaires (le CSS n'en a pas dans les chaînes)
    $css = preg_replace('/\s+/', ' ', $css);                          // espaces multiples → un seul
    $css = preg_replace('/\s*([{};,>])\s*/', '$1', $css);             // autour de { } ; , >
    $css = preg_replace('/;}/', '}', $css);                            // dernier point-virgule d'un bloc
    $css = preg_replace('/([{;])\s*([a-z-]+)\s*:\s*/i', '$1$2:', $css); // « propriété : valeur » → « propriété:valeur »

    return trim($css) . "\n";
}

/**
 * JS : seuls les commentaires sur des lignes entières (« // … » et blocs « /* … *\/ » commençant en
 * début de ligne) et les retraits sont retirés. Aucun commentaire en fin de ligne de code n'est
 * touché : impossible de casser une chaîne ou une expression régulière contenant « // ».
 */
function minifier_js(string $js): string
{
    $sortie = [];
    $dansBloc = false;

    foreach (preg_split('/\R/', $js) as $ligne) {
        $nette = trim($ligne);
        if ($dansBloc) {
            if (str_contains($nette, '*/')) {
                $dansBloc = false;
            }
            continue;
        }
        if (str_starts_with($nette, '/*')) {
            $dansBloc = !str_contains($nette, '*/');
            continue;
        }
        if ($nette === '' || str_starts_with($nette, '//')) {
            continue;
        }
        $sortie[] = $nette;
    }

    return implode("\n", $sortie) . "\n";
}

$fichiers = [
    'css/sama.css' => 'minifier_css',
    'js/sama.js' => 'minifier_js',
];

foreach ($fichiers as $chemin => $fonction) {
    $source = $public . '/' . $chemin;
    $cible = preg_replace('/\.(css|js)$/', '.min.$1', $source);
    $contenu = (string) file_get_contents($source);
    $minifie = $fonction($contenu);
    file_put_contents($cible, $minifie);
    printf("%-14s %6.1f Ko → %6.1f Ko (gzip : %5.1f Ko → %5.1f Ko)\n", $chemin, strlen($contenu) / 1024, strlen($minifie) / 1024,
        strlen(gzencode($contenu, 6)) / 1024, strlen(gzencode($minifie, 6)) / 1024);
}
