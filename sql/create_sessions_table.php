<?php
// tickets-backend/sql/create_sessions_table.php
require_once __DIR__ . '/../db_connect.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL,
      token VARCHAR(64) NOT NULL,
      expires_at DATETIME NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY unique_token (token),
      CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "Table 'user_sessions' created successfully.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
