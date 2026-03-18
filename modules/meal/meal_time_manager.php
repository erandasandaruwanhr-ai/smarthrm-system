<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to access meal time manager
$db = new Database();
if (!hasModulePermission($db, 'meal.meal_time_manager') && !hasAdminAccess()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();

$message = '';
$message_type = '';

// Create meal time settings table if it doesn't exist
try {
    $create_table = "CREATE TABLE IF NOT EXISTS meal_time_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_type ENUM('employee', 'visitor') NOT NULL,
        meal_name VARCHAR(50) NOT NULL,
        order_deadline_time TIME NOT NULL,
        order_deadline_date_offset INT DEFAULT 0 COMMENT 'Days before meal date',
        is_active TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_setting (setting_type, meal_name)
    )";
    $db->query($create_table);

    // Insert default settings if table is empty
    $count_query = "SELECT COUNT(*) as count FROM meal_time_settings";
    $count_result = $db->fetch($count_query);

    if ($count_result && $count_result['count'] == 0) {
        $default_settings = [
            // Employee meal deadlines - all same day
            ['employee', 'breakfast', '06:00:00', 0],
            ['employee', 'snack1', '09:00:00', 0],
            ['employee', 'lunch', '11:00:00', 0],
            ['employee', 'snack2', '14:00:00', 0],
            ['employee', 'dinner', '16:00:00', 0],
            ['employee', 'snack3', '18:00:00', 0],

            // Visitor meal deadlines - 1 day before
            ['visitor', 'breakfast', '16:00:00', 1],
            ['visitor', 'lunch', '16:00:00', 1],
            ['visitor', 'dinner', '16:00:00', 1],
            ['visitor', 'snack1', '16:00:00', 1],
            ['visitor', 'snack2', '16:00:00', 1]
        ];

        foreach ($default_settings as $setting) {
            $insert_query = "INSERT INTO meal_time_settings (setting_type, meal_name, order_deadline_time, order_deadline_date_offset) VALUES (?, ?, ?, ?)";
            $db->query($insert_query, $setting);
        }

        $message = 'Default settings loaded successfully!';
        $message_type = 'success';
    }
} catch (Exception $e) {
    // Table creation failed, handle gracefully
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    try {
        $updated = 0;

        // Process each setting
        if (isset($_POST['settings']) && is_array($_POST['settings'])) {
            foreach ($_POST['settings'] as $id => $data) {
                // Sanitize inputs
                $id = (int)$id;
                $time = $data['time'] ?? '';
                $offset = (int)($data['offset'] ?? 0);
                $active = isset($data['active']) ? 1 : 0;

                // Basic validation
                if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time)) {
                    continue; // Skip invalid time
                }

                // Update the setting
                $update_query = "UPDATE meal_time_settings SET
                                 order_deadline_time = ?,
                                 order_deadline_date_offset = ?,
                                 is_active = ?
                                 WHERE id = ?";

                $db->query($update_query, [$time, $offset, $active, $id]);
                $updated++;
            }
        }

        $message = "Settings updated successfully! ($updated items updated)";
        $message_type = 'success';

    } catch (Exception $e) {
        $message = 'Error updating settings: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get current settings
try {
    $settings_query = "SELECT * FROM meal_time_settings
                       ORDER BY setting_type,
                       CASE meal_name
                           WHEN 'breakfast' THEN 1
                           WHEN 'snack1' THEN 2
                           WHEN 'lunch' THEN 3
                           WHEN 'snack2' THEN 4
                           WHEN 'dinner' THEN 5
                           WHEN 'snack3' THEN 6
                           ELSE 99
                       END";
    $all_settings = $db->fetchAll($settings_query);

    // Separate employee and visitor settings
    $employee_settings = [];
    $visitor_settings = [];

    foreach ($all_settings as $setting) {
        if ($setting['setting_type'] === 'employee') {
            $employee_settings[] = $setting;
        } else {
            $visitor_settings[] = $setting;
        }
    }

} catch (Exception $e) {
    $employee_settings = [];
    $visitor_settings = [];
}

// Current time for status display
$current_time = date('H:i:s');
$current_date = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Meal Time Manager</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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

        .form-card, .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .card-header-modern {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 1px solid #dee2e6;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem 2rem;
            margin: -2rem -2rem 2rem -2rem;
        }

        .card-header-modern h5 {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
        }

        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
        }

        /* Enhanced Time Manager Styles */
        .time-manager-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .time-section {
            background: white;
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .time-section:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .section-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem 2rem;
            margin: 0;
            border-radius: 0;
        }

        .section-header.employee {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }

        .section-header.visitor {
            background: linear-gradient(135deg, #e91e63, #c2185b);
        }

        .section-header h5 {
            color: white !important;
            font-weight: 600;
            margin: 0;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        .meal-time-card {
            background: white;
            border: none;
            border-radius: 0;
            margin: 0;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f0f2f5;
            transition: all 0.3s ease;
            position: relative;
        }

        .meal-time-card:last-child {
            border-bottom: none;
        }

        .meal-time-card:hover {
            background: #f8f9fa;
        }

        .meal-time-card.inactive {
            background: #f8f9fa;
            opacity: 0.7;
        }

        .meal-time-card.inactive::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #6c757d;
        }

        .meal-time-card.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #28a745;
        }

        .meal-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .meal-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
            color: white;
        }

        .meal-icon.breakfast {
            background: linear-gradient(135deg, #ff6b35, #f9a825);
        }

        .meal-icon.lunch {
            background: linear-gradient(135deg, #28a745, #66bb6a);
        }

        .meal-icon.dinner {
            background: linear-gradient(135deg, #6f42c1, #9c27b0);
        }

        .meal-icon.snack1,
        .meal-icon.snack2,
        .meal-icon.snack3 {
            background: linear-gradient(135deg, #e91e63, #f48fb1);
        }

        .meal-title {
            flex: 1;
        }

        .meal-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .meal-description {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .meal-toggle {
            transform: scale(1.3);
        }

        .time-controls {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .time-input-group {
            position: relative;
        }

        .time-input-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .enhanced-time-input {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .enhanced-time-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none;
        }

        .enhanced-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            font-weight: 500;
            background: white;
            transition: all 0.3s ease;
        }

        .enhanced-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none;
        }

        .deadline-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-top: 1rem;
        }

        .deadline-text {
            font-size: 0.9rem;
            color: #495057;
            margin: 0;
        }

        .status-indicator {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-open {
            background: #d4edda;
            color: #155724;
        }

        .status-closed {
            background: #f8d7da;
            color: #721c24;
        }

        .status-disabled {
            background: #e2e3e5;
            color: #6c757d;
        }

        .current-time-display {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .current-time-display::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: rotate(45deg);
        }

        .current-time {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .current-date {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }

        .save-controls {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .btn-save {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 1rem 3rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-save:hover {
            background: linear-gradient(135deg, #20c997, #28a745);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
            color: white;
        }

        .btn-cancel {
            background: transparent;
            border: 2px solid #6c757d;
            color: #6c757d;
            padding: 1rem 3rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-2px);
        }

        .info-panel {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .info-panel::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .info-panel h5 {
            color: white;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .info-panel ul {
            position: relative;
            z-index: 2;
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

            .time-manager-container {
                grid-template-columns: 1fr;
            }

            .time-controls {
                grid-template-columns: 1fr;
            }

            .current-time {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .section-header,
            .meal-time-card {
                padding: 1rem 1.5rem;
            }

            .meal-header {
                flex-direction: column;
                text-align: center;
            }

            .meal-icon {
                margin-right: 0;
                margin-bottom: 1rem;
            }
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
                        <h1><i class="fas fa-clock me-3"></i>Meal Time Manager</h1>
                        <p>Configure meal time slots and dining schedules</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Meal Management</a></li>
                    <li class="breadcrumb-item active">Meal Time Manager</li>
                </ol>
            </nav>

        <!-- Alert Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

            <!-- Current Time Display -->
            <div class="current-time-display">
                <div class="current-time" id="currentTime"><?php echo date('H:i:s'); ?></div>
                <div class="current-date"><?php echo date('F j, Y - l'); ?></div>
            </div>

            <!-- Info Panel -->
            <div class="info-panel">
                <h5><i class="fas fa-info-circle me-2"></i>Meal Time Management Guide</h5>
                <p class="mb-3">Configure deadlines for meal ordering across different user types and meal categories.</p>
                <div class="row">
                    <div class="col-md-6">
                        <ul class="mb-0">
                            <li><strong>Same Day:</strong> Orders close at deadline time on meal date</li>
                            <li><strong>1+ Days Before:</strong> Orders close at deadline time, days before meal date</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="mb-0">
                            <li><strong>Employee Settings:</strong> Typically same-day deadlines</li>
                            <li><strong>Visitor Settings:</strong> Usually require advance notice</li>
                        </ul>
                    </div>
                </div>
            </div>

        <form method="POST" id="settingsForm">
            <input type="hidden" name="save_settings" value="1">

            <!-- Time Manager Container -->
            <div class="time-manager-container">
                <!-- Employee Settings -->
                <div class="time-section">
                    <div class="section-header employee">
                        <h5><i class="fas fa-users me-2"></i>Employee Meal Schedules</h5>
                    </div>

                    <?php
                    $meal_icons = [
                        'breakfast' => 'fa-coffee',
                        'lunch' => 'fa-utensils',
                        'dinner' => 'fa-moon',
                        'snack1' => 'fa-cookie-bite',
                        'snack2' => 'fa-cookie-bite',
                        'snack3' => 'fa-cookie-bite'
                    ];

                    $meal_descriptions = [
                        'breakfast' => 'Morning meal service',
                        'lunch' => 'Afternoon main meal',
                        'dinner' => 'Evening meal service',
                        'snack1' => 'Morning snack break',
                        'snack2' => 'Afternoon snack break',
                        'snack3' => 'Evening snack break'
                    ];

                    foreach ($employee_settings as $setting):
                    ?>
                    <div class="meal-time-card <?php echo $setting['is_active'] ? 'active' : 'inactive'; ?>">
                        <div class="meal-header">
                            <div class="meal-icon <?php echo $setting['meal_name']; ?>">
                                <i class="fas <?php echo $meal_icons[$setting['meal_name']] ?? 'fa-utensils'; ?>"></i>
                            </div>
                            <div class="meal-title">
                                <div class="meal-name"><?php echo ucfirst($setting['meal_name']); ?></div>
                                <div class="meal-description"><?php echo $meal_descriptions[$setting['meal_name']] ?? 'Meal service'; ?></div>
                            </div>
                            <div class="form-check form-switch">
                                <input type="checkbox"
                                       class="form-check-input meal-toggle"
                                       id="emp_<?php echo $setting['id']; ?>"
                                       name="settings[<?php echo $setting['id']; ?>][active]"
                                       <?php echo $setting['is_active'] ? 'checked' : ''; ?>>
                            </div>
                        </div>

                        <div class="time-controls">
                            <div class="time-input-group">
                                <label>Deadline Time</label>
                                <input type="time"
                                       class="form-control enhanced-time-input"
                                       name="settings[<?php echo $setting['id']; ?>][time]"
                                       value="<?php echo substr($setting['order_deadline_time'], 0, 5); ?>"
                                       required>
                            </div>
                            <div class="time-input-group">
                                <label>Days Before</label>
                                <select class="form-select enhanced-select"
                                        name="settings[<?php echo $setting['id']; ?>][offset]">
                                    <option value="0" <?php echo $setting['order_deadline_date_offset'] == 0 ? 'selected' : ''; ?>>Same Day</option>
                                    <option value="1" <?php echo $setting['order_deadline_date_offset'] == 1 ? 'selected' : ''; ?>>1 Day Before</option>
                                </select>
                            </div>
                        </div>

                        <div class="deadline-status">
                            <div class="deadline-text">
                                <?php
                                $time_display = date('g:i A', strtotime($setting['order_deadline_time']));
                                if ($setting['order_deadline_date_offset'] == 0) {
                                    echo "Orders close at $time_display on the meal date";
                                } else {
                                    echo "Orders close at $time_display, {$setting['order_deadline_date_offset']} day(s) before meal date";
                                }
                                ?>
                            </div>
                            <?php if ($setting['is_active']): ?>
                                <span class="status-indicator status-open">Open</span>
                            <?php else: ?>
                                <span class="status-indicator status-disabled">Disabled</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Visitor Settings -->
                <div class="time-section">
                    <div class="section-header visitor">
                        <h5><i class="fas fa-user-friends me-2"></i>Visitor Meal Schedules</h5>
                    </div>

                    <?php foreach ($visitor_settings as $setting): ?>
                    <div class="meal-time-card <?php echo $setting['is_active'] ? 'active' : 'inactive'; ?>">
                        <div class="meal-header">
                            <div class="meal-icon <?php echo $setting['meal_name']; ?>">
                                <i class="fas <?php echo $meal_icons[$setting['meal_name']] ?? 'fa-utensils'; ?>"></i>
                            </div>
                            <div class="meal-title">
                                <div class="meal-name"><?php echo ucfirst($setting['meal_name']); ?></div>
                                <div class="meal-description"><?php echo $meal_descriptions[$setting['meal_name']] ?? 'Meal service'; ?> (Visitors)</div>
                            </div>
                            <div class="form-check form-switch">
                                <input type="checkbox"
                                       class="form-check-input meal-toggle"
                                       id="vis_<?php echo $setting['id']; ?>"
                                       name="settings[<?php echo $setting['id']; ?>][active]"
                                       <?php echo $setting['is_active'] ? 'checked' : ''; ?>>
                            </div>
                        </div>

                        <div class="time-controls">
                            <div class="time-input-group">
                                <label>Deadline Time</label>
                                <input type="time"
                                       class="form-control enhanced-time-input"
                                       name="settings[<?php echo $setting['id']; ?>][time]"
                                       value="<?php echo substr($setting['order_deadline_time'], 0, 5); ?>"
                                       required>
                            </div>
                            <div class="time-input-group">
                                <label>Days Before</label>
                                <select class="form-select enhanced-select"
                                        name="settings[<?php echo $setting['id']; ?>][offset]">
                                    <option value="0" <?php echo $setting['order_deadline_date_offset'] == 0 ? 'selected' : ''; ?>>Same Day</option>
                                    <option value="1" <?php echo $setting['order_deadline_date_offset'] == 1 ? 'selected' : ''; ?>>1 Day Before</option>
                                    <option value="2" <?php echo $setting['order_deadline_date_offset'] == 2 ? 'selected' : ''; ?>>2 Days Before</option>
                                    <option value="3" <?php echo $setting['order_deadline_date_offset'] == 3 ? 'selected' : ''; ?>>3 Days Before</option>
                                </select>
                            </div>
                        </div>

                        <div class="deadline-status">
                            <div class="deadline-text">
                                <?php
                                $time_display = date('g:i A', strtotime($setting['order_deadline_time']));
                                if ($setting['order_deadline_date_offset'] == 0) {
                                    echo "Orders close at $time_display on the meal date";
                                } else {
                                    echo "Orders close at $time_display, {$setting['order_deadline_date_offset']} day(s) before meal date";
                                }
                                ?>
                            </div>
                            <?php if ($setting['is_active']): ?>
                                <span class="status-indicator status-open">Open</span>
                            <?php else: ?>
                                <span class="status-indicator status-disabled">Disabled</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Save Controls -->
            <div class="save-controls">
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <button type="submit" class="btn btn-save">
                        <i class="fas fa-save me-2"></i>Save All Settings
                    </button>
                    <a href="index.php" class="btn btn-cancel">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                </div>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Changes will take effect immediately for new meal requests
                    </small>
                </div>
            </div>
        </form>

        </div>
    </div>

    <script src="../../assets/js/bootstrap.min.js"></script>
    <script>
        // Real-time clock update
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const clockElement = document.getElementById('currentTime');
            if (clockElement) {
                clockElement.textContent = timeString;
            }
        }

        // Update clock every second
        setInterval(updateClock, 1000);
        updateClock(); // Initial call

        // Enhanced form submission with loading states
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            const submitBtn = e.target.querySelector('.btn-save');
            const cancelBtn = e.target.querySelector('.btn-cancel');

            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving Settings...';
            submitBtn.disabled = true;
            cancelBtn.style.display = 'none';

            // Add a subtle loading overlay effect
            const saveControls = document.querySelector('.save-controls');
            saveControls.style.opacity = '0.8';
        });

        // Real-time deadline text updates
        function updateDeadlineText(card) {
            const timeInput = card.querySelector('.enhanced-time-input');
            const offsetSelect = card.querySelector('.enhanced-select');
            const deadlineText = card.querySelector('.deadline-text');

            if (timeInput && offsetSelect && deadlineText) {
                const time = timeInput.value;
                const offset = offsetSelect.value;

                if (time) {
                    const timeObj = new Date('2000-01-01 ' + time);
                    const timeStr = timeObj.toLocaleTimeString('en-US', {
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });

                    let text = '';
                    if (offset == '0') {
                        text = `Orders close at ${timeStr} on the meal date`;
                    } else {
                        text = `Orders close at ${timeStr}, ${offset} day(s) before meal date`;
                    }

                    deadlineText.textContent = text;

                    // Add a subtle animation
                    deadlineText.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        deadlineText.style.transform = 'scale(1)';
                    }, 150);
                }
            }
        }

        // Enhanced input change handlers
        document.querySelectorAll('.enhanced-time-input, .enhanced-select').forEach(input => {
            // Add input transition effects
            input.style.transition = 'all 0.3s ease';

            input.addEventListener('change', function() {
                const card = this.closest('.meal-time-card');
                updateDeadlineText(card);
            });

            input.addEventListener('focus', function() {
                this.style.transform = 'scale(1.02)';
            });

            input.addEventListener('blur', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Enhanced toggle switch functionality
        document.querySelectorAll('.meal-toggle').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const card = this.closest('.meal-time-card');
                const statusIndicator = card.querySelector('.status-indicator');

                // Smooth transition for card state
                card.style.transition = 'all 0.3s ease';

                if (this.checked) {
                    card.classList.remove('inactive');
                    card.classList.add('active');
                    if (statusIndicator) {
                        statusIndicator.className = 'status-indicator status-open';
                        statusIndicator.textContent = 'Open';
                    }
                } else {
                    card.classList.remove('active');
                    card.classList.add('inactive');
                    if (statusIndicator) {
                        statusIndicator.className = 'status-indicator status-disabled';
                        statusIndicator.textContent = 'Disabled';
                    }
                }

                // Add visual feedback
                card.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    card.style.transform = 'scale(1)';
                }, 200);
            });
        });

        // Initialize enhanced interactions on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to time cards
            document.querySelectorAll('.meal-time-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('inactive')) {
                        this.style.transform = 'translateY(-2px)';
                    }
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Add keyboard navigation support
            document.querySelectorAll('.meal-toggle').forEach((toggle, index) => {
                toggle.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.checked = !this.checked;
                        this.dispatchEvent(new Event('change'));
                    }
                });
            });

            // Auto-save indication (visual feedback)
            let changesMade = false;
            document.querySelectorAll('input, select').forEach(input => {
                input.addEventListener('change', function() {
                    if (!changesMade) {
                        changesMade = true;
                        const saveBtn = document.querySelector('.btn-save');
                        if (saveBtn) {
                            saveBtn.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.3)';
                        }
                    }
                });
            });

            // Smooth scroll to first error on validation
            const form = document.getElementById('settingsForm');
            form.addEventListener('invalid', function(e) {
                e.target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }, true);

            console.log('Meal Time Manager initialized successfully');
        });

        // Add notification for unsaved changes
        let hasUnsavedChanges = false;
        document.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('change', function() {
                hasUnsavedChanges = true;
            });
        });

        // Form submission clears unsaved changes flag
        document.getElementById('settingsForm').addEventListener('submit', function() {
            hasUnsavedChanges = false;
        });

        // Warn user about unsaved changes when navigating away
        window.addEventListener('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    </script>
</body>
</html>