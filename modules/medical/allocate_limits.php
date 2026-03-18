<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user is superadmin
if (!isSuperAdmin()) {
    header('Location: index.php');
    exit();
}

$success_message = '';
$error_message = '';

// Available coverage periods (8.3.1.3) - Extended to 2040
$availablePeriods = [
    '2026-2027' => '2026-2027 (Feb 9, 2026 - Feb 8, 2027)',
    '2027-2028' => '2027-2028 (Feb 9, 2027 - Feb 8, 2028)',
    '2028-2029' => '2028-2029 (Feb 9, 2028 - Feb 8, 2029)',
    '2029-2030' => '2029-2030 (Feb 9, 2029 - Feb 8, 2030)',
    '2030-2031' => '2030-2031 (Feb 9, 2030 - Feb 8, 2031)',
    '2031-2032' => '2031-2032 (Feb 9, 2031 - Feb 8, 2032)',
    '2032-2033' => '2032-2033 (Feb 9, 2032 - Feb 8, 2033)',
    '2033-2034' => '2033-2034 (Feb 9, 2033 - Feb 8, 2034)',
    '2034-2035' => '2034-2035 (Feb 9, 2034 - Feb 8, 2035)',
    '2035-2036' => '2035-2036 (Feb 9, 2035 - Feb 8, 2036)',
    '2036-2037' => '2036-2037 (Feb 9, 2036 - Feb 8, 2037)',
    '2037-2038' => '2037-2038 (Feb 9, 2037 - Feb 8, 2038)',
    '2038-2039' => '2038-2039 (Feb 9, 2038 - Feb 8, 2039)',
    '2039-2040' => '2039-2040 (Feb 9, 2039 - Feb 8, 2040)'
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_allocation'])) {
            $coverage_period = $_POST['coverage_period'];
            $allocation = floatval($_POST['allocation_limit']);

            if ($allocation <= 0) {
                throw new Exception("Allocation amount must be greater than zero.");
            }

            // Check if coverage period allocation exists
            $existing = $db->fetch("SELECT id FROM medical_allocations WHERE coverage_period = ?", [$coverage_period]);

            if ($existing) {
                // Update existing allocation
                $db->update('medical_allocations', [
                    'allocation_limit' => $allocation,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'coverage_period = ?', [$coverage_period]);
                $success_message = "Allocation for coverage period $coverage_period updated successfully to Rs. " . number_format($allocation, 2);
            } else {
                // Insert new allocation
                $db->insert('medical_allocations', [
                    'coverage_period' => $coverage_period,
                    'allocation_limit' => $allocation
                ]);
                $success_message = "Allocation for coverage period $coverage_period created successfully with Rs. " . number_format($allocation, 2);
            }
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get all allocations
$allocations = $db->fetchAll("SELECT * FROM medical_allocations ORDER BY coverage_period DESC");
$currentYear = date('Y');
$currentCoveragePeriod = $currentYear . '-' . ($currentYear + 1);

// Calculate statistics for current coverage period
$currentAllocation = 0;
$totalClaimed = 0;
$totalEmployees = 0;
$utilizationPercentage = 0;

try {
    $currentPeriodData = $db->fetch("SELECT allocation_limit FROM medical_allocations WHERE coverage_period = ?", [$currentCoveragePeriod]);
    $currentAllocation = $currentPeriodData ? $currentPeriodData['allocation_limit'] : 125000.00;

    $claimData = $db->fetch("SELECT SUM(claimed_amount) as total FROM medical_claims WHERE coverage_period = ?", [$currentCoveragePeriod]);
    $totalClaimed = $claimData['total'] ?? 0;

    $employeeData = $db->fetch("SELECT COUNT(*) as count FROM employees WHERE is_active = 1");
    $totalEmployees = $employeeData['count'];

    if ($currentAllocation > 0) {
        $totalAllocated = $currentAllocation * $totalEmployees;
        $utilizationPercentage = $totalAllocated > 0 ? ($totalClaimed / $totalAllocated) * 100 : 0;
    }

} catch (Exception $e) {
    error_log("Allocation statistics error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Allocate Limits</title>
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

        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            border-left: 4px solid;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .stats-card.warning { border-left-color: var(--warning-color); }
        .stats-card.success { border-left-color: var(--success-color); }
        .stats-card.info { border-left-color: var(--info-color); }
        .stats-card.primary { border-left-color: var(--primary-color); }

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

        .allocation-form {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .year-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .year-badge.current {
            background: var(--success-color);
        }

        .utilization-bar {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .utilization-fill {
            height: 100%;
            background: linear-gradient(45deg, var(--success-color), var(--info-color));
            transition: width 0.3s ease;
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
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-cogs me-3"></i>Allocate Limits</h1>
                        <p>Configure medical insurance coverage limits and employee allocations</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Medical Insurance</a></li>
                    <li class="breadcrumb-item active">Allocate Limits</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4 mb-0">Allocation Management</h2>
                <div>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Current Year Statistics -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card warning">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3">
                                    <i class="fas fa-coins fa-lg"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4 class="fw-bold mb-0">Rs. <?php echo number_format($currentAllocation, 2); ?></h4>
                                <p class="text-muted mb-0">Current Allocation</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card primary">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                                    <i class="fas fa-users fa-lg"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4 class="fw-bold mb-0"><?php echo number_format($totalEmployees); ?></h4>
                                <p class="text-muted mb-0">Total Employees</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card success">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-success bg-opacity-10 text-success rounded-circle p-3">
                                    <i class="fas fa-chart-line fa-lg"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4 class="fw-bold mb-0">Rs. <?php echo number_format($totalClaimed, 2); ?></h4>
                                <p class="text-muted mb-0">Total Claimed</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card info">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-info bg-opacity-10 text-info rounded-circle p-3">
                                    <i class="fas fa-percentage fa-lg"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4 class="fw-bold mb-0"><?php echo number_format($utilizationPercentage, 1); ?>%</h4>
                                <p class="text-muted mb-0">Utilization Rate</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Update Allocation Form -->
            <div class="form-card">
                <div class="card-header-modern">
                    <h5><i class="fas fa-edit me-2"></i>Update Annual Allocation</h5>
                </div>
                <form method="post" class="row g-3">
                    <input type="hidden" name="update_allocation" value="1">

                    <div class="col-md-6">
                        <label for="coverage_period" class="form-label">Select Coverage Period *</label>
                        <select class="form-control" name="coverage_period" required>
                            <option value="">Choose Coverage Period</option>
                            <?php foreach ($availablePeriods as $period => $display): ?>
                                <option value="<?php echo $period; ?>" <?php echo $period == $currentCoveragePeriod ? 'selected' : ''; ?>>
                                    <?php echo $display; ?> <?php echo $period == $currentCoveragePeriod ? '(Current)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="allocation_limit" class="form-label">Allocation Limit (Rs.) *</label>
                        <input type="number" class="form-control" name="allocation_limit" step="0.01" min="1" placeholder="125000.00" required>
                        <div class="form-text">Amount allocated per employee for the selected year</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save me-2"></i>Update Allocation
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Add New Coverage Period Form -->
            <div class="form-card" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                <div class="card-header-modern" style="background: linear-gradient(135deg, #e9ecef, #dee2e6);">
                    <h5 class="text-success"><i class="fas fa-plus-circle me-2"></i>Add New Coverage Period</h5>
                </div>
                <form method="post" class="row g-3">
                    <input type="hidden" name="update_allocation" value="1">

                    <div class="col-md-3">
                        <label for="start_year" class="form-label">Start Year *</label>
                        <input type="number" class="form-control" id="start_year" min="2026" max="2050" placeholder="2031" required>
                        <div class="form-text">Coverage starts Feb 9</div>
                    </div>

                    <div class="col-md-3">
                        <label for="end_year" class="form-label">End Year *</label>
                        <input type="number" class="form-control" id="end_year" min="2027" max="2051" placeholder="2032" readonly>
                        <div class="form-text">Coverage ends Feb 8</div>
                    </div>

                    <div class="col-md-4">
                        <label for="new_allocation_limit" class="form-label">Allocation Limit (Rs.) *</label>
                        <input type="number" class="form-control" name="allocation_limit" step="0.01" min="1" placeholder="125000.00" required>
                        <div class="form-text">Amount per employee for this period</div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-success w-100" id="addPeriodBtn">
                                <i class="fas fa-plus me-2"></i>Add Period
                            </button>
                        </div>
                    </div>

                    <input type="hidden" name="coverage_period" id="coverage_period_hidden">

                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>New Coverage Period:</strong> <span id="period_preview">Select start year to preview</span>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Existing Allocations Table -->
            <div class="content-card">
                <div class="card-header-modern">
                    <h5>
                        <i class="fas fa-table me-2"></i>Allocation History
                        <span class="badge bg-primary"><?php echo count($allocations); ?> Years</span>
                    </h5>
                </div>
                    <?php if (empty($allocations)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-coins fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Allocations Found</h5>
                            <p class="text-muted">Start by setting an allocation for the current year.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Coverage Period</th>
                                        <th>Allocation Limit</th>
                                        <th>Total Budget</th>
                                        <th>Claims Utilization</th>
                                        <th>Date Range</th>
                                        <th>Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allocations as $allocation): ?>
                                        <?php
                                        $coverage_period = $allocation['coverage_period'];
                                        $limit = $allocation['allocation_limit'];
                                        $totalBudget = $limit * $totalEmployees;

                                        // Get claims for this coverage period
                                        $periodClaims = $db->fetch("SELECT SUM(claimed_amount) as total FROM medical_claims WHERE coverage_period = ?", [$coverage_period]);
                                        $periodClaimsTotal = $periodClaims['total'] ?? 0;
                                        $utilizationPercent = $totalBudget > 0 ? ($periodClaimsTotal / $totalBudget) * 100 : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="period-badge <?php echo $coverage_period == $currentCoveragePeriod ? 'current' : ''; ?>">
                                                    <?php echo $coverage_period; ?>
                                                    <?php if ($coverage_period == $currentCoveragePeriod): ?>
                                                        <i class="fas fa-star ms-1"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong>Rs. <?php echo number_format($limit, 2); ?></strong>
                                                <br><small class="text-muted">per employee</small>
                                            </td>
                                            <td>
                                                Rs. <?php echo number_format($totalBudget, 2); ?>
                                                <br><small class="text-muted"><?php echo number_format($totalEmployees); ?> employees</small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1 me-3">
                                                        <div class="utilization-bar">
                                                            <div class="utilization-fill" style="width: <?php echo min(100, $utilizationPercent); ?>%"></div>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <strong><?php echo number_format($utilizationPercent, 1); ?>%</strong>
                                                        <br><small class="text-muted">Rs. <?php echo number_format($periodClaimsTotal, 0); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php
                                                    $period_parts = explode('-', $coverage_period);
                                                    $start_year = $period_parts[0];
                                                    $end_year = isset($period_parts[1]) ? $period_parts[1] : ($start_year + 1);
                                                    ?>
                                                    Feb 9, <?php echo $start_year; ?> -<br>
                                                    Feb 8, <?php echo $end_year; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($allocation['updated_at'])); ?>
                                                <br><small class="text-muted"><?php echo date('g:i A', strtotime($allocation['updated_at'])); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Pre-fill form when coverage period is selected
        document.querySelector('select[name="coverage_period"]')?.addEventListener('change', function() {
            const coverage_period = this.value;
            if (coverage_period) {
                // Find existing allocation for selected coverage period
                const allocations = <?php echo json_encode($allocations); ?>;
                const existingAllocation = allocations.find(a => a.coverage_period == coverage_period);

                if (existingAllocation) {
                    document.querySelector('input[name="allocation_limit"]').value = existingAllocation.allocation_limit;
                } else {
                    document.querySelector('input[name="allocation_limit"]').value = '';
                }
            }
        });

        // Handle new coverage period form
        document.getElementById('start_year')?.addEventListener('input', function() {
            const startYear = parseInt(this.value);
            if (startYear) {
                const endYear = startYear + 1;
                document.getElementById('end_year').value = endYear;

                const coveragePeriod = startYear + '-' + endYear;
                document.getElementById('coverage_period_hidden').value = coveragePeriod;

                const preview = `${coveragePeriod} (Feb 9, ${startYear} - Feb 8, ${endYear})`;
                document.getElementById('period_preview').textContent = preview;

                // Check if period already exists
                const allocations = <?php echo json_encode($allocations); ?>;
                const exists = allocations.some(a => a.coverage_period === coveragePeriod);

                const addBtn = document.getElementById('addPeriodBtn');
                const previewElement = document.getElementById('period_preview');

                if (exists) {
                    addBtn.disabled = true;
                    addBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Period Exists';
                    addBtn.className = 'btn btn-warning w-100';
                    previewElement.className = 'text-warning';
                    previewElement.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>${preview} - Already exists!`;
                } else {
                    addBtn.disabled = false;
                    addBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Add Period';
                    addBtn.className = 'btn btn-success w-100';
                    previewElement.className = 'text-success';
                    previewElement.innerHTML = `<i class="fas fa-check-circle me-2"></i>${preview} - Ready to add!`;
                }
            } else {
                document.getElementById('end_year').value = '';
                document.getElementById('coverage_period_hidden').value = '';
                document.getElementById('period_preview').textContent = 'Select start year to preview';
                document.getElementById('period_preview').className = '';

                const addBtn = document.getElementById('addPeriodBtn');
                addBtn.disabled = true;
                addBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Add Period';
                addBtn.className = 'btn btn-success w-100';
            }
        });
    </script>
</body>
</html>