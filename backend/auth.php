<?php
/* Fondation FAMO — authentification admin (sessions + CSRF) */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

session_set_cookie_params([
  'httponly' => true,
  'samesite' => 'Lax',
  'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

function csrf_verifier(): void {
  $ok = isset($_POST['csrf'], $_SESSION['csrf'])
     && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
  if (!$ok) { http_response_code(403); exit('Jeton CSRF invalide.'); }
}

function est_connecte(): bool {
  return !empty($_SESSION['admin_id']);
}

function tenter_connexion(string $email, string $pass): bool {
  $q = db()->prepare('SELECT id, mdp_hash FROM admins WHERE email = ?');
  $q->execute([strtolower(trim($email))]);
  $row = $q->fetch();
  if ($row && password_verify($pass, $row['mdp_hash'])) {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$row['id'];
    return true;
  }
  return false;
}

function deconnexion(): void {
  $_SESSION = [];
  session_destroy();
}

function exiger_connexion(): void {
  if (!est_connecte()) { header('Location: index.php'); exit; }
}
