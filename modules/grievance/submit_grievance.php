<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check permission
requirePermission('grievance', 'submit_grievance');

$user = getCurrentUser();
$db = new Database();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        if (empty($_POST['category']) || empty($_POST['urgency']) ||
            empty($_POST['subject']) || empty($_POST['description'])) {
            throw new Exception('Please fill in all required fields');
        }

        // Generate case ID (check both tables for highest number)
        $year = date('Y');

        // Get highest case number from regular grievances
        $last_regular = $db->fetch("SELECT MAX(CAST(SUBSTRING(case_id, -6) AS UNSIGNED)) as last_num FROM grievances WHERE case_id LIKE 'GRV-$year-%'");
        $regular_num = $last_regular['last_num'] ?? 0;

        // Get highest case number from anonymous grievances
        $last_anonymous = $db->fetch("SELECT MAX(CAST(SUBSTRING(case_id, -6) AS UNSIGNED)) as last_num FROM anonymous_grievances WHERE case_id LIKE 'GRV-$year-%'");
        $anonymous_num = $last_anonymous['last_num'] ?? 0;

        // Use the higher of the two numbers
        $next_num = str_pad(max($regular_num, $anonymous_num) + 1, 6, '0', STR_PAD_LEFT);
        $case_id = "GRV-$year-$next_num";

        // Check if submission is anonymous
        $is_anonymous = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] == 'on';

        if ($is_anonymous) {
            // Anonymous grievance - insert into separate table
            $grievance_data = [
                'case_id' => $case_id,
                'submitter_location' => $user['location'] ?? '', // For context only
                'submitter_department' => $user['department'] ?? '', // For context only
                'category' => $_POST['category'] ?? '',
                'urgency' => $_POST['urgency'] ?? '',
                'subject' => $_POST['subject'] ?? '',
                'description' => $_POST['description'] ?? '',
                'incident_date' => !empty($_POST['incident_date']) ? $_POST['incident_date'] : null,
                'incident_location' => $_POST['incident_location'] ?? '',
                'witnesses' => $_POST['witnesses'] ?? ''
            ];

            // Insert into anonymous grievances table
            $grievance_id = $db->insert('anonymous_grievances', $grievance_data);
            $table_type = 'anonymous';
        } else {
            // Regular grievance - insert into regular table
            $grievance_data = [
                'case_id' => $case_id,
                'employee_epf' => $user['epf_number'],
                'employee_name' => $user['name'],
                'employee_location' => $user['location'] ?? '',
                'employee_department' => $user['department'] ?? '',
                'submission_date' => date('Y-m-d H:i:s'),
                'category' => $_POST['category'] ?? '',
                'urgency' => $_POST['urgency'] ?? '',
                'subject' => $_POST['subject'] ?? '',
                'description' => $_POST['description'] ?? '',
                'incident_date' => !empty($_POST['incident_date']) ? $_POST['incident_date'] : null,
                'incident_location' => $_POST['incident_location'] ?? '',
                'witnesses' => $_POST['witnesses'] ?? ''
            ];

            // Insert into regular grievances table
            $grievance_id = $db->insert('grievances', $grievance_data);
            $table_type = 'regular';
        }

        // Handle file uploads
        if (isset($_FILES['evidence']) && !empty($_FILES['evidence']['name'][0])) {
            $upload_dir = '../../uploads/grievances/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            for ($i = 0; $i < count($_FILES['evidence']['name']); $i++) {
                if ($_FILES['evidence']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['evidence']['name'][$i];
                    $file_tmp = $_FILES['evidence']['tmp_name'][$i];
                    $file_size = $_FILES['evidence']['size'][$i];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    // Validate file
                    if (in_array($file_ext, ALLOWED_FILE_TYPES) && $file_size <= MAX_FILE_SIZE) {
                        $new_filename = $case_id . '_' . $i . '_' . time() . '.' . $file_ext;
                        $file_path = $upload_dir . $new_filename;

                        if (move_uploaded_file($file_tmp, $file_path)) {
                            if ($is_anonymous) {
                                $evidence_data = [
                                    'anonymous_grievance_id' => $grievance_id,
                                    'file_name' => $file_name,
                                    'file_path' => $file_path,
                                    'file_type' => $file_ext,
                                    'file_size' => $file_size,
                                    'uploaded_by' => 'ANONYMOUS'
                                ];
                                $db->insert('anonymous_grievance_evidence', $evidence_data);
                            } else {
                                $evidence_data = [
                                    'grievance_id' => $grievance_id,
                                    'file_name' => $file_name,
                                    'file_path' => $file_path,
                                    'file_type' => $file_ext,
                                    'file_size' => $file_size
                                ];
                                $db->insert('grievance_evidence', $evidence_data);
                            }
                        }
                    }
                }
            }
        }

        // Handle initial note and workflow based on case type
        if ($is_anonymous) {
            // Anonymous cases: Add submission note to anonymous table
            $note_data = [
                'anonymous_grievance_id' => $grievance_id,
                'user_epf' => 'ANONYMOUS',
                'user_name' => 'Anonymous',
                'action_type' => 'Submission',
                'note' => 'Anonymous grievance submitted - awaiting superadmin review'
            ];
            $db->insert('anonymous_grievance_notes', $note_data);

            // Anonymous cases start with 'Opened' status, then auto-progress to 'Pending Team Assignment'
            $db->query("UPDATE anonymous_grievances SET status = 'Pending Team Assignment' WHERE id = ?", [$grievance_id]);

            // No supervisor assignment - goes directly to superadmin for team assignment
        } else {
            // Regular cases: Add submission note to regular table
            $note_data = [
                'grievance_id' => $grievance_id,
                'user_epf' => $user['epf_number'],
                'user_name' => $user['name'],
                'action_type' => 'Status Update',
                'note' => 'Grievance submitted by employee'
            ];
            $db->insert('grievance_notes', $note_data);

            // Regular cases: Normal supervisor workflow
            $supervisor = $db->fetch(
                "SELECT e.epf_number, e.name FROM employees e
                 LEFT JOIN locations l ON e.location_id = l.id
                 LEFT JOIN account_types at ON e.account_type_id = at.id
                 WHERE l.location_name = ? AND e.department = ? AND at.type_name = 'supervisor' AND e.is_active = 1
                 LIMIT 1",
                [$user['location'] ?? '', $user['department'] ?? '']
            );

            if ($supervisor) {
                // Update status to Under Supervisory Review
                $db->update('grievances', ['status' => 'Under Supervisory Review'], 'id = ?', [$grievance_id]);

                // Add note about auto-assignment
                $note_data = [
                    'grievance_id' => $grievance_id,
                    'user_epf' => 'SYSTEM',
                    'user_name' => 'System',
                    'action_type' => 'Status Update',
                    'note' => 'Automatically assigned to supervisor: ' . $supervisor['name'] . ' (' . $supervisor['epf_number'] . ')'
                ];
                $db->insert('grievance_notes', $note_data);
            }
        }

        $success_message = "Your grievance has been submitted successfully. Case ID: $case_id";


    } catch (Exception $e) {
        $error_message = "Error submitting grievance: " . $e->getMessage();
    }
}

