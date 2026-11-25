<?php
require_once __DIR__ . '/database_config/env.php';
session_start();
require_once __DIR__ . '/database_config/db.php';
require_once __DIR__ . '/lib/password_reset.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    av_rate_limit_or_die('forgot', 5, 3600);
    if (!av_reset_csrf_valid()) { $_SESSION['error'] = 'Your session expired. Please try again.'; header('Location: /forgot-password'); exit(); }
    $_SESSION['success'] = 'If an account exists for that email, a password reset link will be sent shortly.';
    $email = trim((string) ($_POST['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt = $conn->prepare('SELECT id, username FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = av_reset_token_hash($token);
                $expiry = date('Y-m-d H:i:s', time() + 3600);
                $conn->begin_transaction();
                $del = $conn->prepare('DELETE FROM password_resets WHERE user_id = ?');
                $del->bind_param('i', $user['id']);
                $del->execute();
                $del->close();
                $ins = $conn->prepare('INSERT INTO password_resets (user_id, token, expiry_date) VALUES (?, ?, ?)');
                $ins->bind_param('iss', $user['id'], $tokenHash, $expiry);
                $ins->execute();
                $ins->close();
                $conn->commit();
                $link = av_reset_base_url() . '/reset-password.php?token=' . rawurlencode($token);
                if (!av_reset_mail($email, (string) ($user['username'] ?? ''), $link)) {
                    error_log('AvenPay merchant password reset email could not be sent.');
                }
            }
        } catch (Throwable $e) {
            if ($conn->errno || $conn->thread_id) { try { $conn->rollback(); } catch (Throwable $ignored) {} }
            error_log('AvenPay merchant password reset request failed: ' . $e->getMessage());
        }
    }
    header('Location: /forgot-password');
    exit();
}
$resetCsrf = av_reset_csrf_token();
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($resetCsrf, ENT_QUOTES, 'UTF-8') ?>">
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