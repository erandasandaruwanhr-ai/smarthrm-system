<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Get current month and year from URL parameters or use current date
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($currentMonth < 1 || $currentMonth > 12) $currentMonth = date('n');
if ($currentYear < 2020 || $currentYear > 2030) $currentYear = date('Y');

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$locationFilter = $_GET['location'] ?? '';

// Build WHERE clause for filters
$whereConditions = ["YEAR(e.event_date) = ? AND MONTH(e.event_date) = ?"];
$params = [$currentYear, $currentMonth];

if ($statusFilter) {
    $whereConditions[] = "e.status = ?";
    $params[] = $statusFilter;
}

if ($categoryFilter) {
    $whereConditions[] = "e.category = ?";
    $params[] = $categoryFilter;
}

if ($locationFilter) {
    $whereConditions[] = "e.location_id = ?";
    $params[] = $locationFilter;
}

$whereClause = implode(' AND ', $whereConditions);

// Get events for the current month
$events = $db->fetchAll("
    SELECT e.*, l.location_name
    FROM events e
    LEFT JOIN locations l ON e.location_id = l.id
    WHERE $whereClause
    ORDER BY e.event_date, e.start_time
", $params);

// Get all events (for filters)
$allEvents = $db->fetchAll("SELECT DISTINCT category FROM events ORDER BY category");
$categories = array_column($allEvents, 'category');

$locations = $db->fetchAll("SELECT * FROM locations WHERE is_active = 1 ORDER BY location_name");
$statuses = ['Pending', 'Done', 'Postponed', 'Cancelled'];

// Organize events by date
$eventsByDate = [];
foreach ($events as $event) {
    $date = date('j', strtotime($event['event_date']));
    $eventsByDate[$date][] = $event;
}

// Calendar helper functions
function getFirstDayOfMonth($month, $year) {
    return date('w', mktime(0, 0, 0, $month, 1, $year));
}

function getDaysInMonth($month, $year) {
    return date('t', mktime(0, 0, 0, $month, 1, $year));
}

function getMonthName($month) {
    return date('F', mktime(0, 0, 0, $month, 1));
}

// Navigation
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Calendar View</title>
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

        .calendar-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .calendar-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px 10px 0 0;
        }

        .calendar {
            border-collapse: collapse;
            width: 100%;
        }

        .calendar th, .calendar td {
            border: 1px solid #e9ecef;
            vertical-align: top;
            height: 120px;
            width: 14.28%;
        }

        .calendar th {
            background-color: #f8f9fa;
            height: 50px;
            text-align: center;
            font-weight: 600;
            color: #495057;
        }

        .calendar td {
            padding: 8px;
            position: relative;
        }

        .calendar-day {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }

        .calendar-day.other-month {
            color: #ced4da;
        }

        .calendar-day.today {
            background-color: #007bff;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .event-item {
            font-size: 0.75rem;
            padding: 2px 6px;
            margin: 1px 0;
            border-radius: 3px;
            cursor: pointer;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .event-pending { background-color: #ffc107; color: #000; }
        .event-done { background-color: #28a745; color: #fff; }
        .event-postponed { background-color: #17a2b8; color: #fff; }
        .event-cancelled { background-color: #dc3545; color: #fff; }

        .legend {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 3px;
        }

        .filter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
            padding: 0;
        }

        .filter-card .card-body {
            padding: 2rem;
        }

        .nav-buttons {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-button {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .nav-button:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-1px);
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

            .calendar th, .calendar td {
                height: 80px;
                font-size: 0.8rem;
            }

            .event-item {
                font-size: 0.65rem;
                padding: 1px 3px;
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
                        <h1><i class="fas fa-calendar-alt me-3"></i>Calendar View</h1>
                        <p>Interactive calendar view of all scheduled events and activities</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Event Management</a></li>
                    <li class="breadcrumb-item active">Calendar View</li>
                </ol>
            </nav>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-end mb-4">
                <div class="action-buttons">
                    <a href="event_list.php" class="btn btn-outline-primary">
                        <i class="fas fa-list me-2"></i>List View
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-card">
                <div class="card-header-modern">
                    <h5>
                        <i class="fas fa-filter me-2"></i>Filter Events
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="month" value="<?php echo $currentMonth; ?>">
                        <input type="hidden" name="year" value="<?php echo $currentYear; ?>">

                        <div class="col-md-3">
                            <label class="form-label">Filter by Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                                        <?php echo $status; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Filter by Category</label>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category; ?>" <?php echo $categoryFilter === $category ? 'selected' : ''; ?>>
                                        <?php echo $category; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Filter by Location</label>
                            <select name="location" class="form-select">
                                <option value="">All Locations</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>" <?php echo $locationFilter == $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['location_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>Apply Filters
                                </button>
                                <a href="?month=<?php echo $currentMonth; ?>&year=<?php echo $currentYear; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Calendar -->
            <div class="calendar-container">
                <div class="calendar-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2><?php echo getMonthName($currentMonth) . ' ' . $currentYear; ?></h2>
                        <div class="nav-buttons">
                            <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?><?php echo $statusFilter ? '&status='.$statusFilter : ''; ?><?php echo $categoryFilter ? '&category='.$categoryFilter : ''; ?><?php echo $locationFilter ? '&location='.$locationFilter : ''; ?>" class="nav-button">
                                <i class="fas fa-chevron-left me-2"></i>Previous
                            </a>
                            <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?><?php echo $statusFilter ? '&status='.$statusFilter : ''; ?><?php echo $categoryFilter ? '&category='.$categoryFilter : ''; ?><?php echo $locationFilter ? '&location='.$locationFilter : ''; ?>" class="nav-button">
                                <i class="fas fa-calendar-day me-2"></i>Today
                            </a>
                            <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?><?php echo $statusFilter ? '&status='.$statusFilter : ''; ?><?php echo $categoryFilter ? '&category='.$categoryFilter : ''; ?><?php echo $locationFilter ? '&location='.$locationFilter : ''; ?>" class="nav-button">
                                Next<i class="fas fa-chevron-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <table class="calendar">
                    <thead>
                        <tr>
                            <th>Sunday</th>
                            <th>Monday</th>
                            <th>Tuesday</th>
                            <th>Wednesday</th>
                            <th>Thursday</th>
                            <th>Friday</th>
                            <th>Saturday</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $firstDay = getFirstDayOfMonth($currentMonth, $currentYear);
                        $daysInMonth = getDaysInMonth($currentMonth, $currentYear);
                        $today = date('j');
                        $currentDay = 1;
                        $weekNumber = 0;

                        // Calculate total weeks needed
                        $totalCells = $firstDay + $daysInMonth;
                        $totalWeeks = ceil($totalCells / 7);

                        for ($week = 0; $week < $totalWeeks; $week++):
                        ?>
                            <tr>
                                <?php for ($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++): ?>
                                    <td>
                                        <?php
                                        $cellDay = ($week * 7) + $dayOfWeek - $firstDay + 1;

                                        if ($cellDay > 0 && $cellDay <= $daysInMonth):
                                            $isToday = ($cellDay == $today && $currentMonth == date('n') && $currentYear == date('Y'));
                                        ?>
                                            <div class="calendar-day <?php echo $isToday ? 'today' : ''; ?>">
                                                <?php echo $cellDay; ?>
                                            </div>

                                            <?php if (isset($eventsByDate[$cellDay])): ?>
                                                <?php foreach ($eventsByDate[$cellDay] as $event): ?>
                                                    <div class="event-item event-<?php echo strtolower($event['status']); ?>"
                                                         onclick="showEventDetails(<?php echo htmlspecialchars(json_encode($event)); ?>)"
                                                         title="<?php echo htmlspecialchars($event['title']); ?>">
                                                        <?php echo date('g:i A', strtotime($event['start_time'])); ?> - <?php echo htmlspecialchars(substr($event['title'], 0, 20)); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>

                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>

                <!-- Legend -->
                <div class="p-3">
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #ffc107;"></div>
                            <span>Pending</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #28a745;"></div>
                            <span>Done</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #17a2b8;"></div>
                            <span>Postponed</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #dc3545;"></div>
                            <span>Cancelled</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div class="modal fade" id="eventDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Event Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="eventDetailsBody">
                    <!-- Event details will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showEventDetails(event) {
            const statusColors = {
                'Pending': '#ffc107',
                'Done': '#28a745',
                'Postponed': '#17a2b8',
                'Cancelled': '#dc3545'
            };

            const eventDate = new Date(event.event_date);
            const startTime = new Date(`2000-01-01 ${event.start_time}`);
            const endTime = new Date(`2000-01-01 ${event.end_time}`);

            const detailsHTML = `
                <div class="mb-3">
                    <h6 class="fw-bold">${event.event_id}</h6>
                    <h5>${event.title}</h5>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Category:</strong><br>
                        <span class="badge bg-secondary">${event.category}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong><br>
                        <span class="badge" style="background-color: ${statusColors[event.status]}; color: ${event.status === 'Pending' ? '#000' : '#fff'};">
                            ${event.status}
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <strong>Date & Time:</strong><br>
                    ${eventDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}<br>
                    ${startTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })} -
                    ${endTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}
                </div>

                <div class="mb-3">
                    <strong>Location:</strong><br>
                    ${event.location_name || 'All Locations'}
                    ${event.venue_details ? `<br><small class="text-muted">${event.venue_details}</small>` : ''}
                </div>

                ${event.description ? `
                <div class="mb-3">
                    <strong>Description:</strong><br>
                    <p class="text-muted">${event.description}</p>
                </div>
                ` : ''}
            `;

            document.getElementById('eventDetailsBody').innerHTML = detailsHTML;
            new bootstrap.Modal(document.getElementById('eventDetailsModal')).show();
        }
    </script>
</body>
</html>