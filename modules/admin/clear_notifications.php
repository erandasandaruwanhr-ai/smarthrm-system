<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/notification_system.php';

if (!isSuperAdmin()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();
$success = null;
$error = null;

if ($_POST) {
    $notificationSystem = getNotificationSystem();

    if (isset($_POST['clear_old'])) {
        $hours = intval($_POST['hours']) ?: 48;
        try {
            $result = $notificationSystem->cleanOldNotifications($hours);
            if ($result) {
                $success = "Successfully cleared notifications older than $hours hours.";
            } else {
                $error = "Failed to clear old notifications.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }

    if (isset($_POST['clear_all'])) {
        try {
            // Clear all notifications by marking them as inactive
            $db = new Database();
            $db->query("UPDATE notifications SET is_active = 0");
            $success = "Successfully cleared all notifications.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get notification statistics
$db = new Database();
$stats = [];
try {
    $stats['total'] = $db->fetch("SELECT COUNT(*) as count FROM notifications WHERE is_active = 1")['count'] ?? 0;
    $stats['last_24h'] = $db->fetch("SELECT COUNT(*) as count FROM notifications WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['count'] ?? 0;
    $stats['last_4h'] = $db->fetch("SELECT COUNT(*) as count FROM notifications WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 4 HOUR)")['count'] ?? 0;
    $stats['older_48h'] = $db->fetch("SELECT COUNT(*) as count FROM notifications WHERE is_active = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)")['count'] ?? 0;
} catch (Exception $e) {
    $stats = ['total' => 0, 'last_24h' => 0, 'last_4h' => 0, 'older_48h' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Clear Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0 ms-2">Clear Notifications</h5>
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

        <div class="dashboard-content">
            <nav style="--bs-breadcrumb-divider: '>';" aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Admin Panel</a></li>
                    <li class="breadcrumb-item active">Clear Notifications</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2"><i class="fas fa-bell-slash me-2"></i>Clear Notifications</h1>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Admin
                </a>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Notification Statistics -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <i class="fas fa-bell fa-2x text-primary mb-2"></i>
                            <h3 class="text-primary"><?php echo $stats['total']; ?></h3>
                            <p class="text-muted mb-0">Total Active Notifications</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-2x text-success mb-2"></i>
                            <h3 class="text-success"><?php echo $stats['last_4h']; ?></h3>
                            <p class="text-muted mb-0">Last 4 Hours (Recent)</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar fa-2x text-warning mb-2"></i>
                            <h3 class="text-warning"><?php echo $stats['last_24h']; ?></h3>
                            <p class="text-muted mb-0">Last 24 Hours</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-danger">
                        <div class="card-body text-center">
                            <i class="fas fa-trash fa-2x text-danger mb-2"></i>
                            <h3 class="text-danger"><?php echo $stats['older_48h']; ?></h3>
                            <p class="text-muted mb-0">Older than 48 Hours</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clear Options -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-broom me-2"></i>Clear Old Notifications</h5>
                        </div>
                        <div class="card-body">
                            <p>Remove notifications older than specified hours to keep the system clean.</p>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="hours" class="form-label">Clear notifications older than:</label>
                                    <select class="form-select" id="hours" name="hours">
                                        <option value="4">4 hours</option>
                                        <option value="12">12 hours</option>
                                        <option value="24">24 hours</option>
                                        <option value="48" selected>48 hours (2 days)</option>
                                        <option value="72">72 hours (3 days)</option>
                                        <option value="168">1 week</option>
                                    </select>
                                </div>
                                <button type="submit" name="clear_old" class="btn btn-warning">
                                    <i class="fas fa-broom me-2"></i>Clear Old Notifications
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Clear All Notifications</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-danger"><strong>Warning:</strong> This will clear ALL notifications in the system. This action cannot be undone.</p>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to clear ALL notifications? This cannot be undone.')">
                                <button type="submit" name="clear_all" class="btn btn-danger">
                                    <i class="fas fa-trash-alt me-2"></i>Clear All Notifications
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Information -->
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle me-2"></i>Information</h6>
                <ul class="mb-0">
                    <li><strong>Recent Activities:</strong> Dashboard shows notifications from the last 4 hours only.</li>
                    <li><strong>Auto Cleanup:</strong> The system automatically marks notifications older than 48 hours as inactive when the dashboard loads.</li>
                    <li><strong>Performance:</strong> Regularly clearing old notifications helps maintain system performance.</li>
                    <li><strong>Data Retention:</strong> Cleared notifications are marked as inactive but not permanently deleted from the database.</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>