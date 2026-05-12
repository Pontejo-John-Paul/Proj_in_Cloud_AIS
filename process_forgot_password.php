<?php
// ============================================================
//  TrikScan — Forgot Password Processor (process_forgot_password.php)
//  Handles three actions via POST:
//    action=send_otp   → validate email, send OTP
//    action=verify_otp → check OTP, issue a short-lived reset token
//    action=reset_pass → change password using verified token
// ============================================================
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/TrikMailer.php';

$action = trim($_POST['action'] ?? '');
$ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// ================================================================
//  HELPERS
// ================================================================

/**
 * Generate a cryptographically secure 6-digit OTP string.
 */
function generateOtp(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Send the OTP email via TrikMailer.
 */
function sendOtpEmail(string $toEmail, string $toName, string $otp): bool {
    $mailer = new TrikMailer();
    return $mailer->sendOtpEmail($toEmail, $toName, $otp);
}

// ================================================================
//  ACTION: send_otp
// ================================================================
if ($action === 'send_otp') {

    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    try {
        // 1. Find active user by email
        $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM register_tb WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always return the same message to prevent email enumeration
        if (!$user) {
            echo json_encode(['success' => true, 'message' => 'If that email is registered, an OTP has been sent.']);
            exit;
        }

        // 2. Check resend rate-limit: max 3 OTPs per 5-minute window per email
        $stmt = $pdo->prepare("
            SELECT id, resend_count, window_start,
                   TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(window_start, INTERVAL 5 MINUTE)) AS secs_left
            FROM password_resets
            WHERE email = ?
              AND used = 0
              AND window_start > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing && $existing['resend_count'] >= 3) {
            $waitSecs = max(0, (int) $existing['secs_left']);
            $waitMins = ceil($waitSecs / 60);
            http_response_code(429);
            echo json_encode([
                'success'      => false,
                'message'      => "Too many OTP requests. Please wait {$waitMins} minute(s) before trying again.",
                'wait_seconds' => $waitSecs,
            ]);
            exit;
        }

        // 3. Invalidate all previous unused OTPs for this email
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0")->execute([$email]);

        // 4. Generate OTP, hash it, persist
        //    expires_at and window_start use MySQL's NOW() to avoid PHP/MySQL timezone mismatch
        $otp      = generateOtp();
        $otpHash  = password_hash($otp, PASSWORD_BCRYPT, ['cost' => 10]);
        $newCount = $existing ? $existing['resend_count'] + 1 : 1;

        // Keep the original window_start on resend so the 5-min rate-limit window is preserved;
        // use NOW() for a fresh window on the first request.
        $windowStartExpr = $existing ? '?' : 'NOW()';
        $windowStartBind = $existing ? [$existing['window_start']] : [];

        $ins = $pdo->prepare("
            INSERT INTO password_resets (user_id, email, otp_hash, expires_at, resend_count, window_start, ip_address)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), ?, {$windowStartExpr}, ?)
        ");
        $ins->execute(array_merge(
            [$user['id'], $email, $otpHash, $newCount],
            $windowStartBind,
            [$ip]
        ));
        $insertedId = $pdo->lastInsertId();

        // 5. Send email via TrikMailer
        $fullName = $user['first_name'] . ' ' . $user['last_name'];
        $sent     = sendOtpEmail($email, $fullName, $otp);

        if (!$sent) {
            // Roll back the insert so the resend count isn't wasted
            $pdo->prepare("DELETE FROM password_resets WHERE id = ?")->execute([$insertedId]);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP email. Please try again.']);
            exit;
        }

        $attemptsLeft = 3 - $newCount;
        echo json_encode([
            'success'     => true,
            'message'     => 'OTP sent! Check your email. It expires in 5 minutes.',
            'resend_left' => $attemptsLeft,
            'expires_in'  => 300,
        ]);

    } catch (PDOException $e) {
        error_log('TrikScan forgot_password send_otp error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    }
    exit;
}

// ================================================================
//  ACTION: verify_otp
// ================================================================
if ($action === 'verify_otp') {

    $email = strtolower(trim($_POST['email'] ?? ''));
    $otp   = trim($_POST['otp'] ?? '');

    if (empty($email) || empty($otp) || strlen($otp) !== 6 || !ctype_digit($otp)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid OTP format.']);
        exit;
    }

    try {
        // Get the latest valid, unused OTP for this email
        $stmt = $pdo->prepare("
            SELECT id, otp_hash, expires_at
            FROM password_resets
            WHERE email = ?
              AND used  = 0
              AND expires_at > NOW()
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $record = $stmt->fetch();

        if (!$record || !password_verify($otp, $record['otp_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP. Please try again.']);
            exit;
        }

        // Mark OTP as used
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$record['id']]);

        // Issue a short-lived, single-use reset token (stored in session only)
        $resetToken = bin2hex(random_bytes(32));
        $_SESSION['pw_reset_token']  = $resetToken;
        $_SESSION['pw_reset_email']  = $email;
        $_SESSION['pw_reset_expiry'] = time() + 300; // 5 more minutes to set new password

        echo json_encode([
            'success'     => true,
            'message'     => 'OTP verified! You may now set a new password.',
            'reset_token' => $resetToken,
        ]);

    } catch (PDOException $e) {
        error_log('TrikScan forgot_password verify_otp error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    }
    exit;
}

// ================================================================
//  ACTION: reset_pass
// ================================================================
if ($action === 'reset_pass') {

    $token    = trim($_POST['reset_token']     ?? '');
    $password = $_POST['new_password']         ?? '';
    $confirm  = $_POST['confirm_password']     ?? '';

    // Validate session token
    if (
        empty($_SESSION['pw_reset_token'])  ||
        empty($_SESSION['pw_reset_email'])  ||
        empty($_SESSION['pw_reset_expiry']) ||
        !hash_equals($_SESSION['pw_reset_token'], $token) ||
        time() > $_SESSION['pw_reset_expiry']
    ) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Reset session expired or invalid. Please start over.']);
        exit;
    }

    // Password strength: min 8 chars, at least one uppercase, one number, one special char
    if (strlen($password) < 8) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter.']);
        exit;
    }
    if (!preg_match('/[0-9]/', $password)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one number.']);
        exit;
    }
    if (!preg_match('/[\W_]/', $password)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one special character.']);
        exit;
    }
    if ($password !== $confirm) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    try {
        $email   = $_SESSION['pw_reset_email'];
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $pdo->prepare("UPDATE register_tb SET password = ? WHERE email = ? AND status = 'active'");
        $stmt->execute([$newHash, $email]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Account not found or inactive.']);
            exit;
        }

        // Destroy reset session keys
        unset($_SESSION['pw_reset_token'], $_SESSION['pw_reset_email'], $_SESSION['pw_reset_expiry']);

        echo json_encode(['success' => true, 'message' => 'Password updated successfully! You may now log in.']);

    } catch (PDOException $e) {
        error_log('TrikScan forgot_password reset_pass error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    }
    exit;
}

// ── Unknown action ──
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action.']);