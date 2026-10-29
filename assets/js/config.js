/* ==========================================================
   Fondation FAMO — configuration du système de dons
   Choisissez UN mode dans "mode" puis remplissez la section
   correspondante. Tant que rien n'est configuré, le bouton
   de don ouvre un e-mail à la fondation (aucune page cassée).
   ========================================================== */
window.FAMO_CONFIG = {

  /* "zeffy" (recommandé, formulaire officiel en fenêtre modale) | "stripe" | "paypal" | "" */
  mode: "zeffy",

  /* --- Option A : bouton modal Zeffy (0 % de frais, reçus fiscaux
         automatiques, dons récurrents, FR/EN). Lien fourni par Zeffy
         (Campagnes > Partager mon formulaire > Autres options de
         partage > Intégrer sur mon site). --- */
  widget: {
    formLink: "https://www.zeffy.com/embed/donation-form/donate-to-change-lives-24631?modal=true"
  },

  /* --- Option B : Stripe Payment Links (1 lien par montant).
         "custom" = lien "le client choisit le montant". --- */
  stripe: {
    once:    { "10": "", "20": "", "50": "", "100": "", "250": "" },
    monthly: { "10": "", "20": "", "50": "", "100": "", "250": "" },
    custom:  ""
  },

  /* --- Option C : bouton PayPal Donate (ID du bouton hébergé). --- */
  paypal: {
    hostedButtonId: ""
  },

  contactEmail: "contact@famofondation.org"
};
