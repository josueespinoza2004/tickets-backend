<?php
// db_config.php - template. Fill with your cPanel PostgreSQL credentials.
// IMPORTANT: move this file outside public_html in production if possible.
return [
  // Local Laragon / MySQL defaults — edit if your local MySQL uses a password or different DB name
  'host' => '127.0.0.1',
  'port' => 3306,
'dbname' => 'nameofyour_db',
  'user' => 'youruser',
  'pass' => 'yourpassword',
  // optional: set to false to disable debug errors
  'debug' => false,
  // One-time setup token used by server/create_admin.php.
  // Set to a random value (string) before running create_admin.php, then remove or set to null afterwards.
  // Example:
  // 'setup_token' => 'mi-secreto-temporal-ABC123',
  // After creating the admin user, delete or unset this key to prevent reuse.
  // 'setup_token' => null,
];
