<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check permission - only managers, admins, and superadmins can access reports
if (!in_array($user['account_type'], ['manager', 'admin', 'superadmin'])) {
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

// Get date range from URL parameters or default to last 30 days
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get report data based on user role
if (in_array($user['account_type'], ['superadmin'])) {
    // Superadmin sees all requests
    $requests = $db->fetchAll("
        SELECT er.*, e.name as employee_name, e.department, l.location_name as location
        FROM employee_requests er
        LEFT JOIN employees e ON er.epf_number = e.epf_number
        LEFT JOIN locations l ON e.location_id = l.id
        WHERE er.created_at IS NOT NULL AND DATE(er.created_at) BETWEEN ? AND ?
        ORDER BY er.created_at DESC
    ", [$start_date, $end_date]);
} else {
    // Admin/Manager sees location requests
    $requests = $db->fetchAll("
        SELECT er.*, e.name as employee_name, e.department, l.location_name as location
        FROM employee_requests er
        LEFT JOIN employees e ON er.epf_number = e.epf_number
        LEFT JOIN locations l ON e.location_id = l.id
        WHERE er.created_at IS NOT NULL
          AND DATE(er.created_at) BETWEEN ? AND ?
          AND l.location_name = ?
        ORDER BY er.created_at DESC
    ", [$start_date, $end_date, $user['location']]);
}

// Calculate statistics using mapped statuses
$stats = [
    'total_requests' => count($requests),
    'submitted_requests' => count(array_filter($requests, function($r) { return mapStatusToNewSystem($r['status']) === 'Request Submitted'; })),
    'processing_requests' => count(array_filter($requests, function($r) { return in_array(mapStatusToNewSystem($r['status']), ['Documents Pending', 'Processing']); })),
    'completed_requests' => count(array_filter($requests, function($r) { return mapStatusToNewSystem($r['status']) === 'Completed'; })),
    'handovered_requests' => count(array_filter($requests, function($r) { return mapStatusToNewSystem($r['status']) === 'Handovered'; })),
    'urgent_requests' => count(array_filter($requests, function($r) { return $r['urgency'] === 'Urgent'; }))
];

// Group by request type
$by_type = [];
foreach ($requests as $request) {
    $type = $request['request_type'];
    if (!isset($by_type[$type])) {
        $by_type[$type] = 0;
    }
    $by_type[$type]++;
}

// Group by status using mapped statuses
$by_status = [];
foreach ($requests as $request) {
    $status = mapStatusToNewSystem($request['status']);
    if (!isset($by_status[$status])) {
        $by_status[$status] = 0;
    }
    $by_status[$status]++;
}

// Group by department (if available)
$by_department = [];
foreach ($requests as $request) {
    $dept = $request['department'] ?? 'Unknown';
    if (!isset($by_department[$dept])) {
        $by_department[$dept] = 0;
    }
    $by_department[$dept]++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Request Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .stats-card {
            transition: transform 0.2s;
            border-radius: 10px;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .chart-container {
            position: relative;
            height: 400px;
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
                        <h1><i class="fas fa-chart-line me-3"></i>Request Reports</h1>
                        <p>Generate analytics and reports on employee request trends and processing</p>
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
                    <li class="breadcrumb-item active">Request Reports</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-success" onclick="exportToCSV()">
                    <i class="fas fa-download me-2"></i>Export CSV
                </button>
            </div>

            <!-- Date Range Filter -->
            <div class="content-card">
                <div class="card-header-modern">
                    <h5><i class="fas fa-calendar-alt me-2"></i>Date Range Filter</h5>
                </div>
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" id="start_date"
                                   value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" id="end_date"
                                   value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Generate Report
                            </button>
                        </div>
                    </form>

            <!-- Statistics Overview -->
            <div class="row mb-4">
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card border-primary h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                            <h4 class="text-primary"><?php echo $stats['total_requests']; ?></h4>
                            <p class="card-text text-muted mb-0">Total Requests</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card border-warning h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h4 class="text-warning"><?php echo $stats['submitted_requests']; ?></h4>
                            <p class="card-text text-muted mb-0">Submitted</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card border-info h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-cog fa-2x text-info mb-2"></i>
                            <h4 class="text-info"><?php echo $stats['processing_requests']; ?></h4>
                            <p class="card-text text-muted mb-0">Processing</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card border-success h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h4 class="text-success"><?php echo $stats['completed_requests']; ?></h4>
                            <p class="card-text text-muted mb-0">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card border-dark h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-handshake fa-2x text-dark mb-2"></i>
                            <h4 class="text-dark"><?php echo $stats['handovered_requests']; ?></h4>
                            <p class="card-text text-muted mb-0">Handovered</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card border-secondary h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-percentage fa-2x text-secondary mb-2"></i>
                            <h4 class="text-secondary"><?php echo $stats['total_requests'] > 0 ? round((($stats['completed_requests'] + $stats['handovered_requests']) / $stats['total_requests']) * 100) : 0; ?>%</h4>
                            <p class="card-text text-muted mb-0">Completion Rate</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <!-- Request Types Chart -->
                <div class="col-md-6 mb-4">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5>
                                <i class="fas fa-chart-pie me-2"></i>Requests by Type
                            </h5>
                        </div>
                        <div class="chart-container">
                            <canvas id="typeChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Status Chart -->
                <div class="col-md-6 mb-4">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5>
                                <i class="fas fa-chart-donut me-2"></i>Requests by Status
                            </h5>
                        </div>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department Breakdown -->
            <?php if (!empty($by_department)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5>
                                <i class="fas fa-building me-2"></i>Requests by Department
                            </h5>
                        </div>
                        <div class="chart-container">
                            <canvas id="departmentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Requests Table -->
            <div class="content-card">
                <div class="card-header-modern">
                    <h5>
                        <i class="fas fa-table me-2"></i>Recent Requests
                        <small class="text-muted">(<?php echo $start_date; ?> to <?php echo $end_date; ?>)</small>
                    </h5>
                </div>
                    <?php if (empty($requests)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Data Available</h5>
                            <p class="text-muted">No requests found for the selected date range.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="requestsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($requests, 0, 50) as $request):
                                        $mappedStatus = mapStatusToNewSystem($request['status']);
                                    ?>
                                        <tr>
                                            <td><code><?php echo $request['request_id']; ?></code></td>
                                            <td><?php echo htmlspecialchars($request['employee_name'] ?? $request['epf_number']); ?></td>
                                            <td><?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($request['request_type']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $mappedStatus === 'Completed' ? 'success' : ($mappedStatus === 'Request Submitted' ? 'info' : ($mappedStatus === 'Handovered' ? 'dark' : 'warning')); ?>">
                                                    <?php echo $mappedStatus; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $request['urgency'] === 'Urgent' ? 'danger' : ($request['urgency'] === 'High' ? 'warning text-dark' : 'secondary'); ?>">
                                                    <?php echo $request['urgency']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($request['created_at'] ?? 'now')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (count($requests) > 50): ?>
                                <p class="text-muted text-center mt-3">
                                    Showing first 50 of <?php echo count($requests); ?> requests.
                                    <button class="btn btn-link p-0" onclick="exportToCSV()">Export all to CSV</button>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Request Types Chart
        <?php if (!empty($by_type)): ?>
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($by_type)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($by_type)); ?>,
                    backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>

        // Status Chart
        <?php if (!empty($by_status)): ?>
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($by_status)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($by_status)); ?>,
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>

        // Department Chart
        <?php if (!empty($by_department)): ?>
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($by_department)); ?>,
                datasets: [{
                    label: 'Requests',
                    data: <?php echo json_encode(array_values($by_department)); ?>,
                    backgroundColor: '#007bff',
                    borderColor: '#0056b3',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Export to CSV function
        function exportToCSV() {
            const table = document.getElementById('requestsTable');
            if (!table) return;

            let csv = 'Request ID,Employee,Department,Type,Status,Priority,Date\n';

            <?php foreach ($requests as $request): ?>
            csv += '"<?php echo $request['request_id']; ?>","<?php echo htmlspecialchars($request['employee_name'] ?? $request['epf_number']); ?>","<?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?>","<?php echo htmlspecialchars($request['request_type']); ?>","<?php echo mapStatusToNewSystem($request['status']); ?>","<?php echo $request['urgency']; ?>","<?php echo date('M j, Y', strtotime($request['created_at'] ?? 'now')); ?>"\n';
            <?php endforeach; ?>

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'employee_requests_<?php echo $start_date; ?>_to_<?php echo $end_date; ?>.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>
</body>
</html>