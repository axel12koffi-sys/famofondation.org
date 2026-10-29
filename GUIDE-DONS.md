# Guide — activer les dons en ligne (Fondation FAMO)

Le site est prêt à recevoir des dons dès qu'un mode est activé dans
`assets/js/config.js`. Tant que rien n'est configuré, le bouton « Soutenir la
FAMO » ouvre un e-mail à la fondation avec le montant choisi — aucune page
cassée pour les visiteurs.

## Option A — Zeffy (recommandée, déjà activée)

Plateforme de collecte pour OBNL, **0 % de frais** (plateforme et transaction —
Zeffy se finance par les contributions volontaires des donateurs), avec reçus
fiscaux automatiques, dons récurrents, et interface FR/EN.

Le formulaire **« Donate to Change Lives »** est déjà créé et branché au site
via le bouton modal officiel Zeffy (`assets/js/config.js`, `mode: "zeffy"`) :
un clic sur « Faire un don » ouvre le formulaire en fenêtre superposée.

Pour changer de formulaire ou en créer un nouveau :
1. Zeffy → Campagnes → « Partager mon formulaire » → Autres options de
   partage → Intégrer sur mon site → copier le lien (se termine par
   `?modal=true`).
2. Coller ce lien dans `config.js` → `widget.formLink`.

### Suivi automatique des dons Zeffy

Zeffy envoie un webhook (`payment.completed`) à chaque don complété. Pour le
brancher sur le tableau de bord du backend :
1. Choisir un jeton secret quelconque (une longue chaîne aléatoire) et le
   mettre dans `backend/config.php` → `zeffy_webhook_token`.
2. Dans Zeffy : Réglages → Organisation → Intégrations → Webhook → coller
   `https://votre-domaine/backend/webhook-zeffy.php?token=VOTRE_JETON`.
3. Faire un don test (petit montant, remboursable) pour vérifier qu'il
   apparaît dans le tableau de bord.
4. Si le don n'apparaît pas : activer temporairement
   `zeffy_webhook_debug: true` dans `config.php`, refaire un don test, puis
   ouvrir `backend/webhook-zeffy.log` pour voir le format exact envoyé par
   Zeffy et ajuster si besoin les noms de champs lus dans
   `backend/webhook-zeffy.php`. Remettre `debug` à `false` ensuite et
   supprimer le fichier `.log`.

Sans webhook configuré, les dons Zeffy restent visibles et exportables
directement depuis le tableau de bord Zeffy — le webhook sert uniquement à
les centraliser avec les autres sources dans le tableau de bord FAMO.

## Option B — Stripe Payment Links

Frais standard ~2,9 % + 0,30 $ par transaction (rabais OBNL possible sur
demande auprès de Stripe). Aucun code requis.

1. Créer un compte Stripe pour la fondation.
2. Tableau de bord → Paiements → Payment Links → créer un lien par montant
   (10, 20, 50, 100, 250 $). Pour le don mensuel : produits récurrents.
3. Créer aussi un lien « Le client choisit le montant » pour « Autre montant ».
4. Dans `config.js` :
   - `mode: "stripe"`
   - coller chaque lien dans `stripe.once`, `stripe.monthly` et `stripe.custom`.

## Option C — PayPal Donate

Tarif associatif PayPal (sous réserve de validation du statut d'organisme).

1. Compte PayPal Business de la fondation → paypal.com/donate/buttons.
2. Créer un bouton de don et copier son **hosted_button_id**.
3. Dans `config.js` :
   - `mode: "paypal"`
   - `paypal.hostedButtonId: "XXXXXXXXXX"`

Le montant choisi sur le site est transmis automatiquement à PayPal.

## Vérification

Après configuration, ouvrir `comment-aider.html`, choisir un montant et
cliquer « Soutenir la FAMO » : la page de paiement doit s'ouvrir dans un
nouvel onglet (ou le formulaire Zeffy s'afficher directement dans la page).
