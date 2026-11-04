-- MLM Database Schema
CREATE DATABASE IF NOT EXISTS mlm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mlm_db;

-- 1. users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    plain_password VARCHAR(255) NOT NULL,
    sponsor_id INT NULL,
    role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
    full_name VARCHAR(100) NOT NULL,
    mobile VARCHAR(20) NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    address TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_sponsor FOREIGN KEY (sponsor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. packages table
CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    type ENUM('utility', 'welfare', 'insurance', 'protein_powder') NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. user_purchases table
CREATE TABLE IF NOT EXISTS user_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'completed',
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_purchases_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_purchases_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. wallets table
CREATE TABLE IF NOT EXISTS wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    balance DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_earned DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_withdrawn DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wallets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. transactions / ledger table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender_id INT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    type ENUM('commission', 'withdrawal', 'purchase', 'pin_generation') NOT NULL,
    level_depth INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_transactions_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. payouts table
CREATE TABLE IF NOT EXISTS payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    requested_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_payouts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. e_pins table
CREATE TABLE IF NOT EXISTS e_pins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pin_code VARCHAR(50) NOT NULL UNIQUE,
    package_id INT NOT NULL,
    generated_by INT NOT NULL,
    used_by INT NULL DEFAULT NULL,
    status ENUM('unused', 'used', 'cancelled') NOT NULL DEFAULT 'unused',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_epins_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE RESTRICT,
    CONSTRAINT fk_epins_generated_by FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_epins_used_by FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update user_purchases to track pin_id or pin_code
ALTER TABLE user_purchases ADD COLUMN pin_code VARCHAR(50) NULL DEFAULT NULL AFTER package_id;

-- Seed Default Packages
INSERT INTO packages (id, package_name, price, type, description, is_active) VALUES
(1, 'Protein Powder Wellness Plan', 5000.00, 'protein_powder', 'Premium 100% Whey Isolate Nutritional Fuel with 7-Level High-Yield MLM Commission plan.', 1),
(2, 'Utility Essential Starter', 2000.00, 'utility', 'Household utility credits and recharge bundle with 6-level standard 25% distribution.', 1),
(3, 'Welfare Community Shield', 4000.00, 'welfare', 'Health and welfare mutual assistance pool subscription with 6-level standard 25% distribution.', 1),
(4, 'Term Life Insurance Shield', 10000.00, 'insurance', 'Accidental protection and term life coverage with 6-level standard 25% distribution.', 1)
ON DUPLICATE KEY UPDATE package_name=VALUES(package_name);

-- Seed Administrator Account
-- Per explicit instruction: Passwords must NOT be encrypted (plain text)
INSERT INTO users (id, username, plain_password, sponsor_id, role, full_name, mobile, email, address) VALUES
(1, 'admin', 'admin123', NULL, 'admin', 'System Administrator', '+91 9876543210', 'admin@apexwellness.mlm', 'Headquarters, Silicon Towers, Mumbai')
ON DUPLICATE KEY UPDATE username=VALUES(username);

INSERT INTO wallets (user_id, balance, total_earned, total_withdrawn) VALUES
(1, 0.00, 0.00, 0.00)
ON DUPLICATE KEY UPDATE balance=VALUES(balance);

-- Seed Default MLM Uplines for immediate testing & demonstration
-- Root Leader (User 2)
INSERT INTO users (id, username, plain_password, sponsor_id, role, full_name, mobile, email, address) VALUES
(2, 'rootleader', 'leader123', NULL, 'member', 'Vikramaditya Singhania', '+91 9811122233', 'vikram@apexwellness.mlm', 'Bandra West, Mumbai')
ON DUPLICATE KEY UPDATE username=VALUES(username);

INSERT INTO wallets (user_id, balance, total_earned, total_withdrawn) VALUES
(2, 0.00, 0.00, 0.00)
ON DUPLICATE KEY UPDATE balance=VALUES(balance);

-- Chain: 2 -> 3 -> 4 -> 5 -> 6 -> 7 -> 8 -> 9 (to test 7-level and 6-level depth)
INSERT INTO users (id, username, plain_password, sponsor_id, role, full_name, mobile, email, address) VALUES
(3, 'level1_sponsor', 'pass123', 2, 'member', 'Rajesh Sharma', '+91 9822233344', 'rajesh@apexwellness.mlm', 'Connaught Place, New Delhi'),
(4, 'level2_sponsor', 'pass123', 3, 'member', 'Priya Patel', '+91 9833344455', 'priya@apexwellness.mlm', 'Navrangpura, Ahmedabad'),
(5, 'level3_sponsor', 'pass123', 4, 'member', 'Amitabh Verma', '+91 9844455566', 'amitabh@apexwellness.mlm', 'Gomti Nagar, Lucknow'),
(6, 'level4_sponsor', 'pass123', 5, 'member', 'Sunita Reddy', '+91 9855566677', 'sunita@apexwellness.mlm', 'Banjara Hills, Hyderabad'),
(7, 'level5_sponsor', 'pass123', 6, 'member', 'Ananya Roy', '+91 9866677788', 'ananya@apexwellness.mlm', 'Salt Lake, Kolkata'),
(8, 'level6_sponsor', 'pass123', 7, 'member', 'Karan Malhotra', '+91 9877788899', 'karan@apexwellness.mlm', 'Koramangala, Bengaluru'),
(9, 'level7_shopper', 'pass123', 8, 'member', 'Devendra Joshi', '+91 9888899900', 'devendra@apexwellness.mlm', 'Shivaji Nagar, Pune')
ON DUPLICATE KEY UPDATE username=VALUES(username);

INSERT INTO wallets (user_id, balance, total_earned, total_withdrawn) VALUES
(3, 0.00, 0.00, 0.00),
(4, 0.00, 0.00, 0.00),
(5, 0.00, 0.00, 0.00),
(6, 0.00, 0.00, 0.00),
(7, 0.00, 0.00, 0.00),
(8, 0.00, 0.00, 0.00),
(9, 0.00, 0.00, 0.00)
ON DUPLICATE KEY UPDATE balance=VALUES(balance);
