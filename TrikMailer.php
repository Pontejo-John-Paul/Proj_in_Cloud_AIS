<?php
// ============================================================
//  TrikScan — Simple SMTP Mailer (TrikMailer.php)
//  Self-contained, no external dependencies.
//  Uses Gmail SMTP with App Password over TLS.
// ============================================================

class TrikMailer {

    private string $smtpHost     = 'smtp.gmail.com';
    private int    $smtpPort     = 587;
    private string $smtpUser     = 'your_email';
    private string $smtpPass     = 'your_app_pass';  // Gmail App Password
    private string $fromEmail    = 'pontejo.johnpaul.s@gmail.com';
    private string $fromName     = 'TrikScan System';

    private $socket = null;
    private array  $log = [];

    // ── Public: Send registration confirmation email
    public function sendWelcomeEmail(
        string $toEmail,
        string $firstName,
        string $lastName,
        string $username,
        string $role
    ): bool {

        $roleLabel = match($role) {
            'super_admin' => 'Super Admin',
            'supervisor'  => 'Supervisor',
            default       => 'Admin',
        };

        $subject = '✅ TrikScan Admin Account Successfully Created';

        $html = $this->buildEmailHTML($firstName, $lastName, $username, $toEmail, $roleLabel);
        $text = $this->buildEmailText($firstName, $lastName, $username, $toEmail, $roleLabel);

        return $this->send($toEmail, "{$firstName} {$lastName}", $subject, $html, $text);
    }

    // ── Public: Send OTP / forgot-password email
    public function sendOtpEmail(
        string $toEmail,
        string $toName,
        string $otp
    ): bool {
        $subject = '🔐 TrikScan — Your Password Reset OTP';
        $html    = $this->buildOtpHTML($toName, $otp);
        $text    = $this->buildOtpText($toName, $otp);
        return $this->send($toEmail, $toName, $subject, $html, $text);
    }

    // ── Core SMTP sender
    private function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        try {
            // Open socket with STARTTLS
            $this->socket = fsockopen('tcp://' . $this->smtpHost, $this->smtpPort, $errno, $errstr, 15);
            if (!$this->socket) throw new \Exception("Cannot connect to SMTP: {$errstr}");

            stream_set_timeout($this->socket, 15);

            $this->expect('220');
            $this->cmd("EHLO trikscan.local");
            $this->expect('250');
            $this->cmd("STARTTLS");
            $this->expect('220');

            // Upgrade to TLS
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \Exception("TLS upgrade failed.");
            }

            $this->cmd("EHLO trikscan.local");
            $this->expect('250');
            $this->cmd("AUTH LOGIN");
            $this->expect('334');
            $this->cmd(base64_encode($this->smtpUser));
            $this->expect('334');
            $this->cmd(base64_encode($this->smtpPass));
            $this->expect('235');

            $this->cmd("MAIL FROM:<{$this->fromEmail}>");
            $this->expect('250');
            $this->cmd("RCPT TO:<{$toEmail}>");
            $this->expect('250');
            $this->cmd("DATA");
            $this->expect('354');

            // Build MIME message
            $boundary = 'TrikScan_' . md5(uniqid());
            $headers  = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $headers .= "To: {$toName} <{$toEmail}>\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "X-Mailer: TrikScan-Mailer/1.0\r\n";

            $body  = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $body .= $textBody . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $body .= $htmlBody . "\r\n";
            $body .= "--{$boundary}--\r\n";

            // Dot-stuff: escape lines starting with a dot
            $message = $headers . "\r\n" . $body;
            $message = preg_replace('/^\.$/m', '..', $message);

            fwrite($this->socket, $message . "\r\n.\r\n");
            $this->expect('250');

