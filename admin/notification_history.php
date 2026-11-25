<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
if (empty($_SESSION['admin_id'])) { header('Location: /admin/'); exit(); }
$page_title = 'Notification History';
$filter = (string) ($_GET['status'] ?? 'all');
if (!in_array($filter, ['all', 'sent', 'failed', 'pending'], true)) $filter = 'all';
$rows = [];
$historyError = '';
try {
    if ($filter === 'all') {
        $result = $conn->query('SELECT id, recipient_email, recipient_name, subject, status, provider_reference, error_message, created_at, sent_at FROM email_notification_history ORDER BY id DESC LIMIT 200');
    } else {
        $stmt = $conn->prepare('SELECT id, recipient_email, recipient_name, subject, status, provider_reference, error_message, created_at, sent_at FROM email_notification_history WHERE status = ? ORDER BY id DESC LIMIT 200');
        $stmt->bind_param('s', $filter);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    if (isset($stmt)) $stmt->close();
} catch (Throwable $e) {
    error_log('AvenPay notification history could not be loaded: ' . $e->getMessage());
    $historyError = 'Email history is not available yet. Apply the email notification history database migration, then refresh this page.';
}
include_once __DIR__ . '/admin-frame/header.php';
?>
<style>html,body{background:#f8fafc!important;color:#0f172a!important}</style>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 pb-4 border-b border-inherit">
 <div><h1 class="text-sm font-black tracking-wide uppercase flex items-center gap-2"><i class="iclass fa-solid fa-clock-rotate-left text-teal-600"></i>Notification History</h1><p class="text-[11px] text-slate-500 mt-1">Email delivery attempts, outcomes and SMTP error details. SMS history is not included.</p></div>
 <a href="/admin/email_notices" class="px-3 py-2 bg-teal-700 text-white rounded-sm text-xs font-bold">Compose email</a>
</div>
<?php if ($historyError): ?><div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-sm"><?= htmlspecialchars($historyError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="flex flex-wrap gap-2 mb-4">
 <?php foreach (['all'=>'All','sent'=>'Sent','failed'=>'Failed','pending'=>'Pending'] as $key=>$label): ?>
 <a href="/admin/notification_history?status=<?= $key ?>" class="px-3 py-2 rounded-sm text-xs font-bold <?= $filter===$key?'bg-teal-700 text-white':'bg-white border border-slate-200 text-slate-600' ?>"><?= $label ?></a>
 <?php endforeach; ?>
</div>
<div class="dash-card rounded-sm overflow-hidden">
 <div class="overflow-x-auto">
 <table class="w-full text-left text-xs">
  <thead class="bg-slate-100 text-slate-600 uppercase"><tr><th class="p-3">Date</th><th class="p-3">Recipient</th><th class="p-3">Subject</th><th class="p-3">Status</th><th class="p-3">SMTP / Provider detail</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
   <tr class="border-t border-slate-200 align-top">
    <td class="p-3 whitespace-nowrap"><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?><div class="text-[10px] text-slate-500"><?= $row['sent_at'] ? 'Sent: '.htmlspecialchars((string)$row['sent_at'], ENT_QUOTES, 'UTF-8') : '' ?></div></td>
    <td class="p-3"><strong><?= htmlspecialchars((string)$row['recipient_name'], ENT_QUOTES, 'UTF-8') ?></strong><div class="text-slate-500 break-all"><?= htmlspecialchars((string)$row['recipient_email'], ENT_QUOTES, 'UTF-8') ?></div></td>
    <td class="p-3 min-w-[180px]"><?= htmlspecialchars((string)$row['subject'], ENT_QUOTES, 'UTF-8') ?></td>
    <td class="p-3"><span class="inline-block px-2 py-1 rounded-sm text-[10px] font-bold <?= $row['status']==='sent'?'bg-emerald-50 text-emerald-700':($row['status']==='failed'?'bg-rose-50 text-rose-700':'bg-amber-50 text-amber-700') ?>"><?= htmlspecialchars(strtoupper((string)$row['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
    <td class="p-3 min-w-[220px] break-words"><?= htmlspecialchars((string)($row['error_message'] ?: $row['provider_reference'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
   </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="p-8 text-center text-slate-500">No email notification records found.</td></tr><?php endif; ?>
  </tbody>
 </table>
 </div>
 <div class="p-3 border-t border-slate-200 text-[10px] text-slate-500">Showing up to 200 recent email attempts. Message bodies and SMTP passwords are not stored in this history.</div>
</div>
