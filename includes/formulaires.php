<?php
/**
 * SAMA MAILLOT — aides à l'affichage des formulaires (Bootstrap 5.3, accessibles).
 *
 * Exemple :
 *   <input class="form-control<?= classe_erreur($erreurs, 'email') ?>" id="email" name="email"
 *          <?= aria_erreur($erreurs, 'email') ?>>
 *   <?= message_erreur($erreurs, 'email') ?>
 */

declare(strict_types=1);

/** « is-invalid » si le champ a une erreur (bordure rouge Bootstrap). */
function classe_erreur(array $erreurs, string $champ): string
{
    return isset($erreurs[$champ]) ? ' is-invalid' : '';
}

/** Lie le champ à son message d'erreur pour les lecteurs d'écran. */
function aria_erreur(array $erreurs, string $champ): string
{
    return isset($erreurs[$champ])
        ? 'aria-invalid="true" aria-describedby="erreur-' . e($champ) . '"'
        : '';
}

/** Message d'erreur sous le champ (vide s'il n'y a pas d'erreur). */
function message_erreur(array $erreurs, string $champ): string
{
    if (!isset($erreurs[$champ])) {
        return '';
    }

    return '<div class="invalid-feedback d-block" id="erreur-' . e($champ) . '">' . e($erreurs[$champ]) . '</div>';
}

/** Bandeau d'erreur général en haut d'un formulaire (ex. « Identifiants incorrects »). */
function alerte_formulaire(array $erreurs): string
{
    if (!isset($erreurs['general'])) {
        return '';
    }

    return '<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> '
        . e($erreurs['general']) . '</div>';
}

/**
 * Champ mot de passe avec bouton « afficher / masquer » (icône œil, §8.6).
 * Le mot de passe n'est jamais réaffiché après une erreur (champ toujours vide).
 *
 * @param string      $autocomplete 'current-password' (connexion) ou 'new-password' (création)
 * @param string|null $aide         texte d'aide sous le champ
 */
function champ_mot_de_passe(
    string $nom,
    string $libelle,
    array $erreurs,
    string $autocomplete,
    ?string $aide = null
): string {
    $id = 'champ-' . $nom;
    $idAide = 'aide-' . $nom;
    $decrit = [];

    if ($aide !== null) {
        $decrit[] = $idAide;
    }
    if (isset($erreurs[$nom])) {
        $decrit[] = 'erreur-' . $nom;
    }

    $html = '<div class="mb-3">';
    $html .= '<label class="form-label" for="' . e($id) . '">' . e($libelle) . '</label>';
    $html .= '<div class="input-group">';
    $html .= '<input class="form-control' . classe_erreur($erreurs, $nom) . '" type="password"'
        . ' id="' . e($id) . '" name="' . e($nom) . '" autocomplete="' . e($autocomplete) . '"'
        . ' maxlength="72" required'
        . (isset($erreurs[$nom]) ? ' aria-invalid="true"' : '')
        . ($decrit !== [] ? ' aria-describedby="' . e(implode(' ', $decrit)) . '"' : '')
        . '>';
    $html .= '<button class="btn btn-contour sm-bouton-oeil" type="button" data-afficher-mdp="' . e($id) . '"'
        . ' aria-label="Afficher le mot de passe" aria-pressed="false">'
        . '<i class="bi bi-eye" aria-hidden="true"></i></button>';
    $html .= '</div>';

    if ($aide !== null) {
        $html .= '<div class="form-text" id="' . e($idAide) . '">' . e($aide) . '</div>';
    }

    $html .= message_erreur($erreurs, $nom);
    $html .= '</div>';

    return $html;
}
