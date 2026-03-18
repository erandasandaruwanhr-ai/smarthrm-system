<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check permission
if (!hasModulePermission($db, 'requests.my_requests')) {
    header('Location: ../../dashboard.php');
    exit();
}

// Status mapping function for backward compatibility
function mapStatusToNewSystem($oldStatus) {
    $statusMap = [
        'Pending Supervisor' => 'Request Submitted',
        'Pending Admin' => 'Documents Pending',
        'Pending Manager' => 'Processing',
        'Approved' => 'Completed',
        'Rejected' => 'Request Submitted',
        'On Hold' => 'Documents Pending'
    ];
    return isset($statusMap[$oldStatus]) ? $statusMap[$oldStatus] : $oldStatus;
}

// Get user's requests
$requests = $db->fetchAll("
    SELECT * FROM employee_requests
    WHERE epf_number = ?
    ORDER BY id DESC
", [$user['epf_number']]);

// Handle request cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $request_id = $_POST['request_id'];
    $request = $db->fetch("SELECT * FROM employee_requests WHERE request_id = ? AND epf_number = ?", [$request_id, $user['epf_number']]);

    if ($request && in_array(mapStatusToNewSystem($request['status']), ['Request Submitted', 'Documents Pending'])) {
        $db->update('employee_requests', ['status' => 'Cancelled'], 'request_id = ?', [$request_id]);
        $success_message = "Request $request_id has been cancelled successfully.";

        // Refresh requests
        $requests = $db->fetchAll("
            SELECT * FROM employee_requests
            WHERE epf_number = ?
            ORDER BY id DESC
        ", [$user['epf_number']]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - My Requests</title>
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

        .request-card {
            transition: transform 0.2s;
            border-left: 4px solid #007bff;
        }
        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,123,255,0.15);
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
                        <h1><i class="fas fa-inbox me-3"></i>My Requests</h1>
                        <p>View and track your submitted employee service requests</p>
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
                    <li class="breadcrumb-item active">My Requests</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-end mb-3">
                <a href="submit_request.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>New Request
                </a>
            </div>

            <!-- Success Message -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-primary">
                                <?php echo count($requests); ?>
                            </h5>
                            <p class="card-text text-muted">Total Requests</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-warning">
                                <?php echo count(array_filter($requests, function($r) { return in_array(mapStatusToNewSystem($r['status']), ['Request Submitted', 'Documents Pending', 'Processing']); })); ?>
                            </h5>
                            <p class="card-text text-muted">In Progress</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-success">
                                <?php echo count(array_filter($requests, function($r) { return mapStatusToNewSystem($r['status']) === 'Completed'; })); ?>
                            </h5>
                            <p class="card-text text-muted">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-dark">
                                <?php echo count(array_filter($requests, function($r) { return mapStatusToNewSystem($r['status']) === 'Handovered'; })); ?>
                            </h5>
                            <p class="card-text text-muted">Handovered</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Requests List -->
            <div class="content-card">
                <div class="card-header-modern">
                    <h5>
                        <i class="fas fa-list me-2"></i>Request History
                    </h5>
                </div>
                    <?php if (empty($requests)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Requests Found</h5>
                            <p class="text-muted">You haven't submitted any requests yet.</p>
                            <a href="submit_request.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Submit Your First Request
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($requests as $request):
                                $mappedStatus = mapStatusToNewSystem($request['status']);
                            ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card request-card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <small class="text-muted"><?php echo $request['request_id']; ?></small>
                                            <span class="badge status-badge status-<?php echo strtolower(str_replace(' ', '-', $mappedStatus)); ?>">
                                                <?php echo $mappedStatus; ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($request['request_type']); ?></h6>
                                            <p class="card-text">
                                                <strong>Subject:</strong> <?php echo htmlspecialchars($request['subject']); ?>
                                            </p>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($request['created_at'] ?? 'now')); ?>
                                                </small>
                                            </p>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <i class="fas fa-exclamation-circle me-1"></i>
                                                    Priority: <?php echo $request['urgency']; ?>
                                                </small>
                                            </p>
                                        </div>
                                        <div class="card-footer">
                                            <div class="btn-group w-100" role="group">
                                                <button type="button" class="btn btn-outline-primary btn-sm"
                                                        data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $request['id']; ?>">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </button>
                                                <?php if (in_array($mappedStatus, ['Request Submitted', 'Documents Pending'])): ?>
                                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                                            onclick="confirmCancel('<?php echo $request['request_id']; ?>')">
                                                        <i class="fas fa-times me-1"></i>Cancel
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- View Modal -->
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
                                                        <p><strong>Type:</strong> <?php echo htmlspecialchars($request['request_type']); ?></p>
                                                        <p><strong>Subject:</strong> <?php echo htmlspecialchars($request['subject']); ?></p>
                                                        <p><strong>Urgency:</strong> <?php echo $request['urgency']; ?></p>
                                                        <p><strong>Status:</strong>
                                                            <span class="badge status-badge status-<?php echo strtolower(str_replace(' ', '-', $mappedStatus)); ?>">
                                                                <?php echo $mappedStatus; ?>
                                                            </span>
                                                        </p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($request['created_at'] ?? 'now')); ?></p>
                                                        <p><strong>Current Stage:</strong> <?php echo $request['current_stage']; ?></p>
                                                    </div>
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
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cancel Confirmation Form -->
    <form id="cancelForm" method="post" style="display: none;">
        <input type="hidden" name="cancel_request" value="1">
        <input type="hidden" name="request_id" id="cancelRequestId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmCancel(requestId) {
            if (confirm('Are you sure you want to cancel this request? This action cannot be undone.')) {
                document.getElementById('cancelRequestId').value = requestId;
                document.getElementById('cancelForm').submit();
            }
        }
    </script>
</body>
</html>