<?php
// Include database configuration
if (file_exists('database_config/db.php')) {
    require_once 'database_config/db.php';
}

// Get current relative path to auto-highlight the active menu item
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$currentUri = rtrim($currentUri, '/');

if ($currentUri === '') {
    $currentUri = '/dashboard';
}

/**
 * Render an outlined SVG navigation icon.
 */
function outlineIcon($name, $classes = '')
{
    $icons = [
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a8 8 0 0 0 0-6l1.4-1.1-1.4-2.4-1.7.7a8 8 0 0 0-5.7-1.8L11.7 2H9l-.3 2.4A8 8 0 0 0 5.9 6l-1.7-.7L2.8 7.7 4.2 9a8 8 0 0 0 0 6l-1.4 1.1 1.4 2.4 1.7-.7a8 8 0 0 0 5.7 1.8l.3 2.4h2.7l.3-2.4a8 8 0 0 0 2.8-1.6l1.7.7 1.4-2.4L19.4 15Z"/>',
        'bars' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="m18 6-12 12M6 6l12 12"/>',
        'dashboard' => '<rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="5" rx="1"/><rect x="13" y="10" width="8" height="11" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/>',
        'settlement' => '<path d="m3 9 9-6 9 6H3Z"/><path d="M5 10v9m5-9v9m4-9v9m5-9v9M3 21h18M3 19h18"/>',
        'shield' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/>',
        'receipt' => '<path d="M4 3h16v18l-3-2-3 2-3-2-3 2-4-2V3Z"/><path d="M8 8h8M8 12h8M8 16h4"/>',
        'wallet' => '<path d="M4 7h15a2 2 0 0 1 2 2v10H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h13"/><path d="M21 12h-5a2 2 0 0 0 0 4h5"/><path d="M16 14h.01"/>',
        'dot' => '<circle cx="12" cy="12" r="4"/>',
        'id-card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M7 9h.01M17 15h.01"/>',
        'webhook' => '<circle cx="12" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="19" cy="19" r="2"/><path d="M12 7v5M10.5 13 6 17m7.5-4 4 4"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8m-3 0h3v3m-8 8 3 3"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.1 0l3-3A5 5 0 0 0 13 2.9l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.1 0l-3 3A5 5 0 0 0 11 21.1l1.7-1.7"/>',
        'server' => '<rect x="3" y="3" width="18" height="7" rx="1"/><rect x="3" y="14" width="18" height="7" rx="1"/><path d="M7 6.5h.01M7 17.5h.01M11 6.5h6M11 17.5h6"/>',
        'code' => '<path d="m8 8-4 4 4 4m8-8 4 4-4 4m-3-9-2 10"/>',
        'book' => '<path d="M4 5a2 2 0 0 1 2-2h14v17H6a2 2 0 0 1-2-2V5Z"/><path d="M4 17a2 2 0 0 1 2-2h14M8 7h8M8 11h6"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5m-5 5V3"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/><rect x="3" y="3" width="18" height="18" rx="3"/>',
        'logout' => '<path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
    ];

    $svg = $icons[$name] ?? $icons['dot'];

    return '<span class="icon-outline ' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true">'
        . '<svg viewBox="0 0 24 24">' . $svg . '</svg>'
        . '</span>';
}

/**
 * Render a navigation link with an outlined SVG icon.
 */
