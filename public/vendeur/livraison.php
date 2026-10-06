<?php
/**
 * SAMA MAILLOT — espace vendeur : zones et tarifs de livraison (§9.3).
 *
 * Le vendeur active les zones qu'il dessert (liste gérée par l'admin), fixe le tarif
 * (payé par le client) et un délai indicatif. Option « Retrait sur place » gratuite.
 */
require __DIR__ . '/../../includes/config.php';

[$utilisateur, $boutique] = exiger_vendeur();
$boutiqueId = (int) $boutique['id'];

$zones = zones_et_tarifs($boutiqueId);
$saisies = [];
foreach ($zones as $zone) {
    $saisies[(int) $zone['id']] = [
        'active' => $zone['tarif'] !== null,
        'tarif' => $zone['tarif'] !== null ? (string) $zone['tarif'] : '',
        'delai' => (string) ($zone['delai'] ?? '24-48h'),
    ];
}
$retrait = (int) $boutique['retrait_sur_place'] === 1;
$adresse_retrait = (string) ($boutique['adresse_retrait'] ?? '');
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
    } else {
        $recus = is_array($_POST['zone'] ?? null) ? $_POST['zone'] : [];

        foreach ($saisies as $zoneId => $saisie) {
            $ligne = is_array($recus[$zoneId] ?? null) ? $recus[$zoneId] : [];
            $active = ($ligne['active'] ?? '') === '1';
            $tarif = preg_replace('/\D+/', '', is_string($ligne['tarif'] ?? null) ? $ligne['tarif'] : '') ?? '';
            $delai = is_string($ligne['delai'] ?? null) ? $ligne['delai'] : '';

            $saisies[$zoneId] = ['active' => $active, 'tarif' => $tarif, 'delai' => $delai];

            if ($active) {
                if ($tarif === '' || (int) $tarif > 50000) {
                    $erreurs['zone-' . $zoneId] = 'Indiquez un tarif entre 0 et 50 000 FCFA.';
                }
                if (!in_array($delai, DELAIS_LIVRAISON, true)) {
                    $erreurs['zone-' . $zoneId] = 'Choisissez un délai dans la liste.';
                }
            }
        }

        $retrait = case_cochee('retrait_sur_place');
        $adresse_retrait = champ_post('adresse_retrait');
        if ($retrait && (mb_strlen($adresse_retrait) < 5 || mb_strlen($adresse_retrait) > 255)) {
            $erreurs['adresse_retrait'] = 'Indiquez l\'adresse de retrait (quartier, repère…).';
        }

        $nombreZones = count(array_filter($saisies, static fn (array $s): bool => $s['active']));
        if ($nombreZones === 0 && !$retrait) {
            $erreurs['general'] = 'Activez au moins une zone de livraison ou le retrait sur place.';
        }

        if ($erreurs === []) {
            $pdo = bdd();
            $pdo->beginTransaction();

            $pdo->prepare('DELETE FROM tarifs_livraison WHERE boutique_id = :b')->execute(['b' => $boutiqueId]);
            $ajout = $pdo->prepare('INSERT INTO tarifs_livraison (boutique_id, zone_id, tarif, delai) VALUES (:b, :z, :t, :d)');
            foreach ($saisies as $zoneId => $saisie) {
                if ($saisie['active']) {
                    $ajout->execute(['b' => $boutiqueId, 'z' => $zoneId, 't' => (int) $saisie['tarif'], 'd' => $saisie['delai']]);
                }
            }
            $pdo->prepare('UPDATE boutiques SET retrait_sur_place = :r, adresse_retrait = :a WHERE id = :b')
                ->execute(['r' => $retrait ? 1 : 0, 'a' => $retrait ? $adresse_retrait : null, 'b' => $boutiqueId]);

            $pdo->commit();

            message_flash('succes', 'Zones et tarifs de livraison enregistrés.');
            rediriger('vendeur/livraison.php');
        }
    }
}

$titre_page = 'Livraison';
$sans_indexation = true;
$page_active = 'vendeur';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-catalogue">
    <div class="container">
        <?= menu_vendeur('livraison') ?>

        <h1 class="sm-titre-section mb-1">Zones et tarifs de livraison</h1>
        <p class="text-body-secondary mb-4">Les frais de livraison sont payés par le client. Activez les zones que vous desservez.</p>

        <?= alerte_formulaire($erreurs) ?>

        <form method="post" novalidate>
            <?= champ_csrf() ?>

            <div class="sm-zones">
                <?php foreach ($zones as $zone): ?>
                    <?php $zid = (int) $zone['id']; $s = $saisies[$zid]; ?>
                    <fieldset class="sm-zone<?= $s['active'] ? ' sm-zone--active' : '' ?>">
                        <legend class="visually-hidden"><?= e($zone['nom']) ?></legend>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="zone-<?= e($zid) ?>" name="zone[<?= e($zid) ?>][active]" value="1"<?= $s['active'] ? ' checked' : '' ?>>
                            <label class="form-check-label" for="zone-<?= e($zid) ?>">
                                <strong><?= e($zone['nom']) ?></strong>
                                <span class="d-block small text-body-secondary"><?= e($zone['description']) ?></span>
                            </label>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small" for="tarif-<?= e($zid) ?>">Tarif</label>
                                <div class="input-group">
                                    <input class="form-control<?= classe_erreur($erreurs, 'zone-' . $zid) ?>" type="text" inputmode="numeric" id="tarif-<?= e($zid) ?>"
                                           name="zone[<?= e($zid) ?>][tarif]" value="<?= e($s['tarif']) ?>" placeholder="1500">
                                    <span class="input-group-text">FCFA</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small" for="delai-<?= e($zid) ?>">Délai indicatif</label>
                                <select class="form-select" id="delai-<?= e($zid) ?>" name="zone[<?= e($zid) ?>][delai]">
                                    <?php foreach (DELAIS_LIVRAISON as $delai): ?>
                                        <option value="<?= e($delai) ?>"<?= $s['delai'] === $delai ? ' selected' : '' ?>><?= e($delai) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?= message_erreur($erreurs, 'zone-' . $zid) ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>

            <fieldset class="sm-tuile mt-4">
                <legend class="h5 fw-bold mb-2">Retrait sur place</legend>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="retrait_sur_place" name="retrait_sur_place" value="1"<?= $retrait ? ' checked' : '' ?>>
                    <label class="form-check-label" for="retrait_sur_place">Le client peut venir chercher son maillot (gratuit)</label>
                </div>
                <label class="form-label" for="adresse_retrait">Adresse de retrait</label>
                <input class="form-control<?= classe_erreur($erreurs, 'adresse_retrait') ?>" type="text" id="adresse_retrait" name="adresse_retrait"
                       value="<?= e($adresse_retrait) ?>" maxlength="255" placeholder="Ex. : Médina, rue 22, en face de la pharmacie" <?= aria_erreur($erreurs, 'adresse_retrait') ?>>
                <?= message_erreur($erreurs, 'adresse_retrait') ?>
            </fieldset>

            <button class="btn btn-or mt-4" type="submit"><i class="bi bi-floppy" aria-hidden="true"></i> Enregistrer</button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
