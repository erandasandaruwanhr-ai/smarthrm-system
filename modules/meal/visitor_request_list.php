<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to access visitor request management
requirePermission('meal', 'visitor_request_list');

$db = new Database();

$user = getCurrentUser();

$message = '';
$message_type = '';

// Handle delete action
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['request_id'])) {
    try {
        $request_id = (int)$_POST['request_id'];
        $delete_query = "DELETE FROM meal_requests_visitor WHERE id = ?";
        $db->query($delete_query, [$request_id]);

        $message = 'Visitor meal request deleted successfully!';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Error deleting request: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$location_filter = $_GET['location'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// Available locations
$locations = ['7C', 'Pannala', 'Kobeigane', 'JECOE', 'Head Office'];

// Build query conditions
$conditions = [];
$params = [];

// Add hierarchical access control
$account_type = $user['account_type'] ?? 'user';
$epf_number = $user['epf_number'];

// Hierarchical access based on account type
if ($account_type === 'supervisor') {
    // Supervisors can only see visitor requests they submitted themselves
    $conditions[] = "requesting_emp_number = ?";
    $params[] = $epf_number;
} elseif ($account_type === 'manager') {
    // Managers can see visitor requests from their direct reports AND indirect reports (through supervisors)
    $conditions[] = "requesting_emp_number IN (
        SELECT epf_number FROM employees WHERE reports_to = ?
        UNION
        SELECT epf_number FROM employees WHERE reports_to IN
        (SELECT epf_number FROM employees WHERE reports_to = ?)
    )";
    $params[] = $epf_number;
    $params[] = $epf_number;
} elseif ($account_type === 'admin') {
    // Admins can see visitor requests from their location
    $user_location = $user['location'] ?? $user['location_name'] ?? null;
    if ($user_location) {
        $conditions[] = "requesting_emp_location = ?";
        $params[] = $user_location;
    }
}
// SuperAdmins can see all requests (no additional condition needed)

if ($date_from) {
    $conditions[] = "DATE(request_date) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $conditions[] = "DATE(request_date) <= ?";
    $params[] = $date_to;
}

if ($location_filter !== 'all') {
    $conditions[] = "requesting_emp_location = ?";
    $params[] = $location_filter;
}

if ($status_filter !== 'all') {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get visitor meal requests
try {
    $query = "SELECT * FROM meal_requests_visitor $where_clause ORDER BY created_at DESC";
    $requests = $db->fetchAll($query, $params);
} catch (Exception $e) {
    $requests = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Visitor Request List</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/fontawesome.min.css" rel="stylesheet">
    <link href="../../assets/css/datatables.min.css" rel="stylesheet">
    <script src="../../assets/js/datatables.min.js"></script>
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

        /* Enhanced Table Styles */
        .advanced-table-container {
            background: white;
            border-radius: 15px;
            padding: 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 0;
            margin: 0;
        }

        .table-controls {
            background: #f8f9fa;
            padding: 1rem 2rem;
            border-bottom: 1px solid #dee2e6;
        }

        .enhanced-filter-panel {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .enhanced-filter-panel .form-control,
        .enhanced-filter-panel .form-select {
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 8px;
        }

        .quick-filter-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .quick-filter-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .quick-filter-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            border-color: rgba(255, 255, 255, 0.5);
        }

        .quick-filter-btn.active {
            background: rgba(255, 255, 255, 0.9);
            color: var(--primary-color);
        }

        .visitor-request-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .visitor-request-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .visitor-request-card.status-pending {
            border-left-color: #ffc107;
        }

        .visitor-request-card.status-approved {
            border-left-color: #28a745;
        }

        .visitor-request-card.status-rejected {
            border-left-color: #dc3545;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .visitor-info {
            flex-grow: 1;
        }

        .visitor-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .visit-purpose {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .request-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }

        .meta-icon {
            width: 20px;
            color: #6c757d;
            margin-right: 0.5rem;
        }

        .meal-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .meal-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-indicator {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .view-toggle {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 0.5rem;
            display: inline-flex;
            margin-bottom: 1.5rem;
        }

        .view-btn {
            background: transparent;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            color: #6c757d;
            font-weight: 500;
        }

        .view-btn.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
        }

        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-box input {
            padding-left: 3rem;
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.9);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
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

            .request-meta {
                grid-template-columns: 1fr;
            }

            .view-toggle {
                width: 100%;
                justify-content: center;
            }

            .table-controls {
                padding: 1rem;
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
                        <h1><i class="fas fa-list-alt me-3"></i>Visitor Request List</h1>
                        <p>Manage and approve visitor meal requests</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Meal Management</a></li>
                    <li class="breadcrumb-item active">Visitor Request List</li>
                </ol>
            </nav>

        <!-- Alert Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

            <!-- Statistics Summary -->
            <?php
            $total_requests = count($requests);
            $pending_count = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
            $approved_count = count(array_filter($requests, fn($r) => $r['status'] === 'approved'));
            $rejected_count = count(array_filter($requests, fn($r) => $r['status'] === 'rejected'));
            ?>

            <div class="stats-summary">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_requests; ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-warning"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-success"><?php echo $approved_count; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-danger"><?php echo $rejected_count; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <!-- Enhanced Filters -->
            <div class="enhanced-filter-panel">
                <h5 class="mb-4"><i class="fas fa-filter me-2"></i>Advanced Filters & Search</h5>

                <!-- Quick Filter Buttons -->
                <div class="quick-filter-buttons">
                    <button class="quick-filter-btn active" data-status="all">All Requests</button>
                    <button class="quick-filter-btn" data-status="pending">Pending</button>
                    <button class="quick-filter-btn" data-status="approved">Approved</button>
                    <button class="quick-filter-btn" data-status="rejected">Rejected</button>
                </div>

                <!-- Search Box -->
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" class="form-control" id="globalSearch" placeholder="Search by visitor name, purpose, employee name...">
                </div>

                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location">
                            <option value="all" <?php echo $location_filter === 'all' ? 'selected' : ''; ?>>All Locations</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo htmlspecialchars($location); ?>"
                                        <?php echo $location_filter === $location ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <?php if (hasModulePermission($db, 'meal.filter_visitor_requests')): ?>
                        <button type="submit" class="btn btn-light">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                        <a href="visitor_request_list.php" class="btn btn-outline-light ms-2">
                            <i class="fas fa-undo me-2"></i>Reset
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- View Toggle -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="view-toggle">
                    <button class="view-btn active" id="tableViewBtn">
                        <i class="fas fa-table me-2"></i>Table View
                    </button>
                    <button class="view-btn" id="cardViewBtn">
                        <i class="fas fa-th-large me-2"></i>Card View
                    </button>
                </div>
                <div class="export-options">
                    <button class="btn btn-outline-success btn-sm me-2">
                        <i class="fas fa-file-excel me-1"></i>Excel
                    </button>
                    <button class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-file-pdf me-1"></i>PDF
                    </button>
                </div>
            </div>

            <!-- Table View -->
            <div id="tableView" class="advanced-table-container">
                <div class="table-header">
                    <h5 class="mb-0"><i class="fas fa-table me-2"></i>Visitor Meal Requests</h5>
                </div>
                <div class="table-controls">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <label class="me-2">Show:</label>
                            <select id="entriesPerPage" class="form-select form-select-sm" style="width: auto;">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span class="ms-2">entries</span>
                        </div>
                        <div class="table-info">
                            Showing <span id="currentEntries">1 to <?php echo min(10, count($requests)); ?></span> of <span id="totalEntries"><?php echo count($requests); ?></span> entries
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="visitorRequestsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Visitor Details</th>
                                <th>Requesting Employee</th>
                                <th>Location</th>
                                <th>Request Date</th>
                                <th>Meals Requested</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                    <span class="text-muted">No visitor meal requests found</span>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $request): ?>
                                <tr data-status="<?php echo $request['status']; ?>">
                                    <td><?php echo htmlspecialchars($request['id']); ?></td>
                                    <td>
                                        <div class="mb-1">
                                            <strong class="text-primary"><?php echo htmlspecialchars($request['visitor_names'] ?? 'N/A'); ?></strong>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-clipboard-list me-1"></i>
                                            <?php echo htmlspecialchars($request['visit_purpose'] ?? 'N/A'); ?>
                                        </small>
                                        <?php if (!empty($request['visitor_remarks'])): ?>
                                            <br><small class="text-info">
                                                <i class="fas fa-comment me-1"></i>
                                                <?php echo htmlspecialchars($request['visitor_remarks']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($request['requesting_emp_name']); ?></strong><br>
                                        <small class="text-muted">EPF: <?php echo htmlspecialchars($request['requesting_emp_number']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['requesting_emp_location']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                    <td>
                                        <div class="small">
                                            <?php if ($request['breakfast_needed']): ?>
                                                <span class="badge bg-warning text-dark me-1 meal-badge">Breakfast (<?php echo $request['breakfast_count']; ?>)</span>
                                            <?php endif; ?>
                                            <?php if ($request['lunch_needed']): ?>
                                                <span class="badge bg-success me-1 meal-badge">Lunch (<?php echo $request['lunch_count']; ?>)</span>
                                            <?php endif; ?>
                                            <?php if ($request['dinner_needed']): ?>
                                                <span class="badge bg-primary me-1 meal-badge">Dinner (<?php echo $request['dinner_count']; ?>)</span>
                                            <?php endif; ?>
                                            <?php if ($request['snack1_needed']): ?>
                                                <span class="badge bg-info me-1 meal-badge">Snack 1 (<?php echo $request['snack1_count']; ?>)</span>
                                            <?php endif; ?>
                                            <?php if ($request['snack2_needed']): ?>
                                                <span class="badge bg-secondary me-1 meal-badge">Snack 2 (<?php echo $request['snack2_count']; ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        switch ($request['status']) {
                                            case 'pending':
                                                $status_class = 'bg-warning text-dark';
                                                $status_text = 'Pending Review';
                                                break;
                                            case 'approved':
                                                $status_class = 'bg-success';
                                                $status_text = 'Approved';
                                                break;
                                            case 'rejected':
                                                $status_class = 'bg-danger';
                                                $status_text = 'Rejected';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?> status-indicator"><?php echo $status_text; ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <?php if (hasModulePermission($db, 'meal.view_visitor_details')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="viewDetails(<?php echo $request['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card View -->
            <div id="cardView" class="row" style="display: none;">
                <?php if (empty($requests)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No visitor meal requests found</h4>
                    <p class="text-muted">Try adjusting your filters or search criteria.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                    <div class="col-lg-6 col-xl-4 visitor-card" data-status="<?php echo $request['status']; ?>">
                        <div class="visitor-request-card status-<?php echo $request['status']; ?>">
                            <div class="request-header">
                                <div class="visitor-info">
                                    <div class="visitor-name"><?php echo htmlspecialchars($request['visitor_names'] ?? 'N/A'); ?></div>
                                    <div class="visit-purpose">
                                        <i class="fas fa-clipboard-list me-1"></i>
                                        <?php echo htmlspecialchars($request['visit_purpose'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <div>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch ($request['status']) {
                                        case 'pending':
                                            $status_class = 'bg-warning text-dark';
                                            $status_text = 'Pending';
                                            break;
                                        case 'approved':
                                            $status_class = 'bg-success';
                                            $status_text = 'Approved';
                                            break;
                                        case 'rejected':
                                            $status_class = 'bg-danger';
                                            $status_text = 'Rejected';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?> status-indicator"><?php echo $status_text; ?></span>
                                </div>
                            </div>

                            <div class="request-meta">
                                <div class="meta-item">
                                    <i class="fas fa-user meta-icon"></i>
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['requesting_emp_name']); ?></strong><br>
                                        <small>EPF: <?php echo htmlspecialchars($request['requesting_emp_number']); ?></small>
                                    </div>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-map-marker-alt meta-icon"></i>
                                    <span><?php echo htmlspecialchars($request['requesting_emp_location']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar meta-icon"></i>
                                    <span><?php echo date('M d, Y', strtotime($request['request_date'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-clock meta-icon"></i>
                                    <span><?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?></span>
                                </div>
                            </div>

                            <div class="meal-badges">
                                <?php if ($request['breakfast_needed']): ?>
                                    <span class="badge bg-warning text-dark meal-badge">Breakfast (<?php echo $request['breakfast_count']; ?>)</span>
                                <?php endif; ?>
                                <?php if ($request['lunch_needed']): ?>
                                    <span class="badge bg-success meal-badge">Lunch (<?php echo $request['lunch_count']; ?>)</span>
                                <?php endif; ?>
                                <?php if ($request['dinner_needed']): ?>
                                    <span class="badge bg-primary meal-badge">Dinner (<?php echo $request['dinner_count']; ?>)</span>
                                <?php endif; ?>
                                <?php if ($request['snack1_needed']): ?>
                                    <span class="badge bg-info meal-badge">Snack 1 (<?php echo $request['snack1_count']; ?>)</span>
                                <?php endif; ?>
                                <?php if ($request['snack2_needed']): ?>
                                    <span class="badge bg-secondary meal-badge">Snack 2 (<?php echo $request['snack2_count']; ?>)</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($request['visitor_remarks'])): ?>
                            <div class="mt-2">
                                <small class="text-info">
                                    <i class="fas fa-comment me-1"></i>
                                    <?php echo htmlspecialchars($request['visitor_remarks']); ?>
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="action-buttons">
                                <?php if (hasModulePermission($db, 'meal.view_visitor_details')): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewDetails(<?php echo $request['id']; ?>)">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </button>
                                <?php endif; ?>
                                <div class="ms-auto">
                                    <small class="text-muted">ID: <?php echo $request['id']; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Visitor Meal Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this visitor meal request? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="request_id" id="deleteRequestId">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.min.js"></script>
    <script>
        let visitorTable;
        let allRequests = [];

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize DataTable
            initializeDataTable();

            // Setup view toggle
            setupViewToggle();

            // Setup filters
            setupFilters();

            // Setup search
            setupSearch();

            // Store all request data for filtering
            allRequests = Array.from(document.querySelectorAll('#visitorRequestsTable tbody tr[data-status]')).map(row => {
                return {
                    element: row,
                    status: row.dataset.status,
                    text: row.textContent.toLowerCase()
                };
            });

            // Store card elements
            allRequests.forEach((request, index) => {
                const cardElement = document.querySelectorAll('.visitor-card')[index];
                if (cardElement) {
                    request.cardElement = cardElement;
                }
            });
        });

        function initializeDataTable() {
            if (document.getElementById('visitorRequestsTable')) {
                visitorTable = $('#visitorRequestsTable').DataTable({
                    responsive: true,
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                    order: [[0, 'desc']],
                    columnDefs: [
                        { targets: [8], orderable: false },
                        { targets: [5, 6], className: 'text-center' }
                    ],
                    language: {
                        search: "",
                        searchPlaceholder: "Search requests...",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    },
                    dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex align-items-center"l><"d-flex align-items-center"f>>rtip',
                    drawCallback: function(settings) {
                        updateTableInfo();
                    }
                });

                // Custom styling for DataTable controls
                $('.dataTables_length select').addClass('form-select form-select-sm');
                $('.dataTables_filter input').addClass('form-control form-control-sm').attr('placeholder', 'Search requests...');
            }
        }

        function setupViewToggle() {
            const tableViewBtn = document.getElementById('tableViewBtn');
            const cardViewBtn = document.getElementById('cardViewBtn');
            const tableView = document.getElementById('tableView');
            const cardView = document.getElementById('cardView');

            tableViewBtn?.addEventListener('click', function() {
                tableViewBtn.classList.add('active');
                cardViewBtn.classList.remove('active');
                tableView.style.display = 'block';
                cardView.style.display = 'none';

                // Reinitialize DataTable if needed
                if (visitorTable) {
                    visitorTable.columns.adjust();
                }
            });

            cardViewBtn?.addEventListener('click', function() {
                cardViewBtn.classList.add('active');
                tableViewBtn.classList.remove('active');
                tableView.style.display = 'none';
                cardView.style.display = 'block';
            });
        }

        function setupFilters() {
            const quickFilterBtns = document.querySelectorAll('.quick-filter-btn');

            quickFilterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Update active state
                    quickFilterBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    const filterStatus = this.dataset.status;

                    // Filter table view
                    if (visitorTable) {
                        if (filterStatus === 'all') {
                            visitorTable.search('').columns().search('').draw();
                        } else {
                            visitorTable.column(6).search(filterStatus).draw();
                        }
                    }

                    // Filter card view
                    filterCards(filterStatus);
                });
            });
        }

        function setupSearch() {
            const globalSearch = document.getElementById('globalSearch');

            globalSearch?.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();

                // Search in table view
                if (visitorTable) {
                    visitorTable.search(searchTerm).draw();
                }

                // Search in card view
                searchCards(searchTerm);
            });
        }

        function filterCards(status) {
            const cards = document.querySelectorAll('.visitor-card');

            cards.forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            updateCardViewInfo();
        }

        function searchCards(searchTerm) {
            const cards = document.querySelectorAll('.visitor-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const cardText = card.textContent.toLowerCase();
                if (cardText.includes(searchTerm)) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            updateCardViewInfo(visibleCount);
        }

        function updateTableInfo() {
            const info = visitorTable?.page.info();
            if (info) {
                const currentEntries = document.getElementById('currentEntries');
                const totalEntries = document.getElementById('totalEntries');

                if (currentEntries) {
                    currentEntries.textContent = `${info.start + 1} to ${info.end}`;
                }
                if (totalEntries) {
                    totalEntries.textContent = info.recordsTotal;
                }
            }
        }

        function updateCardViewInfo(visibleCount = null) {
            const cards = document.querySelectorAll('.visitor-card');
            const visible = visibleCount !== null ? visibleCount : Array.from(cards).filter(c => c.style.display !== 'none').length;

            // Update any card view info elements if they exist
            console.log(`Showing ${visible} of ${cards.length} requests in card view`);
        }

        function viewDetails(requestId) {
            // Fetch and display request details
            fetch(`get_visitor_request_details.php?id=${requestId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('modalBody').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('detailsModal')).show();
                })
                .catch(error => {
                    document.getElementById('modalBody').innerHTML = '<div class="alert alert-danger">Error loading details</div>';
                    new bootstrap.Modal(document.getElementById('detailsModal')).show();
                });
        }

        function deleteRequest(requestId) {
            document.getElementById('deleteRequestId').value = requestId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Export functionality
        function exportToExcel() {
            console.log('Exporting to Excel...');
            // Implementation would go here
        }

        function exportToPDF() {
            console.log('Exporting to PDF...');
            // Implementation would go here
        }

        // Add event listeners for export buttons
        document.addEventListener('DOMContentLoaded', function() {
            const excelBtn = document.querySelector('.btn-outline-success');
            const pdfBtn = document.querySelector('.btn-outline-danger');

            excelBtn?.addEventListener('click', exportToExcel);
            pdfBtn?.addEventListener('click', exportToPDF);
        });

        // Enhance table responsiveness
        window.addEventListener('resize', function() {
            if (visitorTable) {
                visitorTable.columns.adjust();
            }
        });

        // Add loading states for better UX
        function showLoading() {
            const spinner = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            return spinner;
        }

        // Enhance modal loading with better feedback
        function viewDetailsEnhanced(requestId) {
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = showLoading();

            new bootstrap.Modal(document.getElementById('detailsModal')).show();

            fetch(`get_visitor_request_details.php?id=${requestId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    modalBody.innerHTML = html;
                })
                .catch(error => {
                    modalBody.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading request details. Please try again.
                        </div>
                    `;
                });
        }
    </script>
</body>
</html>