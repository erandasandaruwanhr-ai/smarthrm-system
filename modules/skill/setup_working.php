<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has matrix setup permission
$user = getCurrentUser();
$db = new Database();

if (!hasModulePermission($db, 'skill.matrix_setup')) {
    header("Location: index.php");
    exit();
}
$message = '';

// Get skill categories first (needed for form processing)
$categories = [];
try {
    $categories = $db->fetchAll("SELECT * FROM skill_matrix_categories ORDER BY display_order");
} catch (Exception $e) {
    $message = "Error loading categories: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_year'])) {
        $year = intval($_POST['year']);

        try {
            $db->query("INSERT INTO skill_matrix_years (year) VALUES (?)", [$year]);
            $message = "✅ Year $year added successfully.";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $message = "⚠️ Year $year already exists.";
            } else {
                $message = "❌ Error adding year: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['save_skills'])) {
        $year_id = intval($_POST['year_id']);

        $added = 0;
        $updated = 0;

        // Loop through each category
        foreach ($categories as $category) {
            $category_id = $category['id'];
            $category_name = $category['name'];

            // Process skills for this category
            for ($i = 1; $i <= 5; $i++) {
                $skill_name = trim($_POST["category_{$category_id}_skill_{$i}_name"] ?? '');
                $skill_description = trim($_POST["category_{$category_id}_skill_{$i}_description"] ?? '');

                if (!empty($skill_name)) {
                    // Check if skill already exists for this position
                    $existing_skill_by_position = $db->fetch("SELECT id FROM skill_matrix_skills WHERE year_id = ? AND category_id = ? AND display_order = ?",
                        [$year_id, $category_id, $i]);

                    // Check if skill name already exists in this category (to avoid constraint violation)
                    $existing_skill_by_name = $db->fetch("SELECT id, display_order FROM skill_matrix_skills WHERE year_id = ? AND category_id = ? AND skill_name = ?",
                        [$year_id, $category_id, $skill_name]);

                    try {
                        if ($existing_skill_by_position) {
                            // Update existing skill at this position
                            $db->query("UPDATE skill_matrix_skills SET skill_name = ?, skill_description = ? WHERE id = ?",
                                [$skill_name, $skill_description, $existing_skill_by_position['id']]);
                            $updated++;
                        } elseif ($existing_skill_by_name && $existing_skill_by_name['display_order'] != $i) {
                            // Skill name exists but at different position - show error
                            $message = "❌ Skill '$skill_name' already exists at position " . $existing_skill_by_name['display_order'] . " in this category. Please use a different name or update the existing one.";
                            break 2;
                        } else {
                            // Insert new skill (safe because we checked for duplicates)
                            $db->query("INSERT INTO skill_matrix_skills (year_id, category_id, skill_name, skill_description, display_order) VALUES (?, ?, ?, ?, ?)",
                                [$year_id, $category_id, $skill_name, $skill_description, $i]);
                            $added++;
                        }
                    } catch (Exception $e) {
                        $message = "❌ Error processing skill '$skill_name': " . $e->getMessage();
                        break 2; // Break out of both loops
                    }
                } else {
                    // If skill name is empty, delete existing skill if it exists
                    $existing_skill = $db->fetch("SELECT id FROM skill_matrix_skills WHERE year_id = ? AND category_id = ? AND display_order = ?",
                        [$year_id, $category_id, $i]);
                    if ($existing_skill) {
                        $db->query("DELETE FROM skill_matrix_skills WHERE id = ?", [$existing_skill['id']]);
                    }
                }
            }
        }

        if (empty($message)) {
            $total_processed = $added + $updated;
            if ($total_processed > 0) {
                $message = "✅ Successfully processed $total_processed skills (Added: $added, Updated: $updated).";
            } else {
                $message = "✅ Skills updated successfully.";
            }
        }
    }
}

// Get available years
$years = [];
try {
    $years = $db->fetchAll("SELECT * FROM skill_matrix_years ORDER BY year DESC");
} catch (Exception $e) {
    $message = "Error loading years: " . $e->getMessage();
}

// Categories already loaded above

