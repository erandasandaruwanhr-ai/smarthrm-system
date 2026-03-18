<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/location_access.php';

$user = getCurrentUser();
$db = new Database();

// Check module permissions
if (!isSuperAdmin() && !hasModulePermission($db, 'training.view')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get user location and account type for access control
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;

// Build role-based filter for training requirements
$tr_filter = '';
$tr_params = [];

if ($account_type === 'superadmin') {
    // No filter
} elseif ($account_type === 'admin') {
    $tr_filter = ' AND tr.location = ?';
    $tr_params = [$user_location];
} elseif (in_array($account_type, ['manager', 'supervisor'])) {
    $accessible = getAccessibleEmployeesByLocation($db, $user);
    $accessible_epfs = array_column($accessible, 'epf_number');
    if (!empty($accessible_epfs)) {
        $placeholders = str_repeat('?,', count($accessible_epfs) - 1) . '?';
        $tr_filter = " AND tr.epf_number IN ($placeholders)";
        $tr_params = $accessible_epfs;
    } else {
        $tr_filter = ' AND 1=0';
    }
} else {
    $tr_filter = ' AND tr.epf_number = ?';
    $tr_params = [$user['epf_number']];
}

$message = '';
$error = '';

// Handle form submission
if ($_POST && isset($_POST['submit_requirement'])) {
    try {
        // Generate Training ID (Format: TR-YYYY-XXX)
        $year = $_POST['year'];
        $latest_training = $db->fetch("SELECT training_id FROM training_requirements WHERE year = ? ORDER BY id DESC LIMIT 1", [$year]);

        if ($latest_training && $latest_training['training_id']) {
            // Extract sequence number from existing training ID (TR-2026-001 -> 001)
            $parts = explode('-', $latest_training['training_id']);
            $sequence = intval(end($parts)) + 1;
        } else {
            $sequence = 1;
        }

        $training_id = sprintf("TR-%s-%03d", $year, $sequence);

        $db->query("INSERT INTO training_requirements (training_id, year, training_requirement, training_type, proposed_period, epf_number, employee_name, location, department, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $training_id,
            $_POST['year'],
            $_POST['training_requirement'],
            $_POST['training_type'],
            $_POST['proposed_period'],
            $_POST['epf_number'],
            $_POST['employee_name'],
            $_POST['location'],
            $_POST['department'],
            $user['epf_number']
        ]);

        $message = "Training requirement submitted successfully! Training ID: " . $training_id;
    } catch (Exception $e) {
        $error = "Error submitting requirement: " . $e->getMessage();
    }
}

// Handle bulk upload
if ($_POST && isset($_POST['bulk_upload']) && isset($_FILES['csv_file'])) {
    try {
        $file = $_FILES['csv_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Could not read file');
        }

        // Skip header row
        fgetcsv($handle);

        $count = 0;
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) >= 8) {
                $db->query("INSERT INTO training_requirements (year, training_requirement, training_type, proposed_period, epf_number, employee_name, location, department, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                    $data[0], // year
                    $data[1], // training_requirement
                    $data[2], // training_type
                    $data[3], // proposed_period
                    $data[4], // epf_number
                    $data[5], // employee_name
                    $data[6], // location
                    $data[7], // department
                    $user['epf_number']
                ]);
                $count++;
            }
        }
        fclose($handle);

        $message = "Successfully uploaded $count training requirements!";
    } catch (Exception $e) {
        $error = "Bulk upload failed: " . $e->getMessage();
    }
}

