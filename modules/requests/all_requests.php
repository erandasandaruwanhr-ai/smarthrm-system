<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/notification_system.php';

$user = getCurrentUser();
$db = new Database();

// Function to get status history for a request
function getStatusHistory($db, $request_id) {
    try {
        return $db->fetchAll("
            SELECT rsh.*, e.name as changed_by_name
            FROM request_status_history rsh
            LEFT JOIN employees e ON rsh.changed_by = e.epf_number
            WHERE rsh.request_id = ?
            ORDER BY rsh.changed_at DESC
        ", [$request_id]);
    } catch (Exception $e) {
        error_log("Error fetching status history: " . $e->getMessage());
        return [];
    }
}

// Function to map old status values to new 5-stage system
function mapStatusToNewSystem($oldStatus) {
    $statusMap = [
        'Pending Supervisor' => 'Request Submitted',
        'Pending Admin' => 'Documents Pending',
        'Pending Manager' => 'Processing',
        'Approved' => 'Completed',
        'Rejected' => 'Cancelled',
        'Cancelled' => 'Cancelled',
        'More Info Required' => 'Documents Pending'
    ];

    return isset($statusMap[$oldStatus]) ? $statusMap[$oldStatus] : $oldStatus;
}

// Check permission - only admins can access this
if (!in_array($user['account_type'], ['admin', 'superadmin'])) {
    header('Location: ../../dashboard.php');
    exit();
}

// Initialize message variables
$success_message = '';
$error_message = '';

// Handle workflow status updates (only for superadmin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    error_log("Status update POST received. User type: " . $user['account_type']);

    if ($user['account_type'] !== 'superadmin') {
        error_log("Access denied - user is not superadmin");
        $error_message = "Access denied. Only superadmins can update request status.";
    } else {
        try {
        $request_id = $_POST['request_id'];
        $new_status = $_POST['new_status'];
        $remarks = $_POST['remarks'] ?? '';

        // Define valid status transitions
        $valid_statuses = [
            'Request Submitted',
            'Documents Pending',
            'Processing',
            'Completed',
            'Handovered'
        ];

        if (in_array($new_status, $valid_statuses)) {
            // Get current status before updating
            $currentRequest = $db->fetch("SELECT status FROM employee_requests WHERE request_id = ?", [$request_id]);
            $previous_status_raw = $currentRequest['status'] ?? '';

            // Map the statuses to the new 5-stage system for history recording
            $previous_status_mapped = mapStatusToNewSystem($previous_status_raw);
            $new_status_mapped = $new_status; // Already using new system values

            // Update the request status
            $db->update('employee_requests', [
                'status' => $new_status,
                'admin_remarks' => $remarks,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'request_id = ?', [$request_id]);

            // Record status change in history table using mapped statuses
            try {
                $db->insert('request_status_history', [
                    'request_id' => $request_id,
                    'previous_status' => $previous_status_mapped,
                    'new_status' => $new_status_mapped,
                    'changed_by' => $user['epf_number'],
                    'change_reason' => 'Admin Status Update',
                    'remarks' => $remarks,
                    'changed_at' => date('Y-m-d H:i:s')
                ]);
                error_log("Status history recorded: $request_id from $previous_status_mapped to $new_status_mapped");
            } catch (Exception $historyError) {
                error_log("Failed to record status history: " . $historyError->getMessage());
            }

            // Set success message first
            $success_message = "Request $request_id status updated to: $new_status";

            // Send notification to employee
            $request = $db->fetch("SELECT * FROM employee_requests WHERE request_id = ?", [$request_id]);
            if ($request) {
                $employee = $db->fetch("SELECT id FROM employees WHERE epf_number = ?", [$request['epf_number']]);
                if ($employee) {
                    try {
                        $notifications = getNotificationSystem();

                        // Debug: Log the employee data
                        error_log("Employee ID found: " . $employee['id'] . " for EPF: " . $request['epf_number']);

                        $result = $notifications->sendNotification(
                            "Request Status Updated - " . $request['request_type'],
                            "Your " . $request['request_type'] . " request (ID: " . $request_id . ") status has been updated to: " . $new_status .
                            ($remarks ? ". Remarks: " . $remarks : ""),
                            "employee",
                            [
                                'target_users' => [$employee['id']],
                                'target_roles' => [], // Empty roles for now
                                'action_needed' => $new_status === 'Documents Pending',
                                'priority' => $new_status === 'Documents Pending' ? 'high' : 'medium',
                                'module_link' => 'modules/requests/my_requests.php'
                            ]
                        );

                        // Debug: Log notification result
                        error_log("Notification sent result: " . ($result ? 'success' : 'failed'));

                        if ($result) {
                            $success_message .= " (Notification sent)";
                        } else {
                            $success_message .= " (Notification failed)";
                        }

                    } catch (Exception $e) {
                        error_log("Notification error: " . $e->getMessage());
                        $success_message .= " (Notification error: " . $e->getMessage() . ")";
                    }
                } else {
                    error_log("Employee not found for EPF: " . $request['epf_number']);
                }
            } else {
                error_log("Request not found: " . $request_id);
            }
        } else {
            $error_message = "Invalid status selected.";
        }
        } catch (Exception $e) {
            $error_message = "Error updating request: " . $e->getMessage();
        }
    }
}

// Get all requests based on user role
if (in_array($user['account_type'], ['superadmin'])) {
    // Superadmin sees all requests
    $all_requests = $db->fetchAll("
        SELECT er.*, e.name as employee_name, e.department, l.location_name as location
        FROM employee_requests er
        LEFT JOIN employees e ON er.epf_number = e.epf_number
        LEFT JOIN locations l ON e.location_id = l.id
        ORDER BY COALESCE(er.created_at, NOW()) DESC
    ");
} else {
    // Admin sees location requests
    $all_requests = $db->fetchAll("
        SELECT er.*, e.name as employee_name, e.department, l.location_name as location
        FROM employee_requests er
        LEFT JOIN employees e ON er.epf_number = e.epf_number
        LEFT JOIN locations l ON e.location_id = l.id
        WHERE l.location_name = ?
        ORDER BY COALESCE(er.created_at, NOW()) DESC
    ", [$user['location']]);
}

// Get statistics with status mapping
$stats = [
    'total' => count($all_requests),
    'submitted' => count(array_filter($all_requests, function($r) { return mapStatusToNewSystem($r['status']) === 'Request Submitted'; })),
    'documents_pending' => count(array_filter($all_requests, function($r) { return mapStatusToNewSystem($r['status']) === 'Documents Pending'; })),
    'processing' => count(array_filter($all_requests, function($r) { return mapStatusToNewSystem($r['status']) === 'Processing'; })),
    'completed' => count(array_filter($all_requests, function($r) { return mapStatusToNewSystem($r['status']) === 'Completed'; })),
    'handovered' => count(array_filter($all_requests, function($r) { return mapStatusToNewSystem($r['status']) === 'Handovered'; })),
    'urgent' => count(array_filter($all_requests, function($r) { return $r['urgency'] === 'Urgent'; }))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - All Requests</title>
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

        .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        .status-request-submitted { background-color: #17a2b8; color: #fff; }
        .status-documents-pending { background-color: #ffc107; color: #000; }
        .status-processing { background-color: #6c757d; color: #fff; }
        .status-completed { background-color: #28a745; color: #fff; }
        .status-handovered { background-color: #343a40; color: #fff; }

        .table-responsive {
            border-radius: 0.5rem;
        }

        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
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
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-list-alt me-3"></i>All Requests</h1>
                        <p>Manage and review all employee service requests across the organization</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Employee Requests</a></li>
                    <li class="breadcrumb-item active">All Requests</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-end mb-3">
                <small class="text-muted">
                    Viewing: <?php echo $user['account_type'] === 'superadmin' ? 'All Locations' : htmlspecialchars($user['location']); ?>
                </small>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message) && !empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message) && !empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-2 col-sm-6 mb-3">
                    <div class="card stats-card h-100 border-primary">
                        <div class="card-body text-center">
                            <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                            <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                            <p class="card-text text-muted mb-0">Total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-3">
                    <div class="card stats-card h-100 border-info">
                        <div class="card-body text-center">
                            <i class="fas fa-paper-plane fa-2x text-info mb-2"></i>
                            <h4 class="text-info"><?php echo $stats['submitted']; ?></h4>
                            <p class="card-text text-muted mb-0">Submitted</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-3">
                    <div class="card stats-card h-100 border-warning">
                        <div class="card-body text-center">
                            <i class="fas fa-file-import fa-2x text-warning mb-2"></i>
                            <h4 class="text-warning"><?php echo $stats['documents_pending']; ?></h4>
                            <p class="card-text text-muted mb-0">Docs Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-3">
                    <div class="card stats-card h-100 border-secondary">
                        <div class="card-body text-center">
                            <i class="fas fa-cogs fa-2x text-secondary mb-2"></i>
                            <h4 class="text-secondary"><?php echo $stats['processing']; ?></h4>
                            <p class="card-text text-muted mb-0">Processing</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-3">
                    <div class="card stats-card h-100 border-success">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h4 class="text-success"><?php echo $stats['completed']; ?></h4>
                            <p class="card-text text-muted mb-0">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-3">
                    <div class="card stats-card h-100 border-dark">
                        <div class="card-body text-center">
                            <i class="fas fa-handshake fa-2x text-dark mb-2"></i>
                            <h4 class="text-dark"><?php echo $stats['handovered']; ?></h4>
                            <p class="card-text text-muted mb-0">Handovered</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Requests Table -->
            <div class="content-card">
                <div class="card-header-modern">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>Request Overview
                        </h5>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="statusFilter" id="all" autocomplete="off" checked>
                            <label class="btn btn-outline-primary btn-sm" for="all">All</label>

                            <input type="radio" class="btn-check" name="statusFilter" id="submitted" autocomplete="off">
                            <label class="btn btn-outline-info btn-sm" for="submitted">Submitted</label>

                            <input type="radio" class="btn-check" name="statusFilter" id="processing" autocomplete="off">
                            <label class="btn btn-outline-secondary btn-sm" for="processing">Processing</label>

                            <input type="radio" class="btn-check" name="statusFilter" id="completed" autocomplete="off">
                            <label class="btn btn-outline-success btn-sm" for="completed">Completed</label>

                            <input type="radio" class="btn-check" name="statusFilter" id="handovered" autocomplete="off">
                            <label class="btn btn-outline-dark btn-sm" for="handovered">Handovered</label>
                        </div>
                    </div>
                    <?php if (empty($all_requests)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Requests Found</h5>
                            <p class="text-muted">No employee requests found in your scope.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Type</th>
                                        <th>Subject</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="requestsTableBody">
                                    <?php foreach ($all_requests as $request): ?>
                                        <tr class="request-row"
                                            data-status="<?php echo strtolower($request['status']); ?>"
                                            data-urgency="<?php echo strtolower($request['urgency']); ?>">
                                            <td>
                                                <code><?php echo $request['request_id']; ?></code>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($request['employee_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($request['epf_number']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?>
                                                </span>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($request['location'] ?? 'N/A'); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($request['request_type']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars(substr($request['subject'], 0, 40)) . (strlen($request['subject']) > 40 ? '...' : ''); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $request['urgency'] === 'Urgent' ? 'danger' : ($request['urgency'] === 'High' ? 'warning text-dark' : 'secondary'); ?>">
                                                    <?php echo $request['urgency']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php $mappedStatus = mapStatusToNewSystem($request['status']); ?>
                                                <span class="badge status-badge status-<?php echo strtolower(str_replace(' ', '-', $mappedStatus)); ?>">
                                                    <?php echo $mappedStatus; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($request['created_at'] ?? 'now')); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('g:i A', strtotime($request['created_at'] ?? 'now')); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-outline-primary btn-sm"
                                                        data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $request['id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php $buttonMappedStatus = mapStatusToNewSystem($request['status']); ?>
                                                <?php if ($user['account_type'] === 'superadmin' && !in_array($buttonMappedStatus, ['Handovered'])): ?>
                                                    <button type="button" class="btn btn-outline-success btn-sm ms-1"
                                                            onclick="showHandleModal('<?php echo $request['request_id']; ?>', '<?php echo htmlspecialchars($buttonMappedStatus); ?>')">
                                                        <i class="fas fa-cogs"></i> Handle
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Modals -->
    <?php foreach ($all_requests as $request): ?>
        <div class="modal fade" id="viewModal<?php echo $request['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Request Details - <?php echo $request['request_id']; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Employee:</strong> <?php echo htmlspecialchars($request['employee_name']); ?></p>
                                <p><strong>EPF Number:</strong> <?php echo htmlspecialchars($request['epf_number']); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?></p>
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($request['location'] ?? 'N/A'); ?></p>
                                <p><strong>Type:</strong> <?php echo htmlspecialchars($request['request_type']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Urgency:</strong>
                                    <span class="badge bg-<?php echo $request['urgency'] === 'Urgent' ? 'danger' : ($request['urgency'] === 'High' ? 'warning text-dark' : 'secondary'); ?>">
                                        <?php echo $request['urgency']; ?>
                                    </span>
                                </p>
                                <p><strong>Status:</strong>
                                    <?php $modalMappedStatus = mapStatusToNewSystem($request['status']); ?>
                                    <span class="badge status-badge status-<?php echo strtolower(str_replace(' ', '-', $modalMappedStatus)); ?>">
                                        <?php echo $modalMappedStatus; ?>
                                    </span>
                                </p>
                                <p><strong>Current Stage:</strong> <?php echo $request['current_stage']; ?></p>
                                <p><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($request['created_at'] ?? 'now')); ?></p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <p><strong>Subject:</strong></p>
                            <p class="bg-light p-3 rounded"><?php echo htmlspecialchars($request['subject']); ?></p>
                        </div>
                        <div class="mt-3">
                            <p><strong>Details:</strong></p>
                            <p class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($request['details'])); ?></p>
                        </div>
                        <div class="mt-3">
                            <p><strong>Reason:</strong></p>
                            <p class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                        </div>
                        <?php if (!empty($request['supervisor_remarks'])): ?>
                        <div class="mt-3">
                            <p><strong>Supervisor Remarks:</strong></p>
                            <p class="bg-warning bg-opacity-10 p-3 rounded"><?php echo nl2br(htmlspecialchars($request['supervisor_remarks'])); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($request['admin_remarks'])): ?>
                        <div class="mt-3">
                            <p><strong>Admin Remarks:</strong></p>
                            <p class="bg-info bg-opacity-10 p-3 rounded"><?php echo nl2br(htmlspecialchars($request['admin_remarks'])); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($request['manager_remarks'])): ?>
                        <div class="mt-3">
                            <p><strong>Manager Remarks:</strong></p>
                            <p class="bg-success bg-opacity-10 p-3 rounded"><?php echo nl2br(htmlspecialchars($request['manager_remarks'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Status History Section -->
                        <?php
                        $statusHistory = getStatusHistory($db, $request['request_id']);
                        if (!empty($statusHistory)):
                        ?>
                        <div class="mt-4 border-top pt-3">
                            <h6><i class="fas fa-history me-2"></i>Status Change History</h6>
                            <div class="timeline-container" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($statusHistory as $index => $history): ?>
                                <div class="card mb-2 border-start border-3 <?php echo $index === 0 ? 'border-primary' : 'border-secondary'; ?>">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="card-title mb-1">
                                                    <?php if ($history['previous_status']): ?>
                                                        <span class="badge bg-light text-dark me-2"><?php echo htmlspecialchars($history['previous_status']); ?></span>
                                                        <i class="fas fa-arrow-right text-muted mx-1"></i>
                                                    <?php endif; ?>
                                                    <span class="badge status-badge status-<?php echo strtolower(str_replace(' ', '-', $history['new_status'])); ?>">
                                                        <?php echo htmlspecialchars($history['new_status']); ?>
                                                    </span>
                                                </h6>
                                                <?php if ($history['remarks']): ?>
                                                <p class="card-text mb-1"><small><?php echo nl2br(htmlspecialchars($history['remarks'])); ?></small></p>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    by <?php echo htmlspecialchars($history['changed_by_name'] ?? $history['changed_by']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y', strtotime($history['changed_at'])); ?><br>
                                                    <?php echo date('g:i A', strtotime($history['changed_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Handle Request Modal -->
    <div class="modal fade" id="handleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Handle Request - <span id="handleRequestId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="request_id" id="handleFormRequestId">

                        <div class="mb-3">
                            <label for="current_status" class="form-label">Current Status</label>
                            <input type="text" class="form-control" id="current_status" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="new_status" class="form-label">Update Status To</label>
                            <select class="form-control" name="new_status" id="new_status" required>
                                <option value="">Select Status...</option>
                                <option value="Request Submitted">Request Submitted</option>
                                <option value="Documents Pending">Documents Pending</option>
                                <option value="Processing">Processing</option>
                                <option value="Completed">Completed</option>
                                <option value="Handovered">Handovered</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="handleRemarks" rows="4"></textarea>
                            <div class="form-text">Add any comments or instructions for the employee.</div>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Status Descriptions:</h6>
                            <ul class="mb-0 small">
                                <li><strong>Request Submitted:</strong> Initial status when request is received</li>
                                <li><strong>Documents Pending:</strong> Waiting for additional documents from employee</li>
                                <li><strong>Processing:</strong> Request is being processed by admin team</li>
                                <li><strong>Completed:</strong> Request processing completed</li>
                                <li><strong>Handovered:</strong> Request completed and delivered to employee/guardroom</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter functionality
        document.querySelectorAll('input[name="statusFilter"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const filter = this.id;
                const rows = document.querySelectorAll('.request-row');

                rows.forEach(row => {
                    const status = row.dataset.status;
                    let show = false;

                    switch(filter) {
                        case 'all':
                            show = true;
                            break;
                        case 'submitted':
                            show = status === 'request-submitted';
                            break;
                        case 'processing':
                            show = status === 'processing' || status === 'documents-pending';
                            break;
                        case 'completed':
                            show = status === 'completed';
                            break;
                        case 'handovered':
                            show = status === 'handovered';
                            break;
                    }

                    row.style.display = show ? 'table-row' : 'none';
                });
            });
        });

        // Handle modal function
        function showHandleModal(requestId, currentStatus) {
            document.getElementById('handleRequestId').textContent = requestId;
            document.getElementById('handleFormRequestId').value = requestId;
            document.getElementById('current_status').value = currentStatus;

            // Reset form
            document.getElementById('new_status').value = '';
            document.getElementById('handleRemarks').value = '';

            const modal = new bootstrap.Modal(document.getElementById('handleModal'));
            modal.show();
        }
    </script>
</body>
</html>