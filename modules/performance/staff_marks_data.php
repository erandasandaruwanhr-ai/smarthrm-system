<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/performance_auth.php';

// Check if user has view marks permission
requirePerformancePermission('performance.view_marks');

$user = getCurrentUser();
$db = new Database();

// Get selected year (default to current year)
$selected_year = $_GET['year'] ?? date('Y');

// Get search and filter parameters
$search_term = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build SQL query with filters
$where_conditions = ["sa.appraisal_year = ?"];
$params = [$selected_year];

if ($search_term) {
    $where_conditions[] = "(sa.appraisee_name LIKE ? OR sa.appraisee_epf LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

if ($department_filter) {
    $where_conditions[] = "sa.department = ?";
    $params[] = $department_filter;
}

if ($status_filter) {
    $where_conditions[] = "sa.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get staff appraisal data with calculated scores
$staff_appraisals = $db->fetchAll("
    SELECT
        sa.*,
        -- Calculate category totals
        (COALESCE(sa.job_knowledge_q1, 0) + COALESCE(sa.job_knowledge_q2, 0) + COALESCE(sa.job_knowledge_q3, 0)) as job_knowledge_total,
        (COALESCE(sa.creativity_q1, 0)) as creativity_total,
        (COALESCE(sa.quality_awareness_q1, 0) + COALESCE(sa.quality_awareness_q2, 0)) as quality_total,
        (COALESCE(sa.goal_achievement_q1, 0)) as goal_total,
        (COALESCE(sa.teamwork_q1, 0) + COALESCE(sa.teamwork_q2, 0) + COALESCE(sa.teamwork_q3, 0) + COALESCE(sa.teamwork_q4, 0)) as teamwork_total,
        (COALESCE(sa.cultural_awareness_q1, 0) + COALESCE(sa.cultural_awareness_q2, 0) + COALESCE(sa.cultural_awareness_q3, 0) +
         COALESCE(sa.cultural_awareness_q4, 0) + COALESCE(sa.cultural_awareness_q5, 0) + COALESCE(sa.cultural_awareness_q6, 0)) as cultural_total,
        (COALESCE(sa.safety_consciousness_q1, 0) + COALESCE(sa.safety_consciousness_q2, 0) + COALESCE(sa.safety_consciousness_q3, 0) + COALESCE(sa.safety_consciousness_q4, 0)) as safety_total,
        (COALESCE(sa.discipline_q1, 0) + COALESCE(sa.discipline_q2, 0) + COALESCE(sa.discipline_q3, 0) + COALESCE(sa.discipline_q4, 0)) as discipline_total,

        -- Calculate overall total
        (COALESCE(sa.job_knowledge_q1, 0) + COALESCE(sa.job_knowledge_q2, 0) + COALESCE(sa.job_knowledge_q3, 0) +
         COALESCE(sa.creativity_q1, 0) +
         COALESCE(sa.quality_awareness_q1, 0) + COALESCE(sa.quality_awareness_q2, 0) +
         COALESCE(sa.goal_achievement_q1, 0) +
         COALESCE(sa.teamwork_q1, 0) + COALESCE(sa.teamwork_q2, 0) + COALESCE(sa.teamwork_q3, 0) + COALESCE(sa.teamwork_q4, 0) +
         COALESCE(sa.cultural_awareness_q1, 0) + COALESCE(sa.cultural_awareness_q2, 0) + COALESCE(sa.cultural_awareness_q3, 0) +
         COALESCE(sa.cultural_awareness_q4, 0) + COALESCE(sa.cultural_awareness_q5, 0) + COALESCE(sa.cultural_awareness_q6, 0) +
         COALESCE(sa.safety_consciousness_q1, 0) + COALESCE(sa.safety_consciousness_q2, 0) + COALESCE(sa.safety_consciousness_q3, 0) + COALESCE(sa.safety_consciousness_q4, 0) +
         COALESCE(sa.discipline_q1, 0) + COALESCE(sa.discipline_q2, 0) + COALESCE(sa.discipline_q3, 0) + COALESCE(sa.discipline_q4, 0)) as overall_total
    FROM staff_appraisals sa
    WHERE $where_clause
    ORDER BY sa.appraisee_name ASC
", $params);

// Get available years
$available_years = $db->fetchAll("
    SELECT DISTINCT appraisal_year
    FROM staff_appraisals
    ORDER BY appraisal_year DESC
");

// Get departments for filter
$departments = $db->fetchAll("
    SELECT DISTINCT department
    FROM staff_appraisals
    WHERE department IS NOT NULL AND department != ''
    ORDER BY department
");

// Calculate statistics
$total_appraisals = count($staff_appraisals);
$completed_appraisals = count(array_filter($staff_appraisals, fn($a) => $a['status'] === 'submitted' || $a['status'] === 'approved'));
$draft_appraisals = count(array_filter($staff_appraisals, fn($a) => $a['status'] === 'draft'));

function getPerformanceGrade($score, $maxScore) {
    if ($maxScore == 0) return 'N/A';
    $percentage = ($score / $maxScore) * 100;
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B+';
    if ($percentage >= 60) return 'B';
    if ($percentage >= 50) return 'C';
    return 'D';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Staff Appraisal Marks Data (13.6)</title>
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
            font-size: 0.9em;
        }

        .score-cell {
            font-weight: bold;
            text-align: center;
        }

        .grade-A { color: var(--success-color); }
        .grade-B { color: var(--primary-color); }
        .grade-C { color: var(--warning-color); }
        .grade-D { color: var(--danger-color); }

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
                        <h1><i class="fas fa-chart-line me-3"></i>Staff Appraisal Marks Data</h1>
                        <p>Review completed appraisal scores and performance analytics</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?= $total_appraisals ?></h3>
                                <small>Total Appraisals</small>
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
                            <label class="form-label">Search Employee</label>
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
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <a href="staff_marks_data.php?year=<?= $selected_year ?>" class="btn btn-outline-secondary">
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
                                    <i class="fas fa-table text-primary me-2"></i>
                                    Staff Appraisal Marks (Year <?= $selected_year ?>)
                                </h5>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-outline-success btn-sm" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel me-1"></i>Export Excel
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($staff_appraisals)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chart-line text-muted" style="font-size: 3em;"></i>
                                <h5 class="mt-3 text-muted">No Appraisal Data Found</h5>
                                <p class="text-muted">No appraisals found for the selected criteria.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped data-table mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Job Knowledge<br><small>(15 max)</small></th>
                                            <th>Creativity<br><small>(5 max)</small></th>
                                            <th>Quality<br><small>(10 max)</small></th>
                                            <th>Goals<br><small>(5 max)</small></th>
                                            <th>Teamwork<br><small>(20 max)</small></th>
                                            <th>Culture<br><small>(30 max)</small></th>
                                            <th>Safety<br><small>(20 max)</small></th>
                                            <th>Discipline<br><small>(20 max)</small></th>
                                            <th>Total<br><small>(125 max)</small></th>
                                            <th>Grade</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($staff_appraisals as $appraisal): ?>
                                            <?php
                                                $grade = getPerformanceGrade($appraisal['overall_total'], 125);
                                                $gradeClass = 'grade-' . substr($grade, 0, 1);
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($appraisal['appraisee_name']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($appraisal['appraisee_epf']) ?></small><br>
                                                    <small class="text-muted"><?= htmlspecialchars($appraisal['designation']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($appraisal['department']) ?></td>
                                                <td class="score-cell"><?= $appraisal['job_knowledge_total'] ?>/15</td>
                                                <td class="score-cell"><?= $appraisal['creativity_total'] ?>/5</td>
                                                <td class="score-cell"><?= $appraisal['quality_total'] ?>/10</td>
                                                <td class="score-cell"><?= $appraisal['goal_total'] ?>/5</td>
                                                <td class="score-cell"><?= $appraisal['teamwork_total'] ?>/20</td>
                                                <td class="score-cell"><?= $appraisal['cultural_total'] ?>/30</td>
                                                <td class="score-cell"><?= $appraisal['safety_total'] ?>/20</td>
                                                <td class="score-cell"><?= $appraisal['discipline_total'] ?>/20</td>
                                                <td class="score-cell fw-bold text-primary"><?= $appraisal['overall_total'] ?>/125</td>
                                                <td class="score-cell <?= $gradeClass ?>"><?= $grade ?></td>
                                                <td>
                                                    <span class="badge status-badge
                                                        <?= $appraisal['status'] === 'approved' ? 'bg-success' :
                                                           ($appraisal['status'] === 'submitted' ? 'bg-info' :
                                                           ($appraisal['status'] === 'rejected' ? 'bg-danger' : 'bg-warning')) ?>">
                                                        <?= ucfirst($appraisal['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-outline-primary btn-sm me-1"
                                                            onclick="viewDetails(<?= $appraisal['id'] ?>)"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-info btn-sm"
                                                            onclick="printAppraisal(<?= $appraisal['id'] ?>)"
                                                            title="Print">
                                                        <i class="fas fa-print"></i>
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
                        <a href="staff_list.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-users me-1"></i>Staff List (13.1)
                        </a>
                        <a href="staff_appraisal.php" class="btn btn-outline-success">
                            <i class="fas fa-edit me-1"></i>New Appraisal (13.5)
                        </a>
                    </div>
                </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDetails(appraisalId) {
            // Open detailed view in new window
            window.open(`staff_appraisal_detail.php?id=${appraisalId}`, '_blank');
        }

        function printAppraisal(appraisalId) {
            // Open printable version
            window.open(`staff_appraisal_print.php?id=${appraisalId}`, '_blank');
        }

        function exportToExcel() {
            // Simple export functionality
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
                    // Handle commas in data
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
            a.setAttribute('download', `staff_appraisal_marks_<?= $selected_year ?>.csv`);
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