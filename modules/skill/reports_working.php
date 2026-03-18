<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once 'hierarchy_functions.php';

$user = getCurrentUser();
$db = new Database();

// Check permissions
$isAdmin = isAdmin() || isSuperAdmin();
$canViewSkills = $isAdmin || hasModulePermission($db, 'skill.view_skill_matrix') || hasModulePermission($db, 'skill.assessment_reports');

if (!$canViewSkills) {
    header("Location: index.php");
    exit();
}

// Get available years
$years = [];
try {
    $years = $db->fetchAll("SELECT * FROM skill_matrix_years WHERE is_active = 1 ORDER BY year DESC");
} catch (Exception $e) {
    $message = "Error loading years: " . $e->getMessage();
}

$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : null;
$selectedEmployee = isset($_GET['employee']) ? $_GET['employee'] : null;

// Get assessment data
$assessments = [];
$summaryStats = [];

if ($selectedYear) {
    $sql = "SELECT
        a.*,
        e.name as employee_name,
        e.designation,
        e.department,
        m.name as manager_name,
        s.skill_name,
        c.name as category_name,
        c.display_order as category_order
    FROM skill_matrix_assessments a
    JOIN employees e ON a.employee_epf = e.epf_number
    JOIN employees m ON a.manager_epf = m.epf_number
    JOIN skill_matrix_skills s ON a.skill_id = s.id
    JOIN skill_matrix_categories c ON s.category_id = c.id
    WHERE a.year_id = ?";

    $params = [$selectedYear];

    if ($selectedEmployee) {
        $sql .= " AND a.employee_epf = ?";
        $params[] = $selectedEmployee;
    }

    if (!$isAdmin) {
        $sql .= " AND a.manager_epf = ?";
        $params[] = $user['epf_number'];
    }

    $sql .= " ORDER BY e.name, c.display_order, s.display_order";

    try {
        $assessmentData = $db->fetchAll($sql, $params);

        foreach ($assessmentData as $row) {
            $assessments[$row['employee_epf']][] = $row;
        }

        // Calculate summary statistics
        if (!empty($assessments)) {
            foreach ($assessments as $empEpf => $empAssessments) {
                $totalTarget = 0;
                $totalCurrent = 0;
                $totalGap = 0;
                $count = count($empAssessments);

                foreach ($empAssessments as $assessment) {
                    $totalTarget += $assessment['target_rating'];
                    $totalCurrent += $assessment['current_rating'];
                    $totalGap += $assessment['gap_rating'];
                }

                $summaryStats[$empEpf] = [
                    'target_avg' => round($totalTarget / $count, 2),
                    'current_avg' => round($totalCurrent / $count, 2),
                    'gap_avg' => round($totalGap / $count, 2),
                    'percentage' => round(($totalCurrent / $totalTarget) * 100, 1),
                    'employee_name' => $empAssessments[0]['employee_name'],
                    'designation' => $empAssessments[0]['designation'],
                    'department' => $empAssessments[0]['department']
                ];
            }
        }
    } catch (Exception $e) {
        $message = "Error loading assessment data: " . $e->getMessage();
    }
}

