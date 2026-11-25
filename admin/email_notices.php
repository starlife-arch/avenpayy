<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/security.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/mailer.php';
if (empty($_SESSION['admin_id'])) { header('Location: /admin/'); exit(); }

$page_title = 'Email Notices';
$success_message = '';
$error_message = '';
if (empty($_SESSION['email_notice_csrf'])) $_SESSION['email_notice_csrf'] = bin2hex(random_bytes(32));
$csrf = (string) $_SESSION['email_notice_csrf'];
$senderHost = parse_url((string) av_env('APP_URL', 'https://avenpay.co.ke'), PHP_URL_HOST) ?: 'avenpay.co.ke';
$senderEmail = (string) av_env('MAIL_FROM', 'no-reply@' . $senderHost);
$replyToEmail = (string) av_env('SUPPORT_EMAIL', 'avenpayenterprises@gmail.com');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'send_email_notice') {
    av_rate_limit_or_die('admin_email_notice_send', 3, 300);
    $submittedCsrf = (string) ($_POST['csrf_token'] ?? '');
    if ($submittedCsrf === '' || !hash_equals($csrf, $submittedCsrf)) {
        $error_message = 'Your session expired. Refresh the page and try again.';
    } else {
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $mode = (string) ($_POST['recipient_mode'] ?? 'selected');
        if ($subject === '' || strlen($subject) > 180 || $message === '' || strlen($message) > 10000) {
            $error_message = 'Enter a subject (up to 180 characters) and message (up to 10,000 characters).';
        } else {
            $recipients = [];
            if ($mode === 'custom') {
                $email = trim((string) ($_POST['custom_email'] ?? ''));
                $name = trim((string) ($_POST['custom_name'] ?? ''));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) $recipients[] = ['id' => null, 'email' => $email, 'name' => $name];
                else $error_message = 'Enter a valid recipient email address.';
            } else {
                $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['user_ids'] ?? [])), static fn($id) => $id > 0)));
                if (!$ids || count($ids) > 50) {
                    $error_message = 'Select between 1 and 50 merchant accounts per send.';
                } else {
                    $marks = implode(',', array_fill(0, count($ids), '?'));
                    $types = str_repeat('i', count($ids));
                    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE id IN ($marks) AND email IS NOT NULL AND email <> ''");
                    $stmt->bind_param($types, ...$ids);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                            $recipients[] = ['id' => (int) $row['id'], 'email' => (string) $row['email'], 'name' => (string) ($row['username'] ?? '')];
                        }
                    }
                    $stmt->close();
                }
            }

            if ($error_message === '' && !$recipients) $error_message = 'No valid recipient addresses were found.';
            if ($error_message === '' && $recipients) {
                $sent = 0;
                $failed = 0;
                $htmlMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                foreach ($recipients as $recipient) {
                    $insert = $conn->prepare("INSERT INTO email_notification_history (admin_id, recipient_user_id, recipient_email, recipient_name, subject, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                    $adminId = (int) $_SESSION['admin_id'];
                    $userId = $recipient['id'];
                    $insert->bind_param('iisss', $adminId, $userId, $recipient['email'], $recipient['name'], $subject);
                    $insert->execute();
                    $historyId = (int) $insert->insert_id;
                    $insert->close();

                    $mailError = null;
                    $ok = av_send_mail($recipient['email'], $recipient['name'], $subject, '<div style="font-family:Arial,sans-serif;line-height:1.65;color:#172033">' . $htmlMessage . '<hr><p style="font-size:12px;color:#64748b">AvenPay</p></div>', $mailError);
                    $status = $ok ? 'sent' : 'failed';
                    $safeError = $ok ? null : substr((string) ($mailError ?: 'SMTP delivery failed. Check the server mail log.'), 0, 1000);
                    $sentAt = $ok ? date('Y-m-d H:i:s') : null;
                    $update = $conn->prepare('UPDATE email_notification_history SET status = ?, error_message = ?, sent_at = ? WHERE id = ?');
                    $update->bind_param('sssi', $status, $safeError, $sentAt, $historyId);
                    $update->execute();
                    $update->close();
                    if ($ok) $sent++; else $failed++;
                }
                $success_message = "Email run finished: {$sent} sent, {$failed} failed. See Notification History for details.";
            }
        }
    }
}

