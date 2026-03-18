<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once 'hierarchy_functions.php';

$user = getCurrentUser();
$db = new Database();

// Check permissions
$isAdmin = isAdmin() || isSuperAdmin();
$canManageSkills = $isAdmin || hasModulePermission($db, 'skill.skill_assessment') || hasModulePermission($db, 'skill.complete_skill_assessment');

if (!$canManageSkills) {
    header("Location: index.php");
    exit();
}

$message = '';
$messageType = 'info';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assessment'])) {
    $year_id = intval($_POST['year_id']);
    $employee_epf = trim($_POST['employee_epf']);
    $manager_epf = $user['epf_number'];

    // Debug: Log what we received
    error_log("Assessment submission - Year: $year_id, Employee: $employee_epf, Manager: $manager_epf");
    error_log("Skills data: " . print_r($_POST['skills'], true));

    try {
        // Start transaction
        $db->query("START TRANSACTION");

        // Delete existing assessments for this employee/year combination
        $db->query("DELETE FROM skill_matrix_assessments WHERE year_id = ? AND employee_epf = ?",
            [$year_id, $employee_epf]);

        $inserted = 0;
        // Insert new assessments
        foreach ($_POST['skills'] as $skill_id => $ratings) {
            $target = intval($ratings['target'] ?? 0);
            $current = intval($ratings['current'] ?? 0);

            // Recalculate gap to ensure database constraint compliance (1-5)
            $gap = abs($target - $current) + 1;

            // Only insert if we have valid ratings
            if ($target > 0 && $current > 0) {
                $db->query("INSERT INTO skill_matrix_assessments (year_id, employee_epf, manager_epf, skill_id, target_rating, current_rating, gap_rating) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$year_id, $employee_epf, $manager_epf, $skill_id, $target, $current, $gap]);
                $inserted++;
                error_log("Inserted assessment - Skill: $skill_id, Current: $current, Target: $target, Gap: $gap");
            }
        }

        $db->query("COMMIT");
        $message = "Assessment submitted successfully! Saved $inserted skill assessments.";
        $messageType = 'success';
    } catch (Exception $e) {
        $db->query("ROLLBACK");
        $message = "Error submitting assessment: " . $e->getMessage();
        $messageType = 'danger';
        error_log("Assessment error: " . $e->getMessage());
    }
}

// Get available years
$years = [];
try {
    $years = $db->fetchAll("SELECT * FROM skill_matrix_years WHERE is_active = 1 ORDER BY year DESC");
} catch (Exception $e) {
    $message = "Error loading years: " . $e->getMessage();
}

// Get employees under current user (if manager) or all employees (if admin)
$employees = [];
if ($isAdmin) {
    try {
        $employees = $db->fetchAll("SELECT epf_number, name, designation, department FROM employees WHERE is_active = 1 ORDER BY CAST(epf_number AS UNSIGNED)");
    } catch (Exception $e) {
        $message = "Error loading employees: " . $e->getMessage();
    }
} else {
    try {
        // Get employees who have this user as their actual manager (level 3) or GM (level 2)
        $employees = getEmployeesUnderManager($db, $user['epf_number']);
    } catch (Exception $e) {
        $message = "Error loading team members: " . $e->getMessage();
    }
}

// Get skills for selected year
$skills = [];
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : null;
$selectedEmployee = isset($_GET['employee']) ? $_GET['employee'] : null;

