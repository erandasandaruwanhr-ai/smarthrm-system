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

// Handle budget approval/modification
if ($_POST && isset($_POST['update_budget'])) {
    try {
        // First check if a budget entry already exists for this requirement
        $existing_budget = $db->fetch("SELECT id FROM training_budget WHERE requirement_id = ?", [$_POST['requirement_id']]);

        // Get training_id from the requirement
        $requirement = $db->fetch("SELECT training_id FROM training_requirements WHERE id = ?", [$_POST['requirement_id']]);
        $training_id = $requirement['training_id'];

        if ($existing_budget) {
            // Update existing budget entry
            $db->query("UPDATE training_budget SET training_id = ?, budget_year = ?, add_to_budget = ?, budget_amount = ?, budget_approved_by = ?, approval_date = NOW() WHERE requirement_id = ?", [
                $training_id,
                $_POST['budget_year'],
                $_POST['add_to_budget'] ? 1 : 0,
                $_POST['budget_amount'],
                $user['epf_number'],
                $_POST['requirement_id']
            ]);
        } else {
            // Create new budget entry
            $db->query("INSERT INTO training_budget (training_id, budget_year, requirement_id, add_to_budget, budget_amount, budget_approved_by, approval_date) VALUES (?, ?, ?, ?, ?, ?, NOW())", [
                $training_id,
                $_POST['budget_year'],
                $_POST['requirement_id'],
                $_POST['add_to_budget'] ? 1 : 0,
                $_POST['budget_amount'],
                $user['epf_number']
            ]);
        }

        $message = "Budget decision saved successfully!";
    } catch (Exception $e) {
        $error = "Error updating budget: " . $e->getMessage();
    }
}

// Get current year
$current_year = $_GET['year'] ?? date('Y');

