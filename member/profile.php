<?php
/**
 * Member Profile Management
 * View and edit profile details (full name, email, mobile, address, password).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

requireMember();

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$pdo = getDBConnection();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $newPassword = $_POST['plain_password'] ?? '';

    if (empty($fullName) || empty($email)) {
        $error = 'Full Name and Email are mandatory fields.';
    } else {
        // Check email uniqueness if changed
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = :e AND id != :uid LIMIT 1');
        $checkStmt->execute(['e' => $email, 'uid' => $userId]);
        if ($checkStmt->fetch()) {
            $error = 'The email address is already in use by another member.';
        } else {
            // Update profile
            if (!empty($newPassword)) {
                $upStmt = $pdo->prepare('
                    UPDATE users
                    SET full_name = :fn, email = :e, mobile = :m, address = :addr, plain_password = :p
                    WHERE id = :uid
                ');
                $upStmt->execute([
                    'fn'   => $fullName,
                    'e'    => $email,
                    'm'    => $mobile,
                    'addr' => $address,
                    'p'    => $newPassword, // Plain text as requested
                    'uid'  => $userId
                ]);
            } else {
                $upStmt = $pdo->prepare('
                    UPDATE users
                    SET full_name = :fn, email = :e, mobile = :m, address = :addr
                    WHERE id = :uid
                ');
                $upStmt->execute([
                    'fn'   => $fullName,
                    'e'    => $email,
                    'm'    => $mobile,
                    'addr' => $address,
                    'uid'  => $userId
                ]);
            }

            $success = 'Profile details updated successfully!';
            $currentUser = getCurrentUser(); // refresh
            $_SESSION['full_name'] = $currentUser['full_name'];
        }
    }
}

$pageTitle = 'My Profile Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <div class="glass-panel rounded-2xl p-6 sm:p-8 border-l-4 border-l-cyan-400">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white">Account & Profile Settings</h1>
        <p class="text-sm text-slate-400 mt-1">
            Manage your personal identity details, contact numbers, and security credentials.
        </p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="p-4 rounded-xl bg-emerald-950/70 border border-emerald-500/40 text-emerald-300 text-sm flex items-center space-x-2">
            <i class="fa-solid fa-circle-check text-emerald-400"></i>
            <span><?= e($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-xl bg-rose-950/70 border border-rose-500/40 text-rose-300 text-sm flex items-center space-x-2">
            <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="glass-panel rounded-2xl p-6 sm:p-8">
        <form method="POST" action="/member/profile.php" class="space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pb-6 border-b border-slate-800">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Username (Fixed)</label>
                    <input type="text" disabled value="<?= e($currentUser['username']) ?>"
                           class="w-full px-3.5 py-2.5 bg-slate-900/60 border border-slate-800 rounded-xl text-slate-400 text-sm cursor-not-allowed">
                    <span class="text-[11px] text-slate-500 mt-1 block">Username cannot be altered after registration.</span>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Member Role</label>
                    <input type="text" disabled value="<?= strtoupper(e($currentUser['role'])) ?>"
                           class="w-full px-3.5 py-2.5 bg-slate-900/60 border border-slate-800 rounded-xl text-cyan-300 font-semibold text-sm cursor-not-allowed">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Full Legal Name *</label>
                    <input type="text" name="full_name" required value="<?= e($currentUser['full_name']) ?>"
                           class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:border-cyan-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Email Address *</label>
                    <input type="email" name="email" required value="<?= e($currentUser['email']) ?>"
                           class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:border-cyan-400 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Mobile Number</label>
                    <input type="text" name="mobile" value="<?= e($currentUser['mobile'] ?? '') ?>"
                           class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:border-cyan-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-amber-300 mb-2">Account Password (Plain Text)</label>
                    <input type="text" name="plain_password" value="<?= e($currentUser['plain_password']) ?>"
                           class="w-full px-3.5 py-2.5 bg-slate-900 border border-amber-500/40 rounded-xl text-amber-300 font-mono text-sm focus:border-amber-400 focus:outline-none">
                    <span class="text-[11px] text-slate-500 mt-1 block">Stored as plain text as requested for testing.</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Residential / Shipping Address</label>
                <textarea name="address" rows="3" class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:border-cyan-400 focus:outline-none"><?= e($currentUser['address'] ?? '') ?></textarea>
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 border-t border-slate-800">
                <a href="/member/dashboard.php" class="px-5 py-2.5 rounded-xl border border-slate-700 text-slate-300 text-sm hover:bg-slate-800 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl font-bold text-slate-950 glow-cyan-btn text-sm shadow-lg">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
