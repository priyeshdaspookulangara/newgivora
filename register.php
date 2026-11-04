<?php
/**
 * Member Registration with Sponsor tree placement
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$sponsorUsername = trim($_GET['ref'] ?? '');
$sponsorId = null;

$pdo = getDBConnection();

// Pre-fill sponsor if passed in query
if (!empty($sponsorUsername)) {
    $stmt = $pdo->prepare('SELECT id, username, full_name FROM users WHERE username = :u LIMIT 1');
    $stmt->execute(['u' => $sponsorUsername]);
    $sponsor = $stmt->fetch();
    if ($sponsor) {
        $sponsorId = (int)$sponsor['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $sponsorInput = trim($_POST['sponsor_username'] ?? '');

    if (empty($username) || empty($password) || empty($fullName) || empty($email)) {
        $error = 'Please fill out all required fields (username, password, full name, email).';
    } else {
        // Resolve sponsor
        $resolvedSponsorId = null;
        if (!empty($sponsorInput)) {
            $sStmt = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
            $sStmt->execute(['u' => $sponsorInput]);
            $sRow = $sStmt->fetch();
            if ($sRow) {
                $resolvedSponsorId = (int)$sRow['id'];
            } else {
                $error = "Sponsor username '{$sponsorInput}' not found.";
            }
        }

        if (empty($error)) {
            // Check username or email uniqueness
            $checkStmt = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
            $checkStmt->execute(['u' => $username, 'e' => $email]);
            if ($checkStmt->fetch()) {
                $error = 'Username or Email is already registered.';
            } else {
                // Insert user
                $pdo->beginTransaction();
                try {
                    $insertStmt = $pdo->prepare('
                        INSERT INTO users (username, plain_password, sponsor_id, role, full_name, mobile, email, address, created_at)
                        VALUES (:u, :p, :s, "member", :fn, :m, :e, :addr, NOW())
                    ');
                    $insertStmt->execute([
                        'u'    => $username,
                        'p'    => $password, // Plain text as requested
                        's'    => $resolvedSponsorId,
                        'fn'   => $fullName,
                        'm'    => $mobile,
                        'e'    => $email,
                        'addr' => $address
                    ]);
                    $newUserId = (int)$pdo->lastInsertId();

                    // Create digital wallet
                    $walletStmt = $pdo->prepare('INSERT INTO wallets (user_id, balance, total_earned, total_withdrawn) VALUES (:uid, 0.00, 0.00, 0.00)');
                    $walletStmt->execute(['uid' => $newUserId]);

                    $pdo->commit();

                    // Authenticate and redirect
                    authenticateUser($username, $password);
                    $_SESSION['flash_success'] = "Welcome to " . APP_NAME . "! Your account is active.";
                    header('Location: /member/dashboard.php');
                    exit;

                } catch (Exception $ex) {
                    $pdo->rollBack();
                    $error = 'Registration failed: ' . $ex->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Join ApexMLM Enterprise';
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex items-center justify-center min-h-[calc(100vh-12rem)] px-4 py-10">
    <div class="max-w-xl w-full glass-panel rounded-2xl p-8 relative shadow-2xl">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold tracking-tight text-white">Join the ApexMLM Network</h2>
            <p class="text-xs text-slate-400 mt-1">Direct selling & high-yield level compensation</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-3.5 rounded-xl bg-rose-950/70 border border-rose-500/40 text-rose-300 text-sm flex items-center space-x-2">
                <i class="fa-solid fa-circle-exclamation text-rose-400"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="/register.php<?= !empty($sponsorUsername) ? '?ref=' . urlencode($sponsorUsername) : '' ?>" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Username *</label>
                    <input type="text" name="username" required value="<?= e($_POST['username'] ?? '') ?>"
                           class="w-full px-3.5 py-2 bg-slate-900/90 border border-slate-700 rounded-xl text-white text-sm focus:border-cyan-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Password *</label>
                    <input type="password" name="password" required
                           class="w-full px-3.5 py-2 bg-slate-900/90 border border-slate-700 rounded-xl text-white text-sm focus:border-cyan-400 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Full Name *</label>
                    <input type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>"
                           class="w-full px-3.5 py-2 bg-slate-900/90 border border-slate-700 rounded-xl text-white text-sm focus:border-cyan-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Email Address *</label>
                    <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"
                           class="w-full px-3.5 py-2 bg-slate-900/90 border border-slate-700 rounded-xl text-white text-sm focus:border-cyan-400 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Mobile Number</label>
                    <input type="text" name="mobile" value="<?= e($_POST['mobile'] ?? '') ?>" placeholder="+91 98765 43210"
                           class="w-full px-3.5 py-2 bg-slate-900/90 border border-slate-700 rounded-xl text-white text-sm focus:border-cyan-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-amber-300 mb-1">Sponsor Username (Optional)</label>
                    <input type="text" name="sponsor_username" value="<?= e($_POST['sponsor_username'] ?? $sponsorUsername) ?>" placeholder="e.g. rootleader"
                           class="w-full px-3.5 py-2 bg-slate-900/90 border border-amber-500/40 rounded-xl text-amber-200 text-sm focus:border-amber-400 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Delivery / Residential Address</label>
                <textarea name="address" rows="2" class="w-full px-3.5 py-2 bg-slate-900/90 border border-slate-700 rounded-xl text-white text-sm focus:border-cyan-400 focus:outline-none"><?= e($_POST['address'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="w-full py-3 px-4 rounded-xl font-bold text-slate-950 glow-gold-btn transition shadow-lg mt-4">
                Register & Activate Member Account
            </button>
        </form>

        <div class="mt-6 text-center text-xs text-slate-400">
            Already registered? <a href="/login.php" class="text-cyan-400 font-semibold hover:underline">Log in here</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
