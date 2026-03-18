<?php
// This is the old password management system - redirect to the enhanced version
header('Location: password_management_enhanced.php');
exit();
?>
$db = new Database();

$default_password = DEFAULT_PASSWORD;

// Initialize error and success variables
$error = null;
$success = null;

if ($_POST) {
    if (isset($_POST['reset_password'])) {
        $employee_id = $_POST['employee_id'] ?? null;
        $account_type_id = $_POST['account_type_id'] ?? 1; // Default to User if not set
        $new_password = isset($_POST['custom_password']) && !empty($_POST['custom_password'])
                       ? $_POST['custom_password']
                       : $default_password;

        // Validate required fields
        if (empty($employee_id) || empty($account_type_id)) {
            $error = "Missing required fields. Please try again.";
        } else {

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Get employee details
        $employee = $db->fetch("SELECT * FROM employees WHERE id = ?", [$employee_id]);

        if ($employee) {
            // Update employee record with account activation and password
            $result = $db->query(
                "UPDATE employees SET
                    account_type_id = ?,
                    password_hash = ?,
                    is_active = 1,
                    updated_at = NOW()
                WHERE id = ?",
                [$account_type_id, $hashed_password, $employee_id]
            );

            if ($result) {
                // Get account type name for success message
                $account_type = $db->fetch("SELECT type_name FROM account_types WHERE id = ?", [$account_type_id]);
                $account_type_name = $account_type ? $account_type['type_name'] : 'Unknown';

                $action = $employee['password_hash'] ? 'Password reset' : 'Account activated';
                $success = "{$action} successfully! Account type set to: {$account_type_name}";
            } else {
                $error = "Failed to update employee account.";
            }
        } else {
            $error = "Employee not found.";
        }
        } // Close validation block
    }

    if (isset($_POST['deactivate_account'])) {
        $employee_id = $_POST['employee_id'] ?? null;

        if (empty($employee_id)) {
            $error = "Missing employee ID. Please try again.";
        } else {
            $result = $db->query(
                "UPDATE employees SET
                    account_type_id = 1,
                    password_hash = NULL,
                    is_active = 0,
                    updated_at = NOW()
                WHERE id = ?",
                [$employee_id]
            );

            if ($result) {
                $success = "Account deactivated successfully!";
            } else {
                $error = "Failed to deactivate account.";
            }
        }
    }
}

// Get account types for dropdown
$account_types = $db->fetchAll("SELECT * FROM account_types ORDER BY level ASC");

