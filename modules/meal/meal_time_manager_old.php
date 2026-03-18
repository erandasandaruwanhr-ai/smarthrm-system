<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

$message = '';
$message_type = '';

// Create meal time settings table
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
            // Employee meal deadlines
            ['employee', 'breakfast', '07:00:00', 0],
            ['employee', 'snack1', '09:00:00', 0],
            ['employee', 'lunch', '11:00:00', 0],
            ['employee', 'snack2', '14:00:00', 0],
            ['employee', 'dinner', '16:00:00', 0],
            ['employee', 'snack3', '18:00:00', 0],

            // Visitor meal deadlines
            ['visitor', 'breakfast', '08:00:00', 1],
            ['visitor', 'lunch', '12:00:00', 1],
            ['visitor', 'dinner', '17:00:00', 1],
            ['visitor', 'snack1', '10:00:00', 1],
            ['visitor', 'snack2', '15:00:00', 1]
        ];

        foreach ($default_settings as $setting) {
            $insert_query = "INSERT INTO meal_time_settings (setting_type, meal_name, order_deadline_time, order_deadline_date_offset) VALUES (?, ?, ?, ?)";
            $db->query($insert_query, $setting);
        }
    }
} catch (Exception $e) {
    // Table creation failed, handle gracefully
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_settings') {
            // Validate form data
            $validation_errors = [];

            if (!isset($_POST['settings']) || empty($_POST['settings'])) {
                $validation_errors[] = 'No settings data received';
            }

            foreach ($_POST['settings'] as $setting_id => $setting_data) {
                // Validate time format
                if (!isset($setting_data['time']) || !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $setting_data['time'])) {
                    $validation_errors[] = "Invalid time format for setting ID $setting_id";
                }

                // Validate date offset
                if (!isset($setting_data['date_offset']) || !is_numeric($setting_data['date_offset']) || $setting_data['date_offset'] < 0 || $setting_data['date_offset'] > 7) {
                    $validation_errors[] = "Invalid date offset for setting ID $setting_id";
                }
            }

            if (!empty($validation_errors)) {
                $message = 'Validation errors: ' . implode(', ', $validation_errors);
                $message_type = 'error';
            } else {
                // Update settings
                $updated_count = 0;
                foreach ($_POST['settings'] as $setting_id => $setting_data) {
                    $update_query = "UPDATE meal_time_settings SET
                        order_deadline_time = ?,
                        order_deadline_date_offset = ?,
                        is_active = ?
                        WHERE id = ?";

                    $is_active = isset($setting_data['is_active']) ? 1 : 0;
                    $db->query($update_query, [
                        $setting_data['time'],
                        $setting_data['date_offset'],
                        $is_active,
                        $setting_id
                    ]);
                    $updated_count++;
                }

                $message = "Meal time settings updated successfully! ($updated_count settings updated)";
                $message_type = 'success';
            }
        }
    } catch (Exception $e) {
        $message = 'Error updating settings: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get current settings with proper meal ordering
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
    $settings = $db->fetchAll($settings_query);
} catch (Exception $e) {
    $settings = [];
}

// Group settings by type
$employee_settings = [];
$visitor_settings = [];

