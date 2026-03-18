<?php
// This file is deprecated - redirect to the main permissions system
header('Location: permissions_central.php');
exit();
?>
$db = new Database();

// Define simplified permission structure based on data access hierarchy
$data_access_levels = [
    1 => [
        'name' => 'User',
        'description' => 'Can only view and edit their own data',
        'permissions' => [
            'dashboard.view' => 'View Dashboard',
            'employee.view_own' => 'View Own Profile',
            'employee.edit_own' => 'Edit Own Profile',
            'meal.submit_request' => 'Submit Meal Requests',
            'transport.submit_request' => 'Submit Transport Requests',
            'grievance.submit' => 'Submit Grievances'
        ]
    ],
    2 => [
        'name' => 'Supervisor',
        'description' => 'Can view all reports, edit direct reports only',
        'permissions' => [
            'dashboard.view' => 'View Dashboard',
            'employee.view_reports' => 'View All Reports (Direct & Indirect)',
            'employee.edit_direct_reports' => 'Edit Direct Reports',
            'employee.view_own' => 'View Own Profile',
            'employee.edit_own' => 'Edit Own Profile',
            'employee.org_chart.view' => 'View Organizational Chart',
            'employee.data_monitor.view' => 'View Data Analytics',
            'meal.view_reports' => 'View Team Meal Data',
            'transport.view_reports' => 'View Team Transport Data',
            'grievance.view_reports' => 'View Team Grievances'
        ]
    ],
    3 => [
        'name' => 'Manager',
        'description' => 'Can view and edit all reports (direct & indirect)',
        'permissions' => [
            'dashboard.view' => 'View Dashboard',
            'employee.view_reports' => 'View All Reports (Direct & Indirect)',
            'employee.edit_reports' => 'Edit All Reports',
            'employee.view_own' => 'View Own Profile',
            'employee.edit_own' => 'Edit Own Profile',
            'employee.org_chart.view' => 'View Organizational Chart',
            'employee.data_monitor.view' => 'View Data Analytics',
            'employee.employee_list.view' => 'View Employee Lists',
            'meal.manage_team' => 'Manage Team Meal Requests',
            'transport.manage_team' => 'Manage Team Transport',
            'grievance.manage_team' => 'Manage Team Grievances',
            'performance.manage_team' => 'Manage Team Performance'
        ]
    ],
    4 => [
        'name' => 'Admin',
        'description' => 'Can manage all employees in their location',
        'permissions' => [
            'dashboard.view' => 'View Dashboard',
            'employee.view_location' => 'View All Location Employees',
            'employee.edit_location' => 'Edit All Location Employees',
            'employee.create' => 'Create New Employees',
            'employee.delete_location' => 'Delete Location Employees',
            'employee.bulk_upload' => 'Bulk Upload Employees',
            'employee.org_chart.view' => 'View Organizational Chart',
            'employee.data_monitor.all' => 'View All Analytics',
            'meal.manage_location' => 'Manage Location Meal System',
            'transport.manage_location' => 'Manage Location Transport',
            'grievance.manage_location' => 'Manage Location Grievances',
            'admin.locations.edit' => 'Edit Location Settings',
            'admin.employment_levels.view' => 'View Employment Levels',
            'admin.dropdown_management.all' => 'Manage Dropdown Lists'
        ]
    ],
    5 => [
        'name' => 'Superadmin',
        'description' => 'Full access to all system data and settings',
        'permissions' => [
            'dashboard.view' => 'View Dashboard',
            'employee.view_all' => 'View All Employees (All Locations)',
            'employee.edit_all' => 'Edit All Employees',
            'employee.create_all' => 'Create Employees (Any Location)',
            'employee.delete_all' => 'Delete Any Employee',
            'employee.bulk_upload' => 'Bulk Upload Employees',
            'admin.system_config.all' => 'System Configuration',
            'admin.account_types.all' => 'Manage Account Types',
            'admin.permissions.all' => 'Manage Permissions',
            'admin.locations.all' => 'Manage All Locations',
            'admin.employment_levels.all' => 'Manage Employment Levels',
            'admin.password_management.all' => 'Password Management',
            'admin.dropdown_management.all' => 'Dropdown Management',
            'employee.org_chart.all' => 'Full Organizational Chart Access',
            'employee.data_monitor.all' => 'All Data Analytics',
            'meal.manage_all' => 'Manage All Meal Systems',
            'transport.manage_all' => 'Manage All Transport',
            'grievance.manage_all' => 'Manage All Grievances',
            'performance.manage_all' => 'Manage All Performance Data'
        ]
    ]
];

// Process form submission
if ($_POST && isset($_POST['apply_permissions'])) {
    $account_type_id = $_POST['account_type_id'];

    // Clear existing permissions for this account type
    $db->query("DELETE FROM account_permissions WHERE account_type_id = ?", [$account_type_id]);

    // Get the predefined permissions for this level
    if (isset($data_access_levels[$account_type_id])) {
        $level_permissions = $data_access_levels[$account_type_id]['permissions'];

        foreach ($level_permissions as $permission_key => $permission_desc) {
            // Parse permission key to get module and action
            $parts = explode('.', $permission_key);
            $module = $parts[0];

            $db->query(
                "INSERT INTO account_permissions (account_type_id, module_name, action_name) VALUES (?, ?, ?)",
                [$account_type_id, $module, $permission_key]
            );
        }

        $success = "Permissions applied successfully for " . $data_access_levels[$account_type_id]['name'] . " level!";
    }
}

