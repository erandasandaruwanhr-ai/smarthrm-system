<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/location_access.php';

$user = getCurrentUser();
$db = new Database();

// Check module permissions
if (!isSuperAdmin() && !hasModulePermission($db, 'training.view')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get user location and account type for access control
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;

// Build role-based filter for training data
$tr_filter = '';
$tr_params = [];

if ($account_type === 'superadmin') {
    // No filter
} elseif ($account_type === 'admin') {
    $tr_filter = ' AND tr.location = ?';
    $tr_params = [$user_location];
} elseif (in_array($account_type, ['manager', 'supervisor'])) {
    $accessible = getAccessibleEmployeesByLocation($db, $user);
    $accessible_epfs = array_column($accessible, 'epf_number');
    if (!empty($accessible_epfs)) {
        $placeholders = str_repeat('?,', count($accessible_epfs) - 1) . '?';
        $tr_filter = " AND tr.epf_number IN ($placeholders)";
        $tr_params = $accessible_epfs;
    } else {
        $tr_filter = ' AND 1=0';
    }
} else {
    $tr_filter = ' AND tr.epf_number = ?';
    $tr_params = [$user['epf_number']];
}

$message = '';
$error = '';

// Handle training plan creation
if ($_POST && isset($_POST['create_plan'])) {
    try {
        // Calculate budget exceed percentage
        $budget_cost = 0;
        if ($_POST['budget_id']) {
            $budget = $db->fetch("SELECT budget_amount FROM training_budget WHERE id = ?", [$_POST['budget_id']]);
            $budget_cost = $budget['budget_amount'] ?? 0;
        }

        $exceed_percentage = $budget_cost > 0 ? (($_POST['training_cost'] - $budget_cost) / $budget_cost) * 100 : 0;

        // Get training_id from the requirement
        $requirement = $db->fetch("SELECT training_id FROM training_requirements WHERE id = ?", [$_POST['requirement_id']]);
        $training_id = $requirement['training_id'];

        $db->query("INSERT INTO training_plans (training_id, training_name, training_institute, trainee_epf, trainee_name, training_cost, training_start_date, training_end_date, budgeted_cost_exceed_percentage, budget_id, requirement_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $training_id,
            $_POST['training_name'],
            $_POST['training_institute'],
            $_POST['trainee_epf'],
            $_POST['trainee_name'],
            $_POST['training_cost'],
            $_POST['training_start_date'],
            $_POST['training_end_date'] ?: null,
            $exceed_percentage,
            $_POST['budget_id'] ?: null,
            $_POST['requirement_id']
        ]);

        $message = "Training plan created successfully!";
    } catch (Exception $e) {
        $error = "Error creating training plan: " . $e->getMessage();
    }
}

// Handle status updates
if ($_POST && isset($_POST['update_status'])) {
    try {
        $db->query("UPDATE training_plans SET status = ? WHERE id = ?", [$_POST['new_status'], $_POST['plan_id']]);
        $message = "Training plan status updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating status: " . $e->getMessage();
    }
}

