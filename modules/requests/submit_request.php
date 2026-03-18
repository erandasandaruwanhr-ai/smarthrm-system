<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/notification_system.php';

$user = getCurrentUser();
$db = new Database();

// Check permission
if (!hasModulePermission($db, 'requests.submit_request')) {
    header('Location: ../../dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        if (empty($_POST['request_type']) || empty($_POST['subject']) || empty($_POST['details']) || empty($_POST['reason'])) {
            throw new Exception('Please fill in all required fields');
        }

        // Generate request ID
        $year = date('Y');
        $last_request = $db->fetch("SELECT MAX(CAST(SUBSTRING(request_id, -6) AS UNSIGNED)) as last_num FROM employee_requests WHERE request_id LIKE 'REQ-$year-%'");
        $next_num = str_pad(($last_request['last_num'] ?? 0) + 1, 6, '0', STR_PAD_LEFT);
        $request_id = "REQ-$year-$next_num";

        // Get supervisor information
        $supervisor = null;
        if ($user['reports_to']) {
            $supervisor = $db->fetch("SELECT epf_number, name FROM employees WHERE epf_number = ? AND is_active = 1", [$user['reports_to']]);
        }

        // If no direct supervisor, find location/department supervisor
        if (!$supervisor) {
            $supervisor = $db->fetch("
                SELECT e.epf_number, e.name
                FROM employees e
                LEFT JOIN locations l ON e.location_id = l.id
                LEFT JOIN account_types at ON e.account_type_id = at.id
                WHERE l.location_name = ? AND e.department = ? AND at.type_name = 'supervisor' AND e.is_active = 1
                LIMIT 1
            ", [$user['location'] ?? '', $user['department'] ?? '']);
        }

        // Prepare request data
        $request_data = [
            'request_id' => $request_id,
            'epf_number' => $user['epf_number'],
            'request_type' => $_POST['request_type'],
            'subject' => $_POST['subject'],
            'details' => $_POST['details'],
            'reason' => $_POST['reason'],
            'urgency' => $_POST['urgency'] ?? 'Normal',
            'status' => 'Request Submitted'
        ];

        // Handle start/end dates for service letter requests if needed
        if ($_POST['request_type'] === 'Service Letter') {
            $request_data['start_date'] = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $request_data['end_date'] = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        }

        // Insert request
        $request_db_id = $db->insert('employee_requests', $request_data);

        if ($request_db_id) {
            // Note: Request submitted successfully, workflow tracking can be added later

            // Note: File attachments functionality will be added when database tables are created

            // Send notifications
            try {
                $notifications = getNotificationSystem();

                // Notification to employee (confirmation)
                $notifications->sendNotification(
                    "Request Submitted - " . $_POST['request_type'] . " - " . $request_id,
                    "Your " . $_POST['request_type'] . " request has been successfully submitted with status 'Request Submitted'. You will receive updates as your request progresses through the workflow.",
                    "employee",
                    [
                        'target_users' => [$user['id']],
                        'action_needed' => false,
                        'priority' => $_POST['urgency'] === 'Urgent' ? 'urgent' : 'medium',
                        'module_link' => 'modules/requests/my_requests.php'
                    ]
                );

                // Notification to superadmin (action needed)
                $notifications->sendNotification(
                    "New Employee Request Submitted - " . $user['location'],
                    "A new " . $_POST['request_type'] . " request has been submitted by " . $user['name'] . " from " . $user['location'] . " (EPF: " . $user['epf_number'] . "). Request ID: " . $request_id . ". Priority: " . ($_POST['urgency'] ?? 'Normal') . ". Please handle this request in the workflow system.",
                    "employee",
                    [
                        'target_roles' => ['superadmin'],
                        'action_needed' => true,
                        'priority' => $_POST['urgency'] === 'Urgent' ? 'urgent' : 'high',
                        'module_link' => 'modules/requests/all_requests.php'
                    ]
                );

            } catch (Exception $e) {
                error_log("Failed to send request notification: " . $e->getMessage());
            }

            $success_message = "Your request has been submitted successfully. Request ID: $request_id";
        }

    } catch (Exception $e) {
        $error_message = "Error submitting request: " . $e->getMessage();
    }
}

// Get request types
$request_types = [
    'Salary Slip Originals' => 'Salary Slip Originals',
    'Bank Documents Fillup' => 'Bank Documents Fillup',
    'Service Letter' => 'Service Letter',
    'Other' => 'Other'
];

