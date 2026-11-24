<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin/');
    exit();
}

$admin_username = htmlspecialchars(
    $_SESSION['admin_username'] ?? 'Administrator',
    ENT_QUOTES,
    'UTF-8'
);
$admin_initial = strtoupper(substr($admin_username, 0, 1));
$currentUri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '', '/');

/**
 * Render a navigation link.
 * Set $showIcon to false for links inside dropdowns.
 */
function renderNavLink($href, $icon, $label, $showIcon = true)
{
    global $currentUri;

    $targetUri = '/admin/' . ltrim($href, '/');
    $isActive = $currentUri === rtrim($targetUri, '/')
        || ($currentUri === '/admin' && $targetUri === '/admin/dashboard');

    $activeClass = $isActive
        ? 'bg-teal-500/15 text-teal-600 dark:text-teal-400 font-bold rounded-r-full'
        : 'text-slate-600 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 hover:bg-slate-200/50 dark:hover:bg-slate-900/50 rounded-sm';

    $iconClass = $isActive
        ? 'text-teal-600 dark:text-teal-400'
        : 'text-slate-500 dark:text-slate-400 group-hover:text-teal-600 dark:group-hover:text-teal-400';

    echo '<a href="' . htmlspecialchars($targetUri, ENT_QUOTES, 'UTF-8')
        . '" class="flex items-center gap-3 px-3 py-2.5 transition group '
        . $activeClass . '">';

    if ($showIcon) {
        echo '<i class="iclass ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8')
            . ' ' . $iconClass . ' w-4 shrink-0"></i>';
    } else {
        echo '<span aria-hidden="true" class="w-1.5 h-1.5 rounded-full bg-current opacity-60 shrink-0 ml-1.5"></span>';
    }

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" id="metaThemeColor" content="#ffffff">

    <title><?php echo isset($page_title)
        ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') . ' - AVENPAY ADMIN'
        : 'Dashboard - AVENPAY ADMIN'; ?></title>

    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-dark: #080b11;
            --bg-light: #f1f5f9;
            --card-dark: #0f172a;
            --card-light: #ffffff;
            --border-dark: rgba(255, 255, 255, 0.08);
            --border-light: rgba(203, 213, 225, 0.9);
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            overflow-x: hidden;
        }

        body {
            background: var(--bg-dark);
            color: #f8fafc;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            transition: background-color .25s ease, color .25s ease;
        }

        html:not(.dark) body {
            background: var(--bg-light);
            color: #0f172a;
        }

        .dash-card {
            background: var(--card-dark);
            border: 1px solid var(--border-dark);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
            transition: background-color .25s ease, border-color .25s ease, box-shadow .25s ease;
        }

        html:not(.dark) .dash-card {
            background: var(--card-light);
            border-color: var(--border-light);
            box-shadow: 0 10px 25px -5px rgba(148, 163, 184, 0.15);
        }

        #mobileSidebar,
        #sidebarBackdrop {
            transition: transform .25s ease-in-out, opacity .25s ease-in-out;
        }

        body.sidebar-open {
            overflow: hidden;
        }
    </style>

    <script>
        (function () {
            try {
                const theme = localStorage.getItem('avenpay_admin_theme_v2');
                document.documentElement.classList.toggle('dark', theme === 'dark');
            } catch (error) {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
</head>

<body class="min-h-screen flex flex-col md:flex-row">

<!-- DESKTOP SIDEBAR -->
<aside class="w-52 hidden md:flex flex-col dash-card border-r z-[25] shrink-0 sticky top-0 h-screen">
    <div class="h-14 px-3 sm:px-4 flex items-center gap-2.5 border-b border-inherit shrink-0">
        <div class="w-8 h-8 rounded-sm bg-teal-500/10 dark:bg-teal-600/20 text-teal-600 dark:text-teal-400 border border-teal-500/30 flex items-center justify-center shrink-0">
            <i class="iclass fa-solid fa-cube text-xs"></i>
        </div>
        <div class="flex flex-col justify-center min-w-0">
            <span class="font-black tracking-widest text-teal-600 dark:text-teal-400 text-xs leading-none">AVENPAY</span>
            <span class="text-[9px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400 mt-1 truncate">Admin Portal</span>
        </div>
    </div>

    <nav class="flex-1 p-2.5 space-y-1 text-xs font-semibold overflow-y-auto">
        <div class="pb-1 px-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Main
        </div>

        <?php
        renderNavLink('dashboard', 'fa-solid fa-gauge-high', 'Dashboard');
        renderNavLink('transactions', 'fa-solid fa-money-bill-transfer', 'Transactions');
        ?>

        <!-- MANAGE USERS -->
        <div class="pt-2">
            <button type="button"
                    onclick="toggleMenu('usersSubMenu', 'usersChevron')"
                    aria-controls="usersSubMenu"
                    aria-expanded="false"
                    class="w-full flex items-center justify-between px-2.5 py-2.5 text-slate-600 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 hover:bg-slate-200/50 dark:hover:bg-slate-900/50 rounded-sm transition cursor-pointer group">
                <span class="flex items-center gap-2.5 min-w-0">
                    <i class="iclass fa-solid fa-users w-4 shrink-0 text-slate-500 dark:text-slate-400 group-hover:text-teal-600 dark:group-hover:text-teal-400"></i>
                    <span class="truncate">Manage Users</span>
                </span>
                <i id="usersChevron" class="iclass fa-solid fa-chevron-down text-[10px] transition-transform duration-200 shrink-0"></i>
            </button>

            <div id="usersSubMenu" class="hidden pl-5 pr-1 pt-1 space-y-1">
                <?php
                renderNavLink('users', '', 'Users', false);
                renderNavLink('users_list', '', 'All Accounts', false);
                renderNavLink('users_subscriptions', '', 'Activate Subs', false);
                renderNavLink('users_delete', '', 'Delete Users', false);
                ?>
            </div>
        </div>

        <!-- DARAJA M-PESA -->
        <div class="pt-3 pb-1 px-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Daraja M-Pesa
        </div>

        <!-- C2B SETUP -->
        <div>
            <button type="button"
                    onclick="toggleMenu('c2bSubMenu', 'c2bChevron')"
                    aria-controls="c2bSubMenu"
                    aria-expanded="false"
                    class="w-full flex items-center justify-between px-2.5 py-2.5 text-slate-600 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 hover:bg-slate-200/50 dark:hover:bg-slate-900/50 rounded-sm transition cursor-pointer group">
                <span class="flex items-center gap-2.5 min-w-0">
                    <i class="iclass fa-solid fa-arrow-down-long w-4 shrink-0 text-slate-500 dark:text-slate-400 group-hover:text-teal-600 dark:group-hover:text-teal-400"></i>
                    <span class="truncate">C2B Setup</span>
                </span>
                <i id="c2bChevron" class="iclass fa-solid fa-chevron-down text-[10px] transition-transform duration-200 shrink-0"></i>
            </button>

            <div id="c2bSubMenu" class="hidden pl-5 pr-1 pt-1 space-y-1">
                <?php
                renderNavLink('daraja', '', 'API Settings', false);
                renderNavLink('c2b', '', 'C2B Top-ups', false);
                ?>
            </div>
        </div>

        <!-- B2C SETUP -->
        <div>
            <button type="button"
                    onclick="toggleMenu('b2cSubMenu', 'b2cChevron')"
                    aria-controls="b2cSubMenu"
                    aria-expanded="false"
                    class="w-full flex items-center justify-between px-2.5 py-2.5 text-slate-600 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 hover:bg-slate-200/50 dark:hover:bg-slate-900/50 rounded-sm transition cursor-pointer group">
                <span class="flex items-center gap-2.5 min-w-0">
                    <i class="iclass fa-solid fa-arrow-up-from-bracket w-4 shrink-0 text-slate-500 dark:text-slate-400 group-hover:text-teal-600 dark:group-hover:text-teal-400"></i>
                    <span class="truncate">B2C Setup</span>
                </span>
                <i id="b2cChevron" class="iclass fa-solid fa-chevron-down text-[10px] transition-transform duration-200 shrink-0"></i>
            </button>

            <div id="b2cSubMenu" class="hidden pl-5 pr-1 pt-1 space-y-1">
                <?php
                renderNavLink('b2c_settings', '', 'B2C Settings', false);
                renderNavLink('b2c', '', 'B2C Payouts', false);
                ?>
            </div>
        </div>

        <!-- COMMON GATEWAYS -->
        <?php
        renderNavLink('daraja_gateways', 'fa-solid fa-network-wired', 'Gateways');
        renderNavLink('fees_manager', 'fa-solid fa-percent', 'Fee Tiers');
        renderNavLink('payout_review', 'fa-solid fa-triangle-exclamation', 'Stuck Payouts');
        ?>

        <!-- NOTIFICATIONS -->
        <div class="pt-3 pb-1 px-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Notifications
        </div>
        <?php
        renderNavLink('sms_notices', 'fa-solid fa-comment-sms', 'SMS Notices');
        renderNavLink('email_notices', 'fa-solid fa-envelope-open-text', 'Email Notices');
        renderNavLink('notification_history', 'fa-solid fa-clock-rotate-left', 'Notif. History');
        ?>

        <!-- SYSTEM -->
        <div class="pt-3 pb-1 px-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            System
        </div>
        <?php
        renderNavLink('admins', 'fa-solid fa-users-gear', 'Admin Accounts');
        renderNavLink('admin_settlement', 'fa-solid fa-building-columns', 'Settlement Accounts');
        ?>
    </nav>

    <div class="p-2.5 border-t border-inherit">
        <a href="/admin/logout" class="flex items-center gap-2.5 px-2.5 py-2 rounded-sm text-rose-500 hover:bg-rose-500/15 transition text-xs font-semibold group">
            <i class="iclass fa-solid fa-arrow-right-from-bracket w-4 shrink-0"></i>
            <span>Sign Out</span>
        </a>
    </div>
</aside>

<!-- MOBILE BACKDROP -->
<div id="sidebarBackdrop"
     class="fixed inset-0 bg-slate-950/80 z-[50] hidden opacity-0"
     onclick="toggleMobileSidebar()"></div>

<!-- MOBILE SIDEBAR -->
<aside id="mobileSidebar"
       class="fixed inset-y-0 left-0 w-64 flex flex-col dash-card border-r z-[60] transform -translate-x-full md:hidden shadow-2xl">

    <div class="h-14 px-4 flex items-center justify-between border-b border-inherit shrink-0">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-sm bg-teal-500/10 dark:bg-teal-600/20 text-teal-600 dark:text-teal-400 border border-teal-500/30 flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-cube text-xs"></i>
            </div>
            <div class="flex flex-col justify-center">
                <span class="font-black tracking-widest text-teal-600 dark:text-teal-400 text-xs leading-none">AVENPAY</span>
                <span class="text-[9px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400 mt-1">Admin Portal</span>
            </div>
        </div>

        <button type="button"
                onclick="toggleMobileSidebar()"
                aria-label="Close menu"
                class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 w-8 h-8 rounded flex items-center justify-center transition">
            <i class="iclass fa-solid fa-xmark"></i>
        </button>
    </div>

    <nav class="flex-1 p-2.5 space-y-1 text-xs font-semibold overflow-y-auto">
        <div class="pb-1 px-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Main
        </div>

        <?php
        renderNavLink('dashboard', 'fa-solid fa-gauge-high', 'Dashboard');
        renderNavLink('transactions', 'fa-solid fa-money-bill-transfer', 'Transactions');
        ?>

        <!-- MANAGE USERS -->
        <div class="pt-2">
            <button type="button"
                    onclick="toggleMenu('usersSubMenuMobile', 'usersChevronMobile')"
                    aria-controls="usersSubMenuMobile"
                    aria-expanded="false"
                    class="w-full flex items-center justify-between px-2.5 py-2.5 text-slate-600 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 hover:bg-slate-200/50 dark:hover:bg-slate-900/50 rounded-sm transition cursor-pointer group">
                <span class="flex items-center gap-2.5 min-w-0">
                    <i class="iclass fa-solid fa-users w-4 shrink-0 text-slate-500 dark:text-slate-400 group-hover:text-teal-600 dark:group-hover:text-teal-400"></i>
                    <span class="truncate">Manage Users</span>
                </span>
                <i id="usersChevronMobile" class="iclass fa-solid fa-chevron-down text-[10px] transition-transform duration-200 shrink-0"></i>
            </button>

            <div id="usersSubMenuMobile" class="hidden pl-5 pr-1 pt-1 space-y-1">
                <?php
                renderNavLink('users', '', 'Users', false);
                renderNavLink('users_list', '', 'All Accounts', false);
                renderNavLink('users_subscriptions', '', 'Activate Subs', false);
                renderNavLink('users_delete', '', 'Delete Users', false);
                ?>
            </div>
        </div>

        <!-- DARAJA M-PESA -->
        <div class="pt-3 pb-1 px-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Daraja M-Pesa
        </div>

        <!-- C2B SETUP -->
        <div>
            <button type="button"
                    onclick="toggleMenu('c2bSubMenuMobile', 'c2bChevronMobile')"
                    aria-controls="c2bSubMenuMobile"
                    aria-expanded="false"
                    class="w-full flex items-center justify-between px-2.5 py-2.5 text-slate-600 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 hover:bg-slate-200/50 dark:hover:bg-slate-900/50 rounded-sm transition cursor-pointer group">
                <span class="flex items-center gap-2.5 min-w-0">
                    <i class="iclass fa-solid fa-arrow-down-long w-4 shrink-0 text-slate-500 dark:text-slate-400 group-hover:text-teal-600 dark:group-hover:text-teal-400"></i>
                    <span class="truncate">C2B Setup</span>
                </span>
                <i id="c2bChevronMobile" class="iclass fa-solid fa-chevron-down text-[10px] transition-transform duration-200 shrink-0"></i>
            </button>

            <div id="c2bSubMenuMobile" class="hidden pl-5 pr-1 pt-1 space-y-1">
                <?php
                renderNavLink('daraja', '', 'API Settings', false);
                renderNavLink('c2b', '', 'C2B Top-ups', false);
                ?>
            </div>
        </div>

        <!-- B2C SETUP -->
        <div>
            <button type="button"
                    onclick="toggleMenu('b2cSubMenuMobile', 'b2cChevronMobile')"
                    aria-controls="b2cSubMenuMobile"
                    aria-expanded="false"
                    class="w-full flex items-center justify-between px-2.5 py-2.5 text-slate-600 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 hover:bg-slate-200/50 dark:hover:bg-slate-900/50 rounded-sm transition cursor-pointer group">
                <span class="flex items-center gap-2.5 min-w-0">
                    <i class="iclass fa-solid fa-arrow-up-from-bracket w-4 shrink-0 text-slate-500 dark:text-slate-400 group-hover:text-teal-600 dark:group-hover:text-teal-400"></i>
                    <span class="truncate">B2C Setup</span>
                </span>
                <i id="b2cChevronMobile" class="iclass fa-solid fa-chevron-down text-[10px] transition-transform duration-200 shrink-0"></i>
            </button>

            <div id="b2cSubMenuMobile" class="hidden pl-5 pr-1 pt-1 space-y-1">
                <?php
                renderNavLink('b2c_settings', '', 'B2C Settings', false);
                renderNavLink('b2c', '', 'B2C Payouts', false);
                ?>
            </div>
        </div>

        <!-- COMMON GATEWAYS -->
        <?php
        renderNavLink('daraja_gateways', 'fa-solid fa-network-wired', 'Gateways');
        renderNavLink('fees_manager', 'fa-solid fa-percent', 'Fee Tiers');
        renderNavLink('payout_review', 'fa-solid fa-triangle-exclamation', 'Stuck Payouts');
        ?>

        <!-- NOTIFICATIONS -->
        <div class="pt-3 pb-1 px-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Notifications
        </div>
        <?php
        renderNavLink('sms_notices', 'fa-solid fa-comment-sms', 'SMS Notices');
        renderNavLink('email_notices', 'fa-solid fa-envelope-open-text', 'Email Notices');
        renderNavLink('notification_history', 'fa-solid fa-clock-rotate-left', 'Notif. History');
        ?>

        <!-- SYSTEM -->
        <div class="pt-3 pb-1 px-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            System
        </div>
        <?php
        renderNavLink('admins', 'fa-solid fa-users-gear', 'Admin Accounts');
        renderNavLink('admin_settlement', 'fa-solid fa-building-columns', 'Settlement Accounts');
        ?>
    </nav>

    <div class="p-2.5 border-t border-inherit">
        <a href="/admin/logout" class="flex items-center gap-2.5 px-2.5 py-2 rounded-sm text-rose-500 hover:bg-rose-500/15 transition text-xs font-semibold group">
            <i class="iclass fa-solid fa-arrow-right-from-bracket w-4 shrink-0"></i>
            <span>Sign Out</span>
        </a>
    </div>
</aside>

<!-- MAIN -->
<div class="flex-1 flex flex-col min-w-0 relative">
    <header class="h-14 dash-card border-b px-4 sm:px-5 flex items-center justify-between z-[40] sticky top-0 shrink-0">
        <div class="flex items-center gap-3">
            <button type="button"
                    onclick="toggleMobileSidebar()"
                    class="md:hidden w-8 h-8 rounded-sm bg-teal-500/10 dark:bg-teal-600/20 text-teal-600 dark:text-teal-400 border border-teal-500/30 flex items-center justify-center transition cursor-pointer shrink-0"
                    aria-label="Open menu">
                <i class="iclass fa-solid fa-bars text-xs"></i>
            </button>

            <div class="flex items-center gap-2.5 md:hidden">
                <div class="w-8 h-8 rounded-sm bg-teal-500/10 dark:bg-teal-600/20 text-teal-600 dark:text-teal-400 border border-teal-500/30 flex items-center justify-center shrink-0">
                    <i class="iclass fa-solid fa-shield-halved text-xs"></i>
                </div>
                <div class="flex flex-col justify-center">
                    <span class="font-black tracking-widest text-teal-600 dark:text-teal-400 text-xs leading-none">AVENPAY</span>
                    <span class="text-[9px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400 mt-1">Admin Portal</span>
                </div>
            </div>

            <span class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 hidden sm:inline">
                Gateway Control Center
            </span>
        </div>

        <div class="flex items-center gap-2">
            <button type="button"
                    onclick="toggleTheme()"
                    id="themeBtn"
                    class="w-8 h-8 rounded-sm bg-teal-500/10 dark:bg-teal-600/20 text-teal-600 dark:text-teal-400 border border-teal-500/30 flex items-center justify-center transition cursor-pointer shrink-0"
                    title="Toggle theme"
                    aria-label="Toggle theme">
                <i id="themeIcon" class="iclass fa-solid fa-moon text-xs"></i>
            </button>

            <div class="flex items-center gap-2 pl-1.5">
                <div class="w-8 h-8 rounded-sm bg-teal-500/10 dark:bg-teal-600/20 text-teal-600 dark:text-teal-400 border border-teal-500/30 flex items-center justify-center font-bold text-xs shrink-0">
                    <?php echo $admin_initial; ?>
                </div>
                <span class="text-xs font-black tracking-widest text-teal-600 dark:text-teal-400 hidden sm:inline">
                    <?php echo $admin_username; ?>
                </span>
            </div>
        </div>
    </header>

    <!-- CONTENT START -->
    <main class="p-4 sm:p-5 lg:p-6 space-y-5 max-w-7xl mx-auto w-full flex-1">

<script>
const dropdownMenus = [
    { menu: 'usersSubMenu', chevron: 'usersChevron' },
    { menu: 'c2bSubMenu', chevron: 'c2bChevron' },
    { menu: 'b2cSubMenu', chevron: 'b2cChevron' },
    { menu: 'usersSubMenuMobile', chevron: 'usersChevronMobile' },
    { menu: 'c2bSubMenuMobile', chevron: 'c2bChevronMobile' },
    { menu: 'b2cSubMenuMobile', chevron: 'b2cChevronMobile' }
];

function setMenuState(menuId, chevronId, isOpen) {
    const menu = document.getElementById(menuId);
    const chevron = document.getElementById(chevronId);
    const button = document.querySelector('[aria-controls="' + menuId + '"]');

    if (menu) menu.classList.toggle('hidden', !isOpen);
    if (chevron) chevron.classList.toggle('rotate-180', isOpen);
    if (button) button.setAttribute('aria-expanded', String(isOpen));
}

function toggleMenu(menuId, chevronId) {
    const targetMenu = document.getElementById(menuId);
    if (!targetMenu) return;

    const shouldOpen = targetMenu.classList.contains('hidden');

    dropdownMenus.forEach(function (item) {
        setMenuState(item.menu, item.chevron, false);
    });

    if (shouldOpen) {
        setMenuState(menuId, chevronId, true);
    }
}

function toggleMobileSidebar() {
    const sidebar = document.getElementById('mobileSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');

    if (!sidebar || !backdrop) return;

    const isOpen = !sidebar.classList.contains('-translate-x-full');

    if (isOpen) {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0');
        document.body.classList.remove('sidebar-open');

        window.setTimeout(function () {
            if (sidebar.classList.contains('-translate-x-full')) {
                backdrop.classList.add('hidden');
            }
        }, 250);
    } else {
        backdrop.classList.remove('hidden');

        requestAnimationFrame(function () {
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');
            sidebar.classList.remove('-translate-x-full');
            document.body.classList.add('sidebar-open');
        });
    }
}

function toggleTheme() {
    const html = document.documentElement;
    const icon = document.getElementById('themeIcon');
    const meta = document.getElementById('metaThemeColor');
    const isDark = html.classList.toggle('dark');

    try {
        localStorage.setItem('avenpay_admin_theme_v2', isDark ? 'dark' : 'light');
    } catch (error) {
        // Theme still changes for the current page if storage is unavailable.
    }

    if (icon) {
        icon.classList.toggle('fa-moon', isDark);
        icon.classList.toggle('fa-sun', !isDark);
    }

    if (meta) {
        meta.setAttribute('content', isDark ? '#080b11' : '#f1f5f9');
    }
}

function expandMenu(menuId, chevronId) {
    setMenuState(menuId, chevronId, true);

    const pairs = {
        usersSubMenu: ['usersSubMenuMobile', 'usersChevronMobile'],
        c2bSubMenu: ['c2bSubMenuMobile', 'c2bChevronMobile'],
        b2cSubMenu: ['b2cSubMenuMobile', 'b2cChevronMobile'],
        usersSubMenuMobile: ['usersSubMenu', 'usersChevron'],
        c2bSubMenuMobile: ['c2bSubMenu', 'c2bChevron'],
        b2cSubMenuMobile: ['b2cSubMenu', 'b2cChevron']
    };

    const pairedMenu = pairs[menuId];
    if (pairedMenu) {
        setMenuState(pairedMenu[0], pairedMenu[1], true);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    let theme = 'light';

    try {
        theme = localStorage.getItem('avenpay_admin_theme_v2') || 'light';
    } catch (error) {
        // Use the default dark theme if storage is unavailable.
    }

    const isDark = theme === 'dark';
    const icon = document.getElementById('themeIcon');
    const meta = document.getElementById('metaThemeColor');

    document.documentElement.classList.toggle('dark', isDark);

    if (icon) {
        icon.classList.toggle('fa-moon', isDark);
        icon.classList.toggle('fa-sun', !isDark);
    }

    if (meta) {
        meta.setAttribute('content', isDark ? '#080b11' : '#f1f5f9');
    }

    const path = window.location.pathname;

    const menuRoutes = [
        {
            routes: ['/admin/users', '/admin/users_list', '/admin/users_subscriptions', '/admin/users_delete'],
            menu: ['usersSubMenu', 'usersChevron']
        },
        {
            routes: ['/admin/daraja', '/admin/c2b'],
            menu: ['c2bSubMenu', 'c2bChevron']
        },
        {
            routes: ['/admin/b2c_settings', '/admin/b2c'],
            menu: ['b2cSubMenu', 'b2cChevron']
        }
    ];

    menuRoutes.forEach(function (item) {
        if (item.routes.some(function (route) {
            return path === route || path.startsWith(route + '/');
        })) {
            expandMenu(item.menu[0], item.menu[1]);
        }
    });

    document.querySelectorAll('#mobileSidebar a').forEach(function (link) {
        link.addEventListener('click', function () {
            const sidebar = document.getElementById('mobileSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');

            if (!sidebar || !backdrop) return;

            sidebar.classList.add('-translate-x-full');
            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0');
            document.body.classList.remove('sidebar-open');

            window.setTimeout(function () {
                backdrop.classList.add('hidden');
            }, 250);
        });
    });
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        const sidebar = document.getElementById('mobileSidebar');

        if (sidebar && !sidebar.classList.contains('-translate-x-full')) {
            toggleMobileSidebar();
        }
    }
});

window.addEventListener('resize', function () {
    if (window.innerWidth >= 768) {
        const sidebar = document.getElementById('mobileSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');

        if (sidebar) {
            sidebar.classList.add('-translate-x-full');
        }

        if (backdrop) {
            backdrop.classList.add('hidden');
            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0');
        }

        document.body.classList.remove('sidebar-open');
    }
});
</script>