$users = [];
$userResult = $conn->query("SELECT id, username, email FROM users WHERE email IS NOT NULL AND email <> '' ORDER BY username ASC LIMIT 500");
if ($userResult) while ($row = $userResult->fetch_assoc()) $users[] = $row;
include_once __DIR__ . '/admin-frame/header.php';
?>
<style>
html,body{background:#f8fafc!important;color:#0f172a!important}
input,select,textarea{background:#fff!important;color:#0f172a!important}
</style>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 pb-4 border-b border-inherit">
 <div><h1 class="text-sm font-black tracking-wide uppercase flex items-center gap-2"><i class="iclass fa-solid fa-envelope-open-text text-teal-600"></i>Email Notices</h1><p class="text-[11px] text-slate-500 mt-1">Compose and send individual or selected-recipient email notices.</p></div>
</div>
<?php if ($success_message): ?><div class="mb-4 p-3 rounded-sm bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs"><?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="mb-4 p-3 rounded-sm bg-rose-50 border border-rose-200 text-rose-700 text-xs"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
 <section class="lg:col-span-2 dash-card rounded-sm p-5">
  <h2 class="text-xs font-bold uppercase tracking-wider mb-4">Compose email</h2>
  <form method="POST" class="space-y-4" onsubmit="return confirm('Send this email to the selected recipient(s)? This sends real email.');">
   <input type="hidden" name="action" value="send_email_notice"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
   <div><label class="block text-xs font-semibold mb-1">Recipients</label>
    <div class="flex flex-wrap gap-4 text-xs"><label><input type="radio" name="recipient_mode" value="selected" checked onchange="toggleRecipientMode()"> Selected merchant accounts</label><label><input type="radio" name="recipient_mode" value="custom" onchange="toggleRecipientMode()"> One custom email address</label></div>
   </div>
   <div id="selectedRecipients" class="border border-slate-200 rounded-sm p-3">
    <div class="flex items-center justify-between gap-2 mb-2"><span class="text-xs font-semibold">Merchant recipients (up to 50)</span><button type="button" class="text-xs text-teal-700 font-semibold" onclick="toggleAllRecipients(true)">Select all shown</button><button type="button" class="text-xs text-slate-600" onclick="toggleAllRecipients(false)">Clear</button></div>
    <div class="max-h-56 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-2">
    <?php foreach ($users as $user): ?><label class="flex items-start gap-2 text-xs p-1"><input type="checkbox" name="user_ids[]" value="<?= (int)$user['id'] ?>" class="recipient-check mt-0.5"><span class="min-w-0"><strong><?= htmlspecialchars((string)$user['username'], ENT_QUOTES, 'UTF-8') ?></strong><span class="block text-slate-500 break-all"><?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?></span></span></label><?php endforeach; ?>
    <?php if (!$users): ?><p class="text-xs text-slate-500">No merchant email addresses found.</p><?php endif; ?>
    </div>
    <p class="text-[10px] text-slate-500 mt-2">Showing up to 500 accounts. Choose recipients explicitly; no automatic all-user broadcast is performed.</p>
   </div>
   <div id="customRecipient" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3">
    <div><label class="block text-xs font-semibold mb-1">Recipient email</label><input type="email" name="custom_email" class="w-full border border-slate-300 rounded-sm p-2 text-sm" placeholder="name@example.com"></div>
    <div><label class="block text-xs font-semibold mb-1">Recipient name (optional)</label><input type="text" name="custom_name" class="w-full border border-slate-300 rounded-sm p-2 text-sm" maxlength="190"></div>
   </div>
   <div><label class="block text-xs font-semibold mb-1">Subject</label><input type="text" name="subject" required maxlength="180" class="w-full border border-slate-300 rounded-sm p-2 text-sm" placeholder="AvenPay account notice"></div>
   <div><label class="block text-xs font-semibold mb-1">Message</label><textarea name="message" required maxlength="10000" rows="8" class="w-full border border-slate-300 rounded-sm p-3 text-sm" placeholder="Write your email message..."></textarea><p class="text-[10px] text-slate-500 mt-1">Text-only message formatting. HTML is escaped before sending.</p></div>
   <button type="submit" class="px-4 py-2 bg-teal-700 hover:bg-teal-800 text-white rounded-sm text-xs font-bold">Send email notice</button>
  </form>
 </section>
 <aside class="dash-card rounded-sm p-5 h-fit space-y-3">
  <h2 class="text-xs font-bold uppercase tracking-wider">Configured sender</h2>
  <div><p class="text-[10px] text-slate-500">From address</p><p class="text-xs font-semibold break-all"><?= htmlspecialchars($senderEmail, ENT_QUOTES, 'UTF-8') ?></p></div>
  <div><p class="text-[10px] text-slate-500">Reply-to address</p><p class="text-xs font-semibold break-all"><?= htmlspecialchars($replyToEmail, ENT_QUOTES, 'UTF-8') ?></p></div>
  <div><p class="text-[10px] text-slate-500">SMTP host</p><p class="text-xs font-semibold"><?= htmlspecialchars((string)av_env('SMTP_HOST', 'mail.' . $senderHost), ENT_QUOTES, 'UTF-8') ?></p></div>
  <div><p class="text-[10px] text-slate-500">SMTP port</p><p class="text-xs font-semibold"><?= (int)av_env('SMTP_PORT', 465) ?></p></div>
  <p class="text-[10px] text-slate-500 border-t border-slate-200 pt-3">Sender and SMTP credentials are controlled by the private server environment. Passwords are never displayed here. Delivery attempts and SMTP errors are recorded in Notification History.</p>
  <a href="/admin/notification_history" class="inline-flex text-xs text-teal-700 font-bold">View notification history →</a>
 </aside>
</div>
<script>
function toggleRecipientMode(){const custom=document.querySelector('input[name="recipient_mode"]:checked')?.value==='custom';document.getElementById('selectedRecipients').classList.toggle('hidden',custom);document.getElementById('customRecipient').classList.toggle('hidden',!custom);}
function toggleAllRecipients(value){document.querySelectorAll('.recipient-check').forEach((el)=>el.checked=value);}
</script>