foreach ($settings as $setting) {
    if ($setting['setting_type'] === 'employee') {
        $employee_settings[] = $setting;
    } else {
        $visitor_settings[] = $setting;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Meal Time Manager</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/fontawesome.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-clock me-2"></i>Meal Time Manager</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Meal Management</a></li>
                            <li class="breadcrumb-item active">Meal Time Manager</li>
                        </ol>
                    </nav>
                </div>
                <div class="action-buttons">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Meal Management
                    </a>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Info Card -->
        <div class="card border-0 shadow mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="text-primary"><i class="fas fa-info-circle me-2"></i>Meal Ordering Deadlines</h5>
                        <p class="mb-0">Set time limits for meal ordering to help with kitchen preparation and planning. Orders placed after the deadline will not be accepted for the specified date.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="text-muted">
                            <small><strong>Current Time:</strong><br><?php echo date('H:i:s'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="update_settings">

            <div class="row">
                <!-- Employee Meal Settings -->
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Employee Meal Deadlines</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($employee_settings as $setting): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input"
                                                       id="emp_active_<?php echo $setting['id']; ?>"
                                                       name="settings[<?php echo $setting['id']; ?>][is_active]"
                                                       <?php echo $setting['is_active'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label fw-bold" for="emp_active_<?php echo $setting['id']; ?>">
                                                    <?php echo ucfirst($setting['meal_name']); ?>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small" for="emp_time_<?php echo $setting['id']; ?>">Order Deadline Time</label>
                                            <input type="time" class="form-control form-control-sm"
                                                   id="emp_time_<?php echo $setting['id']; ?>"
                                                   name="settings[<?php echo $setting['id']; ?>][time]"
                                                   value="<?php echo htmlspecialchars($setting['order_deadline_time']); ?>">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label small" for="emp_offset_<?php echo $setting['id']; ?>">Days Before Meal</label>
                                            <select class="form-select form-select-sm"
                                                    id="emp_offset_<?php echo $setting['id']; ?>"
                                                    name="settings[<?php echo $setting['id']; ?>][date_offset]">
                                                <option value="0" <?php echo $setting['order_deadline_date_offset'] == 0 ? 'selected' : ''; ?>>Same Day</option>
                                                <option value="1" <?php echo $setting['order_deadline_date_offset'] == 1 ? 'selected' : ''; ?>>1 Day Before</option>
                                                <option value="2" <?php echo $setting['order_deadline_date_offset'] == 2 ? 'selected' : ''; ?>>2 Days Before</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <?php
                                                $deadline_text = "";
                                                if ($setting['order_deadline_date_offset'] == 0) {
                                                    $deadline_text = "Orders must be placed by " . date('g:i A', strtotime($setting['order_deadline_time'])) . " on the same day";
                                                } else {
                                                    $deadline_text = "Orders must be placed by " . date('g:i A', strtotime($setting['order_deadline_time'])) . ", " . $setting['order_deadline_date_offset'] . " day(s) before the meal date";
                                                }
                                                echo $deadline_text;
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Visitor Meal Settings -->
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-user-friends me-2"></i>Visitor Meal Deadlines</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($visitor_settings as $setting): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input"
                                                       id="vis_active_<?php echo $setting['id']; ?>"
                                                       name="settings[<?php echo $setting['id']; ?>][is_active]"
                                                       <?php echo $setting['is_active'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label fw-bold" for="vis_active_<?php echo $setting['id']; ?>">
                                                    <?php echo ucfirst($setting['meal_name']); ?>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small" for="vis_time_<?php echo $setting['id']; ?>">Order Deadline Time</label>
                                            <input type="time" class="form-control form-control-sm"
                                                   id="vis_time_<?php echo $setting['id']; ?>"
                                                   name="settings[<?php echo $setting['id']; ?>][time]"
                                                   value="<?php echo htmlspecialchars($setting['order_deadline_time']); ?>">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label small" for="vis_offset_<?php echo $setting['id']; ?>">Days Before Meal</label>
                                            <select class="form-select form-select-sm"
                                                    id="vis_offset_<?php echo $setting['id']; ?>"
                                                    name="settings[<?php echo $setting['id']; ?>][date_offset]">
                                                <option value="0" <?php echo $setting['order_deadline_date_offset'] == 0 ? 'selected' : ''; ?>>Same Day</option>
                                                <option value="1" <?php echo $setting['order_deadline_date_offset'] == 1 ? 'selected' : ''; ?>>1 Day Before</option>
                                                <option value="2" <?php echo $setting['order_deadline_date_offset'] == 2 ? 'selected' : ''; ?>>2 Days Before</option>
                                                <option value="3" <?php echo $setting['order_deadline_date_offset'] == 3 ? 'selected' : ''; ?>>3 Days Before</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <?php
                                                $deadline_text = "";
                                                if ($setting['order_deadline_date_offset'] == 0) {
                                                    $deadline_text = "Orders must be placed by " . date('g:i A', strtotime($setting['order_deadline_time'])) . " on the same day";
                                                } else {
                                                    $deadline_text = "Orders must be placed by " . date('g:i A', strtotime($setting['order_deadline_time'])) . ", " . $setting['order_deadline_date_offset'] . " day(s) before the meal date";
                                                }
                                                echo $deadline_text;
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Save Time Settings
                </button>
            </div>
        </form>

        <!-- Current Status -->
        <div class="card border-0 shadow mt-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Current Deadline Status</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    $current_time = date('H:i:s');
                    $today = date('Y-m-d');
                    ?>
                    <div class="col-md-6">
                        <h6>Today's Employee Meal Deadlines</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <?php foreach ($employee_settings as $setting): ?>
                                    <?php if ($setting['is_active']): ?>
                                        <?php
                                        $deadline_passed = $current_time > $setting['order_deadline_time'];
                                        $status_class = $deadline_passed ? 'text-danger' : 'text-success';
                                        $status_icon = $deadline_passed ? 'times-circle' : 'check-circle';
                                        ?>
                                        <tr>
                                            <td><?php echo ucfirst($setting['meal_name']); ?></td>
                                            <td><?php echo date('g:i A', strtotime($setting['order_deadline_time'])); ?></td>
                                            <td class="<?php echo $status_class; ?>">
                                                <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                                <?php echo $deadline_passed ? 'Closed' : 'Open'; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Today's Visitor Meal Deadlines</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <?php foreach ($visitor_settings as $setting): ?>
                                    <?php if ($setting['is_active']): ?>
                                        <?php
                                        // For visitor meals, check if deadline has passed considering date offset
                                        // If offset is 1 day, deadline for tomorrow's meal is today at deadline time
                                        $meal_date = date('Y-m-d', strtotime('+1 day')); // Assuming we're checking for tomorrow's meals
                                        $deadline_date = date('Y-m-d', strtotime($meal_date . " -{$setting['order_deadline_date_offset']} days"));
                                        $deadline_passed = ($today > $deadline_date) || ($today == $deadline_date && $current_time > $setting['order_deadline_time']);
                                        $status_class = $deadline_passed ? 'text-danger' : 'text-success';
                                        $status_icon = $deadline_passed ? 'times-circle' : 'check-circle';
                                        ?>
                                        <tr>
                                            <td><?php echo ucfirst($setting['meal_name']); ?></td>
                                            <td>
                                                <?php echo date('g:i A', strtotime($setting['order_deadline_time'])); ?>
                                                <?php if ($setting['order_deadline_date_offset'] > 0): ?>
                                                    <br><small class="text-muted">(<?php echo $setting['order_deadline_date_offset']; ?> day(s) before)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="<?php echo $status_class; ?>">
                                                <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                                <?php echo $deadline_passed ? 'Closed' : 'Open'; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const timeInputs = document.querySelectorAll('input[type="time"]');
            const errors = [];
            let hasInvalidTime = false;

            // Validate time inputs - check once
            timeInputs.forEach(function(input) {
                if (!input.value || !input.value.match(/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/)) {
                    hasInvalidTime = true;
                }
            });

            if (hasInvalidTime) {
                errors.push('Please enter valid times for all meal deadlines');
            }

            // Validate meal order logic - with proper day handling
            function validateMealTimes(section, sectionName) {
                const cards = section.querySelectorAll('.card.mb-3');
                const mealTimes = [];

                cards.forEach(function(card) {
                    const checkbox = card.querySelector('input[type="checkbox"]');
                    const timeInput = card.querySelector('input[type="time"]');
                    const label = card.querySelector('.form-check-label');

                    if (checkbox && timeInput && label && checkbox.checked && timeInput.value) {
                        const mealName = label.textContent.trim();
                        mealTimes.push({
                            name: mealName,
                            time: timeInput.value,
                            // Convert to minutes for easier comparison
                            minutes: parseInt(timeInput.value.split(':')[0]) * 60 + parseInt(timeInput.value.split(':')[1])
                        });
                    }
                });

                // Define expected meal order and typical day boundaries
                const mealOrder = {
                    'Breakfast': { order: 1, typicalStart: 6 * 60 },  // 6 AM
                    'Snack1': { order: 2, typicalStart: 9 * 60 },     // 9 AM
                    'Lunch': { order: 3, typicalStart: 12 * 60 },     // 12 PM
                    'Snack2': { order: 4, typicalStart: 14 * 60 },    // 2 PM
                    'Dinner': { order: 5, typicalStart: 16 * 60 },    // 4 PM
                    'Snack3': { order: 6, typicalStart: 18 * 60 }     // 6 PM
                };

                // Check for logical inconsistencies
                for (let i = 0; i < mealTimes.length; i++) {
                    for (let j = i + 1; j < mealTimes.length; j++) {
                        const meal1 = mealTimes[i];
                        const meal2 = mealTimes[j];

                        const order1 = mealOrder[meal1.name]?.order || 99;
                        const order2 = mealOrder[meal2.name]?.order || 99;

                        // Only validate if both meals are in our expected order
                        if (order1 < 99 && order2 < 99) {
                            // If meal1 should come before meal2 in the day
                            if (order1 < order2) {
                                // Check if times are logical (allowing for overnight/next day scenarios)
                                // Skip validation if breakfast (early morning) vs dinner/snack3 (late evening)
                                const isOvernightScenario =
                                    (meal1.name === 'Breakfast' && (meal2.name === 'Dinner' || meal2.name === 'Snack3')) ||
                                    (meal1.name === 'Snack1' && meal2.name === 'Snack3');

                                if (!isOvernightScenario && meal1.minutes > meal2.minutes) {
                                    errors.push(`${sectionName}: ${meal1.name} (${meal1.time}) should be before ${meal2.name} (${meal2.time})`);
                                }
                            } else if (order1 > order2) {
                                // If meal1 should come after meal2 in the day
                                // Check for overnight scenario where late meal is actually "before" early meal
                                const isOvernightScenario =
                                    (meal2.name === 'Breakfast' && (meal1.name === 'Dinner' || meal1.name === 'Snack3'));

                                if (!isOvernightScenario && meal1.minutes < meal2.minutes) {
                                    errors.push(`${sectionName}: ${meal1.name} (${meal1.time}) should be after ${meal2.name} (${meal2.time})`);
                                }
                            }
                        }
                    }
                }
            }

            // Find the two main sections
            const employeeSection = document.querySelector('.col-lg-6:nth-child(1)');
            const visitorSection = document.querySelector('.col-lg-6:nth-child(2)');

            if (employeeSection) {
                validateMealTimes(employeeSection, 'Employee meals');
            }
            if (visitorSection) {
                validateMealTimes(visitorSection, 'Visitor meals');
            }

            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
                return false;
            }

            // Show loading state
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            submitBtn.disabled = true;

            // Re-enable after 5 seconds (fallback)
            setTimeout(function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });

        // Update time display when inputs change
        document.querySelectorAll('input[type="time"], select').forEach(function(input) {
            input.addEventListener('change', function() {
                // Update the explanation text
                const card = input.closest('.card');
                const timeInput = card.querySelector('input[type="time"]');
                const offsetSelect = card.querySelector('select');
                const explanationEl = card.querySelector('.text-muted');

                if (timeInput && offsetSelect && explanationEl) {
                    const time = timeInput.value;
                    const offset = offsetSelect.value;

                    if (time) {
                        const timeFormatted = new Date('1970-01-01T' + time + ':00').toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        });

                        let explanation = '';
                        if (offset == 0) {
                            explanation = `Orders must be placed by ${timeFormatted} on the same day`;
                        } else {
                            explanation = `Orders must be placed by ${timeFormatted}, ${offset} day(s) before the meal date`;
                        }

                        explanationEl.innerHTML = '<i class="fas fa-info-circle me-1"></i>' + explanation;
                    }
                }
            });
        });
    </script>
</body>
</html>