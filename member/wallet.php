<?php
/**
 * Member Digital Wallet & Payout Requests
 * View balance, request withdrawals, see complete transaction ledger and payout history.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

requireMember();

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$pdo = getDBConnection();

$feedbackMsg = '';
$feedbackErr = '';

// Process Withdrawal / Payout Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_payout') {
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;

    // Fetch latest wallet balance
    $wallet = getOrCreateWallet($userId);
    $currentBalance = (float)$wallet['balance'];

    if ($amount <= 0) {
        $feedbackErr = 'Please enter a valid withdrawal amount greater than ₹0.';
    } elseif ($amount > $currentBalance) {
        $feedbackErr = 'Insufficient wallet balance. You requested ₹' . number_format($amount, 2) . ' but only have ₹' . number_format($currentBalance, 2) . '.';
    } else {
        // Create pending payout request
        $pdo->beginTransaction();
        try {
            $payoutStmt = $pdo->prepare('
                INSERT INTO payouts (user_id, requested_amount, status, requested_at)
                VALUES (:uid, :amount, "pending", NOW())
            ');
            $payoutStmt->execute([
                'uid'    => $userId,
                'amount' => $amount
            ]);

            $pdo->commit();
            $feedbackMsg = 'Payout request for ' . APP_CURRENCY . number_format($amount, 2) . ' submitted successfully. Awaiting administrative review.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $feedbackErr = 'Failed to submit withdrawal request: ' . $e->getMessage();
        }
    }
}

// Fetch up-to-date wallet
$wallet = getOrCreateWallet($userId);

// Fetch all transactions
$txStmt = $pdo->prepare('
    SELECT t.*, s.username as sender_username, s.full_name as sender_name
    FROM transactions t
    LEFT JOIN users s ON t.sender_id = s.id
    WHERE t.user_id = :uid
    ORDER BY t.created_at DESC
');
$txStmt->execute(['uid' => $userId]);
$transactions = $txStmt->fetchAll();

// Fetch member payout requests
$payoutsStmt = $pdo->prepare('
    SELECT * FROM payouts
    WHERE user_id = :uid
    ORDER BY requested_at DESC
');
$payoutsStmt->execute(['uid' => $userId]);
$payouts = $payoutsStmt->fetchAll();

$pageTitle = 'Digital Wallet & Ledger';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="w-full space-y-8">

    <!-- Wallet Header Banner -->
    <div class="glass-panel rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 border-l-4 border-l-emerald-400">
        <div>
            <span class="text-xs uppercase tracking-wider text-emerald-400 font-semibold mb-1">Encrypted Digital Vault</span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white">Digital Wallet & Payout Center</h1>
            <p class="text-sm text-slate-400 mt-1">
                View real-time commission disbursements, track withdrawals, and initiate payout distributions.
            </p>
        </div>

        <div class="flex items-center gap-4">
            <div class="glass-panel-gold rounded-xl px-5 py-3 border border-amber-500/30">
                <span class="text-[11px] uppercase tracking-wider text-amber-400 font-bold block">Available Balance</span>
                <span class="text-2xl font-black text-amber-300 font-mono"><?= APP_CURRENCY ?><?= number_format((float)$wallet['balance'], 2) ?></span>
            </div>
        </div>
    </div>

    <!-- Alert Notices -->
    <?php if (!empty($feedbackMsg)): ?>
        <div class="p-4 rounded-xl bg-emerald-950/70 border border-emerald-500/40 text-emerald-300 text-sm flex items-center space-x-2">
            <i class="fa-solid fa-circle-check text-emerald-400"></i>
            <span><?= e($feedbackMsg) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($feedbackErr)): ?>
        <div class="p-4 rounded-xl bg-rose-950/70 border border-rose-500/40 text-rose-300 text-sm flex items-center space-x-2">
            <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
            <span><?= e($feedbackErr) ?></span>
        </div>
    <?php endif; ?>

    <!-- Overview Financial Cards & Withdrawal Form -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Metrics Column (2 Cards) -->
        <div class="space-y-4">
            <div class="glass-panel rounded-2xl p-5">
                <div class="text-xs text-slate-400 uppercase font-semibold">Total Cumulative Earnings</div>
                <div class="text-2xl font-black text-white mt-2 font-mono"><?= APP_CURRENCY ?><?= number_format((float)$wallet['total_earned'], 2) ?></div>
                <p class="text-xs text-emerald-400 mt-1"><i class="fa-solid fa-arrow-trend-up mr-1"></i> Direct & Multi-level Team Yields</p>
            </div>

            <div class="glass-panel rounded-2xl p-5">
                <div class="text-xs text-slate-400 uppercase font-semibold">Total Withdrawn To Date</div>
                <div class="text-2xl font-black text-slate-300 mt-2 font-mono"><?= APP_CURRENCY ?><?= number_format((float)$wallet['total_withdrawn'], 2) ?></div>
                <p class="text-xs text-slate-500 mt-1"><i class="fa-solid fa-building-columns mr-1"></i> Processed and Settled Payouts</p>
            </div>

            <!-- Fast Withdrawal Form -->
            <div class="glass-panel-gold rounded-2xl p-6 border border-amber-500/30">
                <h3 class="text-base font-bold text-white mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-money-bill-transfer text-amber-400"></i> Request Cash Payout
                </h3>
                <p class="text-xs text-slate-300 mb-4">
                    Withdraw commission funds directly to your verified bank account or digital transfer.
                </p>

                <form method="POST" action="/member/wallet.php" class="space-y-4">
                    <input type="hidden" name="action" value="request_payout">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Requested Amount (<?= APP_CURRENCY ?>)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-amber-400 font-bold"><?= APP_CURRENCY ?></span>
                            <input type="number" step="0.01" min="100" max="<?= (float)$wallet['balance'] ?>" name="amount" required
                                   placeholder="Min 100.00"
                                   class="w-full pl-8 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white font-mono text-sm focus:border-amber-400 focus:outline-none">
                        </div>
                        <span class="text-[11px] text-slate-400 mt-1 block">Maximum available: <?= APP_CURRENCY ?><?= number_format((float)$wallet['balance'], 2) ?></span>
                    </div>

                    <button type="submit"
                            <?= (float)$wallet['balance'] <= 0 ? 'disabled' : '' ?>
                            class="w-full py-2.5 px-4 rounded-xl font-bold text-slate-950 <?= (float)$wallet['balance'] > 0 ? 'glow-gold-btn cursor-pointer' : 'bg-slate-700 text-slate-500 cursor-not-allowed' ?> text-xs transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i> Submit Payout Request
                    </button>
                </form>
            </div>
        </div>

        <!-- Payout Requests History -->
        <div class="lg:col-span-2 glass-panel rounded-2xl p-6">
            <h3 class="text-lg font-bold text-white mb-4 pb-3 border-b border-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-cyan-400"></i> Withdrawal Request Pipeline
            </h3>

            <?php if (empty($payouts)): ?>
                <div class="py-8 text-center text-slate-500 text-sm">
                    No withdrawal requests have been initiated yet.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 border-b border-slate-800 font-semibold uppercase">
                            <tr>
                                <th class="py-2.5 px-3">Request ID</th>
                                <th class="py-2.5 px-3">Requested At</th>
                                <th class="py-2.5 px-3">Amount</th>
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3">Processed At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-slate-300">
                            <?php foreach ($payouts as $p): ?>
                                <tr class="hover:bg-slate-800/30">
                                    <td class="py-3 px-3 font-mono font-semibold text-white">#<?= $p['id'] ?></td>
                                    <td class="py-3 px-3 text-slate-400"><?= date('d M Y, H:i', strtotime($p['requested_at'])) ?></td>
                                    <td class="py-3 px-3 font-mono font-bold text-white"><?= APP_CURRENCY ?><?= number_format((float)$p['requested_amount'], 2) ?></td>
                                    <td class="py-3 px-3">
                                        <?php if ($p['status'] === 'approved'): ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                                Approved
                                            </span>
                                        <?php elseif ($p['status'] === 'rejected'): ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                                Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">
                                                Pending Review
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-slate-400">
                                        <?= !empty($p['processed_at']) ? date('d M Y, H:i', strtotime($p['processed_at'])) : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Full Transaction Ledger Table -->
    <div class="glass-panel rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-list-check text-cyan-400"></i> Complete Audit Trail & Transaction Ledger
            </h3>
            <span class="text-xs text-slate-400"><?= count($transactions) ?> record(s)</span>
        </div>

        <?php if (empty($transactions)): ?>
            <p class="text-sm text-slate-400 py-6 text-center">No transactions found in this wallet ledger.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-slate-400 border-b border-slate-800 font-semibold uppercase">
                        <tr>
                            <th class="py-2.5 px-3">TX ID</th>
                            <th class="py-2.5 px-3">Timestamp</th>
                            <th class="py-2.5 px-3">Type</th>
                            <th class="py-2.5 px-3">Origin / Triggered By</th>
                            <th class="py-2.5 px-3">MLM Level Depth</th>
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
                                    <?php if (!empty($tx['sender_username'])): ?>
                                        <span class="text-white font-medium"><?= e($tx['sender_name'] ?? $tx['sender_username']) ?></span>
                                        <span class="text-slate-500">(@<?= e($tx['sender_username']) ?>)</span>
                                    <?php else: ?>
                                        <span class="text-slate-500">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?= !empty($tx['level_depth']) ? "<span class='px-2.5 py-0.5 bg-slate-800 rounded-full font-mono text-cyan-300 border border-cyan-500/20'>Level {$tx['level_depth']}</span>" : '<span class="text-slate-600">—</span>' ?>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
