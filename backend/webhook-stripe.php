<?php
/* Fondation FAMO — webhook Stripe
   Enregistre automatiquement chaque paiement Stripe dans la base.
   Stripe > Développeurs > Webhooks > Ajouter un endpoint :
     URL     : https://votre-domaine/backend/webhook-stripe.php
     Événement : checkout.session.completed
   Puis coller le "Signing secret" (whsec_…) dans config.php. */
declare(strict_types=1);
require __DIR__ . '/db.php';

$cfg     = famo_config();
$secret  = (string)($cfg['stripe_webhook_secret'] ?? '');
$payload = file_get_contents('php://input') ?: '';
$sigHdr  = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

/* ---- Vérification de signature (HMAC SHA-256, tolérance 5 min) ---- */
function stripe_signature_valide(string $payload, string $header, string $secret): bool {
  if ($secret === '' || $header === '') return false;
  $t = null; $v1 = [];
  foreach (explode(',', $header) as $part) {
    [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
    if ($k === 't') $t = $v;
    if ($k === 'v1') $v1[] = $v;
  }
  if (!$t || !$v1 || abs(time() - (int)$t) > 300) return false;
  $attendu = hash_hmac('sha256', $t . '.' . $payload, $secret);
  foreach ($v1 as $sig) {
    if (hash_equals($attendu, $sig)) return true;
  }
  return false;
}

if (!stripe_signature_valide($payload, $sigHdr, $secret)) {
  http_response_code(400);
  exit('Signature invalide');
}

$event = json_decode($payload, true);
if (!is_array($event)) { http_response_code(400); exit('JSON invalide'); }

if (($event['type'] ?? '') === 'checkout.session.completed') {
  $s = $event['data']['object'] ?? [];
  $montant = isset($s['amount_total']) ? ((int)$s['amount_total']) / 100 : 0;
  if ($montant > 0 && ($s['payment_status'] ?? '') === 'paid') {
    $email = $s['customer_details']['email'] ?? null;
    $nom   = $s['customer_details']['name']  ?? null;
    enregistrer_don([
      'donateur_id' => donateur_id($email, $nom),
      'montant'     => $montant,
      'devise'      => strtoupper((string)($s['currency'] ?? 'usd')),
      'frequence'   => (($s['mode'] ?? '') === 'subscription') ? 'mensuel' : 'unique',
      'source'      => 'stripe',
      'reference'   => (string)($s['id'] ?? ''),
      'statut'      => 'recu',
      'don_le'      => isset($event['created']) ? date('Y-m-d H:i:s', (int)$event['created']) : null,
    ]);
  }
}

http_response_code(200);
echo 'ok';
