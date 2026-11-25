<?php
session_start();
require_once __DIR__ . '/database_config/db.php';
require_once __DIR__ . '/lib/password_reset.php';
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) { $_SESSION['error']='Invalid or expired password reset token.'; header('Location: /forgot-password'); exit(); }
$tokenHash = av_reset_token_hash($token);
$stmt=$conn->prepare('SELECT user_id, expiry_date FROM password_resets WHERE token IN (?, ?) LIMIT 1');
$stmt->bind_param('ss',$tokenHash,$token); $stmt->execute(); $resetRequest=$stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$resetRequest || strtotime((string)$resetRequest['expiry_date'])<=time()) {
 if($resetRequest){$del=$conn->prepare('DELETE FROM password_resets WHERE token IN (?, ?)');$del->bind_param('ss',$tokenHash,$token);$del->execute();$del->close();}
 $_SESSION['error']='Invalid or expired password reset token. Please request a new link.'; header('Location: /forgot-password'); exit();
}
$user_id=(int)$resetRequest['user_id']; $resetCsrf=av_reset_csrf_token();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 $password=(string)($_POST['password']??''); $confirmPassword=(string)($_POST['confirm_password']??'');
 if(!av_reset_csrf_valid()){$_SESSION['error']='Your reset session expired. Please reopen the link from your email.';}
 elseif(strlen($password)<6){$_SESSION['error']='Password must be at least 6 characters long.';}
 elseif($password!==$confirmPassword){$_SESSION['error']='Passwords do not match.';}
 else{
  try{
   $conn->begin_transaction();
   $lock=$conn->prepare('SELECT user_id, expiry_date FROM password_resets WHERE token IN (?, ?) FOR UPDATE');$lock->bind_param('ss',$tokenHash,$token);$lock->execute();$locked=$lock->get_result()->fetch_assoc();$lock->close();
   if(!$locked||(int)$locked['user_id']!==$user_id||strtotime((string)$locked['expiry_date'])<=time())throw new RuntimeException('Reset token is no longer valid.');
   $hash=password_hash($password,PASSWORD_DEFAULT);$update=$conn->prepare('UPDATE users SET password = ? WHERE id = ?');$update->bind_param('si',$hash,$user_id);$update->execute();
   if($update->affected_rows!==1){$update->close();throw new RuntimeException('Password update did not affect an account.');}$update->close();
   $delete=$conn->prepare('DELETE FROM password_resets WHERE user_id = ?');$delete->bind_param('i',$user_id);$delete->execute();$delete->close();$conn->commit();
   unset($_SESSION['password_reset_csrf']);$_SESSION['success']='Your password has been successfully reset. You can now sign in.';header('Location: /login');exit();
  }catch(Throwable $e){$conn->rollback();error_log('AvenPay merchant password update failed: '.$e->getMessage());$_SESSION['error']='Unable to reset your password. Please request a new link and try again.';}
 }
 header('Location: /reset-password.php?token='.rawurlencode($token));exit();
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
    <title>Reset Password - AvenPay</title>
    <!-- Font Awesome for iclass icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #ffffff;
            --card-bg: rgba(248, 250, 252, 0.95);
            --card-border: rgba(15, 23, 42, 0.08);
            --input-bg: #ffffff;
            --input-border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #475569;
            --accent-purple: #60f2ce;
            --accent-purple-hover: #3ee9be;
            --success-green: #34d399;
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
            background: rgba(96, 242, 206, 0.15);
            filter: blur(60px);
            pointer-events: none;
            border-radius: 50%;
        }

        .auth-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 1rem;
            padding: 36px 32px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.14);
            backdrop-filter: blur(16px);
            position: relative;
            z-index: 10;
        }

        h1 { font-size: 22px; font-weight: 800; text-align: center; margin-bottom: 6px; letter-spacing: -0.5px; color: #0f172a; }
        .subtitle { font-size: 12px; color: var(--text-muted); text-align: center; margin-bottom: 24px; font-weight: 500; }
        
        .msg-box { padding: 12px 14px; border-radius: 0.75rem; font-size: 11px; text-align: center; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .error { background: rgba(127, 29, 29, 0.4); color: #960303; border: 1px solid rgba(239, 68, 68, 0.3); }
        .success { background: rgba(20, 83, 45, 0.4); color: #12873c; border: 1px solid rgba(34, 197, 94, 0.3); }
        
        form { display: flex; flex-direction: column; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 11px; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px; }
        
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-wrapper input { width: 100%; background-color: var(--input-bg); border: 1px solid var(--input-border); border-radius: 0.75rem; padding: 12px 42px 12px 42px; color: var(--text-main); font-size: 13px; transition: all 0.2s ease; }
        .input-wrapper input:focus { outline: none; border-color: var(--accent-purple); box-shadow: 0 0 0 2px rgba(96, 242, 206, 0.15); }
        
        .input-icon { position: absolute; left: 14px; color: var(--text-muted); display: flex; align-items: center; justify-content: center; width: 18px; pointer-events: none; }
        .input-icon i { font-size: 14px; }

        .toggle-password { position: absolute; right: 14px; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; background: none; border: none; padding: 0; transition: color 0.2s; }
        .toggle-password:hover { color: var(--text-main); }
        .toggle-password i { font-size: 14px; }

        .btn-submit { background-color: var(--accent-purple); color: #0f172a; border: none; border-radius: 0.75rem; padding: 12px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(96, 242, 206, 0.3); margin-top: 4px; }
        .btn-submit:hover { background-color: var(--accent-purple-hover); transform: translateY(-1px); }

        .btn-alternative { background-color: transparent; border: 1px solid var(--input-border); border-radius: 0.75rem; padding: 12px; font-size: 12px; color: var(--text-muted); text-decoration: none; text-align: center; font-weight: 600; transition: all 0.2s ease; display: flex; justify-content: center; align-items: center; gap: 6px; }
        .btn-alternative:hover { border-color: var(--text-muted); color: var(--text-main); background: rgba(255, 255, 255, 0.02); }

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
            <h1>Set new password</h1>
            <p class="subtitle">Enter your new secure password below</p>

            <?php if (isset($_SESSION['error'])): ?>
                <div id="msg-box" class="msg-box error">
                    <i class="iclass fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
                <script>setTimeout(() => { document.getElementById('msg-box').style.display = 'none'; }, 4000);</script>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div id="msg-box" class="msg-box success">
                    <i class="iclass fa-solid fa-circle-check"></i>
                    <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                </div>
                <script>setTimeout(() => { document.getElementById('msg-box').style.display = 'none'; }, 4000);</script>
            <?php endif; ?>

            <form action="/reset-password.php" method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($resetCsrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label>NEW PASSWORD</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="iclass fa-solid fa-lock"></i></span>
                        <input type="password" name="password" id="password" placeholder="At least 6 characters" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password', 'toggle-icon-1')">
                            <i id="toggle-icon-1" class="fa-solid fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label>CONFIRM NEW PASSWORD</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="iclass fa-solid fa-lock"></i></span>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter new password" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password', 'toggle-icon-2')">
                            <i id="toggle-icon-2" class="fa-solid fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">Update Password</button>
                <a href="/login" class="btn-alternative">&larr; Back to Sign In</a>
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