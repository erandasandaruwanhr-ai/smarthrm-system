<?php
/**
 * Permission System Database Setup
 * Creates all necessary tables and initializes default permissions
 */

require_once '../config/config.php';
require_once '../includes/permission_config.php';

$db = new Database();
$errors = [];
$success_messages = [];

try {
    // 1. Ensure account_types table exists with proper structure
    $db->query("
        CREATE TABLE IF NOT EXISTS account_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type_name VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            level INT DEFAULT 1,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_level (level),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Add level column if it doesn't exist
    $columns = $db->fetchAll("DESCRIBE account_types");
    $column_names = array_column($columns, 'Field');

    if (!in_array('level', $column_names)) {
        $db->query("ALTER TABLE account_types ADD COLUMN level INT DEFAULT 1 AFTER description");
        $success_messages[] = "Added 'level' column to account_types table";
    }

    // Insert default account types if they don't exist
    foreach (ENHANCED_ACCOUNT_TYPES as $id => $type_info) {
        $existing = $db->fetch("SELECT id FROM account_types WHERE id = ?", [$id]);

        if (!$existing) {
            $db->query(
                "INSERT INTO account_types (id, type_name, description, level) VALUES (?, ?, ?, ?)",
                [$id, $type_info['name'], $type_info['description'], $type_info['level']]
            );
            $success_messages[] = "Created account type: {$type_info['name']}";
        } else {
            // Update existing with correct level
            $db->query(
                "UPDATE account_types SET level = ? WHERE id = ?",
                [$type_info['level'], $id]
            );
        }
    }

    // 2. Create permission_assignments table
    $db->query("
        CREATE TABLE IF NOT EXISTS permission_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_type_id INT NOT NULL,
            permission_key VARCHAR(255) NOT NULL,
            is_granted TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_assignment (account_type_id, permission_key),
            INDEX idx_account_type (account_type_id),
            INDEX idx_permission (permission_key),
            INDEX idx_granted (is_granted),
            FOREIGN KEY (account_type_id) REFERENCES account_types(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success_messages[] = "Created permission_assignments table";

    // 3. Create access_log table for audit trail
    $db->query("
        CREATE TABLE IF NOT EXISTS access_log (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_epf VARCHAR(50) NOT NULL,
            action VARCHAR(255) NOT NULL,
            context TEXT,
            target_epf VARCHAR(50),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_epf (user_epf),
            INDEX idx_action (action),
            INDEX idx_target_epf (target_epf),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success_messages[] = "Created access_log table";

    // 4. Update employees table to ensure proper columns exist
    $employee_columns = $db->fetchAll("DESCRIBE employees");
    $employee_column_names = array_column($employee_columns, 'Field');

    $required_employee_columns = [
        'account_type_id' => 'INT DEFAULT 1',
        'password_hash' => 'VARCHAR(255)',
        'password_change_required' => 'TINYINT(1) DEFAULT 0',
        'password_reset_at' => 'TIMESTAMP NULL',
        'last_login_at' => 'TIMESTAMP NULL'
    ];

    foreach ($required_employee_columns as $column => $definition) {
        if (!in_array($column, $employee_column_names)) {
            $db->query("ALTER TABLE employees ADD COLUMN $column $definition");
            $success_messages[] = "Added '$column' column to employees table";
        }
    }

    // Add foreign key constraint if it doesn't exist
    $fk_exists = $db->fetch("
        SELECT CONSTRAINT_NAME
        FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND TABLE_NAME = 'employees'
        AND CONSTRAINT_NAME = 'fk_employees_account_type'
    ");

    if (!$fk_exists) {
        try {
            $db->query("
                ALTER TABLE employees
                ADD CONSTRAINT fk_employees_account_type
                FOREIGN KEY (account_type_id) REFERENCES account_types(id)
            ");
            $success_messages[] = "Added foreign key constraint to employees table";
        } catch (Exception $e) {
            $errors[] = "Could not add foreign key constraint: " . $e->getMessage();
        }
    }

    // 5. Initialize default permissions
    $existing_permissions = $db->fetch("SELECT COUNT(*) as count FROM permission_assignments");

    if ($existing_permissions['count'] == 0) {
        $permission_count = 0;

        foreach (ENHANCED_ACCOUNT_TYPES as $account_type_id => $account_info) {
            $permissions = getAccountTypePermissions($account_type_id);

            foreach ($permissions as $permission_key) {
                try {
                    $db->query(
                        "INSERT INTO permission_assignments (account_type_id, permission_key, is_granted)
                         VALUES (?, ?, 1)",
                        [$account_type_id, $permission_key]
                    );
                    $permission_count++;
                } catch (Exception $e) {
                    $errors[] = "Failed to insert permission $permission_key for account type $account_type_id: " . $e->getMessage();
                }
            }
        }

        $success_messages[] = "Initialized $permission_count default permissions";
    } else {
        $success_messages[] = "Permissions already exist, skipping initialization";
    }

    // 6. Update existing users with default account_type_id if null
    $null_account_types = $db->fetchAll("SELECT id FROM employees WHERE account_type_id IS NULL OR account_type_id = 0");

    if (!empty($null_account_types)) {
        $updated_count = 0;
        foreach ($null_account_types as $employee) {
            $db->query("UPDATE employees SET account_type_id = 1 WHERE id = ?", [$employee['id']]);
            $updated_count++;
        }
        $success_messages[] = "Updated $updated_count employees with default account type";
    }

    // 7. Create permission cache table for performance optimization
    $db->query("
        CREATE TABLE IF NOT EXISTS permission_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_type_id INT NOT NULL,
            module_name VARCHAR(100) NOT NULL,
            permissions_json TEXT NOT NULL,
            cache_key VARCHAR(255) NOT NULL UNIQUE,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account_type (account_type_id),
            INDEX idx_module (module_name),
            INDEX idx_cache_key (cache_key),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success_messages[] = "Created permission_cache table";

} catch (Exception $e) {
    $errors[] = "Database setup error: " . $e->getMessage();
}

// HTML Output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Permission System Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .setup-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .setup-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .setup-body {
            padding: 2rem;
        }
        .log-item {
            padding: 0.75rem;
            margin: 0.5rem 0;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .log-success {
            background-color: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .log-error {
            background-color: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-header">
                <h1><i class="fas fa-shield-alt me-3"></i>SmartHRM Permission System Setup</h1>
                <p class="mb-0">Initializing comprehensive permission management system</p>
            </div>

            <div class="setup-body">
                <!-- Setup Results -->
                <?php if (!empty($success_messages)): ?>
                    <div class="alert alert-success">
                        <h5><i class="fas fa-check-circle me-2"></i>Setup Completed Successfully</h5>
                        <p class="mb-0">The permission system has been configured with all necessary components.</p>
                    </div>

                    <h6>Success Messages:</h6>
                    <?php foreach ($success_messages as $message): ?>
                        <div class="log-item log-success">
                            <i class="fas fa-check me-2"></i><?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Errors Encountered</h5>
                        <p class="mb-0">Some issues occurred during setup. Please review and fix them.</p>
                    </div>

                    <h6>Error Messages:</h6>
                    <?php foreach ($errors as $error): ?>
                        <div class="log-item log-error">
                            <i class="fas fa-times me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- System Statistics -->
                <h5 class="mt-4 mb-3"><i class="fas fa-chart-bar me-2"></i>System Statistics</h5>
                <div class="stats-grid">
                    <?php
                    try {
                        $account_types_count = $db->fetch("SELECT COUNT(*) as count FROM account_types")['count'];
                        $permissions_count = $db->fetch("SELECT COUNT(*) as count FROM permission_assignments")['count'];
                        $employees_count = $db->fetch("SELECT COUNT(*) as count FROM employees")['count'];
                        $active_accounts = $db->fetch("SELECT COUNT(*) as count FROM employees WHERE account_type_id > 1 AND is_active = 1")['count'];
                    ?>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $account_types_count; ?></div>
                            <div class="text-muted">Account Types</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $permissions_count; ?></div>
                            <div class="text-muted">Permissions Assigned</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $employees_count; ?></div>
                            <div class="text-muted">Total Employees</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $active_accounts; ?></div>
                            <div class="text-muted">Active Accounts</div>
                        </div>
                    <?php
                    } catch (Exception $e) {
                        echo "<div class='alert alert-warning'>Could not retrieve statistics: " . $e->getMessage() . "</div>";
                    }
                    ?>
                </div>

                <!-- Account Types Overview -->
                <h5 class="mt-4 mb-3"><i class="fas fa-users-cog me-2"></i>Account Types Overview</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Level</th>
                                <th>Data Access</th>
                                <th>Permissions Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (ENHANCED_ACCOUNT_TYPES as $id => $type): ?>
                                <?php
                                try {
                                    $perm_count = $db->fetch(
                                        "SELECT COUNT(*) as count FROM permission_assignments WHERE account_type_id = ?",
                                        [$id]
                                    )['count'];
                                } catch (Exception $e) {
                                    $perm_count = 'N/A';
                                }
                                ?>
                                <tr>
                                    <td><?php echo $id; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $type['color']; ?>">
                                            <?php echo htmlspecialchars($type['name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $type['level']; ?></td>
                                    <td><?php echo $type['data_access']; ?></td>
                                    <td><?php echo $perm_count; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Next Steps -->
                <div class="alert alert-info mt-4">
                    <h6><i class="fas fa-info-circle me-2"></i>Next Steps</h6>
                    <ol class="mb-0">
                        <li><strong>Configure User Accounts:</strong> Visit the <a href="../modules/admin/password_management_enhanced.php" target="_blank">Password Management</a> page to activate user accounts and set account types.</li>
                        <li><strong>Customize Permissions:</strong> Use the <a href="../modules/admin/permissions_central.php" target="_blank">Central Permission Management</a> to fine-tune permissions for each account type.</li>
                        <li><strong>Test Access:</strong> Login with different account types to verify the permission system is working correctly.</li>
                        <li><strong>Monitor Usage:</strong> Check the access logs regularly to monitor system usage and security.</li>
                    </ol>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-center gap-3 mt-4">
                    <a href="../modules/admin/permissions_central.php" class="btn btn-primary">
                        <i class="fas fa-cog me-2"></i>Configure Permissions
                    </a>
                    <a href="../modules/admin/password_management_enhanced.php" class="btn btn-success">
                        <i class="fas fa-users me-2"></i>Manage Accounts
                    </a>
                    <a href="../dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-home me-2"></i>Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>