<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($database, 'offboarding.retention_analytics')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year = (int)$_POST['year'];
    $employee_count_start = (int)$_POST['employee_count_start'];
    $employee_count_end = (int)$_POST['employee_count_end'];
    $total_resignations = (int)$_POST['total_resignations'];
    $new_hires_during_period = (int)$_POST['new_hires_during_period'];

    // Calculate values
    $average_employees = ($employee_count_start + $employee_count_end) / 2;
    $retention_rate = (($employee_count_end - $new_hires_during_period) / $employee_count_start) * 100;
    $turnover_rate = ($total_resignations / $average_employees) * 100;

    try {
        // First, try to create table if it doesn't exist
        $create_table = "
        CREATE TABLE IF NOT EXISTS retention_analytics (
            id INT PRIMARY KEY AUTO_INCREMENT,
            year INT NOT NULL UNIQUE,
            employee_count_start INT NOT NULL,
            employee_count_end INT NOT NULL,
            total_resignations INT NOT NULL,
            new_hires_during_period INT NOT NULL DEFAULT 0,
            average_employees DECIMAL(10,2) NOT NULL,
            retention_rate DECIMAL(5,2) NOT NULL,
            turnover_rate DECIMAL(5,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_year (year)
        )";

        $database->execute($create_table);

        // Insert or update data
        $query = "INSERT INTO retention_analytics
                 (year, employee_count_start, employee_count_end, total_resignations, new_hires_during_period, average_employees, retention_rate, turnover_rate)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                 employee_count_start = VALUES(employee_count_start),
                 employee_count_end = VALUES(employee_count_end),
                 total_resignations = VALUES(total_resignations),
                 new_hires_during_period = VALUES(new_hires_during_period),
                 average_employees = VALUES(average_employees),
                 retention_rate = VALUES(retention_rate),
                 turnover_rate = VALUES(turnover_rate)";

        $database->execute($query, [
            $year, $employee_count_start, $employee_count_end, $total_resignations,
            $new_hires_during_period, $average_employees, $retention_rate, $turnover_rate
        ]);

        $success_message = "Retention analytics data for $year has been saved successfully!";
    } catch (Exception $e) {
        $error_message = "Error saving data: " . $e->getMessage();
    }
}

