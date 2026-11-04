<?php
/**
 * MLM Compensation & Level Commission Engine
 *
 * Rules:
 * 1. Standard Level Plan (25% Total Payout) across 6 levels for Utility/Welfare/Insurance packages:
 *    - Level 1: 10%
 *    - Level 2: 5%
 *    - Level 3: 4%
 *    - Level 4: 3%
 *    - Level 5: 2%
 *    - Level 6: 1%
 *
 * 2. Protein Powder Plan (7-Level Fixed Amount Structure) for 'protein_powder' type packages:
 *    - Level 1: ₹1,000
 *    - Level 2: ₹200
 *    - Level 3: ₹200
 *    - Level 4: ₹150
 *    - Level 5: ₹150
 *    - Level 6: ₹150
 *    - Level 7: ₹100
 *
 * Traversing uplines:
 * Traverses parent/sponsor upwards iteratively up to the max levels.
 * Credits commission to wallet balance and total_earned.
 * Records detailed transaction ledger entries.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

class MLMEngine {

    // Standard percentage breakdown for non-protein powder packages
    const STANDARD_LEVEL_RATES = [
        1 => 0.10, // 10%
        2 => 0.05, // 5%
        3 => 0.04, // 4%
        4 => 0.03, // 3%
        5 => 0.02, // 2%
        6 => 0.01  // 1%
    ];

    // Fixed commission amount breakdown for Protein Powder plan
    const PROTEIN_POWDER_FIXED_RATES = [
        1 => 1000.00,
        2 => 200.00,
        3 => 200.00,
        4 => 150.00,
        5 => 150.00,
        6 => 150.00,
        7 => 100.00
    ];

    /**
     * Traverses uplines for a user up to specified max levels
     * @param PDO $pdo
     * @param int $userId
     * @param int $maxLevels
     * @return array Array of [ 'level' => int, 'user_id' => int, 'user' => array ]
     */
    public static function getUplines(PDO $pdo, int $userId, int $maxLevels = 7): array {
        $uplines = [];
        $currentUserId = $userId;
        $visited = [$userId => true]; // Detect cycles if any

        for ($depth = 1; $depth <= $maxLevels; $depth++) {
            $stmt = $pdo->prepare('SELECT u.sponsor_id, s.id, s.username, s.full_name, s.email, s.role
                                   FROM users u
                                   LEFT JOIN users s ON u.sponsor_id = s.id
                                   WHERE u.id = :uid LIMIT 1');
            $stmt->execute(['uid' => $currentUserId]);
            $row = $stmt->fetch();

            if (!$row || empty($row['sponsor_id']) || empty($row['id'])) {
                // No sponsor found / reached root of tree
                break;
            }

            $sponsorId = (int)$row['id'];
            if (isset($visited[$sponsorId])) {
                // Prevent infinite loop on circular reference
                break;
            }
            $visited[$sponsorId] = true;

            $uplines[] = [
                'level'   => $depth,
                'user_id' => $sponsorId,
                'user'    => $row
            ];

            $currentUserId = $sponsorId;
        }

        return $uplines;
    }

    /**
     * Process multi-level commissions for a completed package purchase.
     * Must be run inside an active PDO transaction or will manage its own transaction.
     *
     * @param int $buyerId
     * @param int $packageId
     * @param float $amountPaid
     * @return array Summary of distributed commissions
     * @throws Exception
     */
    public static function distributeCommissions(int $buyerId, int $packageId, float $amountPaid): array {
        $pdo = getDBConnection();

        $manageTransaction = !$pdo->inTransaction();
        if ($manageTransaction) {
            $pdo->beginTransaction();
        }

        try {
            // Fetch package details
            $pkgStmt = $pdo->prepare('SELECT id, package_name, price, type FROM packages WHERE id = :pid LIMIT 1');
            $pkgStmt->execute(['pid' => $packageId]);
            $package = $pkgStmt->fetch();

            if (!$package) {
                throw new Exception("Invalid package selected (ID: {$packageId})");
            }

            $packageType = $package['type'];
            $isProteinPowder = ($packageType === 'protein_powder');

            // Determine level schedule
            $schedule = $isProteinPowder ? self::PROTEIN_POWDER_FIXED_RATES : self::STANDARD_LEVEL_RATES;
            $maxLevels = count($schedule);

            // Fetch uplines
            $uplines = self::getUplines($pdo, $buyerId, $maxLevels);

            $distributions = [];
            $totalDistributed = 0.00;

            // Prepare statements for wallet and transaction logging
            $updateWalletStmt = $pdo->prepare('
                INSERT INTO wallets (user_id, balance, total_earned, total_withdrawn)
                VALUES (?, ?, ?, 0.00)
                ON DUPLICATE KEY UPDATE
                    balance = balance + ?,
                    total_earned = total_earned + ?
            ');

            $insertTxStmt = $pdo->prepare('
                INSERT INTO transactions (user_id, sender_id, amount, type, level_depth, created_at)
                VALUES (:tx_uid, :sender_id, :tx_amount, "commission", :depth, NOW())
            ');

            foreach ($uplines as $upline) {
                $depth = (int)$upline['level'];
                $sponsorId = (int)$upline['user_id'];

                if (!isset($schedule[$depth])) {
                    continue;
                }

                // Calculate commission amount
                if ($isProteinPowder) {
                    $commissionAmount = (float)$schedule[$depth];
                } else {
                    $rate = (float)$schedule[$depth];
                    $commissionAmount = round($amountPaid * $rate, 2);
                }

                if ($commissionAmount <= 0) {
                    continue;
                }

                // 1. Credit wallet
                $updateWalletStmt->execute([
                    $sponsorId,
                    $commissionAmount,
                    $commissionAmount,
                    $commissionAmount,
                    $commissionAmount
                ]);

                // 2. Insert transaction entry
                $insertTxStmt->execute([
                    ':tx_uid'    => $sponsorId,
                    ':sender_id' => $buyerId,
                    ':tx_amount' => $commissionAmount,
                    ':depth'     => $depth
                ]);

                $distributions[] = [
                    'recipient_id'       => $sponsorId,
                    'recipient_username' => $upline['user']['username'] ?? "ID $sponsorId",
                    'level'              => $depth,
                    'amount'             => $commissionAmount
                ];

                $totalDistributed += $commissionAmount;
            }

            if ($manageTransaction) {
                $pdo->commit();
            }

            return [
                'success'           => true,
                'package_id'        => $packageId,
                'package_type'      => $packageType,
                'amount_paid'       => $amountPaid,
                'distributions'     => $distributions,
                'total_distributed' => $totalDistributed
            ];

        } catch (Exception $e) {
            if ($manageTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Traverses downline recursively or iteratively to build the referral network tree
     * @param PDO $pdo
     * @param int $rootUserId
     * @param int $maxDepth
     * @return array
     */
    public static function getDownlineTree(PDO $pdo, int $rootUserId, int $maxDepth = 7): array {
        $downline = [];
        $currentLevelUsers = [$rootUserId];

        for ($depth = 1; $depth <= $maxDepth; $depth++) {
            if (empty($currentLevelUsers)) {
                break;
            }

            $inPlaceholders = implode(',', array_fill(0, count($currentLevelUsers), '?'));
            $sql = "SELECT u.id, u.username, u.full_name, u.email, u.mobile, u.sponsor_id, u.created_at,
                           w.balance, w.total_earned,
                           (SELECT COUNT(*) FROM user_purchases WHERE user_id = u.id AND payment_status = 'completed') as purchase_count
                    FROM users u
                    LEFT JOIN wallets w ON u.id = w.user_id
                    WHERE u.sponsor_id IN ($inPlaceholders)
                    ORDER BY u.created_at ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($currentLevelUsers);
            $children = $stmt->fetchAll();

            if (empty($children)) {
                break;
            }

            $downline[$depth] = $children;
            $currentLevelUsers = array_column($children, 'id');
        }

        return $downline;
    }
}
