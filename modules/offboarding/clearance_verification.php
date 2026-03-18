<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($database, 'offboarding.clearance_verification')) {
    header('Location: ../../dashboard.php');
    exit;
}
$success_message = '';
$error_message = '';

// Check if user is superadmin
if ($user['account_type'] !== 'superadmin') {
    header('Location: ../../dashboard.php');
    exit();
}

// Handle verification decision
if ($_POST && isset($_POST['action'])) {
    $clearance_id = $_POST['clearance_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $comments = trim($_POST['verification_comments'] ?? '');

    if ($action === 'approve' || $action === 'reject') {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $final_verification = $action === 'approve' ? 1 : 0;

        $update_data = [
            'status' => $status,
            'final_verification' => $final_verification,
            'superadmin_epf' => $user['epf_number'],
            'verification_date' => date('Y-m-d H:i:s'),
            'verification_comments' => $comments
        ];

        try {
            $result = $database->update('clearance_forms', $update_data, 'id = ?', [$clearance_id]);

            if ($result && $action === 'approve') {
                // Update resignation status to completed
                $clearance = $database->fetchAll("SELECT resignation_id FROM clearance_forms WHERE id = ?", [$clearance_id])[0];
                $database->update('resignations', ['status' => 'completed'], 'id = ?', [$clearance['resignation_id']]);

                $success_message = "Clearance has been approved and offboarding process completed successfully. Goodbye notification will be sent to the employee.";
            } elseif ($result) {
                $success_message = "Clearance has been rejected. Employee will be notified to resubmit.";
            } else {
                $error_message = "Failed to update clearance status.";
            }
        } catch (Exception $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Get pending clearances for review
$pending_clearances_query = "
    SELECT cf.*, r.last_working_day, r.reason_for_resignation,
           DATEDIFF(r.last_working_day, CURDATE()) as days_remaining,
           ei.submitted_at as exit_interview_date
    FROM clearance_forms cf
    LEFT JOIN resignations r ON cf.resignation_id = r.id
    LEFT JOIN exit_interviews ei ON r.id = ei.resignation_id
    WHERE cf.status = 'pending'
    ORDER BY cf.submitted_at ASC
";
$pending_clearances = $database->fetchAll($pending_clearances_query);

// Get recent verifications
$recent_verifications_query = "
    SELECT cf.*, r.last_working_day
    FROM clearance_forms cf
    LEFT JOIN resignations r ON cf.resignation_id = r.id
    WHERE cf.status IN ('approved', 'rejected')
    AND cf.superadmin_epf = ?
    ORDER BY cf.verification_date DESC
    LIMIT 10
";
$recent_verifications = $database->fetchAll($recent_verifications_query, [$user['epf_number']]);

// Generate goodbye message
function generateGoodbyeMessage($employee_name, $service_years) {
    return "Dear {$employee_name}, your resignation has been Accepted and we have reviewed your exit process & it was completed. thank you for your {$service_years} years service.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Clearance Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
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

        .card-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
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

        .clearance-card {
            border-left: 4px solid var(--info-color);
            margin-bottom: 1.5rem;
        }

        .clearance-card .card-body {
            padding: 1.5rem;
        }

        .employee-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.25rem 0;
        }

        .info-label {
            font-weight: 500;
            color: #6c757d;
        }

        .info-value {
            font-weight: 600;
            color: #2c3e50;
        }

        .clearance-details {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .clearance-section {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f1f3f4;
        }

        .clearance-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .clearance-title {
            font-weight: 600;
            color: var(--success-color);
            margin-bottom: 0.5rem;
        }

        .clearance-text {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .urgency-high {
            border-left-color: var(--danger-color) !important;
        }

        .urgency-medium {
            border-left-color: var(--warning-color) !important;
        }

        .urgency-low {
            border-left-color: var(--success-color) !important;
        }

        .action-buttons .btn {
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            margin: 0 0.25rem;
        }

        .btn-approve {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            border: none;
            color: white;
        }

        .btn-reject {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            border: none;
            color: white;
        }

        .btn-view {
            background: linear-gradient(135deg, var(--info-color), #138496);
            border: none;
            color: white;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }

        .evidence-preview {
            max-width: 200px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .goodbye-message {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 1rem;
            font-style: italic;
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

            .action-buttons {
                text-align: center;
                margin-top: 1rem;
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
                        <h1><i class="fas fa-check-double me-3"></i>Clearance Verification</h1>
                        <p>Verify and approve clearance completion across all departments</p>
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
                    <li class="breadcrumb-item active">Clearance Verification</li>
                </ol>
            </nav>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Pending Clearances -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-hourglass-half me-2"></i>Pending Verifications (<?php echo count($pending_clearances); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($pending_clearances)): ?>
                        <?php foreach ($pending_clearances as $clearance): ?>
                            <?php
                            $days_remaining = $clearance['days_remaining'];
                            $urgency_class = '';
                            if ($days_remaining <= 3) {
                                $urgency_class = 'urgency-high';
                            } elseif ($days_remaining <= 7) {
                                $urgency_class = 'urgency-medium';
                            } else {
                                $urgency_class = 'urgency-low';
                            }
                            ?>
                            <div class="card clearance-card <?php echo $urgency_class; ?>">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="card-title mb-2"><?php echo htmlspecialchars($clearance['employee_name']); ?></h6>

                                            <div class="employee-info">
                                                <div class="info-row">
                                                    <span class="info-label">Employee ID:</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($clearance['employee_epf']); ?></span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Department:</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($clearance['employee_department']); ?></span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Last Working Day:</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($clearance['last_working_day'])); ?></span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Submitted:</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($clearance['submitted_at'])); ?></span>
                                                </div>
                                                <?php if ($days_remaining >= 0): ?>
                                                <div class="info-row">
                                                    <span class="info-label">Days Remaining:</span>
                                                    <span class="info-value <?php echo $days_remaining <= 3 ? 'text-danger' : ($days_remaining <= 7 ? 'text-warning' : 'text-success'); ?>">
                                                        <?php echo $days_remaining; ?> days
                                                    </span>
                                                </div>
                                                <?php endif; ?>
                                            </div>

                                            <button class="btn btn-view btn-sm" data-bs-toggle="modal" data-bs-target="#clearanceModal"
                                                    data-clearance-id="<?php echo $clearance['id']; ?>"
                                                    data-employee-name="<?php echo htmlspecialchars($clearance['employee_name']); ?>"
                                                    data-it-assets="<?php echo htmlspecialchars($clearance['it_assets_cleared']); ?>"
                                                    data-finance="<?php echo htmlspecialchars($clearance['finance_clearance_cleared']); ?>"
                                                    data-hr="<?php echo htmlspecialchars($clearance['hr_clearance_cleared']); ?>"
                                                    data-stores="<?php echo htmlspecialchars($clearance['stores_cleared']); ?>"
                                                    data-location="<?php echo htmlspecialchars($clearance['location_clearance_cleared']); ?>"
                                                    data-evidence-file="<?php echo htmlspecialchars($clearance['clearance_pdf_file'] ?? ''); ?>"
                                                    data-evidence-path="<?php echo htmlspecialchars($clearance['clearance_pdf_path'] ?? ''); ?>">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </button>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="text-end">
                                                <div class="action-buttons">
                                                    <button class="btn btn-approve" data-bs-toggle="modal" data-bs-target="#verificationModal"
                                                            data-clearance-id="<?php echo $clearance['id']; ?>"
                                                            data-employee-name="<?php echo htmlspecialchars($clearance['employee_name']); ?>"
                                                            data-action="approve">
                                                        <i class="fas fa-check me-1"></i>Approve
                                                    </button>
                                                    <button class="btn btn-reject" data-bs-toggle="modal" data-bs-target="#verificationModal"
                                                            data-clearance-id="<?php echo $clearance['id']; ?>"
                                                            data-employee-name="<?php echo htmlspecialchars($clearance['employee_name']); ?>"
                                                            data-action="reject">
                                                        <i class="fas fa-times me-1"></i>Reject
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No pending clearances to verify</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Verifications -->
            <?php if (!empty($recent_verifications)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Verifications</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="verificationsTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Last Working Day</th>
                                    <th>Decision</th>
                                    <th>Decision Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_verifications as $verification): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($verification['employee_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($verification['employee_epf']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($verification['employee_department']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($verification['last_working_day'])); ?></td>
                                    <td>
                                        <?php if ($verification['final_verification']): ?>
                                            <span class="badge bg-success status-badge">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger status-badge">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($verification['verification_date'])); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = match($verification['status']) {
                                            'approved' => 'bg-success',
                                            'rejected' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?> status-badge">
                                            <?php echo ucfirst($verification['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Clearance Details Modal -->
    <div class="modal fade" id="clearanceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Clearance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="mb-3"><strong>Employee:</strong> <span id="modal-employee-name"></span></h6>

                    <div class="clearance-details">
                        <div class="clearance-section">
                            <div class="clearance-title"><i class="fas fa-laptop me-2"></i>IT Assets Clearance</div>
                            <div class="clearance-text" id="modal-it-assets"></div>
                        </div>

                        <div class="clearance-section">
                            <div class="clearance-title"><i class="fas fa-dollar-sign me-2"></i>Finance Clearance</div>
                            <div class="clearance-text" id="modal-finance"></div>
                        </div>

                        <div class="clearance-section">
                            <div class="clearance-title"><i class="fas fa-users me-2"></i>HR Clearance</div>
                            <div class="clearance-text" id="modal-hr"></div>
                        </div>

                        <div class="clearance-section">
                            <div class="clearance-title"><i class="fas fa-warehouse me-2"></i>Stores Clearance</div>
                            <div class="clearance-text" id="modal-stores"></div>
                        </div>

                        <div class="clearance-section">
                            <div class="clearance-title"><i class="fas fa-map-marker-alt me-2"></i>Location Clearance</div>
                            <div class="clearance-text" id="modal-location"></div>
                        </div>

                        <div class="clearance-section" id="evidence-section" style="display: none;">
                            <div class="clearance-title"><i class="fas fa-paperclip me-2"></i>Evidence Document</div>
                            <div class="clearance-text">
                                <a href="#" id="evidence-link" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-download me-1"></i>Download Evidence
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Verification Modal -->
    <div class="modal fade" id="verificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="verificationModalTitle">Clearance Verification</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="verification_clearance_id" name="clearance_id">
                        <input type="hidden" id="verification_action" name="action">

                        <div class="alert alert-info">
                            <strong>Employee:</strong> <span id="verification_employee_name"></span>
                        </div>

                        <div class="mb-3">
                            <label for="verification_comments" class="form-label">Verification Comments</label>
                            <textarea class="form-control" id="verification_comments" name="verification_comments" rows="3"
                                      placeholder="Add comments about your verification decision..."></textarea>
                        </div>

                        <div id="verification_confirmation" class="alert"></div>

                        <div id="goodbye_message_preview" class="goodbye-message" style="display: none;">
                            <strong>Goodbye Message Preview:</strong><br>
                            <span id="goodbye_text"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="verificationConfirmButton" class="btn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#verificationsTable').DataTable({
                pageLength: 10,
                responsive: true
            });
        });

        // Handle clearance details modal
        const clearanceModal = document.getElementById('clearanceModal');
        clearanceModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            document.getElementById('modal-employee-name').textContent = button.getAttribute('data-employee-name');
            document.getElementById('modal-it-assets').textContent = button.getAttribute('data-it-assets');
            document.getElementById('modal-finance').textContent = button.getAttribute('data-finance');
            document.getElementById('modal-hr').textContent = button.getAttribute('data-hr');
            document.getElementById('modal-stores').textContent = button.getAttribute('data-stores');
            document.getElementById('modal-location').textContent = button.getAttribute('data-location');

            const evidenceFile = button.getAttribute('data-evidence-file');
            const evidencePath = button.getAttribute('data-evidence-path');

            if (evidenceFile && evidencePath) {
                document.getElementById('evidence-section').style.display = 'block';
                document.getElementById('evidence-link').href = '../../' + evidencePath; // Add proper path prefix
                document.getElementById('evidence-link').innerHTML = `<i class="fas fa-download me-1"></i>Download ${evidenceFile}`;
            } else {
                document.getElementById('evidence-section').style.display = 'none';
            }
        });

        // Handle verification modal
        const verificationModal = document.getElementById('verificationModal');
        verificationModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const clearanceId = button.getAttribute('data-clearance-id');
            const employeeName = button.getAttribute('data-employee-name');
            const action = button.getAttribute('data-action');

            document.getElementById('verification_clearance_id').value = clearanceId;
            document.getElementById('verification_action').value = action;
            document.getElementById('verification_employee_name').textContent = employeeName;

            const confirmationDiv = document.getElementById('verification_confirmation');
            const confirmButton = document.getElementById('verificationConfirmButton');
            const goodbyePreview = document.getElementById('goodbye_message_preview');

            if (action === 'approve') {
                document.getElementById('verificationModalTitle').textContent = 'Approve Clearance';
                confirmationDiv.className = 'alert alert-success';
                confirmationDiv.textContent = 'Are you sure you want to APPROVE this clearance? This will complete the offboarding process and send a goodbye notification to the employee.';
                confirmButton.className = 'btn btn-success';
                confirmButton.innerHTML = '<i class="fas fa-check me-1"></i>Approve Clearance';

                // Show goodbye message preview
                const serviceYears = '3'; // This should be calculated from hire date
                const goodbyeMessage = `Dear ${employeeName}, your resignation has been Accepted and we have reviewed your exit process & it was completed. thank you for your ${serviceYears} years service.`;
                document.getElementById('goodbye_text').textContent = goodbyeMessage;
                goodbyePreview.style.display = 'block';
            } else {
                document.getElementById('verificationModalTitle').textContent = 'Reject Clearance';
                confirmationDiv.className = 'alert alert-danger';
                confirmationDiv.textContent = 'Are you sure you want to REJECT this clearance? The employee will need to resubmit their clearance form.';
                confirmButton.className = 'btn btn-danger';
                confirmButton.innerHTML = '<i class="fas fa-times me-1"></i>Reject Clearance';
                goodbyePreview.style.display = 'none';
            }
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>