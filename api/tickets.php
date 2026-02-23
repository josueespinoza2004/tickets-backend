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
    $isGerente = ($currentUser['role'] === 'gerente');

    if ($method === 'GET') {
        // GET: List tickets
        $sql = "SELECT i.*, 
                       u.full_name as creator_name, 
                       u.email as creator_email,
                       u.cargo as creator_cargo,
                       creator_area.name as creator_area_name,
                       b.name as branch_name,
                       a.name as area_name,
                       (SELECT GROUP_CONCAT(COALESCE(full_name, email) SEPARATOR ', ') 
                        FROM users 
                        WHERE FIND_IN_SET(users.id, REPLACE(i.assigned_to, ' ', ''))) as assigned_to_name
                FROM incidents i
                LEFT JOIN users u ON i.creator_id = u.id
                LEFT JOIN areas creator_area ON u.area_id = creator_area.id
                LEFT JOIN branches b ON i.branch_id = b.id
                LEFT JOIN areas a ON i.area_id = a.id";

        // Filter logic: Admin/Gerente sees all, User sees only created by them
        if (!$isAdmin && !$isGerente) {
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

        // Obtener el estado anterior del ticket y datos del creador
        $oldTicketStmt = $pdo->prepare("SELECT status, creator_id, title, description FROM incidents WHERE id = ?");
        $oldTicketStmt->execute([$id]);
        $oldTicket = $oldTicketStmt->fetch();
        $oldStatus = $oldTicket['status'] ?? null;

        // Basic permission check: Admin can update anything. Gerente cannot update. User can update maybe only if Open? 
        // For simplicity: Only Admin can update tickets
        
        $fields = [];
        $params = [];

        // Admin fields (only admins can edit)
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

        // Enviar correo si el estado cambió a "Listo"
        $newStatus = $input['status'] ?? null;
        if ($newStatus === 'Listo' && $oldStatus !== 'Listo' && $oldTicket) {
            // Obtener información completa del ticket incluyendo assigned_to
            $ticketInfoStmt = $pdo->prepare("
                SELECT i.priority, i.assigned_to,
                       (SELECT GROUP_CONCAT(COALESCE(full_name, email) SEPARATOR ', ') 
                        FROM users 
                        WHERE FIND_IN_SET(users.id, REPLACE(i.assigned_to, ' ', ''))) as assigned_to_names
                FROM incidents i
                WHERE i.id = ?
            ");
            $ticketInfoStmt->execute([$id]);
            $ticketInfo = $ticketInfoStmt->fetch();
            
            // Obtener información del creador del ticket
            $creatorStmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
            $creatorStmt->execute([$oldTicket['creator_id']]);
            $creator = $creatorStmt->fetch();

            if ($creator && $creator['email']) {
                require_once __DIR__ . '/../send_email.php';
                
                $resolvedBy = $ticketInfo['assigned_to_names'] ?: 'No asignado';
                
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
                                                    Hola " . htmlspecialchars($creator['full_name'] ?: 'Usuario') . ",
                                                </h2>
                                                
                                                <p style='margin: 0 0 15px 0; color: #6b7280; font-size: 16px; line-height: 1.5;'>
                                                    Tu incidencia ha sido <strong style='color: #16a34a;'>resuelta</strong> exitosamente.
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
                                                                        <strong style='color: #374151;'>Título:</strong>
                                                                        <span style='color: #6b7280;'> " . htmlspecialchars($oldTicket['title']) . "</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='padding: 8px 0;'>
                                                                        <strong style='color: #374151;'>Descripción:</strong>
                                                                        <div style='color: #6b7280; margin-top: 5px;'>" . nl2br(htmlspecialchars($oldTicket['description'])) . "</div>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='padding: 8px 0;'>
                                                                        <strong style='color: #374151;'>Tipo:</strong>
                                                                        <span style='color: #6b7280;'> " . htmlspecialchars($ticketInfo['priority']) . "</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='padding: 8px 0;'>
                                                                        <strong style='color: #374151;'>Resuelta por:</strong>
                                                                        <span style='color: #6b7280;'> " . htmlspecialchars($resolvedBy) . "</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='padding: 8px 0;'>
                                                                        <strong style='color: #374151;'>Estado:</strong>
                                                                        <span style='display: inline-block; padding: 4px 12px; background-color: #dcfce7; color: #166534; border-radius: 12px; font-size: 14px; font-weight: bold;'>Listo</span>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </table>
                                                
                                                <p style='margin: 0; color: #6b7280; font-size: 15px; line-height: 1.5;'>
                                                    Gracias por tu paciencia. Si tienes alguna pregunta o necesitas asistencia adicional, no dudes en contactarnos.
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
                
                // Enviar email (no bloqueante)
                sendEmail($creator['email'], 'Incidencia Resuelta - COOPEFACSA', $htmlBody, true);
            }
        }

        echo json_encode(['success' => true]);

    } elseif ($method === 'DELETE') {
        // DELETE - Only admins can delete
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
