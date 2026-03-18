<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

// Check if user is superadmin
$user = getCurrentUser();
if ($user['account_type'] !== 'superadmin') {
    header('Location: ../../dashboard.php');
    exit();
}

$database = new Database();
$success_message = '';
$error_message = '';

// Handle checklist item update via AJAX
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'update_checklist') {
    $onboarding_id = $_POST['onboarding_id'];
    $field_name = $_POST['field_name'];
    $field_value = $_POST['field_value'] == '1' ? 1 : 0;

    // Update the specific checklist item
    $update_query = "UPDATE onboarding_tracker SET $field_name = ? WHERE id = ?";
    $result = $database->execute($update_query, [$field_value, $onboarding_id]);

    if ($result) {
        // Recalculate completion percentage
        $completion_query = "SELECT
            safety_induction,
            code_of_conduct,
            training_evaluation,
            training_evaluation_na,
            performance_evaluation,
            agreement,
            non_compete_agreement,
            medical_insurance_letter,
            confirmation_letter
        FROM onboarding_tracker WHERE id = ?";

        $data = $database->fetchAll($completion_query, [$onboarding_id])[0];

        // Count completed items (8 total items)
        $completed = 0;

        // 1. Safety Induction
        if ($data['safety_induction']) $completed++;

        // 2. Code of Conduct
        if ($data['code_of_conduct']) $completed++;

        // 3. Training Evaluation (completed OR N/A)
        if ($data['training_evaluation'] || $data['training_evaluation_na']) $completed++;

        // 4. Performance/Probation Evaluation
        if ($data['performance_evaluation']) $completed++;

        // 5. Agreement
        if ($data['agreement']) $completed++;

        // 6. Non-Compete Agreement
        if ($data['non_compete_agreement']) $completed++;

        // 7. Medical Insurance Letter
        if ($data['medical_insurance_letter']) $completed++;

        // 8. Confirmation Letter
        if ($data['confirmation_letter']) $completed++;

        // Calculate percentage: completed/8 * 100
        $completion_percentage = round(($completed / 8) * 100);
        $is_completed = ($completed == 8) ? 1 : 0;

        // Update completion status
        $completion_update = "UPDATE onboarding_tracker SET
                             completion_percentage = ?,
                             is_completed = ?,
                             completion_date = ?
                             WHERE id = ?";

        $completion_date = $is_completed ? date('Y-m-d') : null;
        $database->execute($completion_update, [$completion_percentage, $is_completed, $completion_date, $onboarding_id]);

        echo json_encode(['success' => true, 'completion_percentage' => $completion_percentage]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update checklist item']);
    }
    exit();
}

// Handle N/A status updates
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'update_na_status') {
    $onboarding_id = $_POST['onboarding_id'];
    $field_name = $_POST['field_name'];
    $field_value = $_POST['field_value'] == '1' ? 1 : 0;

    // Update the N/A status
    $update_query = "UPDATE onboarding_tracker SET $field_name = ? WHERE id = ?";
    $result = $database->execute($update_query, [$field_value, $onboarding_id]);

    if ($result) {
        // Recalculate completion percentage after N/A status change
        $completion_query = "SELECT
            safety_induction,
            code_of_conduct,
            training_evaluation,
            training_evaluation_na,
            performance_evaluation,
            agreement,
            non_compete_agreement,
            medical_insurance_letter,
            confirmation_letter
        FROM onboarding_tracker WHERE id = ?";

        $data = $database->fetchAll($completion_query, [$onboarding_id])[0];

        // Count completed items (8 total items)
        $completed = 0;

        // 1. Safety Induction
        if ($data['safety_induction']) $completed++;

        // 2. Code of Conduct
        if ($data['code_of_conduct']) $completed++;

        // 3. Training Evaluation (completed OR N/A)
        if ($data['training_evaluation'] || $data['training_evaluation_na']) $completed++;

        // 4. Performance/Probation Evaluation
        if ($data['performance_evaluation']) $completed++;

        // 5. Agreement
        if ($data['agreement']) $completed++;

        // 6. Non-Compete Agreement
        if ($data['non_compete_agreement']) $completed++;

        // 7. Medical Insurance Letter
        if ($data['medical_insurance_letter']) $completed++;

        // 8. Confirmation Letter
        if ($data['confirmation_letter']) $completed++;

        // Calculate percentage: completed/8 * 100
        $completion_percentage = round(($completed / 8) * 100);
        $is_completed = ($completed == 8) ? 1 : 0;

        // Update completion status
        $completion_update = "UPDATE onboarding_tracker SET
                             completion_percentage = ?,
                             is_completed = ?,
                             completion_date = ?
                             WHERE id = ?";

        $completion_date = $is_completed ? date('Y-m-d') : null;
        $database->execute($completion_update, [$completion_percentage, $is_completed, $completion_date, $onboarding_id]);

        echo json_encode(['success' => true, 'completion_percentage' => $completion_percentage]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update N/A status']);
    }
    exit();
}


// Handle notes addition
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add_note') {
    $onboarding_id = $_POST['onboarding_id'];
    $note_text = trim($_POST['note_text']);

    if (!empty($note_text)) {
        $note_query = "INSERT INTO onboarding_notes (onboarding_id, note_text, created_by) VALUES (?, ?, ?)";
        $result = $database->execute($note_query, [$onboarding_id, $note_text, $user['epf_number']]);

        if ($result) {
            $success_message = "Note added successfully.";
        } else {
            $error_message = "Failed to add note.";
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search_filter = $_GET['search'] ?? '';

// Build query based on filters
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    if ($status_filter === 'completed') {
        $where_conditions[] = "is_completed = 1";
    } elseif ($status_filter === 'pending') {
        $where_conditions[] = "is_completed = 0";
    }
}

if (!empty($search_filter)) {
    $where_conditions[] = "(employee_name LIKE ? OR employee_epf LIKE ?)";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get onboarding records
$query = "SELECT * FROM onboarding_tracker $where_clause ORDER BY created_at DESC";
$onboarding_records = $database->fetchAll($query, $params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Onboarding List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
            z-index: 1;
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

        .data-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .progress-bar {
            border-radius: 10px;
        }

        .checklist-item {
            margin-bottom: 0.5rem;
        }

        .checklist-item input[type="checkbox"] {
            transform: scale(1.2);
            margin-right: 0.5rem;
        }

        .badge {
            font-size: 0.8rem;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
        }

        .btn-sm {
            border-radius: 6px;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            border-radius: 15px 15px 0 0;
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
        }

        .table th {
            background-color: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #2c3e50;
        }

        .table td {
            border: none;
            vertical-align: middle;
        }

        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
                        <h1><i class="fas fa-users me-3"></i>Onboarding List</h1>
                        <p>View and manage all employee onboarding records and progress tracking</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Onboarding Tracker</a></li>
                    <li class="breadcrumb-item active">Onboarding List</li>
                </ol>
            </nav>

            <!-- Alerts -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Status Filter</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search Employee</label>
                        <input type="text" name="search" class="form-control"
                               placeholder="Search by name or EPF..." value="<?php echo htmlspecialchars($search_filter); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                            <a href="onboarding_list.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-refresh me-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Onboarding Records -->
            <div class="data-card">
                <h4 class="mb-4"><i class="fas fa-users me-2"></i>Onboarding Records</h4>

                <div class="table-responsive">
                    <table class="table table-hover" id="onboardingTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Hire Type</th>
                                <th>Start Date</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($onboarding_records as $record): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($record['employee_name']); ?></strong><br>
                                            <small class="text-muted">EPF: <?php echo $record['employee_epf']; ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['employee_department']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($record['hire_type'] === 'new_hire'): ?>
                                            <span class="badge bg-primary">New Hire</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Casual to Cadre</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($record['onboarding_start_date'])); ?></td>
                                    <td>
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar <?php echo $record['completion_percentage'] == 100 ? 'bg-success' : 'bg-primary'; ?>"
                                                 style="width: <?php echo $record['completion_percentage']; ?>%">
                                                <?php echo $record['completion_percentage']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($record['is_completed']): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>Completed
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock me-1"></i>In Progress
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary"
                                                onclick="viewChecklist(<?php echo $record['id']; ?>)">
                                            <i class="fas fa-list me-1"></i>Checklist
                                        </button>
                                        <button class="btn btn-sm btn-info"
                                                onclick="viewNotes(<?php echo $record['id']; ?>)">
                                            <i class="fas fa-sticky-note me-1"></i>Notes
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Checklist Modal -->
    <div class="modal fade" id="checklistModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-list-check me-2"></i>Onboarding Checklist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="checklistContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Notes Modal -->
    <div class="modal fade" id="notesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-sticky-note me-2"></i>Onboarding Notes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="notesContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#onboardingTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[2, 'desc']], // Order by start date
                columnDefs: [
                    { orderable: false, targets: [5] } // Disable ordering on actions column
                ]
            });
        });

        function viewChecklist(onboardingId) {
            $.get('onboarding_checklist.php', { id: onboardingId }, function(data) {
                $('#checklistContent').html(data);
                $('#checklistModal').modal('show');
            });
        }

        function viewNotes(onboardingId) {
            $.get('onboarding_notes.php', { id: onboardingId }, function(data) {
                $('#notesContent').html(data);
                $('#notesModal').modal('show');
            });
        }

        function updateChecklist(onboardingId, fieldName, checkbox) {
            const isChecked = checkbox.checked ? 1 : 0;

            $.post('onboarding_list.php', {
                action: 'update_checklist',
                onboarding_id: onboardingId,
                field_name: fieldName,
                field_value: isChecked
            }, function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    // Update progress bar
                    const progressBar = $(`#progress-${onboardingId}`);
                    progressBar.css('width', data.completion_percentage + '%');
                    progressBar.text(data.completion_percentage + '%');

                    if (data.completion_percentage == 100) {
                        progressBar.removeClass('bg-primary').addClass('bg-success');
                    } else {
                        progressBar.removeClass('bg-success').addClass('bg-primary');
                    }

                    // Show success message
                    showAlert('success', 'Checklist item updated successfully!');
                } else {
                    // Revert checkbox
                    checkbox.checked = !checkbox.checked;
                    showAlert('danger', data.message || 'Failed to update checklist item');
                }
            });
        }

        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('.content-wrapper').prepend(alertHtml);

            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 3000);
        }
    </script>
</body>
</html>