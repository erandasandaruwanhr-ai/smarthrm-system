<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user is admin
if (!isAdmin() && !isSuperAdmin()) {
    header("Location: ../../auth/login.php");
    exit();
}

$user = getCurrentUser();
$db = new Database();
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_year'])) {
        $year = intval($_POST['year']);

        try {
            $stmt = $db->prepare("INSERT INTO skill_matrix_years (year) VALUES (?)");
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $message = "Year $year added successfully.";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $message = "Year $year already exists.";
            } else {
                $message = "Error adding year: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_skills'])) {
        $year_id = intval($_POST['year_id']);
        $category_id = intval($_POST['category_id']);

        for ($i = 1; $i <= 5; $i++) {
            $skill_name = trim($_POST["skill_{$i}_name"]);
            $skill_description = trim($_POST["skill_{$i}_description"]);

            if (!empty($skill_name)) {
                try {
                    $stmt = $db->prepare("INSERT INTO skill_matrix_skills (year_id, category_id, skill_name, skill_description, display_order) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissi", $year_id, $category_id, $skill_name, $skill_description, $i);
                    $stmt->execute();
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        $message = "Error adding skill: " . $e->getMessage();
                        break;
                    }
                }
            }
        }

        if (empty($message)) {
            $message = "Skills added successfully.";
        }
    }
}

// Get available years
$years = [];
$result = $db->query("SELECT * FROM skill_matrix_years ORDER BY year DESC");
while ($row = $result->fetch_assoc()) {
    $years[] = $row;
}

// Get skill categories
$categories = [];
$result = $db->query("SELECT * FROM skill_matrix_categories ORDER BY display_order");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Get existing skills for selected year and category
$existing_skills = [];
if (isset($_GET['year_id']) && isset($_GET['category_id'])) {
    $year_id = intval($_GET['year_id']);
    $category_id = intval($_GET['category_id']);

    $stmt = $db->prepare("SELECT * FROM skill_matrix_skills WHERE year_id = ? AND category_id = ? ORDER BY display_order");
    $stmt->bind_param("ii", $year_id, $category_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $existing_skills[] = $row;
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

        .setup-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .skill-input-group {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #f8f9fa;
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
                <h5 class="mb-0 ms-2">Skill Matrix Setup</h5>
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
                        <li class="breadcrumb-item active">Setup</li>
                    </ol>
                </nav>

                <?php if (!empty($message)): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Step 1: Add Year -->
                <div class="setup-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Step 1: Add Assessment Year</h5>
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
                        <div class="mt-3">
                            <h6>Existing Years:</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($years as $year): ?>
                                <span class="badge bg-success fs-6"><?php echo $year['year']; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 2: Setup Skills for Categories -->
                <?php if (!empty($years)): ?>
                <div class="setup-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Step 2: Setup Skills for Categories</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="mb-4">
                            <div class="row">
                                <div class="col-md-4">
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
                                <div class="col-md-4">
                                    <label for="category_id" class="form-label">Select Category</label>
                                    <select class="form-select" name="category_id" required>
                                        <option value="">Choose Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo (isset($_GET['category_id']) && $_GET['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search me-2"></i>Load Skills
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php if (isset($_GET['year_id']) && isset($_GET['category_id'])): ?>
                        <hr>
                        <h6>Add Skills for: <?php echo htmlspecialchars($categories[array_search($_GET['category_id'], array_column($categories, 'id'))]['name']); ?></h6>

                        <?php if (!empty($existing_skills)): ?>
                        <div class="alert alert-warning">
                            <strong>Existing Skills:</strong>
                            <ul class="mb-0">
                                <?php foreach ($existing_skills as $skill): ?>
                                <li><?php echo htmlspecialchars($skill['skill_name']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="year_id" value="<?php echo intval($_GET['year_id']); ?>">
                            <input type="hidden" name="category_id" value="<?php echo intval($_GET['category_id']); ?>">

                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <div class="skill-input-group">
                                <h6 class="text-primary">Skill <?php echo $i; ?></h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="skill_<?php echo $i; ?>_name" class="form-label">Skill Name</label>
                                        <input type="text" class="form-control" name="skill_<?php echo $i; ?>_name"
                                               placeholder="Enter skill name">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="skill_<?php echo $i; ?>_description" class="form-label">Description (Optional)</label>
                                        <textarea class="form-control" name="skill_<?php echo $i; ?>_description" rows="2"
                                                  placeholder="Brief description of the skill"></textarea>
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>

                            <div class="text-center">
                                <button type="submit" name="add_skills" class="btn btn-success btn-lg">
                                    <i class="fas fa-save me-2"></i>Save Skills
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Navigation -->
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                    <?php if (!empty($years)): ?>
                    <a href="assessment_form.php" class="btn btn-primary">
                        <i class="fas fa-clipboard-check me-2"></i>Start Assessment
                    </a>
                    <?php endif; ?>
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
    </script>
</body>
</html>