// Get budgeted requirements for dropdown with role-based filtering
$budgeted_requirements = $db->fetchAll("
    SELECT
        tr.id,
        tr.training_id,
        tr.training_requirement,
        tr.employee_name,
        tr.epf_number,
        tb.id as budget_id,
        tb.budget_amount
    FROM training_requirements tr
    JOIN training_budget tb ON tr.id = tb.requirement_id
    WHERE tb.add_to_budget = 1
    AND tr.status = 'approved'" . $tr_filter . "
    ORDER BY tr.created_at DESC
", $tr_params);

// Training institutes are now manual input - no need to fetch from database

// Get training plans with related data and role-based filtering
$plans = $db->fetchAll("
    SELECT
        tp.*,
        tr.training_requirement,
        tb.budget_amount,
        CASE
            WHEN tp.budgeted_cost_exceed_percentage > 0 THEN 'Over Budget'
            WHEN tp.budgeted_cost_exceed_percentage < -10 THEN 'Under Budget'
            ELSE 'On Budget'
        END as budget_status
    FROM training_plans tp
    LEFT JOIN training_requirements tr ON tp.requirement_id = tr.id
    LEFT JOIN training_budget tb ON tp.budget_id = tb.id
    WHERE 1=1" . $tr_filter . "
    ORDER BY tp.created_at DESC
", $tr_params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Training Plans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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

        .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .nav-pills .nav-link {
            border-radius: 10px;
            margin-right: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
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
                        <h1><i class="fas fa-tasks me-3"></i>Training Plans</h1>
                        <p class="mb-0">Create and manage detailed training schedules and cost tracking</p>
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Training Management</a></li>
                    <li class="breadcrumb-item active">Training Plans</li>
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

            <!-- Navigation Tabs -->
            <div class="content-card">
                <ul class="nav nav-pills mb-4" id="plansTabs">
                    <li class="nav-item">
                        <button class="nav-link active" id="create-tab" data-bs-toggle="pill" data-bs-target="#create-plan">
                            <i class="fas fa-plus me-2"></i>Create Plan
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="list-tab" data-bs-toggle="pill" data-bs-target="#plans-list">
                            <i class="fas fa-list me-2"></i>View Plans
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="plansTabContent">
                    <!-- Create Plan Tab -->
                    <div class="tab-pane fade show active" id="create-plan">
                        <form method="POST" class="row">
                            <div class="col-12 mb-3">
                                <label for="requirement_id" class="form-label">Training Requirement <span class="text-danger">*</span></label>
                                <select class="form-select" name="requirement_id" id="requirementSelect" required onchange="fillTraineeDetails()">
                                    <option value="">Select approved and budgeted training requirement</option>
                                    <?php foreach ($budgeted_requirements as $req): ?>
                                    <option value="<?php echo $req['id']; ?>"
                                            data-employee="<?php echo htmlspecialchars($req['employee_name']); ?>"
                                            data-epf="<?php echo $req['epf_number']; ?>"
                                            data-budget-id="<?php echo $req['budget_id']; ?>"
                                            data-budget-amount="<?php echo $req['budget_amount']; ?>">
                                        <?php echo htmlspecialchars(substr($req['training_requirement'], 0, 80)); ?>...
                                        | <?php echo $req['employee_name']; ?> (<?php echo $req['epf_number']; ?>)
                                        | Budget: LKR <?php echo number_format($req['budget_amount'], 2); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Budget Information Display -->
                            <div class="col-12 mb-3" id="budgetInfoCard" style="display: none;">
                                <div class="alert alert-info">
                                    <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Selected Training Requirement Details</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Employee:</strong> <span id="selectedEmployee"></span><br>
                                            <strong>EPF Number:</strong> <span id="selectedEpf"></span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Allocated Budget:</strong> <span id="selectedBudget" class="text-success"></span><br>
                                            <strong>Budget ID:</strong> <span id="selectedBudgetId"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="training_name" class="form-label">Training Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="training_name" placeholder="Enter specific training program name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="training_institute" class="form-label">Training Institute <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="training_institute" placeholder="Enter training institute name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="trainee_name" class="form-label">Trainee Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="trainee_name" id="traineeName" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="trainee_epf" class="form-label">Trainee EPF <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="trainee_epf" id="traineeEpf" readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="training_cost" class="form-label">Training Cost (LKR) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="training_cost" id="trainingCost" step="0.01" min="0" required onchange="calculateBudgetExceed()">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="training_start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="training_start_date" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="training_end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" name="training_end_date">
                            </div>
                            <div class="col-12 mb-3">
                                <div class="alert alert-info" id="budgetAlert" style="display: none;">
                                    <h6 class="alert-heading">Budget Analysis</h6>
                                    <p class="mb-0" id="budgetAnalysis"></p>
                                </div>
                            </div>
                            <div class="col-12">
                                <input type="hidden" name="budget_id" id="budgetId">
                                <button type="submit" name="create_plan" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Create Training Plan
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Plans List Tab -->
                    <div class="tab-pane fade" id="plans-list">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5>Training Plans</h5>
                            <a href="export_plans.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-file-excel me-1"></i>Export
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped" id="plansTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Training Name</th>
                                        <th>Institute</th>
                                        <th>Trainee</th>
                                        <th>Dates</th>
                                        <th>Cost</th>
                                        <th>Budget Status</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($plans as $plan): ?>
                                    <tr>
                                        <td><?php echo $plan['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($plan['training_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($plan['training_requirement'], 0, 30)); ?>...</small>
                                        </td>
                                        <td><?php echo htmlspecialchars($plan['training_institute']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($plan['trainee_name']); ?>
                                            <br><small class="text-muted"><?php echo $plan['trainee_epf']; ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo date('M j, Y', strtotime($plan['training_start_date'])); ?></strong>
                                            <?php if ($plan['training_end_date']): ?>
                                            <br><small class="text-muted">to <?php echo date('M j, Y', strtotime($plan['training_end_date'])); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong>LKR <?php echo number_format($plan['training_cost'], 2); ?></strong>
                                            <?php if ($plan['budget_amount']): ?>
                                            <br><small class="text-muted">Budget: <?php echo number_format($plan['budget_amount'], 2); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo match($plan['budget_status']) {
                                                    'On Budget' => 'success',
                                                    'Over Budget' => 'danger',
                                                    'Under Budget' => 'info',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo $plan['budget_status']; ?>
                                                <?php if ($plan['budgeted_cost_exceed_percentage'] != 0): ?>
                                                <br><?php echo number_format(abs($plan['budgeted_cost_exceed_percentage']), 1); ?>%
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo match($plan['status']) {
                                                    'completed' => 'success',
                                                    'ongoing' => 'primary',
                                                    'cancelled' => 'danger',
                                                    default => 'warning'
                                                };
                                            ?>">
                                                <?php echo ucfirst($plan['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="updateStatus(<?php echo $plan['id']; ?>, 'ongoing')">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-success btn-sm" onclick="updateStatus(<?php echo $plan['id']; ?>, 'completed')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="updateStatus(<?php echo $plan['id']; ?>, 'cancelled')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Form (Hidden) -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="plan_id" id="statusPlanId">
        <input type="hidden" name="new_status" id="newStatus">
        <input type="hidden" name="update_status" value="1">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#plansTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [8] }
                ]
            });
        });

        function fillTraineeDetails() {
            const select = document.getElementById('requirementSelect');
            const option = select.options[select.selectedIndex];

            if (option.value) {
                // Fill form fields
                document.getElementById('traineeName').value = option.dataset.employee;
                document.getElementById('traineeEpf').value = option.dataset.epf;
                document.getElementById('budgetId').value = option.dataset.budgetId;

                // Auto-fill Training Cost with budgeted amount
                document.getElementById('trainingCost').value = option.dataset.budgetAmount;

                // Show and populate budget info card
                document.getElementById('budgetInfoCard').style.display = 'block';
                document.getElementById('selectedEmployee').textContent = option.dataset.employee;
                document.getElementById('selectedEpf').textContent = option.dataset.epf;
                document.getElementById('selectedBudget').textContent = 'LKR ' + parseFloat(option.dataset.budgetAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('selectedBudgetId').textContent = option.dataset.budgetId;

                // Trigger budget calculation since cost is now filled
                calculateBudgetExceed();
            } else {
                // Clear form fields
                document.getElementById('traineeName').value = '';
                document.getElementById('traineeEpf').value = '';
                document.getElementById('budgetId').value = '';
                document.getElementById('trainingCost').value = '';

                // Hide budget info card and budget alert
                document.getElementById('budgetInfoCard').style.display = 'none';
                document.getElementById('budgetAlert').style.display = 'none';
            }
        }

        function calculateBudgetExceed() {
            const select = document.getElementById('requirementSelect');
            const option = select.options[select.selectedIndex];
            const trainingCost = parseFloat(document.getElementById('trainingCost').value) || 0;

            if (option.value && trainingCost > 0) {
                const budgetAmount = parseFloat(option.dataset.budgetAmount) || 0;
                const difference = trainingCost - budgetAmount;
                const percentage = budgetAmount > 0 ? (difference / budgetAmount) * 100 : 0;

                const alert = document.getElementById('budgetAlert');
                const analysis = document.getElementById('budgetAnalysis');

                alert.style.display = 'block';

                if (difference > 0) {
                    alert.className = 'alert alert-warning';
                    analysis.innerHTML = `<strong>Over Budget:</strong> Training cost (LKR ${trainingCost.toLocaleString()}) exceeds budget (LKR ${budgetAmount.toLocaleString()}) by LKR ${difference.toLocaleString()} (${percentage.toFixed(1)}%)`;
                } else if (difference < 0) {
                    alert.className = 'alert alert-info';
                    analysis.innerHTML = `<strong>Under Budget:</strong> Training cost (LKR ${trainingCost.toLocaleString()}) is LKR ${Math.abs(difference).toLocaleString()} under budget (LKR ${budgetAmount.toLocaleString()}) by ${Math.abs(percentage).toFixed(1)}%`;
                } else {
                    alert.className = 'alert alert-success';
                    analysis.innerHTML = `<strong>On Budget:</strong> Training cost matches the allocated budget of LKR ${budgetAmount.toLocaleString()}`;
                }
            }
        }

        function updateStatus(id, status) {
            if (confirm(`Are you sure you want to mark this training plan as ${status}?`)) {
                document.getElementById('statusPlanId').value = id;
                document.getElementById('newStatus').value = status;
                document.getElementById('statusForm').submit();
            }
        }
    </script>
</body>
</html>