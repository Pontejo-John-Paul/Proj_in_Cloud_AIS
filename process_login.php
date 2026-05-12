<?php
// ============================================================
//  TrikScan — Login Processor (process_login.php)
// ============================================================
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once 'database.php';

$identifier = trim($_POST['identifier'] ?? '');
$password   = $_POST['password']        ?? '';

if (empty($identifier) || empty($password)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

// Brute-force throttle — max 5 attempts per 10 minutes per IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$attemptKey = 'login_attempt_' . md5($ip);
if (!isset($_SESSION[$attemptKey])) $_SESSION[$attemptKey] = ['count' => 0, 'first' => time()];
$attempt = &$_SESSION[$attemptKey];

// Reset window after 10 minutes
if (time() - $attempt['first'] > 600) { $attempt = ['count' => 0, 'first' => time()]; }

if ($attempt['count'] >= 5) {
    $wait = 600 - (time() - $attempt['first']);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => "Too many failed attempts. Please wait {$wait} seconds."]);
    exit;
}

try {
    // Find by username OR email, must be active
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, username, email, role, password, status
        FROM register_tb
        WHERE (username = ? OR email = ?) AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        $attempt['count']++;
        $left = 5 - $attempt['count'];
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username/email or password.' . ($left > 0 ? " {$left} attempt(s) remaining." : '')
        ]);
        exit;
    }

    // ── Login success — reset attempt counter
    $attempt = ['count' => 0, 'first' => time()];

    // Store session
    $_SESSION['admin_id']        = $user['id'];
    $_SESSION['admin_name']      = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['admin_username']  = $user['username'];
    $_SESSION['admin_email']     = $user['email'];
    $_SESSION['admin_role']      = $user['role'];
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['login_time']      = time();

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'data'    => [
            'name'     => $user['first_name'] . ' ' . $user['last_name'],
            'username' => $user['username'],
            'role'     => $user['role'],
        ]
    ]);

} catch (PDOException $e) {
    error_log('TrikScan login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;
}