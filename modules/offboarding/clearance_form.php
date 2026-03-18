<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($database, 'offboarding.clearance_form')) {
    header('Location: ../../dashboard.php');
    exit;
}
$success_message = '';
$error_message = '';

// Get approved resignation with completed exit interview for current user
$resignation_query = "
    SELECT r.*, ei.id as exit_interview_id
    FROM resignations r
    LEFT JOIN exit_interviews ei ON r.id = ei.resignation_id
    WHERE r.employee_epf = ? AND r.status = 'approved' AND ei.id IS NOT NULL
    ORDER BY r.created_at DESC LIMIT 1
";
$approved_resignation = $database->fetchAll($resignation_query, [$user['epf_number']]);

if (empty($approved_resignation)) {
    $error_message = "You must complete your exit interview before accessing the clearance form.";
}

$resignation = !empty($approved_resignation) ? $approved_resignation[0] : null;

// Check if clearance form already submitted
$existing_clearance = [];
if ($resignation) {
    $existing_clearance = $database->fetchAll(
        "SELECT * FROM clearance_forms WHERE resignation_id = ?",
        [$resignation['id']]
    );
}

// Handle file upload
function handleFileUpload($fileKey, $uploadDir) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error'];
    }

    $file = $_FILES[$fileKey];
    $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'];
    $maxSize = 10 * 1024 * 1024; // 10MB

    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only PDF, JPG, PNG files allowed'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File too large. Maximum 10MB allowed'];
    }

    $fileName = 'clearance_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
    $filePath = $uploadDir . $fileName;

    if (!is_dir(dirname($filePath))) {
        mkdir(dirname($filePath), 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => true, 'filename' => $fileName, 'path' => $filePath];
    } else {
        return ['success' => false, 'message' => 'Failed to save uploaded file'];
    }
}

