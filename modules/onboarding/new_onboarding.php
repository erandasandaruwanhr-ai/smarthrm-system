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

// Handle form submission
if ($_POST) {
    $employee_epf = trim($_POST['employee_epf']);
    $employee_name = trim($_POST['employee_name']);
    $employee_location = trim($_POST['employee_location']);
    $employee_department = trim($_POST['employee_department']);
    $employee_designation = trim($_POST['employee_designation']);
    $hire_type = $_POST['hire_type'];
    $onboarding_start_date = $_POST['onboarding_start_date'];

    // Validate required fields
    if (empty($employee_epf) || empty($employee_name) || empty($hire_type) || empty($onboarding_start_date)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Check if employee exists
        $employee_check = $database->fetchAll("SELECT * FROM employees WHERE epf_number = ?", [$employee_epf]);
        if (empty($employee_check)) {
            $error_message = "Employee with EPF number $employee_epf does not exist.";
        } else {
            // Check if onboarding already exists
            $existing_check = $database->fetchAll("SELECT * FROM onboarding_tracker WHERE employee_epf = ?", [$employee_epf]);
            if (!empty($existing_check)) {
                $error_message = "Onboarding process already exists for this employee.";
            } else {
                // Insert new onboarding record
                $insert_query = "INSERT INTO onboarding_tracker (
                    employee_epf, employee_name, employee_location, employee_department,
                    employee_designation, hire_type, onboarding_start_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";

                $result = $database->execute($insert_query, [
                    $employee_epf, $employee_name, $employee_location,
                    $employee_department, $employee_designation, $hire_type, $onboarding_start_date
                ]);

                if ($result) {
                    $success_message = "Onboarding process started successfully for $employee_name (EPF: $employee_epf).";
                    // Clear form data
                    $_POST = array();
                } else {
                    $error_message = "Failed to start onboarding process. Please try again.";
                }
            }
        }
    }
}

