<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - AvenPay</title>
    <!-- Font Awesome for iclass icons -->
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
            --success-green: #059669;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        
        .auth-container { width: 100%; max-width: 420px; position: relative; }
        
        /* Ambient background glow */
        .auth-container::before {
            content: '';
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

        /* Card Container Styling */
        .auth-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 1rem;
            padding: 36px 32px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(16px);
            position: relative;
            z-index: 10;
        }

        h1 { font-size: 22px; font-weight: 800; text-align: center; margin-bottom: 24px; letter-spacing: -0.5px; color: var(--text-main); }
        
        .msg-box { padding: 12px 14px; border-radius: 0.75rem; font-size: 11px; text-align: center; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .error { background: #fef2f2; color: #b91c1c; border: 1px solid rgba(220, 38, 38, 0.3); }
        .success { background: #f0fdf4; color: #15803d; border: 1px solid rgba(22, 163, 74, 0.3); }
        
        form { display: flex; flex-direction: column; gap: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 11px; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px; }
        
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-wrapper input { width: 100%; background-color: var(--input-bg); border: 1px solid var(--input-border); border-radius: 0.75rem; padding: 12px 42px 12px 42px; color: var(--text-main); font-size: 13px; transition: all 0.2s ease; }
        .input-wrapper input:focus { outline: none; border-color: var(--accent-purple); box-shadow: 0 0 0 2px rgba(11, 154, 116, 0.15); }
        
        .input-icon { position: absolute; left: 14px; color: var(--text-muted); display: flex; align-items: center; justify-content: center; width: 18px; pointer-events: none; }
        .input-icon svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; }

        .toggle-password { position: absolute; right: 14px; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; background: none; border: none; padding: 0; transition: color 0.2s; }
        .toggle-password:hover { color: var(--text-main); }
        .toggle-password i { font-size: 14px; }
        
        .forgot-link { font-size: 11px; color: var(--accent-purple); text-decoration: none; font-weight: 600; align-self: flex-end; transition: color 0.2s; margin-top: -4px; }
        .forgot-link:hover { color: #0b9a74; }

        .btn-submit { background-color: var(--accent-purple); color: #ffffff; border: none; border-radius: 0.75rem; padding: 12px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(11, 154, 116, 0.3); }
        .btn-submit:hover { background-color: var(--accent-purple-hover); transform: translateY(-1px); }

        .btn-alternative { background-color: transparent; border: 1px solid var(--input-border); border-radius: 0.75rem; padding: 12px; font-size: 12px; color: var(--text-muted); text-decoration: none; text-align: center; font-weight: 600; transition: all 0.2s ease; }
        .btn-alternative:hover { border-color: var(--text-muted); color: var(--text-main); background: rgba(15, 23, 42, 0.016); }

        /* Remove white background on autofill */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-text-fill-color: var(--text-main) !important;
            -webkit-box-shadow: 0 0 0px 1000px var(--input-bg) inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>Sign in | AvenPay</h1>

            <?php if (isset($_SESSION['error'])): ?>
                <div id="msg-box" class="msg-box error">
                    <i class="iclass fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
                <script>setTimeout(() => { document.getElementById('msg-box').style.display = 'none'; }, 4000);</script>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div id="msg-box-success" class="msg-box success">
                    <i class="iclass fa-solid fa-circle-check"></i>
                    <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                </div>
                <script>setTimeout(() => { document.getElementById('msg-box-success').style.display = 'none'; }, 4000);</script>
            <?php endif; ?>

            <form action="/user/process_login" method="POST">
                <div class="form-group">
                    <label>EMAIL ADDRESS</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                        <input type="email" name="email" placeholder="you@example.com" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>PASSWORD</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                        <input type="password" name="password" id="password" placeholder="Enter password" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password', 'toggle-icon-1')">
                            <i id="toggle-icon-1" class="fa-solid fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
                
                <a href="/forgot-password" class="forgot-link">Forgot password?</a>
                
                <button type="submit" class="btn-submit">Sign In</button>
                <a href="/register" class="btn-alternative">Don't have an account? Create one &rarr;</a>
            </form>
        </div>
    </div>

    <script>
        function togglePasswordVisibility(fieldId, iconId) {
            const passwordInput = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }
        
        document.addEventListener("keydown", function (event) {
  if (
    (event.ctrlKey || event.metaKey) &&
    event.key.toLowerCase() === "u"
  ) {
    event.preventDefault();
  }
});
    </script>
</body>
</html>