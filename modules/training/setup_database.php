<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();

// Only superadmin can setup database
if (!isSuperAdmin()) {
    header('Location: ../../dashboard.php');
    exit;
}

$message = '';
$error = '';

if ($_POST && isset($_POST['setup_database'])) {
    try {
        $db = new Database();

        // Read and execute the SQL schema file
        $sql_content = file_get_contents(__DIR__ . '/database_schema.sql');

        if ($sql_content === false) {
            throw new Exception('Could not read database schema file');
        }

        // Split SQL into individual statements
        $statements = array_filter(
            array_map('trim', preg_split('/;\s*$/m', $sql_content)),
            function($stmt) {
                return !empty($stmt) &&
                       !preg_match('/^--/', $stmt) &&
                       !preg_match('/^\/\*/', $stmt);
            }
        );

        $executed = 0;
        $connection = $db->getConnection();

        foreach ($statements as $statement) {
            if (trim($statement)) {
                try {
                    $connection->exec($statement);
                    $executed++;
                } catch (PDOException $e) {
                    // If table already exists, continue
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        throw $e;
                    }
                }
            }
        }

        $message = "Training database setup completed successfully! Executed $executed SQL statements.";

    } catch (Exception $e) {
        $error = "Database setup failed: " . $e->getMessage();
    }
}

// Check if tables exist
$db = new Database();
$tables_status = [];
$required_tables = [
    'training_requirements',
    'training_budget',
    'training_plans',
    'training_evaluations',
    'training_feedback',
    'training_managerial_comments',
    'training_tracker',
    'training_types',
    'training_institutes',
    'training_documents'
];

foreach ($required_tables as $table) {
    try {
        $result = $db->query("SELECT COUNT(*) FROM $table LIMIT 1");
        $tables_status[$table] = true;
    } catch (Exception $e) {
        $tables_status[$table] = false;
    }
}

$all_tables_exist = !in_array(false, $tables_status);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Database Setup - SmartHRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --sidebar-width: 280px;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .dashboard-content {
            padding: 2rem;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .setup-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .table-status {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
        }

        .status-icon {
            width: 20px;
            margin-right: 10px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            .dashboard-content {
                padding: 1rem;
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
                        <h1><i class="fas fa-database me-3"></i>Training Database Setup</h1>
                        <p class="mb-0">Initialize training module database tables and master data</p>
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Training Management</a></li>
                    <li class="breadcrumb-item active">Database Setup</li>
                </ol>
            </nav>

            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Database Status -->
            <div class="setup-card">
                <h4 class="mb-4"><i class="fas fa-table me-2"></i>Database Tables Status</h4>
                <div class="row">
                    <?php foreach ($required_tables as $table): ?>
                    <div class="col-md-6">
                        <div class="table-status">
                            <div class="status-icon">
                                <?php if ($tables_status[$table]): ?>
                                    <i class="fas fa-check-circle text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger"></i>
                                <?php endif; ?>
                            </div>
                            <span class="<?php echo $tables_status[$table] ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $table; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($all_tables_exist): ?>
                    <div class="alert alert-success mt-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>All tables are properly configured!</strong> The training module database is ready to use.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mt-4" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Database setup required!</strong> Some tables are missing and need to be created.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Setup Actions -->
            <div class="setup-card">
                <h4 class="mb-4"><i class="fas fa-cogs me-2"></i>Database Setup Actions</h4>

                <?php if (!$all_tables_exist): ?>
                <form method="POST" class="mb-4">
                    <div class="alert alert-info" role="alert">
                        <h6 class="alert-heading">Setup Training Database</h6>
                        <p class="mb-0">This will create all required tables and insert master data for the training module.</p>
                    </div>

                    <button type="submit" name="setup_database" class="btn btn-primary btn-lg">
                        <i class="fas fa-database me-2"></i>Setup Training Database
                    </button>
                </form>
                <?php endif; ?>

                <div class="mt-4">
                    <h6>Database Schema Overview:</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-table text-primary me-2"></i><strong>training_requirements</strong> - Training needs and requirements</li>
                        <li><i class="fas fa-table text-primary me-2"></i><strong>training_budget</strong> - Budget planning and approval</li>
                        <li><i class="fas fa-table text-primary me-2"></i><strong>training_plans</strong> - Training schedules and plans</li>
                        <li><i class="fas fa-table text-primary me-2"></i><strong>training_evaluations</strong> - Trainee feedback and evaluation</li>
                        <li><i class="fas fa-table text-primary me-2"></i><strong>training_feedback</strong> - Supervisor effectiveness feedback</li>
                        <li><i class="fas fa-table text-primary me-2"></i><strong>training_managerial_comments</strong> - Management review and action plans</li>
                        <li><i class="fas fa-table text-primary me-2"></i><strong>training_tracker</strong> - Training progress and status tracking</li>
                        <li><i class="fas fa-table text-secondary me-2"></i><strong>training_types</strong> - Master data for training types</li>
                        <li><i class="fas fa-table text-secondary me-2"></i><strong>training_institutes</strong> - Training provider information</li>
                        <li><i class="fas fa-table text-secondary me-2"></i><strong>training_documents</strong> - Document and certificate storage</li>
                    </ul>
                </div>

                <div class="mt-4">
                    <a href="index.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-arrow-left me-2"></i>Back to Training Module
                    </a>
                    <a href="database_schema.sql" target="_blank" class="btn btn-outline-secondary">
                        <i class="fas fa-file-code me-2"></i>View SQL Schema
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>