-- Table: branches
-- Purpose: almacena las sucursales de la cooperativa
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS branches (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos de ejemplo para poblar branches (ajusta según tus sucursales reales):
INSERT INTO branches (name) VALUES ('Casa Matriz') ON DUPLICATE KEY UPDATE name = name;
INSERT INTO branches (name) VALUES ('Sucursal Norte') ON DUPLICATE KEY UPDATE name = name;
INSERT INTO branches (name) VALUES ('Sucursal Sur') ON DUPLICATE KEY UPDATE name = name;

CREATE TABLE IF NOT EXISTS areas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos de ejemplo para poblar areas:
INSERT INTO areas (name) VALUES ('Directiva') ON DUPLICATE KEY UPDATE name = name;
INSERT INTO areas (name) VALUES ('Informática') ON DUPLICATE KEY UPDATE name = name;
INSERT INTO areas (name) VALUES ('Recursos Humanos') ON DUPLICATE KEY UPDATE name = name;
INSERT INTO areas (name) VALUES ('Contabilidad') ON DUPLICATE KEY UPDATE name = name;
INSERT INTO areas (name) VALUES ('Caja') ON DUPLICATE KEY UPDATE name = name;
INSERT INTO areas (name) VALUES ('Negocios') ON DUPLICATE KEY UPDATE name = name;

-- Tabla: users
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NULL,
  role VARCHAR(50) DEFAULT 'user',
  -- referencia a areas.id (nullable)
  area_id INT UNSIGNED NULL,
  -- referencia a sucursales.id (nullable)
  branch_id INT UNSIGNED NULL,
  -- nuevo: nombre completo
  full_name VARCHAR(255) NULL,
  -- cargo / puesto dentro de la cooperativa
  cargo VARCHAR(255) NULL,
  -- ruta/nombre del archivo de foto de perfil
  profile_photo VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Foreign keys
  CONSTRAINT fk_users_area FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL,
  CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 


-- Table: user_sessions
-- Purpose: Manage active user sessions/tokens
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_token (token),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: incidents
-- Purpose: Store tickets/issues reported by users
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS incidents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  incident_date DATE NOT NULL,
  priority ENUM('Baja', 'Media', 'Alta') NOT NULL DEFAULT 'Baja',
  status ENUM('Sin Empezar', 'En Curso', 'Listo') NOT NULL DEFAULT 'Sin Empezar',
  creator_id INT UNSIGNED NOT NULL,
  assigned_to VARCHAR(255) NULL,
  branch_id INT UNSIGNED NULL,
  area_id INT UNSIGNED NULL,
  evidence_file VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  CONSTRAINT fk_incidents_creator FOREIGN KEY (creator_id) REFERENCES users(id),
  CONSTRAINT fk_incidents_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  CONSTRAINT fk_incidents_area FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

