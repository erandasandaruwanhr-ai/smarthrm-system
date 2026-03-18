<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Get filter parameters
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$locationFilter = $_GET['location'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Sorting parameters
$sortBy = $_GET['sort'] ?? 'event_date';
$sortOrder = $_GET['order'] ?? 'DESC';
$validSortColumns = ['event_date', 'title', 'category', 'status', 'event_id'];
$sortBy = in_array($sortBy, $validSortColumns) ? $sortBy : 'event_date';
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = intval($_GET['limit'] ?? 25);
$validLimits = [25, 50, 100];
$limit = in_array($limit, $validLimits) ? $limit : 25;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereConditions = [];
$params = [];

if ($searchTerm) {
    $whereConditions[] = "(e.title LIKE ? OR e.event_id LIKE ? OR e.description LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

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

if ($dateFrom) {
    $whereConditions[] = "e.event_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "e.event_date <= ?";
    $params[] = $dateTo;
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count for pagination
$totalQuery = "SELECT COUNT(*) as total FROM events e LEFT JOIN locations l ON e.location_id = l.id $whereClause";
$totalResult = $db->fetch($totalQuery, $params);
$totalEvents = $totalResult['total'];
$totalPages = ceil($totalEvents / $limit);

// Get events with pagination
$query = "
    SELECT e.*, l.location_name
    FROM events e
    LEFT JOIN locations l ON e.location_id = l.id
    $whereClause
    ORDER BY e.$sortBy $sortOrder
    LIMIT $limit OFFSET $offset
";

$events = $db->fetchAll($query, $params);

// Get unique values for filters
$categories = $db->fetchAll("SELECT DISTINCT category FROM events WHERE category IS NOT NULL ORDER BY category");
$locations = $db->fetchAll("SELECT * FROM locations WHERE is_active = 1 ORDER BY location_name");
$statuses = ['Pending', 'Done', 'Postponed', 'Cancelled'];

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Remove pagination for export
    $exportQuery = "
        SELECT e.*, l.location_name
        FROM events e
        LEFT JOIN locations l ON e.location_id = l.id
        $whereClause
        ORDER BY e.$sortBy $sortOrder
    ";
    $exportEvents = $db->fetchAll($exportQuery, $params);

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="events_' . date('Y-m-d') . '.xls"');

    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>Event ID</th>";
    echo "<th>Title</th>";
    echo "<th>Category</th>";
    echo "<th>Description</th>";
    echo "<th>Date</th>";
    echo "<th>Start Time</th>";
    echo "<th>End Time</th>";
    echo "<th>Location</th>";
    echo "<th>Venue Details</th>";
    echo "<th>Status</th>";
    echo "</tr>";

    foreach ($exportEvents as $event) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($event['event_id']) . "</td>";
        echo "<td>" . htmlspecialchars($event['title']) . "</td>";
        echo "<td>" . htmlspecialchars($event['category']) . "</td>";
        echo "<td>" . htmlspecialchars($event['description']) . "</td>";
        echo "<td>" . date('Y-m-d', strtotime($event['event_date'])) . "</td>";
        echo "<td>" . $event['start_time'] . "</td>";
        echo "<td>" . $event['end_time'] . "</td>";
        echo "<td>" . htmlspecialchars($event['location_name'] ?? 'All Locations') . "</td>";
        echo "<td>" . htmlspecialchars($event['venue_details']) . "</td>";
        echo "<td>" . htmlspecialchars($event['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Event List</title>
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

        .status-pending { background-color: #ffc107; color: #000; }
        .status-done { background-color: #28a745; color: #fff; }
        .status-postponed { background-color: #17a2b8; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }

        .sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
        }

        .sortable:hover {
            background-color: #f8f9fa;
        }

        .sortable::after {
            content: '↕';
            position: absolute;
            right: 8px;
            opacity: 0.5;
        }

        .sortable.asc::after {
            content: '↑';
            opacity: 1;
        }

        .sortable.desc::after {
            content: '↓';
            opacity: 1;
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

        .table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .table-card .card-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 1px solid #dee2e6;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem 2rem;
        }

        .table-card .card-body {
            padding: 2rem;
        }

        .pagination-info {
            color: #6c757d;
            font-size: 0.9rem;
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
                        <h1><i class="fas fa-list me-3"></i>Event List</h1>
                        <p>Browse and manage all events with detailed information and filtering</p>
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
                    <li class="breadcrumb-item active">Event List</li>
                </ol>
            </nav>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-end mb-4">
                <div class="action-buttons">
                    <a href="calendar_view.php" class="btn btn-outline-primary">
                        <i class="fas fa-calendar-alt me-2"></i>Calendar View
                    </a>
                    <?php if (isSuperAdmin()): ?>
                    <a href="event_management.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Event
                    </a>
                    <?php endif; ?>
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
                        <div class="col-md-3">
                            <label class="form-label">Search Events</label>
                            <input type="text" name="search" class="form-control" placeholder="Search title, ID, description..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                                        <?php echo $status; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $categoryFilter === $cat['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Location</label>
                            <select name="location" class="form-select">
                                <option value="">All Locations</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>" <?php echo $locationFilter == $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['location_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-1-5">
                            <label class="form-label">Date From</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                        </div>

                        <div class="col-md-1-5">
                            <label class="form-label">Date To</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                            <a href="event_list.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear Filters
                            </a>

                            <?php if (!empty($events)): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-success">
                                <i class="fas fa-file-excel me-2"></i>Export Excel
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Events Table -->
            <div class="table-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar me-2"></i>Events
                            <?php if ($totalEvents > 0): ?>
                                <span class="badge bg-primary"><?php echo number_format($totalEvents); ?></span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div>
                        <label class="form-label me-2">Show:</label>
                        <select class="form-select form-select-sm d-inline-block w-auto" onchange="changeLimit(this.value)">
                            <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                </div>

                <div class="card-body">
                    <?php if (empty($events)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Events Found</h5>
                            <p class="text-muted">Try adjusting your search criteria or filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th class="sortable <?php echo $sortBy === 'event_id' ? strtolower($sortOrder) : ''; ?>" onclick="sort('event_id')">
                                            Event ID
                                        </th>
                                        <th class="sortable <?php echo $sortBy === 'title' ? strtolower($sortOrder) : ''; ?>" onclick="sort('title')">
                                            Title
                                        </th>
                                        <th class="sortable <?php echo $sortBy === 'category' ? strtolower($sortOrder) : ''; ?>" onclick="sort('category')">
                                            Category
                                        </th>
                                        <th class="sortable <?php echo $sortBy === 'event_date' ? strtolower($sortOrder) : ''; ?>" onclick="sort('event_date')">
                                            Date & Time
                                        </th>
                                        <th>Location</th>
                                        <th class="sortable <?php echo $sortBy === 'status' ? strtolower($sortOrder) : ''; ?>" onclick="sort('status')">
                                            Status
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($event['event_id']); ?></code></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                                <?php if ($event['description']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($event['description'], 0, 100)) . (strlen($event['description']) > 100 ? '...' : ''); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($event['category']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('g:i A', strtotime($event['start_time'])); ?> -
                                                    <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($event['location_name'] ?? 'All Locations'); ?>
                                                <?php if ($event['venue_details']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($event['venue_details']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge status-<?php echo strtolower($event['status']); ?>">
                                                    <?php echo $event['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-outline-primary btn-sm" onclick="viewEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if (isSuperAdmin()): ?>
                                                <a href="event_management.php" class="btn btn-outline-secondary btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div class="pagination-info">
                                    Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $limit, $totalEvents)); ?> of <?php echo number_format($totalEvents); ?> events
                                </div>
                                <nav>
                                    <ul class="pagination mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">First</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>

                                        <?php
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);

                                        for ($p = $startPage; $p <= $endPage; $p++):
                                        ?>
                                            <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"><?php echo $p; ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>">Last</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div class="modal fade" id="eventDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
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
        function sort(column) {
            const currentSort = '<?php echo $sortBy; ?>';
            const currentOrder = '<?php echo $sortOrder; ?>';

            let newOrder = 'ASC';
            if (column === currentSort && currentOrder === 'ASC') {
                newOrder = 'DESC';
            }

            const url = new URL(window.location);
            url.searchParams.set('sort', column);
            url.searchParams.set('order', newOrder);
            url.searchParams.delete('page'); // Reset to first page

            window.location.href = url.toString();
        }

        function changeLimit(limit) {
            const url = new URL(window.location);
            url.searchParams.set('limit', limit);
            url.searchParams.delete('page'); // Reset to first page

            window.location.href = url.toString();
        }

        function viewEvent(event) {
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
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-primary">${event.event_id}</h6>
                        <h5>${event.title}</h5>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="badge" style="background-color: ${statusColors[event.status]}; color: ${event.status === 'Pending' ? '#000' : '#fff'}; font-size: 1rem;">
                            ${event.status}
                        </span>
                    </div>
                </div>

                <hr>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Category:</strong><br>
                        <span class="badge bg-secondary">${event.category}</span>
                    </div>
                    <div class="col-md-4">
                        <strong>Date:</strong><br>
                        ${eventDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                    </div>
                    <div class="col-md-4">
                        <strong>Time:</strong><br>
                        ${startTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })} -
                        ${endTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}
                    </div>
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

                <div class="text-muted small">
                    <strong>Created:</strong> ${new Date(event.created_at).toLocaleString()}<br>
                    <strong>Last Updated:</strong> ${new Date(event.updated_at).toLocaleString()}
                </div>
            `;

            document.getElementById('eventDetailsBody').innerHTML = detailsHTML;
            new bootstrap.Modal(document.getElementById('eventDetailsModal')).show();
        }
    </script>
</body>
</html>