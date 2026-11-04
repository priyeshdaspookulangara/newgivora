<?php
/**
 * PIN Engine - Multi-Level Marketing Platform
 * Handles PIN generation (Admin & Member with wallet deduction),
 * PIN validation, and registration activation with MLM commissions.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/mlm_engine.php';

/**
 * Generate a unique formatted PIN code
 * Format: PIN-XXXX-XXXX-XXXX
 */
function generateUniquePinCode(PDO $pdo): string {
    do {
        $part1 = strtoupper(bin2hex(random_bytes(2))); // 4 chars
        $part2 = strtoupper(bin2hex(random_bytes(2))); // 4 chars
        $part3 = strtoupper(bin2hex(random_bytes(2))); // 4 chars
        $pinCode = "PIN-{$part1}-{$part2}-{$part3}";

        $stmt = $pdo->prepare("SELECT id FROM e_pins WHERE pin_code = ? LIMIT 1");
        $stmt->execute([$pinCode]);
        $exists = $stmt->fetch();
    } while ($exists);

    return $pinCode;
}

/**
 * Verify a PIN code and return package info
 */
function verifyPin(PDO $pdo, string $pinCode): array {
    $pinCode = trim($pinCode);
    if (empty($pinCode)) {
        return ['valid' => false, 'message' => 'PIN number cannot be empty.'];
    }

    $stmt = $pdo->prepare("
        SELECT ep.id, ep.pin_code, ep.package_id, ep.generated_by, ep.status,
               p.package_name, p.price, p.type AS package_type, p.description
        FROM e_pins ep
        INNER JOIN packages p ON ep.package_id = p.id
        WHERE ep.pin_code = ?
        LIMIT 1
    ");
    $stmt->execute([$pinCode]);
    $pin = $stmt->fetch();

    if (!$pin) {
        return ['valid' => false, 'message' => 'Invalid PIN number. Not found in the system.'];
    }

    if ($pin['status'] === 'used') {
        return ['valid' => false, 'message' => 'This PIN number has already been used.'];
    }

    if ($pin['status'] !== 'unused') {
        return ['valid' => false, 'message' => 'This PIN number is not active or cancelled.'];
    }

    return [
        'valid' => true,
        'message' => 'Valid PIN number.',
        'pin' => $pin
    ];
}

/**
 * Generate PIN(s) by Admin (no wallet deduction)
 */
function generatePinsByAdmin(PDO $pdo, int $adminId, int $packageId, int $quantity = 1): array {
    $stmt = $pdo->prepare("SELECT id, package_name, price FROM packages WHERE id = ? AND is_active = 1");
    $stmt->execute([$packageId]);
    $pkg = $stmt->fetch();

    if (!$pkg) {
        return ['success' => false, 'message' => 'Invalid or inactive package selected.'];
    }

    $quantity = max(1, min(100, $quantity)); // Cap between 1 and 100
    $generated = [];

    $pdo->beginTransaction();
    try {
        $insertStmt = $pdo->prepare("
            INSERT INTO e_pins (pin_code, package_id, generated_by, status, created_at)
            VALUES (?, ?, ?, 'unused', NOW())
        ");

        for ($i = 0; $i < $quantity; $i++) {
            $code = generateUniquePinCode($pdo);
            $insertStmt->execute([$code, $packageId, $adminId]);
            $generated[] = [
                'pin_code' => $code,
                'package_id' => $packageId,
                'package_name' => $pkg['package_name'],
                'price' => (float)$pkg['price']
            ];
        }

        $pdo->commit();
        return [
            'success' => true,
            'message' => "Successfully generated {$quantity} PIN(s) for {$pkg['package_name']}.",
            'pins' => $generated
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Failed to generate PINs: ' . $e->getMessage()];
    }
}

/**
 * Generate PIN by Member (deducts wallet balance)
 */
function generatePinByMember(PDO $pdo, int $userId, int $packageId): array {
    $stmt = $pdo->prepare("SELECT id, package_name, price FROM packages WHERE id = ? AND is_active = 1");
    $stmt->execute([$packageId]);
    $pkg = $stmt->fetch();

    if (!$pkg) {
        return ['success' => false, 'message' => 'Invalid or inactive package selected.'];
    }

    $price = (float)$pkg['price'];

    $pdo->beginTransaction();
    try {
        // Lock member's wallet
        $wStmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ? FOR UPDATE");
        $wStmt->execute([$userId]);
        $wallet = $wStmt->fetch();

        if (!$wallet) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Wallet not found for this user.'];
        }

        $currentBalance = (float)$wallet['balance'];
        if ($currentBalance < $price) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Insufficient wallet balance. Required: ₹' . number_format($price, 2) . ', Available: ₹' . number_format($currentBalance, 2)
            ];
        }

        // Deduct wallet balance
        $deductStmt = $pdo->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
        $deductStmt->execute([$price, $userId]);

        // Generate unique pin
        $code = generateUniquePinCode($pdo);

        $pinStmt = $pdo->prepare("
            INSERT INTO e_pins (pin_code, package_id, generated_by, status, created_at)
            VALUES (?, ?, ?, 'unused', NOW())
        ");
        $pinStmt->execute([$code, $packageId, $userId]);

        // Record in ledger
        $ledgerStmt = $pdo->prepare("
            INSERT INTO transactions (user_id, sender_id, amount, type, level_depth, created_at)
            VALUES (?, NULL, ?, 'pin_generation', NULL, NOW())
        ");
        $ledgerStmt->execute([$userId, -$price]);

        $pdo->commit();
        return [
            'success' => true,
            'message' => "Successfully generated PIN for {$pkg['package_name']}! ₹" . number_format($price, 2) . " deducted from your wallet.",
            'pin_code' => $code,
            'package_name' => $pkg['package_name'],
            'price' => $price
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'PIN generation failed: ' . $e->getMessage()];
    }
}

/**
 * Consume a PIN during user registration:
 * 1. Mark PIN as used by newly registered user
 * 2. Record user_purchases with package and pin_code
 * 3. Trigger MLM level commission distribution to sponsor upline
 */
function activateUserWithPin(PDO $pdo, int $newUserId, string $pinCode, ?int $sponsorId): array {
    $pinResult = verifyPin($pdo, $pinCode);
    if (!$pinResult['valid']) {
        return ['success' => false, 'message' => $pinResult['message']];
    }

    $pin = $pinResult['pin'];
    $pinId = $pin['id'];
    $packageId = (int)$pin['package_id'];
    $amountPaid = (float)$pin['price'];
    $packageType = $pin['package_type'];

    // Update PIN status
    $updatePin = $pdo->prepare("
        UPDATE e_pins
        SET status = 'used', used_by = ?, used_at = NOW()
        WHERE id = ? AND status = 'unused'
    ");
    $updatePin->execute([$newUserId, $pinId]);

    if ($updatePin->rowCount() === 0) {
        return ['success' => false, 'message' => 'PIN was already consumed or is invalid.'];
    }

    // Insert user purchase record with pin_code
    $purchaseStmt = $pdo->prepare("
        INSERT INTO user_purchases (user_id, package_id, pin_code, amount_paid, payment_status, purchased_at)
        VALUES (?, ?, ?, ?, 'completed', NOW())
    ");
    $purchaseStmt->execute([$newUserId, $packageId, $pin['pin_code'], $amountPaid]);
    $purchaseId = (int)$pdo->lastInsertId();

    // Record purchase in transaction ledger
    $txStmt = $pdo->prepare("
        INSERT INTO transactions (user_id, sender_id, amount, type, level_depth, created_at)
        VALUES (?, NULL, ?, 'purchase', NULL, NOW())
    ");
    $txStmt->execute([$newUserId, -$amountPaid]);

    // Distribute MLM commissions if user has an upline sponsor
    $commissionSummary = [];
    if (!empty($sponsorId)) {
        $commissionSummary = MLMEngine::distributeCommissions($newUserId, $packageId, $amountPaid);
    }

    return [
        'success' => true,
        'purchase_id' => $purchaseId,
        'pin_code' => $pin['pin_code'],
        'package_name' => $pin['package_name'],
        'amount_paid' => $amountPaid,
        'commissions' => $commissionSummary
    ];
}
