<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Get system date format
$date_format_setting = $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'date_format'");
$date_format = $date_format_setting['setting_value'] ?? 'Y-m-d';

// Function to format date for display
function formatDateForDisplay($date, $format) {
    if (!$date) return '';
    try {
        $datetime = new DateTime($date);
        return $datetime->format($format);
    } catch (Exception $e) {
        return $date; // Return original if conversion fails
    }
}

// Check if user has permission to access employee form
if (!hasModulePermission($db, 'employee.employee_form') && !hasAdminAccess()) {
    header('Location: ../../dashboard.php?error=access_denied');
    exit();
}

$message = '';
$error = '';
$employee_id = $_GET['id'] ?? null;
$employee = null;

// Load employee data for editing
if ($employee_id) {
    $employee = $db->fetch("SELECT * FROM employees WHERE id = ?", [$employee_id]);
    if (!$employee) {
        header('Location: employee_list.php?error=employee_not_found');
        exit();
    }
}

// Handle form submission
if ($_POST) {
    try {
        // Validate required fields
        $required_fields = ['epf_number', 'name', 'designation', 'department', 'nic', 'birthday', 'joined_date', 'gender', 'employment_level_id', 'location_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field " . ucfirst(str_replace('_', ' ', $field)) . " is required.");
            }
        }

        // Use converted database format dates if available, otherwise use original
        $birthday_db = !empty($_POST['birthday_db']) ? $_POST['birthday_db'] : $_POST['birthday'];
        $joined_date_db = !empty($_POST['joined_date_db']) ? $_POST['joined_date_db'] : $_POST['joined_date'];

        // Calculate age from birthday
        $birthday = new DateTime($birthday_db);
        $today = new DateTime();
        $age = $today->diff($birthday)->y;

        // Calculate service years from joined date
        $joined_date = new DateTime($joined_date_db);
        $service_years = $today->diff($joined_date)->y;
        $service_months = $today->diff($joined_date)->m;
        $service = $service_years . ' years ' . $service_months . ' months';

        $data = [
            'epf_number' => $_POST['epf_number'],
            'name' => $_POST['name'],
            'designation' => $_POST['designation'],
            'department' => $_POST['department'],
            'nic' => $_POST['nic'],
            'birthday' => $birthday_db,
            'age' => $age,
            'joined_date' => $joined_date_db,
            'service' => $service,
            'gender' => $_POST['gender'],
            'employment_level_id' => $_POST['employment_level_id'],
            'location_id' => $_POST['location_id'],
            'reports_to' => !empty($_POST['reports_to']) ? $_POST['reports_to'] : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        if ($employee_id) {
            // Update existing employee
            $data['updated_at'] = date('Y-m-d H:i:s');
            $db->update('employees', $data, 'id = :where_id', ['where_id' => $employee_id]);
            $message = 'Employee updated successfully!';

        } else {
            // Check if EPF number already exists
            $existing = $db->fetch("SELECT id FROM employees WHERE epf_number = ?", [$_POST['epf_number']]);
            if ($existing) {
                throw new Exception("EPF Number already exists. Please use a different EPF Number.");
            }

            // Add new employee
            $data['created_at'] = date('Y-m-d H:i:s');
            $db->insert('employees', $data);
            $message = 'Employee added successfully!';

        }

        // Reload employee data
        if ($employee_id) {
            $employee = $db->fetch("SELECT * FROM employees WHERE id = ?", [$employee_id]);
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get dropdown data
$locations = $db->fetchAll("SELECT * FROM locations WHERE is_active = 1 ORDER BY location_name");
$employment_levels = $db->fetchAll("SELECT * FROM employment_levels WHERE is_active = 1 ORDER BY level_order");

// Get departments from dropdown management system
function getDepartmentOptions($db) {
    // Get custom departments from dropdown_items table
    $custom_departments = $db->fetchAll("SELECT item_name FROM dropdown_items WHERE category = 'departments' AND is_active = 1 ORDER BY item_name ASC");
    $custom_list = array_column($custom_departments, 'item_name');

    // Get predefined departments
    $predefined_departments = [
        'Finance',
        'HR',
        'IT',
        'Maintenance',
        'Material Processing',
        'Production',
        'QHS',
        'Supply chain & Logistics'
    ];

    // Combine and deduplicate
    $all_departments = array_unique(array_merge($predefined_departments, $custom_list));
    sort($all_departments);

    return $all_departments;
}

$departments = getDepartmentOptions($db);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Employee Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
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
                        <h1><i class="fas fa-user-edit me-3"></i><?php echo $employee_id ? 'Edit Employee' : 'Add Employee'; ?></h1>
                        <p><?php echo $employee_id ? 'Update employee information and manage details' : 'Create new employee profile and assign role details'; ?></p>
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
                    <li class="breadcrumb-item"><a href="index.php">Employee Management</a></li>
                    <li class="breadcrumb-item active"><?php echo $employee_id ? 'Edit Employee' : 'Add Employee'; ?></li>
                </ol>
            </nav>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <div class="card-header-modern">
                    <h5>
                        <i class="fas fa-user-plus me-2"></i>
                        <?php echo $employee_id ? 'Edit Employee Information' : 'Add New Employee'; ?>
                    </h5>
                </div>
                    <form method="POST" id="employeeForm">
                        <div class="row">
                            <!-- 2.1.1 EMP No -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-id-badge me-1"></i>EPF Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="epf_number"
                                       value="<?php echo htmlspecialchars($employee['epf_number'] ?? ''); ?>"
                                       required <?php echo $employee_id ? 'readonly' : ''; ?>>
                                <small class="text-muted">Unique employee identification number</small>
                            </div>

                            <!-- 2.1.2 Name -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-user me-1"></i>Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="name"
                                       value="<?php echo htmlspecialchars($employee['name'] ?? ''); ?>" required>
                            </div>

                            <!-- 2.1.3 Designation -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-briefcase me-1"></i>Designation <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="designation"
                                       value="<?php echo htmlspecialchars($employee['designation'] ?? ''); ?>" required>
                            </div>

                            <!-- 2.1.4 Department -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-building me-1"></i>Department <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" name="department" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>"
                                                <?php echo (isset($employee['department']) && $employee['department'] === $dept) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Select from predefined departments or manage in Admin → Dropdown Management</small>
                            </div>

                            <!-- 2.1.5 NIC -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-id-card me-1"></i>NIC Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="nic"
                                       value="<?php echo htmlspecialchars($employee['nic'] ?? ''); ?>" required>
                                <small class="text-muted">National Identity Card Number</small>
                            </div>

                            <!-- 2.1.6 Birthday -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-birthday-cake me-1"></i>Birthday <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control date-picker" name="birthday"
                                       value="<?php echo formatDateForDisplay($employee['birthday'] ?? '', $date_format); ?>"
                                       required id="birthday"
                                       placeholder="<?php echo $date_format == 'Y-m-d' ? 'YYYY-MM-DD' : ($date_format == 'd/m/Y' ? 'DD/MM/YYYY' : 'MM/DD/YYYY'); ?>"
                                       readonly>
                                <small class="text-muted">Format: <?php
                                    echo $date_format == 'Y-m-d' ? 'YYYY-MM-DD (e.g., 1990-01-15)' :
                                        ($date_format == 'd/m/Y' ? 'DD/MM/YYYY (e.g., 15/01/1990)' :
                                         'MM/DD/YYYY (e.g., 01/15/1990)');
                                ?></small>
                            </div>

                            <!-- 2.1.7 Age (auto calculated) -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-calendar me-1"></i>Age
                                </label>
                                <input type="text" class="form-control" id="age" readonly
                                       value="<?php echo $employee['age'] ?? ''; ?>">
                                <small class="text-muted">Automatically calculated from birthday</small>
                            </div>

                            <!-- 2.1.8 Joined Date -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-calendar-plus me-1"></i>Joined Date <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control date-picker" name="joined_date"
                                       value="<?php echo formatDateForDisplay($employee['joined_date'] ?? '', $date_format); ?>"
                                       required id="joinedDate"
                                       placeholder="<?php echo $date_format == 'Y-m-d' ? 'YYYY-MM-DD' : ($date_format == 'd/m/Y' ? 'DD/MM/YYYY' : 'MM/DD/YYYY'); ?>"
                                       readonly>
                                <small class="text-muted">Format: <?php
                                    echo $date_format == 'Y-m-d' ? 'YYYY-MM-DD (e.g., 2020-03-01)' :
                                        ($date_format == 'd/m/Y' ? 'DD/MM/YYYY (e.g., 01/03/2020)' :
                                         'MM/DD/YYYY (e.g., 03/01/2020)');
                                ?></small>
                            </div>

                            <!-- 2.1.9 Service (auto calculated) -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-clock me-1"></i>Service Period
                                </label>
                                <input type="text" class="form-control" id="service" readonly
                                       value="<?php echo $employee['service'] ?? ''; ?>">
                                <small class="text-muted">Automatically calculated from joined date</small>
                            </div>

                            <!-- 2.1.10 Gender -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-venus-mars me-1"></i>Gender <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo ($employee['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($employee['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>

                            <!-- 2.1.11 Employment Level -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-layer-group me-1"></i>Employment Level <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" name="employment_level_id" required>
                                    <option value="">Select Employment Level</option>
                                    <?php foreach ($employment_levels as $level): ?>
                                        <option value="<?php echo $level['id']; ?>"
                                                <?php echo ($employee['employment_level_id'] ?? '') == $level['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($level['level_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- 2.1.12 Location -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-map-marker-alt me-1"></i>Location <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" name="location_id" required>
                                    <option value="">Select Location</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo $location['id']; ?>"
                                                <?php echo ($employee['location_id'] ?? '') == $location['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($location['location_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- 2.1.13 Reports To -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-sitemap me-1"></i>Reports To
                                </label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="reports_to" id="reportsTo"
                                           value="<?php echo htmlspecialchars($employee['reports_to'] ?? ''); ?>"
                                           placeholder="Search by EPF number">
                                    <button type="button" class="btn btn-outline-secondary" onclick="searchReportsTo()">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <div id="reportsToResult" class="mt-1"></div>
                                <small class="text-muted">Leave empty if no reporting manager</small>
                            </div>
                        </div>

                        <!-- Employee Status -->
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" name="is_active" id="isActive"
                                           <?php echo (isset($employee['is_active']) ? $employee['is_active'] : 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isActive">
                                        <i class="fas fa-user-check me-1"></i>Active Employee
                                    </label>
                                    <small class="form-text text-muted d-block">
                                        Uncheck to make employee inactive (preserves all data for reactivation)
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-save me-2"></i><?php echo $employee_id ? 'Update Employee' : 'Save Employee'; ?>
                                </button>
                                <a href="employee_list.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <?php if ($employee_id): ?>
                                    <button type="button" class="btn btn-danger ms-2" onclick="deleteEmployee()">
                                        <i class="fas fa-trash me-2"></i>Delete Employee
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
            </div>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Get system date format from PHP
        const systemDateFormat = '<?php echo $date_format; ?>';

        // Convert PHP date format to Flatpickr format
        const getDatePickerFormat = () => {
            switch(systemDateFormat) {
                case 'Y-m-d': return 'Y-m-d';
                case 'd/m/Y': return 'd/m/Y';
                case 'm/d/Y': return 'm/d/Y';
                case 'd-M-Y': return 'd-M-Y';
                default: return 'Y-m-d';
            }
        };

        // Initialize date pickers
        flatpickr('.date-picker', {
            dateFormat: getDatePickerFormat(),
            allowInput: true,
            onChange: function(selectedDates, dateStr, instance) {
                if (instance.element.id === 'birthday') {
                    calculateAge();
                } else if (instance.element.id === 'joinedDate') {
                    calculateService();
                }
            }
        });

        // Function to parse date based on system format
        function parseDate(dateStr) {
            if (!dateStr) return null;

            let year, month, day;

            switch(systemDateFormat) {
                case 'Y-m-d':
                    const ymdParts = dateStr.split('-');
                    year = parseInt(ymdParts[0]);
                    month = parseInt(ymdParts[1]) - 1; // JavaScript months are 0-indexed
                    day = parseInt(ymdParts[2]);
                    break;
                case 'd/m/Y':
                    const dmyParts = dateStr.split('/');
                    day = parseInt(dmyParts[0]);
                    month = parseInt(dmyParts[1]) - 1;
                    year = parseInt(dmyParts[2]);
                    break;
                case 'm/d/Y':
                    const mdyParts = dateStr.split('/');
                    month = parseInt(mdyParts[0]) - 1;
                    day = parseInt(mdyParts[1]);
                    year = parseInt(mdyParts[2]);
                    break;
                default:
                    return new Date(dateStr);
            }

            return new Date(year, month, day);
        }

        // Function to calculate age
        function calculateAge() {
            const birthdayInput = document.getElementById('birthday');
            if (!birthdayInput.value) return;

            const birthday = parseDate(birthdayInput.value);
            if (!birthday) return;

            const today = new Date();
            let age = today.getFullYear() - birthday.getFullYear();
            const monthDiff = today.getMonth() - birthday.getMonth();

            // Adjust if birthday hasn't occurred this year
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
                age--;
            }

            document.getElementById('age').value = age;
        }

        // Function to calculate service period
        function calculateService() {
            const joinedDateInput = document.getElementById('joinedDate');
            if (!joinedDateInput.value) return;

            const joinedDate = parseDate(joinedDateInput.value);
            if (!joinedDate) return;

            const today = new Date();
            let years = today.getFullYear() - joinedDate.getFullYear();
            let months = today.getMonth() - joinedDate.getMonth();

            // Adjust if join anniversary hasn't occurred this year
            if (months < 0 || (months === 0 && today.getDate() < joinedDate.getDate())) {
                years--;
                months += 12;
            }

            // Adjust for day of month
            if (today.getDate() < joinedDate.getDate()) {
                months--;
                if (months < 0) {
                    months += 12;
                    years--;
                }
            }

            document.getElementById('service').value = years + ' years ' + months + ' months';
        }

        // Function to convert date to database format (Y-m-d)
        function convertToDatabaseFormat(dateStr, format) {
            if (!dateStr) return '';

            let year, month, day;

            switch(format) {
                case 'Y-m-d':
                    return dateStr; // Already in correct format
                case 'd/m/Y':
                    const dmyParts = dateStr.split('/');
                    day = dmyParts[0].padStart(2, '0');
                    month = dmyParts[1].padStart(2, '0');
                    year = dmyParts[2];
                    return `${year}-${month}-${day}`;
                case 'm/d/Y':
                    const mdyParts = dateStr.split('/');
                    month = mdyParts[0].padStart(2, '0');
                    day = mdyParts[1].padStart(2, '0');
                    year = mdyParts[2];
                    return `${year}-${month}-${day}`;
                default:
                    return dateStr;
            }
        }

        // Convert dates to database format before form submission
        document.getElementById('employeeForm').addEventListener('submit', function(e) {
            const birthdayInput = document.getElementById('birthday');
            const joinedDateInput = document.getElementById('joinedDate');

            // Create hidden inputs with converted dates
            const birthdayHidden = document.createElement('input');
            birthdayHidden.type = 'hidden';
            birthdayHidden.name = 'birthday_db';
            birthdayHidden.value = convertToDatabaseFormat(birthdayInput.value, systemDateFormat);

            const joinedDateHidden = document.createElement('input');
            joinedDateHidden.type = 'hidden';
            joinedDateHidden.name = 'joined_date_db';
            joinedDateHidden.value = convertToDatabaseFormat(joinedDateInput.value, systemDateFormat);

            this.appendChild(birthdayHidden);
            this.appendChild(joinedDateHidden);
        });

        // Calculate on page load if values exist
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('birthday').value) {
                calculateAge();
            }
            if (document.getElementById('joinedDate').value) {
                calculateService();
            }
        });

        // Search reports to functionality
        function searchReportsTo() {
            const epfNumber = document.getElementById('reportsTo').value;
            if (!epfNumber) return;

            fetch('search_employee.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'epf_number=' + encodeURIComponent(epfNumber)
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('reportsToResult');
                if (data.success) {
                    resultDiv.innerHTML = '<small class="text-success"><i class="fas fa-check me-1"></i>' +
                                        data.employee.name + ' (' + data.employee.designation + ')</small>';
                } else {
                    resultDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times me-1"></i>' +
                                        data.message + '</small>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Delete employee function
        function deleteEmployee() {
            if (confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
                window.location.href = 'delete_employee.php?id=<?php echo $employee_id; ?>';
            }
        }

        // Sidebar toggle
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>