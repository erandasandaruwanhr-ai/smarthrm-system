<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

if (!canManagePasswords()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();
$db = new Database();

$default_password = DEFAULT_PASSWORD;

// Initialize error and success variables
$error = null;
$success = null;

if ($_POST) {
    if (isset($_POST['activate_account'])) {
        $employee_id = $_POST['employee_id'] ?? null;
        $account_type_id = $_POST['account_type_id'] ?? 1;
        $password_option = $_POST['password_option'] ?? 'default';
        $custom_password = $_POST['custom_password'] ?? '';

        // Validate required fields
        if (empty($employee_id) || empty($account_type_id)) {
            $error = "Missing required fields. Please try again.";
        } else {
            // Determine the password to use
            if ($password_option === 'custom' && !empty($custom_password)) {
                $new_password = $custom_password;
            } else {
                $new_password = $default_password;
            }

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Get employee details
            $employee = $db->fetch("SELECT * FROM employees WHERE id = ?", [$employee_id]);

            if ($employee) {
                try {
                    // Check if columns exist
                    $columns = $db->fetchAll("DESCRIBE employees");
                    $available_columns = array_column($columns, 'Field');

                    // Prepare update data for account activation
                    $update_fields = [
                        'account_type_id' => $account_type_id,
                        'password_hash' => $hashed_password,
                        'is_active' => 1, // Explicitly activate the account
                        'updated_at' => date('Y-m-d H:i:s')
                    ];

                    // Add password change requirement and reset timestamp if columns exist
                    if (in_array('password_change_required', $available_columns)) {
                        $update_fields['password_change_required'] = 1; // Force password change on next login
                    }

                    if (in_array('password_reset_at', $available_columns)) {
                        $update_fields['password_reset_at'] = date('Y-m-d H:i:s');
                    }

                    // Also update account_type field if it exists (for backward compatibility)
                    if (in_array('account_type', $available_columns)) {
                        $account_type_name = $db->fetch("SELECT type_name FROM account_types WHERE id = ?", [$account_type_id]);
                        if ($account_type_name) {
                            $update_fields['account_type'] = $account_type_name['type_name'];
                        }
                    }

                    // Build dynamic UPDATE query
                    $set_clauses = [];
                    $params = [];
                    foreach ($update_fields as $field => $value) {
                        $set_clauses[] = "$field = ?";
                        $params[] = $value;
                    }
                    $params[] = $employee_id; // For WHERE clause

                    $sql = "UPDATE employees SET " . implode(', ', $set_clauses) . " WHERE id = ?";
                    $result = $db->query($sql, $params);

                    if ($result) {
                        // Get account type name for success message
                        $account_type = $db->fetch("SELECT type_name FROM account_types WHERE id = ?", [$account_type_id]);
                        $account_type_name = $account_type ? $account_type['type_name'] : 'Unknown';

                        $password_type = ($password_option === 'custom') ? 'custom password' : 'default password';

                        $success = "Account activated successfully! Account type set to: {$account_type_name}, using {$password_type}. User will be required to change password on next login.";

                    } else {
                        $error = "Failed to activate employee account.";
                    }
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            } else {
                $error = "Employee not found.";
            }
        }
    }

    if (isset($_POST['reset_password'])) {
        $employee_id = $_POST['employee_id'] ?? null;
        $account_type_id = $_POST['account_type_id'] ?? 1;
        $password_option = $_POST['password_option'] ?? 'default';
        $custom_password = $_POST['custom_password'] ?? '';

        // Validate required fields
        if (empty($employee_id) || empty($account_type_id)) {
            $error = "Missing required fields. Please try again.";
        } else {
            // Determine the password to use
            if ($password_option === 'custom' && !empty($custom_password)) {
                $new_password = $custom_password;
            } else {
                $new_password = $default_password;
            }

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Get employee details
            $employee = $db->fetch("SELECT * FROM employees WHERE id = ?", [$employee_id]);

            if ($employee) {
                try {
                    // Check if columns exist
                    $columns = $db->fetchAll("DESCRIBE employees");
                    $available_columns = array_column($columns, 'Field');

                    // Prepare update data
                    $update_fields = [
                        'account_type_id' => $account_type_id,
                        'password_hash' => $hashed_password,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];

                    // Only set is_active if the user was already active OR if this is the first password setup
                    // This prevents accidentally activating inactive users
                    if ($employee['is_active'] == 1 || empty($employee['password_hash'])) {
                        $update_fields['is_active'] = 1;
                    }

                    // Add password change requirement and reset timestamp if columns exist
                    if (in_array('password_change_required', $available_columns)) {
                        $update_fields['password_change_required'] = 1; // Force password change on next login
                    }

                    if (in_array('password_reset_at', $available_columns)) {
                        $update_fields['password_reset_at'] = date('Y-m-d H:i:s');
                    }

                    // Also update account_type field if it exists (for backward compatibility)
                    if (in_array('account_type', $available_columns)) {
                        $account_type_name = $db->fetch("SELECT type_name FROM account_types WHERE id = ?", [$account_type_id]);
                        if ($account_type_name) {
                            $update_fields['account_type'] = $account_type_name['type_name'];
                        }
                    }

                    // Build dynamic UPDATE query
                    $set_clauses = [];
                    $params = [];
                    foreach ($update_fields as $field => $value) {
                        $set_clauses[] = "$field = ?";
                        $params[] = $value;
                    }
                    $params[] = $employee_id; // For WHERE clause

                    $sql = "UPDATE employees SET " . implode(', ', $set_clauses) . " WHERE id = ?";
                    $result = $db->query($sql, $params);

                    if ($result) {
                        // Get account type name for success message
                        $account_type = $db->fetch("SELECT type_name FROM account_types WHERE id = ?", [$account_type_id]);
                        $account_type_name = $account_type ? $account_type['type_name'] : 'Unknown';

                        $action = $employee['password_hash'] ? 'Password reset' : 'Account activated';
                        $password_type = ($password_option === 'custom') ? 'custom password' : 'default password';

                        $success = "{$action} successfully! Account type set to: {$account_type_name}, using {$password_type}. User will be required to change password on next login.";

                    } else {
                        $error = "Failed to update employee account.";
                    }
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            } else {
                $error = "Employee not found.";
            }
        }
    }

    if (isset($_POST['deactivate_account'])) {
        $employee_id = $_POST['employee_id'] ?? null;

        if (empty($employee_id)) {
            $error = "Missing employee ID. Please try again.";
        } else {
            try {
                // Check available columns
                $columns = $db->fetchAll("DESCRIBE employees");
                $available_columns = array_column($columns, 'Field');

                $update_fields = [
                    'account_type_id' => 1,
                    'password_hash' => null,
                    'is_active' => 0,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                // Reset password change requirement if column exists
                if (in_array('password_change_required', $available_columns)) {
                    $update_fields['password_change_required'] = 0;
                }

                if (in_array('password_reset_at', $available_columns)) {
                    $update_fields['password_reset_at'] = null;
                }

                if (in_array('account_type', $available_columns)) {
                    $update_fields['account_type'] = 'user';
                }

                // Build dynamic UPDATE query
                $set_clauses = [];
                $params = [];
                foreach ($update_fields as $field => $value) {
                    $set_clauses[] = "$field = ?";
                    $params[] = $value;
                }
                $params[] = $employee_id;

                $sql = "UPDATE employees SET " . implode(', ', $set_clauses) . " WHERE id = ?";
                $result = $db->query($sql, $params);

                if ($result) {
                    $success = "Account deactivated successfully!";
                } else {
                    $error = "Failed to deactivate account.";
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get account types for dropdown
try {
    $account_types = $db->fetchAll("SELECT * FROM account_types ORDER BY id ASC");
} catch (Exception $e) {
    $account_types = [];
    $error = "Could not load account types: " . $e->getMessage();
}

// Handle sorting and filtering
$sort_by = $_GET['sort'] ?? 'name';
$sort_order = $_GET['order'] ?? 'asc';
$epf_filter = $_GET['epf_filter'] ?? '';
$valid_sorts = ['name', 'epf_number', 'location_name', 'account_type_name', 'password_reset_at'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'name';
$sort_order = in_array($sort_order, ['asc', 'desc']) ? $sort_order : 'asc';

// Build ORDER BY clause
switch($sort_by) {
    case 'epf_number':
        // Cast EPF number to integer for proper numeric sorting
        $order_clause = "CAST(e.epf_number AS UNSIGNED) {$sort_order}";
        break;
    case 'location_name':
        $order_clause = "l.location_name {$sort_order}";
        break;
    case 'account_type_name':
        $order_clause = "at.type_name {$sort_order}";
        break;
    case 'password_reset_at':
        $order_clause = "e.password_reset_at IS NULL, e.password_reset_at {$sort_order}";
        break;
    default:
        $order_clause = "e.name {$sort_order}";
}

// Build WHERE clause for filtering
$where_clause = "";
$params = [];
if (!empty($epf_filter)) {
    $where_clause = "WHERE e.epf_number LIKE ?";
    $params[] = "%{$epf_filter}%";
}

// Get all employees with their account status
try {
    $sql = "
        SELECT
            e.id,
            e.name,
            e.epf_number,
            e.department,
            e.designation,
            e.location_id,
            e.account_type_id,
            e.password_hash,
            e.is_active,
            " . (in_array('password_change_required', array_column($db->fetchAll("DESCRIBE employees"), 'Field')) ? "e.password_change_required," : "0 as password_change_required,") . "
            " . (in_array('password_reset_at', array_column($db->fetchAll("DESCRIBE employees"), 'Field')) ? "e.password_reset_at," : "NULL as password_reset_at,") . "
            at.type_name as account_type_name,
            " . (in_array('level', array_column($db->fetchAll("DESCRIBE account_types"), 'Field')) ? "at.level as account_level," : "at.id as account_level,") . "
            l.location_name
        FROM employees e
        LEFT JOIN account_types at ON e.account_type_id = at.id
        LEFT JOIN locations l ON e.location_id = l.id
        {$where_clause}
        ORDER BY {$order_clause}
    ";

    $employees = $db->fetchAll($sql, $params);
} catch (Exception $e) {
    $employees = [];
    $error = "Could not load employees: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Enhanced Password Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .account-status {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active { background-color: rgba(40, 167, 69, 0.1); color: #28a745; }
        .status-inactive { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .status-password-change { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }

        .account-level-badge { font-size: 0.7rem; padding: 0.15rem 0.4rem; }
        .level-1 { background-color: #6c757d; }
        .level-2 { background-color: #17a2b8; }
        .level-3 { background-color: #ffc107; color: #000; }
        .level-4 { background-color: #fd7e14; }
        .level-5 { background-color: #dc3545; }

        .employee-card { transition: transform 0.2s; }
        .employee-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

        .password-strength-meter {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin-top: 5px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            transition: all 0.3s;
            border-radius: 4px;
        }

        .strength-weak { background: #dc3545; width: 25%; }
        .strength-fair { background: #ffc107; width: 50%; }
        .strength-good { background: #fd7e14; width: 75%; }
        .strength-strong { background: #28a745; width: 100%; }

        /* Table header sorting styles */
        .table-dark th {
            background-color: #212529 !important;
            border-color: #32383e !important;
            color: #ffffff !important;
            position: relative;
            cursor: pointer;
            user-select: none;
        }

        .table-dark th a {
            color: #ffffff !important;
            text-decoration: none !important;
            display: block !important;
            width: 100% !important;
            padding: 8px !important;
            font-weight: bold !important;
        }

        .table-dark th a:hover {
            color: #f8f9fa !important;
            text-decoration: none !important;
            background-color: rgba(255,255,255,0.1) !important;
        }

        .table-dark th a:visited {
            color: #ffffff !important;
        }

        .sort-icon {
            opacity: 0.6;
            margin-left: 8px;
            font-size: 0.9em;
            color: #ffffff !important;
        }

        .sort-icon.active {
            opacity: 1 !important;
            color: #ffc107 !important;
        }

        /* Non-sortable headers */
        .table-dark th.no-sort {
            color: #ffffff !important;
            font-weight: bold !important;
            padding: 8px !important;
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0 ms-2">Enhanced Password Management</h5>
            </div>
            <div class="user-info">
                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>
                        <?php echo htmlspecialchars($user['name']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="dashboard-content">
            <nav style="--bs-breadcrumb-divider: '>';" aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Admin Panel</a></li>
                    <li class="breadcrumb-item active">Password Management</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2"><i class="fas fa-key me-2"></i>Enhanced Password Management</h1>
                <div class="d-flex align-items-center">
                    <?php if (isset($_GET['sort'])): ?>
                        <small class="text-muted me-3">
                            Sorted by: <?php echo ucfirst(str_replace('_', ' ', $sort_by)); ?> (<?php echo strtoupper($sort_order); ?>)
                        </small>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Enhanced Features Info -->
            <div class="alert alert-info mb-4">
                <h6><i class="fas fa-star me-2"></i>Enhanced Features</h6>
                <div class="row">
                    <div class="col-md-4">
                        <ul class="mb-0">
                            <li><strong>Custom Passwords:</strong> Set any password you want</li>
                            <li><strong>Force Password Change:</strong> Users must change password after reset</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <ul class="mb-0">
                            <li><strong>Password Strength:</strong> Real-time strength checking</li>
                            <li><strong>Reset Tracking:</strong> Track when passwords were reset</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <ul class="mb-0">
                            <li><strong>Default Password:</strong> <code><?php echo htmlspecialchars($default_password); ?></code></li>
                            <li><strong>Username:</strong> Always the EPF number</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Account Statistics -->
            <div class="row mb-4">
                <?php
                $stats = [
                    'active' => 0,
                    'inactive' => 0,
                    'password_change_required' => 0,
                    'by_level' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
                ];

                foreach ($employees as $emp) {
                    if ($emp['password_hash'] && $emp['is_active']) {
                        $stats['active']++;
                        $level = $emp['account_level'] ?? 1;
                        $stats['by_level'][$level]++;

                        if ($emp['password_change_required']) {
                            $stats['password_change_required']++;
                        }
                    } else {
                        $stats['inactive']++;
                    }
                }
                ?>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x text-success mb-2"></i>
                            <h3 class="h2 text-success"><?php echo $stats['active']; ?></h3>
                            <p class="text-muted">Active Accounts</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-user-slash fa-2x text-danger mb-2"></i>
                            <h3 class="h2 text-danger"><?php echo $stats['inactive']; ?></h3>
                            <p class="text-muted">Inactive Accounts</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                            <h3 class="h2 text-warning"><?php echo $stats['password_change_required']; ?></h3>
                            <p class="text-muted">Password Change Required</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-crown fa-2x text-primary mb-2"></i>
                            <h3 class="h2 text-primary"><?php echo $stats['by_level'][5]; ?></h3>
                            <p class="text-muted">Superadmins</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee Accounts List -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Employee Account Management</h5>
                        <div class="d-flex align-items-center">
                            <form method="GET" class="d-flex align-items-center">
                                <!-- Preserve sort parameters -->
                                <?php if (isset($_GET['sort'])): ?>
                                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($_GET['sort']); ?>">
                                <?php endif; ?>
                                <?php if (isset($_GET['order'])): ?>
                                    <input type="hidden" name="order" value="<?php echo htmlspecialchars($_GET['order']); ?>">
                                <?php endif; ?>

                                <label class="form-label text-white me-2 mb-0" style="white-space: nowrap;">Filter EPF:</label>
                                <input type="text" name="epf_filter" class="form-control form-control-sm me-2"
                                       placeholder="Enter EPF number..."
                                       value="<?php echo htmlspecialchars($epf_filter); ?>"
                                       style="width: 150px;">
                                <button type="submit" class="btn btn-light btn-sm me-1">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($epf_filter)): ?>
                                    <a href="?" class="btn btn-outline-light btn-sm">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th style="padding: 0;">
                                        <a href="?sort=name&order=<?php echo ($sort_by == 'name' && $sort_order == 'asc') ? 'desc' : 'asc'; ?><?php echo !empty($epf_filter) ? '&epf_filter=' . urlencode($epf_filter) : ''; ?>">
                                            Employee
                                            <i class="fas fa-sort<?php echo ($sort_by == 'name') ? '-' . ($sort_order == 'asc' ? 'up' : 'down') : ''; ?> sort-icon<?php echo ($sort_by == 'name') ? ' active' : ''; ?>"></i>
                                        </a>
                                    </th>
                                    <th style="padding: 0;">
                                        <a href="?sort=epf_number&order=<?php echo ($sort_by == 'epf_number' && $sort_order == 'asc') ? 'desc' : 'asc'; ?><?php echo !empty($epf_filter) ? '&epf_filter=' . urlencode($epf_filter) : ''; ?>">
                                            EPF
                                            <i class="fas fa-sort<?php echo ($sort_by == 'epf_number') ? '-' . ($sort_order == 'asc' ? 'up' : 'down') : ''; ?> sort-icon<?php echo ($sort_by == 'epf_number') ? ' active' : ''; ?>"></i>
                                        </a>
                                    </th>
                                    <th style="padding: 0;">
                                        <a href="?sort=location_name&order=<?php echo ($sort_by == 'location_name' && $sort_order == 'asc') ? 'desc' : 'asc'; ?><?php echo !empty($epf_filter) ? '&epf_filter=' . urlencode($epf_filter) : ''; ?>">
                                            Location
                                            <i class="fas fa-sort<?php echo ($sort_by == 'location_name') ? '-' . ($sort_order == 'asc' ? 'up' : 'down') : ''; ?> sort-icon<?php echo ($sort_by == 'location_name') ? ' active' : ''; ?>"></i>
                                        </a>
                                    </th>
                                    <th class="no-sort">Account Status</th>
                                    <th style="padding: 0;">
                                        <a href="?sort=account_type_name&order=<?php echo ($sort_by == 'account_type_name' && $sort_order == 'asc') ? 'desc' : 'asc'; ?><?php echo !empty($epf_filter) ? '&epf_filter=' . urlencode($epf_filter) : ''; ?>">
                                            Account Type
                                            <i class="fas fa-sort<?php echo ($sort_by == 'account_type_name') ? '-' . ($sort_order == 'asc' ? 'up' : 'down') : ''; ?> sort-icon<?php echo ($sort_by == 'account_type_name') ? ' active' : ''; ?>"></i>
                                        </a>
                                    </th>
                                    <th style="padding: 0;">
                                        <a href="?sort=password_reset_at&order=<?php echo ($sort_by == 'password_reset_at' && $sort_order == 'asc') ? 'desc' : 'asc'; ?><?php echo !empty($epf_filter) ? '&epf_filter=' . urlencode($epf_filter) : ''; ?>">
                                            Last Reset
                                            <i class="fas fa-sort<?php echo ($sort_by == 'password_reset_at') ? '-' . ($sort_order == 'asc' ? 'up' : 'down') : ''; ?> sort-icon<?php echo ($sort_by == 'password_reset_at') ? ' active' : ''; ?>"></i>
                                        </a>
                                    </th>
                                    <th class="no-sort">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($employees)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No employees found.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($employees as $employee): ?>
                                        <?php
                                        $hasAccount = $employee['password_hash'] && $employee['is_active'];
                                        $isCurrentUser = $employee['epf_number'] === $user['epf_number'];
                                        $requiresPasswordChange = $employee['password_change_required'] ?? false;
                                        ?>
                                        <tr class="<?php echo $isCurrentUser ? 'table-warning' : ''; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($employee['name']); ?></strong>
                                                <?php if ($isCurrentUser): ?>
                                                    <span class="badge bg-primary ms-2">You</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($employee['epf_number']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['location_name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if ($hasAccount): ?>
                                                    <?php if ($requiresPasswordChange): ?>
                                                        <span class="account-status status-password-change">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>Password Change Required
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="account-status status-active">
                                                            <i class="fas fa-check-circle me-1"></i>Active
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="account-status status-inactive">
                                                        <i class="fas fa-times-circle me-1"></i>Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($employee['account_type_name']): ?>
                                                    <span class="badge account-level-badge level-<?php echo $employee['account_level']; ?>">
                                                        <?php echo htmlspecialchars($employee['account_type_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($employee['password_reset_at']): ?>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y g:i A', strtotime($employee['password_reset_at'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$isCurrentUser): ?>
                                                    <?php if ($hasAccount): ?>
                                                        <button class="btn btn-sm btn-warning me-1"
                                                                onclick="manageAccount(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($employee['epf_number']); ?>', true, <?php echo $employee['account_type_id'] ?? 1; ?>, 'reset')">
                                                            <i class="fas fa-key me-1"></i>Reset Password
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-success me-1"
                                                                onclick="manageAccount(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($employee['epf_number']); ?>', false, <?php echo $employee['account_type_id'] ?? 1; ?>, 'activate')">
                                                            <i class="fas fa-user-plus me-1"></i>Activate Account
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if ($hasAccount): ?>
                                                        <button class="btn btn-sm btn-outline-danger"
                                                                onclick="deactivateAccount(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-user-slash me-1"></i>Deactivate
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted small">
                                                        <i class="fas fa-lock me-1"></i>Own Account
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Account Management Modal -->
    <div class="modal fade" id="accountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-user-cog me-2"></i><span id="modal_title">Account Management</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="employee_id" id="employee_id">

                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <div class="form-control-plaintext" id="employee_info"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Account Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="account_type_id" id="account_type_id" required>
                                <?php foreach ($account_types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>">
                                        <?php echo htmlspecialchars($type['type_name']); ?>
                                        <?php if (isset($type['level'])): ?>
                                            (Level <?php echo $type['level']; ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password Option</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="password_option" id="use_default" value="default" checked>
                                <label class="form-check-label" for="use_default">
                                    Use default password: <code><?php echo htmlspecialchars($default_password); ?></code>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="password_option" id="use_custom" value="custom">
                                <label class="form-check-label" for="use_custom">
                                    Set custom password
                                </label>
                            </div>
                        </div>

                        <div class="mb-3" id="custom_password_field" style="display: none;">
                            <label class="form-label">Custom Password</label>
                            <input type="password" class="form-control" name="custom_password" id="custom_password" minlength="6">
                            <div class="password-strength-meter">
                                <div class="password-strength-bar" id="strength_bar"></div>
                            </div>
                            <div class="form-text">
                                <small id="strength_text">Minimum 6 characters required</small>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> The user will be required to change their password on the next login for security.
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="action_description"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reset_password" class="btn btn-primary" id="submit_btn">
                            <i class="fas fa-save me-2"></i><span id="action_button_text">Save Changes</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Deactivate Account Modal -->
    <div class="modal fade" id="deactivateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">
                            <i class="fas fa-user-slash me-2"></i>Deactivate Account
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="employee_id" id="deactivate_employee_id">

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This will deactivate the account for <span id="deactivate_employee_name"></span> and revoke all system access.
                        </div>

                        <p>Are you sure you want to deactivate this account? This action will:</p>
                        <ul>
                            <li>Remove system access immediately</li>
                            <li>Clear the user's password</li>
                            <li>Set account type to User (Level 1)</li>
                            <li>Mark account as inactive</li>
                            <li>Reset password change requirements</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="deactivate_account" class="btn btn-danger">
                            <i class="fas fa-user-slash me-2"></i>Deactivate Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Password option toggle
        document.querySelectorAll('input[name="password_option"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const customField = document.getElementById('custom_password_field');
                const customInput = document.getElementById('custom_password');

                if (this.value === 'custom') {
                    customField.style.display = 'block';
                    customInput.required = true;
                    customInput.focus();
                } else {
                    customField.style.display = 'none';
                    customInput.required = false;
                    customInput.value = '';
                    updatePasswordStrength('');
                }
            });
        });

        // Password strength checker
        document.getElementById('custom_password').addEventListener('input', function() {
            updatePasswordStrength(this.value);
            validateForm();
        });

        function updatePasswordStrength(password) {
            const strengthBar = document.getElementById('strength_bar');
            const strengthText = document.getElementById('strength_text');

            if (password.length === 0) {
                strengthBar.className = 'password-strength-bar';
                strengthText.textContent = 'Minimum 6 characters required';
                return;
            }

            let score = 0;
            if (password.length >= 6) score++;
            if (password.length >= 8) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            const levels = [
                { class: 'strength-weak', text: 'Weak' },
                { class: 'strength-weak', text: 'Weak' },
                { class: 'strength-fair', text: 'Fair' },
                { class: 'strength-good', text: 'Good' },
                { class: 'strength-strong', text: 'Strong' },
                { class: 'strength-strong', text: 'Very Strong' }
            ];

            const level = levels[Math.min(score, 5)];
            strengthBar.className = 'password-strength-bar ' + level.class;
            strengthText.textContent = level.text + ' password';
        }

        function validateForm() {
            const submitBtn = document.getElementById('submit_btn');
            const customRadio = document.getElementById('use_custom');
            const customPassword = document.getElementById('custom_password');

            if (customRadio.checked && customPassword.value.length < 6) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Password too short';
            } else {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>' + document.getElementById('action_button_text').textContent;
            }
        }

        function manageAccount(employeeId, employeeName, epfNumber, hasAccount, currentAccountType, actionType = 'reset') {
            document.getElementById('employee_id').value = employeeId;
            document.getElementById('employee_info').innerHTML = `<strong>${employeeName}</strong><br>EPF: ${epfNumber}`;

            // Set current account type
            document.getElementById('account_type_id').value = currentAccountType || 1;

            if (actionType === 'activate') {
                document.getElementById('modal_title').textContent = 'Activate Account';
                document.getElementById('action_description').innerHTML = `This will activate the account for <strong>${employeeName}</strong> with username <strong>${epfNumber}</strong> and require password change on first login.`;
                document.getElementById('action_button_text').textContent = 'Activate Account';
                document.getElementById('submit_btn').setAttribute('data-action', 'activate_account');
            } else {
                document.getElementById('modal_title').textContent = 'Reset Password & Update Account';
                document.getElementById('action_description').textContent = 'This will reset the password, update account type, and require a password change on next login. The account will remain in its current active/inactive state.';
                document.getElementById('action_button_text').textContent = 'Reset Password';
                document.getElementById('submit_btn').setAttribute('data-action', 'reset_password');
            }

            // Reset form
            document.getElementById('use_default').checked = true;
            document.getElementById('custom_password_field').style.display = 'none';
            document.getElementById('custom_password').value = '';
            document.getElementById('custom_password').required = false;
            updatePasswordStrength('');
            validateForm();

            new bootstrap.Modal(document.getElementById('accountModal')).show();
        }

        // Handle form submission with dynamic action
        document.querySelector('#accountModal form').addEventListener('submit', function(e) {
            const actionType = document.getElementById('submit_btn').getAttribute('data-action') || 'reset_password';

            // Remove any existing hidden input
            const existingInput = this.querySelector('input[name="activate_account"], input[name="reset_password"]');
            if (existingInput) {
                existingInput.remove();
            }

            // Add the appropriate hidden input
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = actionType;
            hiddenInput.value = '1';
            this.appendChild(hiddenInput);
        });

        function deactivateAccount(employeeId, employeeName) {
            document.getElementById('deactivate_employee_id').value = employeeId;
            document.getElementById('deactivate_employee_name').textContent = employeeName;

            new bootstrap.Modal(document.getElementById('deactivateModal')).show();
        }
    </script>
</body>
</html>