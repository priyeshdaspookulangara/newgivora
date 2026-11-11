<?php
/**
 * Member Downline Network Tree / Referral Visualizer
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mlm_engine.php';

requireMember();

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$pdo = getDBConnection();

// Fetch direct referrals and downlines up to 7 levels
$downlineTree = MLMEngine::getDownlineTree($pdo, $userId, 7);

$totalNetworkCount = 0;
foreach ($downlineTree as $lvl => $users) {
    $totalNetworkCount += count($users);
}

$pageTitle = 'My Referral Network & Downline Tree';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="w-full space-y-8">

    <div class="glass-panel rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 border-l-4 border-l-purple-400">
        <div>
            <span class="text-xs uppercase tracking-wider text-purple-400 font-semibold mb-1">Genealogy & Tree Placement</span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white">Multi-Level Downline Network</h1>
            <p class="text-sm text-slate-400 mt-1">
                Explore your organization across 7 deep affiliate commission tiers.
            </p>
        </div>

        <div class="glass-panel rounded-xl px-5 py-3 border border-purple-500/30">
            <span class="text-[11px] uppercase tracking-wider text-purple-400 font-bold block">Total Downline Members</span>
            <span class="text-2xl font-black text-white font-mono"><?= $totalNetworkCount ?></span>
        </div>
    </div>

    <?php if (empty($downlineTree)): ?>
        <div class="glass-panel rounded-2xl p-12 text-center text-slate-400">
            <i class="fa-solid fa-sitemap text-4xl text-slate-600 mb-3"></i>
            <h3 class="text-lg font-bold text-white">No Downline Team Members Found</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                Share your referral link with new prospects to grow your direct sales downline and unlock 7-level multi-tier rewards.
            </p>
            <a href="/member/dashboard.php" class="inline-block mt-4 px-4 py-2 bg-cyan-500/20 border border-cyan-500/40 text-cyan-300 text-xs font-semibold rounded-lg hover:bg-cyan-500/30 transition">
                Get Referral Link
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($downlineTree as $level => $members): ?>
                <div class="glass-panel rounded-2xl p-6 border-t-2 <?= $level === 1 ? 'border-amber-400' : 'border-cyan-500/40' ?>">
                    <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded text-xs font-bold uppercase <?= $level === 1 ? 'bg-amber-400/20 text-amber-300 border border-amber-400/40' : 'bg-cyan-400/10 text-cyan-300 border border-cyan-500/20' ?>">
                                Tier Level <?= $level ?>
                            </span>
                            <span class="text-xs text-slate-400">
                                <?= $level === 1 ? '(Direct Referrals - 10% / ₹1,000)' : '(' . count($members) . ' team members)' ?>
                            </span>
                        </div>
                        <span class="text-xs font-mono text-slate-400"><?= count($members) ?> member(s)</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-slate-400 border-b border-slate-800 font-semibold uppercase">
                                <tr>
                                    <th class="py-2 px-3">User ID</th>
                                    <th class="py-2 px-3">Full Name</th>
                                    <th class="py-2 px-3">Username</th>
                                    <th class="py-2 px-3">Email / Mobile</th>
                                    <th class="py-2 px-3">Total Orders</th>
                                    <th class="py-2 px-3">Total Earned</th>
                                    <th class="py-2 px-3">Joined</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 text-slate-300">
                                <?php foreach ($members as $m): ?>
                                    <tr class="hover:bg-slate-800/30">
                                        <td class="py-2.5 px-3 font-mono text-cyan-400">#<?= $m['id'] ?></td>
                                        <td class="py-2.5 px-3 font-medium text-white"><?= e($m['full_name']) ?></td>
                                        <td class="py-2.5 px-3 text-slate-300 font-mono">@<?= e($m['username']) ?></td>
                                        <td class="py-2.5 px-3 text-slate-400">
                                            <div><?= e($m['email']) ?></div>
                                            <div class="text-[11px] text-slate-500"><?= e($m['mobile'] ?? '—') ?></div>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-200">
                                                <?= (int)$m['purchase_count'] ?> orders
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3 font-mono text-emerald-400">
                                            <?= APP_CURRENCY ?><?= number_format((float)($m['total_earned'] ?? 0), 2) ?>
                                        </td>
                                        <td class="py-2.5 px-3 text-slate-500"><?= date('d M Y', strtotime($m['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
