<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($database, 'offboarding.resignation_history')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get completed resignations with full details
$completed_resignations_query = "
    SELECT
        r.*,
        e.department, e.designation,
        ei.id as exit_interview_id,
        cf.it_assets_cleared, cf.finance_clearance_cleared, cf.hr_clearance_cleared,
        cf.stores_cleared, cf.location_clearance_cleared, cf.clearance_pdf_file,
        cf.clearance_pdf_path, cf.status as clearance_status, cf.final_verification,
        cf.submitted_at as clearance_submitted_at, cf.verification_date,
        DATEDIFF(CURDATE(), r.last_working_day) as days_since_exit
    FROM resignations r
    LEFT JOIN employees e ON r.employee_epf = e.epf_number
    LEFT JOIN exit_interviews ei ON r.id = ei.resignation_id
    LEFT JOIN clearance_forms cf ON r.id = cf.resignation_id
    WHERE r.status IN ('completed', 'approved')
    ORDER BY r.last_working_day DESC
";

$completed_resignations = $database->fetchAll($completed_resignations_query);

// Get statistics
$total_completed = count($completed_resignations);

// Calculate average notice period instead of tenure (since hire_date doesn't exist)
$avg_notice_query = "SELECT AVG(DATEDIFF(r.last_working_day, r.date_of_resignation)) as avg_notice
                     FROM resignations r
                     WHERE r.status IN ('completed', 'approved')";
$avg_notice_result = $database->fetchAll($avg_notice_query);
$avg_notice_period = round($avg_notice_result[0]['avg_notice'] ?? 0);

// Department breakdown
$dept_breakdown_query = "SELECT e.department, COUNT(*) as count
                        FROM resignations r
                        LEFT JOIN employees e ON r.employee_epf = e.epf_number
                        WHERE r.status IN ('completed', 'approved')
                        GROUP BY e.department
                        ORDER BY count DESC";