// Handle status updates
if ($_POST && isset($_POST['update_status'])) {
    try {
        $new_status = $_POST['new_status'];
        $requirement_id = $_POST['requirement_id'];

        // Check authorization for delete operation
        if ($new_status === 'cancelled' && !isSuperAdmin()) {
            $error = "Access denied: Only SuperAdmin can delete training requirements.";
        } elseif ($new_status === 'cancelled') {
            // Check for related records in all training sub-modules before deletion
            $related_counts = [];
            $related_counts['training_plans'] = $db->fetch("SELECT COUNT(*) as count FROM training_plans WHERE requirement_id = ?", [$requirement_id])['count'];
            $related_counts['training_budget'] = $db->fetch("SELECT COUNT(*) as count FROM training_budget WHERE requirement_id = ?", [$requirement_id])['count'];
            $related_counts['training_evaluations'] = $db->fetch("SELECT COUNT(*) as count FROM training_evaluations te JOIN training_plans tp ON te.training_plan_id = tp.id WHERE tp.requirement_id = ?", [$requirement_id])['count'];
            $related_counts['training_feedback'] = $db->fetch("SELECT COUNT(*) as count FROM training_feedback tf JOIN training_plans tp ON tf.training_plan_id = tp.id WHERE tp.requirement_id = ?", [$requirement_id])['count'];

            // Check if any related records exist
            $total_related = array_sum($related_counts);
            $related_modules = [];
            foreach ($related_counts as $table => $count) {
                if ($count > 0) {
                    $related_modules[] = str_replace('training_', '', $table) . " ($count)";
                }
            }

            if ($total_related > 0) {
                // Cannot delete due to related records - mark as cancelled instead
                $db->query("UPDATE training_requirements SET status = 'cancelled' WHERE id = ?", [$requirement_id]);
                $related_list = implode(', ', $related_modules);
                $message = "Training requirement cancelled successfully! (Cannot delete due to related records in: $related_list)";
            } else {
                // Actually DELETE the record if no related data exists
                $db->query("DELETE FROM training_requirements WHERE id = ?", [$requirement_id]);
                $message = "Training requirement deleted successfully!";
            }
        } else {
            // Regular status update
            $db->query("UPDATE training_requirements SET status = ? WHERE id = ?", [$new_status, $requirement_id]);

            $status_text = match($new_status) {
                'approved' => 'approved',
                'rejected' => 'rejected',
                default => 'updated'
            };

            $message = "Training requirement {$status_text} successfully!";
        }
    } catch (Exception $e) {
        $error = "Error updating status: " . $e->getMessage();
    }
}

// Get current year requirements with role-based filtering
$current_year = $_GET['year'] ?? date('Y');
$req_params = array_merge([$current_year], $tr_params);
$requirements = $db->fetchAll("SELECT * FROM training_requirements tr WHERE tr.year = ?" . $tr_filter . " ORDER BY tr.created_at DESC", $req_params);

