<?php
/**
 * Member E-Commerce Store Catalog
 * Highlights the ₹5,000 Protein Powder plan with simulated instant checkout.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

requireMember();

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$pdo = getDBConnection();

// Fetch active packages
$pkgStmt = $pdo->query('SELECT * FROM packages WHERE is_active = 1 ORDER BY type = "protein_powder" DESC, price ASC');
$packages = $pkgStmt->fetchAll();

$pageTitle = 'E-Commerce Store & Product Plans';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="w-full space-y-8">

    <!-- Hero / Store Header -->
    <div class="glass-panel rounded-2xl p-6 sm:p-8 relative overflow-hidden border-b-2 border-amber-500/40">
        <div class="max-w-3xl">
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-500/10 text-amber-400 border border-amber-500/30">
                Official E-Commerce Catalog
            </span>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-white mt-3">
                Wellness Products & <span class="bg-gradient-to-r from-amber-400 to-cyan-400 bg-clip-text text-transparent">Multi-Level Packages</span>
            </h1>
            <p class="text-sm text-slate-300 mt-2">
                Order our flagship ₹5,000 Whey Isolate Protein Powder Plan or Essential Utility subscriptions.
                Purchasing instantly unlocks multi-level upline commission distributions into digital wallets.
            </p>
        </div>
    </div>

    <!-- Alert / Response Notification Area -->
    <div id="checkoutFeedback" class="hidden"></div>

    <!-- Products Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php foreach ($packages as $pkg):
            $isProtein = ($pkg['type'] === 'protein_powder');
        ?>
            <div class="<?= $isProtein ? 'glass-panel-gold ring-1 ring-amber-500/40' : 'glass-panel' ?> rounded-2xl p-6 flex flex-col justify-between relative transition duration-300 hover:-translate-y-1">
                <?php if ($isProtein): ?>
                    <div class="absolute -top-3 right-4 px-3 py-0.5 rounded-full bg-gradient-to-r from-amber-500 to-amber-600 text-[10px] font-black uppercase tracking-wider text-slate-950 shadow-lg">
                        TOP EARNER 7-LEVEL
                    </div>
                <?php endif; ?>

                <div>
                    <!-- Product Graphic Placeholder -->
                    <div class="w-full h-40 rounded-xl mb-4 flex items-center justify-center <?= $isProtein ? 'bg-gradient-to-tr from-amber-500/20 to-amber-900/30 text-amber-400 border border-amber-500/30' : 'bg-slate-900/70 text-cyan-400 border border-slate-800' ?>">
                        <?php if ($isProtein): ?>
                            <div class="text-center">
                                <i class="fa-solid fa-bottle-water text-5xl mb-2"></i>
                                <div class="text-[11px] font-bold uppercase tracking-wider text-amber-300">Whey Isolate Fuel</div>
                            </div>
                        <?php elseif ($pkg['type'] === 'utility'): ?>
                            <i class="fa-solid fa-bolt-lightning text-5xl"></i>
                        <?php elseif ($pkg['type'] === 'welfare'): ?>
                            <i class="fa-solid fa-hand-holding-heart text-5xl"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-shield-halved text-5xl"></i>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[11px] font-bold uppercase px-2 py-0.5 rounded <?= $isProtein ? 'bg-amber-400/10 text-amber-300' : 'bg-cyan-400/10 text-cyan-300' ?>">
                            <?= e(str_replace('_', ' ', $pkg['type'])) ?>
                        </span>
                    </div>

                    <h3 class="text-lg font-bold text-white"><?= e($pkg['package_name']) ?></h3>
                    <p class="text-xs text-slate-400 mt-2 line-clamp-3 leading-relaxed">
                        <?= e($pkg['description']) ?>
                    </p>

                    <!-- Commission Structure Breakdown Preview -->
                    <div class="mt-4 p-3 bg-slate-900/70 rounded-xl border border-slate-800 text-[11px] space-y-1">
                        <div class="font-semibold text-slate-300 flex items-center justify-between border-b border-slate-800 pb-1">
                            <span>Compensation Plan:</span>
                            <span class="<?= $isProtein ? 'text-amber-400' : 'text-cyan-400' ?>">
                                <?= $isProtein ? '7-Level Fixed' : '6-Level (25%)' ?>
                            </span>
                        </div>
                        <?php if ($isProtein): ?>
                            <div class="text-amber-300/90 font-medium">L1: ₹1,000 | L2-3: ₹200 | L4-6: ₹150 | L7: ₹100</div>
                        <?php else: ?>
                            <div class="text-slate-400">L1: 10% | L2: 5% | L3: 4% | L4: 3% | L5: 2% | L6: 1%</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-800">
                    <div class="flex items-baseline justify-between mb-3">
                        <span class="text-xs text-slate-400">Product Price:</span>
                        <span class="text-2xl font-black font-mono <?= $isProtein ? 'text-amber-400' : 'text-white' ?>">
                            <?= APP_CURRENCY ?><?= number_format((float)$pkg['price'], 2) ?>
                        </span>
                    </div>

                    <button type="button"
                            onclick="executeCheckout(<?= (int)$pkg['id'] ?>, '<?= e(addslashes($pkg['package_name'])) ?>', <?= (float)$pkg['price'] ?>)"
                            class="w-full py-2.5 px-4 rounded-xl font-bold text-slate-950 <?= $isProtein ? 'glow-gold-btn' : 'glow-cyan-btn' ?> text-xs flex items-center justify-center gap-2 shadow-lg">
                        <i class="fa-solid fa-bag-shopping"></i> Instant Simulated Checkout
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Confirmation / Feedback Script -->
<script>
async function executeCheckout(packageId, packageName, price) {
    if (!confirm(`Confirm purchase of "${packageName}" for ₹${price}? This will immediately process the simulated order and distribute multi-level commissions to your upline.`)) {
        return;
    }

    const feedback = document.getElementById('checkoutFeedback');
    feedback.className = 'glass-panel rounded-2xl p-4 border border-cyan-500/40 text-cyan-200 text-sm flex items-center gap-3';
    feedback.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xl text-cyan-400"></i> Processing purchase and calculating upline ledger commissions...';
    feedback.classList.remove('hidden');

    try {
        const formData = new FormData();
        formData.append('package_id', packageId);

        const response = await fetch('/process_purchase.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            let commSummary = '';
            if (data.distributions && data.distributions.length > 0) {
                commSummary = '<div class="mt-2 text-xs border-t border-emerald-500/30 pt-2"><strong class="text-amber-300">Automated Commissions Disbursed:</strong><ul class="list-disc list-inside mt-1 space-y-0.5">';
                data.distributions.forEach(d => {
                    commSummary += `<li>Level ${d.level} to <span class="font-mono text-cyan-200">@${d.recipient_username}</span>: ₹${parseFloat(d.amount).toFixed(2)}</li>`;
                });
                commSummary += `</ul><div class="mt-1 font-bold text-emerald-300">Total Commissions Credited: ₹${parseFloat(data.total_distributed).toFixed(2)}</div></div>`;
            } else {
                commSummary = '<div class="mt-1 text-xs text-slate-400">No active upline sponsors were found for this buyer. System retained balance.</div>';
            }

            feedback.className = 'glass-panel rounded-2xl p-6 border border-emerald-500/40 text-emerald-200 text-sm shadow-xl';
            feedback.innerHTML = `
                <div class="flex items-start gap-3">
                    <i class="fa-solid fa-circle-check text-2xl text-emerald-400 mt-0.5"></i>
                    <div class="flex-grow">
                        <div class="font-bold text-white text-base">${data.message}</div>
                        <div class="text-xs text-emerald-300 mt-1">Order ID #${data.purchase_id} marked as COMPLETED.</div>
                        ${commSummary}
                        <div class="mt-4 flex gap-3">
                            <a href="/member/wallet.php" class="px-3 py-1.5 rounded-lg bg-emerald-500 text-slate-950 font-bold text-xs hover:bg-emerald-400 transition">View Digital Wallet</a>
                            <a href="/member/dashboard.php" class="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-200 font-semibold text-xs hover:bg-slate-700 transition">Go to Dashboard</a>
                        </div>
                    </div>
                </div>
            `;
        } else {
            feedback.className = 'glass-panel rounded-2xl p-4 border border-rose-500/40 text-rose-200 text-sm flex items-center gap-3';
            feedback.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-xl text-rose-400"></i> Purchase Failed: ${data.error || 'Unknown error occurred.'}`;
        }
    } catch (err) {
        feedback.className = 'glass-panel rounded-2xl p-4 border border-rose-500/40 text-rose-200 text-sm flex items-center gap-3';
        feedback.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-xl text-rose-400"></i> Network or server exception: ${err.message}`;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
