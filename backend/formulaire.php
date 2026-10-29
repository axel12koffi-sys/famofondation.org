<?php
/* Fondation FAMO — réception sécurisée (HTTPS) des formulaires newsletter et contact.
   Notifie par e-mail et, si la base de données est configurée, archive l'envoi.
   La base est facultative : sans backend/config.php, seul l'e-mail part. */
declare(strict_types=1);

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

$nom = mb_substr(trim((string)($_POST['nom'] ?? '')), 0, 150);
$suj = mb_substr(trim((string)($_POST['sujet'] ?? '')), 0, 200);
$msg = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 5000);
if ($type === 'contact' && $msg === '') reponse(false, 'message', $ajax, $lang);

if ($type === 'newsletter') {
  $sujet = 'Nouvelle inscription newsletter';
  $corps = "Nouvel abonné : $email (langue : $lang)";
} else {
  $sujet = 'Message du site : ' . preg_replace('/[\r\n]+/', ' ', $suj);
  $corps = "De : $nom <$email>\nLangue : $lang\n\n$msg";
}

/* Archivage en base — facultatif. Une base absente ou injoignable ne doit
   jamais empêcher l'envoi : le message part quand même par e-mail. */
$archive = false;
if (file_exists(__DIR__ . '/config.php')) {
  try {
    require_once __DIR__ . '/db.php';
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

    if ($type === 'newsletter') {
      $pdo->prepare('INSERT IGNORE INTO abonnes (email, langue) VALUES (?, ?)')->execute([$email, $lang]);
    } else {
      $pdo->prepare('INSERT INTO messages (nom, email, sujet, message, langue) VALUES (?,?,?,?,?)')
          ->execute([$nom, $email, $suj, $msg, $lang]);
    }
    $archive = true;
  } catch (Throwable $e) {
    error_log('[FAMO formulaire] archivage impossible : ' . $e->getMessage());
  }
}

$envoye = @mail(
  'contact@famofondation.org',
  '=?UTF-8?B?' . base64_encode($sujet) . '?=',
  $corps,
  "From: Site FAMO <no-reply@famofondation.org>\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8"
);

/* L'envoi est un succès dès que le message est quelque part : dans la boîte
   de la fondation ou dans la base. Il n'échoue que si les deux ont échoué. */
if ($envoye || $archive) reponse(true, 'ok', $ajax, $lang);

error_log('[FAMO formulaire] ni e-mail ni archivage : message perdu pour ' . $email);
reponse(false, 'serveur', $ajax, $lang);