$dept_breakdown = $database->fetchAll($dept_breakdown_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Resignation History</title>
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

        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            transition: transform 0.3s ease;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .stats-card.info .icon {
            background: linear-gradient(135deg, var(--info-color), #138496);
            color: white;
        }

        .stats-card.success .icon {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
        }

        .stats-card.warning .icon {
            background: linear-gradient(135deg, var(--warning-color), #e0a800);
            color: white;
        }

        .stats-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .history-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .resignation-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: #fff;
        }

        .resignation-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.3s ease;
        }

        .employee-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 500;
            color: #2c3e50;
        }

        .rating-stars {
            color: #ffc107;
        }

        .section-divider {
            border-left: 4px solid var(--primary-color);
            padding-left: 1rem;
            margin: 1.5rem 0;
        }

        .badge-custom {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
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

            .info-grid {
                grid-template-columns: 1fr;
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
                        <h1><i class="fas fa-history me-3"></i>Resignation History</h1>
                        <p>View historical resignation records and employee departure trends</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Employee Offboarding</a></li>
                    <li class="breadcrumb-item active">Resignation History</li>
                </ol>
            </nav>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="stats-card info">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo number_format($total_completed); ?></h3>
                        <p>Total Completed Exits</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3><?php echo $avg_notice_period; ?></h3>
                        <p>Average Notice Period (Days)</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3><?php echo count($dept_breakdown); ?></h3>
                        <p>Departments Affected</p>
                    </div>
                </div>
            </div>

            <!-- Department Breakdown -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="history-card">
                        <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Resignations by Department</h5>
                        <div class="row">
                            <?php foreach ($dept_breakdown as $dept): ?>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="text-center">
                                    <h6><?php echo htmlspecialchars($dept['department'] ?: 'Unknown'); ?></h6>
                                    <span class="badge bg-primary badge-custom"><?php echo $dept['count']; ?> resignations</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resignation History -->
            <div class="history-card">
                <h5 class="mb-4"><i class="fas fa-list me-2"></i>Complete Resignation Records</h5>

                <?php if (empty($completed_resignations)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">No completed resignations found</h6>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Resignation Date</th>
                                <th>Last Working Day</th>
                                <th>Status</th>
                                <th>Exit Interview</th>
                                <th>Clearance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completed_resignations as $index => $resignation): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($resignation['employee_name']); ?></strong><br>
                                        <small class="text-muted">ID: <?php echo htmlspecialchars($resignation['employee_epf']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?php echo htmlspecialchars($resignation['department'] ?? 'Unknown'); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($resignation['employee_designation'] ?? 'Unknown Position'); ?></small>
                                    </div>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($resignation['date_of_resignation'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($resignation['last_working_day'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $resignation['manager_decision'] === 'approved' ? 'success' : 'danger'; ?> badge-custom">
                                        <?php echo ucfirst($resignation['manager_decision'] ?? 'Pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $resignation['exit_interview_id'] ? 'success' : 'secondary'; ?> badge-custom">
                                        <?php echo $resignation['exit_interview_id'] ? 'Completed' : 'N/A'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        if (!$resignation['clearance_submitted_at']) echo 'secondary';
                                        elseif ($resignation['final_verification']) echo 'success';
                                        else echo 'warning';
                                    ?> badge-custom">
                                        <?php
                                        if (!$resignation['clearance_submitted_at']) echo 'N/A';
                                        elseif ($resignation['final_verification']) echo 'Verified';
                                        else echo 'Pending';
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#resignationModal"
                                            onclick="viewResignationDetails(<?php echo $index; ?>)">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Detailed View Modal -->
            <div class="modal fade" id="resignationModal" tabindex="-1" aria-labelledby="resignationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="resignationModalLabel">
                                <i class="fas fa-user me-2"></i>Resignation Workflow Details
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Employee Information -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-user me-2"></i>Employee Information</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="employee-info"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-calendar me-2"></i>Timeline</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="timeline-info"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Workflow Timeline -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-stream me-2"></i>Workflow Process</h6>
                                </div>
                                <div class="card-body">
                                    <div id="workflow-timeline"></div>
                                </div>
                            </div>

                            <!-- Reason & Comments -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-comments me-2"></i>Reason & Comments</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="reason-comments"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Clearance Details -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Clearance Details</h6>
                                </div>
                                <div class="card-body">
                                    <div id="clearance-details"></div>
                                </div>
                            </div>

                            <!-- Documents -->
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Documents & Evidence</h6>
                                </div>
                                <div class="card-body">
                                    <div id="documents-list"></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pass resignation data to JavaScript -->
            <script>
                const resignationData = <?php echo json_encode($completed_resignations); ?>;
            </script>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Function to view resignation details in modal
        function viewResignationDetails(index) {
            const resignation = resignationData[index];

            // Employee Information
            document.getElementById('employee-info').innerHTML = `
                <div class="row">
                    <div class="col-6">
                        <strong>Name:</strong><br>
                        <span class="text-muted">${resignation.employee_name}</span>
                    </div>
                    <div class="col-6">
                        <strong>Employee ID:</strong><br>
                        <span class="text-muted">${resignation.employee_epf}</span>
                    </div>
                    <div class="col-6 mt-2">
                        <strong>Department:</strong><br>
                        <span class="text-muted">${resignation.department || 'Unknown'}</span>
                    </div>
                    <div class="col-6 mt-2">
                        <strong>Designation:</strong><br>
                        <span class="text-muted">${resignation.employee_designation || 'Unknown'}</span>
                    </div>
                </div>
            `;

            // Timeline Information
            const resignationDate = new Date(resignation.date_of_resignation).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            const lastWorkingDay = new Date(resignation.last_working_day).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            const submittedDate = new Date(resignation.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

            document.getElementById('timeline-info').innerHTML = `
                <div class="timeline-item">
                    <strong>Submitted:</strong><br>
                    <span class="text-muted">${submittedDate}</span>
                </div>
                <div class="timeline-item mt-2">
                    <strong>Resignation Date:</strong><br>
                    <span class="text-muted">${resignationDate}</span>
                </div>
                <div class="timeline-item mt-2">
                    <strong>Last Working Day:</strong><br>
                    <span class="text-muted">${lastWorkingDay}</span>
                </div>
                <div class="timeline-item mt-2">
                    <strong>Days Since Exit:</strong><br>
                    <span class="badge bg-${resignation.days_since_exit > 0 ? 'success' : 'warning'}">${resignation.days_since_exit > 0 ? resignation.days_since_exit + ' days ago' : 'Exit Today'}</span>
                </div>
            `;

            // Workflow Timeline
            let workflowHtml = '<div class="timeline">';

            // Step 1: Resignation Submitted
            workflowHtml += `
                <div class="timeline-step completed">
                    <div class="timeline-marker bg-success">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="timeline-content">
                        <h6>Resignation Submitted</h6>
                        <small class="text-muted">${submittedDate}</small>
                        <p class="mb-0">Employee submitted resignation request</p>
                    </div>
                </div>
            `;

            // Step 2: Manager Decision
            const managerDecisionClass = resignation.manager_decision === 'approved' ? 'completed' : 'rejected';
            const managerDecisionColor = resignation.manager_decision === 'approved' ? 'success' : 'danger';
            const managerDecisionIcon = resignation.manager_decision === 'approved' ? 'check' : 'times';
            const managerDecisionDate = resignation.decision_date ? new Date(resignation.decision_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';

            workflowHtml += `
                <div class="timeline-step ${managerDecisionClass}">
                    <div class="timeline-marker bg-${managerDecisionColor}">
                        <i class="fas fa-${managerDecisionIcon}"></i>
                    </div>
                    <div class="timeline-content">
                        <h6>Manager Decision</h6>
                        <small class="text-muted">${managerDecisionDate}</small>
                        <p class="mb-0">Manager ${resignation.manager_decision || 'pending decision'}</p>
                        ${resignation.manager_comments ? '<div class="mt-1"><small class="text-muted">Comments: ' + resignation.manager_comments + '</small></div>' : ''}
                    </div>
                </div>
            `;

            // Step 3: Exit Interview
            if (resignation.exit_interview_id) {
                workflowHtml += `
                    <div class="timeline-step completed">
                        <div class="timeline-marker bg-success">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="timeline-content">
                            <h6>Exit Interview Completed</h6>
                            <small class="text-muted">Interview conducted</small>
                            <p class="mb-0">Exit interview has been completed and recorded</p>
                        </div>
                    </div>
                `;
            } else {
                workflowHtml += `
                    <div class="timeline-step pending">
                        <div class="timeline-marker bg-secondary">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="timeline-content">
                            <h6>Exit Interview</h6>
                            <small class="text-muted">Not completed</small>
                            <p class="mb-0">Exit interview not conducted</p>
                        </div>
                    </div>
                `;
            }

            // Step 4: Clearance Form
            if (resignation.clearance_submitted_at) {
                const clearanceDate = new Date(resignation.clearance_submitted_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                const clearanceClass = resignation.final_verification ? 'completed' : 'pending';
                const clearanceColor = resignation.final_verification ? 'success' : 'warning';
                const clearanceIcon = resignation.final_verification ? 'check' : 'clock';

                workflowHtml += `
                    <div class="timeline-step ${clearanceClass}">
                        <div class="timeline-marker bg-${clearanceColor}">
                            <i class="fas fa-${clearanceIcon}"></i>
                        </div>
                        <div class="timeline-content">
                            <h6>Clearance Form</h6>
                            <small class="text-muted">${clearanceDate}</small>
                            <p class="mb-0">Clearance form submitted - ${resignation.final_verification ? 'Verified' : 'Pending verification'}</p>
                        </div>
                    </div>
                `;
            } else {
                workflowHtml += `
                    <div class="timeline-step pending">
                        <div class="timeline-marker bg-secondary">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="timeline-content">
                            <h6>Clearance Form</h6>
                            <small class="text-muted">Not submitted</small>
                            <p class="mb-0">Clearance form not submitted</p>
                        </div>
                    </div>
                `;
            }

            workflowHtml += '</div>';
            document.getElementById('workflow-timeline').innerHTML = workflowHtml;

            // Reason & Comments
            let reasonHtml = '';
            if (resignation.reason_for_resignation) {
                reasonHtml += `
                    <div class="mb-3">
                        <h6><i class="fas fa-comment me-2"></i>Reason for Resignation</h6>
                        <div class="alert alert-light">
                            <p class="mb-0">${resignation.reason_for_resignation}</p>
                        </div>
                    </div>
                `;
            }
            if (resignation.manager_comments) {
                reasonHtml += `
                    <div class="mb-3">
                        <h6><i class="fas fa-user-tie me-2"></i>Manager Comments</h6>
                        <div class="alert alert-info">
                            <p class="mb-0">${resignation.manager_comments}</p>
                        </div>
                    </div>
                `;
            }
            if (!reasonHtml) {
                reasonHtml = '<p class="text-muted">No reason or comments provided.</p>';
            }
            document.getElementById('reason-comments').innerHTML = reasonHtml;

            // Clearance Details
            let clearanceHtml = '';
            if (resignation.clearance_submitted_at) {
                clearanceHtml = `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6>IT Assets Clearance</h6>
                            <div class="alert alert-light">
                                <p class="mb-0">${resignation.it_assets_cleared || 'N/A'}</p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6>Finance Clearance</h6>
                            <div class="alert alert-light">
                                <p class="mb-0">${resignation.finance_clearance_cleared || 'N/A'}</p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6>HR Clearance</h6>
                            <div class="alert alert-light">
                                <p class="mb-0">${resignation.hr_clearance_cleared || 'N/A'}</p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6>Stores Clearance</h6>
                            <div class="alert alert-light">
                                <p class="mb-0">${resignation.stores_cleared || 'N/A'}</p>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <h6>Location Clearance</h6>
                            <div class="alert alert-light">
                                <p class="mb-0">${resignation.location_clearance_cleared || 'N/A'}</p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Clearance Status:</strong>
                            <span class="badge bg-${resignation.clearance_status === 'approved' ? 'success' : 'warning'} ms-2">
                                ${resignation.clearance_status ? resignation.clearance_status.charAt(0).toUpperCase() + resignation.clearance_status.slice(1) : 'Pending'}
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>Final Verification:</strong>
                            <span class="badge bg-${resignation.final_verification ? 'success' : 'warning'} ms-2">
                                ${resignation.final_verification ? 'Verified' : 'Pending'}
                            </span>
                        </div>
                    </div>
                `;
            } else {
                clearanceHtml = '<p class="text-muted">Clearance form not submitted.</p>';
            }
            document.getElementById('clearance-details').innerHTML = clearanceHtml;

            // Documents
            let documentsHtml = '<div class="d-flex gap-2 flex-wrap">';
            let hasDocuments = false;

            if (resignation.resignation_pdf_file) {
                documentsHtml += `
                    <a href="../../${resignation.resignation_pdf_path}" target="_blank" class="btn btn-outline-danger">
                        <i class="fas fa-file-pdf me-2"></i>Resignation Letter
                    </a>
                `;
                hasDocuments = true;
            }

            if (resignation.clearance_pdf_file) {
                documentsHtml += `
                    <a href="../../${resignation.clearance_pdf_path}" target="_blank" class="btn btn-outline-success">
                        <i class="fas fa-file-pdf me-2"></i>Clearance Evidence
                    </a>
                `;
                hasDocuments = true;
            }

            if (!hasDocuments) {
                documentsHtml += '<p class="text-muted">No documents available.</p>';
            }

            documentsHtml += '</div>';
            document.getElementById('documents-list').innerHTML = documentsHtml;
        }
    </script>

    <style>
        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline-step {
            position: relative;
            margin-bottom: 2rem;
            padding-left: 2rem;
        }

        .timeline-step:before {
            content: '';
            position: absolute;
            left: -1rem;
            top: 2rem;
            width: 2px;
            height: calc(100% + 1rem);
            background-color: #dee2e6;
        }

        .timeline-step:last-child:before {
            display: none;
        }

        .timeline-marker {
            position: absolute;
            left: -1.5rem;
            top: 0;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
        }

        .timeline-content h6 {
            margin-bottom: 0.25rem;
        }
    </style>
</body>
</html>