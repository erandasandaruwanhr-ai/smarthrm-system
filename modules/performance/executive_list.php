<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/performance_auth.php';

$user = getCurrentUser();
$db = new Database();

// Handle AJAX request for employee details
if (isset($_GET['ajax']) && $_GET['ajax'] === 'employee_details' && isset($_GET['epf'])) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');


    // Check permissions for AJAX request and return JSON error if no access
    if (!checkPerformancePermission('performance.staff_list')) {
        echo json_encode(['success' => false, 'message' => 'Access denied: Insufficient permissions']);
        exit;
    }

    $epf_number = $_GET['epf'];

    if (empty($epf_number)) {
        echo json_encode(['success' => false, 'message' => 'EPF number is required']);
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
            LEFT JOIN employees level1_supervisor ON CAST(e.reports_to AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(level1_supervisor.epf_number AS CHAR) COLLATE utf8mb4_unicode_ci
            LEFT JOIN employees level2_supervisor ON CAST(level1_supervisor.reports_to AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(level2_supervisor.epf_number AS CHAR) COLLATE utf8mb4_unicode_ci
            LEFT JOIN employees level3_supervisor ON CAST(level2_supervisor.reports_to AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(level3_supervisor.epf_number AS CHAR) COLLATE utf8mb4_unicode_ci
            LEFT JOIN employees supervisor ON
                CASE
                    WHEN level1_supervisor.employment_level_id IN (1, 2, 3) THEN level1_supervisor.epf_number
                    WHEN level2_supervisor.employment_level_id IN (1, 2, 3) THEN level2_supervisor.epf_number
                    WHEN level3_supervisor.employment_level_id IN (1, 2, 3) THEN level3_supervisor.epf_number
                    ELSE NULL
                END = supervisor.epf_number
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
            $tables = $db->fetchAll("SHOW TABLES LIKE 'executive_appraisals'");
            if (!empty($tables)) {
                // Check which EPF column the table uses
                $columns = $db->fetchAll("SHOW COLUMNS FROM executive_appraisals");
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

                // Check what columns exist in executive_appraisals table
                $appraisal_columns = array_column($columns, 'Field');

                // Build SELECT clause based on available columns
                $select_fields = ['appraisal_year', 'status', 'created_at', 'appraiser_name'];
                $optional_fields = ['overall_rating', 'development_needs', 'achievements', 'total_score', 'final_rating'];

                foreach ($optional_fields as $field) {
                    if (in_array($field, $appraisal_columns)) {
                        $select_fields[] = $field;
                    }
                }

                $select_clause = implode(', ', $select_fields);

                $appraisal_history = $db->fetchAll("
                    SELECT {$select_clause}
                    FROM executive_appraisals
                    WHERE {$appraisal_epf_column} = ?
                    ORDER BY appraisal_year DESC
                    LIMIT 5
                ", [$epf_number]);
            }
        } catch (Exception $e) {
            // Table might not exist yet or query failed, continue with empty array
            error_log("Executive appraisal history query failed: " . $e->getMessage());
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

        // Function to get performance grade
        function getPerformanceGrade($rating) {
            if ($rating >= 4.5) return ['grade' => 'Outstanding', 'color' => 'success'];
            elseif ($rating >= 3.5) return ['grade' => 'Exceeds Expectations', 'color' => 'primary'];
            elseif ($rating >= 2.5) return ['grade' => 'Meets Expectations', 'color' => 'info'];
            elseif ($rating >= 1.5) return ['grade' => 'Below Expectations', 'color' => 'warning'];
            else return ['grade' => 'Unsatisfactory', 'color' => 'danger'];
        }

        // Process appraisal history with grades
        foreach ($appraisal_history as &$appraisal) {
            // Check for different rating column names
            $rating = null;
            if (isset($appraisal['overall_rating']) && $appraisal['overall_rating'] > 0) {
                $rating = $appraisal['overall_rating'];
            } elseif (isset($appraisal['final_rating']) && $appraisal['final_rating'] > 0) {
                $rating = $appraisal['final_rating'];
            } elseif (isset($appraisal['total_score']) && $appraisal['total_score'] > 0) {
                // Convert total score to rating (assuming 125 max like in staff appraisals)
                $rating = ($appraisal['total_score'] / 125) * 5;
            }

            if ($rating !== null && $rating > 0) {
                $performance = getPerformanceGrade($rating);
                $appraisal['grade'] = $performance['grade'];
                $appraisal['grade_color'] = $performance['color'];
                $appraisal['rating_display'] = number_format($rating, 1);
            } else {
                $appraisal['grade'] = 'Not Rated';
                $appraisal['grade_color'] = 'secondary';
                $appraisal['rating_display'] = 'N/A';
            }
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

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Check if user has executive list access for regular page load
requirePerformancePermission('performance.executive_list');

// Get executive members eligible for appraisal (employment levels 3-7: Junior Executive to Manager)
$executive_members = $db->fetchAll("
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
    LEFT JOIN employees level1_supervisor ON CAST(e.reports_to AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(level1_supervisor.epf_number AS CHAR) COLLATE utf8mb4_unicode_ci
    LEFT JOIN employees level2_supervisor ON CAST(level1_supervisor.reports_to AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(level2_supervisor.epf_number AS CHAR) COLLATE utf8mb4_unicode_ci
    LEFT JOIN employees level3_supervisor ON CAST(level2_supervisor.reports_to AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(level3_supervisor.epf_number AS CHAR) COLLATE utf8mb4_unicode_ci
    LEFT JOIN employees supervisor ON
        CASE
            WHEN level1_supervisor.employment_level_id IN (1, 2, 3) THEN level1_supervisor.epf_number
            WHEN level2_supervisor.employment_level_id IN (1, 2, 3) THEN level2_supervisor.epf_number
            WHEN level3_supervisor.employment_level_id IN (1, 2, 3) THEN level3_supervisor.epf_number
            ELSE NULL
        END = supervisor.epf_number
    LEFT JOIN employment_levels el ON e.employment_level_id = el.id
    WHERE e.is_active = 1 AND e.employment_level_id IN (3, 4, 5, 6, 7)
    ORDER BY e.employment_level_id ASC, e.epf_number ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Executive Appraisal List</title>
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

        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-radius: 50%;
            border-top: 3px solid #28a745;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Employee Details Modal */
        .employee-photo-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 2rem;
        }

        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #dc3545; }

        .quick-stats {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 1rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
        }

        .nav-tabs .nav-link.active {
            background: #28a745;
            color: white;
            border-radius: 5px 5px 0 0;
        }

        .info-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
        }

        .performance-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            text-align: center;
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
                        <h1><i class="fas fa-user-tie me-3"></i>Executive Appraisal List</h1>
                        <p>Employee list for executive appraisal - Employment levels: Junior Executive to Manager (Levels 3-7)</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Eligible Executive Members
                    </h5>
                    <span class="badge bg-success"><?= count($executive_members) ?> employees</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="executiveTable" class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>EPF No (13.2.1)</th>
                                    <th>Name (13.2.2)</th>
                                    <th>Designation (13.2.3)</th>
                                    <th>Department (13.2.4)</th>
                                    <th>Location (13.2.5)</th>
                                    <th>Joining Date (13.2.6)</th>
                                    <th>Service Years (13.2.7)</th>
                                    <th>Supervisor EPF (13.2.8)</th>
                                    <th>Supervisor Name (13.2.9)</th>
                                    <th>Employment Level</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($executive_members as $executive): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?= htmlspecialchars($executive['epf_number']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($executive['name']) ?></td>
                                    <td><?= htmlspecialchars($executive['designation']) ?></td>
                                    <td><?= htmlspecialchars($executive['department']) ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?= htmlspecialchars($executive['location']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($executive['joining_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-success">
                                            <?= htmlspecialchars($executive['service_years']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($executive['supervisor_epf']): ?>
                                            <strong class="text-warning">
                                                <?= htmlspecialchars($executive['supervisor_epf']) ?>
                                            </strong>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($executive['supervisor_name']): ?>
                                            <?= htmlspecialchars($executive['supervisor_name']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">No Supervisor</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($executive['employment_level']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                    onclick="viewEmployeeDetails('<?= $executive['epf_number'] ?>')"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (checkPerformancePermission('performance.executive_appraisal')): ?>
                                            <button type="button" class="btn btn-outline-success btn-sm"
                                                    onclick="startAppraisal('<?= $executive['epf_number'] ?>')"
                                                    title="Start Appraisal">
                                                <i class="fas fa-tasks"></i>
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
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user-tie me-2"></i>Executive Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" id="employeeDetailsContent">
                    <div class="text-center p-5">
                        <div class="loading-spinner"></div>
                        <p class="mt-3">Loading executive details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                    <button type="button" class="btn btn-success" id="startAppraisalBtn" style="display: none;">
                        <i class="fas fa-tasks me-1"></i>Start Executive Appraisal
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#executiveTable').DataTable({
                pageLength: 25,
                order: [[0, 'asc']],
                responsive: true,
                language: {
                    search: "Search executives:",
                    lengthMenu: "Show _MENU_ executives per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ executives"
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
                    <p class="mt-3">Loading executive details...</p>
                </div>
            `;

            // Hide start appraisal button initially
            document.getElementById('startAppraisalBtn').style.display = 'none';

            // Show modal after content is reset
            modal.show();

            // Make AJAX call to get executive details
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
                                showError(data.message || 'Failed to load executive details');
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
            <?php if (checkPerformancePermission('performance.executive_appraisal')): ?>
                document.getElementById('startAppraisalBtn').style.display = 'inline-block';
                document.getElementById('startAppraisalBtn').onclick = () => startAppraisal(employee.epf_number);
            <?php endif; ?>

            const content = `
                <div class="container-fluid p-4">
                    <!-- Executive Header -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <div class="employee-photo-wrapper" style="width: 80px; height: 80px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; color: #6c757d; font-size: 2rem;">
                                        <i class="fas fa-user-tie"></i>
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
                    <ul class="nav nav-tabs" id="detailsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button">
                                <i class="fas fa-user me-2"></i>Basic Info
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button">
                                <i class="fas fa-building me-2"></i>Employment
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="appraisal-tab" data-bs-toggle="tab" data-bs-target="#appraisal" type="button">
                                <i class="fas fa-chart-line me-2"></i>Appraisal History
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="leave-tab" data-bs-toggle="tab" data-bs-target="#leave" type="button">
                                <i class="fas fa-calendar me-2"></i>Leave Data
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mt-3" id="detailsTabContent">
                        <!-- Basic Information Tab -->
                        <div class="tab-pane fade show active" id="basic">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6><i class="fas fa-id-card me-2"></i>Personal Information</h6>
                                        <table class="table table-borderless table-sm">
                                            <tr><td><strong>Full Name:</strong></td><td>${employee.name}</td></tr>
                                            <tr><td><strong>EPF Number:</strong></td><td>${employee.epf_number}</td></tr>
                                            <tr><td><strong>NIC:</strong></td><td>${employee.nic || 'N/A'}</td></tr>
                                            <tr><td><strong>Birthday:</strong></td><td>${employee.birthday ? new Date(employee.birthday).toLocaleDateString() : 'N/A'}</td></tr>
                                            <tr><td><strong>Age:</strong></td><td>${employee.age || 'N/A'}</td></tr>
                                            <tr><td><strong>Gender:</strong></td><td>${employee.gender || 'N/A'}</td></tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6><i class="fas fa-map-marker-alt me-2"></i>Contact & Location</h6>
                                        <table class="table table-borderless table-sm">
                                            <tr><td><strong>Department:</strong></td><td>${employee.department}</td></tr>
                                            <tr><td><strong>Location:</strong></td><td><span class="badge bg-info">${employee.location_name}</span></td></tr>
                                            <tr><td><strong>Account Type:</strong></td><td>${employee.account_type || 'N/A'}</td></tr>
                                            <tr><td><strong>Status:</strong></td><td>
                                                <span class="badge bg-${employee.is_active ? 'success' : 'danger'}">
                                                    ${employee.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Employment Tab -->
                        <div class="tab-pane fade" id="employment">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6><i class="fas fa-briefcase me-2"></i>Employment Details</h6>
                                        <table class="table table-borderless table-sm">
                                            <tr><td><strong>Designation:</strong></td><td>${employee.designation}</td></tr>
                                            <tr><td><strong>Employment Level:</strong></td><td>
                                                <span class="badge bg-secondary">${employee.employment_level_name}</span>
                                            </td></tr>
                                            <tr><td><strong>Joining Date:</strong></td><td>${new Date(employee.joined_date).toLocaleDateString()}</td></tr>
                                            <tr><td><strong>Service Years:</strong></td><td>
                                                <strong class="text-success">${data.service_years_calculated} years, ${data.service_months} months</strong>
                                            </td></tr>
                                            <tr><td><strong>Account Level:</strong></td><td>${employee.account_level || 'N/A'}</td></tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6><i class="fas fa-users me-2"></i>Reporting Structure</h6>
                                        <table class="table table-borderless table-sm">
                                            <tr><td><strong>Reports To:</strong></td><td>
                                                ${employee.supervisor_name ?
                                                    `${employee.supervisor_name}<br><small class="text-muted">EPF: ${employee.supervisor_epf}</small>`
                                                    : '<span class="text-muted">No Supervisor</span>'}
                                            </td></tr>
                                            <tr><td><strong>Subordinates:</strong></td><td>
                                                <span class="badge bg-primary">${data.subordinates_count}</span> direct reports
                                            </td></tr>
                                            <tr><td><strong>Department Colleagues:</strong></td><td>
                                                <span class="badge bg-info">${data.colleagues_count}</span> colleagues
                                            </td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Appraisal History Tab -->
                        <div class="tab-pane fade" id="appraisal">
                            <div class="info-card">
                                <h6><i class="fas fa-chart-line me-2"></i>Performance Appraisal History</h6>
                                ${appraisalHistory.length > 0 ? `
                                    <div class="table-responsive">
                                        <table class="table table-striped table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Year</th>
                                                    <th>Status</th>
                                                    <th>Rating</th>
                                                    <th>Grade</th>
                                                    <th>Appraiser</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${appraisalHistory.map(appraisal => `
                                                    <tr>
                                                        <td><strong>${appraisal.appraisal_year}</strong></td>
                                                        <td><span class="badge bg-info">${appraisal.status}</span></td>
                                                        <td>${appraisal.rating_display}</td>
                                                        <td><span class="badge bg-${appraisal.grade_color}">${appraisal.grade}</span></td>
                                                        <td>${appraisal.appraiser_name || 'N/A'}</td>
                                                        <td>${new Date(appraisal.created_at).toLocaleDateString()}</td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                ` : `
                                    <div class="text-center p-4">
                                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No appraisal history found</p>
                                        <p><small>This executive hasn't been appraised yet.</small></p>
                                    </div>
                                `}
                            </div>
                        </div>

                        <!-- Leave Data Tab -->
                        <div class="tab-pane fade" id="leave">
                            <div class="info-card">
                                <h6><i class="fas fa-calendar me-2"></i>Leave Utilization for ${currentYear}</h6>
                                ${leaveData ? `
                                    <div class="row">
                                        <div class="col-md-3 text-center">
                                            <h4 class="text-primary">${leaveData.annual_leave_taken || 0}</h4>
                                            <small>Annual Leave Taken</small>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <h4 class="text-success">${leaveData.casual_leave_taken || 0}</h4>
                                            <small>Casual Leave Taken</small>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <h4 class="text-warning">${leaveData.medical_leave_taken || 0}</h4>
                                            <small>Medical Leave Taken</small>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <h4 class="text-info">${leaveData.total_leave_taken || 0}</h4>
                                            <small>Total Leave Taken</small>
                                        </div>
                                    </div>
                                ` : `
                                    <div class="text-center p-4">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No leave data available for ${currentYear}</p>
                                        <p><small>Leave utilization data will appear here once available.</small></p>
                                    </div>
                                `}
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
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Error Loading Executive Details</h5>
                        <p>${message}</p>
                    </div>
                </div>
            `;
        }


        function startAppraisal(epfNo) {
            if (confirm('Start executive performance appraisal for employee ' + epfNo + '?')) {
                window.location.href = `executive_appraisal.php?epf=${epfNo}`;
            }
        }
    </script>
</body>
</html>