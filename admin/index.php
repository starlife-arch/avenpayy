<?php
session_start();

// Look for db.php across common directory structures (adjusted for 'admin/' subfolder)
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
require_once __DIR__ . '/../lib/security.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { av_rate_limit_or_die('admin_login', 8, 600); }
} else {
    die("Database configuration file not found. Please check your folder structure.");
}

// Capture errors cleanly from session flash messages (OAuth redirects or form validations)
$error = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']); // Clear it immediately so it doesn't persist on page refresh
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? ''); // Accepts Username or Email
    $password = $_POST['password'] ?? '';

    if (!empty($identifier) && !empty($password)) {
        // Prepare statement to check 'admins' table
        $stmt = $conn->prepare("SELECT id, username, email, password FROM admins WHERE username = ? OR email = ?");
        
        if ($stmt) {
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                
                // Verify password against hashed database entry
                if ($admin && password_verify($password, $admin['password'])) {
                    session_regenerate_id(true);
                    
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Invalid password provided for this admin account.";
                }
            } else {
                $error = "This account is not registered as an administrator.";
            }
            $stmt->close();
        } else {
            $error = "Database query error. Please try again.";
        }
    } else {
        $error = "Please fill in all required fields.";
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
    <title>Admin Sign In - AvenPay</title>
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

        .social-btn {
            background-color: #0f172a;
            border: 1px solid #1e293b;
            color: #f8fafc;
            border-radius: 0.25rem;
            transition: all 0.2s ease;
        }

        html:not(.dark) .social-btn {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #0f172a;
        }

        .social-btn:hover {
            border-color: #0bcf9e;
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

    <!-- Strictly contained background wrapper to prevent horizontal overflow/scroll -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl"></div>
    </div>

    <div class="auth-card relative z-10" id="loginCard">
        
        <div class="flex justify-end mb-4">
            <button onclick="toggleTheme()" id="themeBtn" class="theme-box w-9 h-9 flex items-center justify-center transition shadow-sm cursor-pointer hover:opacity-80" title="Toggle Theme">
                <i class="fa-solid fa-moon text-xs" id="themeIcon"></i>
            </button>
        </div>

        <div class="text-center mb-8">
            <div class="theme-box w-12 h-12 mx-auto mb-3 flex items-center justify-center shadow-inner">
                <i class="sketch-icon fa-solid fa-shield-halved text-xl"></i>
            </div>
            <h1 class="text-xl font-black tracking-widest text-teal-600 dark:text-teal-400 mb-1">AVENPAY ADMIN</h1>
            <p class="text-slate-600 dark:text-slate-400 text-xs">Secure Gateway Control & Management Portal</p>
        </div>

        <!-- Theme-Synced Error Notification Banner with Auto-Disappear -->
        <?php if (!empty($error)): ?>
            <div id="errorBanner" class="mb-6 p-3.5 rounded-sm bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs flex items-center gap-3 shadow-sm backdrop-blur-md transition-opacity duration-500">
                <i class="fa-solid fa-circle-exclamation text-sm shrink-0 text-rose-500"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-5">
            <div>
                <label class="block text-[11px] uppercase font-bold tracking-wider text-slate-600 dark:text-slate-400 mb-2">Username or Email</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 dark:text-slate-500 pointer-events-none">
                        <i class="fa-solid fa-user text-xs"></i>
                    </span>
                    <input type="text" name="identifier" required placeholder="Enter username or email" class="theme-input">
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-[11px] uppercase font-bold tracking-wider text-slate-600 dark:text-slate-400">Password</label>
                    <a href="/admin/forgot-password" class="text-[11px] text-teal-600 dark:text-teal-400 hover:underline font-medium">Forgot password?</a>
                </div>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 dark:text-slate-500 pointer-events-none">
                        <i class="fa-solid fa-lock text-xs"></i>
                    </span>
                    <input type="password" name="password" required placeholder="••••••••••••" class="theme-input">
                </div>
            </div>

            <button type="submit" class="w-full mt-2 bg-gradient-to-r from-teal-600 to-teal-500 hover:from-teal-500 hover:to-teal-400 text-white font-bold py-3.5 rounded-sm transition shadow-lg shadow-teal-600/20 text-sm flex items-center justify-center gap-2 cursor-pointer">
                <span>Sign In to Dashboard</span>
                <i class="fa-solid fa-arrow-right text-xs"></i>
            </button>
        </form>

        <!-- OAuth SSO Options for Registered Admins -->
        <div class="mt-6">
            <div class="relative flex py-2 items-center">
                <div class="flex-grow border-t border-slate-200 dark:border-slate-800"></div>
                <span class="flex-shrink mx-4 text-[10px] uppercase font-semibold text-slate-500 dark:text-slate-400 tracking-wider">Other Options</span>
                <div class="flex-grow border-t border-slate-200 dark:border-slate-800"></div>
            </div>

            <div class="grid grid-cols-2 gap-3 mt-3">
                <a href="/admin/auth-google" class="social-btn py-2.5 px-3 flex items-center justify-center gap-2 text-xs font-semibold cursor-pointer shadow-sm">
                    <i class="fa-brands fa-google text-rose-500 text-xs"></i>
                    <span>Google</span>
                </a>
                <a href="/admin/auth-github" class="social-btn py-2.5 px-3 flex items-center justify-center gap-2 text-xs font-semibold cursor-pointer shadow-sm">
                    <i class="fa-brands fa-github text-xs"></i>
                    <span>GitHub</span>
                </a>
            </div>
        </div>

    </div>

    <script>
        // Prevent form resubmission warning popup on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Auto-dismiss error banner after 5 seconds
        document.addEventListener('DOMContentLoaded', () => {
            const errorBanner = document.getElementById('errorBanner');
            if (errorBanner) {
                setTimeout(() => {
                    errorBanner.style.opacity = '0';
                    setTimeout(() => errorBanner.remove(), 500);
                }, 5000);
            }
        });

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
                localStorage.setItem('avenpay_admin_theme_v2', 'light');
            }
        }

        function toggleTheme() {
            const isDark = htmlElement.classList.contains('dark');
            setTheme(!isDark);
        }

        const savedTheme = localStorage.getItem('avenpay_admin_theme_v2');
        if (savedTheme === 'dark') {
            setTheme(true);
        } else {
            setTheme(false);
        }
    </script>
</body>
</html>