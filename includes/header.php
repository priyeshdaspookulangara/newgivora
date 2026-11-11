<?php
/**
 * Shared Header Layout Template with Modern Collapsible Sidebar (<aside>)
 * Supports Admin & Member sidebar-based dashboards with collapsible navigation items.
 */
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser();
$pageTitle = $pageTitle ?? APP_NAME;
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

// Fetch member wallet if member
$userWallet = null;
if ($currentUser && $currentUser['role'] === 'member') {
    $userWallet = getOrCreateWallet((int)$currentUser['id']);
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: {
                            850: '#111827',
                            900: '#0c1220',
                            950: '#070b14',
                        },
                        accent: {
                            cyan: '#06b6d4',
                            gold: '#f59e0b',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/custom.css">
    <style>
        /* Custom scrollbar for sidebar */
        .sidebar-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.2);
            border-radius: 4px;
        }
        .sidebar-collapsed {
            width: 5rem !important;
        }
        .sidebar-collapsed .nav-text,
        .sidebar-collapsed .nav-badge,
        .sidebar-collapsed .chevron-icon,
        .sidebar-collapsed .sidebar-header-text,
        .sidebar-collapsed .user-info-text {
            display: none !important;
        }
        .sidebar-collapsed .sub-nav-container {
            display: none !important;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans antialiased selection:bg-cyan-500 selection:text-white flex flex-col">

<?php if ($currentUser): ?>
<!-- Authenticated App Shell with Sidebar -->
<div class="flex flex-1 min-h-screen relative overflow-x-hidden">

    <!-- Collapsible Sidebar (<aside>) -->
    <aside id="mainSidebar" class="w-64 bg-slate-900/95 backdrop-blur-xl border-r border-slate-800 flex flex-col fixed inset-y-0 left-0 z-40 transition-all duration-300 transform md:translate-x-0 -translate-x-full">

        <!-- Sidebar Brand / Header -->
        <div class="h-16 flex items-center justify-between px-4 border-b border-slate-800/80 bg-slate-950/40">
            <a href="/" class="flex items-center space-x-3 overflow-hidden">
                <div class="w-10 h-10 min-w-[2.5rem] rounded-xl bg-gradient-to-tr from-cyan-600 via-cyan-400 to-amber-400 p-[2px] transition group-hover:scale-105 shadow-lg shadow-cyan-950/50">
                    <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center">
                        <i class="fa-solid fa-bolt-lightning text-amber-400 text-lg"></i>
                    </div>
                </div>
                <div class="sidebar-header-text truncate">
                    <div class="text-base font-extrabold tracking-tight bg-gradient-to-r from-cyan-400 via-teal-300 to-amber-400 bg-clip-text text-transparent truncate">
                        <?= APP_NAME ?>
                    </div>
                    <span class="text-[10px] uppercase font-bold tracking-widest <?= $currentUser['role'] === 'admin' ? 'text-amber-400' : 'text-cyan-400' ?>">
                        <?= $currentUser['role'] === 'admin' ? 'Admin Suite' : 'Member Portal' ?>
                    </span>
                </div>
            </a>

            <!-- Mobile Close Button -->
            <button id="mobileCloseSidebar" class="md:hidden text-slate-400 hover:text-white p-1 rounded-lg">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Sidebar Navigation Items (Scrollable) -->
        <div class="flex-1 overflow-y-auto sidebar-scroll py-4 px-3 space-y-6">

            <?php if ($currentUser['role'] === 'admin'): ?>
                <!-- ADMIN NAVIGATION -->

                <!-- Section: Overview -->
                <div>
                    <div class="sidebar-header-text px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        Overview
                    </div>
                    <ul class="space-y-1">
                        <li>
                            <a href="/admin/dashboard.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $currentUri === '/admin/dashboard.php' ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>" title="Executive Dashboard">
                                <i class="fa-solid fa-chart-line w-5 text-center text-amber-400"></i>
                                <span class="nav-text">Dashboard</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Section: Management (Collapsible Group) -->
                <div>
                    <button type="button" class="group-toggle w-full flex items-center justify-between px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 hover:text-slate-200 transition" data-target="adminManagementGroup">
                        <span class="sidebar-header-text flex items-center">
                            <i class="fa-solid fa-folder-gear mr-2 text-cyan-400"></i> Platform Ops
                        </span>
                        <i class="fa-solid fa-chevron-down chevron-icon text-[10px] transition-transform duration-200"></i>
                    </button>
                    <div id="adminManagementGroup" class="sub-nav-container space-y-1 pl-1">
                        <a href="/admin/users.php" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $currentUri === '/admin/users.php' ? 'bg-cyan-500/15 text-cyan-300 border border-cyan-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>" title="Affiliates & Genealogy">
                            <i class="fa-solid fa-users w-5 text-center text-cyan-400"></i>
                            <span class="nav-text">Members & Tree</span>
                        </a>
                        <a href="/admin/packages.php" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $currentUri === '/admin/packages.php' ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>" title="Package Management">
                            <i class="fa-solid fa-cubes w-5 text-center text-amber-400"></i>
                            <span class="nav-text">Packages</span>
                        </a>
                        <a href="/admin/pins.php" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $currentUri === '/admin/pins.php' ? 'bg-cyan-500/15 text-cyan-300 border border-cyan-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>" title="E-PIN Minting">
                            <i class="fa-solid fa-key w-5 text-center text-cyan-400"></i>
                            <span class="nav-text">E-PIN Minting</span>
                            <span class="nav-badge ml-auto px-1.5 py-0.5 rounded text-[10px] bg-cyan-500/20 text-cyan-300 font-bold border border-cyan-500/30">PIN</span>
                        </a>
                    </div>
                </div>

                <!-- Section: Financials (Collapsible Group) -->
                <div>
                    <button type="button" class="group-toggle w-full flex items-center justify-between px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 hover:text-slate-200 transition" data-target="adminFinanceGroup">
                        <span class="sidebar-header-text flex items-center">
                            <i class="fa-solid fa-vault mr-2 text-emerald-400"></i> Financials
                        </span>
                        <i class="fa-solid fa-chevron-down chevron-icon text-[10px] transition-transform duration-200"></i>
                    </button>
                    <div id="adminFinanceGroup" class="sub-nav-container space-y-1 pl-1">
                        <a href="/admin/payouts.php" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $currentUri === '/admin/payouts.php' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>" title="Payout Queue">
                            <i class="fa-solid fa-hand-holding-dollar w-5 text-center text-emerald-400"></i>
                            <span class="nav-text">Payout Requests</span>
                        </a>
                        <a href="/admin/reports.php" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $currentUri === '/admin/reports.php' ? 'bg-purple-500/15 text-purple-300 border border-purple-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>" title="Global Ledger">
                            <i class="fa-solid fa-receipt w-5 text-center text-purple-400"></i>
                            <span class="nav-text">Ledger & Reports</span>
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- MEMBER / CUSTOMER NAVIGATION -->

                <!-- Section: Member Center -->
                <div>
                    <div class="sidebar-header-text px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        Main Menu
                    </div>
                    <ul class="space-y-1">
                        <li>
                            <a href="/member/dashboard.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $currentUri === '/member/dashboard.php' ? 'bg-cyan-500/15 text-cyan-300 border border-cyan-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>" title="My Dashboard">
                                <i class="fa-solid fa-gauge-high w-5 text-center text-cyan-400"></i>
                                <span class="nav-text">Dashboard</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Section: Commerce & Plans (Collapsible Group) -->
                <div>
                    <button type="button" class="group-toggle w-full flex items-center justify-between px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 hover:text-slate-200 transition" data-target="memberCommerceGroup">
                        <span class="sidebar-header-text flex items-center">
                            <i class="fa-solid fa-store mr-2 text-amber-400"></i> E-Commerce
                        </span>
                        <i class="fa-solid fa-chevron-down chevron-icon text-[10px] transition-transform duration-200"></i>
                    </button>
                    <div id="memberCommerceGroup" class="sub-nav-container space-y-1 pl-1">
                        <a href="/member/store.php" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $currentUri === '/member/store.php' ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>" title="Protein Powder Plan">
                            <i class="fa-solid fa-dumbbell w-5 text-center text-amber-400"></i>
                            <span class="nav-text">Protein Store (₹5K)</span>
                            <span class="nav-badge ml-auto px-1.5 py-0.5 rounded text-[10px] bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30">7-Lvl</span>
                        </a>
                        <a href="/member/pins.php" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $currentUri === '/member/pins.php' ? 'bg-cyan-500/15 text-cyan-300 border border-cyan-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>" title="Generate E-PINs">
                            <i class="fa-solid fa-key w-5 text-center text-cyan-400"></i>
                            <span class="nav-text">My E-PINs</span>
                        </a>
                    </div>
                </div>

                <!-- Section: Wallet & Downline (Collapsible Group) -->
                <div>
                    <button type="button" class="group-toggle w-full flex items-center justify-between px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 hover:text-slate-200 transition" data-target="memberNetworkGroup">
                        <span class="sidebar-header-text flex items-center">
                            <i class="fa-solid fa-sitemap mr-2 text-purple-400"></i> Network & Funds
                        </span>
                        <i class="fa-solid fa-chevron-down chevron-icon text-[10px] transition-transform duration-200"></i>
                    </button>
                    <div id="memberNetworkGroup" class="sub-nav-container space-y-1 pl-1">
                        <a href="/member/wallet.php" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $currentUri === '/member/wallet.php' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>" title="Digital Wallet">
                            <i class="fa-solid fa-wallet w-5 text-center text-emerald-400"></i>
                            <span class="nav-text">Wallet & Payouts</span>
                            <?php if ($userWallet): ?>
                                <span class="nav-badge ml-auto px-1.5 py-0.5 rounded text-[10px] bg-emerald-500/20 text-emerald-300 font-bold font-mono">
                                    <?= APP_CURRENCY ?><?= number_format((float)$userWallet['balance'], 0) ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <a href="/member/network.php" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $currentUri === '/member/network.php' ? 'bg-purple-500/15 text-purple-300 border border-purple-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>" title="Genealogy Downline">
                            <i class="fa-solid fa-network-wired w-5 text-center text-purple-400"></i>
                            <span class="nav-text">Downline Tree</span>
                        </a>
                        <a href="/member/profile.php" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-sm font-medium transition <?= $currentUri === '/member/profile.php' ? 'bg-cyan-500/15 text-cyan-300 border border-cyan-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>" title="Profile Settings">
                            <i class="fa-solid fa-user-shield w-5 text-center text-cyan-400"></i>
                            <span class="nav-text">My Profile</span>
                        </a>
                    </div>
                </div>

            <?php endif; ?>

        </div>

        <!-- Sidebar Footer / User Info -->
        <div class="p-3 border-t border-slate-800/80 bg-slate-950/60">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3 overflow-hidden">
                    <div class="w-9 h-9 min-w-[2.25rem] rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-cyan-400 font-bold text-sm">
                        <?= strtoupper(substr($currentUser['username'], 0, 1)) ?>
                    </div>
                    <div class="user-info-text truncate">
                        <div class="text-xs font-semibold text-white truncate"><?= e($currentUser['full_name'] ?: $currentUser['username']) ?></div>
                        <div class="text-[11px] text-slate-400 truncate font-mono">@<?= e($currentUser['username']) ?></div>
                    </div>
                </div>
                <a href="/logout.php" title="Sign Out" class="p-2 text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 rounded-lg transition">
                    <i class="fa-solid fa-power-off text-sm"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Wrapper (Shifted right for fixed aside on desktop) -->
    <div id="mainContentArea" class="flex-1 flex flex-col md:ml-64 min-w-0 transition-all duration-300">

        <!-- Top Sticky Header -->
        <header class="h-16 bg-slate-900/80 backdrop-blur-md border-b border-slate-800/80 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6">
            <div class="flex items-center space-x-3">
                <!-- Mobile Sidebar Toggle -->
                <button id="mobileOpenSidebar" class="md:hidden p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition">
                    <i class="fa-solid fa-bars text-base"></i>
                </button>

                <!-- Desktop Sidebar Mini Toggle -->
                <button id="desktopCollapseSidebar" class="hidden md:flex p-2 rounded-xl bg-slate-800/60 hover:bg-slate-800 text-slate-400 hover:text-cyan-400 border border-slate-700/60 transition" title="Toggle Sidebar">
                    <i class="fa-solid fa-bars-staggered text-sm"></i>
                </button>

                <h1 class="text-base sm:text-lg font-bold text-white tracking-tight flex items-center">
                    <?= e($pageTitle) ?>
                </h1>
            </div>

            <!-- Header Right Controls -->
            <div class="flex items-center space-x-3 sm:space-x-4">
                <?php if ($userWallet): ?>
                    <a href="/member/wallet.php" class="hidden sm:flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 hover:bg-emerald-500/20 transition">
                        <i class="fa-solid fa-wallet text-emerald-400 text-xs"></i>
                        <span class="text-xs font-mono font-bold"><?= APP_CURRENCY ?><?= number_format((float)$userWallet['balance'], 2) ?></span>
                    </a>
                <?php endif; ?>

                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="/admin/pins.php" class="hidden sm:inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30 hover:bg-amber-500/25 transition">
                        <i class="fa-solid fa-plus-circle text-amber-400"></i>
                        <span>Mint PIN</span>
                    </a>
                <?php else: ?>
                    <a href="/member/store.php" class="hidden sm:inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-bold glow-gold-btn transition shadow">
                        <i class="fa-solid fa-cart-shopping text-xs"></i>
                        <span>Buy Plan</span>
                    </a>
                <?php endif; ?>

                <div class="h-6 w-px bg-slate-800"></div>

                <!-- User Profile Quick Badge -->
                <div class="flex items-center space-x-2">
                    <span class="text-xs text-slate-400 hidden lg:inline">
                        <?= $currentUser['role'] === 'admin' ? '<span class="text-amber-400 font-bold">Admin</span>' : '<span class="text-cyan-400 font-bold">Affiliate</span>' ?>
                    </span>
                    <a href="/logout.php" class="px-2.5 py-1 text-xs font-semibold text-rose-400 hover:text-rose-300 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 rounded-lg transition flex items-center space-x-1">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span class="hidden sm:inline">Logout</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Body Page Content -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <!-- Flash Messages -->
            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="mb-6 p-4 rounded-xl bg-rose-950/70 border border-rose-500/40 text-rose-200 flex items-center justify-between shadow-lg">
                    <div class="flex items-center space-x-3">
                        <i class="fa-solid fa-triangle-exclamation text-rose-400 text-lg"></i>
                        <span class="text-sm font-medium"><?= e($_SESSION['flash_error']) ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-rose-400 hover:text-rose-200"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="mb-6 p-4 rounded-xl bg-emerald-950/70 border border-emerald-500/40 text-emerald-200 flex items-center justify-between shadow-lg">
                    <div class="flex items-center space-x-3">
                        <i class="fa-solid fa-circle-check text-emerald-400 text-lg"></i>
                        <span class="text-sm font-medium"><?= e($_SESSION['flash_success']) ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

<?php else: ?>
<!-- Guest Shell (e.g. Login, Register, Landing) -->
<header class="border-b border-cyan-900/30 bg-slate-900/80 backdrop-blur-md sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="/" class="flex items-center space-x-2 group">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-cyan-600 via-cyan-400 to-amber-400 p-[2px] transition duration-300 group-hover:scale-105">
                <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center">
                    <i class="fa-solid fa-bolt-lightning text-amber-400 text-lg"></i>
                </div>
            </div>
            <span class="text-xl font-bold tracking-tight bg-gradient-to-r from-cyan-400 via-teal-300 to-amber-400 bg-clip-text text-transparent">
                <?= APP_NAME ?>
            </span>
        </a>

        <nav class="flex items-center space-x-3">
            <a href="/login.php" class="px-4 py-2 text-sm font-medium text-slate-200 hover:text-cyan-400 transition">Login</a>
            <a href="/register.php" class="px-4 py-2 text-sm font-semibold text-slate-950 bg-gradient-to-r from-cyan-400 to-amber-400 rounded-lg shadow hover:opacity-90 transition">Join Platform</a>
        </nav>
    </div>
</header>
<main class="flex-grow">
<?php endif; ?>
