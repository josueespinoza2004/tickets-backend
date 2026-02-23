<?php
// api/create_ticket.php

// Suprimir warnings para que no rompan el JSON
error_reporting(E_ERROR | E_PARSE);

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
        
        // Crear directorio si no existe
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception("No se pudo crear el directorio de uploads");
            }
        }
        
        // Verificar que el directorio sea escribible
        if (!is_writable($uploadDir)) {
            throw new Exception("El directorio de uploads no tiene permisos de escritura");
        }

        $fileName = uniqid() . '_' . basename($_FILES['evidence_file']['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['evidence_file']['tmp_name'], $targetPath)) {
            // Establecer permisos del archivo para que sea legible
            chmod($targetPath, 0644);
            // Save relative path
            $evidencePath = 'uploads/' . $fileName;
        } else {
            throw new Exception("Error al mover el archivo subido");
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

    $ticketId = $pdo->lastInsertId();

    // Obtener información del usuario creador incluyendo su rol
    $userStmt = $pdo->prepare("SELECT full_name, email, role FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();

    // Solo enviar correo a los administradores si el creador NO es admin ni gerente
    $isCreatorAdmin = ($user && $user['role'] === 'admin');
    $isCreatorGerente = ($user && $user['role'] === 'gerente');
    
    if (!$isCreatorAdmin && !$isCreatorGerente) {
        // Enviar correo solo a ADMINISTRADORES (no a gerentes)
        $adminStmt = $pdo->query("SELECT email, full_name FROM users WHERE role = 'admin'");
        $admins = $adminStmt->fetchAll();

        if ($admins && count($admins) > 0) {
            require_once __DIR__ . '/../send_email.php';
            
            foreach ($admins as $admin) {
                if ($admin['email']) {
                $htmlBody = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    </head>
                    <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;'>
                        <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f3f4f6; padding: 40px 20px;'>
                            <tr>
                                <td align='center'>
                                    <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 8px; overflow: hidden;'>
                                        <!-- Header con logo -->
                                        <tr>
                                            <td style='padding: 40px 40px 30px 40px; background-color: #ffffff;'>
                                                <table width='100%' cellpadding='0' cellspacing='0'>
                                                    <tr>
                                                        <td style='vertical-align: middle; text-align: center;'>
                                                            <table cellpadding='0' cellspacing='0' style='display: inline-block;'>
                                                                <tr>
                                                                    <td style='vertical-align: middle; padding-right: 15px;'>
                                                                        <img src='https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTDCa-jCPCvDE4kHKJP3pKyfMZjTqcwsxLliQ&s' alt='COOPEFACSA Logo' style='width: 70px; height: auto; display: block;' />
                                                                    </td>
                                                                    <td style='vertical-align: middle; text-align: left;'>
                                                                        <h1 style='margin: 0; color: #2d3748; font-size: 28px; font-weight: bold; line-height: 1.2;'>Gestión de Tickets e Incidencias</h1>
                                                                        <p style='margin: 5px 0 0 0; color: #718096; font-size: 16px; line-height: 1.2; text-align: center;'>COOPEFACSA R.L.</p>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                        
                                        <!-- Línea separadora -->
                                        <tr>
                                            <td style='padding: 0 40px;'>
                                                <div style='height: 3px; background-color: #2d3748;'></div>
                                            </td>
                                        </tr>
                                        
                                        <!-- Contenido principal -->
                                        <tr>
                                            <td style='padding: 40px; background-color: #f9fafb;'>
                                                <h2 style='margin: 0 0 20px 0; color: #374151; font-size: 24px; font-weight: bold;'>
                                                    Hola " . htmlspecialchars($admin['full_name'] ?: 'Administrador') . ",
                                                </h2>
                                                
                                                <p style='margin: 0 0 15px 0; color: #6b7280; font-size: 16px; line-height: 1.5;'>
                                                    Se ha registrado una nueva incidencia en el sistema.
                                                </p>
                                                
                                                <p style='margin: 0 0 20px 0; color: #6b7280; font-size: 16px; line-height: 1.5;'>
                                                    Detalles de la incidencia:
                                                </p>
                                                
                                                <!-- Detalles en caja -->
                                                <table width='100%' cellpadding='0' cellspacing='0' style='margin: 0 0 20px 0;'>
                                                    <tr>
                                                        <td style='padding: 25px; background-color: #ffffff; border: 2px solid #e5e7eb; border-radius: 8px;'>
                                                            <table width='100%' cellpadding='0' cellspacing='0'>
                                                                <tr>
                                                                    <td style='padding: 8px 0;'>
                                                                        <strong style='color: #374151;'>ID de Ticket:</strong>
                                                                        <span style='color: #6b7280;'> #" . $ticketId . "</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='padding: 8px 0;'>
                                                                        <strong style='color: #374151;'>Reportado por:</strong>
                                                                        <span style='color: #6b7280;'> " . htmlspecialchars($user['full_name'] ?: $user['email']) . "</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='padding: 8px 0;'>
                                                                        <strong style='color: #374151;'>Título:</strong>
                                                                        <span style='color: #6b7280;'> " . htmlspecialchars($title) . "</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='padding: 8px 0;'>
                                                                        <strong style='color: #374151;'>Descripción:</strong>
                                                                        <div style='color: #6b7280; margin-top: 5px;'>" . nl2br(htmlspecialchars($description)) . "</div>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='padding: 8px 0;'>
                                                                        <strong style='color: #374151;'>Prioridad:</strong>
                                                                        <span style='color: #6b7280;'> " . htmlspecialchars($priority) . "</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='padding: 8px 0;'>
                                                                        <strong style='color: #374151;'>Estado:</strong>
                                                                        <span style='color: #6b7280;'> " . htmlspecialchars($status) . "</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='padding: 8px 0;'>
                                                                        <strong style='color: #374151;'>Fecha:</strong>
                                                                        <span style='color: #6b7280;'> " . ($incident_date ? date('d/m/Y', strtotime($incident_date)) : 'No especificada') . "</span>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </table>
                                                
                                                <p style='margin: 0; color: #6b7280; font-size: 15px; line-height: 1.5;'>
                                                    Por favor, revisa y atiende esta incidencia lo antes posible.
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <!-- Footer -->
                                        <tr>
                                            <td style='padding: 30px 40px; background-color: #ffffff; text-align: center;'>
                                                <p style='margin: 0 0 10px 0; color: #9ca3af; font-size: 13px;'>
                                                    Este es un correo automático, por favor no respondas.
                                                </p>
                                                <p style='margin: 0; color: #9ca3af; font-size: 12px;'>
                                                    © 2026 COOPEFACSA R.L. - Todos los derechos reservados
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </body>
                    </html>
                ";
                
                // Enviar email a cada administrador
                sendEmail($admin['email'], 'Nueva Incidencia Registrada - COOPEFACSA', $htmlBody, true);
            }
        }
        }
    }

    echo json_encode(['success' => true, 'id' => $ticketId, 'message' => 'Ticket created successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error creating ticket: ' . $e->getMessage()]);
}
