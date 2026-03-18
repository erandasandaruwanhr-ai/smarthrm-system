<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has access to exit interview
if (!isSuperAdmin() && !hasModulePermission($database, 'offboarding.exit_interview')) {
    header('Location: ../../dashboard.php');
    exit;
}

$success_message = '';
$error_message = '';

// Get approved resignation for current user
$approved_resignation = $database->fetchAll(
    "SELECT * FROM resignations WHERE employee_epf = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 1",
    [$user['epf_number']]
);

if (empty($approved_resignation)) {
    header('Location: index.php');
    exit();
}

$resignation = $approved_resignation[0];

// Check if exit interview already submitted
$existing_interview = $database->fetchAll(
    "SELECT * FROM exit_interviews WHERE resignation_id = ?",
    [$resignation['id']]
);

if (!empty($existing_interview)) {
    $success_message = "You have already completed your exit interview. Thank you for your feedback.";
}

// Get all employees for supportive person selection
$employees = $database->fetchAll(
    "SELECT epf_number, name, department, designation FROM employees WHERE is_active = 1 AND epf_number != ? ORDER BY name",
    [$user['epf_number']]
);

// Handle form submission
if ($_POST && empty($existing_interview)) {
    $what_liked = trim($_POST['what_liked']);
    $what_disliked = trim($_POST['what_disliked']);
    $what_would_change = trim($_POST['what_would_change']);
    $recommendations = trim($_POST['recommendations']);
    $supportive_person_epf = $_POST['supportive_person_epf'] ?? null;

    // Get supportive person details if selected
    $supportive_person_name = null;
    if ($supportive_person_epf) {
        $supportive_person = $database->fetchAll(
            "SELECT name FROM employees WHERE epf_number = ?",
            [$supportive_person_epf]
        );
        if (!empty($supportive_person)) {
            $supportive_person_name = $supportive_person[0]['name'];
        }
    }

    // Insert exit interview
    $interview_data = [
        'resignation_id' => $resignation['id'],
        'employee_epf' => $user['epf_number'],
        'employee_name' => $resignation['employee_name'],
        'employee_designation' => $resignation['employee_designation'],
        'employee_department' => $resignation['employee_department'],
        'employee_location' => $resignation['employee_location'],
        'date_of_exit' => $resignation['last_working_day'],
        'reason_for_exit' => $resignation['reason_for_resignation'],
        'what_liked' => $what_liked,
        'what_disliked' => $what_disliked,
        'what_would_change' => $what_would_change,
        'recommendations' => $recommendations,
        'supportive_person_epf' => $supportive_person_epf,
        'supportive_person_name' => $supportive_person_name
    ];

    try {
        $interview_id = $database->insert('exit_interviews', $interview_data);

        if ($interview_id) {
            $success_message = "Your exit interview has been submitted successfully. Thank you for your valuable feedback.";
            // Clear form
            $_POST = [];
        } else {
            $error_message = "Failed to submit exit interview. Please try again.";
        }
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Exit Interview</title>
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

        .question-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--info-color);
        }

        .question-title {
            color: var(--info-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .select2-container--default .select2-selection--single {
            height: calc(3.5rem + 2px);
            padding: 1rem 0.75rem;
            font-size: 1rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding-left: 0;
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
                        <h1><i class="fas fa-comments me-3"></i>Exit Interview</h1>
                        <p>Conduct exit interviews and collect feedback from departing employees</p>
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
                    <li class="breadcrumb-item active">Exit Interview</li>
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

            <?php if (empty($existing_interview) && empty($success_message)): ?>
            <div class="row">
                <div class="col-lg-10 mx-auto">
                    <div class="form-card">
                        <!-- Employee Information -->
                        <div class="employee-info">
                            <h6><i class="fas fa-user me-2"></i>Exit Information</h6>
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

                        <!-- Exit Interview Form -->
                        <form method="POST" action="">
                            <!-- What Liked -->
                            <div class="question-card">
                                <div class="question-title">
                                    <i class="fas fa-thumbs-up me-2"></i>What Did You Like About Working Here?
                                </div>
                                <textarea class="form-control" name="what_liked" rows="4"
                                          placeholder="Please share what you enjoyed most about your time with us..."><?php echo htmlspecialchars($_POST['what_liked'] ?? ''); ?></textarea>
                            </div>

                            <!-- What Disliked -->
                            <div class="question-card">
                                <div class="question-title">
                                    <i class="fas fa-thumbs-down me-2"></i>What Did You Dislike About Working Here?
                                </div>
                                <textarea class="form-control" name="what_disliked" rows="4"
                                          placeholder="Please share any challenges or concerns you experienced..."><?php echo htmlspecialchars($_POST['what_disliked'] ?? ''); ?></textarea>
                            </div>

                            <!-- What Would Change -->
                            <div class="question-card">
                                <div class="question-title">
                                    <i class="fas fa-edit me-2"></i>What Would You Change About Working Here?
                                </div>
                                <textarea class="form-control" name="what_would_change" rows="4"
                                          placeholder="If you could change something about the company or your role, what would it be?"><?php echo htmlspecialchars($_POST['what_would_change'] ?? ''); ?></textarea>
                            </div>

                            <!-- Recommendations -->
                            <div class="question-card">
                                <div class="question-title">
                                    <i class="fas fa-lightbulb me-2"></i>What Would You Recommend to Improve Working Here?
                                </div>
                                <textarea class="form-control" name="recommendations" rows="4"
                                          placeholder="Please provide any suggestions or recommendations for improvement..."><?php echo htmlspecialchars($_POST['recommendations'] ?? ''); ?></textarea>
                            </div>

                            <!-- Supportive Person -->
                            <div class="question-card">
                                <div class="question-title">
                                    <i class="fas fa-hands-helping me-2"></i>Most Supportive Person You Worked With
                                </div>
                                <select class="form-select" name="supportive_person_epf" id="supportive_person">
                                    <option value="">Select a colleague (optional)</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo htmlspecialchars($employee['epf_number']); ?>"
                                            <?php echo (($_POST['supportive_person_epf'] ?? '') === $employee['epf_number']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['name']); ?> -
                                        <?php echo htmlspecialchars($employee['designation']); ?>
                                        (<?php echo htmlspecialchars($employee['department']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Optional: Select a colleague who was particularly supportive during your time here.
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-3 mt-4">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Exit Interview
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('#supportive_person').select2({
                placeholder: 'Search for a colleague...',
                allowClear: true
            });
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>