<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Only superadmins can access this page
if (!isSuperAdmin()) {
    header('Location: ../../dashboard.php');
    exit;
}

$user = getCurrentUser();
$db = new Database();

$message = '';
$error = '';

// Create tables if they don't exist
try {
    $sql = file_get_contents('database_setup.sql');
    if ($sql) {
        $queries = explode(';', $sql);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $db->query($query);
            }
        }
    }
} catch (Exception $e) {
    // Tables might already exist, continue
}

// Handle form submission
if ($_POST) {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_period') {
            $year = (int)$_POST['year'];
            $start_date = $year . '-01-01';
            $end_date = $year . '-12-31';

            // Check if year already exists
            $existing = $db->fetch("SELECT id FROM goal_setting_periods WHERE year = ?", [$year]);
            if ($existing) {
                throw new Exception("Goal Setting period for year $year already exists!");
            }

            $data = [
                'year' => $year,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'status' => 'draft',
                'created_by' => $user['epf_number'],
                'created_date' => date('Y-m-d H:i:s')
            ];

            $period_id = $db->insert('goal_setting_periods', $data);

            // Create default goal structure (6 main sections with 6 sub-items each)
            $goal_sections = ['12.3.3.1', '12.3.3.2', '12.3.3.3', '12.3.3.4', '12.3.3.5', '12.3.3.6'];

            foreach ($goal_sections as $section) {
                // Main section header
                $db->insert('goal_setting_templates', [
                    'period_id' => $period_id,
                    'goal_section' => $section,
                    'goal_subsection' => $section,
                    'main_goals' => '',
                    'activities' => '',
                    'measurement_criteria' => '',
                    'weightage' => 0,
                    'created_date' => date('Y-m-d H:i:s')
                ]);

                // Sub-items
                for ($i = 1; $i <= 6; $i++) {
                    $subsection = $section . '.' . $i;
                    $db->insert('goal_setting_templates', [
                        'period_id' => $period_id,
                        'goal_section' => $section,
                        'goal_subsection' => $subsection,
                        'main_goals' => '',
                        'activities' => '',
                        'measurement_criteria' => '',
                        'weightage' => 0,
                        'created_date' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            $message = "Goal Setting period created successfully for year $year with default goal structure!";

        } elseif ($action === 'update_template') {
            $template_id = $_POST['template_id'];
            $updates = [
                'main_goals' => $_POST['main_goals'] ?? '',
                'activities' => $_POST['activities'] ?? '',
                'measurement_criteria' => $_POST['measurement_criteria'] ?? '',
                'weightage' => (float)($_POST['weightage'] ?? 0)
            ];

            $db->update('goal_setting_templates', $updates, 'id = ?', [$template_id]);
            $message = "Goal template updated successfully!";

        } elseif ($action === 'update_status') {
            $period_id = $_POST['period_id'];
            $new_status = $_POST['status'];

            $db->update('goal_setting_periods', ['status' => $new_status], 'id = ?', [$period_id]);
            $message = "Goal Setting period status updated to " . ucfirst($new_status) . "!";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get existing periods
$periods = $db->fetchAll("
    SELECT * FROM goal_setting_periods
    ORDER BY year DESC
");

// Get selected year's templates
$selected_year = $_GET['year'] ?? '';
$templates = [];
if ($selected_year) {
    $period = $db->fetch("SELECT id FROM goal_setting_periods WHERE year = ?", [$selected_year]);
    if ($period) {
        $templates = $db->fetchAll("
            SELECT * FROM goal_setting_templates
            WHERE period_id = ?
            ORDER BY goal_section, goal_subsection
        ", [$period['id']]);
    }
}

// Generate year options
$current_year = date('Y');
$year_options = [];
for ($i = $current_year; $i <= $current_year + 10; $i++) {
    $year_options[] = $i;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Goal Setting Form Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .goal-section-header {
            background: linear-gradient(135deg, var(--info-color), #138496);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-weight: bold;
        }

        .goal-subsection {
            background: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
        }

        .status-active { color: #28a745; }
        .status-draft { color: #ffc107; }
        .status-closed { color: #dc3545; }

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
                        <h1><i class="fas fa-cogs me-3"></i>Goal Setting Form Setup</h1>
                        <p>Configure yearly goal setting forms and allocations</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Create New Period -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Create New Goal Setting Period</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="create_period">
                                <div class="mb-3">
                                    <label class="form-label">Select Year</label>
                                    <select name="year" class="form-select" required>
                                        <option value="">Choose Year...</option>
                                        <?php foreach ($year_options as $year): ?>
                                            <option value="<?= $year ?>"><?= $year ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Period: 01 January - 31 December</div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Create Period
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Existing Periods</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($periods)): ?>
                                <p class="text-muted">No goal setting periods created yet.</p>
                            <?php else: ?>
                                <?php foreach ($periods as $period): ?>
                                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                        <div>
                                            <strong><?= $period['year'] ?></strong>
                                            <small class="d-block text-muted">
                                                <?= date('M d', strtotime($period['start_date'])) ?> - <?= date('M d, Y', strtotime($period['end_date'])) ?>
                                            </small>
                                        </div>
                                        <div>
                                            <span class="badge bg-secondary status-<?= $period['status'] ?>">
                                                <?= ucfirst($period['status']) ?>
                                            </span>
                                            <a href="?year=<?= $period['year'] ?>" class="btn btn-sm btn-outline-primary ms-2">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Goal Setting Form Configuration -->
            <?php if ($selected_year && !empty($templates)): ?>
                <?php
                $period = $db->fetch("SELECT * FROM goal_setting_periods WHERE year = ?", [$selected_year]);
                ?>
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-clipboard-list me-2"></i>Goal Setting Form - <?= $selected_year ?>
                            </h5>
                            <div>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="period_id" value="<?= $period['id'] ?>">
                                    <select name="status" class="form-select form-select-sm d-inline" style="width: auto;" onchange="this.form.submit()">
                                        <option value="draft" <?= $period['status'] == 'draft' ? 'selected' : '' ?>>Draft</option>
                                        <option value="active" <?= $period['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="closed" <?= $period['status'] == 'closed' ? 'selected' : '' ?>>Closed</option>
                                    </select>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 8%;">Goal S/N #</th>
                                        <th style="width: 20%;">Main Goals</th>
                                        <th style="width: 20%;">Activities</th>
                                        <th style="width: 25%;">Measurement Criteria</th>
                                        <th style="width: 8%;">Weightage (%)</th>
                                        <th style="width: 8%;">Mid-Year Progress</th>
                                        <th style="width: 8%;">Achieved %</th>
                                        <th style="width: 8%;">Self-Rating</th>
                                        <th style="width: 8%;">Supervisor Rating</th>
                                        <th style="width: 8%;">Final Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $current_section = '';
                                    $total_weightage = 0;
                                    foreach ($templates as $template):
                                        if (substr_count($template['goal_subsection'], '.') == 2) { // Main section headers
                                            $current_section = $template['goal_section'];
                                    ?>
                                        <tr class="table-info">
                                            <td><strong><?= htmlspecialchars($template['goal_subsection']) ?></strong></td>
                                            <td colspan="9" class="text-center"><em>Main Goal Section <?= substr($current_section, -1) ?></em></td>
                                        </tr>
                                    <?php
                                        } else { // Sub-items
                                            $total_weightage += $template['weightage'];
                                    ?>
                                        <tr>
                                            <td><small><?= htmlspecialchars($template['goal_subsection']) ?></small></td>
                                            <td>
                                                <textarea class="form-control form-control-sm"
                                                          name="main_goals_<?= $template['id'] ?>"
                                                          rows="2"
                                                          placeholder="Enter main goal..."><?= htmlspecialchars($template['main_goals']) ?></textarea>
                                            </td>
                                            <td>
                                                <textarea class="form-control form-control-sm"
                                                          name="activities_<?= $template['id'] ?>"
                                                          rows="2"
                                                          placeholder="Enter activities..."><?= htmlspecialchars($template['activities']) ?></textarea>
                                            </td>
                                            <td>
                                                <textarea class="form-control form-control-sm"
                                                          name="measurement_criteria_<?= $template['id'] ?>"
                                                          rows="2"
                                                          placeholder="How to measure outcome..."><?= htmlspecialchars($template['measurement_criteria']) ?></textarea>
                                            </td>
                                            <td>
                                                <input type="number"
                                                       class="form-control form-control-sm weightage-input"
                                                       name="weightage_<?= $template['id'] ?>"
                                                       value="<?= $template['weightage'] ?>"
                                                       min="0" max="100" step="0.01">
                                            </td>
                                            <td class="text-center text-muted"><small>YS/IP/C</small></td>
                                            <td class="text-center text-muted"><small>%</small></td>
                                            <td class="text-center text-muted"><small>Rating</small></td>
                                            <td class="text-center text-muted"><small>Rating</small></td>
                                            <td class="text-center text-muted"><small>Rating</small></td>
                                        </tr>
                                        <tr>
                                            <td colspan="10">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update_template">
                                                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                                    <input type="hidden" name="main_goals" value="">
                                                    <input type="hidden" name="activities" value="">
                                                    <input type="hidden" name="measurement_criteria" value="">
                                                    <input type="hidden" name="weightage" value="">
                                                    <button type="button" class="btn btn-sm btn-outline-primary update-btn" data-template-id="<?= $template['id'] ?>">
                                                        <i class="fas fa-save me-1"></i>Update Row
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php
                                        }
                                    endforeach;
                                    ?>
                                    <tr class="table-warning">
                                        <td colspan="4" class="text-end"><strong>TOTAL</strong></td>
                                        <td class="text-center"><strong><span id="total-weightage"><?= number_format($total_weightage, 2) ?></span>%</strong></td>
                                        <td colspan="5"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Agreement Section -->
                        <div class="mt-4">
                            <h6><i class="fas fa-handshake me-2"></i>Agreement on Goals</h6>
                            <p class="text-muted mb-3"><em>(At the commencement of the New Appraisal Year)</em></p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="border rounded p-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" disabled>
                                            <label class="form-check-label">
                                                <strong>Employee Agreement</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted">Date & Time will be recorded when employee agrees</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" disabled>
                                            <label class="form-check-label">
                                                <strong>Manager Agreement</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted">Date & Time will be recorded when manager agrees</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Navigation -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Goal Setting
                        </a>
                        <a href="executive_list.php" class="btn btn-primary">
                            <i class="fas fa-users me-2"></i>View Executive List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update template functionality
        document.querySelectorAll('.update-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const templateId = this.dataset.templateId;
                const form = this.closest('form');

                // Get values from inputs
                const mainGoals = document.querySelector(`textarea[name="main_goals_${templateId}"]`).value;
                const activities = document.querySelector(`textarea[name="activities_${templateId}"]`).value;
                const measurementCriteria = document.querySelector(`textarea[name="measurement_criteria_${templateId}"]`).value;
                const weightage = document.querySelector(`input[name="weightage_${templateId}"]`).value;

                // Set hidden field values
                form.querySelector('input[name="main_goals"]').value = mainGoals;
                form.querySelector('input[name="activities"]').value = activities;
                form.querySelector('input[name="measurement_criteria"]').value = measurementCriteria;
                form.querySelector('input[name="weightage"]').value = weightage;

                // Submit form
                form.submit();
            });
        });

        // Calculate total weightage
        document.querySelectorAll('.weightage-input').forEach(input => {
            input.addEventListener('input', function() {
                let total = 0;
                document.querySelectorAll('.weightage-input').forEach(inp => {
                    total += parseFloat(inp.value) || 0;
                });
                document.getElementById('total-weightage').textContent = total.toFixed(2);

                // Warning if not 100%
                const totalElement = document.getElementById('total-weightage');
                const parentRow = totalElement.closest('tr');
                if (Math.abs(total - 100) > 0.01) {
                    parentRow.classList.add('table-danger');
                    parentRow.classList.remove('table-warning');
                } else {
                    parentRow.classList.add('table-warning');
                    parentRow.classList.remove('table-danger');
                }
            });
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>