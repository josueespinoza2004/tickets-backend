<?php
// tickets-backend/sql/create_incidents_table.php
require_once __DIR__ . '/../db_connect.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS incidents (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      description TEXT NOT NULL,
      priority ENUM('Baja', 'Media', 'Alta', 'Crítica') NOT NULL DEFAULT 'Baja',
      status ENUM('Abierto', 'En Progreso', 'Resuelto', 'Cerrado') NOT NULL DEFAULT 'Abierto',
      creator_id INT UNSIGNED NOT NULL,
      assigned_to INT UNSIGNED NULL,
      branch_id INT UNSIGNED NULL,
      area_id INT UNSIGNED NULL,
      evidence_file VARCHAR(255) NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      
      CONSTRAINT fk_incidents_creator FOREIGN KEY (creator_id) REFERENCES users(id),
      CONSTRAINT fk_incidents_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
      CONSTRAINT fk_incidents_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
      CONSTRAINT fk_incidents_area FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "Table 'incidents' created successfully.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
