<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/performance_auth.php';

// Check if user has view marks permission
requirePerformancePermission('performance.view_marks');

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    try {
        $db = new Database();
        $export_year = $_GET['year'] ?? date('Y');

        // Get all executive appraisal data for the selected year
        $sql = "SELECT * FROM executive_appraisals WHERE appraisal_year = ? ORDER BY appraisee_name ASC";
        $results = $db->fetchAll($sql, [$export_year]);

        // Set headers for Excel download
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="executive_appraisals_' . $export_year . '_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: max-age=0');

        echo "<table border='1'>";
        echo "<tr><td colspan='81' style='font-size: 14px; font-weight: bold; text-align: center;'>Executive Appraisals Data - Year $export_year</td></tr>";
        echo "<tr><td colspan='81' style='text-align: center;'>Generated on: " . date('Y-m-d H:i:s') . "</td></tr>";
        echo "<tr><td colspan='81'></td></tr>";

        // Header row with all columns
        echo "<tr style='background-color: #f0f0f0; font-weight: bold;'>";
        if (!empty($results)) {
            foreach (array_keys($results[0]) as $column) {
                echo "<th>" . htmlspecialchars(ucwords(str_replace('_', ' ', $column))) . "</th>";
            }
        }
        echo "</tr>";

        // Data rows
        foreach ($results as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                $value = $cell ?? '';
                // Handle special formatting for dates and numbers
                if (is_numeric($value) && strpos($value, '.') === false && strlen($value) > 4) {
                    $value = "'" . $value; // Prefix with ' to prevent Excel from converting EPF numbers
                }
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }

        echo "</table>";
        exit();

    } catch (Exception $e) {
        die("Error exporting data: " . $e->getMessage());
    }
}

$user = getCurrentUser();
$db = new Database();

// Get selected year (default to current year)
$selected_year = $_GET['year'] ?? date('Y');

// Get search and filter parameters
$search_term = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build SQL query with filters
$where_conditions = ["ea.appraisal_year = ?"];
$params = [$selected_year];

