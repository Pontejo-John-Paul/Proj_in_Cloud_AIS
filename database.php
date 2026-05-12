<?php
// ============================================================
//  TrikScan — Database Connection (database.php)
//  Include this file wherever you need a DB connection:
//    require_once 'database.php';
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // ← change to your MySQL username if different
define('DB_PASS', '');            // ← change to your MySQL password if set
define('DB_NAME', 'trikscan_db');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Check database.php settings.']);
    exit;
}