$account_types = $db->fetchAll("SELECT * FROM account_types ORDER BY id ASC");

// Get current permissions for selected account type
if (isset($_GET['account_type'])) {
    $selected_account_type = $_GET['account_type'];
    $current_permissions = $db->fetchAll("SELECT action_name FROM account_permissions WHERE account_type_id = ?", [$selected_account_type]);

    $permissions_array = [];
    foreach ($current_permissions as $perm) {
        $permissions_array[$perm['action_name']] = true;
    }
} else {
    $selected_account_type = null;
    $permissions_array = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Data Access Permission Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .permission-level-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .permission-level-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .level-1 { border-left-color: #6c757d; }
        .level-2 { border-left-color: #17a2b8; }
        .level-3 { border-left-color: #ffc107; }
        .level-4 { border-left-color: #fd7e14; }
        .level-5 { border-left-color: #dc3545; }

        .permission-item {
            padding: 8px 12px;
            border-radius: 6px;
            margin: 4px 0;
            background: #f8f9fa;
            border-left: 3px solid #007bff;
        }

        .current-permissions {
            max-height: 400px;
            overflow-y: auto;
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
                <h5 class="mb-0 ms-2">Data Access Permission Management</h5>
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
                    <li class="breadcrumb-item active">Permission Management</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2"><i class="fas fa-shield-alt me-2"></i>Data Access Permission Management</h1>
                <div>
                    <a href="permissions.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-cogs me-2"></i>Advanced Permissions
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Account Type Selection -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Select Account Type to Manage</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($account_types as $account_type): ?>
                            <div class="col-md-2 mb-2">
                                <a href="?account_type=<?php echo $account_type['id']; ?>"
                                   class="btn <?php echo ($selected_account_type == $account_type['id']) ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                                    <i class="fas fa-<?php echo $account_type['id'] == 5 ? 'crown' : ($account_type['id'] == 4 ? 'user-shield' : 'user'); ?> me-2"></i>
                                    <?php echo htmlspecialchars($account_type['type_name']); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ($selected_account_type): ?>
                <div class="row">
                    <!-- Permission Level Information -->
                    <div class="col-md-6">
                        <div class="card shadow-sm permission-level-card level-<?php echo $selected_account_type; ?>">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <?php echo $data_access_levels[$selected_account_type]['name']; ?> Level Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3"><?php echo $data_access_levels[$selected_account_type]['description']; ?></p>

                                <h6><i class="fas fa-list-check me-2"></i>Default Permissions:</h6>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach ($data_access_levels[$selected_account_type]['permissions'] as $perm_key => $perm_desc): ?>
                                        <div class="permission-item">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <strong><?php echo $perm_desc; ?></strong>
                                            <div class="small text-muted"><?php echo $perm_key; ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="account_type_id" value="<?php echo $selected_account_type; ?>">
                                    <button type="submit" name="apply_permissions" class="btn btn-success w-100">
                                        <i class="fas fa-magic me-2"></i>Apply Default Permissions
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Current Applied Permissions -->
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-shield-check me-2"></i>
                                    Currently Applied Permissions
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($permissions_array)): ?>
                                    <div class="current-permissions">
                                        <?php foreach ($permissions_array as $permission => $value): ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                <code class="small"><?php echo htmlspecialchars($permission); ?></code>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Total: <?php echo count($permissions_array); ?> permissions active
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                                        <h6>No Permissions Applied</h6>
                                        <p class="text-muted small">This account type has no permissions assigned. Click "Apply Default Permissions" to assign the appropriate access level.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Access Summary -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-database me-2"></i>Data Access Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php
                            $access_summary = [
                                1 => ['scope' => 'Own Data Only', 'icon' => 'user', 'color' => 'secondary'],
                                2 => ['scope' => 'Direct & Indirect Reports', 'icon' => 'users', 'color' => 'info'],
                                3 => ['scope' => 'All Reports (Manager Level)', 'icon' => 'sitemap', 'color' => 'warning'],
                                4 => ['scope' => 'All Location Employees', 'icon' => 'building', 'color' => 'primary'],
                                5 => ['scope' => 'All Employees, All Locations', 'icon' => 'globe', 'color' => 'danger']
                            ];
                            ?>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="fas fa-<?php echo $access_summary[$selected_account_type]['icon']; ?> fa-2x text-<?php echo $access_summary[$selected_account_type]['color']; ?> mb-2"></i>
                                    <h6>Data Scope</h6>
                                    <p class="small text-muted"><?php echo $access_summary[$selected_account_type]['scope']; ?></p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="fas fa-eye fa-2x text-primary mb-2"></i>
                                    <h6>View Access</h6>
                                    <p class="small text-muted"><?php echo $selected_account_type >= 4 ? 'Full Location' : 'Hierarchical'; ?></p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="fas fa-edit fa-2x text-warning mb-2"></i>
                                    <h6>Edit Access</h6>
                                    <p class="small text-muted"><?php echo $selected_account_type >= 4 ? 'Full' : ($selected_account_type >= 2 ? 'Limited' : 'Self Only'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="fas fa-trash fa-2x text-danger mb-2"></i>
                                    <h6>Delete Access</h6>
                                    <p class="small text-muted"><?php echo $selected_account_type >= 4 ? 'Yes' : 'No'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-users-cog fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Select an Account Type</h5>
                        <p class="text-muted">Choose an account type above to view and manage its permissions and data access levels.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>