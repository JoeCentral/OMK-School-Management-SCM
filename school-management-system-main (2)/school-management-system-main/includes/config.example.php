<?php
// ============================================================
//  OMK School — Configuration File
//  Copy this file to config.php and fill in your values.
//  Never commit config.php to version control.
// ============================================================

define('DB_HOST', 'localhost');           // Database host
define('DB_NAME', 'omk_school');          // Database name
define('DB_USER', 'root');                // Database username
define('DB_PASS', '');                    // Database password

define('BASE_URL', 'http://localhost/school'); // No trailing slash
define('UPLOAD_PATH', __DIR__ . '/../uploads/documents/');
define('APP_NAME', 'OMK School');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES   => false]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'DB Connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
