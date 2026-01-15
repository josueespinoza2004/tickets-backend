<?php
// api/tickets.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth/app_auth.php';

$userId = authenticate(); // Valid user

$method = $_SERVER['REQUEST_METHOD'];

try {
    // Check user role
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
    $isAdmin = ($currentUser['role'] === 'admin');

    if ($method === 'GET') {
        // GET: List tickets
        $sql = "SELECT i.*, 
                       u.full_name as creator_name, 
                       u.email as creator_email,
                       b.name as branch_name,
                       a.name as area_name,
                       (SELECT GROUP_CONCAT(COALESCE(full_name, email) SEPARATOR ', ') 
                        FROM users 
                        WHERE FIND_IN_SET(users.id, REPLACE(i.assigned_to, ' ', ''))) as assigned_to_name
                FROM incidents i
                LEFT JOIN users u ON i.creator_id = u.id
                LEFT JOIN branches b ON i.branch_id = b.id
                LEFT JOIN areas a ON i.area_id = a.id";

        // Filter logic: Admin sees all, User sees only created by them (or assigned to them if they were support staff)
        if (!$isAdmin) {
            $sql .= " WHERE i.creator_id = " . $pdo->quote($userId);
        }

        $sql .= " ORDER BY i.created_at DESC";

        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll());

    } elseif ($method === 'PUT') {
        // PUT: Update ticket
        $id = $_GET['id'] ?? null;
        if (!$id)
            throw new Exception("Ticket ID required");

        $input = json_decode(file_get_contents('php://input'), true);

        // Basic permission check: Admin can update anything. User can update maybe only if Open? 
        // For simplicity: Admin updates status/assignee. User can update description?
        // Let's allow simple updates for now.

        $fields = [];
        $params = [];

        // Admin fields (also allow editing core details)
        if ($isAdmin) {
            if (isset($input['status'])) {
                $fields[] = "status = ?";
                $params[] = $input['status'];
            }
            if (isset($input['priority'])) {
                $fields[] = "priority = ?";
                $params[] = $input['priority'];
            }
            if (isset($input['assigned_to'])) {
                $fields[] = "assigned_to = ?";
                // If array, join with commas. If string/null, keep as is.
                $val = $input['assigned_to'];
                if (is_array($val)) {
                    $val = implode(',', $val);
                }
                $params[] = $val;
            }
            // Allow editing core details too
            if (isset($input['title'])) {
                $fields[] = "title = ?";
                $params[] = $input['title'];
            }
            if (isset($input['description'])) {
                $fields[] = "description = ?";
                $params[] = $input['description'];
            }
            if (isset($input['incident_date'])) {
                $fields[] = "incident_date = ?";
                $params[] = $input['incident_date'];
            }
            if (isset($input['branch_id'])) {
                $fields[] = "branch_id = ?";
                $params[] = $input['branch_id'];
            }
            if (isset($input['area_id'])) {
                $fields[] = "area_id = ?";
                $params[] = $input['area_id'];
            }
        }

        // Common fields (just for example, usually user shouldn't change ticket after creation except maybe cancel)
        // if (isset($input['description'])) ...

        if (empty($fields)) {
            echo json_encode(['message' => 'No changes made']);
            exit;
        }

        $params[] = $id;
        $sql = "UPDATE incidents SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true]);

    } elseif ($method === 'DELETE') {
        // DELETE
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Only admins can delete tickets']);
            exit;
        }

        $id = $_GET['id'] ?? null;
        if (!$id)
            throw new Exception("Ticket ID required");

        $stmt = $pdo->prepare("DELETE FROM incidents WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
