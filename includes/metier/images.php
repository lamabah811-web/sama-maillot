<?php
/**
 * SAMA MAILLOT — réception et traitement des images envoyées (§29.4, §24).
 *
 * Chaque image est contrôlée puis RÉ-ENCODÉE par GD :
 *   - type réel vérifié (finfo), extension et taille contrôlées, dimensions minimales ;
 *   - ré-encodage complet en WebP : le fichier d'origine n'est jamais conservé, ce qui
 *     supprime toutes les métadonnées (dont la localisation GPS) et tout contenu caché ;
 *   - nom de fichier aléatoire ; les dossiers d'envoi interdisent l'exécution de PHP
 *     (public/uploads/.htaccess).
 */

declare(strict_types=1);

const IMAGE_TAILLE_MAX_OCTETS = 5 * 1024 * 1024;     // 5 Mo (Annexe A)
const IMAGE_PIXELS_MAX = 40_000_000;                  // protection contre les images géantes
const IMAGE_QUALITE_WEBP = 80;
const PHOTO_MAILLOT_COTE_MIN = 300;                // px : en dessous, la photo est trop floue
const IMAGE_TYPES_ACCEPTES = [
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png' => ['png'],
    'image/webp' => ['webp'],
    'image/avif' => ['avif'],
];

/** Erreur d'envoi à afficher telle quelle à l'utilisateur (message en français simple). */
class ErreurImage extends RuntimeException
{
}

/** Dossier physique des envois : public/uploads/<sous-dossier>/ (créé si besoin). */
function dossier_envois(string $sousDossier): string
{
    $dossier = DOSSIER_PUBLIC . '/uploads/' . $sousDossier;

    if (!is_dir($dossier) && !mkdir($dossier, 0755, true) && !is_dir($dossier)) {
        throw new RuntimeException('Impossible de créer le dossier d\'envoi ' . $sousDossier);
    }

    return $dossier;
}

/**
 * Réorganise $_FILES['champ'] d'un champ multiple en liste de fichiers.
 *
 * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
 */
