<?php
// frame/footer.php
// Manually defined settings without database connection
$siteName = 'AvenPay';
$manageName = 'Leen Avenpay Solutions';
?>

    </main>
</div> <!-- Closing main content wrapper -->

<footer id="simpleFooter">
    Copyright © <?php echo date("Y"); ?> 
    <strong style="color: #0bcf9e;"><?php echo htmlspecialchars($siteName); ?></strong>. 
    Designed with ❤️ by <strong><?php echo htmlspecialchars($manageName); ?></strong>.
</footer>

<style>
    #simpleFooter {
        padding: 20px;
        text-align: center;
        font-size: 13px;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        margin-top: auto;
        flex-shrink: 0;
        transition: background-color 0.25s ease, color 0.25s ease, border-color 0.25s ease;
        border-top: 1px solid rgba(203, 213, 225, 0.9);
        padding-bottom: calc(20px + env(safe-area-inset-bottom));
    }

    /* Light Mode */
    html:not(.dark) #simpleFooter {
        background-color: #ffffff !important;
        color: #0f172a !important;
        border-top-color: rgba(203, 213, 225, 0.9);
    }

    /* Dark Mode */
    html.dark #simpleFooter {
        background-color: #0f172a !important;
        color: #f8fafc !important;
        border-top-color: rgba(255, 255, 255, 0.08);
    }
</style>

</body>
</html>