function navItem($href, $icon, $label, $currentUri, $iconColor = 'text-teal-600')
{
    $isActive = ($currentUri === rtrim($href, '/')) && ($href !== '#');

    $classes = $isActive
        ? 'nav-item flex items-center space-x-2.5 px-3 py-2 rounded-r-full bg-white border-y border-r border-teal-500/30 text-teal-600 font-semibold shadow-sm ml-3 mr-2'
        : 'nav-item flex items-center space-x-2.5 px-3 py-2 rounded-r-full text-slate-500 hover:bg-white/60 hover:text-slate-900 transition ml-3 mr-2';

    echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . $classes . '">';
    echo outlineIcon($icon, $iconColor . ' w-3.5 text-xs');
    echo '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '</a>';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">

    <title>AvenPay</title>
    <link rel="manifest" href="/manifest.json">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <style>
        .sidebar-bg {
            background-color: #f8fafc;
        }

        .sidebar-transition {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes border-rotate {
            0% { border-color: #0bcf9e; }
            33% { border-color: #60f2ce; }
            66% { border-color: #4ce8c1; }
            100% { border-color: #0bcf9e; }
        }

        .animate-border {
            border: 2px solid transparent;
            animation: border-rotate 4s linear infinite;
        }

        .animate-spin-hover:hover {
            animation: spin-slow 3s linear infinite;
        }

        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .modal-backdrop {
            backdrop-filter: blur(4px);
        }

        .glass-card {
            background: rgba(248, 250, 252, 0.95);
            border: 1px solid rgba(15, 23, 42, 0.1);
        }

        input,
        select {
            transition: all 0.2s;
        }

        input:focus,
        select:focus {
            border-color: #0bcf9e;
            outline: none;
            box-shadow: 0 0 0 2px rgba(11, 207, 158, 0.35);
        }

        .nav-item:active {
            background-color: #f8fafc !important;
            border-color: rgba(11, 207, 158, 0.5) !important;
            color: #0e8b6b !important;
        }

        /* Outlined, unfilled icons matching the dashboard */
        .icon-outline {
            display: inline-flex;
            flex: 0 0 14px;
            width: 14px;
            height: 14px;
            align-items: center;
            justify-content: center;
            color: currentColor;
            line-height: 1;
        }

        .icon-outline > svg {
            display: block;
            width: 14px;
            height: 14px;
            fill: none !important;
            stroke: currentColor !important;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
            shape-rendering: geometricPrecision;
        }

        .icon-outline > svg *,
        .icon-outline > svg path,
        .icon-outline > svg circle,
        .icon-outline > svg rect,
        .icon-outline > svg line,
        .icon-outline > svg polyline,
        .icon-outline > svg polygon,
        .icon-outline > svg ellipse {
            fill: none !important;
            stroke: currentColor !important;
            vector-effect: non-scaling-stroke;
        }

        header .icon-outline {
            flex-basis: 14px;
            width: 14px;
            height: 14px;
        }

        header .icon-outline > svg {
            width: 14px;
            height: 14px;
        }

        .icon-outline.submenu-chevron {
            flex-basis: 10px;
            width: 10px;
            height: 10px;
        }

        .icon-outline.submenu-chevron > svg {
            width: 10px;
            height: 10px;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen">

    <header class="bg-white border-b border-slate-200 h-11 px-4 md:px-5 flex items-center justify-between sticky top-0 z-40">
        <div class="text-base font-black tracking-widest text-teal-600">AVENPAY</div>

        <div class="flex items-center space-x-2.5">
            <button
                type="button"
                aria-label="Notifications"
                class="w-7 h-7 rounded-lg bg-slate-100 border border-slate-300 text-slate-500 hover:text-teal-600 flex items-center justify-center transition text-xs"
            >
                <?= outlineIcon('bell') ?>
            </button>

            <button
                type="button"
                aria-label="Settings"
                class="w-7 h-7 rounded-lg bg-slate-100 border border-slate-300 text-slate-500 hover:text-teal-600 flex items-center justify-center transition text-xs"
            >
                <span class="animate-spin-hover inline-flex">
                    <?= outlineIcon('settings') ?>
                </span>
            </button>

            <div class="w-7 h-7 rounded-lg animate-border p-[2px]">
                <img
                    src="/profile.png"
                    alt="Profile"
                    class="w-full h-full rounded-[5px] object-cover"
                >
            </div>

            <button
                type="button"
                aria-label="Open navigation menu"
                onclick="toggleSidebarLayout(true)"
                class="w-7 h-7 rounded-lg bg-slate-100 border border-slate-300 text-teal-600 flex items-center justify-center transition text-xs"
            >
                <?= outlineIcon('bars') ?>
            </button>
        </div>
    </header>

    <div
        id="sidebarMenu"
        class="sidebar-transition fixed top-0 left-0 h-full w-60 sidebar-bg z-50 transform -translate-x-full border-r border-slate-200/80 shadow-2xl flex flex-col justify-between"
    >
        <div>
            <div class="h-11 flex items-center justify-between px-4 border-b border-slate-200/80">
                <span class="font-bold tracking-wider text-teal-600 text-xs">AVENPAY API</span>
                <button
                    type="button"
                    aria-label="Close navigation menu"
                    onclick="toggleSidebarLayout(false)"
                    class="text-slate-500 hover:text-rose-600 text-xs"
                >
                    <?= outlineIcon('close') ?>
                </button>
            </div>

            <nav class="py-3 space-y-0.5 overflow-y-auto text-xs max-h-[calc(100vh-100px)]">
                <?php
                navItem('/dashboard', 'dashboard', 'Dashboard', $currentUri, 'text-teal-600');
                navItem('/settlement', 'settlement', 'Settlement', $currentUri, 'text-rose-600');
                navItem('/daraja', 'shield', 'Daraja Credentials', $currentUri, 'text-amber-600');
                navItem('/transactions', 'receipt', 'C2B Transactions', $currentUri, 'text-emerald-600');
                ?>

                <!-- B2C Wallet Dropdown -->
                <div class="px-3 pt-1">
                    <button
                        type="button"
                        onclick="toggleSubMenu('b2cSubMenu', 'b2cChevron')"
                        class="w-full nav-item flex items-center justify-between px-3 py-2 rounded-xl text-slate-500 hover:bg-white/60 hover:text-slate-900 transition"
                    >
                        <div class="flex items-center space-x-2.5">
                            <?= outlineIcon('wallet', 'text-teal-600 w-3.5 text-xs') ?>
                            <span class="font-medium">B2C Wallet</span>
                        </div>
                        <?= outlineIcon('chevron-down', 'submenu-chevron text-slate-500 transition-transform') ?>
                    </button>

                    <div id="b2cSubMenu" class="submenu-dropdown hidden pl-7 space-y-1 mt-1">
                        <a href="/b2c/" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-slate-500 hover:text-teal-700 hover:bg-white/40 transition text-[11px]">
                            <?= outlineIcon('dot', 'text-teal-600/70') ?>
                            <span>Payments</span>
                        </a>
                        <a href="/b2c/b2c_api" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-slate-500 hover:text-teal-700 hover:bg-white/40 transition text-[11px]">
                            <?= outlineIcon('dot', 'text-teal-600/70') ?>
                            <span>Test B2C API</span>
                        </a>
                        <a href="/b2c/transactions" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-slate-500 hover:text-teal-700 hover:bg-white/40 transition text-[11px]">
                            <?= outlineIcon('dot', 'text-teal-600/70') ?>
                            <span>B2C Transactions</span>
                        </a>
                        <a href="/b2c/security-credential" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-slate-500 hover:text-teal-700 hover:bg-white/40 transition text-[11px]">
                            <?= outlineIcon('dot', 'text-teal-600/70') ?>
                            <span>Security Credential</span>
                        </a>
                        <a href="/b2c/fees" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-slate-500 hover:text-teal-700 hover:bg-white/40 transition text-[11px]">
                            <?= outlineIcon('dot', 'text-teal-600/70') ?>
                            <span>Transaction Charges</span>
                        </a>
                    </div>
                </div>

                <?php
                navItem('/wallet-token', 'id-card', 'Wallet Token', $currentUri, 'text-teal-700');
                navItem('/webhook', 'webhook', 'Webhooks', $currentUri, 'text-teal-600');
                navItem('/api', 'key', 'Test Stk Push', $currentUri, 'text-teal-600');
                navItem('/links', 'link', 'Payments Link', $currentUri, 'text-teal-600');
                navItem('/hosting', 'server', 'Hosting Plans', $currentUri, 'text-orange-600');
                ?>

                <!-- Sandbox Mode Dropdown -->
                <div class="px-3 pt-1">
                    <button
                        type="button"
                        onclick="toggleSubMenu('sandboxSubMenu', 'sandboxChevron')"
                        class="w-full nav-item flex items-center justify-between px-3 py-2 rounded-xl text-slate-500 hover:bg-white/60 hover:text-slate-900 transition"
                    >
                        <div class="flex items-center space-x-2.5">
                            <?= outlineIcon('code', 'text-teal-600 w-3.5 text-xs') ?>
                            <span class="font-medium">Sandbox Mode</span>
                        </div>
                        <?= outlineIcon('chevron-down', 'submenu-chevron text-slate-500 transition-transform') ?>
                    </button>

                    <div id="sandboxSubMenu" class="submenu-dropdown hidden pl-7 space-y-1 mt-1">
                        <a href="/api/sandbox" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-slate-500 hover:text-teal-700 hover:bg-white/40 transition text-[11px]">
                            <?= outlineIcon('dot', 'text-teal-600/70') ?>
                            <span>C2B Test Sandbox</span>
                        </a>
                        <a href="/b2c/sandbox" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-slate-500 hover:text-teal-700 hover:bg-white/40 transition text-[11px]">
                            <?= outlineIcon('dot', 'text-teal-600/70') ?>
                            <span>B2C Test Sandbox</span>
                        </a>
                    </div>
                </div>

                <!-- Documentation Dropdown -->
                <div class="px-3 pt-1">
                    <button
                        type="button"
                        onclick="toggleSubMenu('docSubMenu', 'docChevron')"
                        class="w-full nav-item flex items-center justify-between px-3 py-2 rounded-xl text-slate-500 hover:bg-white/60 hover:text-slate-900 transition"
                    >
                        <div class="flex items-center space-x-2.5">
                            <?= outlineIcon('book', 'text-amber-600 w-3.5 text-xs') ?>
                            <span class="font-medium">Documentation</span>
                        </div>
                        <?= outlineIcon('chevron-down', 'submenu-chevron text-slate-500 transition-transform') ?>
                    </button>

                    <div id="docSubMenu" class="submenu-dropdown hidden pl-7 space-y-1 mt-1">
                        <a href="/api/docs/c2b" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-slate-500 hover:text-amber-700 hover:bg-white/40 transition text-[11px]">
                            <?= outlineIcon('dot', 'text-amber-600/70') ?>
                            <span>C2B Documentation</span>
                        </a>
                        <a href="/api/docs/b2c" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-slate-500 hover:text-amber-700 hover:bg-white/40 transition text-[11px]">
                            <?= outlineIcon('dot', 'text-amber-600/70') ?>
                            <span>B2C Documentation</span>
                        </a>
                    </div>
                </div>

                <!-- PWA Install Button -->
                <div class="px-3 pt-1">
                    <button
                        type="button"
                        onclick="triggerPwaInstall()"
                        class="w-full nav-item flex items-center space-x-2.5 px-3 py-2 rounded-xl text-slate-500 hover:bg-white/60 hover:text-teal-600 transition cursor-pointer"
                    >
                        <?= outlineIcon('download', 'text-teal-600 w-3.5 text-xs') ?>
                        <span class="font-medium">Download App</span>
                    </button>
                </div>

                <?php
                navItem('/profile', 'profile', 'My Profile', $currentUri, 'text-teal-700');
                ?>
            </nav>
        </div>

        <!-- Logout Button -->
        <div class="p-3 border-t border-slate-200/80 bg-slate-50/40">
            <a
                href="/logout"
                class="flex items-center justify-center space-x-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-rose-950/80 to-rose-900/60 border border-rose-500/30 text-rose-300 hover:text-white hover:border-rose-400/50 hover:shadow-lg hover:shadow-rose-950/50 transition-all font-semibold text-xs"
            >
                <?= outlineIcon('logout', 'text-rose-600') ?>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <div
        id="sidebarBackdrop"
        onclick="toggleSidebarLayout(false)"
        class="fixed inset-0 bg-black/60 z-40 hidden"
    ></div>

    <!-- PWA Install Modal -->
    <div
        id="pwaInstallModal"
        class="hidden fixed inset-0 bg-slate-50/80 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    >
        <div class="w-full max-w-xs bg-white border border-slate-200 rounded-2xl p-6 shadow-2xl text-center space-y-4">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-teal-500/10 border border-teal-500/30 flex items-center justify-center text-teal-600 text-xl">
                <?= outlineIcon('download', 'text-teal-600') ?>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-900 tracking-wide">Install AvenPay App</h3>
                <p id="pwaModalText" class="text-[11px] text-slate-500 mt-1">
                    Add AvenPay to your home screen for instant access.
                </p>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button
                    type="button"
                    onclick="closePwaModal()"
                    class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 py-2 rounded-xl font-semibold text-xs transition cursor-pointer"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    id="pwaConfirmBtn"
                    onclick="confirmPwaInstall()"
                    class="flex-1 bg-teal-600 hover:bg-teal-500 text-white py-2 rounded-xl font-semibold text-xs transition cursor-pointer"
                >
                    Install
                </button>
            </div>
        </div>
    </div>

    <script>
        let globalDeferredPrompt = null;

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            globalDeferredPrompt = event;
        });

        function triggerPwaInstall() {
            toggleSidebarLayout(false);

            const modal = document.getElementById('pwaInstallModal');
            const modalText = document.getElementById('pwaModalText');
            const confirmButton = document.getElementById('pwaConfirmBtn');

            const isInstalled =
                window.matchMedia('(display-mode: standalone)').matches ||
                window.navigator.standalone === true;

            if (isInstalled) {
                modalText.textContent = 'AvenPay is already installed on your device!';
                confirmButton.style.display = 'none';
            } else {
                modalText.textContent = 'Add AvenPay to your home screen for instant access.';
                confirmButton.style.display = 'block';
            }

            modal.classList.remove('hidden');
        }

        function closePwaModal() {
            document.getElementById('pwaInstallModal').classList.add('hidden');
        }

        async function confirmPwaInstall() {
            if (!globalDeferredPrompt) {
                document.getElementById('pwaModalText').textContent =
                    'To install, use your browser menu and choose “Install app” or “Add to Home Screen”.';
                document.getElementById('pwaConfirmBtn').style.display = 'none';
                return;
            }

            globalDeferredPrompt.prompt();
            await globalDeferredPrompt.userChoice;
            globalDeferredPrompt = null;
            closePwaModal();
        }

        function toggleSidebarLayout(show) {
            const sidebar = document.getElementById('sidebarMenu');
            const backdrop = document.getElementById('sidebarBackdrop');

            if (show) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }

        function toggleSubMenu(targetMenuId, targetChevronId) {
            const targetMenu = document.getElementById(targetMenuId);
            const targetChevron = document.getElementById(targetChevronId);
            const isCurrentlyHidden = targetMenu.classList.contains('hidden');

            document.querySelectorAll('.submenu-dropdown').forEach(function (menu) {
                menu.classList.add('hidden');
            });

            document.querySelectorAll('.submenu-chevron').forEach(function (chevron) {
                chevron.classList.remove('rotate-180');
            });

            if (isCurrentlyHidden) {
                targetMenu.classList.remove('hidden');
                targetChevron.classList.add('rotate-180');
            }
        }
    </script>
</body>
</html>