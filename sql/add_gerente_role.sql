-- Script para agregar el rol 'gerente' a la tabla users
-- Copia y pega este script en tu gestor de base de datos (phpMyAdmin, MySQL Workbench, etc.)

-- Modificar la columna role para incluir 'gerente'
ALTER TABLE users 
MODIFY COLUMN role ENUM('user', 'admin', 'gerente') NOT NULL DEFAULT 'user';

-- Verificar que el cambio se aplicó correctamente
-- Puedes ejecutar esta consulta para ver la estructura:
-- DESCRIBE users;
