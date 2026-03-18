<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has access to Offboarding Reports
if (!isSuperAdmin() && !hasModulePermission($database, 'offboarding.offboarding_reports')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get statistics
$total_resignations = $database->fetchAll("SELECT COUNT(*) as count FROM resignations")[0]['count'] ?? 0;
$pending_approvals = $database->fetchAll("SELECT COUNT(*) as count FROM resignations WHERE status = 'pending'")[0]['count'] ?? 0;
$approved_resignations = $database->fetchAll("SELECT COUNT(*) as count FROM resignations WHERE status = 'approved'")[0]['count'] ?? 0;
$completed_offboarding = $database->fetchAll("SELECT COUNT(*) as count FROM resignations WHERE status = 'completed'")[0]['count'] ?? 0;
$rejected_resignations = $database->fetchAll("SELECT COUNT(*) as count FROM resignations WHERE status = 'rejected'")[0]['count'] ?? 0;

// Monthly resignations
$monthly_data = $database->fetchAll("
    SELECT
        YEAR(date_of_resignation) as year,
        MONTH(date_of_resignation) as month,
        COUNT(*) as count
    FROM resignations
    WHERE date_of_resignation >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY YEAR(date_of_resignation), MONTH(date_of_resignation)
    ORDER BY year, month
");

// Department breakdown
$department_data = $database->fetchAll("
    SELECT
        employee_department,
        COUNT(*) as count
    FROM resignations
    GROUP BY employee_department
    ORDER BY count DESC
    LIMIT 10
");

// Average processing time
$avg_processing_time = $database->fetchAll("
    SELECT
        AVG(DATEDIFF(updated_at, created_at)) as avg_days
    FROM resignations
    WHERE status = 'completed'
")[0]['avg_days'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Offboarding Reports</title>
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

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            transition: transform 0.3s ease;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.2rem;
        }

        .stats-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .stats-card p {
            color: #7f8c8d;
            margin: 0;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            position: relative;
        }

        .chart-container canvas {
            max-height: 400px !important;
        }

        #monthlyChart {
            height: 300px !important;
        }

        #statusChart {
            height: 300px !important;
        }

        #departmentChart {
            height: 250px !important;
        }

        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .chart-title i {
            margin-right: 0.5rem;
            color: var(--primary-color);
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
                        <h1><i class="fas fa-chart-line me-3"></i>Offboarding Reports</h1>
                        <p>Generate comprehensive reports on offboarding trends and analytics</p>
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
                    <li class="breadcrumb-item active">Offboarding Reports</li>
                </ol>
            </nav>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="icon" style="background: linear-gradient(135deg, #007bff, #0056b3); color: white;">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <h3><?php echo number_format($total_resignations); ?></h3>
                        <p>Total Resignations</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="icon" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3><?php echo number_format($completed_offboarding); ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="icon" style="background: linear-gradient(135deg, #ffc107, #e0a800); color: white;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3><?php echo number_format($pending_approvals); ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="icon" style="background: linear-gradient(135deg, #17a2b8, #138496); color: white;">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3><?php echo round($avg_processing_time); ?></h3>
                        <p>Avg. Days to Complete</p>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row">
                <!-- Monthly Resignations -->
                <div class="col-lg-8 mb-4">
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-chart-line"></i>
                            Monthly Resignation Trends
                        </div>
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <!-- Status Distribution -->
                <div class="col-lg-4 mb-4">
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-chart-pie"></i>
                            Status Distribution
                        </div>
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Department Breakdown -->
            <div class="row">
                <div class="col-12">
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-building"></i>
                            Resignations by Department
                        </div>
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Resignations Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_data); ?>;

        const monthlyLabels = monthlyData.map(item => {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return months[item.month - 1] + ' ' + item.year;
        });

        const monthlyValues = monthlyData.map(item => item.count);

        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Resignations',
                    data: monthlyValues,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
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
                    }
                }
            }
        });

        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Approved', 'Pending', 'Rejected'],
                datasets: [{
                    data: [
                        <?php echo $completed_offboarding; ?>,
                        <?php echo $approved_resignations; ?>,
                        <?php echo $pending_approvals; ?>,
                        <?php echo $rejected_resignations; ?>
                    ],
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Department Chart
        const departmentCtx = document.getElementById('departmentChart').getContext('2d');
        const departmentData = <?php echo json_encode($department_data); ?>;

        const departmentLabels = departmentData.map(item => item.employee_department || 'Unknown');
        const departmentValues = departmentData.map(item => item.count);

        new Chart(departmentCtx, {
            type: 'bar',
            data: {
                labels: departmentLabels,
                datasets: [{
                    label: 'Resignations',
                    data: departmentValues,
                    backgroundColor: 'rgba(0, 123, 255, 0.8)',
                    borderColor: '#007bff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
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
                    }
                }
            }
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>