// Get requirements with budget status and role-based filtering
$req_params = array_merge([$current_year], $tr_params);
$requirements = $db->fetchAll("
    SELECT
        tr.*,
        tb.id as budget_id,
        tb.add_to_budget,
        tb.budget_amount,
        tb.budget_approved_by,
        tb.approval_date
    FROM training_requirements tr
    LEFT JOIN training_budget tb ON tr.id = tb.requirement_id
    WHERE tr.year = ?" . $tr_filter . "
    ORDER BY tr.created_at DESC
", $req_params);

// Get budget summary with role-based filtering
$budget_summary = $db->fetch("
    SELECT
        COUNT(tb.id) as budgeted_items,
        SUM(tb.budget_amount) as total_budget,
        COUNT(tr.id) as total_requirements
    FROM training_requirements tr
    LEFT JOIN training_budget tb ON tr.id = tb.requirement_id AND tb.add_to_budget = 1
    WHERE tr.year = ?" . $tr_filter, $req_params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Budget Management</title>
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

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .stats-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .stats-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .stats-card p {
            color: #7f8c8d;
            margin: 0;
            font-weight: 500;
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
                        <h1><i class="fas fa-money-check-alt me-3"></i>Budget Management</h1>
                        <p class="mb-0">Plan, approve, and track training budgets for <?php echo $current_year; ?></p>
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Training Management</a></li>
                    <li class="breadcrumb-item active">Budget Management</li>
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

            <!-- Budget Summary Cards -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="icon" style="background-color: rgba(52, 152, 219, 0.1); color: #3498db;">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3><?php echo number_format($budget_summary['total_requirements'] ?? 0); ?></h3>
                        <p>Total Requirements</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="icon" style="background-color: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3><?php echo number_format($budget_summary['budgeted_items'] ?? 0); ?></h3>
                        <p>Budgeted Items</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-12 mb-4">
                    <div class="stats-card">
                        <div class="icon" style="background-color: rgba(231, 76, 60, 0.1); color: #e74c3c;">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h3>LKR <?php echo number_format($budget_summary['total_budget'] ?? 0, 2); ?></h3>
                        <p>Total Budget Allocated</p>
                    </div>
                </div>
            </div>

            <!-- Budget Management -->
            <div class="content-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5>Training Budget Decisions</h5>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" style="width: auto;" onchange="window.location.href='?year='+this.value">
                            <option value="<?php echo date('Y')-1; ?>" <?php echo $current_year == date('Y')-1 ? 'selected' : ''; ?>><?php echo date('Y')-1; ?></option>
                            <option value="<?php echo date('Y'); ?>" <?php echo $current_year == date('Y') ? 'selected' : ''; ?>><?php echo date('Y'); ?></option>
                            <option value="<?php echo date('Y')+1; ?>" <?php echo $current_year == date('Y')+1 ? 'selected' : ''; ?>><?php echo date('Y')+1; ?></option>
                        </select>
                        <a href="export_budget.php?year=<?php echo $current_year; ?>" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-file-excel me-1"></i>Export
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped" id="budgetTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Training ID</th>
                                <th>Training Requirement</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Budget Status</th>
                                <th>Budget Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requirements as $req): ?>
                            <tr>
                                <td><?php echo $req['id']; ?></td>
                                <td><span class="badge bg-primary"><?php echo $req['training_id']; ?></span></td>
                                <td><?php echo htmlspecialchars(substr($req['training_requirement'], 0, 40)); ?>...</td>
                                <td>
                                    <?php echo htmlspecialchars($req['employee_name']); ?>
                                    <br><small class="text-muted"><?php echo $req['epf_number']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($req['department']); ?></td>
                                <td><?php echo $req['proposed_period']; ?></td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo match($req['status']) {
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'completed' => 'primary',
                                            default => 'warning'
                                        };
                                    ?>">
                                        <?php echo ucfirst($req['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($req['add_to_budget'] === null): ?>
                                        <span class="badge bg-warning">Pending Decision</span>
                                    <?php elseif ($req['add_to_budget']): ?>
                                        <span class="badge bg-success">Budgeted</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Not Budgeted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($req['budget_amount']): ?>
                                        LKR <?php echo number_format($req['budget_amount'], 2); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="manageBudget(<?php echo $req['id']; ?>, '<?php echo addslashes($req['training_requirement']); ?>', '<?php echo $req['employee_name']; ?>', <?php echo $req['budget_amount'] ?? 0; ?>, <?php echo $req['add_to_budget'] ? 'true' : 'false'; ?>)">
                                        <i class="fas fa-edit"></i> Manage
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Budget Management Modal -->
    <div class="modal fade" id="budgetModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Training Budget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label"><strong>Training Requirement:</strong></label>
                                <p id="modalRequirement" class="form-control-plaintext border p-2 bg-light rounded"></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>Employee:</strong></label>
                                <p id="modalEmployee" class="form-control-plaintext"></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="budget_year" class="form-label">Budget Year</label>
                                <select class="form-select" name="budget_year" required>
                                    <option value="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></option>
                                    <option value="<?php echo date('Y')+1; ?>"><?php echo date('Y')+1; ?></option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="add_to_budget" id="addToBudget" value="1">
                                    <label class="form-check-label" for="addToBudget">
                                        <strong>Add to Budget</strong>
                                    </label>
                                </div>
                                <div class="form-text">Check this box to include this training requirement in the budget</div>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="budget_amount" class="form-label">Budget Amount (LKR)</label>
                                <input type="number" class="form-control" name="budget_amount" id="budgetAmount" step="0.01" min="0" placeholder="0.00">
                                <div class="form-text">Enter the allocated budget amount for this training</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="requirement_id" id="modalRequirementId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_budget" class="btn btn-primary">Save Budget Decision</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#budgetTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [9] }
                ]
            });
        });

        function manageBudget(id, requirement, employee, amount, budgeted) {
            document.getElementById('modalRequirementId').value = id;
            document.getElementById('modalRequirement').innerText = requirement;
            document.getElementById('modalEmployee').innerText = employee;
            document.getElementById('budgetAmount').value = amount;
            document.getElementById('addToBudget').checked = budgeted;

            new bootstrap.Modal(document.getElementById('budgetModal')).show();
        }

        // Toggle budget amount field based on checkbox
        document.getElementById('addToBudget').addEventListener('change', function() {
            const budgetAmountField = document.getElementById('budgetAmount');
            if (this.checked) {
                budgetAmountField.required = true;
                budgetAmountField.focus();
            } else {
                budgetAmountField.required = false;
                budgetAmountField.value = '';
            }
        });
    </script>
</body>
</html>