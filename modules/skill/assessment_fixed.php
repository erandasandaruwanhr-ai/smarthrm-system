<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once 'hierarchy_functions.php';

$user = getCurrentUser();
$db = new Database();

// Check permissions
$isAdmin = isAdmin() || isSuperAdmin();
$canManageSkills = $isAdmin || hasModulePermission($db, 'skills.manage_location_skills') || hasModulePermission($db, 'skills.complete_skill_assessment');

if (!$canManageSkills) {
    header("Location: index.php");
    exit();
}

$message = '';
$messageType = 'info';

// Get user's employment level
$userLevel = null;
try {
    $userDetails = $db->fetch("SELECT employment_level_id FROM employees WHERE epf_number = ?", [$user['epf_number']]);
    $userLevel = $userDetails ? $userDetails['employment_level_id'] : null;
} catch (Exception $e) {
    $message = "Error loading user details: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assessment'])) {
    $year_id = intval($_POST['year_id']);
    $employee_epf = trim($_POST['employee_epf']);
    $manager_epf = $user['epf_number'];

    try {
        // Start transaction
        $db->query("START TRANSACTION");

        // Delete existing assessments for this employee/year combination
        $db->query("DELETE FROM skill_matrix_assessments WHERE year_id = ? AND employee_epf = ?",
            [$year_id, $employee_epf]);

        // Insert new assessments
        foreach ($_POST['skills'] as $skill_id => $ratings) {
            $target = intval($ratings['target']);
            $current = intval($ratings['current']);
            $gap = intval($ratings['gap']);

            $db->query("INSERT INTO skill_matrix_assessments (year_id, employee_epf, manager_epf, skill_id, target_rating, current_rating, gap_rating) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$year_id, $employee_epf, $manager_epf, $skill_id, $target, $current, $gap]);
        }

        $db->query("COMMIT");
        $message = "Assessment submitted successfully!";
        $messageType = 'success';
    } catch (Exception $e) {
        $db->query("ROLLBACK");
        $message = "Error submitting assessment: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get available years
$years = [];
try {
    $years = $db->fetchAll("SELECT * FROM skill_matrix_years WHERE is_active = 1 ORDER BY year DESC");
} catch (Exception $e) {
    $message = "Error loading years: " . $e->getMessage();
}

// Get employees under current user
$employees = [];
try {
    if ($isAdmin) {
        // Admin sees all employees
        $employees = $db->fetchAll("SELECT epf_number, name, designation, department FROM employees WHERE is_active = 1 ORDER BY name");
    } elseif ($userLevel == 2 || $userLevel == 3) {
        // Manager (Level 3) or GM (Level 2) - get employees under them
        $employees = getEmployeesUnderManager($db, $user['epf_number']);

        // If no employees found through hierarchy, try direct reports as fallback
        if (empty($employees)) {
            $employees = $db->fetchAll("SELECT epf_number, name, designation, department
                                       FROM employees
                                       WHERE is_active = 1 AND reports_to = ?
                                       ORDER BY name", [$user['epf_number']]);
        }
    } else {
        // Not a manager - no employees to assess
        $message = "You must be a Manager (Level 3) or GM (Level 2) to conduct skill assessments. Your current level: $userLevel";
        $messageType = 'warning';
    }
} catch (Exception $e) {
    $message = "Error loading employees: " . $e->getMessage();
    $messageType = 'danger';
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

        .assessment-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .skill-category {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .skill-row {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
        }

        .rating-select {
            width: 100px;
        }

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9em;
        }

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
                <h5 class="mb-0 ms-2">Skill Assessment</h5>
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
                        <li class="breadcrumb-item active">Assessment</li>
                    </ol>
                </nav>

                <!-- Debug Info -->
                <div class="debug-info">
                    <strong>Debug Info:</strong><br>
                    Logged-in User: <?php echo htmlspecialchars($user['name']); ?> (EPF: <?php echo $user['epf_number']; ?>)<br>
                    Employment Level: <?php echo $userLevel ?: 'Unknown'; ?><br>
                    Is Admin: <?php echo $isAdmin ? 'Yes' : 'No'; ?><br>
                    Employees Available: <?php echo count($employees); ?><br>
                    Years Available: <?php echo count($years); ?>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Step 1: Select Year and Employee -->
                <div class="assessment-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user-check me-2"></i>Assessment Setup</h5>
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
                                    <?php if (!empty($employees)): ?>
                                        <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['epf_number']; ?>" <?php echo ($selectedEmployee == $employee['epf_number']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($employee['epf_number'] . ' - ' . $employee['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No employees available</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Load Assessment Form
                                </button>
                                <?php if (empty($employees)): ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <?php if ($userLevel && $userLevel > 3): ?>
                                            Note: You must be a Manager (Level 3) or GM (Level 2) to conduct assessments.
                                        <?php else: ?>
                                            Note: No employees found under your management.
                                        <?php endif ?>
                                    </small>
                                </div>
                                <?php endif; ?>
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
                                            <label class="form-label small">Target</label>
                                            <select class="form-select rating-select" name="skills[<?php echo $skill['id']; ?>][target]" required>
                                                <option value="">-</option>
                                                <option value="1">1</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                                <option value="5">5</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Current</label>
                                            <select class="form-select rating-select" name="skills[<?php echo $skill['id']; ?>][current]" required>
                                                <option value="">-</option>
                                                <option value="1">1</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                                <option value="5">5</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Gap</label>
                                            <select class="form-select rating-select" name="skills[<?php echo $skill['id']; ?>][gap]" required>
                                                <option value="">-</option>
                                                <option value="1">1</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                                <option value="5">5</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>

                            <div class="text-center mt-4">
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

        // Auto-calculate gap when target and current are selected
        document.addEventListener('change', function(e) {
            if (e.target.name && (e.target.name.includes('[target]') || e.target.name.includes('[current]'))) {
                const skillId = e.target.name.match(/\[(\d+)\]/)[1];
                const targetSelect = document.querySelector(`select[name="skills[${skillId}][target]"]`);
                const currentSelect = document.querySelector(`select[name="skills[${skillId}][current]"]`);
                const gapSelect = document.querySelector(`select[name="skills[${skillId}][gap]"]`);

                if (targetSelect.value && currentSelect.value) {
                    const gap = Math.abs(parseInt(targetSelect.value) - parseInt(currentSelect.value)) + 1;
                    gapSelect.value = Math.min(gap, 5);
                }
            }
        });
    </script>
</body>
</html>