// Handle form submission
if ($_POST && $resignation) {
    $it_assets_cleared = trim($_POST['it_assets_cleared']);
    $finance_clearance_cleared = trim($_POST['finance_clearance_cleared']);
    $hr_clearance_cleared = trim($_POST['hr_clearance_cleared']);
    $stores_cleared = trim($_POST['stores_cleared']);
    $location_clearance_cleared = trim($_POST['location_clearance_cleared']);

    $clearance_pdf_file = null;
    $clearance_pdf_path = null;

    // Handle file upload if provided
    if (isset($_FILES['clearance_evidence']) && $_FILES['clearance_evidence']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/clearance/';
        $uploadResult = handleFileUpload('clearance_evidence', $uploadDir);

        if ($uploadResult['success']) {
            $clearance_pdf_file = $uploadResult['filename'];
            $clearance_pdf_path = 'uploads/clearance/' . $uploadResult['filename']; // Web-accessible path

            // Show success message with file info
            $success_message = "File uploaded successfully: " . $clearance_pdf_file;
        } else {
            $error_message = $uploadResult['message'];
        }
    } else {
    }

    if (empty($error_message)) {
        $clearance_data = [
            'it_assets_cleared' => $it_assets_cleared,
            'finance_clearance_cleared' => $finance_clearance_cleared,
            'hr_clearance_cleared' => $hr_clearance_cleared,
            'stores_cleared' => $stores_cleared,
            'location_clearance_cleared' => $location_clearance_cleared,
            'status' => 'pending'
        ];

        // Add PDF columns only if files were uploaded
        if ($clearance_pdf_file !== null) {
            $clearance_data['clearance_pdf_file'] = $clearance_pdf_file;
            $clearance_data['clearance_pdf_path'] = $clearance_pdf_path;
        }

        try {
            error_log("About to save clearance data: " . print_r($clearance_data, true));
            error_log("PDF file value: " . ($clearance_pdf_file ?? 'NULL'));
            error_log("PDF path value: " . ($clearance_pdf_path ?? 'NULL'));

            if (!empty($existing_clearance)) {
                // Update existing clearance form
                $clearance_id = $database->update(
                    'clearance_forms',
                    $clearance_data,
                    'resignation_id = ?',
                    [$resignation['id']]
                );

                if ($clearance_id) {
                    $success_message = "Your clearance form has been updated successfully.";
                } else {
                    $error_message = "Failed to update clearance form. Please try again.";
                }
            } else {
                // Insert new clearance form
                $clearance_data = array_merge($clearance_data, [
                    'resignation_id' => $resignation['id'],
                    'employee_epf' => $user['epf_number'],
                    'employee_name' => $resignation['employee_name'],
                    'employee_designation' => $resignation['employee_designation'],
                    'employee_department' => $resignation['employee_department'],
                    'employee_location' => $resignation['employee_location'],
                    'date_of_exit' => $resignation['last_working_day'],
                    'reason_for_exit' => $resignation['reason_for_resignation']
                ]);

                $clearance_id = $database->insert('clearance_forms', $clearance_data);

                if ($clearance_id) {
                    $success_message = "Your clearance form has been submitted successfully. It will be reviewed by the superadmin for final verification.";
                } else {
                    $error_message = "Failed to submit clearance form. Please try again.";
                }
            }

            // Clear form on success
            if ($clearance_id) {
                $_POST = [];
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
    <title>SmartHRM - Clearance Form</title>
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

        .clearance-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--success-color);
        }

        .clearance-title {
            color: var(--success-color);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .clearance-title i {
            margin-right: 0.5rem;
        }

        .form-floating > .form-control:focus,
        .form-floating > .form-control:not(:placeholder-shown) {
            padding-top: 1.625rem;
            padding-bottom: 0.625rem;
        }

        .form-floating > label {
            opacity: 0.65;
        }

        .file-upload-area {
            border: 2px dashed #ced4da;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .file-upload-area:hover {
            border-color: var(--primary-color);
            background: #e7f3ff;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
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

        .required-asterisk {
            color: #dc3545;
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
                        <h1><i class="fas fa-clipboard-check me-3"></i>Clearance Form</h1>
                        <p>Complete department clearance checklist for departing employees</p>
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
                    <li class="breadcrumb-item active">Clearance Form</li>
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

            <?php if ($resignation && empty($existing_clearance) && empty($success_message)): ?>
            <div class="row">
                <div class="col-lg-10 mx-auto">
                    <div class="form-card">
                        <!-- Employee Information -->
                        <div class="employee-info">
                            <h6><i class="fas fa-user me-2"></i>Employee Information</h6>
                            <div class="info-item">
                                <span class="info-label">Employee Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($resignation['employee_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Employee ID:</span>
                                <span class="info-value"><?php echo htmlspecialchars($resignation['employee_epf']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Designation:</span>
                                <span class="info-value"><?php echo htmlspecialchars($resignation['employee_designation']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Department:</span>
                                <span class="info-value"><?php echo htmlspecialchars($resignation['employee_department']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Location:</span>
                                <span class="info-value"><?php echo htmlspecialchars($resignation['employee_location']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Last Working Day:</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($resignation['last_working_day'])); ?></span>
                            </div>
                        </div>

                        <!-- Clearance Form -->
                        <form method="POST" action="" enctype="multipart/form-data">
                            <!-- IT Assets Clearance -->
                            <div class="clearance-section">
                                <div class="clearance-title">
                                    <i class="fas fa-laptop"></i>
                                    IT Assets Clearance <span class="required-asterisk">*</span>
                                </div>
                                <textarea class="form-control" name="it_assets_cleared" rows="3" required
                                          placeholder="List all IT assets returned (laptops, monitors, keyboards, etc.) or mark as N/A"><?php echo htmlspecialchars($_POST['it_assets_cleared'] ?? ''); ?></textarea>
                            </div>

                            <!-- Finance Clearance -->
                            <div class="clearance-section">
                                <div class="clearance-title">
                                    <i class="fas fa-dollar-sign"></i>
                                    Finance Clearance <span class="required-asterisk">*</span>
                                </div>
                                <textarea class="form-control" name="finance_clearance_cleared" rows="3" required
                                          placeholder="Describe any outstanding financial settlements, loans, or advance payments cleared"><?php echo htmlspecialchars($_POST['finance_clearance_cleared'] ?? ''); ?></textarea>
                            </div>

                            <!-- HR Clearance -->
                            <div class="clearance-section">
                                <div class="clearance-title">
                                    <i class="fas fa-users"></i>
                                    HR Clearance <span class="required-asterisk">*</span>
                                </div>
                                <textarea class="form-control" name="hr_clearance_cleared" rows="3" required
                                          placeholder="Describe HR documentation returned, ID cards, access cards, etc."><?php echo htmlspecialchars($_POST['hr_clearance_cleared'] ?? ''); ?></textarea>
                            </div>

                            <!-- Stores Clearance -->
                            <div class="clearance-section">
                                <div class="clearance-title">
                                    <i class="fas fa-warehouse"></i>
                                    Stores Clearance <span class="required-asterisk">*</span>
                                </div>
                                <textarea class="form-control" name="stores_cleared" rows="3" required
                                          placeholder="List any materials, tools, or inventory items returned to stores"><?php echo htmlspecialchars($_POST['stores_cleared'] ?? ''); ?></textarea>
                            </div>

                            <!-- Location Clearance -->
                            <div class="clearance-section">
                                <div class="clearance-title">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Location Clearance <span class="required-asterisk">*</span>
                                </div>
                                <textarea class="form-control" name="location_clearance_cleared" rows="3" required
                                          placeholder="Describe any location-specific clearances (keys, locker cleanup, etc.)"><?php echo htmlspecialchars($_POST['location_clearance_cleared'] ?? ''); ?></textarea>
                            </div>

                            <!-- Evidence Upload -->
                            <div class="clearance-section">
                                <div class="clearance-title">
                                    <i class="fas fa-paperclip"></i>
                                    Clearance Evidence (Optional)
                                </div>
                                <div class="file-upload-area">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                    <p class="mb-2">Upload supporting documents (PDF, JPG, PNG)</p>
                                    <input type="file" class="form-control" name="clearance_evidence"
                                           accept=".pdf,.jpg,.jpeg,.png" id="clearance_evidence">
                                    <small class="text-muted">Maximum file size: 10MB</small>
                                </div>
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Optional: Upload receipts, acknowledgment forms, or photos as evidence of clearances.
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-3 mt-4">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Clearance Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php elseif (!empty($existing_clearance)): ?>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="alert alert-success text-center">
                        <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                        <h4>Clearance Form Already Submitted</h4>
                        <p class="mb-3">Your clearance form has been submitted successfully and is currently under review by the superadmin for final verification.</p>

                        <div class="row text-start">
                            <div class="col-md-6">
                                <strong>Submitted:</strong> <?php echo date('M d, Y', strtotime($existing_clearance[0]['submitted_at'])); ?><br>
                                <strong>Status:</strong>
                                <?php
                                $statusClass = match($existing_clearance[0]['status']) {
                                    'pending' => 'text-warning',
                                    'approved' => 'text-success',
                                    'rejected' => 'text-danger',
                                    default => 'text-secondary'
                                };
                                echo '<span class="' . $statusClass . '">' . ucfirst($existing_clearance[0]['status']) . '</span>';
                                ?>
                            </div>
                            <div class="col-md-6">
                                <strong>PDF Evidence:</strong>
                                <?php if (!empty($existing_clearance[0]['clearance_pdf_file'])): ?>
                                    <a href="../../<?php echo htmlspecialchars($existing_clearance[0]['clearance_pdf_path']); ?>"
                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-file-pdf me-1"></i>View PDF
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">No file uploaded</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mt-3">
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload preview with improved functionality
        document.getElementById('clearance_evidence')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const fileSize = file.size / 1024 / 1024; // Convert to MB
                if (fileSize > 10) {
                    alert('File size exceeds 10MB limit. Please select a smaller file.');
                    e.target.value = '';
                    return;
                }

                // Show file selection feedback without breaking the input
                const fileName = file.name;
                const uploadText = document.querySelector('.file-upload-area p');
                if (uploadText) {
                    uploadText.innerHTML = `<strong>Selected:</strong> ${fileName}`;
                    uploadText.style.color = '#28a745';
                }
            }
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>