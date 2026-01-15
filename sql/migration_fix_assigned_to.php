<?php
// sql/migration_fix_assigned_to.php
require_once __DIR__ . '/../db_connect.php';

try {
    echo "Attempting to fix incidents table...\n";

    // 1. Drop Foreign Key
    // We check if it exists or just try to drop it.
    try {
        echo "Dropping FK fk_incidents_assignee...\n";
        $pdo->exec("ALTER TABLE incidents DROP FOREIGN KEY fk_incidents_assignee");
        echo "FK dropped (or attempted).\n";
    } catch (PDOException $e) {
        echo "Warning dropping FK: " . $e->getMessage() . "\n";
    }

    // 2. Drop Index (often created with FK, might prevent type change or cause issues)
    try {
        echo "Dropping Index fk_incidents_assignee...\n";
        $pdo->exec("DROP INDEX fk_incidents_assignee ON incidents");
        echo "Index dropped (or attempted).\n";
    } catch (PDOException $e) {
        echo "Warning dropping Index: " . $e->getMessage() . "\n";
    }

    // 3. Change Column Type
    echo "Modifying assigned_to to VARCHAR(255)...\n";
    $sql = "ALTER TABLE incidents MODIFY COLUMN assigned_to VARCHAR(255) NULL";
    $pdo->exec($sql);

    echo "Migration successful: assigned_to converted to VARCHAR.\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
