<?php
/**
 * Global Configuration and Database Connection Setup
 * Platform: MLM Enterprise Platform
 * Uses PDO with prepared statements for all queries.
 */

// Error reporting settings
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Database credentials
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'mlm_db');
define('DB_USER', getenv('DB_USER') ?: 'mlm_user');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'mlm_pass123');
define('DB_CHARSET', 'utf8mb4');

// Application constants
define('APP_NAME', 'ApexMLM Enterprise');
define('APP_CURRENCY', '₹');

// Auto start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie parameters
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

/**
 * Returns a singleton PDO instance with strict error handling and prepared statements
 * @return PDO
 */
function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log real message and show friendly error
            error_log('Database Connection Error: ' . $e->getMessage());
            die(json_encode([
                'success' => false,
                'error'   => 'Database connection error. Please ensure database server is running and configured.'
            ]));
        }
    }

    return $pdo;
}

/**
 * Returns a MySQLi connection if needed (as specified: PHP with PDO and MySQLi)
 * @return mysqli
 */
function getMySQLiConnection(): mysqli {
    static $mysqli = null;

    if ($mysqli === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
            $mysqli->set_charset(DB_CHARSET);
        } catch (mysqli_sql_exception $e) {
            error_log('MySQLi Connection Error: ' . $e->getMessage());
            die('Database connection failure.');
        }
    }

    return $mysqli;
}
