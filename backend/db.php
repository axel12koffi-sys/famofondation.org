<?php
/* Fondation FAMO — connexion base de données */
declare(strict_types=1);

function famo_config(): array {
  static $cfg = null;
  if ($cfg === null) {
    $path = __DIR__ . '/config.php';
    if (!file_exists($path)) {
      http_response_code(500);
      exit('config.php manquant : copiez config.example.php en config.php et renseignez-le.');
    }
    $cfg = require $path;
    date_default_timezone_set($cfg['fuseau'] ?? 'UTC');
  }
  return $cfg;
}

function db(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $c = famo_config();
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $c['db_host'], $c['db_name']);
    $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  }
  return $pdo;
}

/** Trouve ou crée un donateur ; retourne son id (ou null si aucune info). */
function donateur_id(?string $email, ?string $nom, ?string $tel = null): ?int {
  $email = $email ? strtolower(trim($email)) : null;
  $nom   = $nom ? trim($nom) : null;
  if (!$email && !$nom) return null;
  $pdo = db();
  if ($email) {
    $q = $pdo->prepare('SELECT id FROM donateurs WHERE email = ?');
    $q->execute([$email]);
    if ($row = $q->fetch()) {
      if ($nom) {
        $pdo->prepare('UPDATE donateurs SET nom = COALESCE(NULLIF(?, ""), nom) WHERE id = ?')
            ->execute([$nom, $row['id']]);
      }
      return (int)$row['id'];
    }
  }
  $ins = $pdo->prepare('INSERT INTO donateurs (nom, email, telephone) VALUES (?, ?, ?)');
  $ins->execute([$nom, $email, $tel]);
  return (int)$pdo->lastInsertId();
}

/** Insère un don ; ignore silencieusement si la référence externe existe déjà. */
function enregistrer_don(array $d): bool {
  $pdo = db();
  if (!empty($d['reference'])) {
    $q = $pdo->prepare('SELECT id FROM dons WHERE reference = ?');
    $q->execute([$d['reference']]);
    if ($q->fetch()) return false; // idempotence (webhooks rejoués)
  }
  $ins = $pdo->prepare(
    'INSERT INTO dons (donateur_id, montant, devise, frequence, source, reference, statut, note, don_le)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  $ins->execute([
    $d['donateur_id'] ?? null,
    $d['montant'],
    $d['devise']    ?? famo_config()['devise_defaut'],
    $d['frequence'] ?? 'unique',
    $d['source']    ?? 'autre',
    $d['reference'] ?? null,
    $d['statut']    ?? 'recu',
    $d['note']      ?? null,
    $d['don_le']    ?? date('Y-m-d H:i:s'),
  ]);
  return true;
}
