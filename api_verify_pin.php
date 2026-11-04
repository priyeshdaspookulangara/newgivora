<?php
/**
 * API Endpoint: Validate PIN Code
 * Returns package details if PIN is valid and unused
 */
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/pin_engine.php';

$pdo = getDBConnection();

$pinCode = trim($_GET['pin'] ?? $_POST['pin'] ?? '');

if (empty($pinCode)) {
    echo json_encode(['valid' => false, 'message' => 'Please provide a PIN number.']);
    exit;
}

try {
    $result = verifyPin($pdo, $pinCode);
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['valid' => false, 'message' => 'Error verifying PIN: ' . $e->getMessage()]);
}
