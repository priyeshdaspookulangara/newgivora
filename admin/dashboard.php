<?php
/**
 * Administrative Control Panel: Dashboard
 * Platform-wide statistics: total members, total sales volume, pending payouts, active packages, financial summaries.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getDBConnection();

// Metrics: Total Members
$totalMembers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();

// Metrics: Total Sales Volume
$totalSales = (float)$pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM user_purchases WHERE payment_status = 'completed'")->fetchColumn();

// Metrics: Total Orders
$totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM user_purchases WHERE payment_status = 'completed'")->fetchColumn();

// Metrics: Pending Payout Requests
$pendingPayoutsCount = (int)$pdo->query("SELECT COUNT(*) FROM payouts WHERE status = 'pending'")->fetchColumn();
$pendingPayoutsAmount = (float)$pdo->query("SELECT COALESCE(SUM(requested_amount), 0) FROM payouts WHERE status = 'pending'")->fetchColumn();

// Metrics: Total Commissions Disbursed
$totalCommissions = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'commission'")->fetchColumn();

// Metrics: Active Packages
$activePackagesCount = (int)$pdo->query("SELECT COUNT(*) FROM packages WHERE is_active = 1")->fetchColumn();

// Metrics: Total Withdrawn
$totalWithdrawn = (float)$pdo->query("SELECT COALESCE(SUM(requested_amount), 0) FROM payouts WHERE status = 'approved'")->fetchColumn();

// Recent Purchases
$recentPurchases = $pdo->query("
    SELECT up.*, u.username, u.full_name, p.package_name, p.type as package_type
    FROM user_purchases up
    JOIN users u ON up.user_id = u.id
    JOIN packages p ON up.package_id = p.id
    ORDER BY up.purchased_at DESC
    LIMIT 6
")->fetchAll();

// Recent Pending Payouts
$pendingPayouts = $pdo->query("
    SELECT p.*, u.username, u.full_name, u.email, w.balance as user_wallet_balance
    FROM payouts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN wallets w ON u.id = w.user_id
    WHERE p.status = 'pending'
    ORDER BY p.requested_at ASC
    LIMIT 5
")->fetchAll();

$pageTitle = 'Executive Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="w-full space-y-8">

    <!-- Admin Hero Banner -->
    <div class="glass-panel-gold rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 border-l-4 border-l-amber-400">
        <div>
            <div class="text-xs uppercase tracking-wider text-amber-400 font-bold mb-1">Administrative Operations Command</div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white">Platform Health & Global Intelligence</h1>
            <p class="text-sm text-slate-300 mt-1">
                Real-time multi-level affiliate monitoring, financial audits, and automated ledger operations.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="/admin/users.php" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-xs font-semibold text-white transition">
                <i class="fa-solid fa-user-plus mr-1 text-cyan-400"></i> Manage Users
            </a>
            <a href="/admin/payouts.php" class="px-4 py-2 rounded-xl font-bold text-slate-950 glow-gold-btn text-xs transition">
                <i class="fa-solid fa-hand-holding-dollar mr-1"></i> Review Payouts (<?= $pendingPayoutsCount ?>)
            </a>
        </div>
    </div>

    <!-- Platform KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Members -->
        <div class="glass-panel rounded-2xl p-6">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase text-slate-400">Total Members</span>
                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 flex items-center justify-center">
                    <i class="fa-solid fa-users text-lg"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-white tracking-tight"><?= number_format($totalMembers) ?></div>
                <div class="mt-2 text-xs text-cyan-400">
                    <a href="/admin/users.php" class="hover:underline">View All Affiliates &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Sales Volume -->
        <div class="glass-panel-gold rounded-2xl p-6">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase text-slate-400">Total Sales Volume</span>
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-400 flex items-center justify-center">
                    <i class="fa-solid fa-chart-line text-lg"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-amber-300 tracking-tight font-mono"><?= APP_CURRENCY ?><?= number_format($totalSales, 2) ?></div>
                <div class="mt-2 text-xs text-slate-400">
                    Across <span class="text-white font-semibold"><?= $totalOrders ?></span> completed orders
                </div>
            </div>
        </div>

        <!-- Commissions Distributed -->
        <div class="glass-panel rounded-2xl p-6">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase text-slate-400">Commissions Disbursed</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center">
                    <i class="fa-solid fa-coins text-lg"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-emerald-400 tracking-tight font-mono"><?= APP_CURRENCY ?><?= number_format($totalCommissions, 2) ?></div>
                <div class="mt-2 text-xs text-slate-400">
                    Settled: <span class="text-slate-300"><?= APP_CURRENCY ?><?= number_format($totalWithdrawn, 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Pending Payouts -->
        <div class="glass-panel rounded-2xl p-6 <?= $pendingPayoutsCount > 0 ? 'border-amber-500/50' : '' ?>">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase text-slate-400">Pending Payouts</span>
                <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 flex items-center justify-center">
                    <i class="fa-solid fa-clock-rotate-left text-lg"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-white tracking-tight"><?= $pendingPayoutsCount ?> <span class="text-xs font-normal text-slate-400">(<?= APP_CURRENCY ?><?= number_format($pendingPayoutsAmount, 2) ?>)</span></div>
                <div class="mt-2 text-xs text-rose-400">
                    <a href="/admin/payouts.php" class="hover:underline">Process Withdrawals &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Tables Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- Recent Platform Orders -->
        <div class="glass-panel rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-bag-shopping text-cyan-400"></i> Recent Platform Purchases
                </h3>
                <span class="text-xs text-slate-500">Live feed</span>
            </div>

            <?php if (empty($recentPurchases)): ?>
                <p class="text-sm text-slate-400 py-6 text-center">No purchases recorded yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 border-b border-slate-800 font-semibold uppercase">
                            <tr>
                                <th class="py-2.5 px-3">Order ID</th>
                                <th class="py-2.5 px-3">Buyer</th>
                                <th class="py-2.5 px-3">Package</th>
                                <th class="py-2.5 px-3 text-right">Amount</th>
                                <th class="py-2.5 px-3">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-slate-300">
                            <?php foreach ($recentPurchases as $rp): ?>
                                <tr class="hover:bg-slate-800/30">
                                    <td class="py-3 px-3 font-mono text-cyan-400">#<?= $rp['id'] ?></td>
                                    <td class="py-3 px-3 font-medium text-white">
                                        <?= e($rp['full_name']) ?> <span class="text-slate-500">(@<?= e($rp['username']) ?>)</span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $rp['package_type'] === 'protein_powder' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-800 text-slate-300' ?>">
                                            <?= e($rp['package_name']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono font-bold text-emerald-400">
                                        <?= APP_CURRENCY ?><?= number_format((float)$rp['amount_paid'], 2) ?>
                                    </td>
                                    <td class="py-3 px-3 text-slate-500"><?= date('d M, H:i', strtotime($rp['purchased_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pending Payout Queue Quick Action -->
        <div class="glass-panel rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-400"></i> Payout Approval Queue
                </h3>
                <a href="/admin/payouts.php" class="text-xs font-semibold text-amber-400 hover:underline">Manage All &rarr;</a>
            </div>

            <?php if (empty($pendingPayouts)): ?>
                <div class="py-8 text-center text-slate-500 text-sm">
                    <i class="fa-solid fa-check-circle text-2xl text-emerald-500/60 mb-2"></i>
                    <p>All payout requests have been processed.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 border-b border-slate-800 font-semibold uppercase">
                            <tr>
                                <th class="py-2.5 px-3">Member</th>
                                <th class="py-2.5 px-3">Wallet</th>
                                <th class="py-2.5 px-3">Requested</th>
                                <th class="py-2.5 px-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-slate-300">
                            <?php foreach ($pendingPayouts as $pp): ?>
                                <tr class="hover:bg-slate-800/30">
                                    <td class="py-3 px-3 font-medium text-white">
                                        <?= e($pp['full_name']) ?> <span class="text-slate-500 font-mono">(@<?= e($pp['username']) ?>)</span>
                                    </td>
                                    <td class="py-3 px-3 font-mono text-cyan-400">
                                        <?= APP_CURRENCY ?><?= number_format((float)($pp['user_wallet_balance'] ?? 0), 2) ?>
                                    </td>
                                    <td class="py-3 px-3 font-mono font-bold text-amber-400">
                                        <?= APP_CURRENCY ?><?= number_format((float)$pp['requested_amount'], 2) ?>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <a href="/admin/payouts.php" class="px-2.5 py-1 bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 rounded font-semibold text-[11px] transition">
                                            Review
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
