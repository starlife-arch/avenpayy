<?php
require_once __DIR__ . '/database_config/env.php';
session_start();
require_once 'database_config/db.php';
require_once __DIR__ . '/lib/security.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { av_rate_limit_or_die('forgot', 5, 3600); }

require 'vendor/PHPMailer/src/Exception.php';
require 'vendor/PHPMailer/src/PHPMailer.php';
require 'vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Please enter a valid email address.';
        header('Location: /forgot-password');
        exit();
    }

    $stmt = $conn->prepare(
        'SELECT id, username FROM users WHERE email = ? LIMIT 1'
    );

    if (!$stmt) {
        $_SESSION['error'] = 'Unable to process your request. Please try again.';
        header('Location: /forgot-password');
        exit();
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $_SESSION['success'] =
        'If an account exists for that email, a password reset link will be sent shortly.';

    if ($user) {
        try {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + 3600);

            $del = $conn->prepare('DELETE FROM password_resets WHERE user_id = ?');
            if (!$del) {
                throw new RuntimeException('Could not prepare token cleanup.');
            }

            $del->bind_param('i', $user['id']);
            if (!$del->execute()) {
                throw new RuntimeException('Could not remove old reset tokens.');
            }
            $del->close();

            $ins = $conn->prepare(
                'INSERT INTO password_resets (user_id, token, expiry_date) VALUES (?, ?, ?)'
            );
            if (!$ins) {
                throw new RuntimeException('Could not prepare reset token.');
            }

            $ins->bind_param('iss', $user['id'], $token, $expiry);
            if (!$ins->execute()) {
                $ins->close();
                throw new RuntimeException('Could not save reset token.');
            }
            $ins->close();

            // DYNAMIC MULTI-DOMAIN DETECTION (.site or .co.ke)
            $requestHost = $_SERVER['HTTP_HOST'] ?? 'avenpay.co.ke';
            $host = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $requestHost);
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'https://';
            
            $baseUrl = rtrim(av_env('RESET_BASE_URL') ?: ($protocol . $host), '/');
            $resetLink = $baseUrl . '/reset-password.php?token=' . rawurlencode($token);

            // Determine mail domain sender based on current host suffix
            $mailDomain = (str_ends_with($host, '.co.ke')) ? $host : 'avenpay.co.ke';
            $noReplyEmail = 'no-reply@' . $mailDomain;

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = av_env('SMTP_HOST') ?: ('mail.' . $mailDomain);
            $mail->SMTPAuth   = true;
            
            // SMTP Authentication aligned with configured credentials
            $mail->Username   = av_env('SMTP_USERNAME') ?: $noReplyEmail;
            $mail->Password   = av_env('SMTP_PASSWORD');
            
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($noReplyEmail, 'AvenPay');
            $mail->addAddress($email, $user['username'] ?? '');

            $safeUsername = htmlspecialchars(
                $user['username'] ?? '',
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
            $safeResetLink = htmlspecialchars(
                $resetLink,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - AvenPay';
            $mail->Body = "
                <div style='background-color:#f4f6f9;padding:40px 0;font-family:Arial,sans-serif;'>
                    <div style='max-width:520px;margin:0 auto;background:#fff;border-radius:8px;border:1px solid #e2e8f0;overflow:hidden;'>
                        <div style='padding:36px 30px;'>
                            <h2 style='color:#1a202c;font-size:18px;'>Reset Your Password</h2>
                            <p style='color:#4a5568;font-size:14px;'>Hello <strong>{$safeUsername}</strong>,</p>
                            <p style='color:#4a5568;font-size:14px;line-height:1.6;'>
                                We received a request to reset your AvenPay account password.
                                Click the button below to choose a new password.
                            </p>
                            <p style='margin:32px 0;'>
                                <a href='{$safeResetLink}'
                                   style='background:#0b9a74;color:#fff;padding:14px 28px;font-weight:bold;text-decoration:none;border-radius:6px;display:inline-block;'>
                                    Reset Password
                                </a>
                            </p>
                            <p style='color:#4a5568;font-size:13px;line-height:1.5;'>
                                This link expires in 1 hour. If you did not request a reset,
                                you can ignore this email.
                            </p>
                            <hr style='border:0;border-top:1px solid #edf2f7;margin:28px 0;'>
                            <p style='color:#718096;font-size:12px;'>
                                If the button does not work, copy and paste this URL:
                            </p>
                            <p style='word-break:break-all;font-size:12px;'>
                                <a href='{$safeResetLink}'>{$safeResetLink}</a>
                            </p>
                        </div>
                        <div style='background:#f7fafc;padding:20px;text-align:center;border-top:1px solid #edf2f7;'>
                            <p style='color:#a0aec0;font-size:11px;margin:0;'>
                                &copy; " . date('Y') . " AvenPay. All rights reserved.
                            </p>
                        </div>
                    </div>
                </div>
            ";
            $mail->AltBody =
                "Hello " . ($user['username'] ?? '') . ",\n\n" .
                "Reset your AvenPay password using this link (expires in 1 hour):\n" .
                $resetLink;

            $mail->send();
        } catch (Throwable $e) {
            error_log('Password reset request failed: ' . $e->getMessage());
            $_SESSION['success'] =
                'If an account exists for that email, a password reset link will be sent shortly.';
        }
    }

    header('Location: /forgot-password');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - AvenPay</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --card-border: rgba(15, 23, 42, 0.064);
            --input-bg: #f8fafc;
            --input-border: #cbd5e1;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --accent-purple: #0b9a74;
            --accent-purple-hover: #087f5f;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .auth-container {
            width: 100%;
            max-width: 420px;
            position: relative;
        }

        .auth-container::before {
            content: "";
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: 200px;
            background: rgba(11, 154, 116, 0.15);
            filter: blur(60px);
            pointer-events: none;
            border-radius: 50%;
        }

        .auth-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 1rem;
            padding: 36px 32px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(16px);
            position: relative;
            z-index: 1;
        }

        h1 {
            font-size: 22px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
            color: #fff;
        }

        .subtitle {
            font-size: 12px;
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 24px;
            font-weight: 500;
        }

        .msg-box {
            padding: 12px 14px;
            border-radius: 0.75rem;
            font-size: 12px;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid rgba(220, 38, 38, 0.3);
        }

        .success {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid rgba(22, 163, 74, 0.3);
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper input {
            width: 100%;
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 0.75rem;
            padding: 12px 12px 12px 42px;
            color: var(--text-main);
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: var(--accent-purple);
            box-shadow: 0 0 0 2px rgba(11, 154, 116, 0.15);
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            display: flex;
            pointer-events: none;
        }

        .input-icon svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        .btn-submit {
            background-color: var(--accent-purple);
            color: #fff;
            border: none;
            border-radius: 0.75rem;
            padding: 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(11, 154, 116, 0.3);
            margin-top: 4px;
        }

        .btn-submit:hover {
            background-color: var(--accent-purple-hover);
            transform: translateY(-1px);
        }

        .btn-alternative {
            background-color: transparent;
            border: 1px solid var(--input-border);
            border-radius: 0.75rem;
            padding: 12px;
            font-size: 12px;
            color: var(--text-muted);
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            transition: all 0.2s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
        }

        .btn-alternative:hover {
            border-color: var(--text-muted);
            color: var(--text-main);
            background: rgba(15, 23, 42, 0.016);
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-text-fill-color: var(--text-main) !important;
            -webkit-box-shadow: 0 0 0 1000px var(--input-bg) inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>Forgot password?</h1>
            <p class="subtitle">Enter your account email to receive a reset link</p>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="msg-box error">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="msg-box success">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <form action="/forgot-password" method="POST">
                <div class="form-group">
                    <label for="email">EMAIL ADDRESS</label>
                    <div class="input-wrapper">
                        <span class="input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </span>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            placeholder="you@example.com"
                            autocomplete="email"
                            required
                        >
                    </div>
                </div>

                <button type="submit" class="btn-submit">Send Reset Link</button>
                <a href="/login" class="btn-alternative">&larr; Back to Sign In</a>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener("keydown", function (event) {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === "u"
            ) {
                event.preventDefault();
            }
        });

        setTimeout(function () {
            document.querySelectorAll(".msg-box").forEach(function (box) {
                box.style.display = "none";
            });
        }, 4000);
    </script>
</body>
</html>