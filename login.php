<?php
/**
 * Login Page with Role-Based Redirection
 * Password: Plain text comparison as explicitly requested for development/testing
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';

if (isAuthenticated()) {
    $role = $_SESSION['user_role'] ?? 'member';
    if ($role === 'admin') {
        header('Location: /admin/dashboard.php');
    } else {
        header('Location: /member/dashboard.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $user = authenticateUser($username, $password);
            if ($user) {
                if ($user['role'] === 'admin') {
                    header('Location: /admin/dashboard.php');
                } else {
                    header('Location: /member/dashboard.php');
                }
                exit;
            } else {
                $error = 'Invalid username or password credentials.';
            }
        } catch (PDOException $pdoEx) {
            if ($pdoEx->getCode() === '42S02') {
                $error = 'Database tables are not set up yet. Please visit <a href="/install.php" class="underline font-bold text-amber-300">install.php</a> to run the 1-click database setup.';
            } else {
                $error = 'Database error: ' . $pdoEx->getMessage();
            }
        }
    }
}

$pageTitle = 'Secure Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex items-center justify-center min-h-[calc(100vh-12rem)] px-4 py-12">
    <div class="max-w-md w-full glass-panel rounded-2xl p-8 relative overflow-hidden shadow-2xl">
        <div class="absolute -top-24 -right-24 w-48 h-48 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 mb-4 shadow-inner">
                <i class="fa-solid fa-lock-open text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold tracking-tight text-white">Platform Portal Access</h2>
            <p class="text-xs text-slate-400 mt-1">Authenticate with your Member or Admin account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-3.5 rounded-xl bg-rose-950/70 border border-rose-500/40 text-rose-300 text-sm flex items-center space-x-2">
                <i class="fa-solid fa-circle-exclamation text-rose-400"></i>
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login.php" class="space-y-5">
            <div>
                <label for="username" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <input type="text" id="username" name="username" required
                           value="<?= e($_POST['username'] ?? '') ?>"
                           class="w-full pl-10 pr-4 py-2.5 bg-slate-900/90 border border-slate-700/80 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 text-sm transition"
                           placeholder="Enter your username">
                </div>
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <input type="password" id="password" name="password" required
                           class="w-full pl-10 pr-4 py-2.5 bg-slate-900/90 border border-slate-700/80 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 text-sm transition"
                           placeholder="••••••••">
                </div>
                <p class="text-[11px] text-slate-500 mt-1">Note: Test passwords stored in plain text per configuration.</p>
            </div>

            <button type="submit" class="w-full py-3 px-4 rounded-xl font-bold text-slate-950 glow-cyan-btn transition shadow-lg mt-2 flex items-center justify-center space-x-2">
                <span>Sign In to Dashboard</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-800 text-center">
            <p class="text-xs text-slate-400 mb-3">Don't have an account?</p>
            <a href="/register.php" class="text-sm font-semibold text-cyan-400 hover:text-cyan-300 transition">
                Create Member Account &rarr;
            </a>
        </div>

        <div class="mt-6 p-3 bg-slate-900/60 rounded-xl border border-slate-800/80 text-[11px] text-slate-400 space-y-1">
            <div class="font-semibold text-amber-400">Demo Credentials:</div>
            <div><span class="text-slate-300 font-mono">admin</span> / <span class="text-slate-300 font-mono">admin123</span> (Administrator)</div>
            <div><span class="text-slate-300 font-mono">rootleader</span> / <span class="text-slate-300 font-mono">leader123</span> (Top Sponsor)</div>
            <div><span class="text-slate-300 font-mono">level7_shopper</span> / <span class="text-slate-300 font-mono">pass123</span> (Downline Buyer)</div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
