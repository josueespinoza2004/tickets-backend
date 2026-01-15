<?php
// sql/migration_add_date.php
require_once __DIR__ . '/../db_connect.php';

try {
    $sql = "ALTER TABLE incidents ADD COLUMN incident_date DATETIME NULL AFTER description";
    $pdo->exec($sql);
    echo "Migration successful: incident_date column added.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Column incident_date already exists.";
    } else {
        die("Migration failed: " . $e->getMessage());
    }
}
