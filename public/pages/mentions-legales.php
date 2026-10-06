<?php
/**
 * SAMA MAILLOT — mentions légales (§7.3, §25).
 * Informations de l'éditeur : Administration → Paramètres → « Informations légales ».
 */
require __DIR__ . '/../../includes/config.php';

$titre_page = 'Mentions légales';
$description_page = 'Mentions légales de SAMA MAILLOT : éditeur, hébergeur, propriété intellectuelle et contact.';
$page_active = 'mentions-legales';
$email_support = parametre('email_support');
$whatsapp_support = parametre('numero_whatsapp_support');

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-texte">
    <div class="container">
        <article class="sm-texte-legal">
            <header class="sm-texte-legal__entete">
                <span class="sm-sur-titre">Informations légales</span>
                <h1>Mentions légales</h1>
                <p class="text-body-secondary mb-0">Dernière mise à jour : <?= e(DATE_DOCUMENTS_LEGAUX) ?></p>
            </header>

            <h2>1. Éditeur du site</h2>
            <p>
                Le site <strong><?= e(NOM_SITE) ?></strong> (<?= e(APP_URL) ?>) est édité par :<br>
                <strong><?= info_legale('entreprise_raison_sociale', 'Raison sociale') ?></strong>,
                <?= info_legale('entreprise_forme_juridique', 'Forme juridique et capital') ?><br>
                Siège : <?= info_legale('entreprise_adresse', 'Adresse du siège') ?><br>
                NINEA : <?= info_legale('entreprise_ninea', 'NINEA') ?> · RCCM : <?= info_legale('entreprise_rccm', 'RCCM') ?><br>
                Directeur de la publication : <?= info_legale('entreprise_responsable', 'Nom du responsable') ?>
            </p>
            <p>
                Contact :
                <?php if ($email_support !== ''): ?><a href="mailto:<?= e($email_support) ?>"><?= e($email_support) ?></a><?php endif; ?>
                <?php if ($whatsapp_support !== ''): ?> · WhatsApp <?= e(formater_telephone($whatsapp_support)) ?><?php endif; ?>
                · <a href="<?= e(url_page('contact')) ?>">formulaire de contact</a>.
            </p>

            <h2>2. Hébergeur</h2>
            <p>
                <?= e(HEBERGEUR['nom']) ?>, <?= e(HEBERGEUR['adresse']) ?> —
                <a href="<?= e(HEBERGEUR['site']) ?>" rel="noopener" target="_blank"><?= e(preg_replace('#^https://#', '', HEBERGEUR['site'])) ?></a>.
            </p>

            <h2>3. Activité</h2>
            <p>
                <?= e(NOM_SITE) ?> est une <strong>place de marché</strong> (marketplace) : elle met en relation des acheteurs et des
                boutiques indépendantes qui vendent des maillots de football au Sénégal. Les maillots sont vendus par les
                vendeurs, sous leur responsabilité ; <?= e(NOM_SITE) ?> n'est pas le vendeur des articles.
                Voir les <a href="<?= e(url_page('cgu')) ?>">conditions générales d'utilisation</a> et les
                <a href="<?= e(url_page('cgv')) ?>">conditions générales de vente</a>.
            </p>

            <h2>4. Données personnelles</h2>
            <p>
                Les données personnelles sont traitées conformément à la loi n° 2008-12 du 25 janvier 2008 sur la protection
                des données à caractère personnel. Déclaration auprès de la Commission de protection des données personnelles (CDP) :
                <?= info_legale('cdp_declaration', 'Numéro de récépissé') ?>.
                Détails et exercice de vos droits : <a href="<?= e(url_page('confidentialite')) ?>">politique de confidentialité</a>.
            </p>

            <h2>5. Propriété intellectuelle</h2>
            <p>
                Le nom <?= e(NOM_SITE) ?>, le logo, la charte graphique et les textes du site sont protégés ; toute reproduction
                sans autorisation est interdite. Les photos et descriptions des annonces appartiennent aux vendeurs qui les publient
                et qui garantissent en détenir les droits.
            </p>
            <p>
                Les noms et logos des clubs, des sélections et des équipementiers cités appartiennent à leurs propriétaires
                respectifs ; ils sont mentionnés uniquement pour décrire les maillots. <?= e(NOM_SITE) ?> n'est affilié à aucun
                club, fédération ou équipementier. Chaque annonce indique clairement si le maillot est <strong>officiel</strong>,
                une <strong>réplique</strong> ou une <strong>version fan</strong>.
            </p>

            <h2>6. Signaler un contenu</h2>
            <p>
                Tout contenu illicite (contrefaçon présentée comme officielle, photo volée, contenu inapproprié) peut être signalé
                depuis l'annonce ou via le <a href="<?= e(url_page('contact')) ?>">formulaire de contact</a>. L'équipe l'examine
                et le retire s'il est avéré (voir la <a href="<?= e(url_page('politique-vendeur')) ?>">politique vendeur</a>).
            </p>
        </article>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
