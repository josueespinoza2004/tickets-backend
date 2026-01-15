<?php
// api/debug_tickets_json.php
require_once __DIR__ . '/../db_connect.php';

// Force JSON response
header('Content-Type: application/json');

try {
    $sql = "SELECT i.*, 
            (SELECT GROUP_CONCAT(COALESCE(full_name, email) SEPARATOR ', ') 
             FROM users 
             WHERE FIND_IN_SET(users.id, REPLACE(i.assigned_to, ' ', ''))) as assigned_to_name
            FROM incidents i
            ORDER BY i.created_at DESC";

    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll();

    echo json_encode([
        'count' => count($data),
        'tickets' => $data
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
