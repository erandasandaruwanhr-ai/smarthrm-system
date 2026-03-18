<?php
/*
 * SmartHRM Module Template
 * Use this template for consistent module design
 * Replace MODULE_NAME, MODULE_ICON, and content as needed
 */

require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$module_name = "MODULE_NAME"; // Replace with actual module name
$module_icon = "fas fa-icon"; // Replace with actual icon
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - <?php echo $module_name; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include '../../includes/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1><i class="<?php echo $module_icon; ?> me-2"></i><?php echo $module_name; ?></h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active"><?php echo $module_name; ?></li>
                                </ol>
                            </nav>
                        </div>
                        <div class="action-buttons">
                            <!-- Add action buttons here -->
                            <a href="../../dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards (if needed) -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="icon bg-primary-light text-primary">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3>0</h3>
                            <p>Statistic 1</p>
                        </div>
                    </div>
                    <!-- Add more stats cards as needed -->
                </div>

                <!-- Module Content -->
                <div class="row g-4">
                    <!-- Replace this with actual module content -->
                    <div class="col-lg-4 col-md-6">
                        <div class="module-card">
                            <div class="module-icon">
                                <i class="fas fa-plus"></i>
                            </div>
                            <h5>Feature 1</h5>
                            <p>Description of feature 1</p>
                            <a href="#" class="btn btn-primary mt-2">Action</a>
                        </div>
                    </div>
                    <!-- Add more feature cards as needed -->
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>