function fichiers_envoyes(string $champ): array
{
    $brut = $_FILES[$champ] ?? null;

    if (!is_array($brut) || !isset($brut['name'])) {
        return [];
    }

    if (!is_array($brut['name'])) {
        return $brut['error'] === UPLOAD_ERR_NO_FILE ? [] : [$brut];
    }

    $liste = [];
    foreach ($brut['name'] as $n => $nom) {
        if ((int) $brut['error'][$n] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $liste[] = [
            'name' => (string) $nom,
            'type' => (string) $brut['type'][$n],
            'tmp_name' => (string) $brut['tmp_name'][$n],
            'error' => (int) $brut['error'][$n],
            'size' => (int) $brut['size'][$n],
        ];
    }

    return $liste;
}

/**
 * Contrôle une image envoyée et l'ouvre avec GD.
 *
 * @throws ErreurImage si l'image est refusée (message lisible par l'utilisateur)
 */
function ouvrir_image_envoyee(array $fichier, int $coteMinimum): GdImage
{
    $nom = $fichier['name'] ?? 'image';

    if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new ErreurImage(match ((int) $fichier['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "« $nom » est trop lourde (5 Mo maximum).",
            UPLOAD_ERR_PARTIAL => "« $nom » n'a été reçue qu'en partie. Réessayez.",
            default => "« $nom » n'a pas pu être reçue. Réessayez.",
        });
    }

    if (!is_uploaded_file($fichier['tmp_name'])) {
        throw new ErreurImage("« $nom » n'a pas pu être reçue. Réessayez.");
    }

    // Taille maximale : paramètre « photo_taille_max_mo » (réglable par l'admin), 5 Mo par défaut.
    $tailleMax = max(1, min(20, (int) parametre('photo_taille_max_mo', (string) intdiv(IMAGE_TAILLE_MAX_OCTETS, 1048576)))) * 1048576;
    if ((int) $fichier['size'] > $tailleMax) {
        throw new ErreurImage("« $nom » est trop lourde (" . intdiv($tailleMax, 1048576) . ' Mo maximum).');
    }

    // Type RÉEL du contenu : seul lui compte. L'extension du nom est ignorée, car beaucoup
    // d'images téléchargées sont du WebP ou de l'AVIF nommé « .jpg ». Sans risque : l'image est
    // de toute façon réencodée en WebP sous un nom aléatoire.
    $typeReel = (new finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']) ?: '';

    if (in_array($typeReel, ['image/heic', 'image/heif'], true)) {
        throw new ErreurImage("« $nom » est au format HEIC (iPhone), pas encore accepté. Sur l'iPhone : Réglages > Appareil photo > "
            . "Formats > « Le plus compatible », ou envoyez une capture d'écran de la photo.");
    }
    if (!isset(IMAGE_TYPES_ACCEPTES[$typeReel]) || ($typeReel === 'image/avif' && !function_exists('imagecreatefromavif'))) {
        throw new ErreurImage("« $nom » n'est pas une image JPG, PNG, WebP ou AVIF valide.");
    }

    $infos = @getimagesize($fichier['tmp_name']);
    if ($infos === false) {
        throw new ErreurImage("« $nom » n'est pas une image valide.");
    }

    [$largeur, $hauteur] = $infos;
    if ($largeur * $hauteur > IMAGE_PIXELS_MAX) {
        throw new ErreurImage("« $nom » est trop grande en pixels.");
    }
    if ($largeur < $coteMinimum || $hauteur < $coteMinimum) {
        throw new ErreurImage("« $nom » est trop petite ($largeur × $hauteur px). Minimum : $coteMinimum × $coteMinimum px.");
    }

    $image = match ($typeReel) {
        'image/jpeg' => @imagecreatefromjpeg($fichier['tmp_name']),
        'image/png' => @imagecreatefrompng($fichier['tmp_name']),
        'image/webp' => @imagecreatefromwebp($fichier['tmp_name']),
        'image/avif' => @imagecreatefromavif($fichier['tmp_name']),
    };

    if (!$image instanceof GdImage) {
        throw new ErreurImage("« $nom » est abîmée ou illisible.");
    }

    // Photos de téléphone : on respecte le sens de prise de vue (EXIF), avant de tout effacer.
    if ($typeReel === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($fichier['tmp_name']);
        $rotation = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($rotation !== 0) {
            $tournee = imagerotate($image, $rotation, 0);
            if ($tournee instanceof GdImage) {
                $image = $tournee;
            }
        }
    }

    return $image;
}

/**
 * Copie redimensionnée d'une image.
 *   - $recadrer = false : l'image entière tient dans $largeurMax × $hauteurMax (jamais agrandie) ;
 *   - $recadrer = true  : l'image remplit exactement $largeurMax × $hauteurMax (découpe au centre).
 */
function redimensionner_image(GdImage $source, int $largeurMax, int $hauteurMax, bool $recadrer): GdImage
{
    $largeur = imagesx($source);
    $hauteur = imagesy($source);
    $xSource = 0;
    $ySource = 0;
    $largeurSource = $largeur;
    $hauteurSource = $hauteur;

    if ($recadrer) {
        $ratioCible = $largeurMax / $hauteurMax;
        if ($largeur / $hauteur > $ratioCible) {
            $largeurSource = (int) round($hauteur * $ratioCible);
            $xSource = (int) (($largeur - $largeurSource) / 2);
        } else {
            $hauteurSource = (int) round($largeur / $ratioCible);
            $ySource = (int) (($hauteur - $hauteurSource) / 2);
        }
        $largeurCible = $largeurMax;
        $hauteurCible = $hauteurMax;
    } else {
        $echelle = min(1, $largeurMax / $largeur, $hauteurMax / $hauteur);
        $largeurCible = max(1, (int) round($largeur * $echelle));
        $hauteurCible = max(1, (int) round($hauteur * $echelle));
    }

    $cible = imagecreatetruecolor($largeurCible, $hauteurCible);
    // Conserve la transparence des PNG / WebP
    imagealphablending($cible, false);
    imagesavealpha($cible, true);
    imagefill($cible, 0, 0, imagecolorallocatealpha($cible, 255, 255, 255, 127));

    imagecopyresampled($cible, $source, 0, 0, $xSource, $ySource, $largeurCible, $hauteurCible, $largeurSource, $hauteurSource);

    return $cible;
}

/** Enregistre une image en WebP sous un nom aléatoire et renvoie ce nom. */
function enregistrer_webp(GdImage $image, string $sousDossier): string
{
    $nom = bin2hex(random_bytes(16)) . '.webp';

    if (!imagewebp($image, dossier_envois($sousDossier) . '/' . $nom, IMAGE_QUALITE_WEBP)) {
        throw new RuntimeException('Impossible d\'enregistrer l\'image ' . $nom);
    }

    return $nom;
}

/**
 * Photo de maillot (§29.4) : grande version 1200 px et miniature carrée 400 px, en WebP.
 *
 * @return array{fichier: string, miniature: string}
 * @throws ErreurImage
 */
function traiter_photo_maillot(array $fichier): array
{
    $image = ouvrir_image_envoyee($fichier, PHOTO_MAILLOT_COTE_MIN);

    $grande = enregistrer_webp(redimensionner_image($image, 1200, 1200, false), 'maillots');
    $miniature = enregistrer_webp(redimensionner_image($image, 400, 400, true), 'maillots');

    return ['fichier' => $grande, 'miniature' => $miniature];
}

/**
 * Logo de boutique (carré 400 px) ou bannière (1600 × 400 px), en WebP.
 *
 * @param string $type 'logo' ou 'banniere'
 * @throws ErreurImage
 */
function traiter_image_boutique(array $fichier, string $type): string
{
    if ($type === 'logo') {
        return enregistrer_webp(redimensionner_image(ouvrir_image_envoyee($fichier, 200), 400, 400, true), 'boutiques');
    }

    $image = ouvrir_image_envoyee($fichier, 600);

    return enregistrer_webp(redimensionner_image($image, 1600, 400, true), 'boutiques');
}

/** Supprime un fichier envoyé (nom vérifié : jamais de chemin arbitraire). */
function supprimer_fichier_envoye(string $sousDossier, ?string $nom): void
{
    if ($nom === null || preg_match('/^[a-f0-9]{32}\.webp$/', $nom) !== 1) {
        return;
    }

    $chemin = DOSSIER_PUBLIC . '/uploads/' . $sousDossier . '/' . $nom;
    if (is_file($chemin)) {
        @unlink($chemin);
    }
}

/** Adresse publique d'un logo ou d'une bannière de boutique. */
function url_image_boutique(string $nom): string
{
    return url('uploads/boutiques/' . rawurlencode(basename($nom)));
}