// Export functionality
if (isset($_GET['export']) && $_GET['export'] === 'excel' && !empty($assessments)) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="skill_matrix_report_' . date('Y-m-d') . '.xls"');

    echo "<table border='1'>";

    // Build dynamic header with all unique skills
    $allSkills = [];
    foreach ($assessments as $empAssessments) {
        foreach ($empAssessments as $assessment) {
            $skillKey = $assessment['category_name'] . ' - ' . $assessment['skill_name'];
            if (!in_array($skillKey, $allSkills)) {
                $allSkills[] = $skillKey;
            }
        }
    }

    // Header row
    echo "<tr>";
    echo "<th>Employee EPF</th>";
    echo "<th>Employee Name</th>";
    echo "<th>Designation</th>";
    echo "<th>Department</th>";
    echo "<th>Manager</th>";
    echo "<th>Assessment Date</th>";
    echo "<th>Overall Target Avg</th>";
    echo "<th>Overall Current Avg</th>";
    echo "<th>Overall Gap Avg</th>";
    echo "<th>Achievement %</th>";

    // Add columns for each skill (Target, Current, Gap)
    foreach ($allSkills as $skill) {
        echo "<th>" . htmlspecialchars($skill) . " - Target</th>";
        echo "<th>" . htmlspecialchars($skill) . " - Current</th>";
        echo "<th>" . htmlspecialchars($skill) . " - Gap</th>";
    }
    echo "</tr>";

    // Data rows - one per employee
    foreach ($assessments as $empEpf => $empAssessments) {
        if (empty($empAssessments)) continue;

        $firstAssessment = $empAssessments[0];

        // Calculate averages
        $totalTarget = $totalCurrent = $totalGap = 0;
        $skillData = [];

        foreach ($empAssessments as $assessment) {
            $skillKey = $assessment['category_name'] . ' - ' . $assessment['skill_name'];
            $skillData[$skillKey] = [
                'target' => $assessment['target_rating'],
                'current' => $assessment['current_rating'],
                'gap' => $assessment['gap_rating']
            ];
            $totalTarget += $assessment['target_rating'];
            $totalCurrent += $assessment['current_rating'];
            $totalGap += $assessment['gap_rating'];
        }

        $count = count($empAssessments);
        $avgTarget = round($totalTarget / $count, 2);
        $avgCurrent = round($totalCurrent / $count, 2);
        $avgGap = round($totalGap / $count, 2);
        $achievement = $avgTarget > 0 ? round(($avgCurrent / $avgTarget) * 100, 2) : 0;

        echo "<tr>";
        echo "<td>" . htmlspecialchars($firstAssessment['employee_epf']) . "</td>";
        echo "<td>" . htmlspecialchars($firstAssessment['employee_name']) . "</td>";
        echo "<td>" . htmlspecialchars($firstAssessment['designation']) . "</td>";
        echo "<td>" . htmlspecialchars($firstAssessment['department']) . "</td>";
        echo "<td>" . htmlspecialchars($firstAssessment['manager_name']) . "</td>";
        echo "<td>" . $firstAssessment['submitted_at'] . "</td>";
        echo "<td>" . $avgTarget . "</td>";
        echo "<td>" . $avgCurrent . "</td>";
        echo "<td>" . $avgGap . "</td>";
        echo "<td>" . $achievement . "%</td>";

        // Add skill data
        foreach ($allSkills as $skill) {
            if (isset($skillData[$skill])) {
                echo "<td>" . $skillData[$skill]['target'] . "</td>";
                echo "<td>" . $skillData[$skill]['current'] . "</td>";
                echo "<td>" . $skillData[$skill]['gap'] . "</td>";
            } else {
                echo "<td>-</td><td>-</td><td>-</td>";
            }
        }
        echo "</tr>";
    }
    echo "</table>";
    exit();
}

