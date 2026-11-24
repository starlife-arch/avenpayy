</div> <!-- Closing your main content wrapper -->

<footer id="avenpayFooter" class="w-full py-6 px-6 border-t border-slate-200/80 bg-slate-50/90 backdrop-blur-md mt-auto relative z-20" style="padding-bottom: calc(1.5rem + env(safe-area-inset-bottom));">
    <div class="flex flex-col sm:flex-row justify-between items-center max-w-7xl mx-auto w-full gap-4">
        <!-- Branding -->
        <div class="flex items-center space-x-2 text-xs">
            <span class="text-slate-500">&copy; <?php echo date('Y'); ?></span>
            <span class="font-extrabold text-slate-900 tracking-wide">
                <?php echo ucfirst(strtolower("AVENPAY")); ?>
            </span>
            <span class="text-slate-800">|</span>
            <span class="text-slate-500">Designed by <span class="text-slate-600 font-semibold">Leen Avenpay Solutions</span></span>
        </div>
        
        <!-- Updated Documentation Links -->
        <div class="flex items-center space-x-6 text-xs text-slate-500 font-medium">
            <a href="/api/docs/c2b.php" class="hover:text-teal-600 transition-colors flex items-center gap-1.5">
                <i class="iclass fa-solid fa-code text-teal-600 text-[11px]"></i> C2B Docs
            </a>
            <a href="/api/docs/b2c.php" class="hover:text-teal-600 transition-colors flex items-center gap-1.5">
                <i class="iclass fa-solid fa-money-bill-transfer text-emerald-600 text-[11px]"></i> B2C Docs
            </a>
        </div>
    </div>
</footer>

<style>
    #avenpayFooter {
        margin-top: auto !important;
        flex-shrink: 0;
        position: relative;
        z-index: 20;
    }
</style>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const footer = document.getElementById("avenpayFooter");
    if (!footer) return;

    const forceStickyBottom = () => {
        footer.style.marginTop = "auto";
        
        const docHeight = document.body.scrollHeight;
        const screenHeight = window.innerHeight;
        
        if (docHeight < screenHeight) {
            const emptySpace = screenHeight - docHeight;
            const currentMargin = parseFloat(window.getComputedStyle(footer).marginTop) || 0;
            footer.style.marginTop = (currentMargin + emptySpace) + "px";
        }
    };

    forceStickyBottom();
    window.addEventListener("resize", forceStickyBottom);
});

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