<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check permissions
$isAdmin = isAdmin() || isSuperAdmin();
$canViewSkills = $isAdmin || hasModulePermission($db, 'skills.view_skill_matrix') || hasModulePermission($db, 'skills.complete_skill_assessment');

if (!$canViewSkills) {
    header("Location: index.php");
    exit();
}

// Get available years
$years = [];
$result = $db->query("SELECT * FROM skill_matrix_years WHERE is_active = 1 ORDER BY year DESC");
while ($row = $result->fetch_assoc()) {
    $years[] = $row;
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
    $types = "i";

    if ($selectedEmployee) {
        $sql .= " AND a.employee_epf = ?";
        $params[] = $selectedEmployee;
        $types .= "s";
    }

    if (!$isAdmin) {
        $sql .= " AND a.manager_epf = ?";
        $params[] = $user['epf_number'];
        $types .= "s";
    }

    $sql .= " ORDER BY e.name, c.display_order, s.display_order";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
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
}

// Export functionality
if (isset($_GET['export']) && $_GET['export'] === 'excel' && !empty($assessments)) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="skill_matrix_report_' . date('Y-m-d') . '.xls"');

    echo "<table border='1'>";
    echo "<tr><th>Employee EPF</th><th>Employee Name</th><th>Designation</th><th>Department</th><th>Category</th><th>Skill</th><th>Target</th><th>Current</th><th>Gap</th><th>Manager</th><th>Date</th></tr>";

    foreach ($assessments as $empAssessments) {
        foreach ($empAssessments as $assessment) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($assessment['employee_epf']) . "</td>";
            echo "<td>" . htmlspecialchars($assessment['employee_name']) . "</td>";
            echo "<td>" . htmlspecialchars($assessment['designation']) . "</td>";
            echo "<td>" . htmlspecialchars($assessment['department']) . "</td>";
            echo "<td>" . htmlspecialchars($assessment['category_name']) . "</td>";
            echo "<td>" . htmlspecialchars($assessment['skill_name']) . "</td>";
            echo "<td>" . $assessment['target_rating'] . "</td>";
            echo "<td>" . $assessment['current_rating'] . "</td>";
            echo "<td>" . $assessment['gap_rating'] . "</td>";
            echo "<td>" . htmlspecialchars($assessment['manager_name']) . "</td>";
            echo "<td>" . $assessment['submitted_at'] . "</td>";
            echo "</tr>";
        }
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

    if (!$isAdmin) {
        $sql .= " AND a.manager_epf = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("is", $selectedYear, $user['epf_number']);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $selectedYear);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
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
    <style>
        :root {
            --sidebar-width: 280px;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .top-navbar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: auto;
        }

        .content-wrapper {
            padding: 2rem;
        }

        .report-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
        }

        .category-section {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
            padding: 1rem;
            margin-bottom: 1rem;
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

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0 ms-2">Skill Matrix Reports</h5>
            </div>

            <div class="user-info">
                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>
                        <?php echo htmlspecialchars($user['name']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content-wrapper">
            <div class="container-fluid">
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
                <?php endif; ?>

                <!-- Navigation -->
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                    <div>
                        <a href="assessment_form.php" class="btn btn-success">
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

    <script>
        $(document).ready(function() {
            $('#summaryTable').DataTable({
                pageLength: 25,
                order: [[6, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [7] }
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