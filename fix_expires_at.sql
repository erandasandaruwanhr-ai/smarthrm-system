-- Fix for expires_at column MySQL compatibility issue
-- This script ensures compatibility with different MySQL versions

-- Fix notifications table expires_at column
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NOT NULL,
    type ENUM('employee', 'security', 'meal', 'transport', 'grievance', 'admin', 'system') NOT NULL,
    action_needed TINYINT(1) NOT NULL DEFAULT 0,
    module_link VARCHAR(255) DEFAULT '#',
    target_users TEXT,
    target_roles TEXT,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    expires_at DATETIME NULL DEFAULT NULL,
    INDEX idx_type (type),
    INDEX idx_created_at (created_at),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alternative fix: If table already exists, modify the column
-- ALTER TABLE notifications MODIFY COLUMN expires_at DATETIME NULL DEFAULT NULL;

-- For older MySQL versions, use DATETIME instead of TIMESTAMP for nullable expires_at
-- This avoids the "Invalid default value" error in strict mode