<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to KPI Tracker
if (!isSuperAdmin() && !hasModulePermission($db, 'goals.kpi_tracker')) {
    header('Location: ../../dashboard.php');
    exit;
}

$message = '';
$error = '';

// Handle delete action (superadmin only)
if (isset($_POST['action']) && $_POST['action'] == 'delete' && isSuperAdmin()) {
    try {
        $record_id = $_POST['record_id'];

        // Delete goal details first (foreign key constraint)
        $db->query("DELETE FROM goal_details WHERE record_id = ?", [$record_id]);

        // Delete the main record
        $db->query("DELETE FROM executive_goal_records WHERE id = ?", [$record_id]);

        $message = "Goal setting record deleted successfully!";
    } catch (Exception $e) {
        $error = "Error deleting record: " . $e->getMessage();
    }
}

// Get available years (only show years that have been created in form setup)
$years = $db->fetchAll("
    SELECT year, status FROM goal_setting_periods
    ORDER BY year DESC
");

$selected_year = $_GET['year'] ?? '';
$tracking_data = [];

if ($selected_year) {
    // Get tracking data for the selected year
    $period = $db->fetch("SELECT id FROM goal_setting_periods WHERE year = ?", [$selected_year]);

    if ($period) {
        $tracking_data = $db->fetchAll("
            SELECT
                r.id as record_id,
                r.employee_epf,
                e.name as employee_name,
                e.department,
                m.name as manager_name,
                r.form_saved,
                r.mid_year_progress,
                r.final_evaluation,
                r.final_grade,
                r.created_date,
                r.employee_agreement_date,
                r.manager_agreement_date,
                r.mid_year_status,
                r.final_performance_grade
            FROM executive_goal_records r
            JOIN employees e ON r.employee_epf = e.epf_number
            JOIN employees m ON r.manager_epf = m.epf_number
            WHERE r.period_id = ?
            ORDER BY e.department, e.name
        ", [$period['id']]);
    }
}

function getGradeBadge($grade) {
    if (!$grade) return '<span class="badge bg-secondary">Not Set</span>';

    switch (strtoupper($grade)) {
        case 'A':
            return '<span class="badge bg-success">A - Excellent</span>';
        case 'B':
            return '<span class="badge bg-primary">B - Good</span>';
        case 'C':
            return '<span class="badge bg-warning text-dark">C - Average</span>';
        case 'D':
            return '<span class="badge bg-danger">D - Poor</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars($grade) . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - KPI Completion Tracker</title>
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

        .status-yes { color: #28a745; font-weight: bold; }
        .status-no { color: #dc3545; font-weight: bold; }

        .summary-cards .card {
            transition: transform 0.3s ease;
        }

        .summary-cards .card:hover {
            transform: translateY(-5px);
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
                        <h1><i class="fas fa-tasks me-3"></i>KPI Completion Tracker</h1>
                        <p>Automated tracking of goal setting completion status and progress</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Year Selection -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Select Year</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET">
                                <div class="mb-3">
                                    <select name="year" class="form-select" onchange="this.form.submit()">
                                        <option value="">Choose Year...</option>
                                        <?php foreach ($years as $year_data): ?>
                                            <option value="<?= $year_data['year'] ?>" <?= $selected_year == $year_data['year'] ? 'selected' : '' ?>>
                                                <?= $year_data['year'] ?> (<?= ucfirst($year_data['status']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Only shows years created in Goal Setting Form Setup</div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($selected_year): ?>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Summary for <?= $selected_year ?></h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $total_records = count($tracking_data);
                            $forms_saved = array_filter($tracking_data, function($record) { return $record['form_saved'] == 'Y'; });
                            $mid_year_completed = array_filter($tracking_data, function($record) { return $record['mid_year_progress'] == 'Y'; });
                            $final_evaluations = array_filter($tracking_data, function($record) { return $record['final_evaluation'] == 'Y'; });
                            ?>
                            <div class="row text-center">
                                <div class="col-3">
                                    <h4 class="text-primary"><?= $total_records ?></h4>
                                    <small class="text-muted">Total Records</small>
                                </div>
                                <div class="col-3">
                                    <h4 class="text-success"><?= count($forms_saved) ?></h4>
                                    <small class="text-muted">Forms Saved</small>
                                </div>
                                <div class="col-3">
                                    <h4 class="text-warning"><?= count($mid_year_completed) ?></h4>
                                    <small class="text-muted">Mid-Year Done</small>
                                </div>
                                <div class="col-3">
                                    <h4 class="text-info"><?= count($final_evaluations) ?></h4>
                                    <small class="text-muted">Final Done</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- KPI Tracking Data -->
            <?php if ($selected_year && !empty($tracking_data)): ?>
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>KPI Completion Status - <?= $selected_year ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="kpiTable" class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>EPF</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Manager Name</th>
                                    <th>Form Saved</th>
                                    <th>Mid-Year Progress</th>
                                    <th>Final Evaluation</th>
                                    <th>Final Grade</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tracking_data as $record): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($record['employee_epf']) ?></strong></td>
                                    <td><?= htmlspecialchars($record['employee_name']) ?></td>
                                    <td><?= htmlspecialchars($record['department']) ?></td>
                                    <td><?= htmlspecialchars($record['manager_name']) ?></td>
                                    <td>
                                        <span class="<?= $record['form_saved'] == 'Y' ? 'status-yes' : 'status-no' ?>">
                                            <?= $record['form_saved'] ?>
                                        </span>
                                        <?php if ($record['form_saved'] == 'Y' && $record['created_date']): ?>
                                            <br><small class="text-muted"><?= date('M d, Y', strtotime($record['created_date'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="<?= $record['mid_year_progress'] == 'Y' ? 'status-yes' : 'status-no' ?>">
                                            <?= $record['mid_year_progress'] ?>
                                        </span>
                                        <?php if ($record['mid_year_progress'] == 'Y' && $record['mid_year_status']): ?>
                                            <br><small class="badge bg-info"><?= htmlspecialchars($record['mid_year_status']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="<?= $record['final_evaluation'] == 'Y' ? 'status-yes' : 'status-no' ?>">
                                            <?= $record['final_evaluation'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= getGradeBadge($record['final_performance_grade']) ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#viewDetailsModal"
                                                    data-record-id="<?= $record['record_id'] ?>"
                                                    data-employee-name="<?= htmlspecialchars($record['employee_name']) ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (isSuperAdmin()): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteModal"
                                                    data-record-id="<?= $record['record_id'] ?>"
                                                    data-employee-name="<?= htmlspecialchars($record['employee_name']) ?>">
                                                <i class="fas fa-trash"></i>
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
            <?php elseif ($selected_year && empty($tracking_data)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No goal setting records found for year <?= $selected_year ?>. Records are created when managers save goal setting forms.
            </div>
            <?php endif; ?>

            <!-- Navigation -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Goal Setting
                        </a>
                        <div>
                            <a href="mid_year_review.php" class="btn btn-warning me-2">
                                <i class="fas fa-chart-bar me-2"></i>Mid-Year Review
                            </a>
                            <a href="final_evaluation.php" class="btn btn-success">
                                <i class="fas fa-award me-2"></i>Final Evaluation
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>Goal Setting Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="detailsContent">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <?php if (isSuperAdmin()): ?>
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-trash me-2"></i>Delete Goal Setting Record
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the goal setting record for <strong id="deleteEmployeeName"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone and will delete all associated goal details.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="record_id" id="deleteRecordId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Record
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#kpiTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[2, 'asc'], [1, 'asc']], // Order by department, then name
                columnDefs: [
                    { targets: [4, 5, 6], className: 'text-center' }, // Center align status columns
                    { targets: [8], orderable: false } // Disable sorting for actions column
                ]
            });

            // View Details Modal
            $('#viewDetailsModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var recordId = button.data('record-id');
                var employeeName = button.data('employee-name');

                var modal = $(this);
                modal.find('.modal-title').html('<i class="fas fa-eye me-2"></i>Goal Setting Details - ' + employeeName);

                // Load details via AJAX
                $('#detailsContent').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');

                // Load goal details via AJAX
                $.ajax({
                    url: 'ajax/get_goal_details.php',
                    type: 'POST',
                    data: { record_id: recordId },
                    success: function(response) {
                        $('#detailsContent').html(response);
                    },
                    error: function() {
                        $('#detailsContent').html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error loading goal details. Please try again.</div>');
                    }
                });
            });

            // Delete Modal
            $('#deleteModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var recordId = button.data('record-id');
                var employeeName = button.data('employee-name');

                var modal = $(this);
                modal.find('#deleteEmployeeName').text(employeeName);
                modal.find('#deleteRecordId').val(recordId);
            });
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>