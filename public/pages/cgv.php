<?php
/**
 * SAMA MAILLOT — conditions générales de vente (§7.3, §13, §16, §25).
 * Ventes entre acheteurs et vendeurs, paiement (dont le mandat d'encaissement), livraison,
 * réclamations et remboursements. Délais lus dans les paramètres : le texte suit les réglages.
 */
require __DIR__ . '/../../includes/config.php';

$titre_page = 'Conditions générales de vente';
$description_page = 'Conditions générales de vente de SAMA MAILLOT : commande, paiement ' . (paiement_en_ligne_actif() ? 'en ligne ou ' : '') . 'à la livraison, livraison, réclamations et remboursements.';
$page_active = 'cgv';

$confirmation = parametre('confirmation_vendeur_heures', '24');
$avec_code = parametre('deblocage_avec_code_heures', '24');
$sans_code = parametre('deblocage_sans_code_heures', '48');
$minutes_paiement = parametre('paiement_en_ligne_minutes', '30');
$en_ligne = paiement_en_ligne_actif();   // éteint : paiement à la livraison uniquement
$reponse_litige = parametre('litige_reponse_vendeur_heures', '48');

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-texte">
    <div class="container">
        <article class="sm-texte-legal">
            <header class="sm-texte-legal__entete">
                <span class="sm-sur-titre">Informations légales</span>
                <h1>Conditions générales de vente</h1>
                <p class="text-body-secondary mb-0">Dernière mise à jour : <?= e(DATE_DOCUMENTS_LEGAUX) ?></p>
            </header>

            <nav class="sm-sommaire mb-4" aria-label="Sommaire">
                <ol>
                    <li><a href="#parties">Parties et champ</a></li>
                    <li><a href="#annonces">Annonces et prix</a></li>
                    <li><a href="#commande">Commande</a></li>
                    <li><a href="#paiement">Paiement</a></li>
                    <li><a href="#livraison">Livraison et code</a></li>
                    <li><a href="#annulation">Annulation</a></li>
                    <li><a href="#reclamation">Réclamations</a></li>
                    <li><a href="#remboursement">Remboursements</a></li>
                    <li><a href="#litiges">Litiges</a></li>
                </ol>
            </nav>

            <h2 id="parties">1. Parties et champ d'application</h2>
            <p>
                Chaque vente est conclue entre l'<strong>acheteur</strong> et le <strong>vendeur</strong> (boutique indépendante).
                <?= e(nom_editeur()) ?> (« <?= e(NOM_SITE) ?> ») est l'intermédiaire : il fournit la plateforme et, pour les paiements
                en ligne, encaisse le prix pour le compte du vendeur (article 4.2). Ces conditions s'appliquent à toute commande
                passée sur le site, y compris via le bouton « Commander via WhatsApp ».
            </p>

            <h2 id="annonces">2. Annonces et prix</h2>
            <ul>
                <li>Chaque annonce indique l'équipe, la saison, le type, l'état, les tailles disponibles et l'<strong>authenticité</strong> :
                    <em>Officiel</em>, <em>Réplique</em> ou <em>Version fan</em>. Le vendeur garantit l'exactitude de ces informations.</li>
                <li>Les prix sont en francs CFA (FCFA), toutes taxes comprises le cas échéant. Le flocage éventuel est indiqué en supplément.</li>
                <li>Les frais de livraison dépendent de la boutique et de votre zone ; ils sont affichés avant la confirmation.
                    Si une boutique n'a pas de tarif pour votre zone, les frais sont « à convenir » avec le vendeur avant qu'il confirme la commande.</li>
            </ul>

            <h2 id="commande">3. Commande</h2>
            <ol>
                <li>Vous ajoutez des maillots au panier ; un panier contenant plusieurs boutiques donne <strong>une commande par boutique</strong>.</li>
                <li>Vous indiquez votre adresse et un numéro de téléphone, puis vérifiez le <strong>récapitulatif</strong> (articles, frais, total) avant de confirmer.</li>
                <li>Le prix et le stock sont vérifiés au moment de la confirmation ; s'ils ont changé, le nouveau récapitulatif vous est présenté.</li>
                <li>Le vendeur dispose de <strong><?= e($confirmation) ?> heures</strong> pour confirmer la commande ; sans réponse, elle est annulée automatiquement.</li>
                <li>Vous recevez une confirmation et suivez chaque étape dans « Mes commandes ».</li>
            </ol>

            <h2 id="paiement">4. Paiement</h2>
            <?php if ($en_ligne): ?><h3>4.1 Paiement à la livraison</h3><?php endif; ?>
            <p>
                Vous payez le vendeur directement à la réception (espèces ou Wave). <?= e(NOM_SITE) ?> ne détient pas cet argent
                et <strong>ne peut pas rembourser</strong> ; en cas de problème, vous pouvez ouvrir une réclamation et signaler le vendeur.
                Le nombre de commandes à la livraison en cours est limité à <?= e(parametre('commandes_livraison_simultanees', '3')) ?> par client.
            </p>
            <?php if ($en_ligne): ?>
            <h3>4.2 Paiement en ligne (paiement sécurisé)</h3>
            <ul>
                <li>Vous payez le total (maillots + livraison) par Wave, Orange Money, Free Money ou carte, via un prestataire de paiement agréé.
                    Le maillot est réservé pendant <?= e($minutes_paiement) ?> minutes ; sans paiement, la commande est annulée.</li>
                <li><strong>Mandat d'encaissement</strong> : en ouvrant sa boutique, le vendeur donne mandat à <?= e(NOM_SITE) ?> d'encaisser
                    le prix en son nom. Votre paiement à <?= e(NOM_SITE) ?> vaut donc paiement au vendeur.</li>
                <li><?= e(NOM_SITE) ?> <strong>conserve l'argent</strong> jusqu'à la réception, puis le reverse au vendeur
                    (déduction faite des frais de transaction éventuels, affichés au vendeur). En cas de problème reconnu, vous êtes remboursé.</li>
                <li>Un code de livraison à 4 chiffres vous est donné : ne le communiquez <strong>qu'à la réception</strong> du maillot.</li>
            </ul>
            <?php else: ?>
            <p>Le paiement en ligne n'est pas proposé pour le moment : toutes les commandes se paient à la livraison.</p>
            <?php endif; ?>

            <h2 id="livraison">5. Livraison<?= $en_ligne ? ' et code de livraison' : '' ?></h2>
            <ul>
                <li>La livraison est assurée par le vendeur (lui-même ou un livreur de son choix), selon le délai indicatif de sa zone ; le retrait sur place est possible si la boutique le propose.</li>
                <li>Le vendeur vous appelle au numéro indiqué pour organiser la remise. Vous pouvez le contacter sur WhatsApp depuis votre commande.</li>
                <?php if ($en_ligne): ?>
                <li>Paiement en ligne : à la remise, vous donnez votre code ; le vendeur le saisit, ce qui prouve la livraison.
                    S'il déclare la livraison sans code (colis remis à un proche, par exemple), vous en êtes averti.</li>
                <?php endif; ?>
                <li>Vous confirmez la réception depuis votre commande. Sans réclamation, la commande est considérée comme reçue
                    <?= e($avec_code) ?> heures après une livraison prouvée par le code, ou <?= e($sans_code) ?> heures après une livraison déclarée sans code.</li>
            </ul>

            <h2 id="annulation">6. Annulation</h2>
            <ul>
                <li>Vous pouvez annuler une commande tant qu'elle n'est pas expédiée (motif demandé si le vendeur l'a déjà confirmée).</li>
                <li>Le vendeur peut refuser ou annuler une commande avec un motif (rupture, client injoignable, refus à la livraison…).</li>
                <?php if ($en_ligne): ?><li>Commande payée en ligne puis annulée : vous êtes remboursé du montant total (article 8).</li><?php endif; ?>
            </ul>

            <h2 id="reclamation">7. Réclamations</h2>
            <ul>
                <li>Motifs : maillot non reçu, différent de l'annonce, mauvaise taille, abîmé, contrefaçon présentée comme officielle, autre.</li>
                <li>Délai : <?= $en_ligne ? 'dès l\'expédition et jusqu\'à la fin du délai de réception ci-dessus (paiement en ligne) ; pour un paiement à la livraison,' : '' ?>
                    jusqu'à <?= e(parametre('reclamation_delai_heures', '48')) ?> heures après la confirmation de réception.</li>
                <li>Vous décrivez le problème et pouvez joindre jusqu'à 3 photos. Le vendeur a <?= e($reponse_litige) ?> heures pour répondre.
                    <?php if ($en_ligne): ?>Pendant la réclamation, l'argent d'un paiement en ligne est <strong>bloqué</strong>.<?php endif; ?></li>
                <li>Si vous trouvez un accord avec le vendeur, vous clôturez la réclamation. Sinon, <?= e(NOM_SITE) ?> examine les éléments et décide.
                    Une réclamation jugée fondée compte comme un signalement du vendeur.</li>
            </ul>

            <h2 id="remboursement">8. Remboursements</h2>
            <p>
                <?php if ($en_ligne): ?>
                Paiement en ligne : en cas d'annulation d'une commande payée ou de réclamation jugée fondée, <?= e(NOM_SITE) ?> vous
                rembourse le montant payé, sur le moyen de paiement utilisé ou par Wave, et vous communique la référence du remboursement.
                <?php endif; ?>
                Paiement à la livraison : <?= e(NOM_SITE) ?> ne détenant pas l'argent, le remboursement relève d'un accord avec le vendeur ;
                la médiation de <?= e(NOM_SITE) ?> reste possible. Les échanges et retours hors réclamation se font selon les conditions du vendeur.
            </p>

            <h2 id="litiges">9. Litiges et droit applicable</h2>
            <p>
                Les présentes conditions sont soumises au droit sénégalais, notamment la loi n° 2008-08 du 25 janvier 2008 sur les
                transactions électroniques. En cas de désaccord, contactez d'abord le support via le
                <a href="<?= e(url_page('contact')) ?>">formulaire de contact</a> ; une solution amiable est recherchée en priorité.
                À défaut, les tribunaux compétents de Dakar sont saisis.
            </p>
        </article>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
