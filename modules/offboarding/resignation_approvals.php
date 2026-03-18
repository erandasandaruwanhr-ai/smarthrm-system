<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();
$success_message = '';
$error_message = '';

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($database, 'offboarding.resignation_approvals')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Handle approval/rejection
if ($_POST && isset($_POST['action'])) {
    $resignation_id = $_POST['resignation_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $comments = trim($_POST['comments'] ?? '');

    if ($action === 'approve' || $action === 'reject') {
        $decision = $action === 'approve' ? 'approved' : 'rejected';
        $status = $action === 'approve' ? 'approved' : 'rejected';

        $update_data = [
            'status' => $status,
            'manager_decision' => $decision,
            'manager_epf' => $user['epf_number'],
            'manager_comments' => $comments,
            'decision_date' => date('Y-m-d H:i:s')
        ];

        try {
            $result = $database->update('resignations', $update_data, 'id = ?', [$resignation_id]);

            if ($result) {
                $action_text = $action === 'approve' ? 'approved' : 'rejected';
                $success_message = "Resignation has been {$action_text} successfully.";
            } else {
                $error_message = "Failed to update resignation status.";
            }
        } catch (Exception $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Get pending resignations for review
$pending_resignations_query = "
    SELECT r.*,
           e.department, e.designation,
           DATEDIFF(r.last_working_day, CURDATE()) as days_remaining
    FROM resignations r
    LEFT JOIN employees e ON r.employee_epf = e.epf_number
    WHERE r.status = 'pending'
    ORDER BY r.created_at ASC
";
$pending_resignations = $database->fetchAll($pending_resignations_query);

// Get recent decisions
$recent_decisions_query = "
    SELECT r.*,
           e.department, e.designation,
           DATEDIFF(r.last_working_day, CURDATE()) as days_remaining
    FROM resignations r
    LEFT JOIN employees e ON r.employee_epf = e.epf_number
    WHERE r.status IN ('approved', 'rejected')
    AND r.manager_epf = ?
    ORDER BY r.decision_date DESC
    LIMIT 10
";
$recent_decisions = $database->fetchAll($recent_decisions_query, [$user['epf_number']]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Resignation Approvals</title>
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

        .resignation-card {
            border-left: 4px solid var(--warning-color);
            margin-bottom: 1.5rem;
        }

        .resignation-card .card-body {
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

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            border-radius: 15px 15px 0 0;
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
                        <h1><i class="fas fa-check-circle me-3"></i>Resignation Approvals</h1>
                        <p>Review and approve employee resignation requests and manage approval workflow</p>
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
                    <li class="breadcrumb-item active">Resignation Approvals</li>
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

            <!-- Pending Resignations -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Approvals (<?php echo count($pending_resignations); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($pending_resignations)): ?>
                        <?php foreach ($pending_resignations as $resignation): ?>
                            <?php
                            $days_remaining = $resignation['days_remaining'];
                            $urgency_class = '';
                            if ($days_remaining <= 7) {
                                $urgency_class = 'urgency-high';
                            } elseif ($days_remaining <= 14) {
                                $urgency_class = 'urgency-medium';
                            } else {
                                $urgency_class = 'urgency-low';
                            }
                            ?>
                            <div class="card resignation-card <?php echo $urgency_class; ?>">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="card-title mb-2"><?php echo htmlspecialchars($resignation['employee_name']); ?></h6>

                                            <div class="employee-info">
                                                <div class="info-row">
                                                    <span class="info-label">Employee ID:</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($resignation['employee_epf']); ?></span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Department:</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($resignation['employee_department']); ?></span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Designation:</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($resignation['employee_designation']); ?></span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Resignation Date:</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($resignation['date_of_resignation'])); ?></span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Last Working Day:</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($resignation['last_working_day'])); ?></span>
                                                </div>
                                                <?php if ($days_remaining >= 0): ?>
                                                <div class="info-row">
                                                    <span class="info-label">Days Remaining:</span>
                                                    <span class="info-value <?php echo $days_remaining <= 7 ? 'text-danger' : ($days_remaining <= 14 ? 'text-warning' : 'text-success'); ?>">
                                                        <?php echo $days_remaining; ?> days
                                                    </span>
                                                </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($resignation['reason_for_resignation']): ?>
                                            <div class="mt-2">
                                                <strong>Reason:</strong>
                                                <p class="text-muted mb-0"><?php echo htmlspecialchars($resignation['reason_for_resignation']); ?></p>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (isset($resignation['resignation_pdf_path']) && !empty($resignation['resignation_pdf_path'])): ?>
                                            <div class="mt-2">
                                                <strong>Resignation Letter:</strong>
                                                <div class="mt-1">
                                                    <a href="../../<?php echo htmlspecialchars($resignation['resignation_pdf_path']); ?>"
                                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-pdf me-2"></i>View PDF
                                                    </a>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="text-end">
                                                <small class="text-muted">
                                                    Submitted: <?php echo date('M d, Y', strtotime($resignation['created_at'])); ?>
                                                </small>

                                                <div class="action-buttons mt-3">
                                                    <button class="btn btn-approve" data-bs-toggle="modal" data-bs-target="#approvalModal"
                                                            data-resignation-id="<?php echo $resignation['id']; ?>"
                                                            data-employee-name="<?php echo htmlspecialchars($resignation['employee_name']); ?>"
                                                            data-action="approve">
                                                        <i class="fas fa-check me-1"></i>Approve
                                                    </button>
                                                    <button class="btn btn-reject" data-bs-toggle="modal" data-bs-target="#approvalModal"
                                                            data-resignation-id="<?php echo $resignation['id']; ?>"
                                                            data-employee-name="<?php echo htmlspecialchars($resignation['employee_name']); ?>"
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
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No pending resignations to review</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Decisions -->
            <?php if (!empty($recent_decisions)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Decisions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
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
                                <?php foreach ($recent_decisions as $decision): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($decision['employee_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($decision['employee_epf']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($decision['employee_department']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($decision['last_working_day'])); ?></td>
                                    <td>
                                        <?php if ($decision['manager_decision'] === 'approved'): ?>
                                            <span class="badge bg-success status-badge">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger status-badge">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($decision['decision_date'])); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = match($decision['status']) {
                                            'approved' => 'bg-success',
                                            'rejected' => 'bg-danger',
                                            'completed' => 'bg-info',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?> status-badge">
                                            <?php echo ucfirst($decision['status']); ?>
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

    <!-- Approval Modal -->
    <div class="modal fade" id="approvalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Resignation Decision</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="resignation_id" name="resignation_id">
                        <input type="hidden" id="action" name="action">

                        <div class="alert alert-info">
                            <strong>Employee:</strong> <span id="employee_name"></span>
                        </div>

                        <div class="mb-3">
                            <label for="comments" class="form-label">Comments (Optional)</label>
                            <textarea class="form-control" id="comments" name="comments" rows="3"
                                      placeholder="Add any comments about your decision..."></textarea>
                        </div>

                        <div id="confirmation_text" class="alert"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="confirmButton" class="btn">Confirm</button>
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
            $('.table').DataTable({
                pageLength: 10,
                responsive: true
            });
        });

        // Handle approval modal
        const approvalModal = document.getElementById('approvalModal');
        approvalModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const resignationId = button.getAttribute('data-resignation-id');
            const employeeName = button.getAttribute('data-employee-name');
            const action = button.getAttribute('data-action');

            document.getElementById('resignation_id').value = resignationId;
            document.getElementById('action').value = action;
            document.getElementById('employee_name').textContent = employeeName;

            const confirmationText = document.getElementById('confirmation_text');
            const confirmButton = document.getElementById('confirmButton');

            if (action === 'approve') {
                document.getElementById('modalTitle').textContent = 'Approve Resignation';
                confirmationText.className = 'alert alert-success';
                confirmationText.textContent = 'Are you sure you want to APPROVE this resignation? The employee will be notified and can proceed with the exit interview process.';
                confirmButton.className = 'btn btn-success';
                confirmButton.innerHTML = '<i class="fas fa-check me-1"></i>Approve Resignation';
            } else {
                document.getElementById('modalTitle').textContent = 'Reject Resignation';
                confirmationText.className = 'alert alert-danger';
                confirmationText.textContent = 'Are you sure you want to REJECT this resignation? The employee will be notified of your decision.';
                confirmButton.className = 'btn btn-danger';
                confirmButton.innerHTML = '<i class="fas fa-times me-1"></i>Reject Resignation';
            }
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>