if ($selectedYear) {
    try {
        $skillsData = $db->fetchAll("SELECT s.*, c.name as category_name
                FROM skill_matrix_skills s
                JOIN skill_matrix_categories c ON s.category_id = c.id
                WHERE s.year_id = ? AND s.is_active = 1
                ORDER BY c.display_order, s.display_order", [$selectedYear]);

        foreach ($skillsData as $skill) {
            $skills[$skill['category_name']][] = $skill;
        }
    } catch (Exception $e) {
        $message = "Error loading skills: " . $e->getMessage();
    }
}

// Get employee details if selected
$selectedEmployeeDetails = null;
if ($selectedEmployee) {
    try {
        $selectedEmployeeDetails = $db->fetch("SELECT e.*, l.location_name, TIMESTAMPDIFF(YEAR, e.joined_date, CURDATE()) as service_years
                              FROM employees e
                              LEFT JOIN locations l ON e.location_id = l.id
                              WHERE e.epf_number = ?", [$selectedEmployee]);
    } catch (Exception $e) {
        $message = "Error loading employee details: " . $e->getMessage();
    }
}

// Get existing assessments for this employee/year combination
$existingAssessments = [];
if ($selectedEmployee && $selectedYear) {
    try {
        $existingAssessmentsData = $db->fetchAll("SELECT skill_id, target_rating, current_rating, gap_rating
                                                 FROM skill_matrix_assessments
                                                 WHERE employee_epf = ? AND year_id = ?",
                                                 [$selectedEmployee, $selectedYear]);

        // Index by skill_id for easy lookup
        foreach ($existingAssessmentsData as $assessment) {
            $existingAssessments[$assessment['skill_id']] = $assessment;
        }

        if (!empty($existingAssessments)) {
            $message = "✅ Loaded existing assessments for this employee. You can update any ratings and resubmit.";
            $messageType = 'info';
        }
    } catch (Exception $e) {
        error_log("Error loading existing assessments: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Skill Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .assessment-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .assessment-card .card-header {
            border-bottom: none;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0 !important;
        }

        .assessment-card .card-body {
            padding: 2rem;
        }

        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            font-weight: bold;
            font-size: 1.1rem;
            margin-right: 1rem;
        }

        .skill-category {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-left: 4px solid #007bff;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .skill-category h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .skill-row {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .skill-row:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.1);
        }

        .skill-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .skill-description {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .rating-select {
            width: 100px;
        }

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
                        <h1><i class="fas fa-clipboard-check me-3"></i>Skill Assessment</h1>
                        <p>Conduct skill assessments and evaluate team member competency levels</p>
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
                    <li class="breadcrumb-item active">Assessment</li>
                </ol>
            </nav>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Debug Section (temporary) -->
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <div class="alert alert-info">
                    <strong>Debug - POST Data Received:</strong><br>
                    <strong>Method:</strong> <?php echo $_SERVER['REQUEST_METHOD']; ?><br>
                    <strong>Submit Button:</strong> <?php echo isset($_POST['submit_assessment']) ? 'YES' : 'NO'; ?><br>
                    <strong>Skills Count:</strong> <?php echo count($_POST['skills'] ?? []); ?><br>
                    <strong>Year ID:</strong> <?php echo $_POST['year_id'] ?? 'Not set'; ?><br>
                    <strong>Employee EPF:</strong> <?php echo $_POST['employee_epf'] ?? 'Not set'; ?>
                </div>
                <?php endif; ?>

                <!-- Step 1: Select Year and Employee -->
                <div class="assessment-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <span class="step-number">1</span>
                            <i class="fas fa-user-check me-2"></i>Assessment Setup
                        </h5>
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
                                <label for="employee" class="form-label">Employee</label>
                                <select class="form-select" name="employee" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['epf_number']; ?>" <?php echo ($selectedEmployee == $employee['epf_number']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['epf_number'] . ' - ' . $employee['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Load Assessment Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Step 2: Employee Details -->
                <?php if ($selectedEmployeeDetails): ?>
                <div class="assessment-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-id-badge me-2"></i>Employee Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Manager:</strong><br>
                                <span class="text-primary"><?php echo htmlspecialchars($user['epf_number'] . ' - ' . $user['name']); ?></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Employee:</strong><br>
                                <span class="text-success"><?php echo htmlspecialchars($selectedEmployeeDetails['epf_number'] . ' - ' . $selectedEmployeeDetails['name']); ?></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Designation:</strong><br>
                                <?php echo htmlspecialchars($selectedEmployeeDetails['designation'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Department:</strong><br>
                                <?php echo htmlspecialchars($selectedEmployeeDetails['department'] ?? 'N/A'); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Step 3: Assessment Form -->
                <?php if (!empty($skills)): ?>
                <form method="POST">
                    <input type="hidden" name="year_id" value="<?php echo $selectedYear; ?>">
                    <input type="hidden" name="employee_epf" value="<?php echo htmlspecialchars($selectedEmployee); ?>">

                    <div class="assessment-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Skill Assessment</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Rating Scale:</strong>
                                <span class="badge bg-danger">1 - Novice</span>
                                <span class="badge bg-warning">2 - Beginner</span>
                                <span class="badge bg-primary">3 - Competent</span>
                                <span class="badge bg-info">4 - Proficient</span>
                                <span class="badge bg-success">5 - Expert</span>
                            </div>

                            <?php foreach ($skills as $category => $categorySkills): ?>
                            <div class="skill-category">
                                <h6 class="mb-3 text-primary">
                                    <i class="fas fa-layer-group me-2"></i><?php echo htmlspecialchars($category); ?>
                                </h6>

                                <?php foreach ($categorySkills as $skill): ?>
                                <div class="skill-row">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($skill['skill_name']); ?></h6>
                                            <?php if (!empty($skill['skill_description'])): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($skill['skill_description']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Current</label>
                                            <select class="form-select rating-select current-rating" name="skills[<?php echo $skill['id']; ?>][current]" data-skill-id="<?php echo $skill['id']; ?>">
                                                <?php
                                                $currentValue = $existingAssessments[$skill['id']]['current_rating'] ?? '';
                                                ?>
                                                <option value="">-</option>
                                                <option value="1" <?php echo ($currentValue == '1') ? 'selected' : ''; ?>>1</option>
                                                <option value="2" <?php echo ($currentValue == '2') ? 'selected' : ''; ?>>2</option>
                                                <option value="3" <?php echo ($currentValue == '3') ? 'selected' : ''; ?>>3</option>
                                                <option value="4" <?php echo ($currentValue == '4') ? 'selected' : ''; ?>>4</option>
                                                <option value="5" <?php echo ($currentValue == '5') ? 'selected' : ''; ?>>5</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Target</label>
                                            <select class="form-select rating-select target-rating" name="skills[<?php echo $skill['id']; ?>][target]" data-skill-id="<?php echo $skill['id']; ?>">
                                                <?php
                                                $targetValue = $existingAssessments[$skill['id']]['target_rating'] ?? '';
                                                ?>
                                                <option value="">-</option>
                                                <option value="1" <?php echo ($targetValue == '1') ? 'selected' : ''; ?>>1</option>
                                                <option value="2" <?php echo ($targetValue == '2') ? 'selected' : ''; ?>>2</option>
                                                <option value="3" <?php echo ($targetValue == '3') ? 'selected' : ''; ?>>3</option>
                                                <option value="4" <?php echo ($targetValue == '4') ? 'selected' : ''; ?>>4</option>
                                                <option value="5" <?php echo ($targetValue == '5') ? 'selected' : ''; ?>>5</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Gap <small class="text-muted">(Auto)</small></label>
                                            <input type="number" class="form-control gap-rating" name="skills[<?php echo $skill['id']; ?>][gap]" data-skill-id="<?php echo $skill['id']; ?>" readonly placeholder="1" min="1" max="5" value="<?php echo $existingAssessments[$skill['id']]['gap_rating'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>

                            <div class="text-center mt-4">
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> You can submit partial assessments. Only skills with both Current and Target ratings will be saved.
                                </div>
                                <button type="submit" name="submit_assessment" class="btn btn-success btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Assessment
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endif; ?>

                <!-- Navigation -->
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                    <div>
                        <a href="employee_list.php" class="btn btn-info">
                            <i class="fas fa-users me-2"></i>Employee List
                        </a>
                        <a href="reports_working.php" class="btn btn-primary">
                            <i class="fas fa-chart-bar me-2"></i>View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Auto-calculate gap when current or target is selected
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('current-rating') || e.target.classList.contains('target-rating')) {
                const skillId = e.target.getAttribute('data-skill-id');
                const currentSelect = document.querySelector(`select[data-skill-id="${skillId}"].current-rating`);
                const targetSelect = document.querySelector(`select[data-skill-id="${skillId}"].target-rating`);
                const gapInput = document.querySelector(`input[data-skill-id="${skillId}"].gap-rating`);

                if (currentSelect.value && targetSelect.value) {
                    const current = parseInt(currentSelect.value);
                    const target = parseInt(targetSelect.value);
                    const gap = Math.abs(target - current) + 1; // Absolute difference + 1 (always 1-5)

                    gapInput.value = gap;

                    // Color code the gap field based on new 1-5 scale
                    gapInput.className = gapInput.className.replace(/\b(text-success|text-warning|text-danger)\b/g, '');
                    if (gap === 1) {
                        gapInput.classList.add('text-success'); // Gap of 1 = on target (best)
                    } else if (gap <= 3) {
                        gapInput.classList.add('text-warning'); // Gap of 2-3 = moderate difference
                    } else {
                        gapInput.classList.add('text-danger'); // Gap of 4-5 = large difference
                    }
                } else {
                    gapInput.value = '';
                    gapInput.className = gapInput.className.replace(/\b(text-success|text-warning|text-danger)\b/g, '');
                }
            }
        });
    </script>
</body>
</html>