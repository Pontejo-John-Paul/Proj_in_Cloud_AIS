<?php
// ============================================================
//  TrikScan — Admin Signup Processor (process_signup.php)
//  Accepts POST requests from signup.php (via fetch/AJAX)
//  Returns JSON response
// ============================================================

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once 'database.php';   // PDO connection -> $pdo
require_once 'TrikMailer.php'; // Email sender

// 1. Collect & sanitize input
$firstName = trim($_POST['first_name']      ?? '');
$lastName  = trim($_POST['last_name']       ?? '');
$username  = trim($_POST['username']        ?? '');
$email     = trim($_POST['email']           ?? '');
$role      = trim($_POST['role']            ?? '');
$password  = $_POST['password']             ?? '';
$confirmPw = $_POST['confirm_password']     ?? '';

// 2. Server-side validation
$errors = [];
if (empty($firstName))                                          $errors[] = 'First name is required.';
if (empty($lastName))                                           $errors[] = 'Last name is required.';
if (strlen($username) < 4)                                      $errors[] = 'Username must be at least 4 characters.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))                 $errors[] = 'Invalid email address.';
if (!in_array($role, ['super_admin','admin','supervisor']))     $errors[] = 'Invalid role selected.';
if (strlen($password) < 8)                                      $errors[] = 'Password must be at least 8 characters.';
if ($password !== $confirmPw)                                   $errors[] = 'Passwords do not match.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// 3. Check for duplicate username or email
try {
    $stmt = $pdo->prepare("SELECT id FROM register_tb WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $email]);
    $existing = $stmt->fetch();

    if ($existing) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Username or email is already taken.']);
        exit;
    }

    // 4. Hash the password (bcrypt)
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // 5. Insert into register_tb
    $insert = $pdo->prepare("
        INSERT INTO register_tb (first_name, last_name, username, email, role, password)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([$firstName, $lastName, $username, $email, $role, $hashedPassword]);
    $newId = $pdo->lastInsertId();

    // 6. Send welcome email (failure won't stop registration)
    $mailer    = new TrikMailer();
    $emailSent = $mailer->sendWelcomeEmail($email, $firstName, $lastName, $username, $role);

    echo json_encode([
        'success'    => true,
        'message'    => 'Admin account created successfully.' . ($emailSent ? ' A confirmation email has been sent.' : ''),
        'email_sent' => $emailSent,
        'data'       => ['id' => $newId, 'username' => $username, 'role' => $role]
    ]);

} catch (PDOException $e) {
    error_log('TrikScan signup DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Throwable $e) {
    error_log('TrikScan signup error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;
}