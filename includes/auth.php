<?php
/**
 * Authentication and Session Management Helper
 */

require_once __DIR__ . '/../config.php';

/**
 * Check if a user is currently authenticated
 * @return bool
 */
function isAuthenticated(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get the currently logged-in user record
 * @return array|null
 */
function getCurrentUser(): ?array {
    if (!isAuthenticated()) {
        return null;
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT id, username, plain_password, sponsor_id, role, full_name, mobile, email, address, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/**
 * Require authentication. Redirect to login if not logged in.
 */
function requireAuth(): void {
    if (!isAuthenticated()) {
        $_SESSION['flash_error'] = 'Please log in to access this page.';
        header('Location: /login.php');
        exit;
    }
}

/**
 * Require admin role. Redirect if not admin.
 */
function requireAdmin(): void {
    requireAuth();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        $_SESSION['flash_error'] = 'Access denied. Administrative privileges required.';
        header('Location: /member/dashboard.php');
        exit;
    }
}

/**
 * Require member role. Admins are also permitted or can view member pages.
 */
function requireMember(): void {
    requireAuth();
}

/**
 * Authenticate credentials.
 * NOTE: As explicitly requested for development/testing purposes, passwords are NOT encrypted (plain text).
 *
 * @param string $username
 * @param string $password
 * @return array|false User record on success, false on failure
 */
function authenticateUser(string $username, string $password) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT id, username, plain_password, sponsor_id, role, full_name, mobile, email, address FROM users WHERE username = :u LIMIT 1');
    $stmt->execute(['u' => trim($username)]);
    $user = $stmt->fetch();

    if ($user && $user['plain_password'] === $password) {
        // Prevent Session Fixation: Regenerate session ID upon successful authentication if session is active
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        return $user;
    }

    return false;
}

/**
 * Destroy current session and log out
 */
function logoutUser(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }
}

/**
 * Ensure user has a wallet record. If not, creates one.
 * @param int $userId
 * @return array
 */
function getOrCreateWallet(int $userId): array {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT * FROM wallets WHERE user_id = :uid LIMIT 1');
    $stmt->execute(['uid' => $userId]);
    $wallet = $stmt->fetch();

    if (!$wallet) {
        $insert = $pdo->prepare('INSERT INTO wallets (user_id, balance, total_earned, total_withdrawn) VALUES (:uid, 0.00, 0.00, 0.00)');
        $insert->execute(['uid' => $userId]);

        $stmt->execute(['uid' => $userId]);
        $wallet = $stmt->fetch();
    }

    return $wallet;
}

/**
 * Helper to escape output for safe HTML display
 */
function e($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
