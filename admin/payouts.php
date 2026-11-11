<?php
/**
 * Administrative Payout Management
 * Review pending withdrawal requests, approve or reject payouts, and update ledger balances.
 *
 * Approval logic:
 * When an admin APPROVES a payout:
 * 1. Payout status -> 'approved', processed_at -> NOW()
 * 2. User wallet: balance is deducted by requested_amount, total_withdrawn is incremented by requested_amount.
 * 3. A withdrawal transaction ledger record is created: type = 'withdrawal', amount = -requested_amount.
 *
 * Rejection logic:
 * When an admin REJECTS a payout:
 * 1. Payout status -> 'rejected', processed_at -> NOW()
 * (Balance was not deducted beforehand, so no refund needed, or marked cleanly as rejected).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getDBConnection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payoutId = (int)$_POST['payout_id'];
    $action = $_POST['action'];

    // Verify payout exists and is currently pending
    $stmt = $pdo->prepare('SELECT * FROM payouts WHERE id = :id FOR UPDATE');
    $pdo->beginTransaction();

    try {
        $stmt->execute(['id' => $payoutId]);
        $payout = $stmt->fetch();

        if (!$payout) {
            throw new Exception("Payout request #{$payoutId} not found.");
        }

        if ($payout['status'] !== 'pending') {
            throw new Exception("Payout #{$payoutId} has already been processed as '{$payout['status']}'.");
        }

        $userId = (int)$payout['user_id'];
        $amount = (float)$payout['requested_amount'];

        if ($action === 'approve') {
            // Verify user has sufficient balance
            $wStmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = :uid FOR UPDATE');
            $wStmt->execute(['uid' => $userId]);
            $wallet = $wStmt->fetch();

            if (!$wallet || (float)$wallet['balance'] < $amount) {
                throw new Exception("Approval failed: Member has insufficient wallet balance (Available: ₹" . number_format($wallet['balance'] ?? 0, 2) . ", Requested: ₹" . number_format($amount, 2) . ").");
            }

            // 1. Update Payout record
            $upPayout = $pdo->prepare('UPDATE payouts SET status = "approved", processed_at = NOW() WHERE id = :id');
            $upPayout->execute(['id' => $payoutId]);

            // 2. Deduct from wallet balance & increment total_withdrawn
            $upWallet = $pdo->prepare('
                UPDATE wallets
                SET balance = balance - :amount,
                    total_withdrawn = total_withdrawn + :amount
                WHERE user_id = :uid
            ');
            $upWallet->execute([
                'amount' => $amount,
                'uid'    => $userId
            ]);

            // 3. Record in ledger
            $txStmt = $pdo->prepare('
                INSERT INTO transactions (user_id, sender_id, amount, type, level_depth, created_at)
                VALUES (:uid, NULL, :amount, "withdrawal", NULL, NOW())
            ');
            $txStmt->execute([
                'uid'    => $userId,
                'amount' => -$amount
            ]);

            $pdo->commit();
            $message = "Payout request #{$payoutId} for ₹" . number_format($amount, 2) . " has been APPROVED and debited successfully.";

        } elseif ($action === 'reject') {
            $upPayout = $pdo->prepare('UPDATE payouts SET status = "rejected", processed_at = NOW() WHERE id = :id');
            $upPayout->execute(['id' => $payoutId]);

            $pdo->commit();
            $message = "Payout request #{$payoutId} for ₹" . number_format($amount, 2) . " has been REJECTED.";
        } else {
            throw new Exception("Unknown action '{$action}'.");
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Fetch all payouts with user and wallet details
$payoutsQuery = $pdo->query('
    SELECT p.*, u.username, u.full_name, u.email, u.mobile, w.balance as current_wallet_balance
    FROM payouts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN wallets w ON u.id = w.user_id
    ORDER BY
        CASE WHEN p.status = "pending" THEN 1 ELSE 2 END,
        p.requested_at DESC
');
$allPayouts = $payoutsQuery->fetchAll();

$pageTitle = 'Payouts & Withdrawal Approvals';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="w-full space-y-8">

    <!-- Header -->
    <div class="glass-panel-gold rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 border-l-4 border-l-amber-400">
        <div>
            <span class="text-xs uppercase tracking-wider text-amber-400 font-bold mb-1">Treasury & Settlements</span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white">Payout Disbursements & Approvals</h1>
            <p class="text-sm text-slate-300 mt-1">
                Authorize member withdrawal requests, update ledger balances, and audit historical settlements.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="/admin/reports.php" class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 hover:border-slate-600 text-xs font-semibold text-slate-300 transition">
                <i class="fa-solid fa-receipt mr-1 text-purple-400"></i> Financial Ledger
            </a>
        </div>
    </div>

    <!-- Feedback alerts -->
    <?php if (!empty($message)): ?>
        <div class="p-4 rounded-xl bg-emerald-950/70 border border-emerald-500/40 text-emerald-300 text-sm flex items-center space-x-2">
            <i class="fa-solid fa-circle-check text-emerald-400"></i>
            <span><?= e($message) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-xl bg-rose-950/70 border border-rose-500/40 text-rose-300 text-sm flex items-center space-x-2">
            <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Payouts Table -->
    <div class="glass-panel rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-hand-holding-dollar text-amber-400"></i> Withdrawal Request Pipeline
            </h3>
            <span class="text-xs text-slate-400"><?= count($allPayouts) ?> total requests</span>
        </div>

        <?php if (empty($allPayouts)): ?>
            <p class="text-sm text-slate-400 py-8 text-center">No withdrawal records found.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-slate-400 border-b border-slate-800 font-semibold uppercase">
                        <tr>
                            <th class="py-2.5 px-3">Req ID</th>
                            <th class="py-2.5 px-3">Member Details</th>
                            <th class="py-2.5 px-3">Current Wallet</th>
                            <th class="py-2.5 px-3">Amount Requested</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3">Date Requested</th>
                            <th class="py-2.5 px-3">Processed At</th>
                            <th class="py-2.5 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        <?php foreach ($allPayouts as $p): ?>
                            <tr class="hover:bg-slate-800/30 <?= $p['status'] === 'pending' ? 'bg-amber-500/5' : '' ?>">
                                <td class="py-3 px-3 font-mono text-cyan-400 font-bold">#<?= $p['id'] ?></td>
                                <td class="py-3 px-3">
                                    <div class="font-bold text-white"><?= e($p['full_name']) ?></div>
                                    <div class="text-[11px] text-slate-400 font-mono">@<?= e($p['username']) ?> • ID #<?= $p['user_id'] ?></div>
                                    <div class="text-[10px] text-slate-500"><?= e($p['email']) ?></div>
                                </td>
                                <td class="py-3 px-3 font-mono font-semibold text-emerald-400">
                                    <?= APP_CURRENCY ?><?= number_format((float)($p['current_wallet_balance'] ?? 0), 2) ?>
                                </td>
                                <td class="py-3 px-3 font-mono font-bold text-amber-300 text-sm">
                                    <?= APP_CURRENCY ?><?= number_format((float)$p['requested_amount'], 2) ?>
                                </td>
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
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-300 border border-amber-500/40 animate-pulse">
                                            Pending Review
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-slate-400"><?= date('d M Y, H:i', strtotime($p['requested_at'])) ?></td>
                                <td class="py-3 px-3 text-slate-500">
                                    <?= !empty($p['processed_at']) ? date('d M Y, H:i', strtotime($p['processed_at'])) : '—' ?>
                                </td>
                                <td class="py-3 px-3 text-right whitespace-nowrap">
                                    <?php if ($p['status'] === 'pending'): ?>
                                        <div class="inline-flex items-center gap-2">
                                            <form method="POST" action="/admin/payouts.php" onsubmit="return confirm('Approve payout of ₹<?= number_format((float)$p['requested_amount'], 2) ?> for <?= e($p['full_name']) ?>? This will deduct wallet balance and log a withdrawal.')">
                                                <input type="hidden" name="payout_id" value="<?= $p['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="px-2.5 py-1 rounded bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/30 font-bold text-[11px] transition">
                                                    <i class="fa-solid fa-check mr-1"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="/admin/payouts.php" onsubmit="return confirm('Reject payout of ₹<?= number_format((float)$p['requested_amount'], 2) ?>?')">
                                                <input type="hidden" name="payout_id" value="<?= $p['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="px-2.5 py-1 rounded bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/30 font-bold text-[11px] transition">
                                                    <i class="fa-solid fa-xmark mr-1"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-600 text-[11px]">Completed</span>
                                    <?php endif; ?>
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
