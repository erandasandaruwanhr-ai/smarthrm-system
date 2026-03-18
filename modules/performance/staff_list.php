<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/performance_auth.php';

// Check if user has staff list access
requirePerformancePermission('performance.staff_list');

$user = getCurrentUser();
$db = new Database();

// Handle test AJAX request
if (isset($_GET['ajax']) && $_GET['ajax'] === 'test') {
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'AJAX connection working from main file',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Handle AJAX request for employee details
if (isset($_GET['ajax']) && $_GET['ajax'] === 'employee_details' && isset($_GET['epf'])) {
    // Clear any output buffer to prevent HTML from mixing with JSON
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');

    $epf_number = $_GET['epf'];

    // Debug: Let's first try a simple response
    if (isset($_GET['debug'])) {
        echo json_encode([
            'success' => true,
            'debug' => true,
            'epf_requested' => $epf_number,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    try {
        // Get comprehensive employee details using confirmed epf_number column
        $employee = $db->fetch("
            SELECT
                e.*,
                CASE
                    WHEN e.location_id = 1 THEN '7C'
                    WHEN e.location_id = 2 THEN 'Pannala'
                    WHEN e.location_id = 3 THEN 'Kobeigane'
                    WHEN e.location_id = 4 THEN 'JECOE'
                    WHEN e.location_id = 5 THEN 'Head Office'
                    ELSE 'Unknown'
                END as location_name,
                supervisor.epf_number as supervisor_epf,
                supervisor.name as supervisor_name,
                supervisor.designation as supervisor_designation
            FROM employees e
            LEFT JOIN employees supervisor ON CAST(e.reports_to AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(supervisor.epf_number AS CHAR) COLLATE utf8mb4_unicode_ci
            WHERE CAST(e.epf_number AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(? AS CHAR) COLLATE utf8mb4_unicode_ci AND e.is_active = 1
        ", [$epf_number]);

        // Try to get employment level info if table exists
        if ($employee) {
            try {
                $employment_level = $db->fetch("
                    SELECT level_name, id
                    FROM employment_levels
                    WHERE id = ?
                ", [$employee['employment_level_id']]);

                if ($employment_level) {
                    $employee['employment_level_name'] = $employment_level['level_name'];
                    $employee['employment_level'] = $employment_level['level_name'];
                    $employee['employment_level_id'] = $employment_level['id'];
                } else {
                    $employee['employment_level_name'] = 'Unknown Level';
                    $employee['employment_level'] = 'Unknown Level';
                }
            } catch (Exception $e) {
                // Table doesn't exist, use fallback
                $employee['employment_level_name'] = 'Unknown Level';
                $employee['employment_level'] = 'Unknown Level';
            }
        }

        if (!$employee) {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit;
        }

        // Get appraisal history (with error handling for missing table)
        $appraisal_history = [];
        try {
            // First check if the table exists
            $tables = $db->fetchAll("SHOW TABLES LIKE 'staff_appraisals'");
            if (!empty($tables)) {
                // Check which EPF column the table uses
                $columns = $db->fetchAll("SHOW COLUMNS FROM staff_appraisals");
                $appraisal_epf_column = 'appraisee_epf'; // default

                foreach ($columns as $column) {
                    if ($column['Field'] === 'appraisee_epf_number') {
                        $appraisal_epf_column = 'appraisee_epf_number';
                        break;
                    } elseif ($column['Field'] === 'epf_number') {
                        $appraisal_epf_column = 'epf_number';
                        break;
                    } elseif ($column['Field'] === 'epf_no') {
                        $appraisal_epf_column = 'epf_no';
                        break;
                    }
                }

                $appraisal_history = $db->fetchAll("
                    SELECT
                        appraisal_year,
                        status,
                        created_at,
                        appraiser_name,
                        (COALESCE(job_knowledge_q1, 0) + COALESCE(job_knowledge_q2, 0) + COALESCE(job_knowledge_q3, 0) +
                         COALESCE(creativity_q1, 0) + COALESCE(quality_awareness_q1, 0) + COALESCE(quality_awareness_q2, 0) +
                         COALESCE(goal_achievement_q1, 0) + COALESCE(teamwork_q1, 0) + COALESCE(teamwork_q2, 0) +
                         COALESCE(teamwork_q3, 0) + COALESCE(teamwork_q4, 0) + COALESCE(cultural_awareness_q1, 0) +
                         COALESCE(cultural_awareness_q2, 0) + COALESCE(cultural_awareness_q3, 0) + COALESCE(cultural_awareness_q4, 0) +
                         COALESCE(cultural_awareness_q5, 0) + COALESCE(cultural_awareness_q6, 0) + COALESCE(safety_consciousness_q1, 0) +
                         COALESCE(safety_consciousness_q2, 0) + COALESCE(safety_consciousness_q3, 0) + COALESCE(safety_consciousness_q4, 0) +
                         COALESCE(discipline_q1, 0) + COALESCE(discipline_q2, 0) + COALESCE(discipline_q3, 0) + COALESCE(discipline_q4, 0)) as total_score
                    FROM staff_appraisals
                    WHERE {$appraisal_epf_column} = ?
                    ORDER BY appraisal_year DESC
                    LIMIT 5
                ", [$epf_number]);
            }
        } catch (Exception $e) {
            // Table might not exist yet or query failed, continue with empty array
            error_log("Appraisal history query failed: " . $e->getMessage());
        }

        // Get leave utilization for current year (with error handling)
        $current_year = date('Y');
        $leave_data = null;
        try {
            // Check if table exists and determine correct column name
            $tables = $db->fetchAll("SHOW TABLES LIKE 'leave_utilization'");
            if (!empty($tables)) {
                $columns = $db->fetchAll("SHOW COLUMNS FROM leave_utilization");
                $leave_epf_column = 'epf_number'; // default

                foreach ($columns as $column) {
                    if ($column['Field'] === 'epf_no') {
                        $leave_epf_column = 'epf_no';
                        break;
                    }
                }

                $leave_data = $db->fetch("
                    SELECT * FROM leave_utilization
                    WHERE CAST({$leave_epf_column} AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(? AS CHAR) COLLATE utf8mb4_unicode_ci AND year = ?
                ", [$epf_number, $current_year]);
            }
        } catch (Exception $e) {
            // Table might not exist yet or query failed, continue with null
            error_log("Leave utilization query failed: " . $e->getMessage());
        }

        // Get subordinates count (reports_to field should match epf_number format)
        $subordinates_count = $db->fetch("
            SELECT COUNT(*) as count
            FROM employees
            WHERE CAST(reports_to AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(? AS CHAR) COLLATE utf8mb4_unicode_ci AND is_active = 1
        ", [$epf_number])['count'] ?? 0;

        // Get department colleagues count
        $colleagues_count = $db->fetch("
            SELECT COUNT(*) as count
            FROM employees
            WHERE department = ? AND CAST(epf_number AS CHAR) COLLATE utf8mb4_unicode_ci != CAST(? AS CHAR) COLLATE utf8mb4_unicode_ci AND is_active = 1
        ", [$employee['department'], $epf_number])['count'] ?? 0;

        // Calculate service years
        $joining_date = new DateTime($employee['joined_date']);
        $current_date = new DateTime();
        $service_interval = $joining_date->diff($current_date);
        $service_years_calculated = $service_interval->y;
        $service_months = $service_interval->m;

        // Process appraisal history with grades
        foreach ($appraisal_history as &$appraisal) {
            $percentage = round(($appraisal['total_score'] / 125) * 100, 1);
            if ($percentage >= 90) $grade = 'A+';
            elseif ($percentage >= 80) $grade = 'A';
            elseif ($percentage >= 70) $grade = 'B+';
            elseif ($percentage >= 60) $grade = 'B';
            elseif ($percentage >= 50) $grade = 'C';
            else $grade = 'D';

            $appraisal['grade'] = $grade;
            $appraisal['percentage'] = $percentage;
        }

        $response = [
            'success' => true,
            'employee' => $employee,
            'appraisal_history' => $appraisal_history,
            'leave_data' => $leave_data,
            'subordinates_count' => $subordinates_count,
            'colleagues_count' => $colleagues_count,
            'service_years_calculated' => $service_years_calculated,
            'service_months' => $service_months,
            'current_year' => $current_year
        ];

        echo json_encode($response);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Get staff members eligible for appraisal (employment levels 8 and 9)
$staff_members = $db->fetchAll("
    SELECT
        e.epf_number,
        e.name,
        e.designation,
        e.department,
        CASE
            WHEN e.location_id = 1 THEN '7C'
            WHEN e.location_id = 2 THEN 'Pannala'
            WHEN e.location_id = 3 THEN 'Kobeigane'
            WHEN e.location_id = 4 THEN 'JECOE'
            WHEN e.location_id = 5 THEN 'Head Office'
            ELSE 'Unknown'
        END as location,
        e.joined_date as joining_date,
        e.service as service_years,
        supervisor.epf_number as supervisor_epf,
        supervisor.name as supervisor_name,
        COALESCE(el.level_name, 'Unknown Level') as employment_level
    FROM employees e
    LEFT JOIN employees supervisor ON CAST(e.reports_to AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(supervisor.epf_number AS CHAR) COLLATE utf8mb4_unicode_ci
    LEFT JOIN employment_levels el ON e.employment_level_id = el.id
    WHERE e.is_active = 1 AND e.employment_level_id IN (8, 9)
    ORDER BY e.epf_number
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Staff Appraisal List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .table {
            font-size: 0.9rem;
        }

        .badge {
            font-size: 0.75rem;
        }

        /* Employee Details Modal Styles */
        .employee-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
        }

        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
        }

        .info-item {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }

        .info-item strong {
            min-width: 140px;
            color: #495057;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #dc3545; }

        .appraisal-history-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 8px;
        }

        .grade-A-plus, .grade-A { color: #28a745; font-weight: bold; }
        .grade-B-plus, .grade-B { color: #007bff; font-weight: bold; }
        .grade-C { color: #ffc107; font-weight: bold; }
        .grade-D { color: #dc3545; font-weight: bold; }

        .leave-stats {
            background: linear-gradient(45deg, #17a2b8, #138496);
            color: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }

        .leave-item {
            margin-bottom: 5px;
        }

        .quick-stats {
            background: linear-gradient(45deg, #6f42c1, #e83e8c);
            color: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                        <h1><i class="fas fa-users me-3"></i>Staff Appraisal List</h1>
                        <p>Employee list for staff appraisal - Employment levels: Supervisor & Staff</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Eligible Staff Members
                    </h5>
                    <span class="badge bg-primary"><?= count($staff_members) ?> employees</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="staffTable" class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>EPF No</th>
                                    <th>Name</th>
                                    <th>Designation</th>
                                    <th>Department</th>
                                    <th>Location</th>
                                    <th>Joining Date</th>
                                    <th>Service Years</th>
                                    <th>Supervisor EPF</th>
                                    <th>Supervisor Name</th>
                                    <th>Employment Level</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff_members as $staff): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?= htmlspecialchars($staff['epf_number']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($staff['name']) ?></td>
                                    <td><?= htmlspecialchars($staff['designation']) ?></td>
                                    <td><?= htmlspecialchars($staff['department']) ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?= htmlspecialchars($staff['location']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($staff['joining_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-success">
                                            <?= htmlspecialchars($staff['service_years']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($staff['supervisor_epf']): ?>
                                            <strong class="text-warning">
                                                <?= htmlspecialchars($staff['supervisor_epf']) ?>
                                            </strong>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($staff['supervisor_name']): ?>
                                            <?= htmlspecialchars($staff['supervisor_name']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">No Supervisor</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($staff['employment_level']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                    onclick="viewEmployeeDetails('<?= $staff['epf_number'] ?>')"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (checkPerformancePermission('performance.staff_appraisal')): ?>
                                            <button type="button" class="btn btn-outline-success btn-sm"
                                                    onclick="startAppraisal('<?= $staff['epf_number'] ?>')"
                                                    title="Start Appraisal">
                                                <i class="fas fa-clipboard-list"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Details Modal -->
    <div class="modal fade" id="employeeDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user-circle me-2"></i>Employee Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" id="employeeDetailsContent">
                    <div class="text-center p-5">
                        <div class="loading-spinner"></div>
                        <p class="mt-3">Loading employee details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" id="startAppraisalBtn" style="display: none;">
                        <i class="fas fa-clipboard-list me-1"></i>Start Appraisal
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#staffTable').DataTable({
                pageLength: 25,
                order: [[0, 'asc']],
                responsive: true,
                language: {
                    search: "Search staff members:",
                    lengthMenu: "Show _MENU_ employees per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ employees"
                }
            });
        });

        function viewEmployeeDetails(epfNo) {
            const modalElement = document.getElementById('employeeDetailsModal');
            const modal = new bootstrap.Modal(modalElement);

            // Reset content to loading state before showing modal
            document.getElementById('employeeDetailsContent').innerHTML = `
                <div class="text-center p-5">
                    <div class="loading-spinner"></div>
                    <p class="mt-3">Loading employee details...</p>
                </div>
            `;

            // Hide start appraisal button initially
            document.getElementById('startAppraisalBtn').style.display = 'none';

            // Show modal after content is reset
            modal.show();

            // Make AJAX call to get employee details (fallback to same file)
            setTimeout(() => {
                fetch(`?ajax=employee_details&epf=${encodeURIComponent(epfNo)}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('Invalid JSON response:', text.substring(0, 500));
                                throw new Error('Invalid response format');
                            }
                        });
                    })
                    .then(data => {
                        requestAnimationFrame(() => {
                            if (data.success) {
                                displayEmployeeDetails(data);
                            } else {
                                showError(data.message || 'Failed to load employee details');
                            }
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        requestAnimationFrame(() => {
                            showError(`Network error: ${error.message}`);
                        });
                    });
            }, 50);
        }

        function displayEmployeeDetails(data) {
            const employee = data.employee;
            const appraisalHistory = data.appraisal_history || [];
            const leaveData = data.leave_data;
            const currentYear = data.current_year;

            // Show start appraisal button if user has permission
            <?php if (checkPerformancePermission('performance.staff_appraisal')): ?>
                document.getElementById('startAppraisalBtn').style.display = 'inline-block';
                document.getElementById('startAppraisalBtn').onclick = () => startAppraisal(employee.epf_number);
            <?php endif; ?>

            const content = `
                <div class="container-fluid p-4">
                    <!-- Employee Header -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <div class="employee-photo-wrapper" style="width: 80px; height: 80px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; color: #6c757d; font-size: 2rem;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="mb-1">${employee.name}</h4>
                                    <h6 class="text-muted mb-1">${employee.designation}</h6>
                                    <p class="mb-0">
                                        <span class="status-indicator status-${employee.is_active ? 'active' : 'inactive'}"></span>
                                        EPF: <strong>${employee.epf_number}</strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="quick-stats">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h5 class="mb-0">${data.service_years_calculated}</h5>
                                        <small>Years Service</small>
                                    </div>
                                    <div class="col-6">
                                        <h5 class="mb-0">${data.subordinates_count}</h5>
                                        <small>Subordinates</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Tabs -->
                    <ul class="nav nav-tabs" id="employeeDetailsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button">
                                <i class="fas fa-user me-1"></i>Basic Info
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button">
                                <i class="fas fa-briefcase me-1"></i>Employment
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="appraisal-tab" data-bs-toggle="tab" data-bs-target="#appraisal" type="button">
                                <i class="fas fa-chart-line me-1"></i>Appraisal History
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="leave-tab" data-bs-toggle="tab" data-bs-target="#leave" type="button">
                                <i class="fas fa-calendar-alt me-1"></i>Leave Data
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mt-3" id="employeeDetailsTabsContent">
                        <!-- Basic Info Tab -->
                        <div class="tab-pane fade show active" id="basic" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6 class="mb-3"><i class="fas fa-id-card text-primary me-2"></i>Personal Information</h6>
                                        <div class="info-item">
                                            <strong>Full Name:</strong>
                                            <span>${employee.name}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>EPF Number:</strong>
                                            <span class="badge bg-primary">${employee.epf_number}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>NIC Number:</strong>
                                            <span>${employee.nic || 'N/A'}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Email:</strong>
                                            <span>${employee.email || 'N/A'}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Phone:</strong>
                                            <span>${employee.phone || 'N/A'}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Address:</strong>
                                            <span>${employee.address || 'N/A'}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6 class="mb-3"><i class="fas fa-users text-success me-2"></i>Reporting Structure</h6>
                                        <div class="info-item">
                                            <strong>Supervisor EPF:</strong>
                                            <span>${employee.supervisor_epf ? `<span class="badge bg-warning text-dark">${employee.supervisor_epf}</span>` : '<span class="text-muted">No Supervisor</span>'}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Supervisor Name:</strong>
                                            <span>${employee.supervisor_name || 'No Supervisor'}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Supervisor Title:</strong>
                                            <span>${employee.supervisor_designation || 'N/A'}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Team Size:</strong>
                                            <span class="badge bg-info">${data.subordinates_count} Direct Reports</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Department:</strong>
                                            <span class="badge bg-secondary">${employee.department}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Colleagues:</strong>
                                            <span>${data.colleagues_count} in ${employee.department}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Employment Tab -->
                        <div class="tab-pane fade" id="employment" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6 class="mb-3"><i class="fas fa-briefcase text-primary me-2"></i>Employment Details</h6>
                                        <div class="info-item">
                                            <strong>Designation:</strong>
                                            <span class="badge bg-primary">${employee.designation}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Employment Level:</strong>
                                            <span class="badge bg-secondary">${employee.employment_level_name}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Department:</strong>
                                            <span>${employee.department}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Location:</strong>
                                            <span class="badge bg-info">${employee.location_name}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Status:</strong>
                                            <span class="badge ${employee.is_active ? 'bg-success' : 'bg-danger'}">
                                                ${employee.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6 class="mb-3"><i class="fas fa-calendar text-success me-2"></i>Service Information</h6>
                                        <div class="info-item">
                                            <strong>Joined Date:</strong>
                                            <span>${new Date(employee.joined_date).toLocaleDateString('en-GB')}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Service Period:</strong>
                                            <span class="badge bg-success">${data.service_years_calculated} years, ${data.service_months} months</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Recorded Service:</strong>
                                            <span>${employee.service || 'N/A'}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Employee ID:</strong>
                                            <span>${employee.employee_id || 'N/A'}</span>
                                        </div>
                                        <div class="info-item">
                                            <strong>Created:</strong>
                                            <span>${employee.created_at ? new Date(employee.created_at).toLocaleDateString('en-GB') : 'N/A'}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Appraisal History Tab -->
                        <div class="tab-pane fade" id="appraisal" role="tabpanel">
                            <div class="info-card">
                                <h6 class="mb-3"><i class="fas fa-chart-line text-primary me-2"></i>Performance Appraisal History</h6>
                                ${appraisalHistory.length > 0 ?
                                    appraisalHistory.map(appraisal => `
                                        <div class="appraisal-history-item">
                                            <div class="row align-items-center">
                                                <div class="col-md-2">
                                                    <strong>Year ${appraisal.appraisal_year}</strong>
                                                </div>
                                                <div class="col-md-3">
                                                    <span class="badge bg-${getStatusColor(appraisal.status)}">${appraisal.status.toUpperCase()}</span>
                                                </div>
                                                <div class="col-md-2">
                                                    <span class="grade-${appraisal.grade.replace('+', '-plus')}">${appraisal.grade}</span>
                                                </div>
                                                <div class="col-md-2">
                                                    <small>${appraisal.total_score}/125 (${appraisal.percentage}%)</small>
                                                </div>
                                                <div class="col-md-3">
                                                    <small class="text-muted">by ${appraisal.appraiser_name}</small>
                                                </div>
                                            </div>
                                        </div>
                                    `).join('') :
                                    '<p class="text-muted text-center py-4"><i class="fas fa-info-circle me-2"></i>No appraisal history available</p>'
                                }
                            </div>
                        </div>

                        <!-- Leave Data Tab -->
                        <div class="tab-pane fade" id="leave" role="tabpanel">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="info-card">
                                        <h6 class="mb-3"><i class="fas fa-calendar-alt text-primary me-2"></i>Leave Utilization ${currentYear}</h6>
                                        ${leaveData ? `
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="info-item">
                                                        <strong>Annual Leave:</strong>
                                                        <span class="badge bg-primary">${leaveData.annual_leave_days} days</span>
                                                    </div>
                                                    <div class="info-item">
                                                        <strong>Casual Leave:</strong>
                                                        <span class="badge bg-info">${leaveData.casual_leave_days} days</span>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="info-item">
                                                        <strong>Medical Leave:</strong>
                                                        <span class="badge bg-warning">${leaveData.medical_leave_days} days</span>
                                                    </div>
                                                    <div class="info-item">
                                                        <strong>No Pay Days:</strong>
                                                        <span class="badge bg-danger">${leaveData.no_pay_days} days</span>
                                                    </div>
                                                </div>
                                            </div>
                                        ` : '<p class="text-muted text-center py-4"><i class="fas fa-info-circle me-2"></i>No leave data available for ' + currentYear + '</p>'}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="leave-stats">
                                        <h6 class="mb-3">Leave Summary ${currentYear}</h6>
                                        ${leaveData ? `
                                            <div class="leave-item">
                                                <strong>Total Days Used:</strong><br>
                                                <span class="h4">${(leaveData.annual_leave_days || 0) + (leaveData.casual_leave_days || 0) + (leaveData.medical_leave_days || 0) + (leaveData.no_pay_days || 0)}</span>
                                            </div>
                                        ` : '<p class="mb-0">No data available</p>'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('employeeDetailsContent').innerHTML = content;
        }

        function showError(message) {
            document.getElementById('employeeDetailsContent').innerHTML = `
                <div class="text-center p-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <h5>Error Loading Employee Details</h5>
                    <p class="text-muted">${message}</p>
                </div>
            `;
        }

        function getStatusColor(status) {
            switch(status) {
                case 'approved': return 'success';
                case 'submitted': return 'info';
                case 'draft': return 'warning';
                case 'rejected': return 'danger';
                default: return 'secondary';
            }
        }

        function startAppraisal(epfNo) {
            if (confirm('Start performance appraisal for employee ' + epfNo + '?')) {
                window.location.href = `staff_appraisal.php?epf=${epfNo}`;
            }
        }


    </script>
</body>
</html>