// Get existing skills for selected year across all categories
$existing_skills_by_category = [];
if (isset($_GET['year_id'])) {
    $year_id = intval($_GET['year_id']);

    try {
        $all_existing_skills = $db->fetchAll("SELECT * FROM skill_matrix_skills WHERE year_id = ? ORDER BY category_id, display_order", [$year_id]);

        // Debug: Log what we found
        error_log("Skills loaded for year_id $year_id: " . count($all_existing_skills) . " skills found");

        // Group skills by category
        foreach ($all_existing_skills as $skill) {
            $existing_skills_by_category[$skill['category_id']][] = $skill;
            error_log("Loaded skill: category_id={$skill['category_id']}, name={$skill['skill_name']}, display_order={$skill['display_order']}");
        }
    } catch (Exception $e) {
        $message = "Error loading existing skills: " . $e->getMessage();
        error_log("Error loading skills: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Skill Matrix Setup</title>
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

        .setup-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .setup-card .card-header {
            border-bottom: none;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0 !important;
        }

        .setup-card .card-body {
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

        .skill-category-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            margin-top: 2rem;
        }

        .skill-input-group {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            transition: all 0.3s ease;
            position: relative;
        }

        .skill-input-group:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.1);
        }

        .skill-number {
            position: absolute;
            top: -10px;
            left: 20px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
        }

        .alert {
            border-radius: 12px;
            border: none;
        }

        .existing-years {
            background: linear-gradient(135deg, #e8f5e8, #ffffff);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
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
                        <h1><i class="fas fa-cogs me-3"></i>Skill Matrix Setup</h1>
                        <p>Configure skill categories, assessment criteria, and manage skill matrix forms</p>
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
                        <li class="breadcrumb-item active">Setup</li>
                    </ol>
                </nav>

                <?php if (!empty($message)): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Step 1: Add Year -->
                <div class="setup-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <span class="step-number">1</span>
                            <i class="fas fa-calendar me-2"></i>Add Assessment Year
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="year" class="form-label">Assessment Year</label>
                                    <select class="form-select" name="year" required>
                                        <option value="">Select Year</option>
                                        <?php
                                        $currentYear = date('Y');
                                        for ($year = $currentYear - 1; $year <= $currentYear + 5; $year++) {
                                            echo "<option value='$year'>$year</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="submit" name="add_year" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Add Year
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php if (!empty($years)): ?>
                        <div class="existing-years">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <h6 class="mb-0 text-success">Existing Assessment Years</h6>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($years as $year): ?>
                                <span class="badge bg-success fs-6">
                                    <i class="fas fa-calendar-alt me-1"></i><?php echo $year['year']; ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 2: Setup Skills for Categories -->
                <?php if (!empty($years) && !empty($categories)): ?>
                <div class="setup-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <span class="step-number">2</span>
                            <i class="fas fa-cogs me-2"></i>Setup Skills for All Categories
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="year_id" class="form-label">Select Year</label>
                                    <select class="form-select" name="year_id" required>
                                        <option value="">Choose Year</option>
                                        <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year['id']; ?>" <?php echo (isset($_GET['year_id']) && $_GET['year_id'] == $year['id']) ? 'selected' : ''; ?>>
                                            <?php echo $year['year']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search me-2"></i>Load Skills Form
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php if (isset($_GET['year_id'])): ?>
                        <hr>

                        <!-- Debug Section (temporary) -->
                        <div class="alert alert-info mb-3">
                            <strong>Debug - Loaded Skills:</strong><br>
                            <?php
                            $total_loaded = 0;
                            foreach ($categories as $cat) {
                                $cat_skills = $existing_skills_by_category[$cat['id']] ?? [];
                                $count = count($cat_skills);
                                $total_loaded += $count;
                                echo htmlspecialchars($cat['name']) . ": $count skills<br>";
                            }
                            echo "<strong>Total loaded: $total_loaded skills</strong>";
                            ?>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="year_id" value="<?php echo intval($_GET['year_id']); ?>">

                            <?php foreach ($categories as $category): ?>
                            <div class="mb-5">
                                <div class="skill-category-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-layer-group me-2"></i><?php echo htmlspecialchars($category['name']); ?>
                                        <small class="opacity-75 ms-2">(5 skills per category)</small>
                                    </h5>
                                </div>

                                <?php
                                $existing_skills_for_category = $existing_skills_by_category[$category['id']] ?? [];
                                ?>

                                <?php for ($i = 1; $i <= 5; $i++):
                                    // Get existing skill for this position
                                    $existing_skill = null;
                                    foreach ($existing_skills_for_category as $skill) {
                                        if ($skill['display_order'] == $i) {
                                            $existing_skill = $skill;
                                            break;
                                        }
                                    }
                                ?>
                                <div class="skill-input-group">
                                    <div class="skill-number"><?php echo $i; ?></div>
                                    <h6 class="text-primary mb-3">Skill <?php echo $i; ?></h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="category_<?php echo $category['id']; ?>_skill_<?php echo $i; ?>_name" class="form-label">Skill Name</label>
                                            <input type="text" class="form-control" name="category_<?php echo $category['id']; ?>_skill_<?php echo $i; ?>_name"
                                                   placeholder="Enter skill name"
                                                   value="<?php echo htmlspecialchars($existing_skill['skill_name'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="category_<?php echo $category['id']; ?>_skill_<?php echo $i; ?>_description" class="form-label">Description (Optional)</label>
                                            <textarea class="form-control" name="category_<?php echo $category['id']; ?>_skill_<?php echo $i; ?>_description" rows="2"
                                                      placeholder="Brief description of the skill"><?php echo htmlspecialchars($existing_skill['skill_description'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <?php endforeach; ?>

                            <div class="text-center">
                                <button type="submit" name="save_skills" class="btn btn-success btn-lg">
                                    <i class="fas fa-save me-2"></i>Save All Skills
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Navigation -->
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary btn-custom">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                    <div>
                        <a href="test_tables.php" class="btn btn-info btn-custom me-2">
                            <i class="fas fa-database me-2"></i>Test Tables
                        </a>
                        <?php if (!empty($years)): ?>
                        <a href="assessment_working.php" class="btn btn-primary btn-custom">
                            <i class="fas fa-clipboard-check me-2"></i>Start Assessment
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>