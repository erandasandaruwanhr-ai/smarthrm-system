<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($db, 'grievance.view')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Debug: Log user information
error_log("My Grievances Debug - User EPF: " . ($user['epf_number'] ?? 'not set'));
error_log("My Grievances Debug - User emp_number: " . ($user['emp_number'] ?? 'not set'));
error_log("My Grievances Debug - User email: " . ($user['email'] ?? 'not set'));
error_log("My Grievances Debug - Full user data: " . json_encode($user));

// Get user's grievances - try multiple EPF fields
$epf_to_search = $user['epf_number'] ?? $user['emp_number'] ?? $user['email'] ?? '';

// Try multiple approaches to find user's grievances
$grievances = [];

// Approach 1: Try with current EPF
if (!empty($epf_to_search)) {
    $grievances = $db->fetchAll("
        SELECT *, DATEDIFF(NOW(), submission_date) as days_open, 0 as is_anonymous
        FROM grievances
        WHERE employee_epf = ?
        ORDER BY submission_date DESC
    ", [$epf_to_search]);
}

// Approach 2: If no results and we have emp_number, try that
if (empty($grievances) && !empty($user['emp_number'])) {
    $grievances = $db->fetchAll("
        SELECT *, DATEDIFF(NOW(), submission_date) as days_open, 0 as is_anonymous
        FROM grievances
        WHERE employee_epf = ?
        ORDER BY submission_date DESC
    ", [$user['emp_number']]);
    error_log("My Grievances Debug - Tried emp_number: " . $user['emp_number']);
}

// Approach 3: If still no results, try by employee_name (exact match)
if (empty($grievances) && !empty($user['name'])) {
    $grievances = $db->fetchAll("
        SELECT *, DATEDIFF(NOW(), submission_date) as days_open, 0 as is_anonymous
        FROM grievances
        WHERE employee_name = ?
        ORDER BY submission_date DESC
    ", [$user['name']]);
    error_log("My Grievances Debug - Tried name: " . $user['name']);
}

// Approach 4: If still no results, try by email if available
if (empty($grievances) && !empty($user['email'])) {
    $grievances = $db->fetchAll("
        SELECT *, DATEDIFF(NOW(), submission_date) as days_open, 0 as is_anonymous
        FROM grievances
        WHERE employee_epf = ? OR employee_name LIKE ?
        ORDER BY submission_date DESC
    ", [$user['email'], '%' . $user['email'] . '%']);
    error_log("My Grievances Debug - Tried email: " . $user['email']);
}

// Debug: Log query results
error_log("My Grievances Debug - EPF searched for: " . $epf_to_search);
error_log("My Grievances Debug - Number of grievances found: " . count($grievances));

// If no grievances found, let's check what's actually in the grievances table
if (empty($grievances)) {
    $all_grievances = $db->fetchAll("
        SELECT case_id, employee_epf, employee_name
        FROM grievances
        ORDER BY submission_date DESC
        LIMIT 5
    ");
    error_log("My Grievances Debug - Sample grievances in DB: " . json_encode($all_grievances));
}

// Calculate statistics
$total_grievances = count($grievances);
$open_grievances = count(array_filter($grievances, fn($g) => in_array($g['status'], ['Open', 'Under Supervisory Review', 'Under Managerial Review'])));
$resolved_grievances = count(array_filter($grievances, fn($g) => $g['status'] === 'Resolved'));
$closed_grievances = count(array_filter($grievances, fn($g) => $g['status'] === 'Closed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - My Grievances</title>
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

        .stats-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .stats-card p {
            color: #7f8c8d;
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
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 100 !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
            width: 100% !important;
        }
        .top-navbar h5 { color: #007bff; font-weight: 600; margin: 0; }
        @media (max-width: 768px) { .main-content { margin-left: 0 !important; } }
    </style>
    <style>
        .bg-primary-light { background-color: rgba(0, 123, 255, 0.1); }
        .bg-success-light { background-color: rgba(40, 167, 69, 0.1); }
        .bg-warning-light { background-color: rgba(255, 193, 7, 0.1); }
        .bg-info-light { background-color: rgba(23, 162, 184, 0.1); }
        .text-primary { color: #007bff !important; }
        .text-success { color: #28a745 !important; }
        .text-warning { color: #ffc107 !important; }
        .text-info { color: #17a2b8 !important; }

        .grievance-card {
            transition: transform 0.2s ease-in-out;
            border-left: 4px solid #dee2e6;
        }

        .grievance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .grievance-card.status-open { border-left-color: #ffc107; }
        .grievance-card.status-in-progress { border-left-color: #17a2b8; }
        .grievance-card.status-resolved { border-left-color: #28a745; }
        .grievance-card.status-closed { border-left-color: #6c757d; }

        .urgency-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .urgency-critical { background-color: #dc3545; }
        .urgency-high { background-color: #fd7e14; }
        .urgency-medium { background-color: #ffc107; }
        .urgency-low { background-color: #28a745; }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        .timeline-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .timeline-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #dee2e6;
        }

        .timeline-dot.active {
            background-color: #007bff;
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
                        <h1><i class="fas fa-user-clock me-3"></i>My Grievances</h1>
                        <p>View your submitted grievances and track resolution status</p>
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
                    <li class="breadcrumb-item active">My Grievances</li>
                </ol>
            </nav>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-user me-2"></i>My Grievances</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Grievance</a></li>
                                <li class="breadcrumb-item active">My Grievances</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="action-buttons">
                        <a href="submit_grievance.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Submit New Grievance
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="icon bg-primary-light text-primary">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3><?php echo $total_grievances; ?></h3>
                        <p>Total Grievances</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="icon bg-warning-light text-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3><?php echo $open_grievances; ?></h3>
                        <p>Open Cases</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="icon bg-success-light text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3><?php echo $resolved_grievances; ?></h3>
                        <p>Resolved Cases</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="icon bg-info-light text-info">
                            <i class="fas fa-archive"></i>
                        </div>
                        <h3><?php echo $closed_grievances; ?></h3>
                        <p>Closed Cases</p>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card primary">
                        <div class="icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3><?php echo number_format($total_grievances); ?></h3>
                        <p>Total Grievances</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3><?php echo number_format($open_grievances); ?></h3>
                        <p>Open / In Progress</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3><?php echo number_format($resolved_grievances); ?></h3>
                        <p>Resolved</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card danger">
                        <div class="icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h3><?php echo number_format($closed_grievances); ?></h3>
                        <p>Closed</p>
                    </div>
                </div>
            </div>

            <!-- Grievances List -->
            <?php if (empty($grievances)): ?>
            <div class="content-card">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No Grievances Submitted</h4>
                    <p class="text-muted mb-4">You haven't submitted any grievances yet.</p>
                    <a href="submit_grievance.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Submit Your First Grievance
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="row g-4">
                <?php foreach ($grievances as $grievance): ?>
                <div class="col-lg-6">
                    <div class="content-card h-100">
                        <div class="position-relative">
                            <!-- Urgency Indicator -->
                            <div class="urgency-indicator urgency-<?php echo strtolower($grievance['urgency']); ?>" title="<?php echo $grievance['urgency']; ?> Priority"></div>

                            <!-- Case Header -->
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="card-title mb-1">
                                        <?php echo htmlspecialchars($grievance['case_id']); ?>
                                        <?php if ($grievance['is_anonymous']): ?>
                                        <span class="badge bg-secondary ms-2">Anonymous</span>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        Submitted on <?php echo date('d/m/Y H:i', strtotime($grievance['submission_date'])); ?>
                                    </small>
                                </div>
                                <span class="badge <?php
                                echo match($grievance['status']) {
                                    'Open' => 'bg-warning text-dark',
                                    'Under Supervisory Review' => 'bg-info',
                                    'Under Managerial Review' => 'bg-primary',
                                    'Resolved' => 'bg-success',
                                    default => 'bg-secondary'
                                };
                                ?>">
                                    <?php echo htmlspecialchars($grievance['status']); ?>
                                </span>
                            </div>

                            <!-- Subject and Category -->
                            <div class="mb-3">
                                <h6 class="mb-2"><?php echo htmlspecialchars($grievance['subject']); ?></h6>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars(explode('(', $grievance['category'])[0]); ?>
                                    </span>
                                    <span class="badge <?php
                                    echo match($grievance['urgency']) {
                                        'Critical' => 'bg-danger',
                                        'High' => 'bg-warning text-dark',
                                        'Medium' => 'bg-info',
                                        'Low' => 'bg-success'
                                    };
                                    ?>">
                                        <?php echo $grievance['urgency']; ?> Priority
                                    </span>
                                </div>
                            </div>

                            <!-- Description Preview -->
                            <p class="card-text text-muted">
                                <?php
                                $description = htmlspecialchars($grievance['description']);
                                echo strlen($description) > 150 ? substr($description, 0, 150) . '...' : $description;
                                ?>
                            </p>

                            <!-- Timeline/Progress -->
                            <div class="mb-3">
                                <small class="text-muted d-block mb-2">Progress Timeline</small>
                                <div class="timeline-indicator">
                                    <div class="timeline-dot active"></div>
                                    <small class="text-muted">Submitted</small>
                                    <div class="timeline-dot <?php echo in_array($grievance['status'], ['Under Supervisory Review', 'Under Managerial Review', 'Resolved', 'Closed']) ? 'active' : ''; ?>"></div>
                                    <small class="text-muted">Under Review</small>
                                    <div class="timeline-dot <?php echo in_array($grievance['status'], ['Resolved', 'Closed']) ? 'active' : ''; ?>"></div>
                                    <small class="text-muted">Resolved</small>
                                </div>
                            </div>

                            <!-- Days Open and Actions -->
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    <?php
                                    $days = $grievance['days_open'];
                                    if ($grievance['status'] === 'Resolved' || $grievance['status'] === 'Closed') {
                                        echo $grievance['status'];
                                    } else {
                                        $color = $days > 30 ? 'text-danger' : ($days > 14 ? 'text-warning' : 'text-success');
                                        echo "<span class='$color'>Open for $days days</span>";
                                    }
                                    ?>
                                </small>
                                <div class="btn-group btn-group-sm">
                                    <a href="view_grievance.php?id=<?php echo $grievance['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($grievance['status'] === 'Closed'): ?>
                                    <button class="btn btn-outline-warning" onclick="reopenCase(<?php echo $grievance['id']; ?>)">
                                        <i class="fas fa-redo"></i> Reopen
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Resolution Preview (if resolved) -->
                            <?php if ($grievance['status'] === 'Resolved' && !empty($grievance['resolution'])): ?>
                            <div class="mt-3 p-2 bg-success bg-opacity-10 rounded">
                                <small class="text-success">
                                    <i class="fas fa-check-circle me-1"></i>
                                    <strong>Resolution:</strong>
                                    <?php
                                    $resolution = htmlspecialchars($grievance['resolution']);
                                    echo strlen($resolution) > 100 ? substr($resolution, 0, 100) . '...' : $resolution;
                                    ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Help Information -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-question-circle me-2"></i>Grievance Status Guide</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <h6>Initial Stages</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Open:</strong> Your grievance has been submitted and is awaiting assignment</li>
                                        <li><strong>Under Supervisory Review:</strong> A supervisor is reviewing your case</li>
                                        <li><strong>Under Managerial Review:</strong> Management is reviewing your case</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h6>Active Stages</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Resolved:</strong> A solution has been found and implemented</li>
                                        <li><strong>Reopened:</strong> Case was reopened for further review</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h6>Final Stages</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Closed:</strong> Case is permanently closed</li>
                                        <li class="mt-3"><small class="text-muted">You can reopen a closed case if needed by clicking the "Reopen" button</small></li>
                                    </ul>
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
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Reopen case function
        function reopenCase(grievanceId) {
            if (confirm('Are you sure you want to reopen this case?')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'reopen_case.php';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'grievance_id';
                idInput.value = grievanceId;

                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-refresh every 5 minutes to check for status updates
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                window.location.reload();
            }
        }, 300000); // 5 minutes
    </script>
</body>
</html>