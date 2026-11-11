<?php
/**
 * Member Dashboard
 * Overview of active packages, wallet balance, total earnings, referral link, recent activity.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mlm_engine.php';

requireMember();

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$pdo = getDBConnection();

// Fetch Wallet
$wallet = getOrCreateWallet($userId);

// Fetch Active Packages / Purchases
$purchasesStmt = $pdo->prepare('
    SELECT up.*, p.package_name, p.type as package_type, p.price as pkg_price
    FROM user_purchases up
    JOIN packages p ON up.package_id = p.id
    WHERE up.user_id = :uid AND up.payment_status = "completed"
    ORDER BY up.purchased_at DESC
');
$purchasesStmt->execute(['uid' => $userId]);
$activePurchases = $purchasesStmt->fetchAll();

// Count direct referrals
$refStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE sponsor_id = :uid');
$refStmt->execute(['uid' => $userId]);
$directReferralsCount = (int)$refStmt->fetchColumn();

// Fetch Recent Commissions & Transactions
$recentTxStmt = $pdo->prepare('
    SELECT t.*, s.username as sender_username, s.full_name as sender_name
    FROM transactions t
    LEFT JOIN users s ON t.sender_id = s.id
    WHERE t.user_id = :uid
    ORDER BY t.created_at DESC
    LIMIT 6
');
$recentTxStmt->execute(['uid' => $userId]);
$recentTransactions = $recentTxStmt->fetchAll();

// Sponsor Info
$sponsorName = 'Direct Platform Root';
if (!empty($currentUser['sponsor_id'])) {
    $spStmt = $pdo->prepare('SELECT username, full_name FROM users WHERE id = :sid LIMIT 1');
    $spStmt->execute(['sid' => $currentUser['sponsor_id']]);
    $sponsor = $spStmt->fetch();
    if ($sponsor) {
        $sponsorName = $sponsor['full_name'] . ' (@' . $sponsor['username'] . ')';
    }
}

// Build referral URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$referralUrl = "{$protocol}://{$host}/register.php?ref=" . urlencode($currentUser['username']);

$pageTitle = 'Member Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="w-full space-y-8">

    <!-- Welcome Header & Referral Link Box -->
    <div class="glass-panel rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 border-l-4 border-l-cyan-400">
        <div>
            <div class="text-xs uppercase tracking-wider text-cyan-400 font-semibold mb-1">Affiliate Partner Portal</div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white">Welcome back, <?= e($currentUser['full_name']) ?>!</h1>
            <p class="text-sm text-slate-400 mt-1">
                Sponsor: <span class="text-amber-400 font-medium"><?= e($sponsorName) ?></span> • Member ID: <span class="text-slate-200 font-mono">#<?= $userId ?></span>
            </p>
        </div>

        <div class="bg-slate-900/90 p-4 rounded-xl border border-cyan-500/20 max-w-md w-full">
            <label class="block text-xs font-semibold text-slate-300 mb-1.5 flex items-center gap-1.5">
                <i class="fa-solid fa-share-nodes text-cyan-400"></i> Your Direct Referral Link:
            </label>
            <div class="flex items-center gap-2">
                <input type="text" id="refLinkInput" readonly value="<?= e($referralUrl) ?>"
                       class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-cyan-300 font-mono focus:outline-none">
                <button onclick="copyRefLink()" class="px-3 py-1.5 bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-400 text-xs font-semibold rounded-lg border border-cyan-500/40 transition whitespace-nowrap">
                    <i class="fa-regular fa-copy mr-1"></i> Copy
                </button>
            </div>
            <span id="copyNotice" class="text-[11px] text-emerald-400 hidden mt-1">Copied to clipboard!</span>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Balance Card -->
        <div class="glass-panel rounded-2xl p-6 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase text-slate-400">Current Balance</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center">
                    <i class="fa-solid fa-wallet text-lg"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-white tracking-tight"><?= APP_CURRENCY ?><?= number_format((float)$wallet['balance'], 2) ?></div>
                <div class="mt-2 flex items-center justify-between text-xs">
                    <a href="/member/wallet.php" class="text-emerald-400 hover:text-emerald-300 font-semibold flex items-center gap-1">
                        Request Payout &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Total Earnings -->
        <div class="glass-panel-gold rounded-2xl p-6 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase text-slate-400">Total Commissions</span>
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-400 flex items-center justify-center">
                    <i class="fa-solid fa-coins text-lg"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-amber-300 tracking-tight"><?= APP_CURRENCY ?><?= number_format((float)$wallet['total_earned'], 2) ?></div>
                <div class="mt-2 text-xs text-slate-400">
                    Withdrawn: <span class="text-slate-300"><?= APP_CURRENCY ?><?= number_format((float)$wallet['total_withdrawn'], 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Direct Downlines -->
        <div class="glass-panel rounded-2xl p-6 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase text-slate-400">Direct Referrals</span>
                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 flex items-center justify-center">
                    <i class="fa-solid fa-users-rays text-lg"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-white tracking-tight"><?= $directReferralsCount ?></div>
                <div class="mt-2 text-xs">
                    <a href="/member/network.php" class="text-cyan-400 hover:text-cyan-300 font-semibold">View Network Tree &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Active Packages -->
        <div class="glass-panel rounded-2xl p-6 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase text-slate-400">Active Plans</span>
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/30 text-purple-400 flex items-center justify-center">
                    <i class="fa-solid fa-cubes text-lg"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-white tracking-tight"><?= count($activePurchases) ?></div>
                <div class="mt-2 text-xs">
                    <a href="/member/store.php" class="text-purple-400 hover:text-purple-300 font-semibold">Browse Store &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Plans & Quick Store Banner -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- User Active Subscriptions -->
        <div class="lg:col-span-2 glass-panel rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-box-open text-cyan-400"></i> My Active Packages & Products
                </h3>
                <a href="/member/store.php" class="text-xs font-semibold text-cyan-400 hover:underline">Purchase New</a>
            </div>

            <?php if (empty($activePurchases)): ?>
                <div class="p-8 text-center text-slate-400 bg-slate-900/40 rounded-xl border border-slate-800/80">
                    <i class="fa-solid fa-cart-shopping text-3xl text-slate-600 mb-2"></i>
                    <p class="text-sm">You haven't purchased any packages yet.</p>
                    <p class="text-xs text-slate-500 mt-1">Activate the ₹5,000 Protein Powder plan to unlock maximum affiliate earnings!</p>
                    <a href="/member/store.php" class="inline-block mt-4 px-4 py-2 bg-cyan-500/20 border border-cyan-500/40 text-cyan-300 text-xs font-semibold rounded-lg hover:bg-cyan-500/30 transition">
                        Explore Store Catalog
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 border-b border-slate-800 font-semibold uppercase">
                            <tr>
                                <th class="py-2.5 px-3">Package / Plan</th>
                                <th class="py-2.5 px-3">Type</th>
                                <th class="py-2.5 px-3">Paid</th>
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-slate-300">
                            <?php foreach ($activePurchases as $ap): ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="py-3 px-3 font-semibold text-white"><?= e($ap['package_name']) ?></td>
                                    <td class="py-3 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $ap['package_type'] === 'protein_powder' ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-slate-800 text-slate-300' ?>">
                                            <?= e($ap['package_type']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 font-mono text-cyan-300"><?= APP_CURRENCY ?><?= number_format((float)$ap['amount_paid'], 2) ?></td>
                                    <td class="py-3 px-3">
                                        <span class="text-emerald-400 font-semibold"><i class="fa-solid fa-circle-check text-[10px] mr-1"></i> Active</span>
                                    </td>
                                    <td class="py-3 px-3 text-slate-400"><?= date('d M Y, H:i', strtotime($ap['purchased_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Featured Protein Plan Quick Card -->
        <div class="glass-panel-gold rounded-2xl p-6 flex flex-col justify-between">
            <div>
                <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30">
                    Flagship MLM Offer
                </span>
                <h4 class="text-xl font-bold text-white mt-2">Protein Powder Wellness Plan</h4>
                <p class="text-xs text-slate-300 mt-2 leading-relaxed">
                    Purchase the ₹5,000 package to trigger immediate 7-level fixed cash commissions for your entire upline network!
                </p>
                <div class="mt-4 p-3 bg-slate-900/80 rounded-xl border border-amber-500/20 text-xs space-y-1">
                    <div class="text-amber-400 font-semibold flex items-center justify-between">
                        <span>L1 Direct Sponsor:</span>
                        <span>₹1,000</span>
                    </div>
                    <div class="text-slate-400 flex items-center justify-between">
                        <span>L2 - L3:</span>
                        <span>₹200 each</span>
                    </div>
                    <div class="text-slate-400 flex items-center justify-between">
                        <span>L4 - L6:</span>
                        <span>₹150 each</span>
                    </div>
                    <div class="text-slate-400 flex items-center justify-between">
                        <span>L7:</span>
                        <span>₹100</span>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <a href="/member/store.php" class="w-full py-2.5 px-4 rounded-xl font-bold text-slate-950 glow-gold-btn text-xs text-center flex items-center justify-center gap-2">
                    <i class="fa-solid fa-cart-shopping"></i> Order Now for ₹5,000
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Transactions / Commission Activity -->
    <div class="glass-panel rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-receipt text-cyan-400"></i> Recent Ledger Activity
            </h3>
            <a href="/member/wallet.php" class="text-xs font-semibold text-cyan-400 hover:underline">Full Wallet Ledger &rarr;</a>
        </div>

        <?php if (empty($recentTransactions)): ?>
            <p class="text-sm text-slate-400 py-4 text-center">No transactions recorded yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-slate-400 border-b border-slate-800 font-semibold uppercase">
                        <tr>
                            <th class="py-2.5 px-3">Date</th>
                            <th class="py-2.5 px-3">Type</th>
                            <th class="py-2.5 px-3">Sender / Source</th>
                            <th class="py-2.5 px-3">Level Depth</th>
                            <th class="py-2.5 px-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        <?php foreach ($recentTransactions as $tx): ?>
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="py-3 px-3 text-slate-400"><?= date('d M Y, H:i', strtotime($tx['created_at'])) ?></td>
                                <td class="py-3 px-3">
                                    <?php if ($tx['type'] === 'commission'): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                            Commission
                                        </span>
                                    <?php elseif ($tx['type'] === 'withdrawal'): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                            Withdrawal
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                                            Purchase
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?php if (!empty($tx['sender_username'])): ?>
                                        <span class="text-white font-medium"><?= e($tx['sender_name'] ?? $tx['sender_username']) ?></span>
                                        <span class="text-slate-500">(@<?= e($tx['sender_username']) ?>)</span>
                                    <?php else: ?>
                                        <span class="text-slate-500">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?= !empty($tx['level_depth']) ? "<span class='px-2 py-0.5 bg-slate-800 rounded font-mono text-cyan-300'>Level {$tx['level_depth']}</span>" : '—' ?>
                                </td>
                                <td class="py-3 px-3 text-right font-mono font-bold <?= (float)$tx['amount'] >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                                    <?= (float)$tx['amount'] >= 0 ? '+' : '' ?><?= APP_CURRENCY ?><?= number_format((float)$tx['amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyRefLink() {
    const copyText = document.getElementById("refLinkInput");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);

    const notice = document.getElementById("copyNotice");
    notice.classList.remove("hidden");
    setTimeout(() => {
        notice.classList.add("hidden");
    }, 2500);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
