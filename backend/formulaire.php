<?php
/* Fondation FAMO — réception sécurisée (HTTPS) des formulaires newsletter et contact.
   Enregistre en base (tables créées automatiquement) et notifie par e-mail. */
declare(strict_types=1);
require __DIR__ . '/db.php';

$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
$lang = (($_POST['lang'] ?? 'fr') === 'en') ? 'en' : 'fr';

function reponse(bool $ok, string $code, bool $ajax, string $lang): void {
  if ($ajax) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($ok ? 200 : 400);
    echo json_encode(['ok' => $ok, 'code' => $code]);
  } else {
    $page = ($lang === 'en' ? '/en/contact' : '/contact');
    header('Location: ' . $page . '?envoi=' . ($ok ? 'ok' : 'erreur') . '#formulaire-statut', true, 303);
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!empty($_POST['site_web'])) reponse(true, 'ok', $ajax, $lang); // pot de miel : les robots remplissent ce champ

$type  = ($_POST['type'] ?? '') === 'contact' ? 'contact' : 'newsletter';
$email = strtolower(trim((string)($_POST['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) reponse(false, 'email', $ajax, $lang);

try {
  $pdo = db();
  $pdo->exec('CREATE TABLE IF NOT EXISTS abonnes (
    id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE,
    langue VARCHAR(2) NOT NULL DEFAULT "fr", cree_le DATETIME DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
  $pdo->exec('CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(150) NULL, email VARCHAR(190) NOT NULL,
    sujet VARCHAR(200) NULL, message TEXT NOT NULL, langue VARCHAR(2) NOT NULL DEFAULT "fr",
    cree_le DATETIME DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

  $dest = 'contact@famofondation.org';
  if ($type === 'newsletter') {
    $pdo->prepare('INSERT IGNORE INTO abonnes (email, langue) VALUES (?, ?)')->execute([$email, $lang]);
    $sujet = 'Nouvelle inscription newsletter';
    $corps = "Nouvel abonné : $email (langue : $lang)";
  } else {
    $nom = mb_substr(trim((string)($_POST['nom'] ?? '')), 0, 150);
    $suj = mb_substr(trim((string)($_POST['sujet'] ?? '')), 0, 200);
    $msg = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 5000);
    if ($msg === '') reponse(false, 'message', $ajax, $lang);
    $pdo->prepare('INSERT INTO messages (nom, email, sujet, message, langue) VALUES (?,?,?,?,?)')
        ->execute([$nom, $email, $suj, $msg, $lang]);
    $sujet = 'Message du site : ' . preg_replace('/[\r\n]+/', ' ', $suj);
    $corps = "De : $nom <$email>\n\n$msg";
  }
  // Notification (échec d'envoi sans conséquence : le message est déjà en base)
  @mail($dest, '=?UTF-8?B?' . base64_encode($sujet) . '?=', $corps,
    "From: Site FAMO <no-reply@famofondation.org>\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8");
  reponse(true, 'ok', $ajax, $lang);
} catch (Throwable $e) {
  reponse(false, 'serveur', $ajax, $lang);
}
