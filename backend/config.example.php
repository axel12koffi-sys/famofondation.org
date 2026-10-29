<?php
/* ==========================================================
   Fondation FAMO — configuration du backend de suivi des dons
   1. Copiez ce fichier en config.php
   2. Renseignez les accès MySQL (créés dans cPanel > Bases de données)
   3. Définissez le mot de passe admin initial, puis lancez install.php
   ========================================================== */

return [
  // --- Base de données MySQL (cPanel) ---
  'db_host' => 'localhost',
  'db_name' => 'famo_dons',
  'db_user' => '',
  'db_pass' => '',

  // --- Compte administrateur créé par install.php ---
  'admin_email'    => 'admin@famofondation.org',
  'admin_password' => 'CHANGEZ-MOI',   // changez-le AVANT d'exécuter install.php

  // --- Webhook Stripe (facultatif) ---
  // Tableau de bord Stripe > Développeurs > Webhooks > Signing secret (whsec_…)
  'stripe_webhook_secret' => '',

  // --- Webhook Zeffy (facultatif) ---
  // Choisissez un jeton secret quelconque (ex. une longue chaîne aléatoire),
  // puis dans Zeffy : Réglages > Organisation > Intégrations > Webhook,
  // collez : https://votre-domaine/backend/webhook-zeffy.php?token=CE_JETON
  'zeffy_webhook_token' => '',
  'zeffy_webhook_debug' => false, // true temporairement pour vérifier le format du payload

  // --- Divers ---
  'devise_defaut' => 'USD',
  'fuseau'        => 'America/New_York',
];
