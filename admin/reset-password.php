<?php
session_start();

$possible_paths = [
    __DIR__ . '/../database_config/db.php',
    '../database_config/db.php',
    '../../database_config/db.php',
    __DIR__ . '/db.php'
];

$db_path = null;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $db_path = $path;
        break;
    }
}

if ($db_path) {
    require_once $db_path;
} else {
    die("Database configuration file not found.");
}

$error = '';
$success = '';
$valid_token = false;
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

if (!empty($token)) {
    $stmt = $conn->prepare("SELECT email, expires_at FROM admin_password_resets WHERE token = ?");
    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $reset_record = $result->fetch_assoc();
            if (strtotime($reset_record['expires_at']) > time()) {
                $valid_token = true;
            } else {
                $error = "This password reset link has expired. Please request a new one.";
            }
        } else {
            $error = "Invalid password reset token.";
        }
        $stmt->close();
    }
} else {
    $error = "No password reset token provided.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all password fields.";
    } elseif ($password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $email = $reset_record['email'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $update_stmt = $conn->prepare("UPDATE admins SET password = ? WHERE email = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("ss", $hashed_password, $email);
            $update_stmt->execute();
            $update_stmt->close();

            // Delete the used token
            $del_stmt = $conn->prepare("DELETE FROM admin_password_resets WHERE token = ?");
            if ($del_stmt) {
                $del_stmt->bind_param("s", $token);
                $del_stmt->execute();
                $del_stmt->close();
            }

            $success = "Your password has been successfully updated. You can now log in.";
            $valid_token = false; // Hide form after success
        } else {
            $error = "Database update error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta name="theme-color" content="#ffffff">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" id="metaThemeColor" content="#ffffff">
    <title>Reset Password - AvenPay Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: #080b11;
            --bg-light: #f1f5f9;
            --card-dark: rgba(15, 23, 42, 0.95);
            --card-light: rgba(255, 255, 255, 0.98);
            --border-dark: rgba(255, 255, 255, 0.08);
            --border-light: rgba(203, 213, 225, 0.9);
            --input-bg-dark: rgba(15, 23, 42, 0.9);
            --input-bg-light: #ffffff;
            --input-border-dark: #1e293b;
            --input-border-light: #cbd5e1;
            --text-dark: #f8fafc;
            --text-light: #0f172a;
        }

        html, body {
            overflow-x: hidden !important;
            max-width: 100vw;
            box-sizing: border-box;
        }

        body { 
            background-color: var(--bg-dark);
            color: var(--text-dark);
            font-family: system-ui, -apple-system, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding-left: max(5vw, 2rem);
            padding-right: 2rem;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        html:not(.dark) body {
            background-color: var(--bg-light);
            color: var(--text-light);
        }
        
        .auth-card {
            width: 100%;
            max-width: 420px;
            border-radius: 0.25rem;
            padding: 2.5rem;
            box-sizing: border-box;
            background: var(--card-dark);
            border: 1px solid var(--border-dark);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(16px);
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }

        html:not(.dark) .auth-card {
            background: var(--card-light);
            border: 1px solid var(--border-light);
            box-shadow: 0 20px 35px -10px rgba(148, 163, 184, 0.25);
        }

        .theme-input {
            width: 100%;
            background-color: var(--input-bg-dark);
            border: 1px solid var(--input-border-dark);
            color: #ffffff;
            border-radius: 0.25rem;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        html:not(.dark) .theme-input {
            background-color: var(--input-bg-light);
            border: 1px solid var(--input-border-light);
            color: #0f172a;
        }

        .theme-input::placeholder { color: #64748b; }
        html:not(.dark) .theme-input::placeholder { color: #94a3b8; }

        .theme-input:focus {
            border-color: #0bcf9e;
            outline: none;
            box-shadow: 0 0 10px rgba(11, 207, 158, 0.2);
        }

        .theme-box {
            background-color: #0f172a;
            border: 1px solid #1e293b;
            color: #3df3c6;
            border-radius: 0.25rem;
        }

        html:not(.dark) .theme-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #07c293;
        }

        html.dark input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 30px #0f172a inset !important;
            -webkit-text-fill-color: #ffffff !important;
        }
        html:not(.dark) input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 30px #ffffff inset !important;
            -webkit-text-fill-color: #0f172a !important;
        }

        .sketch-icon {
            color: transparent !important;
            -webkit-text-stroke: 1.2px #0bcf9e;
            text-shadow: 0.8px 0.8px 0px rgba(11, 207, 158, 0.4);
        }
    </style>
</head>
<body class="relative">

    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl"></div>
    </div>

    <div class="auth-card relative z-10">
        <div class="flex justify-end mb-4">
            <button onclick="toggleTheme()" id="themeBtn" class="theme-box w-9 h-9 flex items-center justify-center transition shadow-sm cursor-pointer hover:opacity-80" title="Toggle Theme">
                <i class="fa-solid fa-moon text-xs" id="themeIcon"></i>
            </button>
        </div>

        <div class="text-center mb-8">
            <div class="theme-box w-12 h-12 mx-auto mb-3 flex items-center justify-center shadow-inner">
                <i class="sketch-icon fa-solid fa-shield-halved text-xl"></i>
            </div>
            <h1 class="text-xl font-black tracking-widest text-teal-600 dark:text-teal-400 mb-1">SET NEW PASSWORD</h1>
            <p class="text-slate-600 dark:text-slate-400 text-xs">Enter your new administrator password</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-3.5 rounded-sm bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs flex items-center gap-3 shadow-sm backdrop-blur-md">
                <i class="fa-solid fa-circle-exclamation text-sm shrink-0 text-rose-500"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="mb-6 p-3.5 rounded-sm bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs flex items-center gap-3 shadow-sm backdrop-blur-md">
                <i class="fa-solid fa-circle-check text-sm shrink-0 text-emerald-500"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($valid_token): ?>
            <form action="" method="POST" class="space-y-5">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div>
                    <label class="block text-[11px] uppercase font-bold tracking-wider text-slate-600 dark:text-slate-400 mb-2">New Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 dark:text-slate-500 pointer-events-none">
                            <i class="fa-solid fa-lock text-xs"></i>
                        </span>
                        <input type="password" name="password" required placeholder="••••••••" class="theme-input">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] uppercase font-bold tracking-wider text-slate-600 dark:text-slate-400 mb-2">Confirm New Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 dark:text-slate-500 pointer-events-none">
                            <i class="fa-solid fa-lock text-xs"></i>
                        </span>
                        <input type="password" name="confirm_password" required placeholder="••••••••" class="theme-input">
                    </div>
                </div>

                <button type="submit" class="w-full mt-2 bg-gradient-to-r from-teal-600 to-teal-500 hover:from-teal-500 hover:to-teal-400 text-white font-bold py-3.5 rounded-sm transition shadow-lg shadow-teal-600/20 text-sm flex items-center justify-center gap-2 cursor-pointer">
                    <span>Update Password</span>
                    <i class="fa-solid fa-check text-xs"></i>
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-6 text-center">
            <a href="/admin/" class="text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 transition flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-arrow-left text-[10px]"></i>
                <span>Return to Admin Login</span>
            </a>
        </div>
    </div>

    <script>
        const htmlElement = document.documentElement;
        const themeIcon = document.getElementById('themeIcon');
        const metaThemeColor = document.getElementById('metaThemeColor');

        function setTheme(isDark) {
            if (isDark) {
                htmlElement.classList.add('dark');
                metaThemeColor.setAttribute('content', '#080b11');
                themeIcon.className = "fa-solid fa-moon text-xs";
                localStorage.setItem('avenpay_admin_theme_v2', 'dark');
            } else {
                htmlElement.classList.remove('dark');
                metaThemeColor.setAttribute('content', '#f1f5f9');
                themeIcon.className = "fa-solid fa-sun text-xs";
                localStorage.setItem('avenpay_admin_theme_v2', 'light');
            }
        }

        function toggleTheme() {
            setTheme(!htmlElement.classList.contains('dark'));
        }

        if (localStorage.getItem('avenpay_admin_theme_v2') === 'dark') {
            setTheme(true);
        } else {
            setTheme(false);
        }
    </script>
</body>
</html>