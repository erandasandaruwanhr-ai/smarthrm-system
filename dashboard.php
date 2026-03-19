<?php
require_once 'config/config.php';
require_once 'includes/auth_check.php';


$user = getCurrentUser();
$db = new Database();


// Helper function for account type badge colors
function getAccountTypeBadgeColor($account_type) {
    switch (strtolower($account_type)) {
        case 'superadmin': return 'danger';
        case 'admin': return 'primary';
        case 'manager': return 'warning';
        case 'supervisor': return 'info';
        case 'user': return 'secondary';
        default: return 'dark';
    }
}

// Get comprehensive dashboard statistics
$total_employees = $db->fetch("SELECT COUNT(*) as count FROM employees WHERE is_active = 1")['count'];

// Employee statistics by location
$employee_by_location = $db->fetchAll("
    SELECT l.location_name, COUNT(e.id) as count
    FROM employees e
    LEFT JOIN locations l ON e.location_id = l.id
    WHERE e.is_active = 1
    GROUP BY e.location_id, l.location_name
    ORDER BY count DESC
");

// Employee statistics by department
$employee_by_department = $db->fetchAll("
    SELECT COALESCE(e.department, 'Unassigned') as department, COUNT(e.id) as count
    FROM employees e
    WHERE e.is_active = 1
    GROUP BY e.department
    ORDER BY count DESC
");

// Employee statistics by account type
$employee_by_type = $db->fetchAll("
    SELECT at.type_name, COUNT(e.id) as count
    FROM employees e
    LEFT JOIN account_types at ON e.account_type_id = at.id
    WHERE e.is_active = 1 AND e.password_hash IS NOT NULL
    GROUP BY at.type_name
    ORDER BY at.id
");

// Meal statistics
$meal_stats = [];
try {
    $today = date('Y-m-d');
    $meal_stats['employee_requests'] = $db->fetch("SELECT COUNT(*) as count FROM meal_requests_employee WHERE DATE(created_at) = ?", [$today])['count'] ?? 0;
    $meal_stats['visitor_requests'] = $db->fetch("SELECT COUNT(*) as count FROM meal_requests_visitor WHERE DATE(created_at) = ?", [$today])['count'] ?? 0;
} catch (Exception $e) {
    $meal_stats = ['employee_requests' => 0, 'visitor_requests' => 0];
}

// Transport statistics
$transport_stats = [];
try {
    $transport_stats['total_vehicles'] = $db->fetch("SELECT COUNT(*) as count FROM vehicles WHERE status IN ('available', 'in_use', 'maintenance')")['count'] ?? 0;
    $transport_stats['available_vehicles'] = $db->fetch("SELECT COUNT(*) as count FROM vehicles WHERE status = 'available'")['count'] ?? 0;
    $transport_stats['pending_requests'] = $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE status = 'Pending'")['count'] ?? 0;
} catch (Exception $e) {
    $transport_stats = ['total_vehicles' => 0, 'available_vehicles' => 0, 'pending_requests' => 0];
}

// Grievance statistics
$grievance_stats = [];
try {
    $grievance_stats['total'] = $db->fetch("SELECT COUNT(*) as count FROM grievances")['count'] ?? 0;
    $grievance_stats['open'] = $db->fetch("SELECT COUNT(*) as count FROM grievances WHERE status IN ('Open', 'In Progress')")['count'] ?? 0;
    $grievance_stats['anonymous'] = $db->fetch("SELECT COUNT(*) as count FROM anonymous_grievances WHERE status IN ('Open', 'Under Investigation')")['count'] ?? 0;
} catch (Exception $e) {
    $grievance_stats = ['total' => 0, 'open' => 0, 'anonymous' => 0];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/notifications.css" rel="stylesheet">
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

        .welcome-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-header::before {
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

        .welcome-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .welcome-header .user-details {
            position: relative;
            z-index: 2;
            opacity: 0.95;
        }

        .logo-container {
            position: relative;
            z-index: 2;
        }

        .welcome-logo {
            height: 60px;
            width: auto;
            background: rgba(255, 255, 255, 0.9);
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .welcome-content {
            position: relative;
            z-index: 2;
        }

        .welcome-content h1 {
            margin-bottom: 0;
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

        .stats-card.primary .icon {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
        }

        .stats-card.warning .icon {
            background: linear-gradient(135deg, var(--warning-color), #e0a800);
            color: white;
        }

        .stats-card.success .icon {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
        }

        .stats-card.danger .icon {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            color: white;
        }

        .stats-card.info .icon {
            background: linear-gradient(135deg, var(--info-color), #138496);
            color: white;
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

        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            height: 350px;
            position: relative;
        }

        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }

        .chart-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .activity-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            height: 100%;
        }

        .activity-item {
            padding: 1rem;
            border-left: 3px solid #e9ecef;
            margin-bottom: 1rem;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background: #e9ecef;
            border-left-color: var(--primary-color);
            transform: translateX(5px);
        }

        .activity-item.unread {
            border-left-color: var(--warning-color);
            background: #fff8e1;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .badge-modern {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .nav-pills-modern .nav-link {
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            margin: 0 0.25rem;
            border: none;
            background: #f8f9fa;
            color: #6c757d;
            transition: all 0.3s ease;
        }

        .nav-pills-modern .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3);
        }

        /* Top Navbar for Mobile */
        .top-navbar {
            background: white;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid #e9ecef;
            position: sticky;
            top: 0;
            z-index: 1040;
            display: none;
        }

        .top-navbar h5 {
            color: #2c3e50;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .dashboard-content {
                padding: 1rem;
            }

            .welcome-header h1 {
                font-size: 1.8rem;
            }

            .top-navbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            #sidebar-toggle {
                color: #007bff;
                font-size: 1.2rem;
                padding: 0.5rem;
                border: none;
                background: none;
            }

            #sidebar-toggle:hover {
                color: #0056b3;
                background-color: #f8f9fa;
                border-radius: 0.375rem;
            }

            #sidebar-toggle.active {
                color: #0056b3;
                background-color: #e9ecef;
                border-radius: 0.375rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Top Navbar for Mobile -->
    <div class="top-navbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none" id="sidebar-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0 ms-2">Dashboard</h5>
        </div>
        <div class="user-info">
            <span class="text-muted">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-content">
            <!-- Welcome Section -->
            <div class="welcome-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="welcome-content">
                        <h1><i class="fas fa-tachometer-alt me-3"></i>Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
                    </div>
                    <div class="logo-container">
                        <img src="jiffy-logo.svg" alt="Jiffy Logo" class="welcome-logo">
                    </div>
                </div>
                <div class="user-details">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><i class="fas fa-id-card me-2"></i><strong>EPF:</strong> <?php echo htmlspecialchars($user['epf_number']); ?></p>
                            <p class="mb-1"><i class="fas fa-briefcase me-2"></i><strong>Level:</strong> <?php echo htmlspecialchars($user['employment_level'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><i class="fas fa-map-marker-alt me-2"></i><strong>Location:</strong> <?php echo htmlspecialchars($user['location_name'] ?? 'N/A'); ?></p>
                            <p class="mb-1"><i class="fas fa-building me-2"></i><strong>Department:</strong> <?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="badge badge-modern bg-<?php echo getAccountTypeBadgeColor($user['account_type']); ?>">
                            <i class="fas fa-shield-alt me-1"></i><?php echo htmlspecialchars($user['account_type'] ?? 'N/A'); ?> Access
                        </span>
                    </div>
                </div>
            </div>

            <!-- Key Performance Indicators -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo number_format($total_employees); ?></h3>
                        <p>Active Employees</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <h3><?php echo number_format($meal_stats['employee_requests'] + $meal_stats['visitor_requests']); ?></h3>
                        <p>Today's Meal Requests</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card danger">
                        <div class="icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3><?php echo number_format($grievance_stats['open'] + $grievance_stats['anonymous']); ?></h3>
                        <p>Active Grievances</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card info">
                        <div class="icon">
                            <i class="fas fa-bus"></i>
                        </div>
                        <h3><?php echo number_format($transport_stats['available_vehicles']); ?>/<?php echo number_format($transport_stats['total_vehicles']); ?></h3>
                        <p>Available Vehicles</p>
                    </div>
                </div>
            </div>

            <!-- Analytics Section -->
            <div class="activity-card mb-4">
                <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Analytics</h5>
                        <div class="row">
                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="chart-card">
                                    <h6 class="chart-title"><i class="fas fa-map-marker-alt me-2"></i>By Location</h6>
                                    <div class="chart-container">
                                        <canvas id="locationChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="chart-card">
                                    <h6 class="chart-title"><i class="fas fa-building me-2"></i>By Department</h6>
                                    <div class="chart-container">
                                        <canvas id="departmentChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="chart-card">
                                    <h6 class="chart-title"><i class="fas fa-user-shield me-2"></i>Account Types</h6>
                                    <div class="chart-container">
                                        <canvas id="accountChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="chart-card">
                                    <h6 class="chart-title"><i class="fas fa-heartbeat me-2"></i>System Health</h6>
                                    <div class="text-center mt-4">
                                        <div class="row">
                                            <div class="col-4">
                                                <i class="fas fa-database fa-2x text-success"></i>
                                                <p class="small mt-2 mb-0">Database</p>
                                                <span class="badge bg-success">Online</span>
                                            </div>
                                            <div class="col-4">
                                                <i class="fas fa-server fa-2x text-success"></i>
                                                <p class="small mt-2 mb-0">Server</p>
                                                <span class="badge bg-success">Healthy</span>
                                            </div>
                                            <div class="col-4">
                                                <i class="fas fa-shield-alt fa-2x text-success"></i>
                                                <p class="small mt-2 mb-0">Security</p>
                                                <span class="badge bg-success">Active</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Charts Data
        const locationData = <?php echo json_encode(array_column($employee_by_location, 'location_name')); ?>;
        const locationCounts = <?php echo json_encode(array_column($employee_by_location, 'count')); ?>;

        const departmentData = <?php echo json_encode(array_column($employee_by_department, 'department')); ?>;
        const departmentCounts = <?php echo json_encode(array_column($employee_by_department, 'count')); ?>;

        const accountData = <?php echo json_encode(array_column($employee_by_type, 'type_name')); ?>;
        const accountCounts = <?php echo json_encode(array_column($employee_by_type, 'count')); ?>;

        // Chart colors
        const colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6f42c1', '#fd7e14', '#20c997'];

        // Animation function to update chart data
        function animateChartData(chart, newData, duration = 1000) {
            chart.data.datasets[0].data = newData;
            chart.update('active');
        }

        // Location Chart with animation
        const locationChart = new Chart(document.getElementById('locationChart'), {
            type: 'doughnut',
            data: {
                labels: locationData,
                datasets: [{
                    data: locationData.map(() => 0), // Start with 0 data
                    backgroundColor: colors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 2500,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 10,
                            usePointStyle: true,
                            boxWidth: 12
                        }
                    }
                }
            }
        });

        // Department Chart with animation
        const departmentChart = new Chart(document.getElementById('departmentChart'), {
            type: 'doughnut',
            data: {
                labels: departmentData,
                datasets: [{
                    data: departmentData.map(() => 0), // Start with 0 data
                    backgroundColor: colors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 2500,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 10,
                            usePointStyle: true,
                            boxWidth: 12
                        }
                    }
                }
            }
        });

        // Account Types Chart with animation
        const accountChart = new Chart(document.getElementById('accountChart'), {
            type: 'bar',
            data: {
                labels: accountData,
                datasets: [{
                    data: accountData.map(() => 0), // Start with 0 data
                    backgroundColor: colors,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 2500,
                    easing: 'easeOutQuart'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Animate all charts at once after delay
        setTimeout(() => {
            animateChartData(locationChart, locationCounts);
            animateChartData(departmentChart, departmentCounts);
            animateChartData(accountChart, accountCounts);
        }, 500);


        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            const toggleBtn = document.getElementById('sidebar-toggle');

            sidebar.classList.toggle('show');
            toggleBtn.classList.toggle('active');

            // Debug logging
            console.log('Sidebar toggle clicked');
            console.log('Sidebar element:', sidebar);
            console.log('Sidebar classes:', sidebar ? sidebar.className : 'not found');
            console.log('Sidebar show class present:', sidebar ? sidebar.classList.contains('show') : 'N/A');

            if (sidebar) {
                // Force visible state for debugging
                if (sidebar.classList.contains('show')) {
                    sidebar.style.visibility = 'visible';
                    sidebar.style.opacity = '1';
                    sidebar.style.transform = 'translateX(0)';
                    console.log('Sidebar should now be visible');
                } else {
                    // Remove forced inline styles when hiding
                    sidebar.style.visibility = '';
                    sidebar.style.opacity = '';
                    sidebar.style.transform = '';
                    console.log('Sidebar is hidden');
                }
            }
        });

        // Close sidebar when clicking outside on mobile - use capture to ensure we get the event
        document.addEventListener('click', function(event) {
            console.log('Document click detected:', event.target);

            // Small delay to allow any ongoing class changes to complete
            setTimeout(function() {

            const sidebar = document.querySelector('.sidebar');
            const sidebarToggle = document.getElementById('sidebar-toggle');

            // Only proceed if sidebar is open
            if (!sidebar) {
                console.log('No sidebar found, ignoring click');
                return;
            }

            const hasShowClass = sidebar.classList.contains('show');
            const hasInlineStyles = sidebar.style.visibility === 'visible';
            const isVisible = hasShowClass || hasInlineStyles;

            console.log('Sidebar state check:', {
                hasShowClass,
                hasInlineStyles,
                isVisible,
                classList: sidebar.className,
                visibility: sidebar.style.visibility
            });

            if (!isVisible) {
                console.log('Sidebar not open, ignoring click');
                return;
            }

            console.log('Sidebar is open, checking click location');

            // Check if clicked on toggle button - if so, let toggle handler deal with it
            if (sidebarToggle && sidebarToggle.contains(event.target)) {
                console.log('Clicked toggle button - letting toggle handler deal with it');
                return;
            }

            // Check if clicked inside sidebar
            if (sidebar.contains(event.target)) {
                console.log('Clicked inside sidebar - staying open');
                return;
            }

            // If we get here, clicked outside - close sidebar
            console.log('Clicked outside sidebar - closing');
            sidebar.classList.remove('show');

            // Remove forced inline styles
            sidebar.style.visibility = '';
            sidebar.style.opacity = '';
            sidebar.style.transform = '';

            // Remove active state from toggle button
            if (sidebarToggle) {
                sidebarToggle.classList.remove('active');
            }

            }, 50); // Small delay to allow class changes
        }, true); // Use capture phase to ensure we get the event first
    </script>

    <!-- Notification System -->
    <script src="assets/js/notifications.js"></script>
</body>
</html>