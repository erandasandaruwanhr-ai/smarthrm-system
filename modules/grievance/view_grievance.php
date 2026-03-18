<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

$grievance_id = $_GET['id'] ?? null;
$case_type = $_GET['type'] ?? 'regular'; // Default to regular if not specified

if (!$grievance_id) {
    header('Location: index.php');
    exit();
}

// Get grievance details from appropriate table
if ($case_type === 'anonymous') {
    $grievance = $db->fetch("SELECT *, 1 as is_anonymous FROM anonymous_grievances WHERE id = ?", [$grievance_id]);
    $table_prefix = 'anonymous_';

    // Add null employee fields for anonymous cases to prevent undefined key errors
    $grievance['employee_name'] = null;
    $grievance['employee_epf'] = null;
    $grievance['employee_location'] = null;
    $grievance['employee_department'] = null;
} else {
    $grievance = $db->fetch("SELECT *, 0 as is_anonymous FROM grievances WHERE id = ?", [$grievance_id]);
    $table_prefix = '';
}

if (!$grievance) {
    header('Location: index.php');
    exit();
}

// Check access permissions
$account_type = $user['account_type'] ?? 'user';
$can_view = false;
$is_investigation_team_member = false;

// Check if user is part of investigation team for anonymous cases
if ($grievance['is_anonymous']) {
    $investigation_team = $db->fetch(
        "SELECT * FROM anonymous_investigation_teams
         WHERE anonymous_grievance_id = ? AND (team_leader_epf = ? OR member1_epf = ? OR member2_epf = ?)",
        [$grievance_id, $user['epf_number'], $user['epf_number'], $user['epf_number']]
    );
    $is_investigation_team_member = ($investigation_team !== false);
}

if ($account_type === 'superadmin') {
    $can_view = true;
} elseif ($account_type === 'admin') {
    $can_view = true;
} elseif ($account_type === 'manager') {
    if (!$grievance['is_anonymous']) {
        $can_view = (($grievance['employee_location'] ?? '') === $user['location']);
    }
} elseif ($account_type === 'supervisor') {
    if (!$grievance['is_anonymous']) {
        $can_view = (($grievance['employee_location'] ?? '') === $user['location'] &&
                     ($grievance['employee_department'] ?? '') === $user['department']);
    }
} else {
    // Regular user can only view their own grievances
    if (!$grievance['is_anonymous']) {
        $can_view = (($grievance['employee_epf'] ?? '') === $user['epf_number']);
    }
}

// Universal access for investigation team members (regardless of account type)
if ($grievance['is_anonymous'] && $is_investigation_team_member) {
    $can_view = true;
}

if (!$can_view) {
    header('Location: index.php');
    exit();
}

// Get case notes from appropriate table
if ($grievance['is_anonymous']) {
    $notes = $db->fetchAll("SELECT * FROM anonymous_grievance_notes WHERE anonymous_grievance_id = ? ORDER BY created_at ASC", [$grievance_id]);
    $evidence_files = $db->fetchAll("SELECT * FROM anonymous_grievance_evidence WHERE anonymous_grievance_id = ?", [$grievance_id]);
} else {
    $notes = $db->fetchAll("SELECT * FROM grievance_notes WHERE grievance_id = ? ORDER BY created_at ASC", [$grievance_id]);
    $evidence_files = $db->fetchAll("SELECT * FROM grievance_evidence WHERE grievance_id = ?", [$grievance_id]);
}

