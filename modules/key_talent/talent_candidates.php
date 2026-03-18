<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has candidates list permission
if (!isSuperAdmin() && !hasModulePermission($database, 'key_talent.candidates_list')) {
    header('Location: index.php');
    exit();
}

// Get filter parameters
$manager_filter = isset($_GET['manager_epf']) ? trim($_GET['manager_epf']) : '';

// Get list of managers for filter dropdown
$managers_query = "
    SELECT DISTINCT
        epf_number as manager_epf,
        name as manager_name
    FROM employees
    WHERE employment_level_id IN (2, 3)
    ORDER BY CAST(epf_number AS UNSIGNED)
";

$available_managers = $database->fetchAll($managers_query);

// Get talent candidates list (14.1 - all employees from 2.2 list)
try {
    /*
     * RECURSIVE MANAGER HIERARCHY LOOKUP LOGIC:
     * ==========================================
     * 1. Start with each employee's IMMEDIATE SUPERVISOR (not employee themselves)
     * 2. Keep going up through supervisor → supervisor → supervisor...
     * 3. Stop only when it finds a manager at level 2 (GM) or level 3 (Manager)
     * 4. Handle deep hierarchies up to 10 levels deep (prevents infinite loops)
     * 5. Show the ultimate manager - not just immediate supervisor
     *
     * FIXED LOGIC: Base case now starts with reports_to (immediate supervisor)
     * instead of the employee themselves, preventing self-assignment as manager.
     * This ensures every employee shows their true ultimate manager (GM or Manager)
     * regardless of how many levels are between them in the organizational hierarchy.
     */
    $candidates_query = "
        WITH RECURSIVE manager_hierarchy AS (
            -- Base case: start with each employee's immediate supervisor
            SELECT
                epf_number as employee_epf,
                reports_to as current_epf,
                1 as level_depth
            FROM employees
            WHERE reports_to IS NOT NULL

            UNION ALL

            -- Recursive case: go up the chain
            SELECT
                mh.employee_epf,
                e.reports_to as current_epf,
                mh.level_depth + 1
            FROM manager_hierarchy mh
            JOIN employees e ON mh.current_epf = e.epf_number
            WHERE e.employment_level_id NOT IN (2, 3)
            AND e.reports_to IS NOT NULL
            AND mh.level_depth < 10
        ),
        ultimate_managers AS (
            SELECT
                mh.employee_epf,
                e.epf_number as manager_epf,
                e.name as manager_name,
                ROW_NUMBER() OVER (PARTITION BY mh.employee_epf ORDER BY mh.level_depth) as rn
            FROM manager_hierarchy mh
            JOIN employees e ON mh.current_epf = e.epf_number
            WHERE e.employment_level_id IN (2, 3)
        )
        SELECT
            e.epf_number,
            e.name,
            e.designation,
            e.department,
            e.location_id as location,
            e.joined_date as date_of_joining,
            TIMESTAMPDIFF(YEAR, e.joined_date, CURDATE()) as service_years,
            TIMESTAMPDIFF(MONTH, e.joined_date, CURDATE()) % 12 as service_months,
            um.manager_epf,
            um.manager_name
        FROM employees e
        LEFT JOIN ultimate_managers um ON e.epf_number = um.employee_epf AND um.rn = 1" .
        ($manager_filter ? " WHERE um.manager_epf = ?" : "") . "
        ORDER BY CAST(e.epf_number AS UNSIGNED)
    ";

    $candidates = $database->fetchAll($candidates_query, $manager_filter ? [$manager_filter] : []);

    // Get summary statistics
    $total_candidates = count($candidates);
    $departments = array_unique(array_column($candidates, 'department'));
    $total_departments = count($departments);
    $candidates_with_managers = count(array_filter($candidates, function($c) { return !empty($c['manager_epf']); }));

} catch (Exception $e) {
    $candidates = [];
    $total_candidates = 0;
    $total_departments = 0;
    $candidates_with_managers = 0;
    $error_message = "Error loading talent candidates: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Talent Candidates List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .breadcrumb {
            background: transparent;
            padding: 0;
        }

        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: white;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .stats-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.2rem;
        }

        .stats-card.primary .icon {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
        }

        .stats-card.success .icon {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
        }

        .stats-card.info .icon {
            background: linear-gradient(135deg, var(--info-color), #138496);
            color: white;
        }

        .stats-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .stats-card p {
            color: #7f8c8d;
            margin: 0;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .table thead th {
            background: linear-gradient(135deg, #6f42c1, #007bff);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
            font-size: 0.9rem;
        }

        .table tbody td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            border-color: #e9ecef;
            font-size: 0.9rem;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
        }

        .badge-department {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .badge-manager {
            background-color: #e8f5e8;
            color: #2e7d32;
        }

        .badge-no-manager {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .service-years {
            font-weight: 600;
            color: var(--primary-color);
        }

        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .action-buttons .btn {
            margin-left: 0.5rem;
        }

        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin-top: 1rem;
        }

        .dataTables_wrapper .dataTables_filter input {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 0.5rem 0.75rem;
        }

        .dataTables_wrapper .dataTables_length select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 0.5rem;
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

            .content-card {
                padding: 1rem;
            }

            .table-responsive {
                border-radius: 8px;
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
                        <h1><i class="fas fa-address-book me-3"></i>Talent Candidates List</h1>
                        <p>Complete list of employees eligible for talent assessment and identification</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="stats-card primary">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo number_format($total_candidates); ?></h3>
                        <p>Total Talent Candidates</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="stats-card info">
                        <div class="icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3><?php echo number_format($total_departments); ?></h3>
                        <p>Departments Covered</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3><?php echo number_format($candidates_with_managers); ?></h3>
                        <p>With Assigned Managers</p>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="manager_epf" class="form-label">
                            <i class="fas fa-user-tie me-1"></i>Filter by Manager
                        </label>
                        <select name="manager_epf" id="manager_epf" class="form-select">
                            <option value="">All Managers</option>
                            <?php foreach ($available_managers as $manager): ?>
                                <option value="<?php echo htmlspecialchars($manager['manager_epf']); ?>"
                                        <?php echo ($manager_filter === $manager['manager_epf']) ? 'selected' : ''; ?>>
                                    EPF <?php echo htmlspecialchars($manager['manager_epf']); ?> - <?php echo htmlspecialchars($manager['manager_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i>Apply Filter
                            </button>
                            <a href="?" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </div>
                    <?php if ($manager_filter): ?>
                    <div class="col-md-5">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Showing employees managed by EPF <?php echo htmlspecialchars($manager_filter); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Talent Candidates Table -->
            <div class="content-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Talent Candidate Database</h5>
                    <div class="d-flex align-items-center">
                        <small class="text-muted me-3">Last updated: <?php echo date('M d, Y H:i'); ?></small>
                        <a href="talent_candidates_export.php<?php echo $manager_filter ? '?manager_epf=' . urlencode($manager_filter) : ''; ?>"
                           class="btn btn-sm btn-success me-2">
                            <i class="fas fa-download me-1"></i>Export CSV
                        </a>
                        <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                    </div>
                </div>

                <?php if (!empty($candidates)): ?>
                <div class="table-responsive">
                    <table id="candidatesTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>EPF No</th>
                                <th>Employee Name</th>
                                <th>Designation</th>
                                <th>Department</th>
                                <th>Location</th>
                                <th>Joining Date</th>
                                <th>Service</th>
                                <th>Manager</th>
                                <th>Manager EPF</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidates as $candidate): ?>
                            <tr>
                                <td>
                                    <strong class="text-primary"><?php echo htmlspecialchars($candidate['epf_number']); ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                                                 style="width: 35px; height: 35px; font-size: 0.8rem; color: white;">
                                                <?php echo strtoupper(substr($candidate['name'], 0, 2)); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($candidate['name']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($candidate['designation']); ?></td>
                                <td>
                                    <span class="badge badge-department">
                                        <?php echo htmlspecialchars($candidate['department']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($candidate['location']); ?>
                                    </small>
                                </td>
                                <td>
                                    <small>
                                        <?php echo date('M d, Y', strtotime($candidate['date_of_joining'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="service-years">
                                        <?php
                                        $years = (int)$candidate['service_years'];
                                        $months = (int)$candidate['service_months'];
                                        if ($years > 0) {
                                            echo $years . 'y';
                                            if ($months > 0) echo ' ' . $months . 'm';
                                        } else {
                                            echo $months . 'm';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($candidate['manager_name'])): ?>
                                        <span class="badge badge-manager">
                                            <?php echo htmlspecialchars($candidate['manager_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-no-manager">
                                            <i class="fas fa-minus me-1"></i>No Manager
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($candidate['manager_epf'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($candidate['manager_epf']); ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Talent Candidates Found</h5>
                    <p class="text-muted">No employee data available for talent assessment.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-md-8">
                    <div class="content-card">
                        <h6><i class="fas fa-info-circle me-2"></i>Talent Candidate Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled small text-muted">
                                    <li><i class="fas fa-check text-success me-1"></i> All active employees included</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Manager hierarchy mapped</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Service years calculated</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled small text-muted">
                                    <li><i class="fas fa-info text-info me-1"></i> Ready for assessment</li>
                                    <li><i class="fas fa-info text-info me-1"></i> Department-wise grouping</li>
                                    <li><i class="fas fa-info text-info me-1"></i> Manager-subordinate mapping</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="content-card">
                        <h6><i class="fas fa-cog me-2"></i>Quick Actions</h6>
                        <?php if ($user['account_type'] === 'superadmin'): ?>
                            <a href="kti_form_setup.php" class="btn btn-warning btn-sm w-100 mb-2">
                                <i class="fas fa-tools me-2"></i>Setup Assessment Forms
                            </a>
                        <?php endif; ?>
                        <?php if ($user['account_type'] === 'manager' || $user['account_type'] === 'superadmin'): ?>
                            <a href="assessment_form.php" class="btn btn-success btn-sm w-100 mb-2">
                                <i class="fas fa-edit me-2"></i>Start Assessment
                            </a>
                        <?php endif; ?>
                        <a href="talent_grid.php" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-th me-2"></i>View Talent Grid
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#candidatesTable').DataTable({
                "order": [[ 3, "asc" ], [ 2, "asc" ]], // Sort by department, then designation
                "pageLength": 25,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "responsive": true,
                "columnDefs": [
                    { "width": "10%", "targets": 0 }, // EPF No
                    { "width": "20%", "targets": 1 }, // Name
                    { "width": "15%", "targets": 2 }, // Designation
                    { "width": "12%", "targets": 3 }, // Department
                    { "width": "10%", "targets": 4 }, // Location
                    { "width": "10%", "targets": 5 }, // Joining Date
                    { "width": "8%", "targets": 6 },  // Service
                    { "width": "12%", "targets": 7 }, // Manager
                    { "width": "8%", "targets": 8 }   // Manager EPF
                ],
                "language": {
                    "search": "Search candidates:",
                    "lengthMenu": "Show _MENU_ candidates",
                    "info": "Showing _START_ to _END_ of _TOTAL_ candidates",
                    "infoEmpty": "No candidates available",
                    "infoFiltered": "(filtered from _MAX_ total candidates)"
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