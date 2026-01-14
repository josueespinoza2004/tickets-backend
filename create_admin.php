<?php
// create_admin.php
// Ejecuta este script UNA VEZ desde el navegador para crear tu usuario administrador
// http://localhost/tickets/tickets-backend/create_admin.php

require_once __DIR__ . '/db_connect.php';

try {
    $email = 'admin@coopefacsa.com';
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Verificar si ya existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        die("El usuario admin ya existe.");
    }

    $sql = "INSERT INTO users (email, password, full_name, role, cargo) 
            VALUES (:email, :pass, 'Administrador Sistema', 'admin', 'Super Admin')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':email' => $email,
        ':pass' => $hashed_password
    ]);

    echo "Usuario Admin creado con éxito.<br>";
    echo "Email: $email<br>";
    echo "Pass: $password";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
