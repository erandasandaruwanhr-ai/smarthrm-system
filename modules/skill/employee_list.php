<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once 'hierarchy_functions.php';

$user = getCurrentUser();
$db = new Database();

// Check permissions
$isAdmin = isAdmin() || isSuperAdmin();

// Check if user is a manager by employment level (level 3 = Manager, level 2 = GM)
$userEmploymentLevel = $db->fetch("SELECT employment_level_id FROM employees WHERE epf_number = ?", [$user['epf_number']]);
$isManager = ($userEmploymentLevel && ($userEmploymentLevel['employment_level_id'] == 3 || $userEmploymentLevel['employment_level_id'] == 2));

$canViewSkills = $isAdmin || hasModulePermission($db, 'skill.view_skill_matrix') || hasModulePermission($db, 'skill.employee_list');

if (!$canViewSkills) {
    header("Location: index.php");
    exit();
}

// Get employee list based on user role
if ($isAdmin) {
    // Admin can see all employees
    $employees = getEmployeeListWithActualManagers($db);
} elseif ($isManager) {
    // Manager can only see employees under their management
    $employees = getEmployeesUnderManager($db, $user['epf_number']);
} else {
    // Regular users see employees from their location
    $employees = getEmployeeListWithActualManagers($db);
    $userLocation = $user['location_id'];
    $employees = array_filter($employees, function($employee) use ($userLocation) {
        return $employee['location_id'] == $userLocation;
    });
}

// Export to Excel functionality
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="employee_skill_matrix_list_' . date('Y-m-d') . '.xls"');

    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>EPF No</th>";
    echo "<th>Name</th>";
    echo "<th>Designation</th>";
    echo "<th>Department</th>";
    echo "<th>Location</th>";
    echo "<th>Manager EPF No</th>";
    echo "<th>Manager Name</th>";
    echo "<th>Joining Date</th>";
    echo "<th>Service Years</th>";
    echo "</tr>";

    foreach ($employees as $employee) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($employee['epf_number']) . "</td>";
        echo "<td>" . htmlspecialchars($employee['name']) . "</td>";
        echo "<td>" . htmlspecialchars($employee['designation']) . "</td>";
        echo "<td>" . htmlspecialchars($employee['department']) . "</td>";
        echo "<td>" . htmlspecialchars($employee['location_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($employee['manager_epf'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($employee['manager_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($employee['joined_date']) . "</td>";
        echo "<td>" . htmlspecialchars($employee['service_years']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Employee List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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

        .data-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            padding: 1.5rem;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }

        .btn-custom {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
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
                        <h1><i class="fas fa-users me-3"></i>Employee List</h1>
                        <p>View all employees eligible for skill assessment with their manager relationships</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Skill Matrix</a></li>
                    <li class="breadcrumb-item active">Employee List</li>
                </ol>
            </nav>

                <!-- Stats Card -->
                <div class="stats-card">
                    <div class="row">
                        <div class="col-md-8">
                            <h4><i class="fas fa-users me-2"></i>Employee List</h4>
                            <p class="mb-0">
                                <?php if ($isAdmin): ?>
                                    Complete list of all employees eligible for skill matrix assessment
                                <?php elseif ($isManager): ?>
                                    Employees under your management eligible for skill matrix assessment
                                <?php else: ?>
                                    Employees in your location eligible for skill matrix assessment
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <h2 class="mb-0"><?php echo count($employees); ?></h2>
                            <small>Total Employees</small>
                        </div>
                    </div>
                </div>

                <!-- Debug Info (temporary) -->
                <?php if (count($employees) === 0): ?>
                <div class="alert alert-info">
                    <strong>Debug Information:</strong><br>
                    User Role: <?php echo htmlspecialchars($user['role'] ?? 'Unknown'); ?><br>
                    User EPF: <?php echo htmlspecialchars($user['epf_number'] ?? 'Unknown'); ?><br>
                    Is Admin: <?php echo $isAdmin ? 'Yes' : 'No'; ?><br>
                    Is Manager: <?php echo $isManager ? 'Yes' : 'No'; ?><br>
                    Filter Type: <?php
                        if ($isAdmin) echo 'All Employees';
                        elseif ($isManager) echo 'Manager\'s Direct Reports';
                        else echo 'Location-based';
                    ?>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Employee Directory</h5>
                    <div>
                        <a href="?export=excel" class="btn btn-success">
                            <i class="fas fa-file-excel me-2"></i>Export to Excel
                        </a>
                        <a href="assessment_form.php" class="btn btn-primary">
                            <i class="fas fa-clipboard-check me-2"></i>Start Assessment
                        </a>
                    </div>
                </div>

                <!-- Employee Table -->
                <div class="data-card">
                    <div class="table-responsive">
                        <table id="employeeTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>EPF No</th>
                                    <th>Name</th>
                                    <th>Designation</th>
                                    <th>Department</th>
                                    <th>Location</th>
                                    <th>Manager EPF No</th>
                                    <th>Manager Name</th>
                                    <th>Joining Date</th>
                                    <th>Service Years</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($employee['epf_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['designation']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($employee['location_name'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($employee['manager_epf'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($employee['manager_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($employee['joined_date']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $employee['service_years']; ?> years</span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="assessment_form.php?employee=<?php echo urlencode($employee['epf_number']); ?>"
                                               class="btn btn-sm btn-primary" title="Assess Skills">
                                                <i class="fas fa-clipboard-check"></i>
                                            </a>
                                            <a href="reports.php?employee=<?php echo urlencode($employee['epf_number']); ?>"
                                               class="btn btn-sm btn-info" title="View Reports">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="d-flex justify-content-between mt-4">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                    <div>
                        <a href="setup_form.php" class="btn btn-warning">
                            <i class="fas fa-cogs me-2"></i>Setup Skills
                        </a>
                        <a href="assessment_form.php" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>New Assessment
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#employeeTable').DataTable({
                pageLength: 25,
                order: [[1, 'asc']],
                columnDefs: [
                    { orderable: false, targets: [9] }
                ],
                language: {
                    search: "Search employees:",
                    lengthMenu: "Show _MENU_ employees per page",
                    info: "Showing _START_ to _END_ of _Total_ employees",
                    emptyTable: "No employees found"
                }
            });
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>