<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/location_access.php';

// Check if user has permission to access grievance list
requirePermission('grievance', 'grievance_list');

$db = new Database();

$user = getCurrentUser();

// Get notification count for grievances - DIRECT CALCULATION (bypassing class for reliability)
$notification_count = 0;
$account_type = $user['account_type'] ?? 'user';

if (in_array($account_type, ['supervisor', 'manager', 'admin', 'superadmin'])) {
    $user_location = $user['location'] ?? $user['location_name'] ?? null;

    // Get location from database if not in session
    if (!$user_location && isset($user['epf_number'])) {
        $location_data = $db->fetch("
            SELECT l.location_name
            FROM employees e
            LEFT JOIN locations l ON e.location_id = l.id
            WHERE e.epf_number = ?
        ", [$user['epf_number']]);

        if ($location_data) {
            $user_location = $location_data['location_name'];
        }
    }

    if ($account_type === 'superadmin') {
        // SuperAdmin sees all normal grievances + all anonymous grievances
        $normal_result = $db->fetch("
            SELECT COUNT(*) as count
            FROM grievances g
            WHERE g.status IN ('Open', 'Under Supervisory Review', 'Under Managerial Review', 'In Progress', 'Reopened')
        ");
        $normal_count = $normal_result ? (int)$normal_result['count'] : 0;

        $anonymous_result = $db->fetch("
            SELECT COUNT(*) as count
            FROM anonymous_grievances ag
            WHERE ag.status IN ('Opened', 'Reviewing', 'Action', 'Under Investigation', 'Open')
        ");
        $anonymous_count = $anonymous_result ? (int)$anonymous_result['count'] : 0;

        $notification_count = $normal_count + $anonymous_count;

    } elseif (in_array($account_type, ['admin', 'manager', 'supervisor'])) {
        // Admin/Manager/Supervisor sees normal grievances from their location only
        if ($user_location) {
            $result = $db->fetch("
                SELECT COUNT(*) as count
                FROM grievances g
                WHERE g.status IN ('Open', 'Under Supervisory Review', 'Under Managerial Review', 'In Progress', 'Reopened')
                AND g.employee_location = ?
            ", [$user_location]);
            $notification_count = $result ? (int)$result['count'] : 0;
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$urgency_filter = $_GET['urgency'] ?? '';
$location_filter = $_GET['location'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// Build WHERE clause based on user access level
$where_conditions = [];
$params = [];

// Access control - Build separate conditions for regular and anonymous tables
$account_type = $user['account_type'] ?? 'user';

// Initialize base conditions that will be applied differently to each table
$base_conditions = [];
$base_params = [];

if ($account_type === 'superadmin') {
    // Superadmin can see all grievances - no additional conditions needed
} elseif ($account_type === 'admin') {
    // Admin can see grievances from employees in their location only
    $accessible_employees = getAccessibleEmployeesByLocation($db, $user);
    $accessible_epf_numbers = array_column($accessible_employees, 'epf_number');

    if (!empty($accessible_epf_numbers)) {
        $placeholders = str_repeat('?,', count($accessible_epf_numbers) - 1) . '?';
        $base_conditions[] = "epf_field IN ($placeholders)";
        $base_params = array_merge($base_params, $accessible_epf_numbers);
    } else {
        $base_conditions[] = "1 = 0"; // No accessible employees - show nothing
    }
} elseif ($account_type === 'manager') {
    // Manager can see cases from their department AND cases in their hierarchical chain
    $manager_conditions = [];

    // Direct department cases
    if (!empty($user['department'])) {
        $manager_conditions[] = "department_field = ?";
        $base_params[] = $user['department'];
    }

    // IMPROVED: Get cases from hierarchical reporting chain (regardless of account_type)
    // This finds all employees in the manager's reporting hierarchy using a more robust approach
    $hierarchical_employees = $db->fetchAll(
        "SELECT e1.epf_number
         FROM employees e1
         INNER JOIN employees e2 ON e1.reports_to = e2.epf_number
         WHERE e2.reports_to = ? AND e1.is_active = 1",
        [$user['epf_number']]
    );

    if (!empty($hierarchical_employees)) {
        $hierarchical_epfs = array_column($hierarchical_employees, 'epf_number');
        $placeholders = str_repeat('?,', count($hierarchical_epfs) - 1) . '?';
        $manager_conditions[] = "epf_field IN ($placeholders)";
        $base_params = array_merge($base_params, $hierarchical_epfs);
    }

    // Always ensure manager has some conditions (if no direct access, at least fail gracefully)
    if (empty($manager_conditions)) {
        // If somehow no conditions were built, create a minimal condition
        $manager_conditions[] = "1 = 0"; // This will return no results but won't break the query
    }

    if (!empty($manager_conditions)) {
        $base_conditions[] = "(" . implode(' OR ', $manager_conditions) . ")";
    }
} elseif ($account_type === 'supervisor') {
    // Supervisor can see grievances from their location and department
    if (!empty($user['location']) && !empty($user['department'])) {
        $base_conditions[] = "(location_field = ? AND department_field = ?)"; // Will be replaced per table
        $base_params[] = $user['location'];
        $base_params[] = $user['department'];
    }

    // Note: Subordinate logic will be handled separately for regular table only
} else {
    // Regular users can only see their own regular grievances (no anonymous access)
    $base_conditions[] = "epf_field = ?"; // Will be replaced per table
    $base_params[] = $user['epf_number'];
}

// Apply base conditions to regular table (with correct column names)
$regular_conditions = [];
foreach ($base_conditions as $condition) {
    $regular_condition = str_replace('department_field', 'employee_department', $condition);
    $regular_condition = str_replace('location_field', 'employee_location', $regular_condition);
    $regular_condition = str_replace('epf_field', 'employee_epf', $regular_condition);
    $regular_conditions[] = $regular_condition;
}
$regular_access_params = $base_params;

// Handle supervisor subordinates for regular table only
if ($account_type === 'supervisor') {
    $subordinates = $db->fetchAll("SELECT epf_number FROM employees WHERE reports_to = ? AND is_active = 1", [$user['epf_number']]);
    if (!empty($subordinates)) {
        $subordinate_epfs = array_column($subordinates, 'epf_number');
        $placeholders = str_repeat('?,', count($subordinate_epfs) - 1) . '?';
        if (!empty($regular_conditions)) {
            // Combine with existing conditions using OR
            $existing_conditions = implode(' AND ', $regular_conditions); // Changed from OR to AND
            $regular_conditions = ["($existing_conditions) OR (employee_epf IN ($placeholders))"];
        } else {
            $regular_conditions[] = "employee_epf IN ($placeholders)";
        }
        $regular_access_params = array_merge($regular_access_params, $subordinate_epfs);
    } else {
        // If no subordinates found but supervisor has other conditions, keep them
        // If no conditions at all, ensure supervisor can at least see their own cases
        if (empty($regular_conditions)) {
            $regular_conditions[] = "employee_epf = ?";
            $regular_access_params[] = $user['epf_number'];
        }
    }
}

// UNIVERSAL SUBORDINATE ACCESS: Anyone with direct reports should see those reports' cases
// This handles cases where account_type doesn't match but user has supervisory role
if (!in_array($account_type, ['superadmin', 'admin'])) {
    $universal_subordinates = $db->fetchAll("SELECT epf_number FROM employees WHERE reports_to = ? AND is_active = 1", [$user['epf_number']]);
    if (!empty($universal_subordinates)) {
        $universal_epfs = array_column($universal_subordinates, 'epf_number');
        $placeholders = str_repeat('?,', count($universal_epfs) - 1) . '?';
        if (!empty($regular_conditions)) {
            // Add as additional OR condition
            $existing_conditions = implode(' AND ', $regular_conditions);
            $regular_conditions = ["($existing_conditions) OR (employee_epf IN ($placeholders))"];
        } else {
            $regular_conditions[] = "employee_epf IN ($placeholders)";
        }
        $regular_access_params = array_merge($regular_access_params, $universal_epfs);
    }
}

// Anonymous table access - superadmin + investigation team members
$anonymous_conditions = [];
$anonymous_access_params = [];
if ($account_type === 'superadmin') {
    // Superadmin can see all anonymous cases - no additional conditions
} else {
    // Non-superadmin users can only see anonymous cases they're assigned to investigate
    $user_investigations = $db->fetchAll(
        "SELECT anonymous_grievance_id FROM anonymous_investigation_teams
         WHERE (team_leader_epf = ? OR member1_epf = ? OR member2_epf = ?) AND status = 'Active'",
        [$user['epf_number'], $user['epf_number'], $user['epf_number']]
    );

    if (!empty($user_investigations)) {
        $investigation_ids = array_column($user_investigations, 'anonymous_grievance_id');
        $placeholders = str_repeat('?,', count($investigation_ids) - 1) . '?';
        $anonymous_conditions[] = "id IN ($placeholders)";
        $anonymous_access_params = $investigation_ids;
    } else {
        // No assigned investigations, no access
        $anonymous_conditions[] = "1 = 0";
    }
}

// Apply filters (these will be added to both regular and anonymous conditions)
$filter_conditions = [];
$filter_params = [];

if (!empty($status_filter)) {
    $filter_conditions[] = "status = ?";
    $filter_params[] = $status_filter;
}

if (!empty($category_filter)) {
    $filter_conditions[] = "category = ?";
    $filter_params[] = $category_filter;
}

if (!empty($urgency_filter)) {
    $filter_conditions[] = "urgency = ?";
    $filter_params[] = $urgency_filter;
}

if (!empty($date_from)) {
    $filter_conditions[] = "DATE(submission_date) >= ?";
    $filter_params[] = $date_from;
}

if (!empty($date_to)) {
    $filter_conditions[] = "DATE(submission_date) <= ?";
    $filter_params[] = $date_to;
}

if (!empty($search)) {
    $filter_conditions[] = "(case_id LIKE ? OR subject LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $filter_params[] = $search_param;
    $filter_params[] = $search_param;
    $filter_params[] = $search_param;
}

// Location filter (different column names for each table)
$regular_location_conditions = [];
$anonymous_location_conditions = [];
$location_params = [];

if (!empty($location_filter)) {
    $regular_location_conditions[] = "employee_location = ?";
    $anonymous_location_conditions[] = "submitter_location = ?";
    $location_params[] = $location_filter;
}

// Combine all conditions for each table
$final_regular_conditions = array_merge($regular_conditions, $filter_conditions, $regular_location_conditions);
$final_regular_params = array_merge($regular_access_params, $filter_params, $location_params);

$final_anonymous_conditions = array_merge($anonymous_conditions, $filter_conditions, $anonymous_location_conditions);
$final_anonymous_params = array_merge($anonymous_access_params, $filter_params, $location_params);

// Build final WHERE clauses
$regular_where_final = !empty($final_regular_conditions) ? 'WHERE ' . implode(' AND ', $final_regular_conditions) : '';
$anonymous_where_final = !empty($final_anonymous_conditions) ? 'WHERE ' . implode(' AND ', $final_anonymous_conditions) : '';

// Unified query with UNION
$sql = "
    SELECT
        'regular' as case_type,
        id,
        case_id,
        employee_name as submitter_name,
        employee_epf as submitter_epf,
        employee_location as location,
        employee_department as department,
        category,
        urgency,
        subject,
        status,
        submission_date,
        DATEDIFF(NOW(), submission_date) as days_open
    FROM grievances
    $regular_where_final

    UNION ALL

    SELECT
        'anonymous' as case_type,
        id,
        case_id,
        'Anonymous' as submitter_name,
        'ANONYMOUS' as submitter_epf,
        submitter_location as location,
        submitter_department as department,
        category,
        urgency,
        subject,
        status,
        submission_date,
        DATEDIFF(NOW(), submission_date) as days_open
    FROM anonymous_grievances
    $anonymous_where_final

    ORDER BY
        CASE urgency
            WHEN 'Critical' THEN 4
            WHEN 'High' THEN 3
            WHEN 'Medium' THEN 2
            WHEN 'Low' THEN 1
        END DESC,
        submission_date DESC
    LIMIT $limit OFFSET $offset
";

// Combined params for both queries
$combined_params = array_merge($final_regular_params, $final_anonymous_params);

// Get total count for pagination (combine both tables)
$regular_count = !empty($regular_where_final) ?
    $db->fetch("SELECT COUNT(*) as total FROM grievances $regular_where_final", $final_regular_params)['total'] :
    $db->fetch("SELECT COUNT(*) as total FROM grievances")['total'];

$anonymous_count = !empty($anonymous_where_final) ?
    $db->fetch("SELECT COUNT(*) as total FROM anonymous_grievances $anonymous_where_final", $final_anonymous_params)['total'] :
    ($account_type === 'superadmin' ? $db->fetch("SELECT COUNT(*) as total FROM anonymous_grievances")['total'] : 0);

$total_records = $regular_count + $anonymous_count;
$total_pages = ceil($total_records / $limit);

$grievances = $db->fetchAll($sql, $combined_params);

// Get filter options
$categories = [
    'Professional (Career & Work)',
    'Financial (Money & Compensation)',
    'Behavioral (People & Conduct)',
    'Environment (Physical Workspace)',
    'Policy (Rules & Procedures)',
    'Safety (Health & Security)',
    'Discrimination (Unfair Treatment)',
    'Harassment (Inappropriate Behavior)',
    'Communication (Information & Feedback)'
];

$statuses = ['Open', 'Under Supervisory Review', 'Under Managerial Review', 'Resolved', 'Closed', 'Reopened'];
$urgency_levels = ['Low', 'Medium', 'High', 'Critical'];
$locations = LOCATIONS;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Grievance List</title>
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

        .urgency-critical { border-left: 5px solid #dc3545; }
        .urgency-high { border-left: 5px solid #fd7e14; }
        .urgency-medium { border-left: 5px solid #ffc107; }
        .urgency-low { border-left: 5px solid #28a745; }

        .status-open { background-color: #fff3cd; }
        .status-in-progress { background-color: #cfe2ff; }
        .status-resolved { background-color: #d1e7dd; }
        .status-closed { background-color: #f8d7da; }

        .table-responsive {
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .filter-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
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
                        <h1><i class="fas fa-list me-3"></i>Grievance List</h1>
                        <p>View and manage all submitted grievances with status tracking</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Grievance Management</a></li>
                    <li class="breadcrumb-item active">Grievance List</li>
                </ol>
            </nav>

            <!-- Filters -->
            <div class="form-card">
                <div class="card-header-modern">
                    <h5><i class="fas fa-filter me-2"></i>Filter Grievances</h5>
                </div>
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <option value="">All Status</option>
                                <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                    <?php echo $status; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Category</label>
                            <select class="form-select form-select-sm" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category; ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                    <?php echo substr($category, 0, strpos($category, '(')); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Urgency</label>
                            <select class="form-select form-select-sm" name="urgency">
                                <option value="">All Urgency</option>
                                <?php foreach ($urgency_levels as $urgency): ?>
                                <option value="<?php echo $urgency; ?>" <?php echo $urgency_filter === $urgency ? 'selected' : ''; ?>>
                                    <?php echo $urgency; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Search</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Case ID, Subject...">
                                <button class="btn btn-outline-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                    <div class="mt-3">
                        <a href="grievance_list.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times me-1"></i>Clear Filters
                        </a>
                        <span class="ms-3 text-muted">
                            <strong><?php echo $total_records; ?></strong> grievances found
                        </span>
                    </div>
            </div>

            <!-- Grievance Table -->
            <div class="content-card">
                <div class="card-header-modern">
                    <h5><i class="fas fa-table me-2"></i>Grievance Table</h5>
                </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Case ID</th>
                                    <th>Employee</th>
                                    <th>Category</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Urgency</th>
                                    <th>Days Open</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($grievances)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No grievances found matching your criteria</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($grievances as $grievance): ?>
                                    <tr class="urgency-<?php echo strtolower($grievance['urgency']); ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($grievance['case_id']); ?></strong>
                                            <?php if ($grievance['case_type'] === 'anonymous'): ?>
                                            <span class="badge bg-secondary ms-1">Anonymous</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($grievance['case_type'] === 'anonymous' && $account_type !== 'superadmin'): ?>
                                                Anonymous
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($grievance['submitter_name']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($grievance['submitter_epf']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars(substr($grievance['category'], 0, strpos($grievance['category'], '('))); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars(substr($grievance['subject'], 0, 50)); ?>
                                            <?php if (strlen($grievance['subject']) > 50): ?>...<?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = match($grievance['status']) {
                                                'Open' => 'bg-warning text-dark',
                                                'Under Supervisory Review' => 'bg-info',
                                                'Under Managerial Review' => 'bg-primary',
                                                'Resolved' => 'bg-success',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($grievance['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $urgency_class = match($grievance['urgency']) {
                                                'Critical' => 'bg-danger',
                                                'High' => 'bg-warning text-dark',
                                                'Medium' => 'bg-info',
                                                'Low' => 'bg-success'
                                            };
                                            ?>
                                            <span class="badge <?php echo $urgency_class; ?>">
                                                <?php echo htmlspecialchars($grievance['urgency']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $days = $grievance['days_open'];
                                            $color = $days > 30 ? 'text-danger' : ($days > 14 ? 'text-warning' : 'text-success');
                                            ?>
                                            <span class="<?php echo $color; ?>">
                                                <?php echo $days; ?> days
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('d/m/Y H:i', strtotime($grievance['submission_date'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view_grievance.php?id=<?php echo $grievance['id']; ?>&type=<?php echo $grievance['case_type']; ?>"
                                                   class="btn btn-outline-primary btn-sm" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (in_array($account_type, ['superadmin', 'admin', 'manager', 'supervisor'])): ?>
                                                <a href="case_management.php?id=<?php echo $grievance['id']; ?>&type=<?php echo $grievance['case_type']; ?>"
                                                   class="btn btn-outline-success btn-sm" title="Manage Case">
                                                    <i class="fas fa-cogs"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center p-3">
                        <div>
                            <small class="text-muted">
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries
                            </small>
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page - 1); ?>&<?php echo http_build_query($_GET); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page + 1); ?>&<?php echo http_build_query($_GET); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Legend -->
            <div class="row">
                <div class="col-md-6">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-info-circle me-2"></i>Urgency Levels</h5>
                        </div>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="urgency-critical p-2 rounded bg-light">
                                    <span class="badge bg-danger">Critical</span> - Immediate attention required
                                </div>
                                <div class="urgency-high p-2 rounded bg-light">
                                    <span class="badge bg-warning text-dark">High</span> - Urgent action needed
                                </div>
                                <div class="urgency-medium p-2 rounded bg-light">
                                    <span class="badge bg-info">Medium</span> - Standard priority
                                </div>
                                <div class="urgency-low p-2 rounded bg-light">
                                    <span class="badge bg-success">Low</span> - Low priority
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-eye me-2"></i>Access Level: <?php echo ucfirst($account_type); ?></h5>
                        </div>
                            <p class="mb-0 text-muted">
                                <?php
                                echo match($account_type) {
                                    'superadmin' => 'You can view all grievances including anonymous submissions.',
                                    'admin' => 'You can view all non-anonymous grievances.',
                                    'manager' => 'You can view grievances from your location.',
                                    'supervisor' => 'You can view grievances from your location and department.',
                                    default => 'You can view your own submitted grievances only.'
                                };
                                ?>
                            </p>
                        </div>
                    </div>
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

        // Auto-submit form on filter change
        document.querySelectorAll('select[name="status"], select[name="category"], select[name="urgency"]').forEach(function(select) {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>