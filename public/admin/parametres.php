<?php
/**
 * SAMA MAILLOT — administration : paramètres du site (Annexe A, §23.2).
 *
 * Chaque paramètre a un type contrôlé (nombre entier borné, téléphone, e-mail, texte).
 * Seules les valeurs modifiées sont enregistrées ; chaque changement est journalisé
 * (ancienne → nouvelle valeur).
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();

/** [groupe => [clé => [libellé, type, min, max, unité]]] — type : entier, telephone, email, texte */
const PARAMETRES_EDITABLES = [
    'Commandes et livraison' => [
        'confirmation_vendeur_heures' => ['Délai de confirmation par le vendeur', 'entier', 1, 168, 'heures'],
        'commandes_livraison_simultanees' => ['Commandes à la livraison en cours par client', 'entier', 1, 20, 'commandes'],
        'refus_livraison_avant_blocage' => ['Refus à la livraison avant blocage', 'entier', 1, 20, 'refus'],
        'deblocage_sans_code_heures' => ['Passage automatique en « Reçue » après livraison', 'entier', 1, 720, 'heures'],
        'deblocage_avec_code_heures' => ['Délai de réclamation avec code (paiement en ligne)', 'entier', 1, 720, 'heures'],
    ],
    'Signalements' => [
        'signalements_avant_suspension' => ['Clients différents avant suspension automatique', 'entier', 1, 50, 'clients'],
        'signalements_periode_jours' => ['Période de comptage des signalements', 'entier', 1, 365, 'jours'],
    ],
    'Abonnements vendeurs' => [
        'essai_gratuit_jours' => ['Durée de l\'essai gratuit', 'entier', 0, 365, 'jours'],
        'abonnement_prix_fcfa' => ['Prix de l\'abonnement mensuel', 'entier', 0, 1000000, 'FCFA'],
        'abonnement_rappel_jours' => ['Rappel avant la fin de l\'abonnement', 'entier', 0, 30, 'jours'],
        'abonnement_grace_jours' => ['Période de grâce avant masquage', 'entier', 0, 30, 'jours'],
    ],
    'Maillots et mise en avant' => [
        'photos_par_maillot' => ['Photos maximum par maillot', 'entier', 1, 20, 'photos'],
        'photo_taille_max_mo' => ['Taille maximale d\'une photo', 'entier', 1, 20, 'Mo'],
        'mise_en_avant_7_jours_fcfa' => ['Mise en avant 7 jours', 'entier', 0, 1000000, 'FCFA'],
        'mise_en_avant_30_jours_fcfa' => ['Mise en avant 30 jours', 'entier', 0, 1000000, 'FCFA'],
    ],
    'Paiements et versements' => [
        'paiement_en_ligne_minutes' => ['Délai de paiement en ligne', 'entier', 5, 1440, 'minutes'],
        'commission_pourcentage' => ['Commission sur les ventes', 'entier', 0, 50, '%'],
        'frais_transaction_pourcentage' => ['Frais de transaction déduits', 'entier', 0, 20, '%'],
        'versement_minimum_fcfa' => ['Versement minimum', 'entier', 0, 1000000, 'FCFA'],
        'versement_jours' => ['Jours de versement', 'texte', 0, 60, ''],
        'numero_wave_plateforme' => ['Numéro Wave de la plateforme', 'telephone', 0, 0, ''],
        'lien_paiement_wave' => ['Lien de paiement Wave Business (abonnements)', 'lien_wave', 0, 0, ''],
        'litige_reponse_vendeur_heures' => ['Délai de réponse du vendeur à un litige', 'entier', 1, 720, 'heures'],
        'avis_delai_jours' => ['Délai pour laisser un avis', 'entier', 1, 365, 'jours'],
    ],
    'Informations légales (mentions légales)' => [
        'entreprise_raison_sociale' => ['Raison sociale', 'texte', 0, 120, ''],
        'entreprise_forme_juridique' => ['Forme juridique et capital', 'texte', 0, 120, ''],
        'entreprise_adresse' => ['Adresse du siège', 'texte', 0, 200, ''],
        'entreprise_ninea' => ['NINEA', 'texte', 0, 30, ''],
        'entreprise_rccm' => ['RCCM', 'texte', 0, 40, ''],
        'entreprise_responsable' => ['Directeur de la publication', 'texte', 0, 120, ''],
        'cdp_declaration' => ['Récépissé de déclaration CDP', 'texte', 0, 60, ''],
    ],
    'Support' => [
        'numero_whatsapp_support' => ['Numéro WhatsApp du support', 'telephone', 0, 0, ''],
        'email_support' => ['E-mail du support (copie des messages de contact)', 'email', 0, 0, ''],
    ],
];

/** Paramètres enregistrés dès maintenant mais utilisés par une étape à venir : [clé => étape]. */
const PARAMETRES_A_VENIR = [
    'mise_en_avant_7_jours_fcfa' => 'mise en avant (V1.1)',
    'mise_en_avant_30_jours_fcfa' => 'mise en avant (V1.1)',
    'commission_pourcentage' => 'commission (V2)',
];

