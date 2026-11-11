<?php
/**
 * ApexMLM One-Click Database Installer & Setup Wizard
 * Initializes tables and seed data automatically from database/schema.sql
 */

require_once __DIR__ . '/config.php';

$message = '';
$error = '';
$installed = false;

// Check if tables already exist
try {
    $pdo = getDBConnection();
    $check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($check) {
        $installed = true;
    }
} catch (Exception $e) {
    $error = "Database Connection Failed: " . $e->getMessage() . "<br><br>Please verify your DB_HOST, DB_NAME, DB_USER, and DB_PASS in <code>config.php</code>.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        $error = "Schema file not found at: {$schemaFile}";
    } else {
        $sql = file_get_contents($schemaFile);
        try {
            $pdo->exec($sql);
            $message = "Database tables and initial records installed successfully!";
            $installed = true;
        } catch (PDOException $ex) {
            $error = "Installation error: " . $ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup Wizard | <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg w-full glass-panel rounded-2xl p-8 relative shadow-2xl border border-cyan-500/30">
        <div class="text-center mb-6">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-cyan-500 to-amber-500 p-[2px] mx-auto mb-4">
                <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center">
                    <i class="fa-solid fa-database text-amber-400 text-2xl"></i>
                </div>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Database Setup Wizard</h1>
            <p class="text-xs text-slate-400 mt-1">ApexMLM Enterprise Platform Initializer</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 rounded-xl bg-rose-950/80 border border-rose-500/50 text-rose-200 text-sm">
                <div class="font-bold mb-1 flex items-center"><i class="fa-solid fa-triangle-exclamation mr-2"></i> Error:</div>
                <div><?= $error ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 text-sm">
                <div class="font-bold mb-1 flex items-center"><i class="fa-solid fa-circle-check mr-2"></i> Success:</div>
                <div><?= $message ?></div>
            </div>
        <?php endif; ?>

        <div class="space-y-4 mb-6 text-xs text-slate-300 bg-slate-900/60 p-4 rounded-xl border border-slate-800">
            <div class="flex justify-between border-b border-slate-800 pb-2">
                <span class="text-slate-400">Database Name:</span>
                <span class="font-mono text-cyan-400"><?= defined('DB_NAME') ? DB_NAME : 'unknown' ?></span>
            </div>
            <div class="flex justify-between border-b border-slate-800 pb-2">
                <span class="text-slate-400">Database Host:</span>
                <span class="font-mono text-cyan-400"><?= defined('DB_HOST') ? DB_HOST : 'unknown' ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-400">Table 'users' Status:</span>
                <?php if ($installed): ?>
                    <span class="font-bold text-emerald-400"><i class="fa-solid fa-circle-check mr-1"></i> Installed & Ready</span>
                <?php else: ?>
                    <span class="font-bold text-amber-400"><i class="fa-solid fa-circle-xmark mr-1"></i> Missing (Needs Setup)</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$installed): ?>
            <form method="POST" action="/install.php">
                <p class="text-xs text-slate-400 mb-4 text-center">
                    Click the button below to initialize all required database tables (`users`, `packages`, `wallets`, `e_pins`, `payouts`, etc.) and default seed accounts.
                </p>
                <button type="submit" class="w-full py-3 px-4 rounded-xl font-bold text-slate-950 bg-gradient-to-r from-cyan-400 to-amber-400 hover:opacity-90 transition shadow-lg flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-bolt"></i>
                    <span>Run 1-Click Database Setup</span>
                </button>
            </form>
        <?php else: ?>
            <div class="space-y-3">
                <a href="/login.php" class="w-full py-3 px-4 rounded-xl font-bold text-slate-950 bg-cyan-400 hover:bg-cyan-300 transition shadow-lg flex items-center justify-center space-x-2 text-sm">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Go to Login Page</span>
                </a>
                <form method="POST" action="/install.php" onsubmit="return confirm('Warning: Re-running installer may reset seed data. Continue?');">
                    <button type="submit" class="w-full py-2 px-3 text-xs text-slate-400 hover:text-rose-400 hover:bg-slate-900 rounded-lg transition text-center">
                        Re-run Installer (Reset Tables)
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
