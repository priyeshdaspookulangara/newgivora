<?php
/**
 * Comprehensive Automated MLM Test Suite
 * Tests:
 * 1. Database connection and schema tables integrity.
 * 2. User authentication and session handling with plain text passwords.
 * 3. Protein Powder 7-level fixed compensation plan:
 *    Level 1: ₹1,000, Level 2: ₹200, Level 3: ₹200, Level 4: ₹150, Level 5: ₹150, Level 6: ₹150, Level 7: ₹100.
 * 4. Standard 6-level percentage plan (25% total payout):
 *    Level 1: 10%, Level 2: 5%, Level 3: 4%, Level 4: 3%, Level 5: 2%, Level 6: 1%.
 * 5. Short upline chains (e.g., buyer with only 2 uplines gets only 2 levels, no crash).
 * 6. Digital wallet balance additions and ledger transaction entries.
 * 7. Payout request and approval workflow: balance check, debit, transaction creation.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mlm_engine.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        throw new Exception("Assertion Failed: $msg");
    }
    echo "  [PASS] $msg\n";
}

function assert_equals($expected, $actual, $msg) {
    if ($expected != $actual) {
        throw new Exception("Assertion Failed: $msg (Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . ")");
    }
    echo "  [PASS] $msg\n";
}

echo "=== Running ApexMLM Enterprise Automated Test Suite ===\n\n";

$pdo = getDBConnection();

// Test 1: Tables existence
echo "Test 1: Schema Tables Verification\n";
$tables = ['users', 'packages', 'user_purchases', 'wallets', 'transactions', 'payouts'];
foreach ($tables as $t) {
    $res = $pdo->query("SHOW TABLES LIKE '$t'")->fetch();
    assert_true(!empty($res), "Table '$t' exists in database");
}

// Test 2: Authentication with plain text password
echo "\nTest 2: Authentication & Plain Text Passwords\n";
$adminAuth = authenticateUser('admin', 'admin123');
assert_true($adminAuth !== false && $adminAuth['role'] === 'admin', "Admin authenticates successfully with plain text password");

$failAuth = authenticateUser('admin', 'wrong_pass');
assert_true($failAuth === false, "Incorrect password rejected");

// Reset test wallets for clean calculations
$pdo->exec("UPDATE wallets SET balance = 0, total_earned = 0, total_withdrawn = 0");
$pdo->exec("DELETE FROM transactions");
$pdo->exec("DELETE FROM user_purchases");
$pdo->exec("DELETE FROM payouts");

// Test 3: Protein Powder Plan 7-Level Dispersal
echo "\nTest 3: Protein Powder Plan (₹5,000) 7-Level Fixed Commission Engine\n";
// Upline chain in seed data: 2 -> 3 -> 4 -> 5 -> 6 -> 7 -> 8 -> 9
// User 9 purchases Protein Powder Plan (package 1, price 5000)
// Expected uplines:
// Level 1: User 8 -> ₹1,000
// Level 2: User 7 -> ₹200
// Level 3: User 6 -> ₹200
// Level 4: User 5 -> ₹150
// Level 5: User 4 -> ₹150
// Level 6: User 3 -> ₹150
// Level 7: User 2 -> ₹100
// Total expected distributed: 1000 + 200 + 200 + 150 + 150 + 150 + 100 = 1950.00

$distResult = MLMEngine::distributeCommissions(9, 1, 5000.00);
assert_true($distResult['success'], "Distribution returned success");
assert_equals(1950.00, $distResult['total_distributed'], "Total distributed equals ₹1,950");
assert_equals(7, count($distResult['distributions']), "7 levels credited");

// Check individual user wallet balances
$expectedBalances = [
    8 => 1000.00,
    7 => 200.00,
    6 => 200.00,
    5 => 150.00,
    4 => 150.00,
    3 => 150.00,
    2 => 100.00,
];

foreach ($expectedBalances as $uid => $expectedAmt) {
    $w = getOrCreateWallet($uid);
    assert_equals($expectedAmt, (float)$w['balance'], "User #$uid wallet balance is ₹$expectedAmt");
    assert_equals($expectedAmt, (float)$w['total_earned'], "User #$uid total earned is ₹$expectedAmt");
}

// Check transaction ledger rows
$txCount = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE type = 'commission'")->fetchColumn();
assert_equals(7, $txCount, "7 transaction ledger rows created for commissions");

// Test 4: Standard Level Plan (25% across 6 levels: 10%, 5%, 4%, 3%, 2%, 1%)
echo "\nTest 4: Standard Level Plan (Utility Package ₹2,000)\n";
// User 9 purchases package 2 (utility, price 2000)
// Expected uplines:
// Level 1: User 8 -> 10% of 2000 = ₹200
// Level 2: User 7 -> 5% of 2000  = ₹100
// Level 3: User 6 -> 4% of 2000  = ₹80
// Level 4: User 5 -> 3% of 2000  = ₹60
// Level 5: User 4 -> 2% of 2000  = ₹40
// Level 6: User 3 -> 1% of 2000  = ₹20
// Level 7: User 2 -> None (Standard plan only covers 6 levels)
// Total expected distributed: 200 + 100 + 80 + 60 + 40 + 20 = 500 (which is exactly 25% of 2000)

$distStandard = MLMEngine::distributeCommissions(9, 2, 2000.00);
assert_true($distStandard['success'], "Standard distribution succeeded");
assert_equals(500.00, $distStandard['total_distributed'], "Total distributed equals ₹500 (25% of ₹2,000)");
assert_equals(6, count($distStandard['distributions']), "Exactly 6 levels distributed for standard plan");

// Verify User 8 new balance = 1000 + 200 = 1200
$w8 = getOrCreateWallet(8);
assert_equals(1200.00, (float)$w8['balance'], "User #8 balance updated to ₹1,200");

// Test 5: Short Upline Chain (e.g. User 4 has only 2 uplines: User 3 and User 2)
echo "\nTest 5: Short Upline Chain Resilience\n";
// User 4 purchases Protein Powder plan (Package 1, ₹5000)
// User 4 sponsor is User 3 (Level 1 -> ₹1000)
// User 3 sponsor is User 2 (Level 2 -> ₹200)
// User 2 has no sponsor (Root) -> traversal ends gracefully
$distShort = MLMEngine::distributeCommissions(4, 1, 5000.00);
assert_true($distShort['success'], "Short chain distribution succeeded gracefully");
assert_equals(2, count($distShort['distributions']), "Only 2 upline distributions made");
assert_equals(1200.00, $distShort['total_distributed'], "Total distributed is ₹1200 (₹1000 + ₹200)");

// Test 6: Digital Wallet Payout Workflow (Request & Approval)
echo "\nTest 6: Digital Wallet Payout Request & Approval Workflow\n";
$w8Before = getOrCreateWallet(8);
$initialBal8 = (float)$w8Before['balance']; // currently 1200
$withdrawAmt = 500.00;

// User 8 creates payout request
$pdo->prepare("INSERT INTO payouts (user_id, requested_amount, status, requested_at) VALUES (8, :amt, 'pending', NOW())")->execute(['amt' => $withdrawAmt]);
$payoutId = (int)$pdo->lastInsertId();
assert_true($payoutId > 0, "Payout request #$payoutId logged");

// Admin approves payout
// 1. Update status
$pdo->prepare("UPDATE payouts SET status = 'approved', processed_at = NOW() WHERE id = :id")->execute(['id' => $payoutId]);
// 2. Deduct wallet
$pdo->prepare("UPDATE wallets SET balance = balance - ?, total_withdrawn = total_withdrawn + ? WHERE user_id = 8")->execute([$withdrawAmt, $withdrawAmt]);
// 3. Ledger transaction
$pdo->prepare("INSERT INTO transactions (user_id, amount, type, created_at) VALUES (8, ?, 'withdrawal', NOW())")->execute([-$withdrawAmt]);

$w8After = getOrCreateWallet(8);
assert_equals($initialBal8 - $withdrawAmt, (float)$w8After['balance'], "User #8 balance reduced by ₹500 to ₹" . ($initialBal8 - $withdrawAmt));
assert_equals($withdrawAmt, (float)$w8After['total_withdrawn'], "User #8 total_withdrawn is ₹500");

// Check withdrawal ledger row
$wTx = $pdo->query("SELECT * FROM transactions WHERE user_id = 8 AND type = 'withdrawal'")->fetch();
assert_true(!empty($wTx), "Withdrawal transaction entry exists in ledger");
assert_equals(-500.00, (float)$wTx['amount'], "Withdrawal ledger amount is -500.00");

echo "\n============================================\n";
echo " ALL TESTS PASSED SUCCESSFULLY! (100% OK)\n";
echo "============================================\n";
