<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has system config permissions using new role-based system
requirePermission('admin', 'system_config');

$user = getCurrentUser();
$db = new Database();

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_colors':
                try {
                    // Update color settings
                    $primary_color = $_POST['primary_color'];
                    $secondary_color = $_POST['secondary_color'];
                    $success_color = $_POST['success_color'];
                    $danger_color = $_POST['danger_color'];
                    $warning_color = $_POST['warning_color'];
                    $info_color = $_POST['info_color'];

                    $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                               ['primary_color', $primary_color]);
                    $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                               ['secondary_color', $secondary_color]);
                    $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                               ['success_color', $success_color]);
                    $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                               ['danger_color', $danger_color]);
                    $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                               ['warning_color', $warning_color]);
                    $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                               ['info_color', $info_color]);

                    $message = 'Color settings updated successfully!';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $message = 'Error updating color settings: ' . $e->getMessage();
                    $message_type = 'danger';
                }
                break;

            case 'update_calendar':
                try {
                    // Update calendar settings
                    $timezone = $_POST['timezone'];
                    $date_format = $_POST['date_format'];
                    $time_format = $_POST['time_format'];
                    $first_day_of_week = $_POST['first_day_of_week'];

                    $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                               ['timezone', $timezone]);
                    $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                               ['date_format', $date_format]);
                    $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                               ['time_format', $time_format]);
                    $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                               ['first_day_of_week', $first_day_of_week]);

                    $message = 'Calendar settings updated successfully!';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $message = 'Error updating calendar settings: ' . $e->getMessage();
                    $message_type = 'danger';
                }
                break;
        }
    }
}

// Get current settings
$color_settings = [
    'primary_color' => $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'primary_color'")['setting_value'] ?? '#007bff',
    'secondary_color' => $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'secondary_color'")['setting_value'] ?? '#6c757d',
    'success_color' => $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'success_color'")['setting_value'] ?? '#28a745',
    'danger_color' => $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'danger_color'")['setting_value'] ?? '#dc3545',
    'warning_color' => $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'warning_color'")['setting_value'] ?? '#ffc107',
    'info_color' => $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'info_color'")['setting_value'] ?? '#17a2b8'
];