// Get dropdown data
$categories = [
    'Professional (Career & Work)' => 'Professional (Career & Work)',
    'Financial (Money & Compensation)' => 'Financial (Money & Compensation)',
    'Behavioral (People & Conduct)' => 'Behavioral (People & Conduct)',
    'Environment (Physical Workspace)' => 'Environment (Physical Workspace)',
    'Policy (Rules & Procedures)' => 'Policy (Rules & Procedures)',
    'Safety (Health & Security)' => 'Safety (Health & Security)',
    'Discrimination (Unfair Treatment)' => 'Discrimination (Unfair Treatment)',
    'Harassment (Inappropriate Behavior)' => 'Harassment (Inappropriate Behavior)',
    'Communication (Information & Feedback)' => 'Communication (Information & Feedback)'
];

$urgency_levels = ['Low', 'Medium', 'High', 'Critical'];
$locations = LOCATIONS;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Submit Grievance</title>
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
                        <h1><i class="fas fa-edit me-3"></i>Submit Grievance</h1>
                        <p>Submit employee complaints and concerns for proper resolution</p>
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
                    <li class="breadcrumb-item active">Submit Grievance</li>
                </ol>
            </nav>

            <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Grievance Form -->
            <div class="form-card">
                <div class="card-header-modern">
                    <h5><i class="fas fa-file-alt me-2"></i>Grievance Submission Form</h5>
                </div>
                    <form method="POST" enctype="multipart/form-data" id="grievanceForm">
                        <div class="row">
                            <!-- Employee Information -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">EPF Number</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['epf_number']); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>" readonly>
                                </div>
                            </div>

                            <!-- Anonymity Option -->
                            <div class="col-12">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_anonymous" name="is_anonymous">
                                        <label class="form-check-label" for="is_anonymous">
                                            <strong>Submit as Anonymous</strong>
                                            <small class="d-block text-muted">Your identity will be hidden from all except Superadmin</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Category and Urgency -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Grievance Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="urgency" class="form-label">Urgency Level <span class="text-danger">*</span></label>
                                    <select class="form-select" id="urgency" name="urgency" required>
                                        <option value="">Select Urgency</option>
                                        <?php foreach ($urgency_levels as $level): ?>
                                        <option value="<?php echo $level; ?>"><?php echo $level; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Subject -->
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="subject" name="subject" maxlength="200" required>
                                    <div class="form-text">Maximum 200 characters</div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Detailed Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="5" minlength="50" maxlength="2000" required></textarea>
                                    <div class="form-text">
                                        <span id="charCount">0</span>/2000 characters (minimum 50 required)
                                    </div>
                                </div>
                            </div>

                            <!-- Incident Details -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="incident_date" class="form-label">Incident Date</label>
                                    <input type="date" class="form-control" id="incident_date" name="incident_date">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="incident_location" class="form-label">Incident Location</label>
                                    <select class="form-select" id="incident_location" name="incident_location">
                                        <option value="">Select Location</option>
                                        <?php foreach ($locations as $id => $location): ?>
                                        <option value="<?php echo $location; ?>"><?php echo $location; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Witnesses -->
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="witnesses" class="form-label">Witnesses (if any)</label>
                                    <textarea class="form-control" id="witnesses" name="witnesses" rows="3" placeholder="List any witnesses to the incident"></textarea>
                                </div>
                            </div>

                            <!-- Evidence Upload -->
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="evidence" class="form-label">Evidence Upload</label>
                                    <input type="file" class="form-control" id="evidence" name="evidence[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx">
                                    <div class="form-text">
                                        Maximum 5 files, 10MB each. Supported formats: PDF, JPG, JPEG, PNG, DOCX
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Submit Grievance
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
            </div>

            <!-- Category Help -->
            <div class="content-card">
                <div class="card-header-modern">
                    <h5><i class="fas fa-question-circle me-2"></i>Category Guidelines</h5>
                </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <h6>Work Related</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Professional:</strong> Training denial, unfair evaluations, workload issues</li>
                                        <li><strong>Financial:</strong> Missing overtime, salary discrepancies, benefit errors</li>
                                        <li><strong>Policy:</strong> Denied vacation, inconsistent application, unclear procedures</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h6>Behavioral</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Behavioral:</strong> Manager belittling, unprofessional conduct, favoritism</li>
                                        <li><strong>Harassment:</strong> Intimidation, sexual harassment, bullying</li>
                                        <li><strong>Discrimination:</strong> Age/gender/race bias, religious discrimination</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h6>Environment</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Environment:</strong> Broken AC, poor lighting, inadequate facilities</li>
                                        <li><strong>Safety:</strong> Unsafe conditions, missing equipment, health hazards</li>
                                        <li><strong>Communication:</strong> Lack of communication, missing information, no feedback</li>
                                    </ul>
                                </div>
                            </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character counter for description
        document.getElementById('description').addEventListener('input', function() {
            const charCount = this.value.length;
            document.getElementById('charCount').textContent = charCount;

            if (charCount < 50) {
                document.getElementById('charCount').style.color = 'red';
            } else {
                document.getElementById('charCount').style.color = 'green';
            }
        });

        // File upload validation
        document.getElementById('evidence').addEventListener('change', function() {
            const files = this.files;
            if (files.length > 5) {
                alert('Maximum 5 files allowed');
                this.value = '';
                return;
            }

            for (let i = 0; i < files.length; i++) {
                if (files[i].size > 10 * 1024 * 1024) {
                    alert('File "' + files[i].name + '" exceeds 10MB limit');
                    this.value = '';
                    return;
                }
            }
        });

        // Form validation
        document.getElementById('grievanceForm').addEventListener('submit', function(e) {
            const description = document.getElementById('description').value;
            if (description.length < 50) {
                e.preventDefault();
                alert('Description must be at least 50 characters long');
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