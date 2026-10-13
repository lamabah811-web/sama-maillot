<?php
/**
 * SAMA MAILLOT — validation et mise en forme des données saisies dans les formulaires.
 *
 * Les fonctions « valider_* » renvoient un message d'erreur en français simple,
 * ou null si la valeur est correcte.
 */

declare(strict_types=1);

/**
 * Lit un champ texte envoyé par formulaire POST, sans espaces inutiles.
 * Renvoie une chaîne vide si le champ est absent ou n'est pas du texte.
 */
function champ_post(string $nom): string
{
    $valeur = $_POST[$nom] ?? '';

    return is_string($valeur) ? trim($valeur) : '';
}

/** Lit une case à cocher envoyée par formulaire POST. */
function case_cochee(string $nom): bool
{
    return isset($_POST[$nom]) && $_POST[$nom] === '1';
}

/* -------------------------------------------------------------------------
   Téléphone sénégalais (§8.3) : +221 suivi de 9 chiffres commençant par
   70, 75, 76, 77 ou 78.
   ------------------------------------------------------------------------- */

/**
 * Met un numéro au format unique « +221771234567 », ou renvoie null s'il est invalide.
 * Accepte : « 77 123 45 67 », « 771234567 », « +221 77 123 45 67 », « 00221771234567 »...
 */
function normaliser_telephone(string $saisie): ?string
{
    // On ne garde que les chiffres (espaces, points, tirets et « + » retirés).
    $chiffres = preg_replace('/\D+/', '', $saisie) ?? '';

    // Retire l'indicatif du Sénégal s'il a été saisi.
    if (str_starts_with($chiffres, '00221') && strlen($chiffres) === 14) {
        $chiffres = substr($chiffres, 5);
    } elseif (str_starts_with($chiffres, '221') && strlen($chiffres) === 12) {
        $chiffres = substr($chiffres, 3);
    }

    if (preg_match('/^7[05678]\d{7}$/', $chiffres) !== 1) {
        return null;
    }

    return '+221' . $chiffres;
}

/** Affichage lisible : « +221771234567 » → « +221 77 123 45 67 ». */
function formater_telephone(?string $telephone): string
{
    if ($telephone === null || preg_match('/^\+221(\d{2})(\d{3})(\d{2})(\d{2})$/', $telephone, $m) !== 1) {
        return (string) $telephone;
    }

    return "+221 {$m[1]} {$m[2]} {$m[3]} {$m[4]}";
}

/** Numéro partiellement masqué pour les messages : « +221 77 *** ** 67 ». */
function masquer_telephone(string $telephone): string
{
    if (preg_match('/^\+221(\d{2})\d{5}(\d{2})$/', $telephone, $m) !== 1) {
        return '';
    }

    return "+221 {$m[1]} *** ** {$m[2]}";
}

/* -------------------------------------------------------------------------
   Autres champs
   ------------------------------------------------------------------------- */

/** Adresse e-mail en minuscules, ou null si elle est invalide. */
function normaliser_email(string $saisie): ?string
{
    $email = mb_strtolower(trim($saisie));

    if ($email === '' || mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return null;
    }

    return $email;
}

/**
 * Mot de passe (§8.6) : 8 caractères minimum, au moins une lettre et un chiffre.
 * Maximum 72 octets : au-delà, password_hash() (bcrypt) ignorerait la fin.
 */
function valider_mot_de_passe(string $motDePasse): ?string
{
    if (mb_strlen($motDePasse) < 8) {
        return 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if (strlen($motDePasse) > 72) {
        return 'Le mot de passe est trop long (72 caractères maximum).';
    }
    if (preg_match('/\p{L}/u', $motDePasse) !== 1 || preg_match('/\d/', $motDePasse) !== 1) {
        return 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
    }

    return null;
}

/** Prénom ou nom : 2 à 50 caractères, lettres, espaces, tirets et apostrophes. */
function valider_nom_personne(string $valeur, string $libelle): ?string
{
    if ($valeur === '') {
        return "Le $libelle est obligatoire.";
    }
    if (mb_strlen($valeur) < 2 || mb_strlen($valeur) > 50) {
        return "Le $libelle doit contenir entre 2 et 50 caractères.";
    }
    if (preg_match("/^[\p{L}][\p{L} '’-]*$/u", $valeur) !== 1) {
        return "Le $libelle ne peut contenir que des lettres, des espaces, des tirets et des apostrophes.";
    }

    return null;
}

/**
 * Adresse lisible à partir d'un nom (§9.2) : « Dakar Foot Store » → « dakar-foot-store ».
 */
function creer_slug(string $texte): string
{
    $texte = mb_strtolower($texte);

    // Retire les accents : « é » → « e », « ç » → « c »...
    if (class_exists('Normalizer')) {
        $texte = (string) Normalizer::normalize($texte, Normalizer::FORM_D);
        $texte = preg_replace('/\p{Mn}+/u', '', $texte) ?? $texte;
    } else {
        // Sans l'extension intl (PHP en ligne de commande, certains hébergements) : lettres accentuées du français.
        $texte = strtr($texte, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e',
            'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ú' => 'u', 'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae']);
    }

    $texte = preg_replace('/[^a-z0-9]+/', '-', $texte) ?? '';

    return trim($texte, '-');
}
