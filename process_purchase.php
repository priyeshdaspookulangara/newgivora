<?php
/**
 * Backend logic handling order creation and multi-level commission distribution calculations.
 * Supports AJAX POST or direct POST redirection.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mlm_engine.php';

requireAuth();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$currentUser = getCurrentUser();
$buyerId = (int)$currentUser['id'];

// Package ID from POST
$packageId = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
if ($packageId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid package selected.']);
    exit;
}

$pdo = getDBConnection();

try {
    // 1. Fetch Package
    $pkgStmt = $pdo->prepare('SELECT id, package_name, price, type, is_active FROM packages WHERE id = :id LIMIT 1');
    $pkgStmt->execute(['id' => $packageId]);
    $package = $pkgStmt->fetch();

    if (!$package || !$package['is_active']) {
        echo json_encode(['success' => false, 'error' => 'The requested package is inactive or not found.']);
        exit;
    }

    $price = (float)$package['price'];

    // 2. Start Transaction for atomic order creation and commission dispersal
    $pdo->beginTransaction();

    // Insert purchase record (simulated instant checkout)
    $purchaseStmt = $pdo->prepare('
        INSERT INTO user_purchases (user_id, package_id, amount_paid, payment_status, purchased_at)
        VALUES (:uid, :pid, :amount, "completed", NOW())
    ');
    $purchaseStmt->execute([
        'uid'    => $buyerId,
        'pid'    => $package['id'],
        'amount' => $price
    ]);
    $purchaseId = (int)$pdo->lastInsertId();

    // Record buyer purchase transaction in ledger
    $txStmt = $pdo->prepare('
        INSERT INTO transactions (user_id, sender_id, amount, type, level_depth, created_at)
        VALUES (:uid, NULL, :amount, "purchase", NULL, NOW())
    ');
    $txStmt->execute([
        'uid'    => $buyerId,
        'amount' => -$price
    ]);

    // 3. Trigger automated MLM Level Commission distribution
    $distributionResult = MLMEngine::distributeCommissions($buyerId, $package['id'], $price);

    $pdo->commit();

    echo json_encode([
        'success'           => true,
        'message'           => "Order #{$purchaseId} successfully placed for {$package['package_name']}!",
        'purchase_id'       => $purchaseId,
        'package_name'      => $package['package_name'],
        'amount_paid'       => $price,
        'total_distributed' => $distributionResult['total_distributed'],
        'recipients_count'  => count($distributionResult['distributions']),
        'distributions'     => $distributionResult['distributions']
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Purchase Processing Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to process purchase: ' . $e->getMessage()
    ]);
    exit;
}