// Get all active employees for the dropdown
$employees = $database->fetchAll("
    SELECT e.epf_number, e.name, l.location_name as location, e.department, e.designation
    FROM employees e
    LEFT JOIN locations l ON e.location_id = l.id
    WHERE e.is_active = 1
    ORDER BY e.name
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Start New Onboarding</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 123, 255, 0.3);
        }

        .btn-secondary {
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .select2-container--default .select2-selection--single {
            height: 50px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-left: 1rem;
            padding-top: 0.75rem;
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
                        <h1><i class="fas fa-user-plus me-3"></i>New Onboarding</h1>
                        <p>Create new employee onboarding records and track integration process</p>
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
                    <li class="breadcrumb-item active">New Onboarding</li>
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

            <!-- Form Card -->
            <div class="form-card">
                <h3 class="mb-4"><i class="fas fa-user-plus me-2"></i>Employee Onboarding Information</h3>

                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="employee_select" class="form-label">Select Employee <span class="text-danger">*</span></label>
                                <select id="employee_select" class="form-select" required>
                                    <option value="">Choose an employee...</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['epf_number']; ?>"
                                                data-name="<?php echo htmlspecialchars($employee['name']); ?>"
                                                data-location="<?php echo htmlspecialchars($employee['location']); ?>"
                                                data-department="<?php echo htmlspecialchars($employee['department']); ?>"
                                                data-designation="<?php echo htmlspecialchars($employee['designation']); ?>">
                                            <?php echo $employee['name']; ?> (EPF: <?php echo $employee['epf_number']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="hire_type" class="form-label">Hire Type <span class="text-danger">*</span></label>
                                <select name="hire_type" id="hire_type" class="form-select" required>
                                    <option value="">Select hire type...</option>
                                    <option value="new_hire" <?php echo (isset($_POST['hire_type']) && $_POST['hire_type'] == 'new_hire') ? 'selected' : ''; ?>>New Hire</option>
                                    <option value="casual_cadre" <?php echo (isset($_POST['hire_type']) && $_POST['hire_type'] == 'casual_cadre') ? 'selected' : ''; ?>>Casual to Cadre</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden fields that will be populated by JavaScript -->
                    <input type="hidden" name="employee_epf" id="employee_epf" value="<?php echo $_POST['employee_epf'] ?? ''; ?>">
                    <input type="hidden" name="employee_name" id="employee_name" value="<?php echo $_POST['employee_name'] ?? ''; ?>">
                    <input type="hidden" name="employee_location" id="employee_location" value="<?php echo $_POST['employee_location'] ?? ''; ?>">
                    <input type="hidden" name="employee_department" id="employee_department" value="<?php echo $_POST['employee_department'] ?? ''; ?>">
                    <input type="hidden" name="employee_designation" id="employee_designation" value="<?php echo $_POST['employee_designation'] ?? ''; ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="onboarding_start_date" class="form-label">Onboarding Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="onboarding_start_date" id="onboarding_start_date"
                                       class="form-control" required
                                       value="<?php echo $_POST['onboarding_start_date'] ?? date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Display selected employee details -->
                    <div id="employee_details" class="mt-4" style="display: none;">
                        <h5><i class="fas fa-info-circle me-2"></i>Selected Employee Details</h5>
                        <div class="card bg-light border-0">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>EPF Number:</strong><br>
                                        <span id="display_epf"></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Name:</strong><br>
                                        <span id="display_name"></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Location:</strong><br>
                                        <span id="display_location"></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Department:</strong><br>
                                        <span id="display_department"></span>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <strong>Designation:</strong><br>
                                        <span id="display_designation"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Onboarding Checklist Preview -->
                    <div class="mt-4">
                        <h5><i class="fas fa-list-check me-2"></i>Onboarding Checklist Items</h5>
                        <div class="card bg-info bg-opacity-10 border-0">
                            <div class="card-body">
                                <p class="text-muted mb-3">The following checklist items will be tracked for this employee:</p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-shield-alt text-primary me-2"></i>Safety Induction</li>
                                            <li class="mb-2"><i class="fas fa-book text-info me-2"></i>Code of Conduct</li>
                                            <li class="mb-2"><i class="fas fa-graduation-cap text-success me-2"></i>Training Evaluation <small class="text-muted">(Flexible: N/A or Track)</small></li>
                                            <li class="mb-2"><i class="fas fa-star text-warning me-2"></i>Probation Evaluation</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-handshake text-primary me-2"></i>Agreement</li>
                                            <li class="mb-2"><i class="fas fa-ban text-danger me-2"></i>Non-Compete Agreement</li>
                                            <li class="mb-2"><i class="fas fa-heartbeat text-success me-2"></i>Medical Insurance Letter</li>
                                            <li class="mb-2"><i class="fas fa-certificate text-info me-2"></i>Confirmation Letter</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-4 d-flex justify-content-end gap-3">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play me-2"></i>Start Onboarding Process
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('#employee_select').select2({
                placeholder: "Search for an employee...",
                allowClear: true
            });

            // Handle employee selection
            $('#employee_select').change(function() {
                const selectedOption = $(this).find('option:selected');

                if (selectedOption.val()) {
                    // Populate hidden fields
                    $('#employee_epf').val(selectedOption.val());
                    $('#employee_name').val(selectedOption.data('name'));
                    $('#employee_location').val(selectedOption.data('location'));
                    $('#employee_department').val(selectedOption.data('department'));
                    $('#employee_designation').val(selectedOption.data('designation'));

                    // Show employee details
                    $('#display_epf').text(selectedOption.val());
                    $('#display_name').text(selectedOption.data('name'));
                    $('#display_location').text(selectedOption.data('location') || 'Not specified');
                    $('#display_department').text(selectedOption.data('department') || 'Not specified');
                    $('#display_designation').text(selectedOption.data('designation') || 'Not specified');

                    $('#employee_details').show();
                } else {
                    // Clear hidden fields
                    $('#employee_epf, #employee_name, #employee_location, #employee_department, #employee_designation').val('');
                    $('#employee_details').hide();
                }
            });
        });
    </script>
</body>
</html>