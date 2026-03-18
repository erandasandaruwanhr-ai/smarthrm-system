<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/permission_config.php';

// Check permission
if (!canManagePermissions()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();
$db = new Database();

$success_message = '';
$error_message = '';

// Handle form submission for permission updates
if ($_POST && isset($_POST['account_type_id']) && isset($_POST['save_permissions'])) {
    $account_type_id = intval($_POST['account_type_id']);
    $submitted_permissions = $_POST['permissions'] ?? [];

    try {
        // Get account type name
        $account_type = $db->fetch("SELECT type_name FROM account_types WHERE id = ?", [$account_type_id]);
        $account_type_name = $account_type ? $account_type['type_name'] : 'Unknown';

        // Handle Superadmin special case
        if ($account_type_id == 5) {
            // Superadmin gets ALL permissions
            $db->query("DELETE FROM permission_assignments WHERE account_type_id = ?", [$account_type_id]);

            // Get all available permissions
            $all_permissions = [];
            foreach (MODULE_PERMISSIONS as $module_key => $module) {
                foreach ($module['permissions'] as $permission_key => $permission_title) {
                    $all_permissions[] = $module_key . '.' . $permission_key;
                }
            }

            // Insert all permissions for superadmin
            foreach ($all_permissions as $permission_key) {
                $db->query(
                    "INSERT INTO permission_assignments (account_type_id, permission_key, is_granted) VALUES (?, ?, 1)",
                    [$account_type_id, $permission_key]
                );
            }

            $success_message = "Superadmin permissions updated! All " . count($all_permissions) . " permissions granted.";
        } else {
            // For other account types, use submitted permissions
            $db->query("DELETE FROM permission_assignments WHERE account_type_id = ?", [$account_type_id]);

            $permission_count = 0;
            foreach ($submitted_permissions as $permission_key) {
                $db->query(
                    "INSERT INTO permission_assignments (account_type_id, permission_key, is_granted) VALUES (?, ?, 1)",
                    [$account_type_id, $permission_key]
                );
                $permission_count++;
            }

            $success_message = "Permissions updated for {$account_type_name}! {$permission_count} permissions granted.";
        }

    } catch (Exception $e) {
        $error_message = "Error updating permissions: " . $e->getMessage();
    }
}

// Handle account type preset loading
if (isset($_GET['load_preset']) && isset($_GET['account_type_id'])) {
    $account_type_id = intval($_GET['account_type_id']);

    try {
        // Delete existing permissions
        $db->query("DELETE FROM permission_assignments WHERE account_type_id = ?", [$account_type_id]);

        // Load preset permissions
        $preset_permissions = getAccountTypePermissions($account_type_id);

        foreach ($preset_permissions as $permission_key) {
            $db->query(
                "INSERT INTO permission_assignments (account_type_id, permission_key, is_granted) VALUES (?, ?, 1)",
                [$account_type_id, $permission_key]
            );
        }

        $account_type = $db->fetch("SELECT type_name FROM account_types WHERE id = ?", [$account_type_id]);
        $success_message = "Loaded preset permissions for " . $account_type['type_name'] . "! " . count($preset_permissions) . " permissions assigned.";

    } catch (Exception $e) {
        $error_message = "Error loading preset: " . $e->getMessage();
    }
}

// Get account types
$account_types = $db->fetchAll("SELECT * FROM account_types ORDER BY level ASC");

// Get current selected account type
$selected_account_type_id = $_GET['account_type'] ?? 5; // Default to superadmin

// Get current permissions for selected account type
$current_permissions = [];
try {
    $permissions_result = $db->fetchAll(
        "SELECT permission_key FROM permission_assignments WHERE account_type_id = ? AND is_granted = 1",
        [$selected_account_type_id]
    );
    $current_permissions = array_column($permissions_result, 'permission_key');
} catch (Exception $e) {
    // Table might not exist yet
    $current_permissions = [];
}

// Get account type info
$selected_account_type = $db->fetch("SELECT * FROM account_types WHERE id = ?", [$selected_account_type_id]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Enhanced Permissions Central</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .permission-section {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin: 15px 0;
            overflow: hidden;
        }
        .permission-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 15px 20px;
            cursor: pointer;
            position: relative;
        }
        .permission-header:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
        }
        .permission-body {
            padding: 20px;
            background: #f8f9fa;
        }
        .permission-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .permission-item:last-child {
            border-bottom: none;
        }
        .permission-checkbox {
            margin-right: 15px;
        }
        .account-type-selector {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .level-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.6rem;
        }
        .stats-card {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        .collapse-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.3s;
        }
        .collapsed .collapse-icon {
            transform: translateY(-50%) rotate(-90deg);
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
                <h5 class="mb-0 ms-2">Enhanced Permissions Central</h5>
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
                    <li class="breadcrumb-item active">Enhanced Permissions Central</li>
                </ol>
            </nav>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3"><i class="fas fa-shield-alt me-2"></i>Enhanced Permissions Central</h1>
                    <p class="text-muted">Manage what each account type can access across all modules</p>
                </div>
                <div>
                    <a href="permissions_central.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Original Version
                    </a>
                </div>
            </div>

            <!-- Account Type Selector -->
            <div class="account-type-selector">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-3">Select Account Type to Configure</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach ($account_types as $type): ?>
                                <a href="?account_type=<?php echo $type['id']; ?>"
                                   class="btn <?php echo ($type['id'] == $selected_account_type_id) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                    <span class="badge level-badge bg-light text-dark ms-1">L<?php echo $type['level']; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h4><?php echo count($current_permissions); ?></h4>
                            <small>Permissions Granted</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <h6>Quick Actions</h6>
                        <a href="?load_preset=1&account_type_id=<?php echo $selected_account_type_id; ?>"
                           class="btn btn-success btn-sm mb-1">
                            <i class="fas fa-magic me-1"></i>Load Preset
                        </a>
                        <br>
                        <button type="button" class="btn btn-warning btn-sm" onclick="selectAllPermissions()">
                            <i class="fas fa-check-double me-1"></i>Select All
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm ms-1" onclick="deselectAllPermissions()">
                            <i class="fas fa-times me-1"></i>Clear All
                        </button>
                    </div>
                </div>
            </div>

            <!-- Current Account Type Info -->
            <?php if ($selected_account_type): ?>
            <div class="alert alert-info">
                <h6><i class="fas fa-user-tag me-2"></i>Currently Configuring: <?php echo htmlspecialchars($selected_account_type['type_name']); ?></h6>
                <p class="mb-0">
                    <strong>Level:</strong> <?php echo $selected_account_type['level']; ?> |
                    <strong>Description:</strong> <?php echo htmlspecialchars($selected_account_type['description'] ?? ''); ?> |
                    <strong>Data Access:</strong> <?php echo ENHANCED_ACCOUNT_TYPES[$selected_account_type['level']]['data_access'] ?? 'Unknown'; ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- Permissions Form -->
            <form method="POST" id="permissionsForm">
                <input type="hidden" name="account_type_id" value="<?php echo $selected_account_type_id; ?>">

                <?php foreach (MODULE_PERMISSIONS as $module_key => $module_data): ?>
                    <div class="permission-section">
                        <div class="permission-header" data-bs-toggle="collapse" data-bs-target="#module-<?php echo $module_key; ?>">
                            <h5 class="mb-0">
                                <i class="<?php echo $module_data['icon']; ?> me-2"></i>
                                <?php echo htmlspecialchars($module_data['title']); ?>
                                <small class="ms-2 opacity-75">
                                    <?php
                                    $module_perms = 0;
                                    foreach ($module_data['permissions'] as $perm_key => $perm_title) {
                                        if (in_array($module_key . '.' . $perm_key, $current_permissions)) {
                                            $module_perms++;
                                        }
                                    }
                                    echo "$module_perms / " . count($module_data['permissions']) . " enabled";
                                    ?>
                                </small>
                            </h5>
                            <i class="fas fa-chevron-down collapse-icon"></i>
                        </div>

                        <div class="collapse permission-body" id="module-<?php echo $module_key; ?>">
                            <div class="row">
                                <?php foreach ($module_data['permissions'] as $permission_key => $permission_title): ?>
                                    <?php
                                    $full_permission_key = $module_key . '.' . $permission_key;
                                    $is_checked = in_array($full_permission_key, $current_permissions);
                                    ?>
                                    <div class="col-md-6">
                                        <div class="permission-item">
                                            <label class="form-check-label d-flex align-items-center">
                                                <input type="checkbox"
                                                       class="form-check-input permission-checkbox"
                                                       name="permissions[]"
                                                       value="<?php echo $full_permission_key; ?>"
                                                       <?php echo $is_checked ? 'checked' : ''; ?>>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($permission_title); ?></strong><br>
                                                    <small class="text-muted"><?php echo $full_permission_key; ?></small>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Save Button -->
                <div class="text-center mt-4 mb-4">
                    <button type="submit" name="save_permissions" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>
                        Save Permissions for <?php echo htmlspecialchars($selected_account_type['type_name'] ?? 'Account Type'); ?>
                    </button>
                </div>
            </form>

            <!-- Footer Info -->
            <div class="alert alert-light">
                <h6><i class="fas fa-info-circle me-2"></i>How This Works</h6>
                <div class="row">
                    <div class="col-md-4">
                        <strong>Check Permissions:</strong> Select which features each account type can access
                    </div>
                    <div class="col-md-4">
                        <strong>Save Changes:</strong> Click save to apply permissions to all users of this type
                    </div>
                    <div class="col-md-4">
                        <strong>Test Results:</strong> Use the <a href="../employee/employee_list_enhanced.php">Enhanced Employee List</a> to test
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle collapse icons
        document.addEventListener('DOMContentLoaded', function() {
            const collapseElements = document.querySelectorAll('[data-bs-toggle="collapse"]');
            collapseElements.forEach(function(element) {
                element.addEventListener('click', function() {
                    setTimeout(() => {
                        element.classList.toggle('collapsed');
                    }, 150);
                });
            });
        });

        // Select all permissions
        function selectAllPermissions() {
            const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = true);
        }

        // Deselect all permissions
        function deselectAllPermissions() {
            const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = false);
        }

        // Sidebar toggle
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Auto-expand sections with permissions
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.permission-section');
            sections.forEach(section => {
                const checkedBoxes = section.querySelectorAll('input[type="checkbox"]:checked');
                if (checkedBoxes.length > 0) {
                    const collapseElement = section.querySelector('.collapse');
                    if (collapseElement) {
                        collapseElement.classList.add('show');
                    }
                }
            });
        });
    </script>
</body>
</html>