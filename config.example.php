<?php
// Copy this file to config.php and fill in your details
// NEVER commit config.php to GitHub

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

define('SITE_NAME', 'Cardrona Hut');

// Leave empty if installed in domain root
// Set to '/hut' if installed in a subdirectory
define('BASE_URL', '');

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_MB', 10);
define('MAX_UPLOAD_SIZE', MAX_UPLOAD_MB * 1024 * 1024);
