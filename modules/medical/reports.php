<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($db, 'medical.reports')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Medical insurance months
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

// Available coverage periods
$availablePeriods = [
    '2023-2024' => '2023-2024 (Feb 9, 2023 - Feb 8, 2024)',
    '2024-2025' => '2024-2025 (Feb 9, 2024 - Feb 8, 2025)',
    '2025-2026' => '2025-2026 (Feb 9, 2025 - Feb 8, 2026)',
    '2026-2027' => '2026-2027 (Feb 9, 2026 - Feb 8, 2027)',
    '2027-2028' => '2027-2028 (Feb 9, 2027 - Feb 8, 2028)',
    '2028-2029' => '2028-2029 (Feb 9, 2028 - Feb 8, 2029)',
    '2029-2030' => '2029-2030 (Feb 9, 2029 - Feb 8, 2030)',
    '2030-2031' => '2030-2031 (Feb 9, 2030 - Feb 8, 2031)'
];

$currentYear = date('Y');
$selectedCoveragePeriod = $_GET['coverage_period'] ?? $currentYear . '-' . ($currentYear + 1);
$selectedMonth = $_GET['month'] ?? '';
$reportType = $_GET['report_type'] ?? 'summary';
$selectedEpf = $_GET['epf_number'] ?? '';

// Get report data based on type
$reportData = [];
$totalStats = [];

