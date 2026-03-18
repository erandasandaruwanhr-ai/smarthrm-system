<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/enhanced_auth_middleware.php';

// Check permission using enhanced system
enforcePermission('employee.view');

$user = getCurrentUser();
$middleware = new PermissionMiddleware(new Database());

// Get filter parameters
$location_filter = $_GET['location'] ?? '';
$department_filter = $_GET['department'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$employment_level_filter = $_GET['employment_level'] ?? '';
$status_filter = $_GET['status'] ?? 'active';
$search = $_GET['search'] ?? '';

// Build filters array
$filters = [];
if ($location_filter) $filters['location_id'] = $location_filter;
if ($department_filter) $filters['department'] = $department_filter;
if ($gender_filter) $filters['gender'] = $gender_filter;
if ($employment_level_filter) $filters['employment_level_id'] = $employment_level_filter;
if ($status_filter) $filters['status'] = $status_filter;
if ($search) $filters['search'] = $search;

// Get accessible employees using enhanced permission system
$employees = $middleware->getAccessibleData('employees', $filters);

// Get filter options based on user's permissions
$locations = $middleware->getAccessibleData('locations');
$departments = $middleware->getAccessibleData('departments');
$employment_levels = (new Database())->fetchAll("SELECT * FROM employment_levels WHERE is_active = 1 ORDER BY level_order");

// Handle messages
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Check various permissions for UI elements
$canAddEmployee = checkPermission('employee.add');
$canBulkUpload = checkPermission('employee.bulk_upload');
$canExport = checkPermission('employee.export');
$canDeleteAny = checkPermission('employee.delete');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Employee List (Enhanced)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .permission-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            border-radius: 0.25rem;
        }
        .access-level-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.75rem;
        }
        .data-scope-info {
            background: linear-gradient(45deg, #17a2b8, #138496);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #007bff, #0056b3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }
        .permission-debug {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            max-width: 300px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-navbar position-relative">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0 ms-2">Employee List (Enhanced Permissions)</h5>
            </div>

            <!-- Access Level Indicator -->
            <div class="access-level-indicator">
                <span class="badge bg-<?php echo ENHANCED_ACCOUNT_TYPES[$user['account_level'] ?? 1]['color'] ?? 'secondary'; ?>">
                    <?php echo ENHANCED_ACCOUNT_TYPES[$user['account_level'] ?? 1]['name'] ?? 'Unknown'; ?> -
                    <?php echo ENHANCED_ACCOUNT_TYPES[$user['account_level'] ?? 1]['data_access'] ?? 'SELF'; ?> Access
                </span>
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
                    <li class="breadcrumb-item"><a href="index.php">Employee Management</a></li>
                    <li class="breadcrumb-item active">Employee List (Enhanced)</li>
                </ol>
            </nav>

            <!-- Data Scope Information -->
            <div class="data-scope-info">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="mb-1"><i class="fas fa-shield-alt me-2"></i>Your Data Access Scope</h6>
                        <p class="mb-0">
                            As a <strong><?php echo ENHANCED_ACCOUNT_TYPES[$user['account_level'] ?? 1]['name']; ?></strong>,
                            you can access <strong><?php echo ENHANCED_ACCOUNT_TYPES[$user['account_level'] ?? 1]['data_access']; ?></strong> level data.
                            Showing <?php echo count($employees); ?> accessible employees.
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex gap-2 justify-content-end">
                            <?php if (isset($_GET['debug']) && $user['account_level'] >= 4): ?>
                                <a href="?" class="btn btn-sm btn-outline-light">
                                    <i class="fas fa-eye-slash me-1"></i>Hide Debug
                                </a>
                            <?php elseif ($user['account_level'] >= 4): ?>
                                <a href="?debug=1" class="btn btn-sm btn-outline-light">
                                    <i class="fas fa-bug me-1"></i>Debug Mode
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Header with Actions -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3"><i class="fas fa-users me-2"></i>Employee List</h1>
                    <p class="text-muted">Showing <?php echo count($employees); ?> employees based on your access level</p>
                </div>
                <div>
                    <?php echo renderPermissionButton('employee.add', [], 'employee_form.php', 'Add Employee', 'btn-primary', 'fas fa-plus'); ?>
                    <?php echo renderPermissionButton('employee.bulk_upload', [], 'bulk_upload.php', 'Bulk Upload', 'btn-success ms-2', 'fas fa-upload'); ?>
                    <?php if ($canExport): ?>
                        <button class="btn btn-info ms-2" onclick="exportData()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                    <?php endif; ?>
                    <a href="employee_list.php" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-arrow-left me-2"></i>Original View
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search"
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   placeholder="EPF, Name, Designation...">
                        </div>

                        <?php if (!empty($locations)): ?>
                        <div class="col-md-2">
                            <label class="form-label">Location</label>
                            <select class="form-select" name="location">
                                <option value="">All Accessible Locations</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>"
                                            <?php echo $location_filter == $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['location_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($departments)): ?>
                        <div class="col-md-2">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Accessible Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                                            <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-2">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender">
                                <option value="">All Genders</option>
                                <option value="Male" <?php echo $gender_filter == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $gender_filter == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Employment Level</label>
                            <select class="form-select" name="employment_level">
                                <option value="">All Levels</option>
                                <?php foreach ($employment_levels as $level): ?>
                                    <option value="<?php echo $level['id']; ?>"
                                            <?php echo $employment_level_filter == $level['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($level['level_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-1">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                            <a href="employee_list_enhanced.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-refresh me-2"></i>Reset
                            </a>
                            <?php if (isset($_GET['debug'])): ?>
                                <input type="hidden" name="debug" value="1">
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Employee Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-primary">
                                <tr>
                                    <th>Employee</th>
                                    <th>EPF No</th>
                                    <th>Designation</th>
                                    <th>Department</th>
                                    <th>Employment Level</th>
                                    <th>Location</th>
                                    <th>Access Control</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($employees)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No employees accessible with your current permission level</p>
                                            <small class="text-muted">
                                                Contact your administrator if you believe you should have access to more employee data.
                                            </small>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($employees as $employee): ?>
                                        <?php
                                        $employee_epf = $employee['epf_number'];
                                        $canView = canViewEmployee($employee_epf);
                                        $canEdit = canEditEmployee($employee_epf);
                                        $canDelete = checkPermission('employee.delete', ['employee_epf' => $employee_epf]);
                                        $isSelf = $employee_epf === ($user['epf_number'] ?? '');
                                        ?>
                                        <tr class="<?php echo $isSelf ? 'table-warning' : ''; ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="employee-avatar">
                                                        <?php echo strtoupper(substr($employee['name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold">
                                                            <?php echo htmlspecialchars($employee['name']); ?>
                                                            <?php if ($isSelf): ?>
                                                                <span class="badge bg-primary ms-1">You</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            <i class="fas fa-venus-mars me-1"></i><?php echo $employee['gender']; ?> |
                                                            <i class="fas fa-calendar me-1"></i><?php echo $employee['age']; ?> years
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($employee['epf_number']); ?></code>
                                            </td>
                                            <td><?php echo htmlspecialchars($employee['designation'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars($employee['department']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($employee['employment_level_name'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <i class="fas fa-map-marker-alt text-primary me-1"></i>
                                                <?php echo htmlspecialchars($employee['location_name']); ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    <?php if ($canView): ?>
                                                        <span class="permission-badge bg-success text-white">
                                                            <i class="fas fa-eye me-1"></i>View
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($canEdit): ?>
                                                        <span class="permission-badge bg-warning text-dark">
                                                            <i class="fas fa-edit me-1"></i>Edit
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($canDelete): ?>
                                                        <span class="permission-badge bg-danger text-white">
                                                            <i class="fas fa-trash me-1"></i>Delete
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($employee['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($canView): ?>
                                                        <button type="button" class="btn btn-outline-info"
                                                                title="View Details"
                                                                onclick="viewEmployee('<?php echo $employee['id']; ?>')">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if ($canEdit): ?>
                                                        <a href="employee_form.php?id=<?php echo $employee['id']; ?>"
                                                           class="btn btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if ($canDelete && !$isSelf): ?>
                                                        <button type="button" class="btn btn-outline-danger"
                                                                title="Delete"
                                                                onclick="deleteEmployee('<?php echo $employee['id']; ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
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

    <!-- Employee Details Modal -->
    <div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="employeeModalBody">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Debug Panel -->
    <?php if (isset($_GET['debug']) && $user['account_level'] >= 4): ?>
        <div class="permission-debug">
            <strong>Permission Debug Info:</strong><br>
            User: <?php echo htmlspecialchars($user['name']); ?><br>
            Account Level: <?php echo $user['account_level'] ?? 1; ?><br>
            Data Access: <?php echo ENHANCED_ACCOUNT_TYPES[$user['account_level'] ?? 1]['data_access']; ?><br>
            Employees Shown: <?php echo count($employees); ?><br>
            <small>Only visible to Admin+ users</small>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewEmployee(id) {
            fetch('view_employee.php?id=' + id)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('employeeModalBody').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('employeeModal')).show();
                })
                .catch(error => {
                    alert('Error loading employee details: ' + error.message);
                });
        }

        function deleteEmployee(id) {
            if (confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
                window.location.href = 'delete_employee.php?id=' + id;
            }
        }

        function exportData() {
            const form = document.querySelector('form');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            window.location.href = 'export.php?' + params.toString();
        }

        // Sidebar toggle
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>