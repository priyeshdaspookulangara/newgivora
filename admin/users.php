<?php
/**
 * Administrative User Management
 * View all registered users, inspect downlines, and edit user profiles/details directly.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mlm_engine.php';

requireAdmin();

$pdo = getDBConnection();
$message = '';
$error = '';

// Edit User Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $editUserId = (int)$_POST['user_id'];
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['admin', 'member']) ? $_POST['role'] : 'member';
    $address = trim($_POST['address'] ?? '');
    $sponsorId = !empty($_POST['sponsor_id']) ? (int)$_POST['sponsor_id'] : null;
    $plainPassword = $_POST['plain_password'] ?? '';

    if (empty($fullName) || empty($email)) {
        $error = 'Full Name and Email are mandatory.';
    } else {
        try {
            if (!empty($plainPassword)) {
                $stmt = $pdo->prepare('
                    UPDATE users
                    SET full_name = :fn, email = :e, mobile = :m, role = :r, address = :addr, sponsor_id = :s, plain_password = :p
                    WHERE id = :id
                ');
                $stmt->execute([
                    'fn'   => $fullName,
                    'e'    => $email,
                    'm'    => $mobile,
                    'r'    => $role,
                    'addr' => $address,
                    's'    => $sponsorId,
                    'p'    => $plainPassword, // Plain text
                    'id'   => $editUserId
                ]);
            } else {
                $stmt = $pdo->prepare('
                    UPDATE users
                    SET full_name = :fn, email = :e, mobile = :m, role = :r, address = :addr, sponsor_id = :s
                    WHERE id = :id
                ');
                $stmt->execute([
                    'fn'   => $fullName,
                    'e'    => $email,
                    'm'    => $mobile,
                    'r'    => $role,
                    'addr' => $address,
                    's'    => $sponsorId,
                    'id'   => $editUserId
                ]);
            }
            $message = "User #{$editUserId} updated successfully!";
        } catch (Exception $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

// Fetch user for downline inspection if requested
$inspectUser = null;
$inspectedDownline = [];
if (isset($_GET['inspect_id'])) {
    $inspectId = (int)$_GET['inspect_id'];
    $uStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $uStmt->execute(['id' => $inspectId]);
    $inspectUser = $uStmt->fetch();
    if ($inspectUser) {
        $inspectedDownline = MLMEngine::getDownlineTree($pdo, $inspectId, 7);
    }
}

// Fetch all users with wallet info and sponsor name
$usersList = $pdo->query('
    SELECT u.*, s.username as sponsor_username, s.full_name as sponsor_name,
           w.balance, w.total_earned, w.total_withdrawn,
           (SELECT COUNT(*) FROM user_purchases WHERE user_id = u.id AND payment_status = "completed") as total_orders
    FROM users u
    LEFT JOIN users s ON u.sponsor_id = s.id
    LEFT JOIN wallets w ON u.id = w.user_id
    ORDER BY u.id ASC
')->fetchAll();

$pageTitle = 'Member & Affiliate Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header -->
    <div class="glass-panel rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 border-l-4 border-l-cyan-400">
        <div>
            <span class="text-xs uppercase tracking-wider text-cyan-400 font-bold mb-1">Affiliate Directory</span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white">Member Directory & Downlines</h1>
            <p class="text-sm text-slate-400 mt-1">
                Inspect affiliate downlines, modify credentials (plain text passwords), adjust sponsor hierarchy.
            </p>
        </div>

        <div class="text-xs font-mono text-slate-400">
            Total Users: <span class="text-white font-bold text-sm"><?= count($usersList) ?></span>
        </div>
    </div>

    <!-- Feedback messages -->
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

    <!-- Inspected Downline Tree Modal/Section -->
    <?php if ($inspectUser): ?>
        <div class="glass-panel-gold rounded-2xl p-6 border border-amber-500/40 relative">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-amber-500/20">
                <div>
                    <span class="text-xs uppercase font-bold text-amber-400">Network Tree Inspector</span>
                    <h3 class="text-xl font-bold text-white mt-1">
                        Downline of <?= e($inspectUser['full_name']) ?> <span class="text-slate-400 font-mono text-sm">(@<?= e($inspectUser['username']) ?>)</span>
                    </h3>
                </div>
                <a href="/admin/users.php" class="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300 hover:text-white text-xs font-semibold">
                    <i class="fa-solid fa-xmark mr-1"></i> Close Inspector
                </a>
            </div>

            <?php if (empty($inspectedDownline)): ?>
                <p class="text-xs text-slate-400 py-4">No downline members enrolled under this user.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($inspectedDownline as $lvl => $members): ?>
                        <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-800">
                            <div class="text-xs font-bold text-amber-300 mb-2 flex items-center justify-between">
                                <span>Tier Level <?= $lvl ?> (<?= count($members) ?> member<?= count($members) > 1 ? 's' : '' ?>)</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                <?php foreach ($members as $m): ?>
                                    <div class="bg-slate-950 p-2.5 rounded-lg border border-slate-800 text-xs">
                                        <div class="font-semibold text-white"><?= e($m['full_name']) ?></div>
                                        <div class="text-slate-400 font-mono text-[11px]">@<?= e($m['username']) ?> • ID #<?= $m['id'] ?></div>
                                        <div class="text-emerald-400 text-[11px] mt-1 font-mono">Earned: <?= APP_CURRENCY ?><?= number_format((float)($m['total_earned'] ?? 0), 2) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- User Management Table -->
    <div class="glass-panel rounded-2xl p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="text-slate-400 border-b border-slate-800 font-semibold uppercase">
                    <tr>
                        <th class="py-2.5 px-3">ID</th>
                        <th class="py-2.5 px-3">Username & Role</th>
                        <th class="py-2.5 px-3">Full Name & Contact</th>
                        <th class="py-2.5 px-3">Sponsor</th>
                        <th class="py-2.5 px-3">Password (Plain)</th>
                        <th class="py-2.5 px-3">Wallet Balance</th>
                        <th class="py-2.5 px-3">Total Earned</th>
                        <th class="py-2.5 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    <?php foreach ($usersList as $u): ?>
                        <tr class="hover:bg-slate-800/30">
                            <td class="py-3 px-3 font-mono text-cyan-400 font-bold">#<?= $u['id'] ?></td>
                            <td class="py-3 px-3">
                                <div class="font-bold text-white"><?= e($u['username']) ?></div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $u['role'] === 'admin' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20' ?>">
                                    <?= e($u['role']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-medium text-slate-200"><?= e($u['full_name']) ?></div>
                                <div class="text-[11px] text-slate-400"><?= e($u['email']) ?></div>
                                <div class="text-[11px] text-slate-500"><?= e($u['mobile'] ?? '—') ?></div>
                            </td>
                            <td class="py-3 px-3">
                                <?php if (!empty($u['sponsor_username'])): ?>
                                    <div class="text-amber-400 font-mono">@<?= e($u['sponsor_username']) ?></div>
                                    <div class="text-[11px] text-slate-500">#<?= $u['sponsor_id'] ?> - <?= e($u['sponsor_name']) ?></div>
                                <?php else: ?>
                                    <span class="text-slate-600">None (Root)</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 font-mono text-amber-300 text-[11px]">
                                <?= e($u['plain_password']) ?>
                            </td>
                            <td class="py-3 px-3 font-mono font-semibold text-emerald-400">
                                <?= APP_CURRENCY ?><?= number_format((float)($u['balance'] ?? 0), 2) ?>
                            </td>
                            <td class="py-3 px-3 font-mono font-semibold text-amber-300">
                                <?= APP_CURRENCY ?><?= number_format((float)($u['total_earned'] ?? 0), 2) ?>
                            </td>
                            <td class="py-3 px-3 text-right space-x-1 whitespace-nowrap">
                                <a href="/admin/users.php?inspect_id=<?= $u['id'] ?>" class="px-2.5 py-1 rounded bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 border border-purple-500/30 font-semibold text-[11px] transition">
                                    <i class="fa-solid fa-sitemap mr-1"></i> Downline
                                </a>
                                <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)"
                                        class="px-2.5 py-1 rounded bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/30 font-semibold text-[11px] transition">
                                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="glass-panel rounded-2xl max-w-lg w-full p-6 border border-cyan-500/40 relative shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-user-pen text-cyan-400"></i> Edit Member Profile & Settings
            </h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <form method="POST" action="/admin/users.php" class="space-y-4 mt-4">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="modalUserId">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Username (Read-only)</label>
                    <input type="text" id="modalUsername" disabled class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-slate-400 text-xs font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">System Role</label>
                    <select name="role" id="modalRole" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                        <option value="member">Member</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Full Name *</label>
                    <input type="text" name="full_name" id="modalFullName" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Email Address *</label>
                    <input type="email" name="email" id="modalEmail" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Mobile</label>
                    <input type="text" name="mobile" id="modalMobile" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-amber-300 mb-1">Password (Plain Text)</label>
                    <input type="text" name="plain_password" id="modalPassword" class="w-full px-3 py-2 bg-slate-900 border border-amber-500/40 rounded-lg text-amber-300 font-mono text-xs">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Sponsor ID (Tree Placement)</label>
                <input type="number" name="sponsor_id" id="modalSponsorId" placeholder="Leave empty for root" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Address</label>
                <textarea name="address" id="modalAddress" rows="2" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-lg border border-slate-700 text-slate-300 text-xs">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-lg font-bold text-slate-950 glow-cyan-btn text-xs">Save Updates</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(user) {
    document.getElementById('modalUserId').value = user.id;
    document.getElementById('modalUsername').value = user.username;
    document.getElementById('modalRole').value = user.role;
    document.getElementById('modalFullName').value = user.full_name;
    document.getElementById('modalEmail').value = user.email;
    document.getElementById('modalMobile').value = user.mobile || '';
    document.getElementById('modalPassword').value = user.plain_password || '';
    document.getElementById('modalSponsorId').value = user.sponsor_id || '';
    document.getElementById('modalAddress').value = user.address || '';

    document.getElementById('editUserModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editUserModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
