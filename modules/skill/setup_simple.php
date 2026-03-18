<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
            $message = "✅ Year $year added successfully.";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $message = "⚠️ Year $year already exists.";
            } else {
                $message = "❌ Error adding year: " . $e->getMessage();
            }
        }
    }
}

// Get available years
$years = [];
try {
    $result = $db->query("SELECT * FROM skill_matrix_years ORDER BY year DESC");
    while ($row = $result->fetch_assoc()) {
        $years[] = $row;
    }
} catch (Exception $e) {
    $message = "Error loading years: " . $e->getMessage();
}

// Get skill categories
$categories = [];
try {
    $result = $db->query("SELECT * FROM skill_matrix_categories ORDER BY display_order");
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
} catch (Exception $e) {
    $message = "Error loading categories: " . $e->getMessage();
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
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }

        .setup-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4"><i class="fas fa-cogs me-2"></i>Skill Matrix Setup</h1>

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

        <!-- Add Year -->
        <div class="setup-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Add Assessment Year</h5>
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

        <!-- Show Categories -->
        <?php if (!empty($categories)): ?>
        <div class="setup-card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Skill Categories</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($categories as $category): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title text-primary"><?php echo htmlspecialchars($category['name']); ?></h6>
                                <p class="card-text"><small class="text-muted">Order: <?php echo $category['display_order']; ?></small></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>