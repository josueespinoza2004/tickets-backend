<?php
// api/create_ticket.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth/app_auth.php';

// Validate Session and get User ID
$userId = authenticate();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // If has file upload, data comes in $_POST, otherwise json body
    // Next.js with FormData sends multipart/form-data, so we use $_POST
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $incident_date = $_POST['incident_date'] ?? null;
    $priority = $_POST['priority'] ?? 'Baja';
    $status = $_POST['status'] ?? 'Sin Empezar'; // Default, but user can override

    $assigned_to = $_POST['assigned_to'] ?? null;
    if (is_array($assigned_to)) {
        $assigned_to = implode(',', $assigned_to);
    }

    $branch_id = $_POST['branch_id'] ?? null;
    $area_id = $_POST['area_id'] ?? null;

    if (!$title || !$description) {
        throw new Exception("Title and description are required");
    }

    // Handle File Upload
    $evidencePath = null;
    if (isset($_FILES['evidence_file']) && $_FILES['evidence_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['evidence_file']['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['evidence_file']['tmp_name'], $targetPath)) {
            // Save relative path or full URL. Usually relative to be served via separate endpoint or static
            $evidencePath = 'uploads/' . $fileName;
        }
    }

    $sql = "INSERT INTO incidents (title, description, incident_date, priority, status, assigned_to, branch_id, area_id, creator_id, evidence_file) 
            VALUES (:title, :description, :incident_date, :priority, :status, :assigned_to, :branch_id, :area_id, :creator_id, :evidence_file)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':incident_date' => $incident_date,
        ':priority' => $priority,
        ':status' => $status,
        ':assigned_to' => $assigned_to,
        ':branch_id' => !empty($branch_id) ? $branch_id : null,
        ':area_id' => !empty($area_id) ? $area_id : null,
        ':creator_id' => $userId,
        ':evidence_file' => $evidencePath
    ]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Ticket created successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error creating ticket: ' . $e->getMessage()]);
}
