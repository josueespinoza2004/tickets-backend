<?php
// api/users.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth/app_auth.php';

// Validate Session
authenticate(); // Will exit if invalid

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;

    if ($method === 'GET') {
        if ($id) {
            // Get single user logic if needed, for now mostly list all
            $stmt = $pdo->prepare("SELECT id, full_name, email, role, branch_id, cargo FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch());
        } else {
            // List all (original logic)
            // Joined with branches to get branch name if needed, but for now simple select
            // Joined with branches and areas
            $stmt = $pdo->query("SELECT u.id, u.full_name as name, u.email, u.role, u.branch_id, u.area_id, u.cargo, 
                                        b.name as branch_name, a.name as area_name
                                 FROM users u
                                 LEFT JOIN branches b ON u.branch_id = b.id
                                 LEFT JOIN areas a ON u.area_id = a.id
                                 ORDER BY u.created_at DESC");
            echo json_encode($stmt->fetchAll());
        }
    } elseif ($method === 'DELETE') {
        if (!$id) {
            throw new Exception("ID required for deletion");
        }
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => 'User deleted']);
    } elseif ($method === 'PUT') {
        if (!$id) {
            throw new Exception("ID required for update");
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Build dynamic update query
        $fields = [];
        $params = [];

        if (isset($data['full_name'])) {
            $fields[] = "full_name = ?";
            $params[] = $data['full_name'];
        }
        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $params[] = $data['email'];
        }
        if (isset($data['role'])) {
            $fields[] = "role = ?";
            $params[] = $data['role'];
        }
        if (isset($data['branch_id'])) {
            $fields[] = "branch_id = ?";
            $params[] = $data['branch_id'];
        }
        if (isset($data['area_id'])) {
            $fields[] = "area_id = ?";
            $params[] = $data['area_id'];
        }
        if (isset($data['cargo'])) {
            $fields[] = "cargo = ?";
            $params[] = $data['cargo'];
        }

        // Only update password if provided
        if (!empty($data['password'])) {
            $fields[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (empty($fields)) {
            echo json_encode(['message' => 'No fields to update']);
            exit;
        }

        $params[] = $id; // For WHERE id = ?

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['message' => 'User updated']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
