<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($database, 'offboarding.offboarding_list')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get all resignations with their current status
$resignations_query = "
    SELECT r.*,
           ei.id as exit_interview_completed,
           cf.id as clearance_submitted,
           cf.status as clearance_status,
           cf.final_verification,
           DATEDIFF(r.last_working_day, CURDATE()) as days_remaining,
           CASE
               WHEN r.status = 'pending' THEN 1
               WHEN r.status = 'rejected' THEN 2
               WHEN r.status = 'approved' AND ei.id IS NULL THEN 3
               WHEN r.status = 'approved' AND ei.id IS NOT NULL AND cf.id IS NULL THEN 4
               WHEN cf.status = 'pending' THEN 5
               WHEN cf.status = 'rejected' THEN 6
               WHEN r.status = 'completed' THEN 7
               ELSE 0
           END as process_stage
    FROM resignations r
    LEFT JOIN exit_interviews ei ON r.id = ei.resignation_id
    LEFT JOIN clearance_forms cf ON r.id = cf.resignation_id
    ORDER BY r.created_at DESC
";

$resignations = $database->fetchAll($resignations_query);

// Define process stages
$process_stages = [
    1 => ['label' => 'Pending Manager Approval', 'class' => 'warning', 'icon' => 'fa-clock'],
    2 => ['label' => 'Rejected by Manager', 'class' => 'danger', 'icon' => 'fa-times-circle'],
    3 => ['label' => 'Awaiting Exit Interview', 'class' => 'info', 'icon' => 'fa-comments'],
    4 => ['label' => 'Awaiting Clearance Form', 'class' => 'primary', 'icon' => 'fa-clipboard-list'],
    5 => ['label' => 'Pending Final Verification', 'class' => 'warning', 'icon' => 'fa-hourglass-half'],
    6 => ['label' => 'Clearance Rejected', 'class' => 'danger', 'icon' => 'fa-exclamation-triangle'],
    7 => ['label' => 'Process Completed', 'class' => 'success', 'icon' => 'fa-check-circle']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Offboarding List</title>
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

        .status-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }

        .progress-indicator {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            display: inline-flex;
            align-items: center;
        }

        .progress-indicator i {
            margin-right: 0.5rem;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }

        .employee-info {
            display: flex;
            flex-direction: column;
        }

        .employee-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .employee-details {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .days-indicator {
            font-weight: 600;
        }

        .days-critical {
            color: #dc3545;
        }

        .days-warning {
            color: #fd7e14;
        }

        .days-normal {
            color: #28a745;
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
                        <h1><i class="fas fa-list-alt me-3"></i>Offboarding List</h1>
                        <p>View and manage all employee offboarding cases and status tracking</p>
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
                    <li class="breadcrumb-item active">Offboarding List</li>
                </ol>
            </nav>

            <!-- Progress Tracking Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>All Offboarding Cases</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($resignations)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="offboardingTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Resignation Date</th>
                                        <th>Last Working Day</th>
                                        <th>Days Remaining</th>
                                        <th>Current Stage</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resignations as $resignation): ?>
                                        <?php
                                        $stage = $process_stages[$resignation['process_stage']] ?? $process_stages[0];
                                        $days_remaining = $resignation['days_remaining'];

                                        $days_class = 'days-normal';
                                        if ($days_remaining <= 3) {
                                            $days_class = 'days-critical';
                                        } elseif ($days_remaining <= 7) {
                                            $days_class = 'days-warning';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="employee-info">
                                                    <span class="employee-name"><?php echo htmlspecialchars($resignation['employee_name']); ?></span>
                                                    <span class="employee-details"><?php echo htmlspecialchars($resignation['employee_epf']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($resignation['employee_department']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($resignation['employee_designation']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($resignation['date_of_resignation'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($resignation['last_working_day'])); ?></td>
                                            <td>
                                                <?php if ($days_remaining >= 0): ?>
                                                    <span class="days-indicator <?php echo $days_class; ?>">
                                                        <?php echo $days_remaining; ?> days
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Overdue</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="progress-indicator bg-<?php echo $stage['class']; ?> text-white">
                                                    <i class="fas <?php echo $stage['icon']; ?>"></i>
                                                    <?php echo $stage['label']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = match($resignation['status']) {
                                                    'pending' => 'bg-warning text-dark',
                                                    'approved' => 'bg-info',
                                                    'rejected' => 'bg-danger',
                                                    'completed' => 'bg-success',
                                                    default => 'bg-secondary'
                                                };
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?> status-badge">
                                                    <?php echo ucfirst($resignation['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No Offboarding Cases</h5>
                            <p class="text-muted">No resignation submissions found in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Process Flow Legend -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Process Flow</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($process_stages as $stage): ?>
                            <div class="col-md-6 col-lg-3 mb-2">
                                <span class="progress-indicator bg-<?php echo $stage['class']; ?> text-white">
                                    <i class="fas <?php echo $stage['icon']; ?>"></i>
                                    <?php echo $stage['label']; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
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
        // Initialize DataTables
        $(document).ready(function() {
            $('#offboardingTable').DataTable({
                pageLength: 25,
                responsive: true,
                order: [[2, 'desc']], // Order by resignation date descending
                columnDefs: [
                    { orderable: false, targets: [5, 6] } // Disable sorting on stage and status columns
                ]
            });
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>