if ($search_term) {
    $where_conditions[] = "(ea.appraisee_name LIKE ? OR ea.appraisee_epf LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

if ($department_filter) {
    $where_conditions[] = "ea.department = ?";
    $params[] = $department_filter;
}

if ($status_filter) {
    $where_conditions[] = "ea.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get basic executive appraisal tracking data
$executive_appraisals = $db->fetchAll("
    SELECT
        ea.id,
        ea.appraisal_year,
        ea.appraisee_epf,
        ea.appraisee_name,
        ea.designation,
        ea.department,
        ea.appraiser_epf,
        ea.appraiser_name,
        ea.status,
        ea.evaluation_date,
        ea.created_at,
        ea.updated_at,
        ea.submitted_at
    FROM executive_appraisals ea
    WHERE $where_clause
    ORDER BY ea.appraisee_name ASC
", $params);

// Get available years
$available_years = $db->fetchAll("
    SELECT DISTINCT appraisal_year
    FROM executive_appraisals
    ORDER BY appraisal_year DESC
");

// Get departments for filter
$departments = $db->fetchAll("
    SELECT DISTINCT department
    FROM executive_appraisals
    WHERE department IS NOT NULL AND department != ''
    ORDER BY department
");

// Calculate statistics
$total_appraisals = count($executive_appraisals);
$completed_appraisals = count(array_filter($executive_appraisals, fn($a) => $a['status'] === 'submitted' || $a['status'] === 'approved'));
$draft_appraisals = count(array_filter($executive_appraisals, fn($a) => $a['status'] === 'draft'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Executive Appraisal Tracking Data</title>
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

        .stats-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .data-table {
            font-size: 0.85em;
        }

        .score-cell {
            font-weight: bold;
            text-align: center;
        }

        .status-badge {
            font-size: 0.75em;
            padding: 0.25em 0.6em;
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
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
        .competency-score {
            background: #e3f2fd;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .core-values-score {
            background: #f3e5f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .attitude-score {
            background: #fff3e0;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
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
                        <h1><i class="fas fa-chart-bar me-3"></i>Executive Appraisal Tracking</h1>
                        <p>Track and analyze executive performance appraisal data and metrics</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0"><i class="fas fa-list text-success me-2"></i>Executive Appraisal Tracking</h1>
                        <p class="text-muted mb-0">Track completion status of executive performance appraisals</p>
                    </div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Performance</a></li>
                            <li class="breadcrumb-item active">Executive Marks Data</li>
                        </ol>
                    </nav>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?= $total_appraisals ?></h3>
                                <small>Total Executive Appraisals</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?= $completed_appraisals ?></h3>
                                <small>Completed</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?= $draft_appraisals ?></h3>
                                <small>In Draft</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?= $total_appraisals > 0 ? round(($completed_appraisals / $total_appraisals) * 100) : 0 ?>%</h3>
                                <small>Completion Rate</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Year</label>
                            <select name="year" class="form-select">
                                <?php foreach ($available_years as $year): ?>
                                    <option value="<?= $year['appraisal_year'] ?>"
                                            <?= $year['appraisal_year'] == $selected_year ? 'selected' : '' ?>>
                                        <?= $year['appraisal_year'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search Executive</label>
                            <input type="text" name="search" class="form-control"
                                   placeholder="Name or EPF..." value="<?= htmlspecialchars($search_term) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['department']) ?>"
                                            <?= $dept['department'] === $department_filter ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="submitted" <?= $status_filter === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                                <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <a href="executive_marks_data.php?year=<?= $selected_year ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Data Table -->
                <div class="card">
                    <div class="card-header bg-light">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-table text-success me-2"></i>
                                    Executive Appraisal Status (Year <?= $selected_year ?>)
                                </h5>
                            </div>
                            <div class="col-auto">
                                <a href="?export=excel&year=<?= $selected_year ?>" class="btn btn-outline-success btn-sm me-2">
                                    <i class="fas fa-file-excel me-1"></i>Export All Data to Excel
                                </a>
                                <button class="btn btn-outline-info btn-sm" onclick="showScoringGuide()">
                                    <i class="fas fa-question-circle me-1"></i>Scoring Guide
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($executive_appraisals)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chart-bar text-muted" style="font-size: 3em;"></i>
                                <h5 class="mt-3 text-muted">No Executive Appraisal Data Found</h5>
                                <p class="text-muted">No executive appraisals found for the selected criteria.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped data-table mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>EPF</th>
                                            <th>Employee Name</th>
                                            <th>Designation</th>
                                            <th>Department</th>
                                            <th>Appraiser</th>
                                            <th>Status</th>
                                            <th>Evaluation Date</th>
                                            <th>Submitted Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($executive_appraisals as $appraisal): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($appraisal['appraisee_epf']) ?></strong>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($appraisal['appraisee_name']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($appraisal['designation']) ?></td>
                                                <td><?= htmlspecialchars($appraisal['department']) ?></td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($appraisal['appraiser_name']) ?></strong>
                                                        <br>
                                                        <small class="text-muted">EPF: <?= htmlspecialchars($appraisal['appraiser_epf']) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge status-badge
                                                        <?= $appraisal['status'] === 'submitted' ? 'bg-success' :
                                                           ($appraisal['status'] === 'draft' ? 'bg-warning' : 'bg-info') ?>">
                                                        <?= ucfirst($appraisal['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($appraisal['evaluation_date']): ?>
                                                        <?= date('M d, Y', strtotime($appraisal['evaluation_date'])) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($appraisal['submitted_at']): ?>
                                                        <?= date('M d, Y', strtotime($appraisal['submitted_at'])) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not submitted</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-outline-success btn-sm"
                                                            onclick="viewDetails(<?= $appraisal['id'] ?>)"
                                                            title="View Details">
                                                        <i class="fas fa-eye me-1"></i>View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Back to Performance Dashboard -->
                <div class="d-flex justify-content-between mt-4">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Performance Dashboard
                    </a>
                    <div>
                        <a href="executive_list.php" class="btn btn-outline-success me-2">
                            <i class="fas fa-user-tie me-1"></i>Executive List (13.2)
                        </a>
                        <a href="executive_appraisal.php" class="btn btn-outline-primary">
                            <i class="fas fa-clipboard-check me-1"></i>New Appraisal (13.8)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scoring Guide Modal -->
    <div class="modal fade" id="scoringGuideModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle text-info me-2"></i>Executive Appraisal Scoring Guide</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-success">Scoring Breakdown</h6>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Competency Evaluation</span>
                                    <span class="competency-score">40 points max</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Core Values</span>
                                    <span class="core-values-score">15 points max</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Attitude & Behavior</span>
                                    <span class="attitude-score">40 points max</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between fw-bold">
                                    <span>Total Possible</span>
                                    <span>95 points</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success">Performance Grades</h6>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-success fw-bold">Outstanding</span>
                                    <span>90-100%</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-primary fw-bold">Exceeds Expectations</span>
                                    <span>80-89%</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-info">Meets Expectations</span>
                                    <span>70-79%</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-warning">Below Expectations</span>
                                    <span>60-69%</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-danger">Needs Improvement</span>
                                    <span>&lt; 60%</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDetails(appraisalId) {
            window.location.href = `executive_appraisal_detail.php?id=${appraisalId}`;
        }

        function printAppraisal(appraisalId) {
            window.open(`executive_appraisal_print.php?id=${appraisalId}`, '_blank');
        }

        function viewAchievements(appraisalId) {
            // Show achievements in modal or new window
            window.open(`executive_achievements.php?id=${appraisalId}`, '_blank', 'width=600,height=400');
        }

        function viewDevelopment(appraisalId) {
            // Show development areas in modal or new window
            window.open(`executive_development.php?id=${appraisalId}`, '_blank', 'width=600,height=400');
        }

        function showScoringGuide() {
            new bootstrap.Modal(document.getElementById('scoringGuideModal')).show();
        }

        function exportToExcel() {
            const table = document.querySelector('.data-table');
            let csv = [];

            // Get headers
            const headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                headers.push(th.innerText.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim());
            });
            csv.push(headers.join(','));

            // Get data rows
            table.querySelectorAll('tbody tr').forEach(row => {
                const rowData = [];
                row.querySelectorAll('td').forEach(td => {
                    let cellText = td.innerText.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
                    if (cellText.includes(',')) {
                        cellText = `"${cellText}"`;
                    }
                    rowData.push(cellText);
                });
                csv.push(rowData.join(','));
            });

            // Download CSV
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', `executive_appraisal_marks_<?= $selected_year ?>.csv`);
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
    </div>
</body>
</html>