$calendar_settings = [
    'timezone' => $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'timezone'")['setting_value'] ?? 'Asia/Colombo',
    'date_format' => $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'date_format'")['setting_value'] ?? 'Y-m-d',
    'time_format' => $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'time_format'")['setting_value'] ?? 'H:i',
    'first_day_of_week' => $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'first_day_of_week'")['setting_value'] ?? '1'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - System Configuration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $color_settings['primary_color']; ?>;
            --secondary-color: <?php echo $color_settings['secondary_color']; ?>;
            --success-color: <?php echo $color_settings['success_color']; ?>;
            --danger-color: <?php echo $color_settings['danger_color']; ?>;
            --warning-color: <?php echo $color_settings['warning_color']; ?>;
            --info-color: <?php echo $color_settings['info_color']; ?>;
            --sidebar-width: 280px;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, #0056b3 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .sidebar-item {
            margin: 0.5rem 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: white;
        }

        .sidebar-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1rem;
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

        .dashboard-content {
            padding: 2rem;
        }

        .config-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 5px;
            border: 2px solid #ddd;
            margin-left: 10px;
            display: inline-block;
            vertical-align: middle;
        }

        .scroll-container {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }

        .scroll-container::-webkit-scrollbar {
            width: 6px;
        }

        .scroll-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .scroll-container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

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
                <h5 class="mb-0 ms-2">System Configuration</h5>
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

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h2"><i class="fas fa-sliders-h me-2"></i>System Configuration</h1>
                    <p class="text-muted">Configure system-wide settings for colors and calendar</p>

                    <!-- Back Button -->
                    <div class="mb-3">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Admin Panel
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Color Management Section -->
            <div class="config-section">
                <h4 class="mb-4"><i class="fas fa-palette me-2"></i>Color Management</h4>
                <p class="text-muted mb-4">Configure the main color scheme for the entire system</p>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_colors">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="primary_color" class="form-label">Primary Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="primary_color" name="primary_color"
                                       value="<?php echo htmlspecialchars($color_settings['primary_color']); ?>" title="Choose primary color">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($color_settings['primary_color']); ?>" readonly>
                            </div>
                            <small class="text-muted">Used for buttons, links, and main branding elements</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="secondary_color" class="form-label">Secondary Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="secondary_color" name="secondary_color"
                                       value="<?php echo htmlspecialchars($color_settings['secondary_color']); ?>" title="Choose secondary color">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($color_settings['secondary_color']); ?>" readonly>
                            </div>
                            <small class="text-muted">Used for supporting elements and text</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="success_color" class="form-label">Success Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="success_color" name="success_color"
                                       value="<?php echo htmlspecialchars($color_settings['success_color']); ?>" title="Choose success color">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($color_settings['success_color']); ?>" readonly>
                            </div>
                            <small class="text-muted">Used for positive actions and success messages</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="danger_color" class="form-label">Danger Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="danger_color" name="danger_color"
                                       value="<?php echo htmlspecialchars($color_settings['danger_color']); ?>" title="Choose danger color">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($color_settings['danger_color']); ?>" readonly>
                            </div>
                            <small class="text-muted">Used for error messages and delete actions</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="warning_color" class="form-label">Warning Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="warning_color" name="warning_color"
                                       value="<?php echo htmlspecialchars($color_settings['warning_color']); ?>" title="Choose warning color">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($color_settings['warning_color']); ?>" readonly>
                            </div>
                            <small class="text-muted">Used for warnings and pending states</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="info_color" class="form-label">Info Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="info_color" name="info_color"
                                       value="<?php echo htmlspecialchars($color_settings['info_color']); ?>" title="Choose info color">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($color_settings['info_color']); ?>" readonly>
                            </div>
                            <small class="text-muted">Used for informational elements</small>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Color Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Calendar Setup Section -->
            <div class="config-section">
                <h4 class="mb-4"><i class="fas fa-calendar-alt me-2"></i>Calendar Setup</h4>
                <p class="text-muted mb-4">Configure date and time settings for the system</p>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_calendar">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone" required>
                                <option value="Asia/Colombo" <?php echo $calendar_settings['timezone'] == 'Asia/Colombo' ? 'selected' : ''; ?>>Asia/Colombo (Sri Lanka)</option>
                                <option value="Asia/Dhaka" <?php echo $calendar_settings['timezone'] == 'Asia/Dhaka' ? 'selected' : ''; ?>>Asia/Dhaka</option>
                                <option value="Asia/Karachi" <?php echo $calendar_settings['timezone'] == 'Asia/Karachi' ? 'selected' : ''; ?>>Asia/Karachi</option>
                                <option value="Asia/Kolkata" <?php echo $calendar_settings['timezone'] == 'Asia/Kolkata' ? 'selected' : ''; ?>>Asia/Kolkata</option>
                                <option value="UTC" <?php echo $calendar_settings['timezone'] == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="date_format" class="form-label">Date Format</label>
                            <select class="form-select" id="date_format" name="date_format" required>
                                <option value="Y-m-d" <?php echo $calendar_settings['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (<?php echo date('Y-m-d'); ?>)</option>
                                <option value="d/m/Y" <?php echo $calendar_settings['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (<?php echo date('d/m/Y'); ?>)</option>
                                <option value="m/d/Y" <?php echo $calendar_settings['date_format'] == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (<?php echo date('m/d/Y'); ?>)</option>
                                <option value="d-M-Y" <?php echo $calendar_settings['date_format'] == 'd-M-Y' ? 'selected' : ''; ?>>DD-MON-YYYY (<?php echo date('d-M-Y'); ?>)</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="time_format" class="form-label">Time Format</label>
                            <select class="form-select" id="time_format" name="time_format" required>
                                <option value="H:i" <?php echo $calendar_settings['time_format'] == 'H:i' ? 'selected' : ''; ?>>24 Hour (<?php echo date('H:i'); ?>)</option>
                                <option value="h:i A" <?php echo $calendar_settings['time_format'] == 'h:i A' ? 'selected' : ''; ?>>12 Hour (<?php echo date('h:i A'); ?>)</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="first_day_of_week" class="form-label">First Day of Week</label>
                            <select class="form-select" id="first_day_of_week" name="first_day_of_week" required>
                                <option value="0" <?php echo $calendar_settings['first_day_of_week'] == '0' ? 'selected' : ''; ?>>Sunday</option>
                                <option value="1" <?php echo $calendar_settings['first_day_of_week'] == '1' ? 'selected' : ''; ?>>Monday</option>
                            </select>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Calendar Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Auto-hide sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.querySelector('.sidebar');
                const toggle = document.getElementById('sidebar-toggle');

                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // Update text input when color input changes
        document.querySelectorAll('input[type="color"]').forEach(colorInput => {
            const textInput = colorInput.nextElementSibling;
            colorInput.addEventListener('change', function() {
                textInput.value = this.value;
            });
        });
    </script>
</body>
</html>