try {
    switch ($reportType) {
        case 'summary':
            // Overall medical claims summary
            $totalStats = [
                'total_allocation' => $db->fetch("SELECT allocation_limit FROM medical_allocations WHERE coverage_period = ?", [$selectedCoveragePeriod])['allocation_limit'] ?? 125000,
                'total_claims' => $db->fetch("SELECT COUNT(*) as count FROM medical_claims WHERE coverage_period = ?", [$selectedCoveragePeriod])['count'] ?? 0,
                'total_amount' => $db->fetch("SELECT SUM(claimed_amount) as total FROM medical_claims WHERE coverage_period = ?", [$selectedCoveragePeriod])['total'] ?? 0,
                'active_claimants' => $db->fetch("SELECT COUNT(DISTINCT epf_number) as count FROM medical_claims WHERE coverage_period = ?", [$selectedCoveragePeriod])['count'] ?? 0,
                'spectacles_claims' => $db->fetch("SELECT COUNT(*) as count FROM spectacles_claims WHERE coverage_period = ?", [$selectedCoveragePeriod])['count'] ?? 0,
                'spectacles_amount' => $db->fetch("SELECT SUM(claimed_amount) as total FROM spectacles_claims WHERE coverage_period = ?", [$selectedCoveragePeriod])['total'] ?? 0
            ];

            // Claims by month
            $reportData = $db->fetchAll("
                SELECT
                    month,
                    COUNT(*) as claim_count,
                    SUM(claimed_amount) as total_amount,
                    COUNT(DISTINCT epf_number) as unique_claimants
                FROM medical_claims
                WHERE coverage_period = ?
                GROUP BY month
                ORDER BY month", [$selectedCoveragePeriod]);
            break;

        case 'employee':
            // Employee-wise breakdown
            $reportData = $db->fetchAll("
                SELECT
                    mc.epf_number,
                    e.first_name,
                    e.last_name,
                    l.location_name,
                    COUNT(mc.id) as total_claims,
                    SUM(mc.claimed_amount) as total_amount,
                    (SELECT allocation_limit FROM medical_allocations WHERE coverage_period = ?) as allocation_limit,
                    (SELECT allocation_limit FROM medical_allocations WHERE coverage_period = ?) - SUM(mc.claimed_amount) as balance
                FROM medical_claims mc
                LEFT JOIN employees e ON mc.epf_number = e.epf_number
                LEFT JOIN locations l ON e.location_id = l.id
                WHERE mc.coverage_period = ?
                GROUP BY mc.epf_number, e.first_name, e.last_name, l.location_name
                ORDER BY total_amount DESC", [$selectedCoveragePeriod, $selectedCoveragePeriod, $selectedCoveragePeriod]);
            break;

        case 'monthly':
            // Monthly detailed report
            $whereClause = "WHERE coverage_period = ?";
            $params = [$selectedCoveragePeriod];

            if (!empty($selectedMonth)) {
                $whereClause .= " AND month = ?";
                $params[] = $selectedMonth;
            }

            $reportData = $db->fetchAll("
                SELECT
                    mc.*,
                    e.first_name,
                    e.last_name,
                    l.location_name
                FROM medical_claims mc
                LEFT JOIN employees e ON mc.epf_number = e.epf_number
                LEFT JOIN locations l ON e.location_id = l.id
                $whereClause
                ORDER BY mc.created_at DESC", $params);
            break;

        case 'spectacles':
            // Spectacles claims report
            $reportData = $db->fetchAll("
                SELECT
                    sc.*,
                    e.first_name,
                    e.last_name,
                    l.location_name
                FROM spectacles_claims sc
                LEFT JOIN employees e ON sc.epf_number = e.epf_number
                LEFT JOIN locations l ON e.location_id = l.id
                WHERE sc.coverage_period = ?
                ORDER BY sc.created_at DESC", [$selectedCoveragePeriod]);
            break;

        case 'individual':
            // Individual employee report
            if (!empty($selectedEpf)) {
                $reportData = [
                    'medical_claims' => $db->fetchAll("
                        SELECT * FROM medical_claims
                        WHERE epf_number = ? AND coverage_period = ?
                        ORDER BY created_at DESC", [$selectedEpf, $selectedCoveragePeriod]),
                    'spectacles_claims' => $db->fetchAll("
                        SELECT * FROM spectacles_claims
                        WHERE epf_number = ? AND coverage_period = ?
                        ORDER BY created_at DESC", [$selectedEpf, $selectedCoveragePeriod]),
                    'employee_info' => $db->fetch("
                        SELECT e.*, l.location_name
                        FROM employees e
                        LEFT JOIN locations l ON e.location_id = l.id
                        WHERE e.epf_number = ?", [$selectedEpf])
                ];
            }
            break;
    }
} catch (Exception $e) {
    error_log("Medical reports error: " . $e->getMessage());
}

// Export functionality
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="medical_report_' . $reportType . '_' . $selectedCoveragePeriod . '.csv"');

    $output = fopen('php://output', 'w');

    switch ($reportType) {
        case 'summary':
            fputcsv($output, ['Month', 'Claims Count', 'Total Amount', 'Unique Claimants']);
            foreach ($reportData as $row) {
                fputcsv($output, [$row['month'], $row['claim_count'], $row['total_amount'], $row['unique_claimants']]);
            }
            break;
        case 'employee':
            fputcsv($output, ['EPF Number', 'Name', 'Location', 'Total Claims', 'Total Amount', 'Balance']);
            foreach ($reportData as $row) {
                fputcsv($output, [$row['epf_number'], $row['first_name'] . ' ' . $row['last_name'], $row['location_name'], $row['total_claims'], $row['total_amount'], $row['balance']]);
            }
            break;
        case 'monthly':
            fputcsv($output, ['EPF Number', 'Name', 'Location', 'Month', 'Amount', 'Date Created']);
            foreach ($reportData as $row) {
                fputcsv($output, [$row['epf_number'], $row['first_name'] . ' ' . $row['last_name'], $row['location_name'], $row['month'], $row['claimed_amount'], $row['created_at']]);
            }
            break;
        case 'spectacles':
            fputcsv($output, ['EPF Number', 'Name', 'Location', 'Amount', 'Date Created']);
            foreach ($reportData as $row) {
                fputcsv($output, [$row['epf_number'], $row['first_name'] . ' ' . $row['last_name'], $row['location_name'], $row['claimed_amount'], $row['created_at']]);
            }
            break;
    }

    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Medical Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.15s ease-in-out;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
        }

        .filters-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .btn-custom {
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
        }

        .table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
        }

        .table thead {
            background: var(--primary-color);
            color: white;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-content">
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2 class="mb-0">
                            <i class="fas fa-chart-line me-3"></i>
                            Medical Insurance Reports
                        </h2>
                        <p class="mb-0 opacity-75">Comprehensive medical claims analysis and reporting</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="index.php" class="btn btn-outline-light btn-custom">
                            <i class="fas fa-arrow-left me-2"></i>Back to Medical
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="report_type" class="form-label">Report Type</label>
                        <select name="report_type" id="report_type" class="form-select" onchange="this.form.submit()">
                            <option value="summary" <?= $reportType === 'summary' ? 'selected' : '' ?>>Summary Report</option>
                            <option value="employee" <?= $reportType === 'employee' ? 'selected' : '' ?>>Employee-wise</option>
                            <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Monthly Details</option>
                            <option value="spectacles" <?= $reportType === 'spectacles' ? 'selected' : '' ?>>Spectacles Claims</option>
                            <option value="individual" <?= $reportType === 'individual' ? 'selected' : '' ?>>Individual Report</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="coverage_period" class="form-label">Coverage Period</label>
                        <select name="coverage_period" id="coverage_period" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($availablePeriods as $period => $label): ?>
                                <option value="<?= $period ?>" <?= $selectedCoveragePeriod === $period ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($reportType === 'monthly'): ?>
                    <div class="col-md-3">
                        <label for="month" class="form-label">Month (Optional)</label>
                        <select name="month" id="month" class="form-select" onchange="this.form.submit()">
                            <option value="">All Months</option>
                            <?php foreach ($medicalMonths as $month): ?>
                                <option value="<?= $month ?>" <?= $selectedMonth === $month ? 'selected' : '' ?>>
                                    <?= $month ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if ($reportType === 'individual'): ?>
                    <div class="col-md-3">
                        <label for="epf_number" class="form-label">EPF Number</label>
                        <input type="text" name="epf_number" id="epf_number" class="form-control"
                               value="<?= htmlspecialchars($selectedEpf) ?>" placeholder="Enter EPF Number">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-custom d-block">
                            <i class="fas fa-search me-2"></i>Generate Report
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"
                           class="btn btn-success btn-custom d-block">
                            <i class="fas fa-download me-2"></i>Export CSV
                        </a>
                    </div>
                </form>
            </div>

            <!-- Report Content -->
            <?php if ($reportType === 'summary'): ?>
                <!-- Summary Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h5 class="text-primary">Total Allocation</h5>
                            <h3>LKR <?= number_format($totalStats['total_allocation'], 2) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h5 class="text-success">Total Claims</h5>
                            <h3><?= $totalStats['total_claims'] ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h5 class="text-warning">Claimed Amount</h5>
                            <h3>LKR <?= number_format($totalStats['total_amount'], 2) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h5 class="text-info">Active Claimants</h5>
                            <h3><?= $totalStats['active_claimants'] ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Monthly Breakdown Chart -->
                <div class="chart-container">
                    <h4 class="mb-3">Monthly Claims Breakdown</h4>
                    <canvas id="monthlyChart" height="100"></canvas>
                </div>

                <!-- Monthly Data Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Monthly Claims Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Claims Count</th>
                                        <th>Total Amount</th>
                                        <th>Unique Claimants</th>
                                        <th>Avg. per Claim</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['month']) ?></td>
                                        <td><span class="badge bg-primary"><?= $row['claim_count'] ?></span></td>
                                        <td>LKR <?= number_format($row['total_amount'], 2) ?></td>
                                        <td><?= $row['unique_claimants'] ?></td>
                                        <td>LKR <?= $row['claim_count'] > 0 ? number_format($row['total_amount'] / $row['claim_count'], 2) : '0.00' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($reportType === 'employee'): ?>
                <!-- Employee-wise Report -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Employee-wise Claims Report</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>EPF Number</th>
                                        <th>Employee Name</th>
                                        <th>Location</th>
                                        <th>Total Claims</th>
                                        <th>Total Amount</th>
                                        <th>Balance</th>
                                        <th>Utilization %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                    <?php
                                    $utilization = ($row['total_amount'] / $row['allocation_limit']) * 100;
                                    $utilizationClass = $utilization > 80 ? 'danger' : ($utilization > 60 ? 'warning' : 'success');
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['epf_number']) ?></td>
                                        <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                        <td><?= htmlspecialchars($row['location_name']) ?></td>
                                        <td><span class="badge bg-primary"><?= $row['total_claims'] ?></span></td>
                                        <td>LKR <?= number_format($row['total_amount'], 2) ?></td>
                                        <td>LKR <?= number_format($row['balance'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $utilizationClass ?>">
                                                <?= number_format($utilization, 1) ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($reportType === 'monthly'): ?>
                <!-- Monthly Details -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            Monthly Claims Details
                            <?= !empty($selectedMonth) ? ' - ' . $selectedMonth : '' ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>EPF Number</th>
                                        <th>Employee Name</th>
                                        <th>Location</th>
                                        <th>Month</th>
                                        <th>Amount</th>
                                        <th>Date Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['epf_number']) ?></td>
                                        <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                        <td><?= htmlspecialchars($row['location_name']) ?></td>
                                        <td><?= htmlspecialchars($row['month']) ?></td>
                                        <td>LKR <?= number_format($row['claimed_amount'], 2) ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($reportType === 'spectacles'): ?>
                <!-- Spectacles Claims -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Spectacles Claims Report</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>EPF Number</th>
                                        <th>Employee Name</th>
                                        <th>Location</th>
                                        <th>Amount</th>
                                        <th>Date Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['epf_number']) ?></td>
                                        <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                        <td><?= htmlspecialchars($row['location_name']) ?></td>
                                        <td>LKR <?= number_format($row['claimed_amount'], 2) ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($reportType === 'individual' && !empty($selectedEpf)): ?>
                <!-- Individual Employee Report -->
                <?php if (isset($reportData['employee_info'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Employee Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>EPF Number:</strong> <?= htmlspecialchars($reportData['employee_info']['epf_number']) ?></p>
                                        <p><strong>Name:</strong> <?= htmlspecialchars($reportData['employee_info']['first_name'] . ' ' . $reportData['employee_info']['last_name']) ?></p>
                                        <p><strong>Location:</strong> <?= htmlspecialchars($reportData['employee_info']['location_name']) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Email:</strong> <?= htmlspecialchars($reportData['employee_info']['email']) ?></p>
                                        <p><strong>Department:</strong> <?= htmlspecialchars($reportData['employee_info']['department']) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medical Claims -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Medical Claims</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($reportData['medical_claims'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Amount</th>
                                            <th>Date Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['medical_claims'] as $claim): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($claim['month']) ?></td>
                                            <td>LKR <?= number_format($claim['claimed_amount'], 2) ?></td>
                                            <td><?= date('Y-m-d H:i', strtotime($claim['created_at'])) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No medical claims found for this employee in the selected period.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Spectacles Claims -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Spectacles Claims</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($reportData['spectacles_claims'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Amount</th>
                                            <th>Date Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['spectacles_claims'] as $claim): ?>
                                        <tr>
                                            <td>LKR <?= number_format($claim['claimed_amount'], 2) ?></td>
                                            <td><?= date('Y-m-d H:i', strtotime($claim['created_at'])) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No spectacles claims found for this employee in the selected period.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

            <?php elseif ($reportType === 'individual' && empty($selectedEpf)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Please enter an EPF number to generate an individual report.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($reportType === 'summary' && !empty($reportData)): ?>
    <script>
        // Monthly Claims Chart
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($reportData, 'month')) ?>,
                datasets: [{
                    label: 'Claims Amount (LKR)',
                    data: <?= json_encode(array_column($reportData, 'total_amount')) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }, {
                    label: 'Claims Count',
                    data: <?= json_encode(array_column($reportData, 'claim_count')) ?>,
                    type: 'line',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 2,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Medical Claims Analysis'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>