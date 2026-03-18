<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Get system date format
$date_format_setting = $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'date_format'");
$date_format = $date_format_setting['setting_value'] ?? 'Y-m-d';

// Function to parse date based on system format with enhanced CSV compatibility
function parseSystemDate($dateStr, $format) {
    if (!$dateStr) return null;

    // Clean the date string
    $dateStr = trim($dateStr);

    // Try multiple date formats for maximum CSV compatibility
    $formats = [];

    // Add system-specific formats
    switch($format) {
        case 'Y-m-d':
            $formats = ['Y-m-d', 'Y/m/d', 'Y.m.d'];
            break;
        case 'd/m/Y':
            $formats = ['d/m/Y', 'd-m-Y', 'd.m.Y'];
            break;
        case 'm/d/Y':
            $formats = ['m/d/Y', 'm-d-Y', 'm.d.Y'];
            break;
        case 'd-M-Y':
            $formats = ['d-M-Y', 'd/M/Y', 'd.M.Y'];
            break;
        default:
            $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y'];
    }

    // Add common CSV formats regardless of system format
    $common_csv_formats = [
        'n/j/Y',      // 1/17/1979 (most common CSV export format)
        'm/d/Y',      // 01/17/1979
        'd/m/Y',      // 17/01/1979
        'Y-m-d',      // 1979-01-17
        'Y/m/d',      // 1979/01/17
        'd-m-Y',      // 17-01-1979
        'm-d-Y',      // 01-17-1979
        'd.m.Y',      // 17.01.1979
        'm.d.Y',      // 01.17.1979
        'j/n/Y',      // 17/1/1979 (day/month)
        'n-j-Y',      // 1-17-1979
        'j-n-Y'       // 17-1-1979
    ];

    // Combine formats, putting CSV formats first for better matching
    $all_formats = array_unique(array_merge($common_csv_formats, $formats));

    foreach ($all_formats as $fmt) {
        $date = DateTime::createFromFormat($fmt, $dateStr);
        if ($date && $date !== false) {
            // Additional validation to prevent false positives
            $test_format = $date->format($fmt);

            // For flexible formats like n/j/Y, we need special handling
            if (in_array($fmt, ['n/j/Y', 'j/n/Y', 'n-j-Y', 'j-n-Y'])) {
                // Parse the original string to validate
                $parts = preg_split('/[\/\-]/', $dateStr);
                if (count($parts) == 3) {
                    $parsed_parts = preg_split('/[\/\-]/', $test_format);
                    if (count($parsed_parts) == 3) {
                        // Check if the year matches and month/day are reasonable
                        if (intval($parts[2]) == intval($parsed_parts[2]) &&
                            intval($parts[0]) >= 1 && intval($parts[0]) <= 12 &&
                            intval($parts[1]) >= 1 && intval($parts[1]) <= 31) {
                            return $date;
                        }
                    }
                }
            } else if ($test_format === $dateStr) {
                return $date;
            }
        }
    }

    return null;
}

// Function to clean text data - remove problematic characters and fix encoding issues
function cleanText($text) {
    if (empty($text)) return '';

    $text = trim($text);

    // First, try to fix double-encoded or corrupted UTF-8
    $original = $text;

    // Try to detect if this is double-encoded UTF-8
    $test = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    if ($test !== false && $test !== $text) {
        $text = $test;
    }

    // Try to convert from various encodings
    $encodings_to_try = ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'CP1252'];
    foreach ($encodings_to_try as $encoding) {
        $converted = @mb_convert_encoding($original, 'UTF-8', $encoding);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            $text = $converted;
            break;
        }
    }

    // Replace common problematic multi-byte sequences
    $text = str_replace([
        "\xE2\x80\x93", // en dash UTF-8
        "\xE2\x80\x94", // em dash UTF-8
        "\xE2\x80\x98", // left single quotation mark
        "\xE2\x80\x99", // right single quotation mark
        "\xE2\x80\x9C", // left double quotation mark
        "\xE2\x80\x9D", // right double quotation mark
        "\xE2\x80\xA6", // ellipsis
        "\xE2\x84\xA2", // trademark
        "\xE2\x80\xA2", // bullet
    ], [
        '-', '-', "'", "'", '"', '"', '...', '™', '•'
    ], $text);

    // Handle corrupted sequences like \xE2\xE2
    $text = preg_replace('/\xE2[\x80-\xBF]*/', '', $text);

    // Replace other problematic characters
    $replacements = [
        // Non-breaking spaces
        "\xA0" => ' ',
        "\xC2\xA0" => ' ',

        // Windows-1252 special characters (single byte)
        "\x91" => "'", "\x92" => "'", "\x93" => '"', "\x94" => '"',
        "\x95" => '•', "\x96" => '-', "\x97" => '-', "\x85" => '...',
        "\x80" => '€', "\x99" => '™',

        // Character codes
        chr(145) => "'", chr(146) => "'", chr(147) => '"', chr(148) => '"',
        chr(150) => '-', chr(151) => '-',
    ];

    $text = str_replace(array_keys($replacements), array_values($replacements), $text);

    // Remove any remaining problematic multi-byte sequences
    $text = preg_replace('/[\x80-\xFF]{2,}/', '', $text);

    // Remove control characters
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

    // Final cleanup - ensure only safe characters remain
    $text = preg_replace('/[^\x20-\x7E]/', '', $text);

    return trim($text);
}

