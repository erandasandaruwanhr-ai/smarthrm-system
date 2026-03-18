<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has access to resignation form
if (!isSuperAdmin() && !hasModulePermission($database, 'offboarding.resignation_form')) {
    header('Location: ../../dashboard.php');
    exit;
}

$success_message = '';
$error_message = '';

// Get complete employee data
$employee_data = getCurrentUserEmployee($database);
if ($employee_data) {
    // Merge employee data with user session data
    $user = array_merge($user, $employee_data);
}

// Check if user already has a pending resignation
$existing_resignation = $database->fetchAll(
    "SELECT * FROM resignations WHERE employee_epf = ? AND status IN ('pending', 'approved')",
    [$user['epf_number']]
);

if (!empty($existing_resignation)) {
    $error_message = "You already have a pending resignation. Please contact HR for assistance.";
}

// Handle form submission
if ($_POST && empty($existing_resignation)) {
    $date_of_resignation = $_POST['date_of_resignation'];
    $reason_for_resignation = trim($_POST['reason_for_resignation']);
    $last_working_day = $_POST['last_working_day'];

    // Handle PDF upload
    $resignation_pdf_file = null;
    $resignation_pdf_path = null;

    if (isset($_FILES['resignation_pdf']) && $_FILES['resignation_pdf']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/resignations/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file = $_FILES['resignation_pdf'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($fileExt !== 'pdf') {
            $error_message = "Only PDF files are allowed for resignation letters.";
        } elseif ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
            $error_message = "File size must be less than 10MB.";
        } else {
            $fileName = 'resignation_' . $user['epf_number'] . '_' . time() . '.pdf';
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $resignation_pdf_file = $fileName;
                $resignation_pdf_path = 'uploads/resignations/' . $fileName;
            } else {
                $error_message = "Failed to upload resignation PDF.";
            }
        }
    }

    // Validation
    if (empty($date_of_resignation) || empty($last_working_day)) {
        $error_message = "Please fill in all required fields.";
    } elseif (strtotime($date_of_resignation) < time()) {
        $error_message = "Resignation date cannot be in the past.";
    } elseif (strtotime($last_working_day) < strtotime($date_of_resignation)) {
        $error_message = "Last working day must be after resignation date.";
    } else {
        // Insert resignation using already loaded user data
        $resignation_data = [
            'employee_epf' => $user['epf_number'],
            'employee_name' => $user['name'] ?? 'Unknown',
            'employee_designation' => $user['designation'] ?? 'Unknown',
            'employee_department' => $user['department'] ?? 'Unknown',
            'employee_location' => $user['location_name'] ?? 'Unknown',
            'date_of_resignation' => $date_of_resignation,
            'reason_for_resignation' => $reason_for_resignation,
            'last_working_day' => $last_working_day,
            'status' => 'pending'
        ];

        // Add PDF columns only if files were uploaded
        if ($resignation_pdf_file !== null) {
            $resignation_data['resignation_pdf_file'] = $resignation_pdf_file;
            $resignation_data['resignation_pdf_path'] = $resignation_pdf_path;
        }

        try {
            $resignation_id = $database->insert('resignations', $resignation_data);

            if ($resignation_id) {
                $success_message = "Your resignation has been submitted successfully. Your manager will be notified for approval.";
                // Reset form
                $_POST = [];
            } else {
                $error_message = "Failed to submit resignation. Please try again.";
            }
        } catch (Exception $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Resignation Form</title>
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

        .employee-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .employee-info h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: #6c757d;
        }

        .info-value {
            font-weight: 600;
            color: #2c3e50;
        }

        .form-floating > .form-control:focus,
        .form-floating > .form-control:not(:placeholder-shown) {
            padding-top: 1.625rem;
            padding-bottom: 0.625rem;
        }

        .form-floating > label {
            opacity: 0.65;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 8px;
        }

        .btn-secondary {
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 8px;
        }

        .warning-card {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .warning-card .fa-exclamation-triangle {
            color: #856404;
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

            .form-card {
                padding: 1.5rem;
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
                        <h1><i class="fas fa-edit me-3"></i>Resignation Form</h1>
                        <p>Submit employee resignation request with notice period and documentation</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Employee Offboarding</a></li>
                    <li class="breadcrumb-item active">Resignation Form</li>
                </ol>
            </nav>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (empty($existing_resignation)): ?>
            <!-- Warning Card -->
            <div class="warning-card">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-1">Important Notice</h5>
                        <p class="mb-0">
                            Please ensure you have discussed your resignation with your immediate supervisor before submitting this form.
                            Once submitted, your resignation will be forwarded to your manager for approval.
                        </p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="form-card">
                        <!-- Employee Information -->
                        <div class="employee-info">
                            <h6><i class="fas fa-user me-2"></i>Employee Information</h6>
                            <div class="info-item">
                                <span class="info-label">Employee Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Employee ID:</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['epf_number'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Designation:</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['designation'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Department:</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Location:</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['location_name'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <!-- Resignation Form -->
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label for="resignation_pdf" class="form-label">
                                    <i class="fas fa-file-pdf me-2"></i>Upload Resignation Letter (PDF)
                                </label>
                                <input type="file" class="form-control" id="resignation_pdf"
                                       name="resignation_pdf" accept=".pdf">
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Upload your formal resignation letter in PDF format (Max: 10MB)
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="date_of_resignation"
                                               name="date_of_resignation"
                                               value="<?php echo $_POST['date_of_resignation'] ?? ''; ?>"
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                        <label for="date_of_resignation">Date of Resignation *</label>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="last_working_day"
                                               name="last_working_day"
                                               value="<?php echo $_POST['last_working_day'] ?? ''; ?>"
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                        <label for="last_working_day">Last Working Day *</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-floating">
                                    <textarea class="form-control" id="reason_for_resignation"
                                              name="reason_for_resignation"
                                              style="height: 120px;"
                                              placeholder="Please provide your reason for resignation..."><?php echo htmlspecialchars($_POST['reason_for_resignation'] ?? ''); ?></textarea>
                                    <label for="reason_for_resignation">Reason for Resignation</label>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Please provide a brief explanation for your resignation (optional).
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-3">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-file-signature me-2"></i>Submit Resignation
                                </button>
                            </div>
                        </form>
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

        // Auto-set minimum last working day when resignation date changes
        document.getElementById('date_of_resignation').addEventListener('change', function() {
            const resignationDate = new Date(this.value);
            const lastWorkingDayInput = document.getElementById('last_working_day');

            if (resignationDate) {
                // Set minimum date to resignation date
                lastWorkingDayInput.min = this.value;

                // If last working day is before resignation date, clear it
                if (lastWorkingDayInput.value && new Date(lastWorkingDayInput.value) < resignationDate) {
                    lastWorkingDayInput.value = '';
                }
            }
        });

        // File upload validation and preview
        document.getElementById('resignation_pdf').addEventListener('change', function() {
            const file = this.files[0];
            const maxSize = 10 * 1024 * 1024; // 10MB

            if (file) {
                // Check file type
                if (file.type !== 'application/pdf') {
                    alert('Please select a PDF file only.');
                    this.value = '';
                    return;
                }

                // Check file size
                if (file.size > maxSize) {
                    alert('File size must be less than 10MB.');
                    this.value = '';
                    return;
                }

                // Show file info
                const fileInfo = document.createElement('div');
                fileInfo.className = 'mt-2 text-success';
                fileInfo.innerHTML = `<i class="fas fa-check-circle me-2"></i>File selected: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;

                // Remove existing file info
                const existingInfo = this.parentNode.querySelector('.file-info');
                if (existingInfo) {
                    existingInfo.remove();
                }

                fileInfo.className += ' file-info';
                this.parentNode.appendChild(fileInfo);
            }
        });
    </script>
</body>
</html>