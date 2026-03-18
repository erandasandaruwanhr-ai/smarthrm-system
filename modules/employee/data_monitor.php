<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/location_access.php';

// Check if user has permission to access data monitor
$db = new Database();
if (!hasModulePermission($db, 'employee.data_monitor') && !hasAdminAccess()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();

// Get filter parameters
$location_filter = $_GET['location'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$department_filter = $_GET['department'] ?? '';

// Get accessible employees based on location/department access
$accessible_employees = getAccessibleEmployeesByLocation($db, $user);

// Apply additional filters if specified
if ($location_filter) {
    $accessible_employees = array_filter($accessible_employees, function($emp) use ($location_filter) {
        return $emp['location_id'] == $location_filter;
    });
}

if ($gender_filter) {
    $accessible_employees = array_filter($accessible_employees, function($emp) use ($gender_filter) {
        return $emp['gender'] == $gender_filter;
    });
}

if ($department_filter) {
    $accessible_employees = array_filter($accessible_employees, function($emp) use ($department_filter) {
        return $emp['department'] == $department_filter;
    });
}

// 2.3.1 Gender distribution
$gender_stats = ['Male' => 0, 'Female' => 0];
foreach ($accessible_employees as $employee) {
    if (isset($gender_stats[$employee['gender']])) {
        $gender_stats[$employee['gender']]++;
    }
}

// 2.3.2 Location-wise employee count
$location_stats = [];
foreach ($accessible_employees as $employee) {
    $location_name = getLocationName($db, $employee['location_id']);
    $location_stats[$location_name] = ($location_stats[$location_name] ?? 0) + 1;
}

// 2.3.3 Employment level breakdown
$employment_stats = [];
$employment_levels = $db->fetchAll("SELECT id, level_name FROM employment_levels ORDER BY level_order");
foreach ($employment_levels as $level) {
    $employment_stats[$level['level_name']] = 0;
}
foreach ($accessible_employees as $employee) {
    $level = $db->fetch("SELECT level_name FROM employment_levels WHERE id = ?", [$employee['employment_level_id']]);
    if ($level) {
        $employment_stats[$level['level_name']]++;
    }
}

// 2.3.4 Age-wise distribution (18-25, 26-35, 36-45, 46-55, 55-60, over 60)
$age_stats = [
    '18-25' => 0,
    '26-35' => 0,
    '36-45' => 0,
    '46-55' => 0,
    '55-60' => 0,
    'Over 60' => 0
];

foreach ($accessible_employees as $employee) {
    $age = $employee['age'];
    if ($age >= 18 && $age <= 25) {
        $age_stats['18-25']++;
    } elseif ($age >= 26 && $age <= 35) {
        $age_stats['26-35']++;
    } elseif ($age >= 36 && $age <= 45) {
        $age_stats['36-45']++;
    } elseif ($age >= 46 && $age <= 55) {
        $age_stats['46-55']++;
    } elseif ($age >= 55 && $age <= 60) {
        $age_stats['55-60']++;
    } else {
        $age_stats['Over 60']++;
    }
}

// 2.3.5 Department-wise distribution
$department_stats = [];
foreach ($accessible_employees as $employee) {
    $dept = $employee['department'] ?? 'Unknown';
    $department_stats[$dept] = ($department_stats[$dept] ?? 0) + 1;
}

// Additional analytics
// Service length distribution
$service_stats = [
    '0-1 years' => 0,
    '1-3 years' => 0,
    '3-5 years' => 0,
    '5-10 years' => 0,
    '10+ years' => 0
];

foreach ($accessible_employees as $employee) {
    if (isset($employee['joined_date'])) {
        $joined_date = new DateTime($employee['joined_date']);
        $today = new DateTime();
        $service_years = $today->diff($joined_date)->y;

        if ($service_years < 1) {
            $service_stats['0-1 years']++;
        } elseif ($service_years < 3) {
            $service_stats['1-3 years']++;
        } elseif ($service_years < 5) {
            $service_stats['3-5 years']++;
        } elseif ($service_years < 10) {
            $service_stats['5-10 years']++;
        } else {
            $service_stats['10+ years']++;
        }
    }
}

// Get filter options
$locations = $db->fetchAll("SELECT * FROM locations WHERE is_active = 1 ORDER BY location_name");
$departments = $db->fetchAll("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department");

// Total employees for percentages
$total_employees = count($accessible_employees);

// Calculate averages
$total_age = 0;
$total_service = 0;
$age_count = 0;
$service_count = 0;

foreach ($accessible_employees as $employee) {
    if (isset($employee['age']) && is_numeric($employee['age'])) {
        $total_age += $employee['age'];
        $age_count++;
    }
    if (isset($employee['joined_date'])) {
        $joined_date = new DateTime($employee['joined_date']);
        $today = new DateTime();
        $service_years = $today->diff($joined_date)->y;
        $total_service += $service_years;
        $service_count++;
    }
}

$avg_age = $age_count > 0 ? round($total_age / $age_count, 1) : 0;
$avg_service = $service_count > 0 ? round($total_service / $service_count, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Data Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
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

        .chart-container {
            position: relative;
            height: 350px;
            margin: 20px 0;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        .chart-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 25px;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .chart-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        .metric-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .metric-card:hover {
            transform: scale(1.05);
        }
        .metric-value {
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .metric-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .filter-card {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        .chart-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .chart-title i {
            margin-right: 10px;
            color: #3498db;
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
                        <h1><i class="fas fa-chart-bar me-3"></i>Data Monitor</h1>
                        <p>Comprehensive analysis of employee data with interactive charts and analytics</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Employee Management</a></li>
                    <li class="breadcrumb-item active">Data Monitor</li>
                </ol>
            </nav>

            <!-- Filters -->
            <div class="content-card">
                <div class="card-header-modern">
                    <h5><i class="fas fa-filter me-2"></i>Filter Options</h5>
                </div>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label"><i class="fas fa-map-marker-alt me-2"></i>Filter by Location</label>
                            <select class="form-select" name="location">
                                <option value="">All Locations</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>"
                                            <?php echo $location_filter == $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['location_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="fas fa-venus-mars me-2"></i>Filter by Gender</label>
                            <select class="form-select" name="gender">
                                <option value="">All Genders</option>
                                <option value="Male" <?php echo $gender_filter == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $gender_filter == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="fas fa-building me-2"></i>Filter by Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                                            <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                            <a href="data_monitor.php" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh me-2"></i>Reset
                            </a>
                        </div>
                    </form>
            </div>

            <!-- Summary Stats -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="metric-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="metric-value"><?php echo $total_employees; ?></div>
                        <div class="metric-label"><i class="fas fa-users me-1"></i>Total Employees</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="metric-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="metric-value"><?php echo $gender_stats['Male']; ?></div>
                        <div class="metric-label"><i class="fas fa-mars me-1"></i>Male</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="metric-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="metric-value"><?php echo $gender_stats['Female']; ?></div>
                        <div class="metric-label"><i class="fas fa-venus me-1"></i>Female</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="metric-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <div class="metric-value"><?php echo $avg_age; ?></div>
                        <div class="metric-label"><i class="fas fa-calendar me-1"></i>Avg Age</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="metric-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <div class="metric-value"><?php echo $avg_service; ?></div>
                        <div class="metric-label"><i class="fas fa-clock me-1"></i>Avg Service</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="metric-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                        <div class="metric-value" style="color: #333;"><?php echo count($department_stats); ?></div>
                        <div class="metric-label" style="color: #555;"><i class="fas fa-building me-1"></i>Departments</div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row">
                <!-- 2.3.1 Gender Distribution Chart -->
                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="chart-title">
                            <i class="fas fa-venus-mars"></i>Gender Distribution
                        </div>
                        <div class="chart-container">
                            <canvas id="genderChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- 2.3.2 Location-wise Employee Count -->
                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="chart-title">
                            <i class="fas fa-map-marker-alt"></i>Employees by Location
                        </div>
                        <div class="chart-container">
                            <canvas id="locationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row">
                <!-- 2.3.3 Employment Level Breakdown -->
                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="chart-title">
                            <i class="fas fa-layer-group"></i>Employment Level Distribution
                        </div>
                        <div class="chart-container">
                            <canvas id="employmentChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- 2.3.4 Age-wise Distribution -->
                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="chart-title">
                            <i class="fas fa-birthday-cake"></i>Age Group Distribution
                        </div>
                        <div class="chart-container">
                            <canvas id="ageChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 3 -->
            <div class="row">
                <!-- 2.3.5 Department-wise Distribution -->
                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="chart-title">
                            <i class="fas fa-building"></i>Department-wise Employee Count
                        </div>
                        <div class="chart-container">
                            <canvas id="departmentChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Service Length Distribution -->
                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="chart-title">
                            <i class="fas fa-hourglass-half"></i>Service Length Distribution
                        </div>
                        <div class="chart-container">
                            <canvas id="serviceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Tables -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Statistical Summary</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Metric</th>
                                            <th>Count</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Male Employees</td>
                                            <td><?php echo $gender_stats['Male']; ?></td>
                                            <td><?php echo $total_employees > 0 ? round(($gender_stats['Male'] / $total_employees) * 100, 1) : 0; ?>%</td>
                                        </tr>
                                        <tr>
                                            <td>Female Employees</td>
                                            <td><?php echo $gender_stats['Female']; ?></td>
                                            <td><?php echo $total_employees > 0 ? round(($gender_stats['Female'] / $total_employees) * 100, 1) : 0; ?>%</td>
                                        </tr>
                                        <?php foreach ($age_stats as $range => $count): ?>
                                        <tr>
                                            <td>Age <?php echo $range; ?></td>
                                            <td><?php echo $count; ?></td>
                                            <td><?php echo $total_employees > 0 ? round(($count / $total_employees) * 100, 1) : 0; ?>%</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="employee_list.php" class="btn btn-outline-primary">
                                    <i class="fas fa-list me-2"></i>View Employee List
                                </a>
                                <a href="employee_form.php" class="btn btn-outline-success">
                                    <i class="fas fa-plus me-2"></i>Add New Employee
                                </a>
                                <a href="export.php" class="btn btn-outline-warning">
                                    <i class="fas fa-download me-2"></i>Export Data
                                </a>
                                <a href="org_chart.php" class="btn btn-outline-info">
                                    <i class="fas fa-sitemap me-2"></i>Organizational Chart
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modern Chart.js configuration with attractive styling
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.padding = 20;

        // Enhanced color schemes
        const modernColors = [
            '#667eea', '#764ba2', '#f093fb', '#f5576c',
            '#4facfe', '#00f2fe', '#43e97b', '#38f9d7',
            '#fa709a', '#fee140', '#a8edea', '#fed6e3'
        ];

        const gradientColors = [
            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
            'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
            'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)'
        ];

        // Helper function to create gradients
        function createGradient(ctx, colorStart, colorEnd) {
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, colorStart);
            gradient.addColorStop(1, colorEnd);
            return gradient;
        }

        // 1. Gender Distribution Chart - Enhanced Doughnut
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        new Chart(genderCtx, {
            type: 'doughnut',
            data: {
                labels: ['Male', 'Female'],
                datasets: [{
                    data: [<?php echo $gender_stats['Male']; ?>, <?php echo $gender_stats['Female']; ?>],
                    backgroundColor: [
                        createGradient(genderCtx, '#667eea', '#764ba2'),
                        createGradient(genderCtx, '#f093fb', '#f5576c')
                    ],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverBorderWidth: 5,
                    hoverOffset: 10
                }]
            },
            options: {
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#667eea',
                        borderWidth: 2,
                        callbacks: {
                            label: function(context) {
                                const total = <?php echo $total_employees; ?>;
                                const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 2000
                }
            }
        });

        // 2. Location Chart - Gradient Bar Chart
        const locationCtx = document.getElementById('locationChart').getContext('2d');
        new Chart(locationCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($location_stats)); ?>,
                datasets: [{
                    label: 'Employees',
                    data: <?php echo json_encode(array_values($location_stats)); ?>,
                    backgroundColor: createGradient(locationCtx, '#4facfe', '#00f2fe'),
                    borderColor: '#4facfe',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // 3. Employment Level Chart - Horizontal Bar
        const employmentCtx = document.getElementById('employmentChart').getContext('2d');
        new Chart(employmentCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($employment_stats)); ?>,
                datasets: [{
                    label: 'Employees',
                    data: <?php echo json_encode(array_values($employment_stats)); ?>,
                    backgroundColor: createGradient(employmentCtx, '#43e97b', '#38f9d7'),
                    borderColor: '#43e97b',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    y: {
                        ticks: {
                            font: {
                                size: 11,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeInOutBounce'
                }
            }
        });

        // 4. Age Distribution Chart - Area Chart
        const ageCtx = document.getElementById('ageChart').getContext('2d');
        new Chart(ageCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($age_stats)); ?>,
                datasets: [{
                    label: 'Employees',
                    data: <?php echo json_encode(array_values($age_stats)); ?>,
                    borderColor: '#fa709a',
                    backgroundColor: createGradient(ageCtx, 'rgba(250, 112, 154, 0.3)', 'rgba(254, 225, 64, 0.1)'),
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#fa709a',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 3000,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // 5. Department Chart - Multi-color Bar
        const departmentCtx = document.getElementById('departmentChart').getContext('2d');
        new Chart(departmentCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($department_stats)); ?>,
                datasets: [{
                    label: 'Employees',
                    data: <?php echo json_encode(array_values($department_stats)); ?>,
                    backgroundColor: modernColors.slice(0, <?php echo count($department_stats); ?>),
                    borderWidth: 2,
                    borderColor: '#fff',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                animation: {
                    duration: 2000,
                    delay: (context) => context.dataIndex * 200
                }
            }
        });

        // 6. Service Length Chart - Polar Area
        const serviceCtx = document.getElementById('serviceChart').getContext('2d');
        new Chart(serviceCtx, {
            type: 'polarArea',
            data: {
                labels: <?php echo json_encode(array_keys($service_stats)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($service_stats)); ?>,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(240, 147, 251, 0.8)',
                        'rgba(79, 172, 254, 0.8)',
                        'rgba(67, 233, 123, 0.8)',
                        'rgba(250, 112, 154, 0.8)'
                    ],
                    borderColor: '#fff',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 2500
                }
            }
        });

        // Sidebar toggle
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>