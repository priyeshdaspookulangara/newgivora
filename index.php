<?php
/**
 * Root Landing Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isAuthenticated()) {
    $role = $_SESSION['user_role'] ?? 'member';
    if ($role === 'admin') {
        header('Location: /admin/dashboard.php');
    } else {
        header('Location: /member/dashboard.php');
    }
    exit;
}

$pageTitle = 'Next-Gen MLM & E-Commerce Platform';
require_once __DIR__ . '/includes/header.php';
?>

<div class="relative overflow-hidden py-16 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 text-xs font-semibold mb-6">
            <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
            Direct Selling + 7-Level Protein Powder Plan
        </div>

        <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-white max-w-4xl mx-auto leading-tight">
            Next-Generation <span class="bg-gradient-to-r from-cyan-400 via-teal-300 to-amber-400 bg-clip-text text-transparent">MLM Platform</span> & Digital Commerce
        </h1>

        <p class="mt-6 text-lg text-slate-300 max-w-2xl mx-auto">
            High-yield automated compensation engine, instant digital wallet disbursements, and an integrated nutrition wellness catalog.
        </p>

        <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="/login.php" class="w-full sm:w-auto px-8 py-3.5 rounded-xl font-bold text-slate-950 glow-cyan-btn transition shadow-xl text-center">
                Access Platform <i class="fa-solid fa-arrow-right ml-1"></i>
            </a>
            <a href="/register.php" class="w-full sm:w-auto px-8 py-3.5 rounded-xl font-bold text-white bg-slate-900 hover:bg-slate-800 border border-slate-700 hover:border-slate-600 transition text-center">
                Create Member Account
            </a>
        </div>

        <!-- Featured Product Teaser Card -->
        <div class="mt-16 max-w-3xl mx-auto glass-panel-gold rounded-3xl p-8 relative overflow-hidden text-left border border-amber-500/30">
            <div class="flex flex-col md:flex-row items-center gap-8">
                <div class="w-32 h-32 md:w-40 md:h-40 rounded-2xl bg-gradient-to-tr from-amber-500/20 to-cyan-500/20 border border-amber-500/30 flex items-center justify-center text-amber-400 text-6xl shadow-inner flex-shrink-0">
                    <i class="fa-solid fa-bottle-water"></i>
                </div>
                <div class="flex-grow">
                    <div class="inline-block px-2.5 py-0.5 rounded-md text-[11px] font-bold uppercase bg-amber-400/10 text-amber-300 border border-amber-400/30 mb-2">
                        Flagship Wellness Plan
                    </div>
                    <h3 class="text-2xl font-bold text-white">Pure Whey Isolate Protein Powder</h3>
                    <p class="text-sm text-slate-300 mt-2">
                        Priced at <span class="text-amber-400 font-bold text-lg">₹5,000</span> with exclusive 7-Level automated commission dispersal:
                        L1 ₹1,000 • L2 ₹200 • L3 ₹200 • L4 ₹150 • L5 ₹150 • L6 ₹150 • L7 ₹100.
                    </p>
                    <div class="mt-4 flex items-center gap-3">
                        <a href="/login.php" class="text-xs font-bold text-amber-400 hover:text-amber-300 flex items-center gap-1">
                            Login to Purchase & Earn <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