// Get employees for filter
$employees = [];
if ($selectedYear) {
    $sql = "SELECT DISTINCT e.epf_number, e.name
            FROM employees e
            JOIN skill_matrix_assessments a ON e.epf_number = a.employee_epf
            WHERE a.year_id = ?";

    $params = [$selectedYear];

    if (!$isAdmin) {
        $sql .= " AND a.manager_epf = ?";
        $params[] = $user['epf_number'];
    }

    $sql .= " ORDER BY e.name";

    try {
        $employees = $db->fetchAll($sql, $params);
    } catch (Exception $e) {
        // Ignore error, just won't have employee filter
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Skill Matrix Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/fixedcolumns/4.1.0/css/fixedColumns.bootstrap5.min.css" rel="stylesheet">
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

        .report-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .category-section {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
        }

        .skill-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .rating-badge {
            font-size: 0.8em;
            padding: 0.25rem 0.5rem;
        }

        .rating-1 { background-color: #dc3545; }
        .rating-2 { background-color: #fd7e14; }
        .rating-3 { background-color: #ffc107; color: black; }
        .rating-4 { background-color: #20c997; }
        .rating-5 { background-color: #198754; }

        .btn-custom {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-custom:hover {
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
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-chart-bar me-3"></i>Assessment Reports</h1>
                        <p>View completed assessments, analyze skill gaps, and generate comprehensive reports</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Skill Matrix</a></li>
                    <li class="breadcrumb-item active">Reports</li>
                </ol>
            </nav>

                <!-- Filters -->
                <div class="report-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Report Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label for="year" class="form-label">Assessment Year</label>
                                <select class="form-select" name="year" required>
                                    <option value="">Select Year</option>
                                    <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year['id']; ?>" <?php echo ($selectedYear == $year['id']) ? 'selected' : ''; ?>>
                                        <?php echo $year['year']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="employee" class="form-label">Employee (Optional)</label>
                                <select class="form-select" name="employee">
                                    <option value="">All Employees</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['epf_number']; ?>" <?php echo ($selectedEmployee == $employee['epf_number']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['epf_number'] . ' - ' . $employee['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Generate Report
                                </button>
                                <?php if (!empty($assessments)): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-success">
                                    <i class="fas fa-file-excel me-2"></i>Export to Excel
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!empty($summaryStats)): ?>
                <!-- Summary Statistics -->
                <div class="report-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Assessment Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="summaryTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Designation</th>
                                        <th>Department</th>
                                        <th>Target Avg</th>
                                        <th>Current Avg</th>
                                        <th>Gap Avg</th>
                                        <th>Achievement %</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($summaryStats as $epf => $stats): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stats['employee_name']); ?></td>
                                        <td><?php echo htmlspecialchars($stats['designation']); ?></td>
                                        <td><?php echo htmlspecialchars($stats['department']); ?></td>
                                        <td><span class="badge bg-primary"><?php echo $stats['target_avg']; ?></span></td>
                                        <td><span class="badge bg-success"><?php echo $stats['current_avg']; ?></span></td>
                                        <td><span class="badge bg-warning"><?php echo $stats['gap_avg']; ?></span></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" style="width: <?php echo min($stats['percentage'], 100); ?>%">
                                                    <?php echo $stats['percentage']; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="#detailed-<?php echo $epf; ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse">
                                                <i class="fas fa-eye"></i> Details
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Detailed Assessment Data -->
                <?php foreach ($assessments as $empEpf => $empAssessments): ?>
                <div class="collapse" id="detailed-<?php echo $empEpf; ?>">
                    <div class="report-card">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-user me-2"></i>
                                Detailed Assessment: <?php echo htmlspecialchars($empAssessments[0]['employee_name']); ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $categories = [];
                            foreach ($empAssessments as $assessment) {
                                $categories[$assessment['category_name']][] = $assessment;
                            }
                            ?>

                            <?php foreach ($categories as $categoryName => $categorySkills): ?>
                            <div class="category-section">
                                <h6 class="text-primary mb-3"><?php echo htmlspecialchars($categoryName); ?></h6>
                                <?php foreach ($categorySkills as $skill): ?>
                                <div class="skill-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <strong><?php echo htmlspecialchars($skill['skill_name']); ?></strong>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <span class="badge rating-<?php echo $skill['target_rating']; ?>">
                                                Target: <?php echo $skill['target_rating']; ?>
                                            </span>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <span class="badge rating-<?php echo $skill['current_rating']; ?>">
                                                Current: <?php echo $skill['current_rating']; ?>
                                            </span>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <span class="badge rating-<?php echo $skill['gap_rating']; ?>">
                                                Gap: <?php echo $skill['gap_rating']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Consolidated View - One Row Per Employee -->
                <div class="report-card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>Consolidated Report - One Row Per Employee
                            <small class="ms-2">(Same format as Excel export)</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="consolidatedTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th rowspan="2">Employee EPF</th>
                                        <th rowspan="2">Employee Name</th>
                                        <th rowspan="2">Designation</th>
                                        <th rowspan="2">Department</th>
                                        <th rowspan="2">Manager</th>
                                        <th rowspan="2">Date</th>
                                        <th colspan="4" class="text-center bg-primary text-white">Overall Averages</th>
                                        <?php
                                        // Get all unique skills for header
                                        $allSkills = [];
                                        foreach ($assessments as $empAssessments) {
                                            foreach ($empAssessments as $assessment) {
                                                $skillKey = $assessment['category_name'] . ' - ' . $assessment['skill_name'];
                                                if (!in_array($skillKey, $allSkills)) {
                                                    $allSkills[] = $skillKey;
                                                }
                                            }
                                        }

                                        foreach ($allSkills as $skill):
                                        ?>
                                        <th colspan="3" class="text-center bg-info text-white"><?php echo htmlspecialchars($skill); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <th class="bg-primary text-white">Target Avg</th>
                                        <th class="bg-primary text-white">Current Avg</th>
                                        <th class="bg-primary text-white">Gap Avg</th>
                                        <th class="bg-primary text-white">Achievement %</th>
                                        <?php foreach ($allSkills as $skill): ?>
                                        <th class="bg-success text-white">Target</th>
                                        <th class="bg-warning text-dark">Current</th>
                                        <th class="bg-danger text-white">Gap</th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assessments as $empEpf => $empAssessments): ?>
                                    <?php if (!empty($empAssessments)): ?>
                                    <?php
                                    $firstAssessment = $empAssessments[0];

                                    // Calculate averages
                                    $totalTarget = $totalCurrent = $totalGap = 0;
                                    $skillData = [];

                                    foreach ($empAssessments as $assessment) {
                                        $skillKey = $assessment['category_name'] . ' - ' . $assessment['skill_name'];
                                        $skillData[$skillKey] = [
                                            'target' => $assessment['target_rating'],
                                            'current' => $assessment['current_rating'],
                                            'gap' => $assessment['gap_rating']
                                        ];
                                        $totalTarget += $assessment['target_rating'];
                                        $totalCurrent += $assessment['current_rating'];
                                        $totalGap += $assessment['gap_rating'];
                                    }

                                    $count = count($empAssessments);
                                    $avgTarget = round($totalTarget / $count, 2);
                                    $avgCurrent = round($totalCurrent / $count, 2);
                                    $avgGap = round($totalGap / $count, 2);
                                    $achievement = $avgTarget > 0 ? round(($avgCurrent / $avgTarget) * 100, 2) : 0;
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($firstAssessment['employee_epf']); ?></strong></td>
                                        <td><strong><?php echo htmlspecialchars($firstAssessment['employee_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($firstAssessment['designation']); ?></td>
                                        <td><?php echo htmlspecialchars($firstAssessment['department']); ?></td>
                                        <td><?php echo htmlspecialchars($firstAssessment['manager_name']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($firstAssessment['submitted_at'])); ?></td>
                                        <td><span class="badge bg-primary"><?php echo $avgTarget; ?></span></td>
                                        <td><span class="badge bg-success"><?php echo $avgCurrent; ?></span></td>
                                        <td><span class="badge bg-warning"><?php echo $avgGap; ?></span></td>
                                        <td>
                                            <div class="progress" style="height: 20px; min-width: 80px;">
                                                <div class="progress-bar" style="width: <?php echo min($achievement, 100); ?>%">
                                                    <?php echo $achievement; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <?php foreach ($allSkills as $skill): ?>
                                        <?php if (isset($skillData[$skill])): ?>
                                        <td class="text-center"><span class="badge bg-success"><?php echo $skillData[$skill]['target']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-warning"><?php echo $skillData[$skill]['current']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-danger"><?php echo $skillData[$skill]['gap']; ?></span></td>
                                        <?php else: ?>
                                        <td class="text-center">-</td>
                                        <td class="text-center">-</td>
                                        <td class="text-center">-</td>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <p class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Note:</strong> This table shows one row per employee with all skills consolidated.
                                Use the Export to Excel button to download this exact format.
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Navigation -->
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                    <div>
                        <a href="assessment_working.php" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>New Assessment
                        </a>
                        <a href="employee_list.php" class="btn btn-info">
                            <i class="fas fa-users me-2"></i>Employee List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/fixedcolumns/4.1.0/js/dataTables.fixedColumns.min.js"></script>

    <script>
        $(document).ready(function() {
            if (document.getElementById('summaryTable')) {
                $('#summaryTable').DataTable({
                    pageLength: 25,
                    order: [[6, 'desc']],
                    columnDefs: [
                        { orderable: false, targets: [7] }
                    ]
                });
            }

            if (document.getElementById('consolidatedTable')) {
                $('#consolidatedTable').DataTable({
                    pageLength: 25,
                    order: [[9, 'desc']], // Order by Achievement %
                    scrollX: true,
                    fixedColumns: {
                        leftColumns: 2 // Fix Employee EPF and Name columns
                    },
                    columnDefs: [
                        { className: "text-center", targets: "_all" }
                    ]
                });
            }
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>