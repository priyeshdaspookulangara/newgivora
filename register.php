<?php
/**
 * Member Registration with Sponsor tree placement
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pin_engine.php';

$error = '';
$sponsorUsername = trim($_GET['ref'] ?? '');
$pinCodeInput = trim($_GET['pin'] ?? $_POST['pin_code'] ?? '');
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
    $pinCodeInput = trim($_POST['pin_code'] ?? '');

    if (empty($username) || empty($password) || empty($fullName) || empty($email)) {
        $error = 'Please fill out all required fields (username, password, full name, email).';
    } elseif (empty($pinCodeInput)) {
        $error = 'PIN Number is required to activate your membership package.';
    } else {
        // Validate PIN first before creating user
        $pinCheck = verifyPin($pdo, $pinCodeInput);
        if (!$pinCheck['valid']) {
            $error = $pinCheck['message'];
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
                    // Insert user & execute transaction
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

                        // Consume PIN and activate package & distribute commissions
                        $activationResult = activateUserWithPin($pdo, $newUserId, $pinCodeInput, $resolvedSponsorId);
                        if (!$activationResult['success']) {
                            throw new Exception($activationResult['message']);
                        }

                        $pdo->commit();

                        // Authenticate and redirect
                        authenticateUser($username, $password);
                        $_SESSION['flash_success'] = "Welcome to " . APP_NAME . "! Your package '{$activationResult['package_name']}' is activated via PIN {$activationResult['pin_code']}.";
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

        <form method="POST" action="/register.php<?= !empty($sponsorUsername) ? '?ref=' . urlencode($sponsorUsername) : '' ?>" class="space-y-4" id="registrationForm">

            <!-- E-PIN Verification Section -->
            <div class="p-4 rounded-xl bg-slate-900/90 border border-cyan-500/40 shadow-inner">
                <label class="block text-xs font-bold uppercase tracking-wider text-cyan-300 mb-1">
                    <i class="fa-solid fa-key mr-1"></i> Membership PIN Number *
                </label>
                <div class="flex space-x-2">
                    <input type="text" id="pin_code" name="pin_code" required value="<?= e($pinCodeInput) ?>" placeholder="e.g. PIN-A1B2-C3D4-E5F6"
                           class="w-full px-3.5 py-2 bg-slate-950 border border-cyan-500/50 rounded-xl text-cyan-200 text-sm font-mono tracking-wider uppercase focus:border-cyan-300 focus:outline-none">
                    <button type="button" id="btnVerifyPin" class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 hover:bg-cyan-500/30 transition whitespace-nowrap">
                        Verify PIN
                    </button>
                </div>

                <!-- Package Detection Badge Container -->
                <div id="pinDetectionBox" class="mt-3 hidden transition-all duration-300">
                    <div id="pinStatusContent" class="p-3 rounded-lg text-xs flex items-center justify-between">
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-1.5">
                    Enter the PIN provided by your sponsor or admin. The system will detect your package automatically.
                </p>
            </div>

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

            <button type="submit" id="btnSubmitRegistration" class="w-full py-3 px-4 rounded-xl font-bold text-slate-950 glow-gold-btn transition shadow-lg mt-4">
                Register & Activate Member Account
            </button>
        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pinInput = document.getElementById('pin_code');
            const btnVerify = document.getElementById('btnVerifyPin');
            const box = document.getElementById('pinDetectionBox');
            const content = document.getElementById('pinStatusContent');

            async function checkPin() {
                const pin = pinInput.value.trim();
                if (!pin) {
                    box.classList.add('hidden');
                    return;
                }

                btnVerify.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
                btnVerify.disabled = true;

                try {
                    const res = await fetch('/api_verify_pin.php?pin=' + encodeURIComponent(pin));
                    const data = await res.json();

                    box.classList.remove('hidden');
                    if (data.valid && data.pin) {
                        content.className = 'p-3 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 flex items-center justify-between';
                        content.innerHTML = `
                            <div>
                                <span class="font-bold text-emerald-300 flex items-center">
                                    <i class="fa-solid fa-circle-check mr-2 text-emerald-400"></i>
                                    Detected Package: ${data.pin.package_name}
                                </span>
                                <p class="text-[11px] text-emerald-400/80 mt-0.5">Value: ₹${parseFloat(data.pin.price).toLocaleString('en-IN', {minimumFractionDigits: 2})} | Type: ${data.pin.package_type}</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[10px] font-bold uppercase tracking-wider">
                                Verified PIN
                            </span>
                        `;
                    } else {
                        content.className = 'p-3 rounded-xl bg-rose-950/80 border border-rose-500/50 text-rose-200 flex items-center justify-between';
                        content.innerHTML = `
                            <div class="flex items-center">
                                <i class="fa-solid fa-circle-xmark mr-2 text-rose-400"></i>
                                <span>${data.message || 'Invalid or used PIN number.'}</span>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg bg-rose-500/20 text-rose-300 border border-rose-500/30 text-[10px] font-bold uppercase tracking-wider">
                                Invalid
                            </span>
                        `;
                    }
                } catch (e) {
                    box.classList.remove('hidden');
                    content.className = 'p-3 rounded-xl bg-rose-950/80 border border-rose-500/50 text-rose-200';
                    content.innerHTML = `<i class="fa-solid fa-triangle-exclamation mr-2"></i> Verification network error.`;
                } finally {
                    btnVerify.innerHTML = 'Verify PIN';
                    btnVerify.disabled = false;
                }
            }

            btnVerify.addEventListener('click', checkPin);

            let typingTimer;
            pinInput.addEventListener('input', function() {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(checkPin, 500);
            });

            // If pre-filled, verify immediately
            if (pinInput.value.trim().length > 3) {
                checkPin();
            }
        });
        </script>

        <div class="mt-6 text-center text-xs text-slate-400">
            Already registered? <a href="/login.php" class="text-cyan-400 font-semibold hover:underline">Log in here</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
