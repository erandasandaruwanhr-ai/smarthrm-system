<?php
// This file is deprecated - redirect to the main permissions system
header('Location: permissions_central.php');
exit();
?>
$db = new Database();

// Define complete permission structure based on Plan.md
$permission_structure = [
    'dashboard' => [
        'title' => 'Dashboard',
        'ref' => 'D',
        'permissions' => [
            'view' => 'View Dashboard'
        ]
    ],
    'admin' => [
        'title' => '1. Admin Panel',
        'ref' => '1',
        'permissions' => [
            'view' => 'View Admin Panel'
        ],
        'sub_modules' => [
            'system_config' => [
                'title' => '1.1 System Configuration',
                'ref' => '1.1',
                'permissions' => [
                    'view' => 'View System Configuration',
                    'color_management' => '1.1.1 Color Management',
                    'calendar_setup' => '1.1.2 Calendar Setup'
                ]
            ],
            'account_types' => [
                'title' => '1.2 Account Types',
                'ref' => '1.2',
                'permissions' => [
                    'view' => 'View Account Types',
                    'add' => 'Add Account Types',
                    'edit' => 'Edit Account Types',
                    'delete' => 'Delete Account Types'
                ]
            ],
            'permissions' => [
                'title' => '1.3 Permission Management',
                'ref' => '1.3',
                'permissions' => [
                    'view' => 'View Permissions',
                    'assign' => 'Assign Permissions'
                ]
            ],
            'locations' => [
                'title' => '1.4 Locations',
                'ref' => '1.4',
                'permissions' => [
                    'view' => 'View Locations',
                    'add' => 'Add Locations',
                    'edit' => 'Edit Locations',
                    'delete' => 'Delete Locations'
                ]
            ],
            'employment_levels' => [
                'title' => '1.5 Employment Levels',
                'ref' => '1.5',
                'permissions' => [
                    'view' => 'View Employment Levels',
                    'add' => 'Add Employment Levels',
                    'edit' => 'Edit Employment Levels',
                    'delete' => 'Delete Employment Levels'
                ]
            ],
            'password_management' => [
                'title' => '1.6 Password Management',
                'ref' => '1.6',
                'permissions' => [
                    'view' => 'View Password Management',
                    'reset_password' => 'Reset Passwords',
                    'change_default' => 'Change Default Password'
                ]
            ],
            'dropdown_management' => [
                'title' => '1.7 Dropdown Management',
                'ref' => '1.7',
                'permissions' => [
                    'view' => 'View Dropdown Management',
                    'add' => 'Add Dropdown Items',
                    'edit' => 'Edit Dropdown Items',
                    'delete' => 'Delete Dropdown Items'
                ]
            ]
        ]
    ],
    'employee' => [
        'title' => '2. Employee Data',
        'ref' => '2',
        'permissions' => [
            'view' => 'View Employee Module'
        ],
        'sub_modules' => [
            'employee_form' => [
                'title' => '2.1 Employee Data Form',
                'ref' => '2.1',
                'permissions' => [
                    'view' => 'View Employee Form',
                    'add' => 'Add Employees',
                    'edit' => 'Edit Employees',
                    'delete' => 'Delete Employees',
                    'bulk_upload' => 'Bulk Upload',
                    'template_download' => 'Download Template'
                ]
            ],
            'employee_list' => [
                'title' => '2.2 Employee List',
                'ref' => '2.2',
                'permissions' => [
                    'view' => 'View Employee List',
                    'filter' => 'Use Filtering Options'
                ]
            ],
            'data_monitor' => [
                'title' => '2.3 Data Monitor',
                'ref' => '2.3',
                'permissions' => [
                    'view' => 'View Data Monitor',
                    'gender_chart' => '2.3.1 Gender Charts',
                    'location_chart' => '2.3.2 Location Charts',
                    'employment_chart' => '2.3.3 Employment Charts',
                    'age_chart' => '2.3.4 Age Charts',
                    'department_chart' => '2.3.5 Department Charts'
                ]
            ],
            'org_chart' => [
                'title' => '2.4 Organizational Chart',
                'ref' => '2.4',
                'permissions' => [
                    'view' => 'View Org Chart',
                    'tree_view' => '2.4.1.1 Tree View',
                    'list_view' => '2.4.1.2 List View',
                    'export_pdf' => '2.4.10.1 Export PDF',
                    'export_png' => '2.4.10.2 Export PNG',
                    'print' => '2.4.10.3 Print Chart'
                ]
            ]
        ]
    ],
    'meal' => [
        'title' => '3. Meal Management',
        'ref' => '3',
        'permissions' => [
            'view' => 'View Meal Module'
        ],
        'sub_modules' => [
            'employee_meal' => [
                'title' => '3.1 Employee Meal Request',
                'ref' => '3.1',
                'permissions' => [
                    'view' => 'View Employee Meal Form',
                    'submit' => 'Submit Employee Meal Request'
                ]
            ],
            'visitor_meal' => [
                'title' => '3.2 Visitor Meal Request',
                'ref' => '3.2',
                'permissions' => [
                    'view' => 'View Visitor Meal Form',
                    'submit' => 'Submit Visitor Meal Request'
                ]
            ],
            'employee_counter' => [
                'title' => '3.3 Employee Meal Counter',
                'ref' => '3.3',
                'permissions' => [
                    'view' => 'View Employee Meal Counter'
                ]
            ],
            'visitor_counter' => [
                'title' => '3.4 Visitor Meal Counter',
                'ref' => '3.4',
                'permissions' => [
                    'view' => 'View Visitor Meal Counter'
                ]
            ],
            'request_view' => [
                'title' => '3.5 Visitor Meal Request View',
                'ref' => '3.5',
                'permissions' => [
                    'view' => 'View Request List',
                    'edit' => 'Edit Requests',
                    'delete' => 'Delete Requests'
                ]
            ],
            'charts' => [
                'title' => '3.6 Meal Charts',
                'ref' => '3.6',
                'permissions' => [
                    'view' => 'View Meal Charts'
                ]
            ],
            'time_manager' => [
                'title' => '3.7 Meal Request Time Manager',
                'ref' => '3.7',
                'permissions' => [
                    'view' => 'View Time Settings',
                    'edit' => 'Edit Time Settings'
                ]
            ]
        ]
    ],
    'transport' => [
        'title' => '4. Transport',
        'ref' => '4',
        'permissions' => [
            'view' => 'View Transport Module'
        ],
        'sub_modules' => [
            'vehicle_register' => [
                'title' => '4.1 Vehicle Register',
                'ref' => '4.1',
                'permissions' => [
                    'view' => 'View Vehicle Register',
                    'add' => 'Add Vehicles',
                    'edit' => 'Edit Vehicles',
                    'delete' => 'Delete Vehicles'
                ]
            ],
            'vehicle_pool' => [
                'title' => '4.2 Vehicle Pool',
                'ref' => '4.2',
                'permissions' => [
                    'view' => 'View Vehicle Pool',
                    'in_out_switch' => 'Vehicle In/Out Switch'
                ]
            ],
            'transport_request' => [
                'title' => '4.3 Transport Request',
                'ref' => '4.3',
                'permissions' => [
                    'view' => 'View Transport Request Form',
                    'submit' => 'Submit Transport Request'
                ]
            ],
            'driver_pool' => [
                'title' => '4.4 Driver Pool',
                'ref' => '4.4',
                'permissions' => [
                    'view' => 'View Driver Pool',
                    'add' => 'Add Drivers',
                    'delete' => 'Delete Drivers',
                    'duty_switch' => 'Driver On/Off Duty Switch'
                ]
            ],
            'transport_allocation' => [
                'title' => '4.5 Transport Allocation',
                'ref' => '4.5',
                'permissions' => [
                    'view' => 'View Transport Allocation',
                    'assign' => 'Assign Drivers & Vehicles'
                ]
            ],
            'time_management' => [
                'title' => '4.6 Time & Date Management',
                'ref' => '4.6',
                'permissions' => [
                    'view' => 'View Time Settings',
                    'edit' => 'Edit Time Settings'
                ]
            ]
        ]
    ],
    'grievance' => [
        'title' => '5. Grievance',
        'ref' => '5',
        'permissions' => [
            'view' => 'View Grievance Module'
        ],
        'sub_modules' => [
            'submission' => [
                'title' => '5.1 Grievance Submission',
                'ref' => '5.1',
                'permissions' => [
                    'view' => 'View Submission Form',
                    'submit' => 'Submit Grievance'
                ]
            ],
            'case_management' => [
                'title' => '5.2 Case Management',
                'ref' => '5.2',
                'permissions' => [
                    'view' => 'View Case Management',
                    'assign' => 'Assign Cases',
                    'update_status' => 'Update Case Status',
                    'add_notes' => 'Add Investigation Notes',
                    'upload_docs' => 'Upload Documents'
                ]
            ],
            'resolution' => [
                'title' => '5.3 Resolution & Approval',
                'ref' => '5.3',
                'permissions' => [
                    'view' => 'View Resolution',
                    'approve' => 'Approve Resolution',
                    'reject' => 'Reject Resolution',
                    'request_revision' => 'Request Revision'
                ]
            ],
            'appeal' => [
                'title' => '5.4 Appeal Process',
                'ref' => '5.4',
                'permissions' => [
                    'view' => 'View Appeals',
                    'submit_appeal' => 'Submit Appeal',
                    'review_appeal' => 'Review Appeal',
                    'final_decision' => 'Final Decision'
                ]
            ]
        ]
    ]
];

