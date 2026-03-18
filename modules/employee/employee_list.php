<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to access employee list
requirePermission('employee', 'employee_list');

$user = getCurrentUser();

// Get filter parameters
$location_filter = $_GET['location'] ?? '';
$department_filter = $_GET['department'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$employment_level_filter = $_GET['employment_level'] ?? '';
$status_filter = $_GET['status'] ?? 'active';
$search = $_GET['search'] ?? '';

// Build WHERE clause for filters
$where_conditions = ["1=1"];
$params = [];

// Apply location/department access control
$accessible_employees = getAccessibleEmployeesByLocation($db, $user);
$accessible_epf_numbers = array_column($accessible_employees, 'epf_number');

if (empty($accessible_epf_numbers)) {
    $where_conditions[] = "0=1"; // No access to any employees
} else {
    $epf_placeholders = str_repeat('?,', count($accessible_epf_numbers) - 1) . '?';
    $where_conditions[] = "e.epf_number IN ($epf_placeholders)";
    $params = array_merge($params, $accessible_epf_numbers);
}

// Additional filters
if ($location_filter) {
    $where_conditions[] = "e.location_id = ?";
    $params[] = $location_filter;
}

if ($department_filter) {
    $where_conditions[] = "e.department = ?";
    $params[] = $department_filter;
}

if ($gender_filter) {
    $where_conditions[] = "e.gender = ?";
    $params[] = $gender_filter;
}

if ($employment_level_filter) {
    $where_conditions[] = "e.employment_level_id = ?";
    $params[] = $employment_level_filter;
}

if ($status_filter === 'active') {
    $where_conditions[] = "e.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "e.is_active = 0";
}

if ($search) {
    $where_conditions[] = "(e.epf_number LIKE ? OR e.name LIKE ? OR e.designation LIKE ? OR e.department LIKE ? OR e.nic LIKE ?)";
    $search_term = '%' . $search . '%';
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

$where_clause = implode(' AND ', $where_conditions);

// Get employees with joins for readable names
$query = "
    SELECT e.*,
           l.location_name,
           el.level_name as employment_level_name,
           supervisor.name as supervisor_name
    FROM employees e
    LEFT JOIN locations l ON e.location_id = l.id
    LEFT JOIN employment_levels el ON e.employment_level_id = el.id
    LEFT JOIN employees supervisor ON e.reports_to = supervisor.epf_number
    WHERE $where_clause
    ORDER BY CAST(e.epf_number AS UNSIGNED) ASC
";

$employees = $db->fetchAll($query, $params);

// Get filter options
$locations = $db->fetchAll("SELECT * FROM locations WHERE is_active = 1 ORDER BY location_name");
$employment_levels = $db->fetchAll("SELECT * FROM employment_levels WHERE is_active = 1 ORDER BY level_order");
$departments = $db->fetchAll("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department");

// Handle messages
$message = $_GET['message'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Employee List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .dashboard-content {
            background: #f8f9fa;
            padding: 2rem;
            min-height: calc(100vh - 40px);
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50px, -50px);
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
            position: relative;
            z-index: 2;
        }

        .page-header .d-flex {
            position: relative;
            z-index: 2;
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .page-header-logo {
            height: 60px;
            width: auto;
            background: rgba(255, 255, 255, 0.9);
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 2;
        }

        .form-card, .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .card-header-modern {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 1px solid #dee2e6;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem 2rem;
            margin: -2rem -2rem 2rem -2rem;
        }

        .card-header-modern h5 {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
        }

        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
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

        .badge-level {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .dashboard-content {
                padding: 1rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-list me-3"></i>Employee List</h1>
                        <p>View and manage all employee records with filtering and search capabilities</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Employee Management</a></li>
                    <li class="breadcrumb-item active">Employee List</li>
                </ol>
            </nav>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>


            <!-- Header with Actions -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3><i class="fas fa-users me-2"></i>Employees Overview</h3>
                    <p class="text-muted">Showing <?php echo count($employees); ?> employees</p>
                </div>
                <div>
                    <?php if (hasModulePermission($db, 'employee.add') || hasModulePermission($db, 'employee.add_employee')): ?>
                    <a href="employee_form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Employee
                    </a>
                    <?php endif; ?>
                    <?php if (hasModulePermission($db, 'employee.bulk_upload')): ?>
                    <a href="bulk_upload.php" class="btn btn-success ms-2">
                        <i class="fas fa-upload me-2"></i>Bulk Upload
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filters -->
            <div class="content-card">
                <div class="card-header-modern">
                    <h5><i class="fas fa-filter me-2"></i>Filter Options</h5>
                </div>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search"
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   placeholder="EPF, Name, Designation...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Location</label>
                            <select class="form-select" name="location">
                                <option value="">All Locations</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>"
                                            <?php echo $location_filter == $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['location_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                                            <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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
                            <?php if (hasModulePermission($db, 'employee.filter_search')): ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                            <a href="employee_list.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-refresh me-2"></i>Reset
                            </a>
                            <?php endif; ?>
                            <?php if (hasModulePermission($db, 'employee.export') || hasModulePermission($db, 'employee.export_employees')): ?>
                            <button type="button" class="btn btn-outline-success ms-2" onclick="exportData()">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
            </div>

            <!-- Employee Table -->
            <div class="content-card">
                <div class="card-header-modern">
                    <h5><i class="fas fa-table me-2"></i>Employee Records</h5>
                </div>
                <div class="p-0">
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
                                    <th>Reports To</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($employees)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No employees found matching your criteria</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($employees as $employee): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="employee-avatar">
                                                        <?php echo strtoupper(substr($employee['name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($employee['name']); ?></div>
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
                                            <td><?php echo htmlspecialchars($employee['designation']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars($employee['department']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-level bg-info">
                                                    <?php echo htmlspecialchars($employee['employment_level_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <i class="fas fa-map-marker-alt text-primary me-1"></i>
                                                <?php echo htmlspecialchars($employee['location_name']); ?>
                                            </td>
                                            <td>
                                                <?php if ($employee['supervisor_name']): ?>
                                                    <small><?php echo htmlspecialchars($employee['supervisor_name']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
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
                                                    <?php if (hasModulePermission($db, 'employee.edit')): ?>
                                                    <a href="employee_form.php?id=<?php echo $employee['id']; ?>"
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <?php if (hasModulePermission($db, 'employee.view_details')): ?>
                                                    <button type="button" class="btn btn-outline-info"
                                                            title="View Details"
                                                            onclick="viewEmployee(<?php echo $employee['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php if (hasModulePermission($db, 'employee.edit')): ?>
                                                    <button type="button"
                                                            class="btn <?php echo $employee['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                                                            title="<?php echo $employee['is_active'] ? 'Make Inactive' : 'Make Active'; ?>"
                                                            onclick="toggleEmployeeStatus(<?php echo $employee['id']; ?>, <?php echo $employee['is_active'] ? '0' : '1'; ?>)">
                                                        <i class="fas <?php echo $employee['is_active'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php if (hasModulePermission($db, 'employee.delete')): ?>
                                                        <button type="button" class="btn btn-outline-danger"
                                                                title="Delete"
                                                                onclick="deleteEmployee(<?php echo $employee['id']; ?>)">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewEmployee(id) {
            fetch('view_employee.php?id=' + id)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('employeeModalBody').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('employeeModal')).show();
                });
        }

        function deleteEmployee(id) {
            if (confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
                window.location.href = 'delete_employee.php?id=' + id;
            }
        }

        function toggleEmployeeStatus(id, newStatus) {
            const action = newStatus === 1 ? 'activate' : 'make inactive';
            const message = newStatus === 1 ? 'activate this employee' : 'make this employee inactive';

            if (confirm(`Are you sure you want to ${message}? Their details will be preserved.`)) {
                window.location.href = 'toggle_employee_status.php?id=' + id + '&status=' + newStatus;
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