// Get departments and locations for filters
$departments = $db->fetchAll("SELECT DISTINCT department FROM training_requirements ORDER BY department");
$locations = $db->fetchAll("SELECT DISTINCT location FROM training_requirements ORDER BY location");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Training Requirements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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

        .header-content {
            position: relative;
            z-index: 2;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .nav-pills .nav-link {
            border-radius: 10px;
            margin-right: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
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
                        <h1><i class="fas fa-clipboard-list me-3"></i>Training Requirements</h1>
                        <p class="mb-0">Manage training needs assessment and requirements for <?php echo $current_year; ?></p>
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Training Management</a></li>
                    <li class="breadcrumb-item active">Training Requirements</li>
                </ol>
            </nav>

            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Navigation Tabs -->
            <div class="content-card">
                <ul class="nav nav-pills mb-4" id="requirementsTabs">
                    <li class="nav-item">
                        <button class="nav-link active" id="add-tab" data-bs-toggle="pill" data-bs-target="#add-requirement">
                            <i class="fas fa-plus me-2"></i>Add Requirement
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="bulk-tab" data-bs-toggle="pill" data-bs-target="#bulk-upload">
                            <i class="fas fa-upload me-2"></i>Bulk Upload
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="list-tab" data-bs-toggle="pill" data-bs-target="#requirements-list">
                            <i class="fas fa-list me-2"></i>View Requirements
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="requirementsTabContent">
                    <!-- Add Requirement Tab -->
                    <div class="tab-pane fade show active" id="add-requirement">
                        <form method="POST" class="row">
                            <div class="col-md-6 mb-3">
                                <label for="year" class="form-label">Year <span class="text-danger">*</span></label>
                                <select class="form-select" name="year" required>
                                    <option value="<?php echo date('Y'); ?>" selected><?php echo date('Y'); ?></option>
                                    <option value="<?php echo date('Y')+1; ?>"><?php echo date('Y')+1; ?></option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="training_type" class="form-label">Training Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="training_type" required>
                                    <option value="">Select Type</option>
                                    <option value="awareness">Awareness</option>
                                    <option value="certificate">Certificate</option>
                                    <option value="diploma">Diploma</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="training_requirement" class="form-label">Training Requirement <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="training_requirement" rows="3" placeholder="Describe the training requirement in detail..." required></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="proposed_period" class="form-label">Proposed Period <span class="text-danger">*</span></label>
                                <select class="form-select" name="proposed_period" required>
                                    <option value="">Select Quarter</option>
                                    <option value="1st Quarter">1st Quarter</option>
                                    <option value="2nd Quarter">2nd Quarter</option>
                                    <option value="3rd Quarter">3rd Quarter</option>
                                    <option value="4th Quarter">4th Quarter</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="epf_number" class="form-label">EPF Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="epf_number" id="epf_number" placeholder="Enter EPF number" required>
                                <div class="form-text" id="epf_loading" style="display: none;">
                                    <i class="fas fa-spinner fa-spin"></i> Loading employee details...
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="employee_name" class="form-label">Employee Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="employee_name" id="employee_name" placeholder="Enter employee full name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="location" id="location" placeholder="Enter work location" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="department" id="department" placeholder="Enter department name" required>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="submit_requirement" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Submit Requirement
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Bulk Upload Tab -->
                    <div class="tab-pane fade" id="bulk-upload">
                        <div class="alert alert-info">
                            <h6 class="alert-heading">CSV Upload Format</h6>
                            <p class="mb-2">Please ensure your CSV file has the following columns in order:</p>
                            <p class="mb-0"><strong>Year, Training Requirement, Training Type, Proposed Period, EPF Number, Employee Name, Location, Department</strong></p>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="csv_file" class="form-label">CSV File <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                                <div class="form-text">Maximum file size: 2MB. Only CSV files are allowed.</div>
                            </div>
                            <button type="submit" name="bulk_upload" class="btn btn-success btn-lg">
                                <i class="fas fa-upload me-2"></i>Upload Requirements
                            </button>
                        </form>

                        <div class="mt-4">
                            <a href="templates/training_requirements_template.csv" class="btn btn-outline-primary">
                                <i class="fas fa-download me-2"></i>Download CSV Template
                            </a>
                        </div>
                    </div>

                    <!-- Requirements List Tab -->
                    <div class="tab-pane fade" id="requirements-list">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5>Training Requirements for <?php echo $current_year; ?></h5>
                            <div class="d-flex gap-2">
                                <select class="form-select form-select-sm" style="width: auto;" onchange="window.location.href='?year='+this.value">
                                    <option value="<?php echo date('Y')-1; ?>" <?php echo $current_year == date('Y')-1 ? 'selected' : ''; ?>><?php echo date('Y')-1; ?></option>
                                    <option value="<?php echo date('Y'); ?>" <?php echo $current_year == date('Y') ? 'selected' : ''; ?>><?php echo date('Y'); ?></option>
                                    <option value="<?php echo date('Y')+1; ?>" <?php echo $current_year == date('Y')+1 ? 'selected' : ''; ?>><?php echo date('Y')+1; ?></option>
                                </select>
                                <a href="export_requirements.php?year=<?php echo $current_year; ?>" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-file-excel me-1"></i>Export
                                </a>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped" id="requirementsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Training ID</th>
                                        <th>Training Requirement</th>
                                        <th>Type</th>
                                        <th>Period</th>
                                        <th>Employee</th>
                                        <th>Location</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requirements as $req): ?>
                                    <tr>
                                        <td><?php echo $req['id']; ?></td>
                                        <td><span class="badge bg-primary"><?php echo $req['training_id']; ?></span></td>
                                        <td><?php echo htmlspecialchars(substr($req['training_requirement'], 0, 50)); ?>...</td>
                                        <td><span class="badge bg-info"><?php echo ucfirst($req['training_type']); ?></span></td>
                                        <td><?php echo $req['proposed_period']; ?></td>
                                        <td><?php echo htmlspecialchars($req['employee_name']); ?><br><small class="text-muted"><?php echo $req['epf_number']; ?></small></td>
                                        <td><?php echo htmlspecialchars($req['location']); ?></td>
                                        <td><?php echo htmlspecialchars($req['department']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo match($req['status']) {
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    'completed' => 'primary',
                                                    'cancelled' => 'dark',
                                                    default => 'warning'
                                                };
                                            ?>">
                                                <?php echo ucfirst($req['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <!-- View button is always available -->
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="viewRequirement(<?php echo $req['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>

                                                <!-- Approve/Reject buttons only for pending requirements -->
                                                <?php if ($req['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-outline-success btn-sm" onclick="updateStatus(<?php echo $req['id']; ?>, 'approved')" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="updateStatus(<?php echo $req['id']; ?>, 'rejected')" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php else: ?>
                                                <!-- Show status info for non-pending requirements -->
                                                <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Status: <?php echo ucfirst($req['status']); ?>">
                                                    <i class="fas fa-<?php
                                                        echo match($req['status']) {
                                                            'approved' => 'check-circle',
                                                            'rejected' => 'times-circle',
                                                            'completed' => 'flag-checkered',
                                                            'cancelled' => 'ban',
                                                            default => 'clock'
                                                        };
                                                    ?>"></i>
                                                </button>
                                                <?php endif; ?>

                                                <!-- Cancel/Delete button for SuperAdmin only -->
                                                <?php if (isSuperAdmin()): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="cancelRequirement(<?php echo $req['id']; ?>)" title="Delete Training Requirement">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Form (Hidden) -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="requirement_id" id="statusRequirementId">
        <input type="hidden" name="new_status" id="newStatus">
        <input type="hidden" name="update_status" value="1">
    </form>

    <!-- View Requirement Modal -->
    <div class="modal fade" id="viewRequirementModal" tabindex="-1" aria-labelledby="viewRequirementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewRequirementModalLabel">
                        <i class="fas fa-eye me-2"></i>Training Requirement Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="requirementDetails">
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading requirement details...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <div class="btn-group" id="requirementActions" style="display: none;">
                        <button type="button" class="btn btn-success" onclick="approveFromModal()">
                            <i class="fas fa-check me-1"></i>Approve
                        </button>
                        <button type="button" class="btn btn-danger" onclick="rejectFromModal()">
                            <i class="fas fa-times me-1"></i>Reject
                        </button>
                    </div>

                    <!-- SuperAdmin Cancel Button (always available) -->
                    <?php if (isSuperAdmin()): ?>
                    <button type="button" class="btn btn-danger" id="cancelFromModal" onclick="cancelFromModal()" style="display: none;">
                        <i class="fas fa-trash-alt me-1"></i>Delete
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#requirementsTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [9] }
                ]
            });
        });

        function updateStatus(id, status) {
            if (confirm(`Are you sure you want to ${status} this training requirement?`)) {
                document.getElementById('statusRequirementId').value = id;
                document.getElementById('newStatus').value = status;
                document.getElementById('statusForm').submit();
            }
        }

        let currentRequirementId = null;

        function viewRequirement(id) {
            currentRequirementId = id;

            // Show modal
            $('#viewRequirementModal').modal('show');

            // Reset modal content to loading state
            $('#requirementDetails').html('<div class="text-center py-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading requirement details...</p></div>');

            // Hide action buttons initially
            $('#requirementActions').hide();

            // Fetch requirement details
            $.ajax({
                url: 'ajax/get_requirement_details.php',
                method: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayRequirementDetails(response);
                    } else {
                        $('#requirementDetails').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + response.message + '</div>');
                    }
                },
                error: function() {
                    $('#requirementDetails').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error loading requirement details. Please try again.</div>');
                }
            });
        }

        function displayRequirementDetails(data) {
            const req = data.requirement;
            const plans = data.training_plans;
            const evaluations = data.evaluations;
            const feedback = data.feedback;

            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-info-circle me-2"></i>Basic Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Training ID:</th>
                                <td><span class="badge bg-primary">${req.training_id}</span></td>
                            </tr>
                            <tr>
                                <th>Year:</th>
                                <td>${req.year}</td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge bg-${req.status === 'approved' ? 'success' : req.status === 'rejected' ? 'danger' : req.status === 'completed' ? 'primary' : req.status === 'cancelled' ? 'dark' : 'warning'}">
                                        ${req.status.charAt(0).toUpperCase() + req.status.slice(1)}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Training Type:</th>
                                <td><span class="badge bg-info">${req.training_type.charAt(0).toUpperCase() + req.training_type.slice(1)}</span></td>
                            </tr>
                            <tr>
                                <th>Proposed Period:</th>
                                <td>${req.proposed_period}</td>
                            </tr>
                            <tr>
                                <th>Created Date:</th>
                                <td>${new Date(req.created_at).toLocaleDateString()}</td>
                            </tr>
                            ${req.created_by_name ? `
                            <tr>
                                <th>Created By:</th>
                                <td>${req.created_by_name}</td>
                            </tr>
                            ` : ''}
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-user me-2"></i>Employee Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">EPF Number:</th>
                                <td>${req.epf_number}</td>
                            </tr>
                            <tr>
                                <th>Employee Name:</th>
                                <td>${req.employee_name}</td>
                            </tr>
                            ${req.employee_full_name ? `
                            <tr>
                                <th>Full Name:</th>
                                <td>${req.employee_full_name}</td>
                            </tr>
                            ` : ''}
                            ${req.employment_level ? `
                            <tr>
                                <th>Employment Level:</th>
                                <td>${req.employment_level}</td>
                            </tr>
                            ` : ''}
                            <tr>
                                <th>Location:</th>
                                <td>${req.location}</td>
                            </tr>
                            <tr>
                                <th>Department:</th>
                                <td>${req.department}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="mt-3">
                    <h6><i class="fas fa-clipboard-list me-2"></i>Training Requirement Details</h6>
                    <div class="card">
                        <div class="card-body">
                            ${req.training_requirement}
                        </div>
                    </div>
                </div>
            `;

            // Add training plans if any
            if (plans && plans.length > 0) {
                html += `
                    <div class="mt-4">
                        <h6><i class="fas fa-calendar-check me-2"></i>Training Plans (${plans.length})</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Training Name</th>
                                        <th>Institute</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Cost</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;

                plans.forEach(plan => {
                    html += `
                        <tr>
                            <td>${plan.training_name}</td>
                            <td>${plan.training_institute}</td>
                            <td>${plan.training_start_date}</td>
                            <td>${plan.training_end_date || 'N/A'}</td>
                            <td>Rs. ${parseFloat(plan.training_cost).toLocaleString()}</td>
                            <td><span class="badge bg-${plan.status === 'completed' ? 'success' : plan.status === 'cancelled' ? 'danger' : 'warning'}">${plan.status.charAt(0).toUpperCase() + plan.status.slice(1)}</span></td>
                        </tr>
                    `;
                });

                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            }

            // Add evaluation count if any
            if (evaluations && evaluations.length > 0) {
                html += `
                    <div class="mt-3">
                        <h6><i class="fas fa-star me-2"></i>Evaluations</h6>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            ${evaluations.length} training evaluation(s) submitted
                        </div>
                    </div>
                `;
            }

            // Add feedback count if any
            if (feedback && feedback.length > 0) {
                html += `
                    <div class="mt-3">
                        <h6><i class="fas fa-comments me-2"></i>Feedback</h6>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            ${feedback.length} training feedback(s) submitted
                        </div>
                    </div>
                `;
            }

            $('#requirementDetails').html(html);

            // Show action buttons only if status allows modification
            if (req.status === 'pending') {
                $('#requirementActions').show();
            } else {
                $('#requirementActions').hide();
            }

            // Show cancel button for SuperAdmin (always available)
            <?php if (isSuperAdmin()): ?>
            $('#cancelFromModal').show();
            <?php endif; ?>
        }

        function approveFromModal() {
            if (currentRequirementId && confirm('Are you sure you want to approve this training requirement?')) {
                // Update status via AJAX to avoid page refresh
                updateStatusAjax(currentRequirementId, 'approved');
            }
        }

        function rejectFromModal() {
            if (currentRequirementId && confirm('Are you sure you want to reject this training requirement?')) {
                // Update status via AJAX to avoid page refresh
                updateStatusAjax(currentRequirementId, 'rejected');
            }
        }

        function cancelRequirement(id) {
            if (confirm('Are you sure you want to DELETE this training requirement?\n\nIf this requirement has related records (plans, budget, evaluations, feedback), it will be CANCELLED instead of deleted to preserve data integrity.\n\nOtherwise, it will be permanently removed from the database.\n\nThis action cannot be undone.')) {
                updateStatusAjax(id, 'cancelled');
            }
        }

        function cancelFromModal() {
            if (currentRequirementId && confirm('Are you sure you want to DELETE this training requirement?\n\nIf this requirement has related records (plans, budget, evaluations, feedback), it will be CANCELLED instead of deleted to preserve data integrity.\n\nOtherwise, it will be permanently removed from the database.\n\nThis action cannot be undone.')) {
                updateStatusAjax(currentRequirementId, 'cancelled');
            }
        }

        function updateStatusAjax(id, status) {
            // Show loading state in action buttons
            $('#requirementActions').html('<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Updating...</span></div><span class="ms-2">Updating status...</span>');

            // Create form data
            const formData = new FormData();
            formData.append('requirement_id', id);
            formData.append('new_status', status);
            formData.append('update_status', '1');

            // Submit via AJAX
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function() {
                    // Show success message
                    const statusText = status === 'approved' ? 'approved' : status === 'rejected' ? 'rejected' : status === 'cancelled' ? 'deleted' : 'updated';

                    // Show success message in action area
                    $('#requirementActions').html('<div class="alert alert-success mb-0 py-2"><i class="fas fa-check-circle me-2"></i>Training requirement has been ' + statusText + ' successfully!</div>');

                    // Close modal and refresh page to show updated status
                    setTimeout(() => {
                        $('#viewRequirementModal').modal('hide');
                        location.reload();
                    }, 1500);
                },
                error: function() {
                    // Reset action buttons on error
                    $('#requirementActions').html('<button type="button" class="btn btn-success" onclick="approveFromModal()"><i class="fas fa-check me-1"></i>Approve</button><button type="button" class="btn btn-danger" onclick="rejectFromModal()"><i class="fas fa-times me-1"></i>Reject</button>');
                    alert('Error updating status. Please try again.');
                }
            });
        }

        // Auto-fill employee details when EPF number is entered
        $('#epf_number').on('blur', function() {
            const epfNumber = $(this).val().trim();

            if (epfNumber === '') {
                return;
            }

            $('#epf_loading').show();

            $.ajax({
                url: 'ajax/get_employee_details.php',
                method: 'GET',
                data: { epf_number: epfNumber },
                dataType: 'json',
                success: function(response) {
                    $('#epf_loading').hide();

                    if (response.success) {
                        $('#employee_name').val(response.employee.employee_name);
                        $('#location').val(response.employee.location);
                        $('#department').val(response.employee.department);

                        // Show success feedback
                        $('#epf_number').addClass('is-valid').removeClass('is-invalid');
                    } else {
                        // Show error feedback
                        $('#epf_number').addClass('is-invalid').removeClass('is-valid');

                        // Clear the fields
                        $('#employee_name').val('');
                        $('#location').val('');
                        $('#department').val('');

                        // Show error message
                        if (!$('#epf_error').length) {
                            $('#epf_number').after('<div class="invalid-feedback" id="epf_error">' + response.message + '</div>');
                        } else {
                            $('#epf_error').text(response.message);
                        }
                    }
                },
                error: function() {
                    $('#epf_loading').hide();
                    $('#epf_number').addClass('is-invalid').removeClass('is-valid');

                    if (!$('#epf_error').length) {
                        $('#epf_number').after('<div class="invalid-feedback" id="epf_error">Error connecting to server. Please try again.</div>');
                    } else {
                        $('#epf_error').text('Error connecting to server. Please try again.');
                    }
                }
            });
        });

        // Clear validation state when user starts typing again
        $('#epf_number').on('input', function() {
            $(this).removeClass('is-valid is-invalid');
            $('#epf_error').remove();
        });
    </script>
</body>
</html>