<?php
/* Fondation FAMO — installation : crée les tables et le compte admin.
   À exécuter UNE fois via le navigateur, puis SUPPRIMER ce fichier. */
declare(strict_types=1);
require __DIR__ . '/db.php';

$cfg = famo_config();
$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS donateurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NULL,
  email VARCHAR(190) NULL UNIQUE,
  telephone VARCHAR(50) NULL,
  cree_le DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS dons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  donateur_id INT NULL,
  montant DECIMAL(10,2) NOT NULL,
  devise VARCHAR(10) NOT NULL DEFAULT 'USD',
  frequence ENUM('unique','mensuel') NOT NULL DEFAULT 'unique',
  source ENUM('stripe','paypal','zeffy','especes','cheque','autre') NOT NULL DEFAULT 'autre',
  reference VARCHAR(190) NULL UNIQUE,
  statut ENUM('recu','en_attente','rembourse') NOT NULL DEFAULT 'recu',
  note VARCHAR(255) NULL,
  don_le DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_don_donateur FOREIGN KEY (donateur_id)
    REFERENCES donateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  mdp_hash VARCHAR(255) NOT NULL,
  cree_le DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$email = strtolower(trim($cfg['admin_email']));
$pass  = (string)$cfg['admin_password'];
if ($pass === '' || $pass === 'CHANGEZ-MOI') {
  exit('Définissez un vrai admin_password dans config.php avant l\'installation.');
}
$q = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
$q->execute([$email]);
if (!$q->fetch()) {
  $pdo->prepare('INSERT INTO admins (email, mdp_hash) VALUES (?, ?)')
      ->execute([$email, password_hash($pass, PASSWORD_DEFAULT)]);
  echo "Compte admin créé : {$email}<br>";
} else {
  echo "Compte admin déjà existant.<br>";
}
echo "Tables créées. Installation terminée.<br>";
echo "<strong>Supprimez maintenant install.php du serveur.</strong> ";
echo '<a href="index.php">Aller au tableau de bord</a>';
