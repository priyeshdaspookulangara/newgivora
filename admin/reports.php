<?php
/**
 * Administrative Financial Reports & Global Audit Trail
 * Comprehensive view of all system transactions, orders, and commission disbursements.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getDBConnection();

// Filter parameters
$typeFilter = $_GET['type'] ?? '';
$whereClause = '1=1';
$params = [];

if (in_array($typeFilter, ['commission', 'withdrawal', 'purchase'])) {
    $whereClause .= ' AND t.type = :type';
    $params['type'] = $typeFilter;
}

// Fetch all ledger transactions with recipient and sender usernames
$sql = "
    SELECT t.*,
           u.username as recipient_username, u.full_name as recipient_name,
           s.username as sender_username, s.full_name as sender_name
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN users s ON t.sender_id = s.id
    WHERE {$whereClause}
    ORDER BY t.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Aggregates
$totalCommissions = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'commission'")->fetchColumn();
$totalWithdrawals = (float)$pdo->query("SELECT COALESCE(SUM(ABS(amount)), 0) FROM transactions WHERE type = 'withdrawal'")->fetchColumn();
$totalPurchases = (float)$pdo->query("SELECT COALESCE(SUM(ABS(amount)), 0) FROM transactions WHERE type = 'purchase'")->fetchColumn();

$pageTitle = 'Financial Reports & Audit Ledger';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="w-full space-y-8">

    <!-- Header -->
    <div class="glass-panel rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 border-l-4 border-l-purple-400">
        <div>
            <span class="text-xs uppercase tracking-wider text-purple-400 font-bold mb-1">Financial Compliance</span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white">System Ledger & Commission Audits</h1>
            <p class="text-sm text-slate-400 mt-1">
                Real-time tracking of multi-level commission payouts, product revenues, and user withdrawals.
            </p>
        </div>

        <!-- Filter tabs -->
        <div class="flex items-center gap-2 bg-slate-900/90 p-1.5 rounded-xl border border-slate-800 text-xs">
            <a href="/admin/reports.php" class="px-3 py-1.5 rounded-lg <?= empty($typeFilter) ? 'bg-cyan-500 text-slate-950 font-bold' : 'text-slate-400 hover:text-white' ?>">All</a>
            <a href="/admin/reports.php?type=commission" class="px-3 py-1.5 rounded-lg <?= $typeFilter === 'commission' ? 'bg-cyan-500 text-slate-950 font-bold' : 'text-slate-400 hover:text-white' ?>">Commissions</a>
            <a href="/admin/reports.php?type=purchase" class="px-3 py-1.5 rounded-lg <?= $typeFilter === 'purchase' ? 'bg-cyan-500 text-slate-950 font-bold' : 'text-slate-400 hover:text-white' ?>">Purchases</a>
            <a href="/admin/reports.php?type=withdrawal" class="px-3 py-1.5 rounded-lg <?= $typeFilter === 'withdrawal' ? 'bg-cyan-500 text-slate-950 font-bold' : 'text-slate-400 hover:text-white' ?>">Withdrawals</a>
        </div>
    </div>

    <!-- Quick Financial Highlights -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="glass-panel rounded-2xl p-5">
            <span class="text-xs text-slate-400 uppercase font-semibold">Total Gross Purchases</span>
            <div class="text-2xl font-black text-cyan-400 mt-2 font-mono"><?= APP_CURRENCY ?><?= number_format($totalPurchases, 2) ?></div>
        </div>
        <div class="glass-panel-gold rounded-2xl p-5">
            <span class="text-xs text-slate-400 uppercase font-semibold">Total Commissions Disbursed</span>
            <div class="text-2xl font-black text-amber-300 mt-2 font-mono"><?= APP_CURRENCY ?><?= number_format($totalCommissions, 2) ?></div>
        </div>
        <div class="glass-panel rounded-2xl p-5">
            <span class="text-xs text-slate-400 uppercase font-semibold">Total Debited Withdrawals</span>
            <div class="text-2xl font-black text-rose-400 mt-2 font-mono"><?= APP_CURRENCY ?><?= number_format($totalWithdrawals, 2) ?></div>
        </div>
    </div>

    <!-- Transaction Ledger Table -->
    <div class="glass-panel rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-file-invoice-dollar text-purple-400"></i> Global Transaction Audit Log
            </h3>
            <span class="text-xs text-slate-400"><?= count($transactions) ?> record(s)</span>
        </div>

        <?php if (empty($transactions)): ?>
            <p class="text-sm text-slate-400 py-8 text-center">No transactions matching filter criteria.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-slate-400 border-b border-slate-800 font-semibold uppercase">
                        <tr>
                            <th class="py-2.5 px-3">TX ID</th>
                            <th class="py-2.5 px-3">Timestamp</th>
                            <th class="py-2.5 px-3">Type</th>
                            <th class="py-2.5 px-3">Recipient Account</th>
                            <th class="py-2.5 px-3">Triggered By / Downline Buyer</th>
                            <th class="py-2.5 px-3">Depth</th>
                            <th class="py-2.5 px-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        <?php foreach ($transactions as $tx): ?>
                            <tr class="hover:bg-slate-800/30">
                                <td class="py-3 px-3 font-mono text-slate-500">#<?= $tx['id'] ?></td>
                                <td class="py-3 px-3 text-slate-400"><?= date('d M Y, H:i:s', strtotime($tx['created_at'])) ?></td>
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
                                    <div class="font-medium text-white"><?= e($tx['recipient_name']) ?></div>
                                    <div class="text-[11px] text-slate-500 font-mono">@<?= e($tx['recipient_username']) ?> (ID #<?= $tx['user_id'] ?>)</div>
                                </td>
                                <td class="py-3 px-3">
                                    <?php if (!empty($tx['sender_username'])): ?>
                                        <div class="text-white"><?= e($tx['sender_name']) ?></div>
                                        <div class="text-[11px] text-slate-500 font-mono">@<?= e($tx['sender_username']) ?> (ID #<?= $tx['sender_id'] ?>)</div>
                                    <?php else: ?>
                                        <span class="text-slate-600">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?= !empty($tx['level_depth']) ? "<span class='px-2.5 py-0.5 bg-slate-800 rounded font-mono text-cyan-300'>Level {$tx['level_depth']}</span>" : '<span class="text-slate-600">—</span>' ?>
                                </td>
                                <td class="py-3 px-3 text-right font-mono font-bold text-sm <?= (float)$tx['amount'] >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
