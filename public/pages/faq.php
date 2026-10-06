<?php
/**
 * SAMA MAILLOT — questions fréquentes (§7.3) : paiement, livraison, code de livraison, réclamations,
 * abonnement. Réponses en texte simple (échappé), délais et montants lus dans les paramètres.
 * Données structurées FAQPage pour les moteurs de recherche (§30).
 */
require __DIR__ . '/../../includes/config.php';

$titre_page = 'Questions fréquentes';
$description_page = 'Toutes les réponses sur SAMA MAILLOT : paiement en ligne ou à la livraison, livraison, code de livraison, réclamations, abonnement vendeur.';
$page_active = 'faq';

$confirmation = parametre('confirmation_vendeur_heures', '24');

$rubriques = [
    'Commander et payer' => [
        'Comment commander un maillot ?' =>
            'Choisissez votre maillot et votre taille, ajoutez-le au panier, indiquez votre adresse et votre numéro, puis confirmez. '
            . 'Vous pouvez aussi cliquer sur « Commander via WhatsApp » depuis la fiche du maillot.',
        'Quels moyens de paiement sont acceptés ?' =>
            'Paiement en ligne (Wave, Orange Money, Free Money ou carte) ou paiement à la livraison, en espèces ou par Wave, directement au vendeur.',
        'Pourquoi payer en ligne ?' =>
            'C\'est le paiement protégé : SAMA MAILLOT garde l\'argent jusqu\'à ce que vous receviez votre maillot. '
            . 'S\'il n\'arrive pas ou n\'est pas conforme, vous êtes remboursé après examen de votre réclamation.',
        'Les prix sont-ils en FCFA ?' =>
            'Oui, tous les prix sont en francs CFA. Les frais de livraison s\'affichent avant la confirmation de la commande.',
        'J\'ai commandé dans plusieurs boutiques, pourquoi plusieurs commandes ?' =>
            'Chaque boutique prépare et livre ses propres maillots : votre panier donne donc une commande par boutique, chacune avec son suivi.',
    ],
    'Livraison' => [
        'Qui livre ma commande ?' =>
            'Le vendeur lui-même ou un livreur de son choix. Il vous appelle au numéro indiqué pour organiser la remise. '
            . 'Certaines boutiques proposent aussi le retrait sur place, gratuitement.',
        'Combien de temps pour être livré ?' =>
            'Le vendeur confirme votre commande dans les ' . $confirmation . ' heures, puis livre selon le délai affiché pour votre zone.',
        'Ma zone n\'a pas de tarif, que se passe-t-il ?' =>
            'Vous pouvez quand même commander en paiement à la livraison : le vendeur vous indique le prix de la livraison avant de confirmer.',
        'Comment suivre ma commande ?' =>
            'Dans « Mes commandes » : chaque étape est affichée (confirmée, en route, livrée…) et vous êtes prévenu par notification et par e-mail.',
    ],
    'Code de livraison' => [
        'À quoi sert le code de livraison ?' =>
            'Pour une commande payée en ligne, un code à 4 chiffres prouve que vous avez bien reçu votre maillot. '
            . 'Il est visible dans votre commande, jamais chez le vendeur.',
        'Quand dois-je donner mon code ?' =>
            'Uniquement quand vous avez le maillot en main. Ne le donnez jamais avant, ni par téléphone.',
        'Et si le vendeur déclare la livraison sans code ?' =>
            'Vous êtes averti et vous avez ' . parametre('deblocage_sans_code_heures', '48') . ' heures pour signaler un problème avant que l\'argent soit versé au vendeur.',
    ],
    'Réclamations et remboursements' => [
        'Mon maillot n\'est pas conforme, que faire ?' =>
            'Ouvrez une réclamation depuis votre commande : choisissez le motif, décrivez le problème et ajoutez jusqu\'à 3 photos. '
            . 'Le vendeur a ' . parametre('litige_reponse_vendeur_heures', '48') . ' heures pour répondre, et SAMA MAILLOT tranche si vous ne trouvez pas d\'accord.',
        'Comment suis-je remboursé ?' =>
            'Si vous avez payé en ligne et que la réclamation est jugée fondée (ou que la commande payée est annulée), '
            . 'SAMA MAILLOT vous rembourse et vous envoie la référence du remboursement.',
        'Et si j\'ai payé à la livraison ?' =>
            'SAMA MAILLOT ne détient pas l\'argent et ne peut pas rembourser, mais fait la médiation avec le vendeur. '
            . 'Une réclamation fondée compte comme un signalement du vendeur.',
        'Comment signaler un vendeur ?' =>
            'Si vous avez commandé chez lui, un lien « Signaler » apparaît sur sa boutique et dans votre commande. '
            . 'Au bout de ' . parametre('signalements_avant_suspension', '3') . ' clients différents, la boutique est suspendue automatiquement.',
    ],
    'Vendeurs et abonnement' => [
        'Comment ouvrir ma boutique ?' =>
            'Cliquez sur « Devenir vendeur », renseignez votre boutique et votre téléphone : vous profitez de '
            . parametre('essai_gratuit_jours', (string) ESSAI_GRATUIT_JOURS) . ' jours d\'essai gratuit.',
        'Combien coûte l\'abonnement ?' =>
            format_fcfa(prix_abonnement()) . ' par mois, payables pour 1, 3 ou 6 mois par Wave depuis « Mon abonnement ». Aucune commission sur vos ventes.',
        'Quand suis-je payé pour une vente en ligne ?' =>
            'L\'argent devient disponible à la réception par le client, puis il est versé sur votre numéro Wave le '
            . str_replace(',', ' et le ', parametre('versement_jours', 'mardi,vendredi')) . '.',
        'Puis-je vendre des répliques ?' =>
            'Oui, à condition de les déclarer comme « Réplique ». Présenter une réplique comme officielle est interdit et sanctionné.',
    ],
];

// Données structurées FAQPage (§30)
$donnees_faq = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];
foreach ($rubriques as $questions) {
    foreach ($questions as $question => $reponse) {
        $donnees_faq['mainEntity'][] = ['@type' => 'Question', 'name' => $question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $reponse]];
    }
}

require __DIR__ . '/../../includes/entete.php';
?>

<script type="application/ld+json"><?= json_encode($donnees_faq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>

<section class="sm-page-texte">
    <div class="container">
        <article class="sm-texte-legal">
            <header class="sm-texte-legal__entete">
                <span class="sm-sur-titre">Aide</span>
                <h1>Questions fréquentes</h1>
                <p class="text-body-secondary mb-0">
                    Vous ne trouvez pas votre réponse ? <a href="<?= e(url_page('contact')) ?>">Contactez-nous</a>.
                </p>
            </header>

            <?php $n = 0; ?>
            <?php foreach ($rubriques as $rubrique => $questions): ?>
                <h2><?= e($rubrique) ?></h2>
                <div class="accordion sm-faq" id="faq-<?= $n ?>">
                    <?php foreach ($questions as $question => $reponse): ?>
                        <?php $n++; ?>
                        <div class="accordion-item">
                            <h3 class="accordion-header m-0">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#reponse-<?= $n ?>" aria-expanded="false" aria-controls="reponse-<?= $n ?>">
                                    <?= e($question) ?>
                                </button>
                            </h3>
                            <div id="reponse-<?= $n ?>" class="accordion-collapse collapse">
                                <div class="accordion-body"><?= e($reponse) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </article>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
