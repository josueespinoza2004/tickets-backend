<?php
// sql/migration_change_assigned_to.php
require_once __DIR__ . '/../db_connect.php';

try {
    // 1. Drop Foreign Key
    // Note: Constraint name might vary. We try standard name.
    try {
        $pdo->exec("ALTER TABLE incidents DROP FOREIGN KEY fk_incidents_assignee");
    } catch (Exception $e) {
        // FK might not exist or verify name. Proceeding.
    }

    // 2. Change Column Type
    $sql = "ALTER TABLE incidents MODIFY COLUMN assigned_to VARCHAR(255) NULL";
    $pdo->exec($sql);

    echo "Migration successful: assigned_to converted to VARCHAR for multiple IDs.";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