// Check if user has permission to bulk upload employees
if (!hasModulePermission($db, 'employee.bulk_upload') && !hasAdminAccess()) {
    header('Location: index.php?error=access_denied');
    exit();
}

$message = '';
$error = '';
$upload_results = [];

// Simple debugging - always show if we received any request
$debug_info = 'Request method: ' . $_SERVER['REQUEST_METHOD'] . '. ';
if ($_POST) {
    $debug_info .= 'POST data received. ';
    if (isset($_POST['test_submit'])) {
        $debug_info .= 'Test form submitted! ';
    }
    if (isset($_POST['upload_submit'])) {
        $debug_info .= 'Upload form submitted! ';
    }
}
if (!empty($_FILES)) {
    $debug_info .= 'FILES data received. ';
}

// Handle test form submission
if (isset($_POST['test_submit'])) {
    $message = 'Test form submission successful! The form processing is working.';
}

if (isset($_POST['upload_submit']) && isset($_FILES['csv_file'])) {
    try {
        $upload_file = $_FILES['csv_file'];

        // Check for upload errors
        if ($upload_file['error'] !== UPLOAD_ERR_OK) {
            switch ($upload_file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    throw new Exception('File too large. Maximum size allowed is 5MB.');
                case UPLOAD_ERR_PARTIAL:
                    throw new Exception('File upload was interrupted. Please try again.');
                case UPLOAD_ERR_NO_FILE:
                    throw new Exception('No file was uploaded.');
                case UPLOAD_ERR_NO_TMP_DIR:
                    throw new Exception('Server configuration error: No temporary directory.');
                case UPLOAD_ERR_CANT_WRITE:
                    throw new Exception('Server configuration error: Cannot write file.');
                default:
                    throw new Exception('File upload failed with error code: ' . $upload_file['error']);
            }
        }

        // Validate file type
        $file_extension = strtolower(pathinfo($upload_file['name'], PATHINFO_EXTENSION));
        if ($file_extension !== 'csv') {
            throw new Exception('Please upload a CSV file only.');
        }

        // Check file size (max 5MB)
        if ($upload_file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File size too large. Maximum 5MB allowed.');
        }

        // Read CSV file
        $file_handle = fopen($upload_file['tmp_name'], 'r');
        if (!$file_handle) {
            throw new Exception('Could not read the uploaded file.');
        }

        // Get locations and employment levels for validation
        $locations = $db->fetchAll("SELECT id, location_name FROM locations WHERE is_active = 1");
        $employment_levels = $db->fetchAll("SELECT id, level_name FROM employment_levels WHERE is_active = 1");

        $location_map = array_column($locations, 'id', 'location_name');
        $employment_map = array_column($employment_levels, 'id', 'level_name');

        $row_count = 0;
        $success_count = 0;
        $error_count = 0;
        $insert_count = 0;
        $update_count = 0;

        // Skip header row
        $headers = fgetcsv($file_handle);

        while (($row = fgetcsv($file_handle)) !== FALSE) {
            $row_count++;

            // Skip empty rows and instruction rows
            if (empty(array_filter($row)) || strpos($row[0], '#') === 0) {
                continue;
            }

            try {
                // Map CSV columns to database fields with cleaning
                $epf_number = cleanText($row[0] ?? '');
                $name = cleanText($row[1] ?? '');
                $designation = cleanText($row[2] ?? '');
                $department = cleanText($row[3] ?? '');
                $nic = cleanText($row[4] ?? '');
                $birthday = cleanText($row[5] ?? '');
                $joined_date = cleanText($row[6] ?? '');
                $gender = cleanText($row[7] ?? '');
                $employment_level = cleanText($row[8] ?? '');
                $location = cleanText($row[9] ?? '');
                $reports_to = cleanText($row[10] ?? '');

                // Additional EPF number cleaning - ensure no hidden characters
                $epf_number = preg_replace('/[^\w]/', '', $epf_number); // Keep only alphanumeric
                $epf_number = trim($epf_number);

                // Validate required fields
                if (empty($epf_number) || empty($name) || empty($designation) || empty($department) ||
                    empty($nic) || empty($birthday) || empty($joined_date) || empty($gender) ||
                    empty($employment_level) || empty($location)) {
                    throw new Exception("Missing required fields in row $row_count");
                }

                // Check if EPF number already exists
                $existing = $db->fetch("SELECT id, name FROM employees WHERE epf_number = ?", [$epf_number]);
                $is_update = false;
                if ($existing) {
                    $is_update = true;
                    $employee_id = $existing['id'];
                    // Debug info for updates
                    error_log("BULK UPLOAD DEBUG: Updating existing employee EPF: $epf_number, ID: {$employee_id}");
                } else {
                    // Debug info for new inserts
                    error_log("BULK UPLOAD DEBUG: Creating new employee EPF: $epf_number");
                }

                // Validate location
                if (!isset($location_map[$location])) {
                    throw new Exception("Invalid location: $location");
                }

                // Validate employment level
                if (!isset($employment_map[$employment_level])) {
                    throw new Exception("Invalid employment level: $employment_level");
                }

                // Validate gender
                if (!in_array($gender, ['Male', 'Female'])) {
                    throw new Exception("Invalid gender: $gender (must be Male or Female)");
                }

                // Validate and format dates using system format
                $birthday_date = parseSystemDate($birthday, $date_format);
                if (!$birthday_date) {
                    $format_example = $date_format == 'Y-m-d' ? 'YYYY-MM-DD' :
                                    ($date_format == 'd/m/Y' ? 'DD/MM/YYYY' : 'MM/DD/YYYY');
                    throw new Exception("Invalid birthday format: $birthday (use $format_example)");
                }

                $joined_date_obj = parseSystemDate($joined_date, $date_format);
                if (!$joined_date_obj) {
                    $format_example = $date_format == 'Y-m-d' ? 'YYYY-MM-DD' :
                                    ($date_format == 'd/m/Y' ? 'DD/MM/YYYY' : 'MM/DD/YYYY');
                    throw new Exception("Invalid joined date format: $joined_date (use $format_example)");
                }

                // Calculate age and service
                $today = new DateTime();
                $age = $today->diff($birthday_date)->y;
                $service_years = $today->diff($joined_date_obj)->y;
                $service_months = $today->diff($joined_date_obj)->m;
                $service = $service_years . ' years ' . $service_months . ' months';

                // For bulk upload, we'll skip Reports To validation and set it as null initially
                // This will be updated in a second pass after all employees are imported
                $reports_to_epf = !empty($reports_to) ? $reports_to : null;

                // Prepare employee data
                $employee_data = [
                    'epf_number' => $epf_number,
                    'name' => $name,
                    'designation' => $designation,
                    'department' => $department,
                    'nic' => $nic,
                    'birthday' => $birthday_date->format('Y-m-d'), // Always store in database format
                    'age' => $age,
                    'joined_date' => $joined_date_obj->format('Y-m-d'), // Always store in database format
                    'service' => $service,
                    'gender' => $gender,
                    'employment_level_id' => $employment_map[$employment_level],
                    'location_id' => $location_map[$location],
                    'reports_to' => null, // Will be updated in second pass
                    'is_active' => 1
                ];

                if ($is_update) {
                    // Update existing employee
                    $employee_data['updated_at'] = date('Y-m-d H:i:s');
                    $db->update('employees', $employee_data, 'id = :where_id', ['where_id' => $employee_id]);

                    $upload_results[] = [
                        'row' => $row_count,
                        'status' => 'success',
                        'message' => "Employee $name ($epf_number) updated successfully",
                        'epf_number' => $epf_number,
                        'reports_to' => $reports_to_epf
                    ];
                    $update_count++;
                } else {
                    // Insert new employee
                    $employee_data['created_at'] = date('Y-m-d H:i:s');
                    $db->insert('employees', $employee_data);

                    $upload_results[] = [
                        'row' => $row_count,
                        'status' => 'success',
                        'message' => "Employee $name ($epf_number) added successfully",
                        'epf_number' => $epf_number,
                        'reports_to' => $reports_to_epf
                    ];
                    $insert_count++;
                }
                $success_count++;

            } catch (Exception $e) {
                $upload_results[] = [
                    'row' => $row_count,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $error_count++;
            }
        }

        fclose($file_handle);

        // Second pass: Update reporting relationships with improved logic
        $reports_updated = 0;
        $reports_failed = 0;
        $reports_pending = 0;

        // Create a map of EPF numbers from this upload for quick lookup
        $uploaded_epfs = [];
        foreach ($upload_results as $result) {
            if ($result['status'] === 'success') {
                $uploaded_epfs[] = $result['epf_number'];
            }
        }

        // Process reporting relationships in multiple passes to handle hierarchical dependencies
        $max_passes = 3; // Limit to prevent infinite loops
        $pass_count = 0;

        do {
            $pass_count++;
            $updated_in_this_pass = 0;

            foreach ($upload_results as &$result) {
                // Skip if already processed or no reporting relationship
                if ($result['status'] !== 'success' ||
                    empty($result['reports_to']) ||
                    strpos($result['message'], '(reporting updated)') !== false ||
                    strpos($result['message'], '(reporting failed)') !== false) {
                    continue;
                }

                try {
                    $reports_to_epf = $result['reports_to'];

                    // Check if the supervisor EPF exists (including existing employees and newly uploaded ones)
                    $supervisor = $db->fetch("SELECT id, name FROM employees WHERE epf_number = ? AND is_active = 1", [$reports_to_epf]);

                    if ($supervisor) {
                        // Update the employee's reports_to field
                        $rows_affected = $db->update('employees',
                            ['reports_to' => $reports_to_epf],
                            'epf_number = :where_epf',
                            ['where_epf' => $result['epf_number']]
                        );

                        if ($rows_affected > 0) {
                            $result['message'] .= " (reports to: " . htmlspecialchars($supervisor['name']) . ")";
                            $reports_updated++;
                            $updated_in_this_pass++;
                        } else {
                            $result['message'] .= " (reporting update failed - employee not found)";
                            $reports_failed++;
                        }
                    } else {
                        // Check if this is a dependency issue (supervisor might be uploaded later in the same file)
                        if (in_array($reports_to_epf, $uploaded_epfs)) {
                            // Supervisor is in this upload but might be processed later
                            if ($pass_count < $max_passes) {
                                continue; // Try again in next pass
                            } else {
                                $result['message'] .= " (reporting failed - circular dependency or missing supervisor)";
                                $reports_failed++;
                            }
                        } else {
                            // Supervisor EPF doesn't exist at all
                            $result['message'] .= " (supervisor EPF " . htmlspecialchars($reports_to_epf) . " not found)";
                            $reports_failed++;
                        }
                    }
                } catch (Exception $e) {
                    $result['message'] .= " (reporting update error: " . $e->getMessage() . ")";
                    $reports_failed++;
                }
            }

        } while ($updated_in_this_pass > 0 && $pass_count < $max_passes);

        // Count remaining pending relationships
        foreach ($upload_results as $result) {
            if ($result['status'] === 'success' &&
                !empty($result['reports_to']) &&
                strpos($result['message'], '(reporting updated)') === false &&
                strpos($result['message'], '(reporting failed)') === false &&
                strpos($result['message'], '(reports to:') === false) {
                $reports_pending++;
            }
        }

        $summary_parts = [];
        if ($insert_count > 0) $summary_parts[] = "$insert_count new employees added";
        if ($update_count > 0) $summary_parts[] = "$update_count employees updated";
        if ($error_count > 0) $summary_parts[] = "$error_count errors";

        $message = "Upload completed: " . implode(', ', $summary_parts);
        if ($reports_updated > 0) {
            $message .= ", $reports_updated reporting relationships updated";
        }
        if ($reports_failed > 0) {
            $message .= ", $reports_failed reporting relationships failed";
        }
        if ($reports_pending > 0) {
            $message .= ", $reports_pending reporting relationships pending (may need manual review)";
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Bulk Upload</title>
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
                        <h1><i class="fas fa-upload me-3"></i>Bulk Upload</h1>
                        <p>Upload multiple employee records from CSV file with validation and error handling</p>
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
                    <li class="breadcrumb-item active">Bulk Upload</li>
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

            <?php if ($debug_info): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i><strong>Debug:</strong> <?php echo $debug_info; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="form-card">
                        <div class="card-header-modern">
                            <h5>
                                <i class="fas fa-upload me-2"></i>Upload Employee Data
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="csv_file" class="form-label">
                                        <i class="fas fa-file-csv me-1"></i>Select CSV File
                                    </label>
                                    <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                                    <small class="text-muted">
                                        Maximum file size: 5MB. Only CSV files are accepted.
                                    </small>
                                </div>

                                <button type="submit" class="btn btn-primary" name="upload_submit" value="1">
                                    <i class="fas fa-upload me-2"></i>Upload Employees
                                </button>
                                <a href="index.php" class="btn btn-secondary ms-2">
                                    <i class="fas fa-arrow-left me-2"></i>Back
                                </a>
                            </form>

                            <!-- Test form (outside main form) -->
                            <hr class="my-3">
                            <small class="text-muted">Test form (temporary):</small><br>
                            <form method="POST">
                                <input type="hidden" name="test_submit" value="1">
                                <button type="submit" class="btn btn-outline-info btn-sm">Test Form Submit</button>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($upload_results)): ?>
                        <div class="content-card">
                            <div class="card-header-modern">
                                <h5>
                                    <i class="fas fa-list me-2"></i>Upload Results
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Row</th>
                                                <th>Status</th>
                                                <th>Message</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upload_results as $result): ?>
                                                <tr>
                                                    <td><?php echo $result['row']; ?></td>
                                                    <td>
                                                        <?php if ($result['status'] === 'success'): ?>
                                                            <span class="badge bg-success">Success</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Error</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($result['message']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5>
                                <i class="fas fa-info-circle me-2"></i>Instructions
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6>Step 1: Download Template</h6>
                            <p class="text-muted">Download the CSV template with proper format and sample data.</p>
                            <a href="template_download.php" class="btn btn-outline-info btn-sm mb-3">
                                <i class="fas fa-download me-1"></i>Download Template
                            </a>

                            <h6>Step 2: Fill Employee Data</h6>
                            <?php
                            $format_display = $date_format == 'Y-m-d' ? 'YYYY-MM-DD' :
                                            ($date_format == 'd/m/Y' ? 'DD/MM/YYYY' : 'MM/DD/YYYY');
                            $format_example = $date_format == 'Y-m-d' ? '1990-01-15' :
                                            ($date_format == 'd/m/Y' ? '15/01/1990' : '01/15/1990');
                            ?>
                            <ul class="text-muted small">
                                <li><strong>EPF Number must be unique</strong></li>
                                <li><strong>Date format: <?php echo $format_display; ?></strong> (e.g., <?php echo $format_example; ?>)</li>
                                <li><strong>Birthday:</strong> Use <?php echo $format_display; ?> format</li>
                                <li><strong>Joined Date:</strong> Use <?php echo $format_display; ?> format</li>
                                <li>Gender: Male or Female only</li>
                                <li>Employment Level: MD, GM, Manager, etc.</li>
                                <li>Location: 7C, Pannala, Kobeigane, JECOE, Head Office</li>
                                <li><strong>Reports To:</strong> Use EPF number of supervisor (can be left empty)</li>
                            </ul>

                            <div class="alert alert-info alert-sm">
                                <small>
                                    <i class="fas fa-info-circle me-1"></i>
                                    <strong>Reporting Relationships:</strong>
                                    <ul class="mb-0 mt-1">
                                        <li>Supervisors can be included in the same upload file</li>
                                        <li>System processes relationships in multiple passes</li>
                                        <li>For best results, include supervisors before subordinates</li>
                                        <li>Failed relationships can be updated manually later</li>
                                    </ul>
                                </small>
                            </div>

                            <h6>Step 3: Upload File</h6>
                            <p class="text-muted small">
                                Select your filled CSV file and click upload. The system will validate all data before importing.
                            </p>

                            <div class="alert alert-warning alert-sm">
                                <small>
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Age and Service will be calculated automatically from Birthday and Joined Date.
                                </small>
                            </div>

                            <div class="alert alert-info alert-sm">
                                <small>
                                    <i class="fas fa-calendar me-1"></i>
                                    <strong>Date Format:</strong> All dates must be in <?php echo $format_display; ?> format (e.g., <?php echo $format_example; ?>).
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>