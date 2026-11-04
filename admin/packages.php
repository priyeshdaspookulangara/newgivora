<?php
/**
 * Administrative Package & Plan Management
 * Add, edit, or toggle packages (Utility, Welfare, Insurance, Protein Powder).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getDBConnection();
$message = '';
$error = '';

// Toggle package status
if (isset($_GET['toggle_id'])) {
    $toggleId = (int)$_GET['toggle_id'];
    $stmt = $pdo->prepare('UPDATE packages SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
    $stmt->execute(['id' => $toggleId]);
    $_SESSION['flash_success'] = "Package #{$toggleId} status toggled.";
    header('Location: /admin/packages.php');
    exit;
}

// Add or edit package
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = trim($_POST['package_name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $type = $_POST['type'] ?? 'utility';
    $description = trim($_POST['description'] ?? '');

    if (empty($name) || $price <= 0) {
        $error = 'Valid Package Name and Price greater than 0 are required.';
    } elseif (!in_array($type, ['utility', 'welfare', 'insurance', 'protein_powder'])) {
        $error = 'Invalid package type selected.';
    } else {
        try {
            if ($action === 'create') {
                $ins = $pdo->prepare('
                    INSERT INTO packages (package_name, price, type, description, is_active, created_at)
                    VALUES (:n, :p, :t, :d, 1, NOW())
                ');
                $ins->execute([
                    'n' => $name,
                    'p' => $price,
                    't' => $type,
                    'd' => $description
                ]);
                $message = "Package '{$name}' created successfully!";
            } elseif ($action === 'update') {
                $pkgId = (int)$_POST['package_id'];
                $upd = $pdo->prepare('
                    UPDATE packages
                    SET package_name = :n, price = :p, type = :t, description = :d
                    WHERE id = :id
                ');
                $upd->execute([
                    'n'  => $name,
                    'p'  => $price,
                    't'  => $type,
                    'd'  => $description,
                    'id' => $pkgId
                ]);
                $message = "Package #{$pkgId} updated successfully!";
            }
        } catch (Exception $e) {
            $error = 'Package operation failed: ' . $e->getMessage();
        }
    }
}

// Fetch all packages
$packages = $pdo->query('
    SELECT p.*,
           (SELECT COUNT(*) FROM user_purchases WHERE package_id = p.id AND payment_status = "completed") as orders_count,
           (SELECT COALESCE(SUM(amount_paid), 0) FROM user_purchases WHERE package_id = p.id AND payment_status = "completed") as total_revenue
    FROM packages p
    ORDER BY p.id ASC
')->fetchAll();

$pageTitle = 'Package & Plan Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <div class="glass-panel rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 border-l-4 border-l-amber-400">
        <div>
            <span class="text-xs uppercase tracking-wider text-amber-400 font-bold mb-1">Catalog Architecture</span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white">Product & MLM Package Catalog</h1>
            <p class="text-sm text-slate-400 mt-1">
                Configure plan pricing, active statuses, and MLM compensation classifications.
            </p>
        </div>

        <div>
            <button onclick="openCreateModal()" class="px-4 py-2.5 rounded-xl font-bold text-slate-950 glow-gold-btn text-xs transition flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> Add New Package
            </button>
        </div>
    </div>

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

    <div class="glass-panel rounded-2xl p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="text-slate-400 border-b border-slate-800 font-semibold uppercase">
                    <tr>
                        <th class="py-2.5 px-3">ID</th>
                        <th class="py-2.5 px-3">Package Details</th>
                        <th class="py-2.5 px-3">Plan Type & MLM Rule</th>
                        <th class="py-2.5 px-3">Price</th>
                        <th class="py-2.5 px-3">Total Orders</th>
                        <th class="py-2.5 px-3">Total Revenue</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    <?php foreach ($packages as $pkg): ?>
                        <tr class="hover:bg-slate-800/30">
                            <td class="py-3 px-3 font-mono text-cyan-400 font-bold">#<?= $pkg['id'] ?></td>
                            <td class="py-3 px-3">
                                <div class="font-bold text-white"><?= e($pkg['package_name']) ?></div>
                                <div class="text-[11px] text-slate-400 line-clamp-1 max-w-sm"><?= e($pkg['description']) ?></div>
                            </td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $pkg['type'] === 'protein_powder' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20' ?>">
                                    <?= e($pkg['type']) ?>
                                </span>
                                <div class="text-[10px] text-slate-400 mt-0.5">
                                    <?= $pkg['type'] === 'protein_powder' ? '7-Level Fixed Structure' : '6-Level 25% Payout' ?>
                                </div>
                            </td>
                            <td class="py-3 px-3 font-mono font-bold text-white text-sm">
                                <?= APP_CURRENCY ?><?= number_format((float)$pkg['price'], 2) ?>
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-300">
                                <?= (int)$pkg['orders_count'] ?> sales
                            </td>
                            <td class="py-3 px-3 font-mono font-semibold text-emerald-400">
                                <?= APP_CURRENCY ?><?= number_format((float)$pkg['total_revenue'], 2) ?>
                            </td>
                            <td class="py-3 px-3">
                                <?php if ($pkg['is_active']): ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">Active</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-800 text-slate-500 border border-slate-700">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-right space-x-2 whitespace-nowrap">
                                <a href="/admin/packages.php?toggle_id=<?= $pkg['id'] ?>" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 font-semibold text-[11px] transition">
                                    <?= $pkg['is_active'] ? 'Disable' : 'Enable' ?>
                                </a>
                                <button type="button" onclick="openEditPackageModal(<?= htmlspecialchars(json_encode($pkg), ENT_QUOTES, 'UTF-8') ?>)" class="px-2.5 py-1 rounded bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/30 font-semibold text-[11px] transition">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Package Modal (Add / Edit) -->
<div id="packageModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="glass-panel rounded-2xl max-w-md w-full p-6 border border-amber-500/40 relative shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <h3 id="modalTitle" class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-cubes text-amber-400"></i> Configure Package
            </h3>
            <button onclick="closePackageModal()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <form method="POST" action="/admin/packages.php" class="space-y-4 mt-4">
            <input type="hidden" name="action" id="pkgAction" value="create">
            <input type="hidden" name="package_id" id="pkgId">

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Package Name *</label>
                <input type="text" name="package_name" id="pkgName" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Price (<?= APP_CURRENCY ?>) *</label>
                    <input type="number" step="0.01" min="1" name="price" id="pkgPrice" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Compensation Plan Type</label>
                    <select name="type" id="pkgType" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                        <option value="protein_powder">Protein Powder (7-Level Fixed)</option>
                        <option value="utility">Utility (6-Level 25%)</option>
                        <option value="welfare">Welfare (6-Level 25%)</option>
                        <option value="insurance">Insurance (6-Level 25%)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Package Description</label>
                <textarea name="description" id="pkgDesc" rows="3" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
                <button type="button" onclick="closePackageModal()" class="px-4 py-2 rounded-lg border border-slate-700 text-slate-300 text-xs">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-lg font-bold text-slate-950 glow-gold-btn text-xs">Save Package</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-plus text-amber-400"></i> Add New Package';
    document.getElementById('pkgAction').value = 'create';
    document.getElementById('pkgId').value = '';
    document.getElementById('pkgName').value = '';
    document.getElementById('pkgPrice').value = '';
    document.getElementById('pkgType').value = 'utility';
    document.getElementById('pkgDesc').value = '';
    document.getElementById('packageModal').classList.remove('hidden');
}

function openEditPackageModal(pkg) {
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-cubes text-cyan-400"></i> Edit Package #' + pkg.id;
    document.getElementById('pkgAction').value = 'update';
    document.getElementById('pkgId').value = pkg.id;
    document.getElementById('pkgName').value = pkg.package_name;
    document.getElementById('pkgPrice').value = pkg.price;
    document.getElementById('pkgType').value = pkg.type;
    document.getElementById('pkgDesc').value = pkg.description || '';
    document.getElementById('packageModal').classList.remove('hidden');
}

function closePackageModal() {
    document.getElementById('packageModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