// Get all retention analytics data
try {
    // Try to create table first in case it doesn't exist
    $create_table = "
    CREATE TABLE IF NOT EXISTS retention_analytics (
        id INT PRIMARY KEY AUTO_INCREMENT,
        year INT NOT NULL UNIQUE,
        employee_count_start INT NOT NULL,
        employee_count_end INT NOT NULL,
        total_resignations INT NOT NULL,
        new_hires_during_period INT NOT NULL DEFAULT 0,
        average_employees DECIMAL(10,2) NOT NULL,
        retention_rate DECIMAL(5,2) NOT NULL,
        turnover_rate DECIMAL(5,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_year (year)
    )";

    $database->execute($create_table);

    $analytics_query = "SELECT * FROM retention_analytics ORDER BY year DESC";
    $analytics_data = $database->fetchAll($analytics_query);
} catch (Exception $e) {
    $analytics_data = [];
}

// Get current year
$current_year = date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Retention Analytics</title>
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

        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
        }

        .analytics-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #e1e5e9;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #003d82);
            transform: translateY(-1px);
        }

        .stats-row {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .stats-label {
            font-weight: 600;
            color: #495057;
        }

        .stats-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #e9ecef;
        }

        .retention-rate {
            color: var(--success-color);
            font-weight: 700;
        }

        .turnover-rate {
            color: var(--danger-color);
            font-weight: 700;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin-top: 2rem;
        }

        .formula-card {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1rem 0;
        }

        .formula-title {
            font-weight: 700;
            color: #1565c0;
            margin-bottom: 0.5rem;
        }

        .formula-text {
            font-family: 'Courier New', monospace;
            background: white;
            padding: 0.5rem;
            border-radius: 4px;
            border-left: 4px solid #2196f3;
        }

        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: 0;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: var(--primary-color);
            background-color: #f8f9fa;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: white;
            border-bottom: 3px solid var(--primary-color);
            border-top: none;
            border-left: none;
            border-right: none;
        }

        .tab-content {
            background: white;
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

            .nav-tabs .nav-link {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
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
                        <h1><i class="fas fa-chart-pie me-3"></i>Retention Analytics</h1>
                        <p>Analyze employee retention rates and identify improvement opportunities</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Employee Offboarding</a></li>
                    <li class="breadcrumb-item active">Retention Analytics</li>
                </ol>
            </nav>

            <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Tabs Navigation -->
            <div class="analytics-card">
                <div class="d-flex justify-content-between align-items-center">
                    <ul class="nav nav-tabs" id="analyticsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="data-entry-tab" data-bs-toggle="tab" data-bs-target="#data-entry" type="button" role="tab">
                                <i class="fas fa-keyboard me-2"></i>Enter Annual Data
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">
                                <i class="fas fa-chart-bar me-2"></i>Historical Analytics Data
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="analyticsTabContent">
                    <!-- Data Entry Tab -->
                    <div class="tab-pane fade show active" id="data-entry" role="tabpanel">
                        <div class="p-4">
                            <!-- Formula Cards -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="formula-card">
                                        <div class="formula-title">A. Retention Rate Formula</div>
                                        <div class="formula-text">
                                            (Employees at End - New Hires) / Employees at Start × 100
                                        </div>
                                        <small class="text-muted mt-2 d-block">
                                            Example: (85 / 100) × 100 = 85% retention
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="formula-card">
                                        <div class="formula-title">B. Turnover Rate Formula</div>
                                        <div class="formula-text">
                                            Total Resignations / Average Number of Employees × 100
                                        </div>
                                        <small class="text-muted mt-2 d-block">
                                            Average = (Start Count + End Count) / 2
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Data Input Form -->
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="year" class="form-label">Year</label>
                                            <select name="year" id="year" class="form-control" required>
                                                <option value="">Select Year</option>
                                                <?php for($y = 2019; $y <= $current_year + 2; $y++): ?>
                                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="employee_count_start" class="form-label">Employee Count at 01.01.Year</label>
                                            <input type="number" name="employee_count_start" id="employee_count_start" class="form-control" required min="1">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="employee_count_end" class="form-label">Employee Count at 31.12.Year</label>
                                            <input type="number" name="employee_count_end" id="employee_count_end" class="form-control" required min="1">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="total_resignations" class="form-label">Total Resignations at 31.12.Year</label>
                                            <input type="number" name="total_resignations" id="total_resignations" class="form-control" required min="0">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="new_hires_during_period" class="form-label">New Hires During Period</label>
                                            <input type="number" name="new_hires_during_period" id="new_hires_during_period" class="form-control" required min="0">
                                        </div>
                                    </div>
                                </div>

                                <!-- Live Calculation Preview -->
                                <div class="stats-row" id="calculation-preview" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="stats-label">Average Employees</div>
                                            <div class="stats-value" id="avg-employees">-</div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stats-label">Retention Rate</div>
                                            <div class="stats-value retention-rate" id="retention-rate">-</div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stats-label">Turnover Rate</div>
                                            <div class="stats-value turnover-rate" id="turnover-rate">-</div>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-save me-2"></i>Save Data
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Historical Analytics Tab -->
                    <div class="tab-pane fade" id="analytics" role="tabpanel">
                        <div class="p-4">
                            <?php if (!empty($analytics_data)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Year</th>
                                                <th>Start Count</th>
                                                <th>End Count</th>
                                                <th>Resignations</th>
                                                <th>New Hires</th>
                                                <th>Avg Employees</th>
                                                <th>Retention Rate</th>
                                                <th>Turnover Rate</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics_data as $data): ?>
                                            <tr>
                                                <td><strong><?php echo $data['year']; ?></strong></td>
                                                <td><?php echo number_format($data['employee_count_start']); ?></td>
                                                <td><?php echo number_format($data['employee_count_end']); ?></td>
                                                <td><?php echo number_format($data['total_resignations']); ?></td>
                                                <td><?php echo number_format($data['new_hires_during_period']); ?></td>
                                                <td><?php echo number_format($data['average_employees'], 1); ?></td>
                                                <td class="retention-rate"><?php echo number_format($data['retention_rate'], 2); ?>%</td>
                                                <td class="turnover-rate"><?php echo number_format($data['turnover_rate'], 2); ?>%</td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary edit-btn"
                                                            data-year="<?php echo $data['year']; ?>"
                                                            data-start="<?php echo $data['employee_count_start']; ?>"
                                                            data-end="<?php echo $data['employee_count_end']; ?>"
                                                            data-resignations="<?php echo $data['total_resignations']; ?>"
                                                            data-hires="<?php echo $data['new_hires_during_period']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Charts -->
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="chart-container">
                                            <canvas id="retentionChart"></canvas>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="chart-container">
                                            <canvas id="turnoverChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-chart-line fa-3x mb-3"></i>
                                    <h5>No Analytics Data Available</h5>
                                    <p>Start by entering your first year's data in the "Enter Annual Data" tab</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal functions
        function showInfoModal() {
            var infoModalEl = document.getElementById('infoModal');
            if (infoModalEl) {
                var infoModal = new bootstrap.Modal(infoModalEl);
                infoModal.show();
            }
        }

        // Live calculation
        function updateCalculations() {
            const start = parseInt(document.getElementById('employee_count_start').value) || 0;
            const end = parseInt(document.getElementById('employee_count_end').value) || 0;
            const resignations = parseInt(document.getElementById('total_resignations').value) || 0;
            const newHires = parseInt(document.getElementById('new_hires_during_period').value) || 0;

            if (start > 0 && end > 0) {
                const avgEmployees = (start + end) / 2;
                const retentionRate = ((end - newHires) / start) * 100;
                const turnoverRate = (resignations / avgEmployees) * 100;

                document.getElementById('avg-employees').textContent = avgEmployees.toFixed(1);
                document.getElementById('retention-rate').textContent = retentionRate.toFixed(2) + '%';
                document.getElementById('turnover-rate').textContent = turnoverRate.toFixed(2) + '%';
                document.getElementById('calculation-preview').style.display = 'block';
            } else {
                document.getElementById('calculation-preview').style.display = 'none';
            }
        }

        // Add event listeners for live calculation
        ['employee_count_start', 'employee_count_end', 'total_resignations', 'new_hires_during_period'].forEach(id => {
            document.getElementById(id).addEventListener('input', updateCalculations);
        });

        // Edit functionality
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Switch to data entry tab
                const dataEntryTab = new bootstrap.Tab(document.getElementById('data-entry-tab'));
                dataEntryTab.show();

                // Populate form fields
                setTimeout(() => {
                    document.getElementById('year').value = this.dataset.year;
                    document.getElementById('employee_count_start').value = this.dataset.start;
                    document.getElementById('employee_count_end').value = this.dataset.end;
                    document.getElementById('total_resignations').value = this.dataset.resignations;
                    document.getElementById('new_hires_during_period').value = this.dataset.hires;
                    updateCalculations();
                    document.querySelector('form').scrollIntoView({behavior: 'smooth'});
                }, 100);
            });
        });

        // Charts
        <?php if (!empty($analytics_data)): ?>
        const chartData = <?php echo json_encode(array_reverse($analytics_data)); ?>;

        // Common chart options
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#333',
                    bodyColor: '#333',
                    borderColor: '#ddd',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + '%';
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'category',
                    title: {
                        display: true,
                        text: 'Year',
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        font: {
                            size: 10,
                            weight: 'bold'
                        },
                        maxTicksLimit: 10
                    }
                },
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Percentage (%)',
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        font: {
                            size: 10
                        },
                        stepSize: 20,
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            elements: {
                point: {
                    hoverRadius: 6
                }
            }
        };

        // Retention Rate Chart
        const retentionCtx = document.getElementById('retentionChart').getContext('2d');
        new Chart(retentionCtx, {
            type: 'line',
            data: {
                labels: chartData.map(d => d.year.toString()),
                datasets: [{
                    label: 'Retention Rate',
                    data: chartData.map(d => parseFloat(d.retention_rate)),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderWidth: 3,
                    pointRadius: 6,
                    pointBackgroundColor: '#28a745',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    title: {
                        display: true,
                        text: 'Employee Retention Trend',
                        font: {
                            size: 14,
                            weight: 'bold'
                        },
                        color: '#28a745',
                        padding: 15
                    }
                }
            }
        });

        // Turnover Rate Chart
        const turnoverCtx = document.getElementById('turnoverChart').getContext('2d');
        new Chart(turnoverCtx, {
            type: 'line',
            data: {
                labels: chartData.map(d => d.year.toString()),
                datasets: [{
                    label: 'Turnover Rate',
                    data: chartData.map(d => parseFloat(d.turnover_rate)),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderWidth: 3,
                    pointRadius: 6,
                    pointBackgroundColor: '#dc3545',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    title: {
                        display: true,
                        text: 'Employee Turnover Trend',
                        font: {
                            size: 14,
                            weight: 'bold'
                        },
                        color: '#dc3545',
                        padding: 15
                    }
                }
            }
        });
        <?php endif; ?>
    </script>

    <!-- Info Modal (always available) -->
    <div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="infoModalLabel">
                        <i class="fas fa-info-circle me-2"></i>Why Don't Retention & Turnover Rates Add to 100%?
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>These are different types of measurements that serve different purposes!</strong>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>Retention Rate</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Purpose:</strong> Measures what percentage of <em>original employees stayed</em></p>
                                    <p><strong>Formula:</strong><br>
                                    <code>(End Count - New Hires) / Start Count × 100</code></p>
                                    <p><strong>Question it answers:</strong><br>
                                    "How well did we keep our original team?"</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0"><i class="fas fa-sign-out-alt me-2"></i>Turnover Rate</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Purpose:</strong> Measures what percentage of <em>average workforce left</em></p>
                                    <p><strong>Formula:</strong><br>
                                    <code>Total Resignations / Average Employees × 100</code></p>
                                    <p><strong>Question it answers:</strong><br>
                                    "How much churn did we experience relative to our workforce size?"</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6><i class="fas fa-calculator me-2"></i>Why They Don't Add to 100%:</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <strong>Different denominators:</strong> Retention uses "start count", Turnover uses "average count"
                            </li>
                            <li class="list-group-item">
                                <strong>Different time perspectives:</strong> Retention focuses on original employees, Turnover on overall workforce activity
                            </li>
                            <li class="list-group-item">
                                <strong>New hires affect the math:</strong> Adding new employees changes the dynamics
                            </li>
                        </ul>
                    </div>

                    <div class="mt-4 p-3 bg-light rounded">
                        <h6><i class="fas fa-example me-2"></i>Example with Your Data:</h6>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="border-end">
                                    <strong class="text-success">Retention Rate</strong><br>
                                    <span class="h4 text-success">86.84%</span><br>
                                    <small>of original staff stayed</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border-end">
                                    <strong class="text-danger">Turnover Rate</strong><br>
                                    <span class="h4 text-danger">11.11%</span><br>
                                    <small>of avg workforce left</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <strong class="text-muted">Total</strong><br>
                                <span class="h4 text-muted">≠ 100%</span><br>
                                <small>and that's normal!</small>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-chart-pie me-2"></i>What WOULD Add to 100%?</h6>
                            <p class="mb-2">If you wanted complementary percentages:</p>
                            <ul class="mb-0">
                                <li><strong>Retention Rate:</strong> 86.84% (stayed from original)</li>
                                <li><strong>Attrition Rate:</strong> 10.53% (left from original)</li>
                                <li><strong>Growth Rate:</strong> 2.63% (new hires added)</li>
                                <li class="text-success"><strong>Total:</strong> 100% ✓</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        <i class="fas fa-check me-2"></i>Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>