<?php
/* Fondation FAMO — webhook Zeffy
   Enregistre automatiquement chaque don Zeffy dans la base.

   Zeffy (Réglages > Organisation > Intégrations > Webhook) : coller
     https://votre-domaine/backend/webhook-zeffy.php?token=VOTRE_JETON
   Zeffy envoie un POST JSON à chaque paiement complété (payment.completed).

   NB : Zeffy ne documente pas publiquement de signature HMAC sur ses
   webhooks (contrairement à Stripe). La protection ici repose sur un
   jeton secret dans l'URL (connu de vous seul) — traitez cette URL
   comme un mot de passe et ne la partagez pas. */
declare(strict_types=1);
require __DIR__ . '/db.php';

$cfg   = famo_config();
$token = (string)($cfg['zeffy_webhook_token'] ?? '');

if ($token === '' || ($_GET['token'] ?? '') !== $token) {
  http_response_code(403);
  exit('Jeton invalide');
}

$payload = file_get_contents('php://input') ?: '';
$event   = json_decode($payload, true);
if (!is_array($event)) { http_response_code(400); exit('JSON invalide'); }

/* Journalisation temporaire : mettre 'zeffy_webhook_debug' => true dans
   config.php pour enregistrer le payload brut du prochain don reçu dans
   webhook-zeffy.log (à consulter puis à supprimer). Utile pour confirmer
   les noms de champs exacts envoyés par Zeffy, puis à désactiver. */
if (!empty($cfg['zeffy_webhook_debug'])) {
  file_put_contents(__DIR__ . '/webhook-zeffy.log',
    date('c') . " " . $payload . "\n", FILE_APPEND | LOCK_EX);
}

/* La structure exacte du payload peut varier ; on lit les champs les
   plus probables avec repli, pour rester tolérant. À ajuster une fois
   qu'un premier webhook réel aura été reçu (voir webhook-zeffy-log.php). */
$type = $event['event'] ?? $event['type'] ?? '';
if (stripos((string)$type, 'payment') === false) {
  http_response_code(200); echo 'ignoré'; exit;
}

$data = $event['data'] ?? $event['payment'] ?? $event;

$montant = $data['amount'] ?? $data['total'] ?? $data['montant'] ?? null;
if (is_array($montant)) $montant = $montant['value'] ?? $montant['amount'] ?? null;
$montant = $montant !== null ? (float)$montant : 0;

$devise = strtoupper((string)($data['currency'] ?? $data['devise'] ?? 'USD'));

$buyer = $data['buyer'] ?? $data['contact'] ?? $data['donor'] ?? [];
$email = $buyer['email'] ?? $data['email'] ?? null;
$nom   = trim((string)(($buyer['firstName'] ?? $buyer['prenom'] ?? '') . ' ' .
                        ($buyer['lastName'] ?? $buyer['nom'] ?? ''))) ?: ($buyer['name'] ?? null);

$reference = (string)($data['id'] ?? $data['paymentId'] ?? $event['id'] ?? '');
$recurrence = $data['frequency'] ?? $data['recurrence'] ?? 'unique';
$frequence = (stripos((string)$recurrence, 'month') !== false || stripos((string)$recurrence, 'mensuel') !== false)
  ? 'mensuel' : 'unique';

if ($montant > 0 && $reference !== '') {
  enregistrer_don([
    'donateur_id' => donateur_id($email, $nom),
    'montant'     => $montant,
    'devise'      => $devise,
    'frequence'   => $frequence,
    'source'      => 'zeffy',
    'reference'   => 'zeffy_' . $reference,
    'statut'      => 'recu',
  ]);
}

http_response_code(200);
echo 'ok';