// Get investigation team (if exists)
if ($grievance['is_anonymous']) {
    $investigation_team = $db->fetch("SELECT * FROM anonymous_investigation_teams WHERE anonymous_grievance_id = ? AND status = 'Active'", [$grievance_id]);
} else {
    $investigation_team = $db->fetch("SELECT * FROM grievance_investigators WHERE grievance_id = ? AND status = 'Active'", [$grievance_id]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - View Grievance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        /* Force correct layout for grievance sub-module pages */
        body { margin: 0; padding: 0; }
        .main-content {
            margin-left: 280px !important;
            min-height: 100vh;
            padding: 0 !important;
            width: calc(100% - 280px) !important;
            position: relative;
        }
        .top-navbar {
            background: white !important;
            border-bottom: 1px solid #dee2e6 !important;
            padding: 1rem 2rem !important;
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
        .case-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border-radius: 0.5rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -0.75rem;
            top: 0.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #007bff;
            border: 3px solid #fff;
            box-shadow: 0 0 0 3px #dee2e6;
        }

        .info-card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .status-badge {
            font-size: 1.1rem;
            padding: 0.5rem 1rem;
        }

        .evidence-file {
            border: 1px dashed #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .evidence-file:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }

        .print-section {
            display: none;
        }

        @media print {
            .no-print { display: none !important; }
            .print-section { display: block !important; }
            .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar no-print">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0 ms-2">View Grievance</h5>
            </div>

            <div class="user-info">
                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>
                        <?php echo htmlspecialchars($user['name']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header no-print">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-eye me-2"></i>Grievance Details</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Grievance</a></li>
                                <li class="breadcrumb-item active">View</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="action-buttons">
                        <?php if (in_array($account_type, ['superadmin', 'admin', 'manager', 'supervisor'])): ?>
                        <a href="case_management.php?id=<?php echo $grievance['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-cogs me-2"></i>Manage Case
                        </a>
                        <?php endif; ?>
                        <button onclick="window.print()" class="btn btn-outline-secondary">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                        <?php if (hasModulePermission($db, 'grievance.grievance_list')): ?>
                        <a href="grievance_list.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to List
                        </a>
                        <?php elseif (hasModulePermission($db, 'grievance.my_grievances')): ?>
                        <a href="my_grievances.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to My Grievances
                        </a>
                        <?php else: ?>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Grievance Dashboard
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Print Header -->
            <div class="print-section">
                <div class="text-center mb-4">
                    <h2>SmartHRM - Grievance Report</h2>
                    <p>Generated on <?php echo date('d/m/Y H:i'); ?></p>
                </div>
            </div>

            <!-- Case Header -->
            <div class="case-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-1"><?php echo htmlspecialchars($grievance['case_id']); ?></h2>
                        <p class="mb-2 opacity-75"><?php echo htmlspecialchars($grievance['subject']); ?></p>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($grievance['is_anonymous']): ?>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-user-secret me-1"></i>Anonymous
                            </span>
                            <?php endif; ?>
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
                    <div class="col-md-4 text-end">
                        <span class="status-badge badge <?php
                        echo match($grievance['status']) {
                            'Open' => 'bg-warning text-dark',
                            'Under Supervisory Review' => 'bg-primary',
                            'Under Managerial Review' => 'bg-primary',
                            'Resolved' => 'bg-success',
                            'Closed' => 'bg-secondary',
                            'Reopened' => 'bg-danger',
                            default => 'bg-secondary'
                        };
                        ?>">
                            <?php echo htmlspecialchars($grievance['status']); ?>
                        </span>
                        <div class="mt-2">
                            <small class="opacity-75">
                                Submitted: <?php echo date('d/m/Y H:i', strtotime($grievance['submission_date'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Employee Information -->
                    <div class="card info-card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Employee Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td><strong>Name:</strong></td>
                                            <td>
                                                <?php if ($grievance['is_anonymous'] && $account_type !== 'superadmin'): ?>
                                                    <span class="text-muted"><i class="fas fa-user-secret me-2"></i>Anonymous</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($grievance['employee_name'] ?? 'N/A'); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>EPF Number:</strong></td>
                                            <td>
                                                <?php if ($grievance['is_anonymous'] && $account_type !== 'superadmin'): ?>
                                                    <span class="text-muted"><i class="fas fa-eye-slash me-2"></i>Protected</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($grievance['employee_epf'] ?? 'N/A'); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td><strong>Location:</strong></td>
                                            <td>
                                                <?php if ($grievance['is_anonymous']): ?>
                                                    <span class="text-muted"><?php echo htmlspecialchars($grievance['submitter_location'] ?? 'Not disclosed'); ?></span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($grievance['employee_location'] ?? 'Unknown'); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Department:</strong></td>
                                            <td>
                                                <?php if ($grievance['is_anonymous']): ?>
                                                    <span class="text-muted"><?php echo htmlspecialchars($grievance['submitter_department'] ?? 'Not disclosed'); ?></span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($grievance['employee_department'] ?? 'Unknown'); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grievance Details -->
                    <div class="card info-card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Grievance Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6>Category</h6>
                                <p><?php echo htmlspecialchars($grievance['category']); ?></p>
                            </div>

                            <div class="mb-3">
                                <h6>Subject</h6>
                                <p><?php echo htmlspecialchars($grievance['subject']); ?></p>
                            </div>

                            <div class="mb-3">
                                <h6>Detailed Description</h6>
                                <p><?php echo nl2br(htmlspecialchars($grievance['description'])); ?></p>
                            </div>

                            <?php if (!empty($grievance['incident_date']) || !empty($grievance['incident_location'])): ?>
                            <div class="row">
                                <?php if (!empty($grievance['incident_date'])): ?>
                                <div class="col-md-6">
                                    <h6>Incident Date</h6>
                                    <p><?php echo date('d/m/Y', strtotime($grievance['incident_date'])); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($grievance['incident_location'])): ?>
                                <div class="col-md-6">
                                    <h6>Incident Location</h6>
                                    <p><?php echo htmlspecialchars($grievance['incident_location'] ?? 'Not specified'); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($grievance['witnesses'])): ?>
                            <div class="mb-3">
                                <h6>Witnesses</h6>
                                <p><?php echo nl2br(htmlspecialchars($grievance['witnesses'] ?? '')); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($grievance['status'] === 'Resolved' && !empty($grievance['resolution'])): ?>
                            <div class="mt-4 p-3 bg-success bg-opacity-10 rounded">
                                <h6 class="text-success"><i class="fas fa-check-circle me-2"></i>Resolution</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($grievance['resolution'] ?? '')); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Evidence Files -->
                    <?php if (!empty($evidence_files)): ?>
                    <div class="card info-card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-paperclip me-2"></i>Evidence Files</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($evidence_files as $file): ?>
                                <div class="col-md-4">
                                    <div class="evidence-file">
                                        <i class="fas fa-file fa-2x text-muted mb-2"></i>
                                        <p class="mb-1 small"><strong><?php echo htmlspecialchars($file['file_name']); ?></strong></p>
                                        <p class="mb-2 text-muted small"><?php echo strtoupper($file['file_type']); ?> - <?php echo number_format($file['file_size'] / 1024, 1); ?> KB</p>
                                        <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="btn btn-outline-primary btn-sm no-print">
                                            <i class="fas fa-download me-1"></i>Download
                                        </a>
                                        <p class="print-section small text-muted">File: <?php echo htmlspecialchars($file['file_name']); ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Case Timeline -->
                    <div class="card info-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Case Timeline</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($notes)): ?>
                            <p class="text-muted text-center py-4">No activities recorded yet</p>
                            <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($notes as $note): ?>
                                <div class="timeline-item">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($note['action_type']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y H:i', strtotime($note['created_at'])); ?>
                                                </small>
                                            </div>
                                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($note['note'] ?? '')); ?></p>
                                            <small class="text-muted">
                                                by <?php echo htmlspecialchars($note['user_name'] ?? 'Unknown'); ?> (<?php echo htmlspecialchars($note['user_epf'] ?? 'N/A'); ?>)
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Information -->
                <div class="col-lg-4">
                    <!-- Case Summary -->
                    <div class="card info-card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Case Summary</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td><strong>Submitted:</strong></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($grievance['submission_date'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Days Open:</strong></td>
                                    <td>
                                        <?php
                                        $days = (new DateTime())->diff(new DateTime($grievance['submission_date']))->days;
                                        $color = $days > 30 ? 'text-danger' : ($days > 14 ? 'text-warning' : 'text-success');
                                        echo "<span class='$color'>$days days</span>";
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Urgency:</strong></td>
                                    <td>
                                        <span class="badge <?php
                                        echo match($grievance['urgency']) {
                                            'Critical' => 'bg-danger',
                                            'High' => 'bg-warning text-dark',
                                            'Medium' => 'bg-info',
                                            'Low' => 'bg-success'
                                        };
                                        ?>">
                                            <?php echo $grievance['urgency']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Type:</strong></td>
                                    <td><?php echo $grievance['is_anonymous'] ? 'Anonymous' : 'Regular'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Investigation Team -->
                    <?php if ($investigation_team): ?>
                    <div class="card info-card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Investigation Team</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>Team Leader:</strong><br>
                                <?php echo htmlspecialchars($investigation_team['team_leader_name'] ?? 'Unknown'); ?>
                                <small class="text-muted d-block"><?php echo htmlspecialchars($investigation_team['team_leader_epf'] ?? 'N/A'); ?></small>
                            </div>
                            <?php if ($investigation_team['member1_name']): ?>
                            <div class="mb-2">
                                <strong>Member 1:</strong><br>
                                <?php echo htmlspecialchars($investigation_team['member1_name'] ?? 'Unknown'); ?>
                                <small class="text-muted d-block"><?php echo htmlspecialchars($investigation_team['member1_epf'] ?? 'N/A'); ?></small>
                            </div>
                            <?php endif; ?>
                            <?php if ($investigation_team['member2_name']): ?>
                            <div class="mb-2">
                                <strong>Member 2:</strong><br>
                                <?php echo htmlspecialchars($investigation_team['member2_name'] ?? 'Unknown'); ?>
                                <small class="text-muted d-block"><?php echo htmlspecialchars($investigation_team['member2_epf'] ?? 'N/A'); ?></small>
                            </div>
                            <?php endif; ?>
                            <small class="text-muted">
                                Assigned: <?php echo date('d/m/Y', strtotime($investigation_team['assigned_date'])); ?>
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Quick Actions -->
                    <div class="card info-card no-print">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if ($grievance['employee_epf'] === $user['epf_number']): ?>
                                <a href="my_grievances.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-list me-2"></i>My Grievances
                                </a>
                                <?php endif; ?>
                                <a href="submit_grievance.php" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-plus me-2"></i>New Grievance
                                </a>
                                <?php if (in_array($account_type, ['superadmin', 'admin', 'manager', 'supervisor'])): ?>
                                <a href="case_management.php?id=<?php echo $grievance['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-cogs me-2"></i>Manage Case
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>