            $this->cmd("QUIT");
            fclose($this->socket);
            return true;

        } catch (\Exception $e) {
            error_log('TrikMailer error: ' . $e->getMessage());
            if ($this->socket) @fclose($this->socket);
            return false;
        }
    }

    // ── SMTP helpers
    private function cmd(string $command): void {
        fwrite($this->socket, $command . "\r\n");
        $this->log[] = ">> " . $command;
    }

    private function expect(string $code): string {
        $response = '';
        while ($line = fgets($this->socket, 512)) {
            $this->log[] = "<< " . trim($line);
            $response   .= $line;
            // Multi-line responses: "250-..." continues, "250 " ends
            if (substr($line, 3, 1) === ' ') break;
        }
        if (substr(trim($response), 0, 3) !== $code) {
            throw new \Exception("Expected {$code}, got: " . trim($response));
        }
        return $response;
    }

    // ── OTP Email HTML template
    private function buildOtpHTML(string $toName, string $otp): string {
        $toName = htmlspecialchars($toName);
        $otp    = htmlspecialchars($otp);
        $date   = date('F d, Y \a\t h:i A');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>TrikScan — Password Reset OTP</title>
</head>
<body style="margin:0;padding:0;background:#0a0f1c;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0f1c;padding:40px 20px;">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#111827;border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;max-width:560px;">

      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#1a2235 0%,#0f1929 100%);padding:36px 40px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.06);">
          <table cellpadding="0" cellspacing="0" align="center">
            <tr>
              <td style="background:#f59e0b;border-radius:10px;width:44px;height:44px;text-align:center;vertical-align:middle;">
                <span style="font-size:22px;line-height:44px;">🔲</span>
              </td>
              <td style="padding-left:12px;">
                <span style="font-family:Arial,sans-serif;font-size:26px;font-weight:900;letter-spacing:2px;color:#f1f5f9;">TRIK<span style="color:#f59e0b;">SCAN</span></span>
              </td>
            </tr>
          </table>
          <p style="margin:14px 0 0;color:#64748b;font-size:13px;letter-spacing:1px;text-transform:uppercase;">Password Reset Request</p>
        </td>
      </tr>

      <!-- Body -->
      <tr>
        <td style="padding:40px 40px 20px;">
          <p style="margin:0 0 8px;color:#64748b;font-size:14px;">Hi <strong style="color:#f1f5f9;">{$toName}</strong>,</p>
          <p style="margin:0 0 24px;color:#64748b;font-size:14px;line-height:1.7;">We received a request to reset your TrikScan admin password. Use the one-time password below to continue. <strong style="color:#f1f5f9;">It expires in 5 minutes.</strong></p>

          <!-- OTP Box -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#1a2235;border:1px solid rgba(59,130,246,0.3);border-radius:12px;margin-bottom:24px;">
            <tr>
              <td style="padding:28px;text-align:center;">
                <div style="font-family:'Courier New',Courier,monospace;font-size:42px;font-weight:900;letter-spacing:14px;color:#3b82f6;line-height:1;">{$otp}</div>
                <p style="margin:12px 0 0;color:#64748b;font-size:12px;">One-Time Password — do not share this with anyone.</p>
              </td>
            </tr>
          </table>

          <!-- Timer Notice -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.2);border-radius:8px;margin-bottom:24px;">
            <tr>
              <td style="padding:12px 18px;color:#fcd34d;font-size:13px;line-height:1.6;">
                ⏱ This OTP is valid for <strong>5 minutes</strong> from the time it was sent ({$date}).
              </td>
            </tr>
          </table>

          <p style="margin:0;color:#64748b;font-size:13px;line-height:1.7;">If you did not request a password reset, you can safely ignore this email. Your account remains secure.</p>
        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="background:#0d1422;padding:24px 40px;text-align:center;border-top:1px solid rgba(255,255,255,0.06);">
          <p style="margin:0 0 6px;color:#64748b;font-size:12px;">© 2025 TrikScan — QR-Based Attendance System for Tricycle Drivers</p>
          <p style="margin:0;color:#334155;font-size:11px;">This is an automated message. Please do not reply to this email.</p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }

    // ── OTP plain text fallback
    private function buildOtpText(string $toName, string $otp): string {
        $date = date('F d, Y \a\t h:i A');
        return <<<TEXT
TRIKSCAN — Password Reset OTP
==============================

Hi {$toName},

We received a request to reset your TrikScan admin password.

YOUR OTP CODE: {$otp}

This code expires in 5 minutes (sent at {$date}).
Do NOT share this code with anyone.

If you did not request a password reset, you can safely ignore this email.

---
© 2025 TrikScan — QR-Based Attendance Monitoring System
This is an automated message. Please do not reply.
TEXT;
    }

    // ── Welcome Email HTML template
    private function buildEmailHTML(
        string $firstName,
        string $lastName,
        string $username,
        string $email,
        string $roleLabel
    ): string {
        $fullName  = htmlspecialchars("{$firstName} {$lastName}");
        $username  = htmlspecialchars($username);
        $email     = htmlspecialchars($email);
        $roleLabel = htmlspecialchars($roleLabel);
        $date      = date('F d, Y \a\t h:i A');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>TrikScan — Account Created</title>
</head>
<body style="margin:0;padding:0;background:#0a0f1c;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0f1c;padding:40px 20px;">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#111827;border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;max-width:560px;">

      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#1a2235 0%,#0f1929 100%);padding:36px 40px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.06);">
          <table cellpadding="0" cellspacing="0" align="center">
            <tr>
              <td style="background:#f59e0b;border-radius:10px;width:44px;height:44px;text-align:center;vertical-align:middle;">
                <span style="font-size:22px;line-height:44px;">🔲</span>
              </td>
              <td style="padding-left:12px;">
                <span style="font-family:Arial,sans-serif;font-size:26px;font-weight:900;letter-spacing:2px;color:#f1f5f9;">TRIK<span style="color:#f59e0b;">SCAN</span></span>
              </td>
            </tr>
          </table>
          <p style="margin:14px 0 0;color:#64748b;font-size:13px;letter-spacing:1px;text-transform:uppercase;">QR Attendance System</p>
        </td>
      </tr>

      <!-- Success Icon -->
      <tr>
        <td style="padding:40px 40px 0;text-align:center;">
          <div style="display:inline-block;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);border-radius:50%;width:72px;height:72px;line-height:72px;font-size:32px;">✅</div>
          <h1 style="margin:20px 0 8px;color:#f1f5f9;font-size:22px;font-weight:700;">Admin Account Created!</h1>
          <p style="margin:0;color:#64748b;font-size:14px;line-height:1.6;">Your administrator account for TrikScan has been successfully registered.</p>
        </td>
      </tr>

      <!-- Account Details -->
      <tr>
        <td style="padding:28px 40px;">
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#1a2235;border-radius:10px;border:1px solid rgba(255,255,255,0.06);overflow:hidden;">
            <tr><td colspan="2" style="padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.06);color:#f59e0b;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;">Account Details</td></tr>
            <tr>
              <td style="padding:12px 20px;color:#64748b;font-size:13px;width:40%;border-bottom:1px solid rgba(255,255,255,0.04);">Full Name</td>
              <td style="padding:12px 20px;color:#f1f5f9;font-size:13px;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.04);">{$fullName}</td>
            </tr>
            <tr>
              <td style="padding:12px 20px;color:#64748b;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.04);">Username</td>
              <td style="padding:12px 20px;color:#f59e0b;font-size:13px;font-family:monospace;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.04);">{$username}</td>
            </tr>
            <tr>
              <td style="padding:12px 20px;color:#64748b;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.04);">Email</td>
              <td style="padding:12px 20px;color:#f1f5f9;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.04);">{$email}</td>
            </tr>
            <tr>
              <td style="padding:12px 20px;color:#64748b;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.04);">Role</td>
              <td style="padding:12px 20px;border-bottom:1px solid rgba(255,255,255,0.04);"><span style="background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);color:#f59e0b;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;letter-spacing:0.5px;">{$roleLabel}</span></td>
            </tr>
            <tr>
              <td style="padding:12px 20px;color:#64748b;font-size:13px;">Registered On</td>
              <td style="padding:12px 20px;color:#f1f5f9;font-size:13px;">{$date}</td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Notice -->
      <tr>
        <td style="padding:0 40px 28px;">
          <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(59,130,246,0.07);border:1px solid rgba(59,130,246,0.2);border-radius:8px;">
            <tr>
              <td style="padding:14px 18px;color:#93c5fd;font-size:13px;line-height:1.6;">
                🔒 <strong>Security Notice:</strong> Please keep your credentials safe. If you did not create this account, contact your system administrator immediately.
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="background:#0d1422;padding:24px 40px;text-align:center;border-top:1px solid rgba(255,255,255,0.06);">
          <p style="margin:0 0 6px;color:#64748b;font-size:12px;">© 2025 TrikScan — QR-Based Attendance System for Tricycle Drivers</p>
          <p style="margin:0;color:#334155;font-size:11px;">This is an automated message. Please do not reply to this email.</p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }

    // ── Plain text fallback
    private function buildEmailText(
        string $firstName,
        string $lastName,
        string $username,
        string $email,
        string $roleLabel
    ): string {
        $date = date('F d, Y \a\t h:i A');
        return <<<TEXT
TRIKSCAN — Admin Account Created
=================================

Hi {$firstName} {$lastName},

Your TrikScan administrator account has been successfully created.

ACCOUNT DETAILS
---------------
Full Name  : {$firstName} {$lastName}
Username   : {$username}
Email      : {$email}
Role       : {$roleLabel}
Registered : {$date}

SECURITY NOTICE
---------------
Please keep your credentials safe. If you did not create this account,
contact your system administrator immediately.

---
© 2025 TrikScan — QR-Based Attendance Monitoring System
This is an automated message. Please do not reply.
TEXT;
    }
}
