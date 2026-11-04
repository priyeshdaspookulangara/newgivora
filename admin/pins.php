<?php
/**
 * Admin E-PIN Generator & Master Inventory Management
 * Admin can generate PINs freely for any package (single or bulk batch)
 * and view all PINs in the system with search and filter.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pin_engine.php';

requireAdmin();
$adminUser = getCurrentUser();
$pdo = getDBConnection();

$error = '';
$success = '';

// Handle Admin PIN Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_pin') {
    $packageId = (int)($_POST['package_id'] ?? 0);
    $quantity = max(1, min(100, (int)($_POST['quantity'] ?? 1)));

    $result = generatePinsByAdmin($pdo, (int)$adminUser['id'], $packageId, $quantity);
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Fetch active packages
$packages = $pdo->query("SELECT id, package_name, price, type FROM packages WHERE is_active = 1 ORDER BY price ASC")->fetchAll();

// Filter parameters
$statusFilter = trim($_GET['status'] ?? '');
$searchPin = trim($_GET['search'] ?? '');

$sql = "
    SELECT ep.id, ep.pin_code, ep.status, ep.created_at, ep.used_at,
           p.package_name, p.price, p.type AS package_type,
           gen.username AS gen_username, gen.full_name AS gen_name, gen.role AS gen_role,
           used.username AS used_username, used.full_name AS used_name
    FROM e_pins ep
    JOIN packages p ON ep.package_id = p.id
    JOIN users gen ON ep.generated_by = gen.id
    LEFT JOIN users used ON ep.used_by = used.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND ep.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchPin)) {
    $sql .= " AND (ep.pin_code LIKE ? OR used.username LIKE ? OR gen.username LIKE ?)";
    $like = "%{$searchPin}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY ep.id DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allPins = $stmt->fetchAll();

// Statistics
$totalPinsCount = (int)$pdo->query("SELECT COUNT(*) FROM e_pins")->fetchColumn();
$unusedPinsCount = (int)$pdo->query("SELECT COUNT(*) FROM e_pins WHERE status = 'unused'")->fetchColumn();
$usedPinsCount = (int)$pdo->query("SELECT COUNT(*) FROM e_pins WHERE status = 'used'")->fetchColumn();

$pageTitle = 'E-PIN Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8">
    <!-- Header Banner -->
    <div class="glass-panel p-6 sm:p-8 rounded-2xl relative overflow-hidden border-l-4 border-l-amber-400">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-amber-400">System Key Vault</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-1">E-PIN Generation & Inventory</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Generate registration PIN numbers for any package and audit system-wide PIN usage.
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <div class="px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400">Total PINs</span>
                    <div class="text-xl font-bold text-white"><?= $totalPinsCount ?></div>
                </div>
                <div class="px-4 py-2.5 rounded-xl bg-slate-900 border border-emerald-500/40 text-center">
                    <span class="text-[10px] uppercase font-bold text-emerald-400">Active / Unused</span>
                    <div class="text-xl font-bold text-emerald-300"><?= $unusedPinsCount ?></div>
                </div>
                <div class="px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400">Redeemed</span>
                    <div class="text-xl font-bold text-cyan-300"><?= $usedPinsCount ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-xl bg-rose-950/70 border border-rose-500/40 text-rose-300 text-sm flex items-center space-x-2">
            <i class="fa-solid fa-circle-exclamation text-rose-400"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="p-4 rounded-xl bg-emerald-950/70 border border-emerald-500/40 text-emerald-300 text-sm flex items-center space-x-2">
            <i class="fa-solid fa-circle-check text-emerald-400"></i>
            <span><?= e($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- Admin Generate PIN Form -->
    <div class="glass-panel p-6 rounded-2xl">
        <h2 class="text-lg font-bold text-white flex items-center mb-4">
            <i class="fa-solid fa-key text-amber-400 mr-2"></i> Administrative PIN Minting
        </h2>
        <form method="POST" action="/admin/pins.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <input type="hidden" name="action" value="generate_pin">
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Target Package</label>
                <select name="package_id" required class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:border-amber-400 focus:outline-none">
                    <?php foreach ($packages as $pkg): ?>
                        <option value="<?= $pkg['id'] ?>">
                            <?= e($pkg['package_name']) ?> — ₹<?= number_format((float)$pkg['price'], 2) ?> (<?= strtoupper($pkg['type']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">Quantity (1 - 100)</label>
                <input type="number" name="quantity" min="1" max="100" value="1" required
                       class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:border-amber-400 focus:outline-none">
            </div>
            <div>
                <button type="submit" class="w-full py-2.5 px-4 rounded-xl font-bold text-slate-950 glow-gold-btn transition shadow-md flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-bolt"></i>
                    <span>Generate PIN(s)</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Filter & Table -->
    <div class="glass-panel rounded-2xl overflow-hidden">
        <div class="p-6 border-b border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <h2 class="text-lg font-bold text-white flex items-center">
                <i class="fa-solid fa-list-check text-cyan-400 mr-2"></i> Master E-PIN Inventory
            </h2>

            <form method="GET" action="/admin/pins.php" class="flex flex-wrap items-center gap-2">
                <input type="text" name="search" value="<?= e($searchPin) ?>" placeholder="Search PIN or username..."
                       class="px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs focus:border-cyan-400 focus:outline-none">
                <select name="status" class="px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs focus:border-cyan-400 focus:outline-none">
                    <option value="">All Statuses</option>
                    <option value="unused" <?= $statusFilter === 'unused' ? 'selected' : '' ?>>Unused</option>
                    <option value="used" <?= $statusFilter === 'used' ? 'selected' : '' ?>>Used</option>
                </select>
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 text-xs font-bold hover:bg-cyan-500/30">
                    Filter
                </button>
                <?php if (!empty($statusFilter) || !empty($searchPin)): ?>
                    <a href="/admin/pins.php" class="px-2 py-1.5 text-xs text-slate-400 hover:text-slate-200">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400 bg-slate-900/60">
                        <th class="py-3 px-4">PIN Code</th>
                        <th class="py-3 px-4">Package</th>
                        <th class="py-3 px-4">Value</th>
                        <th class="py-3 px-4">Generated By</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Redeemed By</th>
                        <th class="py-3 px-4">Created Date</th>
                        <th class="py-3 px-4 text-right">Quick Copy</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($allPins)): ?>
                        <tr>
                            <td colspan="8" class="py-10 text-center text-slate-500">
                                No E-PIN records match the criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allPins as $p): ?>
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4 font-mono font-bold text-amber-300 select-all">
                                    <?= e($p['pin_code']) ?>
                                </td>
                                <td class="py-3 px-4 font-medium text-white">
                                    <?= e($p['package_name']) ?>
                                    <span class="text-[10px] text-slate-400 block"><?= strtoupper($p['package_type']) ?></span>
                                </td>
                                <td class="py-3 px-4 text-emerald-400 font-semibold">
                                    ₹<?= number_format((float)$p['price'], 2) ?>
                                </td>
                                <td class="py-3 px-4 text-xs text-slate-300">
                                    <span class="font-semibold text-slate-200"><?= e($p['gen_name']) ?></span>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-slate-800 text-slate-400 border border-slate-700">
                                        <?= strtoupper($p['gen_role']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($p['status'] === 'unused'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                            Unused / Active
                                        </span>
                                    <?php elseif ($p['status'] === 'used'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-700/60 text-slate-400 border border-slate-600">
                                            Redeemed
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                            <?= ucfirst($p['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-xs">
                                    <?php if (!empty($p['used_username'])): ?>
                                        <span class="text-cyan-300 font-semibold"><?= e($p['used_name']) ?></span>
                                        <div class="text-[10px] text-slate-400">@<?= e($p['used_username']) ?> (<?= date('d M Y', strtotime($p['used_at'])) ?>)</div>
                                    <?php else: ?>
                                        <span class="text-slate-500 italic">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-xs text-slate-400">
                                    <?= date('d M Y, H:i', strtotime($p['created_at'])) ?>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <button onclick="copyPin('<?= e($p['pin_code']) ?>', this)" class="px-3 py-1 rounded-lg text-xs bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-200 transition">
                                        <i class="fa-regular fa-copy mr-1"></i> Copy
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function copyPin(code, btn) {
    navigator.clipboard.writeText(code).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check text-emerald-400 mr-1"></i> Copied!';
        setTimeout(() => { btn.innerHTML = orig; }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
