<?php
/**
 * Shared Header Layout Template
 */
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser();
$pageTitle = $pageTitle ?? APP_NAME;
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
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col font-sans antialiased selection:bg-cyan-500 selection:text-white">

<!-- Top Navigation Bar -->
<header class="border-b border-cyan-900/30 bg-slate-900/80 backdrop-blur-md sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center space-x-3">
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
            <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                <span class="ml-2 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">ADMIN PANEL</span>
            <?php elseif ($currentUser): ?>
                <span class="ml-2 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">MEMBER HUB</span>
            <?php endif; ?>
        </div>

        <nav class="flex items-center space-x-1 sm:space-x-3">
            <?php if ($currentUser): ?>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="/admin/dashboard.php" class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-amber-400 rounded-lg hover:bg-slate-800/60 transition"><i class="fa-solid fa-chart-line mr-1 text-amber-400"></i> Dashboard</a>
                    <a href="/admin/users.php" class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-cyan-400 rounded-lg hover:bg-slate-800/60 transition"><i class="fa-solid fa-users mr-1 text-cyan-400"></i> Members</a>
                    <a href="/admin/packages.php" class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-amber-400 rounded-lg hover:bg-slate-800/60 transition"><i class="fa-solid fa-cubes mr-1 text-amber-400"></i> Packages</a>
                    <a href="/admin/pins.php" class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-amber-400 rounded-lg hover:bg-slate-800/60 transition"><i class="fa-solid fa-key mr-1 text-amber-400"></i> E-PINs</a>
                    <a href="/admin/payouts.php" class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-emerald-400 rounded-lg hover:bg-slate-800/60 transition"><i class="fa-solid fa-money-bill-transfer mr-1 text-emerald-400"></i> Payouts</a>
                    <a href="/admin/reports.php" class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-purple-400 rounded-lg hover:bg-slate-800/60 transition"><i class="fa-solid fa-receipt mr-1 text-purple-400"></i> Reports</a>
                <?php else: ?>
                    <a href="/member/dashboard.php" class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-cyan-400 rounded-lg hover:bg-slate-800/60 transition"><i class="fa-solid fa-gauge mr-1 text-cyan-400"></i> Dashboard</a>
                    <a href="/member/store.php" class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-amber-400 rounded-lg hover:bg-slate-800/60 transition"><i class="fa-solid fa-store mr-1 text-amber-400"></i> Protein Store</a>
                    <a href="/member/pins.php" class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-cyan-400 rounded-lg hover:bg-slate-800/60 transition"><i class="fa-solid fa-key mr-1 text-cyan-400"></i> E-PINs</a>
                    <a href="/member/wallet.php" class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-emerald-400 rounded-lg hover:bg-slate-800/60 transition"><i class="fa-solid fa-wallet mr-1 text-emerald-400"></i> Wallet</a>
                    <a href="/member/network.php" class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-purple-400 rounded-lg hover:bg-slate-800/60 transition"><i class="fa-solid fa-sitemap mr-1 text-purple-400"></i> Downline</a>
                    <a href="/member/profile.php" class="px-3 py-2 text-sm font-medium text-slate-300 hover:text-cyan-400 rounded-lg hover:bg-slate-800/60 transition"><i class="fa-solid fa-id-badge mr-1 text-cyan-400"></i> Profile</a>
                <?php endif; ?>

                <div class="h-5 w-px bg-slate-800 mx-2"></div>

                <div class="flex items-center space-x-2">
                    <span class="text-xs text-slate-400 hidden md:inline">Logged as <span class="text-cyan-300 font-semibold"><?= e($currentUser['username']) ?></span></span>
                    <a href="/logout.php" class="px-3 py-1.5 text-xs font-semibold text-rose-400 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 rounded-lg transition flex items-center">
                        <i class="fa-solid fa-arrow-right-from-bracket mr-1.5"></i> Logout
                    </a>
                </div>
            <?php else: ?>
                <a href="/login.php" class="px-4 py-2 text-sm font-medium text-slate-200 hover:text-cyan-400 transition">Login</a>
                <a href="/register.php" class="px-4 py-2 text-sm font-semibold text-slate-900 bg-gradient-to-r from-cyan-400 to-amber-400 rounded-lg shadow hover:opacity-90 transition">Join Platform</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="flex-grow">
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="p-4 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-200 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <i class="fa-solid fa-triangle-exclamation text-rose-400 text-lg"></i>
                    <span><?= e($_SESSION['flash_error']) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-rose-400 hover:text-rose-200"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-500/40 text-emerald-200 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <i class="fa-solid fa-circle-check text-emerald-400 text-lg"></i>
                    <span><?= e($_SESSION['flash_success']) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
