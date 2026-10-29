-- ============================================================
-- Fondation FAMO — schéma de la base de suivi des dons (MySQL)
-- Alternative à install.php : importer ce fichier dans
-- phpMyAdmin (cPanel) après avoir créé la base et l'utilisateur.
-- NB : ce script ne crée PAS le compte admin — soit exécuter
-- install.php ensuite, soit utiliser l'INSERT commenté en bas.
-- ============================================================

CREATE TABLE IF NOT EXISTS donateurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NULL,
  email VARCHAR(190) NULL UNIQUE,
  telephone VARCHAR(50) NULL,
  cree_le DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  donateur_id INT NULL,
  montant DECIMAL(10,2) NOT NULL,
  devise VARCHAR(10) NOT NULL DEFAULT 'USD',
  frequence ENUM('unique','mensuel') NOT NULL DEFAULT 'unique',
  source ENUM('stripe','paypal','zeffy','especes','cheque','autre') NOT NULL DEFAULT 'autre',
  reference VARCHAR(190) NULL UNIQUE,   -- id de transaction externe (idempotence)
  statut ENUM('recu','en_attente','rembourse') NOT NULL DEFAULT 'recu',
  note VARCHAR(255) NULL,
  don_le DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_don_donateur FOREIGN KEY (donateur_id)
    REFERENCES donateurs(id) ON DELETE SET NULL,
  INDEX idx_don_le (don_le),
  INDEX idx_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  mdp_hash VARCHAR(255) NOT NULL,
  cree_le DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Création manuelle d'un admin (si vous n'utilisez pas install.php) :
-- générer le hash en local avec :
--   php -r "echo password_hash('VotreMotDePasse', PASSWORD_DEFAULT);"
-- puis :
-- INSERT INTO admins (email, mdp_hash)
-- VALUES ('admin@famofondation.org', '$2y$10$...hash-généré...');
-- ------------------------------------------------------------

-- Newsletter et messages du formulaire de contact
-- (ces tables sont aussi créées automatiquement par formulaire.php)
CREATE TABLE IF NOT EXISTS abonnes (
  id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE,
  langue VARCHAR(2) NOT NULL DEFAULT 'fr', cree_le DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(150) NULL, email VARCHAR(190) NOT NULL,
  sujet VARCHAR(200) NULL, message TEXT NOT NULL, langue VARCHAR(2) NOT NULL DEFAULT 'fr',
  cree_le DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