$actuels = bdd()->query('SELECT cle, valeur FROM parametres')->fetchAll(PDO::FETCH_KEY_PAIR);
$erreurs = [];
$saisies = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } else {
        $nouvelles = [];

        foreach (PARAMETRES_EDITABLES as $parametres) {
            foreach ($parametres as $cle => [$libelle, $type, $min, $max]) {
                $saisie = trim((string) ($_POST['p'][$cle] ?? ''));
                $saisies[$cle] = $saisie;

                $valeur = match ($type) {
                    'entier' => preg_match('/^\d+$/', $saisie) === 1 && (int) $saisie >= $min && (int) $saisie <= $max ? (string) (int) $saisie : null,
                    'telephone' => $saisie === '' ? '' : normaliser_telephone($saisie),
                    'email' => $saisie === '' ? '' : normaliser_email($saisie),
                    'lien_wave' => $saisie === '' ? '' : normaliser_lien_wave($saisie),
                    default => mb_strlen($saisie) <= $max ? $saisie : null,
                };

                if ($valeur === null) {
                    $erreurs[$cle] = match ($type) {
                        'entier' => 'Nombre entier entre ' . $min . ' et ' . $max . '.',
                        'telephone' => 'Numéro sénégalais de 9 chiffres (ou laissez vide).',
                        'email' => 'Adresse e-mail valide (ou laissez vide).',
                        'lien_wave' => 'Lien Wave Business commençant par https://pay.wave.com/ (ou laissez vide).',
                        default => 'Texte trop long.',
                    };
                } elseif ($valeur !== ($actuels[$cle] ?? '')) {
                    $nouvelles[$cle] = $valeur;
                }
            }
        }

        if ($erreurs === []) {
            $requete = bdd()->prepare(
                'INSERT INTO parametres (cle, valeur, description) VALUES (:cle, :valeur, :description)
                 ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)'
            );
            foreach ($nouvelles as $cle => $valeur) {
                $libelle = '';
                foreach (PARAMETRES_EDITABLES as $parametres) {
                    $libelle = $parametres[$cle][0] ?? $libelle;
                }
                $requete->execute(['cle' => $cle, 'valeur' => $valeur, 'description' => $libelle]);
                journaliser_action('admin_parametre_modifie', (int) $admin['id'], 'parametre', null, $cle . ' = ' . ($actuels[$cle] ?? ''), $cle . ' = ' . $valeur);
            }

            message_flash('succes', $nouvelles === [] ? 'Aucun changement.' : count($nouvelles) . ' paramètre(s) mis à jour.');
            rediriger('admin/parametres.php');
        }
        $erreurs['general'] = 'Certaines valeurs ne sont pas valides : corrigez les champs signalés.';
    }
}

$titre_page = 'Paramètres';
$admin_actif = 'parametres';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<div class="sm-admin__titre">
    <div>
        <h1>Paramètres</h1>
        <p>Réglages du site (annexe A du cahier des charges). Chaque modification est journalisée.</p>
    </div>
</div>

<?= alerte_formulaire(array_intersect_key($erreurs, ['general' => 1])) ?>

<form method="post" novalidate>
    <?= champ_csrf() ?>
    <div class="row g-3">
        <?php foreach (PARAMETRES_EDITABLES as $groupe => $parametres): ?>
            <div class="col-12 col-xl-6">
                <section class="sm-panneau">
                    <header class="sm-panneau__entete">
                        <span class="sm-kpi__icone sm-teinte--or" aria-hidden="true"><i class="bi bi-gear-fill"></i></span>
                        <div><h2><?= e($groupe) ?></h2></div>
                    </header>
                    <div class="d-grid gap-3">
                        <?php foreach ($parametres as $cle => [$libelle, $type, $min, $max, $unite]): ?>
                            <?php
                            $valeur = $saisies[$cle] ?? ($actuels[$cle] ?? '');
                            if ($type === 'telephone' && !isset($saisies[$cle]) && $valeur !== '') {
                                $valeur = formater_telephone($valeur);
                            }
                            ?>
                            <div class="sm-parametre">
                                <label class="form-label small fw-semibold mb-1" for="p-<?= e($cle) ?>"><?= e($libelle) ?></label>
                                <div class="input-group input-group-sm">
                                    <?php if ($type === 'telephone'): ?><span class="input-group-text">+221</span><?php endif; ?>
                                    <input class="form-control<?= isset($erreurs[$cle]) ? ' is-invalid' : '' ?>" id="p-<?= e($cle) ?>" name="p[<?= e($cle) ?>]"
                                           value="<?= e($valeur) ?>"
                                           type="<?= $type === 'entier' ? 'number' : ($type === 'email' ? 'email' : ($type === 'telephone' ? 'tel' : ($type === 'lien_wave' ? 'url' : 'text'))) ?>"
                                           <?= $type === 'entier' ? 'min="' . e($min) . '" max="' . e($max) . '" step="1" inputmode="numeric"' : '' ?>
                                           <?= isset($erreurs[$cle]) ? 'aria-invalid="true" aria-describedby="erreur-' . e($cle) . '"' : '' ?>>
                                    <?php if ($unite !== ''): ?><span class="input-group-text"><?= e($unite) ?></span><?php endif; ?>
                                </div>
                                <?php if (isset($erreurs[$cle])): ?>
                                    <div class="invalid-feedback d-block" id="erreur-<?= e($cle) ?>"><?= e($erreurs[$cle]) ?></div>
                                <?php elseif (isset(PARAMETRES_A_VENIR[$cle])): ?>
                                    <div class="form-text"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Pris en compte avec l'étape : <?= e(PARAMETRES_A_VENIR[$cle]) ?>.</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="sm-barre-enregistrer">
        <button class="btn btn-or btn-lg" type="submit"><i class="bi bi-check2" aria-hidden="true"></i> Enregistrer les paramètres</button>
    </div>
</form>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
