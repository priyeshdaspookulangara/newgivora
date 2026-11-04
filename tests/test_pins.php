<?php
/**
 * Automated Test Suite for E-PIN (PIN Number) Functionality
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mlm_engine.php';
require_once __DIR__ . '/../includes/pin_engine.php';

function assertTest($condition, $message) {
    if ($condition) {
        echo "  \033[32m[PASS]\033[0m {$message}\n";
    } else {
        echo "  \033[31m[FAIL]\033[0m {$message}\n";
        exit(1);
    }
}

echo "=== Running E-PIN Automated Test Suite ===\n\n";

$pdo = getDBConnection();

// 1. Admin generates PIN for ₹5,000 Protein Powder Plan
echo "Test 1: Admin PIN Generation\n";
$adminRes = generatePinsByAdmin($pdo, 1, 1, 2); // Admin ID 1, Package 1 (Protein Powder), quantity 2
assertTest($adminRes['success'] === true, "Admin successfully generated 2 PINs");
assertTest(count($adminRes['pins']) === 2, "Returned 2 PIN records");
$testPin1 = $adminRes['pins'][0]['pin_code'];
$testPin2 = $adminRes['pins'][1]['pin_code'];
assertTest(str_starts_with($testPin1, 'PIN-'), "PIN code format starts with 'PIN-'");

// 2. PIN Validation & Package Detection
echo "\nTest 2: PIN Validation & Package Detection\n";
$verifyRes1 = verifyPin($pdo, $testPin1);
assertTest($verifyRes1['valid'] === true, "PIN 1 is detected as valid");
assertTest($verifyRes1['pin']['package_id'] == 1, "Package ID detected as 1");
assertTest($verifyRes1['pin']['package_name'] === 'Protein Powder Wellness Plan', "Package name detected correctly as 'Protein Powder Wellness Plan'");
assertTest($verifyRes1['pin']['price'] == 5000.00, "Package price detected as 5000.00");
assertTest($verifyRes1['pin']['status'] === 'unused', "PIN status is 'unused'");

$fakeVerify = verifyPin($pdo, 'PIN-FAKE-0000-0000');
assertTest($fakeVerify['valid'] === false, "Bogus PIN is rejected");

// 3. User Registration with PIN & Automatic Commission Distribution
echo "\nTest 3: Registration with PIN & Automated 7-Level Commission Distribution\n";
$newUsername = 'pin_member_' . time();
$newEmail = $newUsername . '@test.mlm';

// Register user with sponsor = user 9 (level7_shopper)
// Upline tree: 2 -> 3 -> 4 -> 5 -> 6 -> 7 -> 8 -> 9 -> new_user
$insertStmt = $pdo->prepare('
    INSERT INTO users (username, plain_password, sponsor_id, role, full_name, mobile, email, address, created_at)
    VALUES (?, ?, 9, "member", "PIN Test Recruit", "9998887776", ?, "Test Address", NOW())
');
$insertStmt->execute([$newUsername, 'pass123', $newEmail]);
$newUserId = (int)$pdo->lastInsertId();

$wStmt = $pdo->prepare('INSERT INTO wallets (user_id, balance, total_earned, total_withdrawn) VALUES (?, 0.00, 0.00, 0.00)');
$wStmt->execute([$newUserId]);

// Activate user with PIN 1
$actRes = activateUserWithPin($pdo, $newUserId, $testPin1, 9);
assertTest($actRes['success'] === true, "Activation with PIN succeeded");
assertTest($actRes['amount_paid'] == 5000.00, "Purchase record amount is ₹5000");

// Check user_purchases record has pin_code saved
$pCheck = $pdo->prepare("SELECT pin_code, package_id, amount_paid, payment_status FROM user_purchases WHERE user_id = ?");
$pCheck->execute([$newUserId]);
$pRow = $pCheck->fetch();
assertTest($pRow['pin_code'] === $testPin1, "user_purchases saved pin_code successfully: {$pRow['pin_code']}");
assertTest($pRow['payment_status'] === 'completed', "Purchase status is completed");

// Check PIN is marked used
$pinCheckUsed = verifyPin($pdo, $testPin1);
assertTest($pinCheckUsed['valid'] === false, "Used PIN cannot be validated again");
assertTest(str_contains($pinCheckUsed['message'], 'already been used'), "Message clarifies PIN was already used");

// Check commission distribution to upline
// L1 upline is user 9 (₹1000)
$u9Wallet = $pdo->query("SELECT balance FROM wallets WHERE user_id = 9")->fetchColumn();
assertTest((float)$u9Wallet >= 1000.00, "Sponsor user 9 received ₹1000 commission");

// 4. Member generates PIN if wallet has sufficient balance
echo "\nTest 4: Member PIN Generation via Wallet Balance\n";
// User 9 now has at least ₹1000 balance. Let's try generating a package 2 (Utility, price ₹2000) -> should fail
$memberGenFail = generatePinByMember($pdo, 9, 2);
assertTest($memberGenFail['success'] === false, "Member cannot generate PIN when wallet balance is insufficient");
assertTest(str_contains($memberGenFail['message'], 'Insufficient wallet balance'), "Error message mentions Insufficient wallet balance");

// Give User 9 extra balance to afford ₹2000 package
$pdo->query("UPDATE wallets SET balance = balance + 2000.00 WHERE user_id = 9");
$u9BalBefore = (float)$pdo->query("SELECT balance FROM wallets WHERE user_id = 9")->fetchColumn();

$memberGenSuccess = generatePinByMember($pdo, 9, 2); // Package 2 (Utility ₹2,000)
assertTest($memberGenSuccess['success'] === true, "Member with sufficient wallet balance generates PIN successfully");
$u9BalAfter = (float)$pdo->query("SELECT balance FROM wallets WHERE user_id = 9")->fetchColumn();
assertTest(abs($u9BalBefore - $u9BalAfter - 2000.00) < 0.01, "User 9 balance accurately deducted by ₹2,000");

// Verify transaction ledger logged pin_generation
$txCheck = $pdo->prepare("SELECT amount, type FROM transactions WHERE user_id = 9 AND type = 'pin_generation' ORDER BY id DESC LIMIT 1");
$txCheck->execute();
$txRow = $txCheck->fetch();
assertTest($txRow && (float)$txRow['amount'] == -2000.00, "Ledger contains debit of -₹2,000 for pin_generation");

// Verify generated PIN is in database with generated_by = 9
$memberPinCode = $memberGenSuccess['pin_code'];
$mPinCheck = verifyPin($pdo, $memberPinCode);
assertTest($mPinCheck['valid'] === true, "Member generated PIN is valid in system");
assertTest($mPinCheck['pin']['generated_by'] == 9, "PIN generated_by belongs to Member #9");
assertTest($mPinCheck['pin']['price'] == 2000.00, "PIN package price is ₹2,000");

echo "\n============================================\n";
echo " ALL E-PIN TESTS PASSED! (100% OK)\n";
echo "============================================\n\n";