// Pre-fill type from URL if provided
$selected_type = $_GET['type'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Submit Request</title>
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
                        <h1><i class="fas fa-paper-plane me-3"></i>Submit Request</h1>
                        <p>Submit employee service requests and track approval workflow</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Employee Requests</a></li>
                    <li class="breadcrumb-item active">Submit Request</li>
                </ol>
            </nav>

            <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="form-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-file-alt me-2"></i>Employee Request Form</h5>
                        </div>
                            <form method="POST" enctype="multipart/form-data" id="requestForm">
                                <!-- Employee Information (Auto-filled) -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Employee EPF</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['epf_number']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Employee Name</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Location</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['location'] ?? 'Not Set'); ?>" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Department</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['department'] ?? 'Not Set'); ?>" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Employment Level</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['employment_level'] ?? 'Not Set'); ?>" readonly>
                                    </div>
                                </div>

                                <!-- Request Details -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="request_type" class="form-label">Request Type <span class="text-danger">*</span></label>
                                        <select class="form-select" name="request_type" id="request_type" required>
                                            <option value="">Select Request Type</option>
                                            <?php foreach ($request_types as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo ($selected_type === $value) ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="urgency" class="form-label">Urgency</label>
                                        <select class="form-select" name="urgency" id="urgency">
                                            <option value="Normal">Normal</option>
                                            <option value="High">High</option>
                                            <option value="Urgent">Urgent</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="subject" id="subject" maxlength="200" required
                                           placeholder="Brief summary of your request (max 200 characters)">
                                </div>

                                <div class="mb-3">
                                    <label for="details" class="form-label">Request Details <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="details" id="details" rows="4" required
                                              placeholder="Provide detailed description of your request (50-2000 characters)"
                                              minlength="50" maxlength="2000"></textarea>
                                    <div class="form-text">Character count: <span id="detailsCount">0</span>/2000</div>
                                </div>


                                <div class="mb-3">
                                    <label for="reason" class="form-label">Reason/Justification <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="reason" id="reason" rows="3" required
                                              placeholder="Explain why this request is needed (20-500 characters)"
                                              minlength="20" maxlength="500"></textarea>
                                    <div class="form-text">Character count: <span id="reasonCount">0</span>/500</div>
                                </div>

                                <!-- File attachments disabled until database tables are created
                                <div class="mb-3">
                                    <label for="attachments" class="form-label">Supporting Documents</label>
                                    <input type="file" class="form-control" name="attachments[]" id="attachments" multiple
                                           accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx">
                                    <div class="form-text">
                                        Maximum 5 files, 10MB each. Allowed types: PDF, JPG, PNG, DOCX, XLSX
                                    </div>
                                </div>
                                -->

                                <div class="mb-4">
                                    <label for="additional_comments" class="form-label">Additional Comments</label>
                                    <textarea class="form-control" name="additional_comments" id="additional_comments" rows="3"
                                              placeholder="Any additional information or comments (optional, max 1000 characters)"
                                              maxlength="1000"></textarea>
                                    <div class="form-text">Character count: <span id="commentsCount">0</span>/1000</div>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="button" class="btn btn-secondary me-md-2" onclick="window.location.href='index.php'">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Request
                                    </button>
                                </div>
                            </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character count for textareas
        function updateCharCount(textareaId, counterId, maxLength) {
            const textarea = document.getElementById(textareaId);
            const counter = document.getElementById(counterId);

            textarea.addEventListener('input', function() {
                const current = this.value.length;
                counter.textContent = current;

                if (current > maxLength * 0.9) {
                    counter.style.color = '#dc3545';
                } else if (current > maxLength * 0.75) {
                    counter.style.color = '#ffc107';
                } else {
                    counter.style.color = '#6c757d';
                }
            });
        }

        // Initialize character counters
        updateCharCount('details', 'detailsCount', 2000);
        updateCharCount('reason', 'reasonCount', 500);
        updateCharCount('additional_comments', 'commentsCount', 1000);

        // Show/hide training specific fields
        document.getElementById('request_type').addEventListener('change', function() {
            const trainingFields = document.getElementById('trainingFields');
            if (false) {
                // No special fields needed
            } else {
                trainingFields.style.display = 'none';
            }
        });

        // Trigger change event on page load for pre-selected values
        document.getElementById('request_type').dispatchEvent(new Event('change'));

        // File upload validation
        document.getElementById('attachments').addEventListener('change', function() {
            const files = this.files;
            const maxFiles = 5;
            const maxSize = 10 * 1024 * 1024; // 10MB

            if (files.length > maxFiles) {
                alert(`Maximum ${maxFiles} files allowed`);
                this.value = '';
                return;
            }

            for (let file of files) {
                if (file.size > maxSize) {
                    alert(`File "${file.name}" is too large. Maximum size is 10MB`);
                    this.value = '';
                    return;
                }
            }
        });

        // Form validation before submission
        document.getElementById('requestForm').addEventListener('submit', function(e) {
            const details = document.getElementById('details').value;
            const reason = document.getElementById('reason').value;

            if (details.length < 50) {
                alert('Request details must be at least 50 characters long');
                e.preventDefault();
                return;
            }

            if (reason.length < 20) {
                alert('Reason must be at least 20 characters long');
                e.preventDefault();
                return;
            }
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>