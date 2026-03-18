<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($db, 'medical.balance_checker')) {
    header('Location: ../../dashboard.php');
    exit;
}

$currentYear = date('Y');

// Get search parameters for admins/superadmins
$searchEpf = $_GET['search_epf'] ?? '';

// Determine access level and get employee data
$employees = [];
$showSearch = false;

if ($user['account_type'] === 'superadmin') {
    // Superadmin can view all employees
    $showSearch = true;
    if ($searchEpf) {
        $employees = $db->fetchAll("
            SELECT e.epf_number, e.name, e.gender, e.age, l.location_name
            FROM employees e
            LEFT JOIN locations l ON e.location_id = l.id
            WHERE e.epf_number LIKE ? AND e.is_active = 1
            ORDER BY e.name
        ", ["%$searchEpf%"]);
    } else {
        // Show all employees
        $employees = $db->fetchAll("
            SELECT e.epf_number, e.name, e.gender, e.age, l.location_name
            FROM employees e
            LEFT JOIN locations l ON e.location_id = l.id
            WHERE e.is_active = 1
            ORDER BY e.name
        ");
    }
} elseif ($user['account_type'] === 'admin') {
    // Admin can view employees from their location
    $showSearch = true;
    $locationCondition = "l.location_name = ?";
    if ($searchEpf) {
        $employees = $db->fetchAll("
            SELECT e.epf_number, e.name, e.gender, e.age, l.location_name
            FROM employees e
            JOIN locations l ON e.location_id = l.id
            WHERE $locationCondition AND e.epf_number LIKE ? AND e.is_active = 1
            ORDER BY e.name
        ", [$user['location'], "%$searchEpf%"]);
    } else {
        $employees = $db->fetchAll("
            SELECT e.epf_number, e.name, e.gender, e.age, l.location_name
            FROM employees e
            JOIN locations l ON e.location_id = l.id
            WHERE $locationCondition AND e.is_active = 1
            ORDER BY e.name
        ", [$user['location']]);
    }
} else {
    // Regular users see only their own balance
    $employees = $db->fetchAll("
        SELECT e.epf_number, e.name, e.gender, e.age, l.location_name
        FROM employees e
        LEFT JOIN locations l ON e.location_id = l.id
        WHERE e.epf_number = ?
    ", [$user['epf_number']]);
}

// Get current year allocation
$currentAllocation = 125000.00;
try {
    $currentCoveragePeriod = $currentYear . '-' . ($currentYear + 1);
    $allocation = $db->fetch("SELECT allocation_limit FROM medical_allocations WHERE coverage_period = ?", [$currentCoveragePeriod]);
    if ($allocation) {
        $currentAllocation = $allocation['allocation_limit'];
    }
} catch (Exception $e) {
    error_log("Failed to get allocation: " . $e->getMessage());
}

// Calculate balances for all employees
$balanceData = [];
foreach ($employees as $employee) {
    $epf = $employee['epf_number'];

    // Get medical claims total
    $medicalClaims = $db->fetch("
        SELECT SUM(claimed_amount) as total
        FROM medical_claims
        WHERE epf_number = ? AND coverage_period = ?
    ", [$epf, $currentCoveragePeriod]);

    $totalClaimed = $medicalClaims['total'] ?? 0;
    $balance = $currentAllocation - $totalClaimed;
    $utilizationPercent = $currentAllocation > 0 ? ($totalClaimed / $currentAllocation) * 100 : 0;

    // Get month-wise breakdown ordered by medical insurance calendar
    $monthlyBreakdown = $db->fetchAll("
        SELECT month, SUM(claimed_amount) as amount
        FROM medical_claims
        WHERE epf_number = ? AND coverage_period = ?
        GROUP BY month
        ORDER BY CASE
            WHEN month = 'February (9-28/29)' THEN 1
            WHEN month = 'March' THEN 2
            WHEN month = 'April' THEN 3
            WHEN month = 'May' THEN 4
            WHEN month = 'June' THEN 5
            WHEN month = 'July' THEN 6
            WHEN month = 'August' THEN 7
            WHEN month = 'September' THEN 8
            WHEN month = 'October' THEN 9
            WHEN month = 'November' THEN 10
            WHEN month = 'December' THEN 11
            WHEN month = 'January' THEN 12
            WHEN month = 'February (1-8)' THEN 13
            ELSE 99
        END
    ", [$epf, $currentCoveragePeriod]);

    // Get spectacles claims (separate from medical insurance)
    $spectaclesClaims = $db->fetch("
        SELECT SUM(claimed_amount) as total
        FROM spectacles_claims
        WHERE epf_number = ? AND coverage_period = ?
    ", [$epf, $currentCoveragePeriod]);

    $spectaclesTotal = $spectaclesClaims['total'] ?? 0;

    $balanceData[] = [
        'employee' => $employee,
        'allocation' => $currentAllocation,
        'total_claimed' => $totalClaimed,
        'balance' => $balance,
        'utilization_percent' => $utilizationPercent,
        'monthly_breakdown' => $monthlyBreakdown,
        'spectacles_total' => $spectaclesTotal
    ];
}

// Medical months for reference
$medicalMonths = [
    'February (9-28/29)',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
    'January',
    'February (1-8)'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Balance Checker</title>
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

        .balance-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            border: 1px solid #e3f2fd;
        }

        .balance-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .balance-amount {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }

        .utilization-bar {
            height: 12px;
            background-color: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .utilization-fill {
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 6px;
        }

        .utilization-low { background: linear-gradient(45deg, #28a745, #20c997); }
        .utilization-medium { background: linear-gradient(45deg, #ffc107, #fd7e14); }
        .utilization-high { background: linear-gradient(45deg, #dc3545, #e83e8c); }

        .monthly-breakdown {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .month-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #dee2e6;
        }

        .month-item:last-child {
            border-bottom: none;
        }

        .search-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .spectacles-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
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

            .balance-amount {
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
                        <h1><i class="fas fa-calculator me-3"></i>Balance Checker</h1>
                        <p>Check remaining medical insurance balance and coverage limits</p>
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
                    <li class="breadcrumb-item active">Balance Checker</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4 mb-0">Balance Overview</h2>
                <div>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>

            <!-- Coverage Period Info -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6><i class="fas fa-calendar-alt me-2"></i>Coverage Period Information</h6>
                                <p class="mb-0">
                                    Current coverage: <strong>February 9, <?php echo $currentYear; ?> - February 8, <?php echo $currentYear + 1; ?></strong> |
                                    Annual Allocation: <strong>Rs. <?php echo number_format($currentAllocation, 2); ?></strong> per employee
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <span class="badge bg-primary px-3 py-2">Year <?php echo $currentYear; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($showSearch): ?>
            <!-- Search Section -->
            <div class="form-card">
                <div class="card-header-modern">
                    <h5><i class="fas fa-search me-2"></i>Search Employee Balance</h5>
                </div>
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="search_epf" placeholder="Enter EPF Number (e.g., 484, 443)" value="<?php echo htmlspecialchars($searchEpf); ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                    <div class="col-md-3">
                        <a href="balance_checker.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Balance Cards -->
            <?php if (empty($balanceData)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-times fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Employee Found</h5>
                    <p class="text-muted">
                        <?php if ($searchEpf): ?>
                            No employee found with EPF number "<?php echo htmlspecialchars($searchEpf); ?>".
                        <?php else: ?>
                            No employee data available.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($balanceData as $data): ?>
                        <?php
                        $employee = $data['employee'];
                        $utilizationClass = 'utilization-low';
                        if ($data['utilization_percent'] > 75) {
                            $utilizationClass = 'utilization-high';
                        } elseif ($data['utilization_percent'] > 50) {
                            $utilizationClass = 'utilization-medium';
                        }
                        ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="balance-card">
                                <div class="balance-header text-center">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($employee['name']); ?></h5>
                                    <p class="mb-0">
                                        <i class="fas fa-id-badge me-1"></i><?php echo htmlspecialchars($employee['epf_number']); ?> |
                                        <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($employee['location_name'] ?? 'N/A'); ?>
                                    </p>
                                </div>

                                <!-- Balance Summary -->
                                <div class="text-center">
                                    <div class="row">
                                        <div class="col-4">
                                            <h6 class="text-muted mb-1">Allocation</h6>
                                            <p class="h5 text-primary mb-0">Rs. <?php echo number_format($data['allocation'], 0); ?></p>
                                        </div>
                                        <div class="col-4">
                                            <h6 class="text-muted mb-1">Used</h6>
                                            <p class="h5 text-warning mb-0">Rs. <?php echo number_format($data['total_claimed'], 0); ?></p>
                                        </div>
                                        <div class="col-4">
                                            <h6 class="text-muted mb-1">Balance</h6>
                                            <p class="h5 <?php echo $data['balance'] >= 0 ? 'text-success' : 'text-danger'; ?> mb-0">
                                                Rs. <?php echo number_format($data['balance'], 0); ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Utilization Bar -->
                                    <div class="utilization-bar">
                                        <div class="utilization-fill <?php echo $utilizationClass; ?>" style="width: <?php echo min(100, max(0, $data['utilization_percent'])); ?>%"></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Utilization</small>
                                        <strong class="<?php echo $data['utilization_percent'] > 90 ? 'text-danger' : ($data['utilization_percent'] > 70 ? 'text-warning' : 'text-success'); ?>">
                                            <?php echo number_format($data['utilization_percent'], 1); ?>%
                                        </strong>
                                    </div>
                                </div>

                                <!-- Spectacles Information -->
                                <?php if ($data['spectacles_total'] > 0): ?>
                                    <div class="spectacles-info">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-glasses me-2"></i>
                                                <strong>Spectacles Claims</strong>
                                            </div>
                                            <div>
                                                <strong>Rs. <?php echo number_format($data['spectacles_total'], 2); ?></strong>
                                            </div>
                                        </div>
                                        <small>Separate from medical insurance limit</small>
                                    </div>
                                <?php endif; ?>

                                <!-- Monthly Breakdown -->
                                <?php if (!empty($data['monthly_breakdown'])): ?>
                                    <div class="monthly-breakdown">
                                        <h6><i class="fas fa-calendar me-2"></i>Monthly Breakdown</h6>
                                        <?php foreach ($data['monthly_breakdown'] as $month): ?>
                                            <div class="month-item">
                                                <div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($month['month']); ?></small>
                                                </div>
                                                <div>
                                                    <strong>Rs. <?php echo number_format($month['amount'], 2); ?></strong>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted mt-3">
                                        <i class="fas fa-info-circle me-2"></i>No claims submitted yet for <?php echo $currentYear; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Summary Statistics -->
            <?php if (!empty($balanceData) && $showSearch): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="content-card">
                            <div class="card-header-modern">
                                <h5><i class="fas fa-chart-bar me-2"></i>Summary Statistics</h5>
                            </div>
                                <?php
                                $totalEmployees = count($balanceData);
                                $totalAllocated = $totalEmployees * $currentAllocation;
                                $totalClaimed = array_sum(array_column($balanceData, 'total_claimed'));
                                $totalBalance = $totalAllocated - $totalClaimed;
                                $avgUtilization = $totalEmployees > 0 ? array_sum(array_column($balanceData, 'utilization_percent')) / $totalEmployees : 0;
                                $totalSpectacles = array_sum(array_column($balanceData, 'spectacles_total'));
                                ?>
                                <div class="row">
                                    <div class="col-md-3">
                                        <h6>Total Employees</h6>
                                        <h4 class="text-primary"><?php echo number_format($totalEmployees); ?></h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6>Total Allocated</h6>
                                        <h4 class="text-info">Rs. <?php echo number_format($totalAllocated, 0); ?></h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6>Total Claimed</h6>
                                        <h4 class="text-warning">Rs. <?php echo number_format($totalClaimed, 0); ?></h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6>Remaining Balance</h6>
                                        <h4 class="text-success">Rs. <?php echo number_format($totalBalance, 0); ?></h4>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-12">
                                        <h6>Average Utilization</h6>
                                        <div class="d-flex align-items-center">
                                            <div class="utilization-bar flex-grow-1 me-3">
                                                <div class="utilization-fill <?php echo $avgUtilization > 75 ? 'utilization-high' : ($avgUtilization > 50 ? 'utilization-medium' : 'utilization-low'); ?>" style="width: <?php echo min(100, $avgUtilization); ?>%"></div>
                                            </div>
                                            <strong><?php echo number_format($avgUtilization, 1); ?>%</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus search input
        document.querySelector('input[name="search_epf"]')?.focus();
    </script>
</body>
</html>