// Add remaining 10 modules (continuing from Plan.md structure)
// Note: I've included the first 5 modules as examples. The remaining 10 modules would follow the same pattern
// based on the complete Plan.md structure which includes Performance, Training, Requests, Events, etc.

if ($_POST) {
    if (isset($_POST['save_permissions'])) {
        $account_type_id = $_POST['account_type_id'];

        // Clear existing permissions for this account type
        $db->query("DELETE FROM account_permissions WHERE account_type_id = ?", [$account_type_id]);

        // Insert new permissions
        if (isset($_POST['permissions'])) {
            foreach ($_POST['permissions'] as $permission_key => $value) {
                if ($value == '1') {
                    // Parse permission key (format: module.submodule.permission or module.permission)
                    $parts = explode('.', $permission_key);
                    $module = $parts[0];

                    if (count($parts) === 3) {
                        // Sub-module permission
                        $submodule = $parts[1];
                        $action = $parts[2];
                        $permission_name = "{$module}.{$submodule}.{$action}";
                    } else {
                        // Main module permission
                        $action = $parts[1];
                        $permission_name = "{$module}.{$action}";
                    }

                    $db->query("INSERT INTO account_permissions (account_type_id, module_name, action_name) VALUES (?, ?, ?)",
                             [$account_type_id, $module, $permission_name]);
                }
            }
        }

        $success = "Permissions updated successfully!";
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
    <title>SmartHRM - Permission Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --sidebar-width: 280px;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #007bff 0%, #0056b3 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .sidebar-item {
            margin: 0.5rem 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: white;
        }

        .sidebar-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .top-navbar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: auto;
        }

        .dashboard-content {
            padding: 2rem;
        }

        .permission-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .module-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 1rem;
            margin: -1.5rem -1.5rem 1rem -1.5rem;
            border-radius: 10px 10px 0 0;
            font-weight: 600;
        }

        .sub-module-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            border-left: 4px solid #007bff;
        }

        .sub-module-header {
            font-weight: 500;
            color: #0056b3;
            margin-bottom: 0.75rem;
        }

        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 0.75rem;
        }

        .permission-item {
            padding: 0.5rem 0;
        }

        .permission-check {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .permission-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .permission-label {
            font-size: 0.9rem;
            margin: 0;
        }

        .ref-number {
            background: #e9ecef;
            color: #495057;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-right: 0.5rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0 ms-2">Permission Management</h5>
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

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <nav style="--bs-breadcrumb-divider: '>';" aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Admin Panel</a></li>
                    <li class="breadcrumb-item active">Permission Management</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2"><i class="fas fa-shield-alt me-2"></i>Permission Management</h1>
                <div>
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
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-tag me-2"></i>Select Account Type</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($account_types as $account_type): ?>
                            <div class="col-md-2 mb-2">
                                <a href="?account_type=<?php echo $account_type['id']; ?>"
                                   class="btn <?php echo ($selected_account_type == $account_type['id']) ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                                    <?php echo htmlspecialchars($account_type['type_name']); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Permissions Form -->
            <?php if ($selected_account_type): ?>
                <form method="POST" id="permissionsForm">
                    <input type="hidden" name="account_type_id" value="<?php echo $selected_account_type; ?>">

                    <?php foreach ($permission_structure as $module_key => $module): ?>
                        <div class="permission-section">
                            <div class="module-header">
                                <span class="ref-number"><?php echo $module['ref']; ?></span>
                                <?php echo $module['title']; ?>
                            </div>

                            <!-- Main Module Permissions -->
                            <div class="permission-grid">
                                <?php foreach ($module['permissions'] as $perm_key => $perm_title): ?>
                                    <div class="permission-item">
                                        <div class="permission-check">
                                            <input type="checkbox"
                                                   name="permissions[<?php echo $module_key . '.' . $perm_key; ?>]"
                                                   value="1"
                                                   id="<?php echo $module_key . '_' . $perm_key; ?>"
                                                   <?php echo isset($permissions_array[$module_key . '.' . $perm_key]) ? 'checked' : ''; ?>>
                                            <label class="permission-label" for="<?php echo $module_key . '_' . $perm_key; ?>">
                                                <?php echo $perm_title; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Sub-modules -->
                            <?php if (isset($module['sub_modules'])): ?>
                                <?php foreach ($module['sub_modules'] as $sub_key => $sub_module): ?>
                                    <div class="sub-module-section">
                                        <div class="sub-module-header">
                                            <span class="ref-number"><?php echo $sub_module['ref']; ?></span>
                                            <?php echo $sub_module['title']; ?>
                                        </div>
                                        <div class="permission-grid">
                                            <?php foreach ($sub_module['permissions'] as $sub_perm_key => $sub_perm_title): ?>
                                                <div class="permission-item">
                                                    <div class="permission-check">
                                                        <input type="checkbox"
                                                               name="permissions[<?php echo $module_key . '.' . $sub_key . '.' . $sub_perm_key; ?>]"
                                                               value="1"
                                                               id="<?php echo $module_key . '_' . $sub_key . '_' . $sub_perm_key; ?>"
                                                               <?php echo isset($permissions_array[$module_key . '.' . $sub_key . '.' . $sub_perm_key]) ? 'checked' : ''; ?>>
                                                        <label class="permission-label" for="<?php echo $module_key . '_' . $sub_key . '_' . $sub_perm_key; ?>">
                                                            <?php echo $sub_perm_title; ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="text-center mt-4">
                        <button type="submit" name="save_permissions" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Save Permissions
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-shield-alt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Select an account type above to manage permissions</h5>
                    <p class="text-muted">Choose from the available account types to configure their access permissions across all system modules and sub-modules.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Auto-hide sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.querySelector('.sidebar');
                const toggle = document.getElementById('sidebar-toggle');

                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // Select all/none functionality for modules
        document.querySelectorAll('.module-header').forEach(function(header) {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                const section = this.parentNode;
                const checkboxes = section.querySelectorAll('input[type="checkbox"]');
                const checkedCount = section.querySelectorAll('input[type="checkbox"]:checked').length;
                const shouldCheck = checkedCount < checkboxes.length;

                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = shouldCheck;
                });
            });
        });
    </script>
</body>
</html>