// Get all employees with their account status
$employees = $db->fetchAll("
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
        at.type_name as account_type_name,
        at.level as account_level,
        l.location_name
    FROM employees e
    LEFT JOIN account_types at ON e.account_type_id = at.id
    LEFT JOIN locations l ON e.location_id = l.id
    ORDER BY e.name ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Password Management & Account Activation</title>
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

        .status-active {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-inactive {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .account-level-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.4rem;
        }

        .level-1 { background-color: #6c757d; }
        .level-2 { background-color: #17a2b8; }
        .level-3 { background-color: #ffc107; color: #000; }
        .level-4 { background-color: #fd7e14; }
        .level-5 { background-color: #dc3545; }

        .employee-card {
            transition: transform 0.2s;
        }

        .employee-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
                <h5 class="mb-0 ms-2">Password Management & Account Activation</h5>
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
                <h1 class="h2"><i class="fas fa-key me-2"></i>Password Management & Account Activation</h1>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </a>
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

            <!-- Account Statistics -->
            <div class="row mb-4">
                <?php
                $stats = [
                    'active' => 0,
                    'inactive' => 0,
                    'by_level' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
                ];

                foreach ($employees as $emp) {
                    if ($emp['password_hash'] && $emp['is_active']) {
                        $stats['active']++;
                        $level = $emp['account_level'] ?? 1;
                        $stats['by_level'][$level]++;
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
                            <i class="fas fa-crown fa-2x text-warning mb-2"></i>
                            <h3 class="h2 text-warning"><?php echo $stats['by_level'][5]; ?></h3>
                            <p class="text-muted">Superadmins</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-user-shield fa-2x text-info mb-2"></i>
                            <h3 class="h2 text-info"><?php echo $stats['by_level'][4]; ?></h3>
                            <p class="text-muted">Admins</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Default Password Info -->
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Default Password:</strong> <code><?php echo htmlspecialchars($default_password); ?></code>
                <br>
                <small>This password is automatically assigned to new employee accounts. Username is the EPF number.</small>
            </div>

            <!-- Employee Accounts List -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Employee Account Management</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Employee</th>
                                    <th>EPF</th>
                                    <th>Location</th>
                                    <th>Designation</th>
                                    <th>Account Status</th>
                                    <th>Account Type</th>
                                    <th>Actions</th>
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
                                            <td><?php echo htmlspecialchars($employee['designation'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if ($hasAccount): ?>
                                                    <span class="account-status status-active">
                                                        <i class="fas fa-check-circle me-1"></i>Active
                                                    </span>
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
                                                <?php if (!$isCurrentUser): ?>
                                                    <button class="btn btn-sm btn-primary me-1"
                                                            onclick="manageAccount(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($employee['epf_number']); ?>', <?php echo $hasAccount ? 'true' : 'false'; ?>, <?php echo $employee['account_type_id'] ?? 1; ?>)">
                                                        <i class="fas fa-<?php echo $hasAccount ? 'key' : 'user-plus'; ?> me-1"></i>
                                                        <?php echo $hasAccount ? 'Reset' : 'Activate'; ?>
                                                    </button>

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

    <!-- Account Management Modal -->
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
                                    <option value="<?php echo $type['id']; ?>" data-level="<?php echo $type['level']; ?>">
                                        <?php echo htmlspecialchars($type['type_name']); ?> (Level <?php echo $type['level']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Select the appropriate access level for this user</div>
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
                                    Use custom password
                                </label>
                            </div>
                        </div>

                        <div class="mb-3" id="custom_password_field" style="display: none;">
                            <label class="form-label">Custom Password</label>
                            <input type="password" class="form-control" name="custom_password" id="custom_password" minlength="6">
                            <div class="form-text">Minimum 6 characters required</div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="action_description"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reset_password" class="btn btn-primary">
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
                if (this.value === 'custom') {
                    customField.style.display = 'block';
                    document.getElementById('custom_password').required = true;
                } else {
                    customField.style.display = 'none';
                    document.getElementById('custom_password').required = false;
                    document.getElementById('custom_password').value = '';
                }
            });
        });

        function manageAccount(employeeId, employeeName, epfNumber, hasAccount, currentAccountType) {
            document.getElementById('employee_id').value = employeeId;
            document.getElementById('employee_info').innerHTML = `<strong>${employeeName}</strong><br>EPF: ${epfNumber}`;

            // Set current account type
            document.getElementById('account_type_id').value = currentAccountType || 1;

            if (hasAccount) {
                document.getElementById('modal_title').textContent = 'Reset Password & Update Account';
                document.getElementById('action_description').textContent = 'This will reset the password and update account type for this existing user account.';
                document.getElementById('action_button_text').textContent = 'Update Account';
            } else {
                document.getElementById('modal_title').textContent = 'Activate Account';
                document.getElementById('action_description').innerHTML = `This will create a new active account with username <strong>${epfNumber}</strong>, selected account type, and the chosen password.`;
                document.getElementById('action_button_text').textContent = 'Activate Account';
            }

            // Reset form
            document.getElementById('use_default').checked = true;
            document.getElementById('custom_password_field').style.display = 'none';
            document.getElementById('custom_password').value = '';
            document.getElementById('custom_password').required = false;

            new bootstrap.Modal(document.getElementById('accountModal')).show();
        }

        function deactivateAccount(employeeId, employeeName) {
            document.getElementById('deactivate_employee_id').value = employeeId;
            document.getElementById('deactivate_employee_name').textContent = employeeName;

            new bootstrap.Modal(document.getElementById('deactivateModal')).show();
        }
    </script>
</body>
</html>