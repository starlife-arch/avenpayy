<?php
require_once 'database_config/db.php';
$slug = $_GET['slug']; // Get from URL

$stmt = $conn->prepare("SELECT * FROM payment_links WHERE slug = ?");
$stmt->bind_param("s", $slug);
$stmt->execute();
$link = $stmt->get_result()->fetch_assoc();

if (!$link) die("Link not found.");
?>

<!-- UI to show link details -->
<div class="doc-card p-6">
    <?php if ($link['logo_path']): ?>
        <img src="/<?= $link['logo_path'] ?>" class="h-20 w-20 rounded-full mb-4">
    <?php endif; ?>
    
    <h1 class="text-xl font-bold"><?= htmlspecialchars($link['title']) ?></h1>
    <p><?= htmlspecialchars($link['description']) ?></p>
    
    <!-- If is_fixed is 1, show as text. Otherwise, show input -->
    <?php if ($link['is_fixed']): ?>
        <p class="text-teal-400 font-bold">Amount: KES <?= number_format($link['amount'], 2) ?></p>
    <?php else: ?>
        <input type="number" placeholder="Enter Amount" class="custom-input">
    <?php endif; ?>
</div>
