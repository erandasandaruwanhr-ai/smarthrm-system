<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/performance_auth.php';

// Check if user has leave utilization permission
requirePerformancePermission('performance.leave_utilization');

$user = getCurrentUser();
$db = new Database();

$message = '';
$error = '';

// Get selected year (default to current year)
$selected_year = $_GET['year'] ?? date('Y');

// Handle CSV template download
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    $template_year = $_GET['template_year'] ?? $selected_year;

    // Get only staff members (employment levels 8 and 9) for the template
    $employees = $db->fetchAll("
        SELECT epf_number, name, designation, department
        FROM employees
        WHERE is_active = 1 AND employment_level_id IN (8, 9)
        ORDER BY epf_number
    ");

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="staff_leave_template_' . $template_year . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write CSV header
    fputcsv($output, [
        'EPF_Number',
        'Staff_Name',
        'Designation',
        'Department',
        'Annual_Leave',
        'Casual_Leave',
        'Medical_Leave',
        'No_Pay',
        'Year'
    ]);

    // Write employee data with default values
    foreach ($employees as $employee) {
        fputcsv($output, [
            $employee['epf_number'],
            $employee['name'],
            $employee['designation'],
            $employee['department'],
            0, // Annual_Leave default
            0, // Casual_Leave default
            0, // Medical_Leave default
            0, // No_Pay default
            $template_year
        ]);
    }

    fclose($output);
    exit;
}

// Determine EPF column name in leave_utilization table
function getLeaveEpfColumn($db) {
    try {
        $columns = $db->fetchAll("SHOW COLUMNS FROM leave_utilization");
        foreach ($columns as $column) {
            if ($column['Field'] === 'epf_no') {
                return 'epf_no';
            } elseif ($column['Field'] === 'employee_epf') {
                return 'employee_epf';
            }
        }
        return 'epf_number'; // default
    } catch (Exception $e) {
        return 'epf_number'; // default if table doesn't exist
    }
}

// Handle form submissions
if ($_POST) {
    $epf_column = getLeaveEpfColumn($db);

    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'upload_csv') {
            // Handle CSV upload
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $csv_file = $_FILES['csv_file']['tmp_name'];

                if (($handle = fopen($csv_file, "r")) !== FALSE) {
                    $row_count = 0;
                    $success_count = 0;
                    $error_count = 0;

                    // Skip header row
                    fgetcsv($handle);

                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $row_count++;

                        // Handle both old format (6 columns) and new format (9 columns)
                        $epf_number = null;
                        $annual_leave = 0;
                        $casual_leave = 0;
                        $medical_leave = 0;
                        $no_pay = 0;
                        $year = $selected_year;

                        if (count($data) >= 9) {
                            // New format with employee details
                            $epf_number = trim($data[0]);
                            $annual_leave = (int) trim($data[4]);
                            $casual_leave = (int) trim($data[5]);
                            $medical_leave = (int) trim($data[6]);
                            $no_pay = (int) trim($data[7]);
                            $year = trim($data[8]) ?: $selected_year;
                        } elseif (count($data) >= 6) {
                            // Old format for backward compatibility
                            $epf_number = trim($data[0]);
                            $annual_leave = (int) trim($data[1]);
                            $casual_leave = (int) trim($data[2]);
                            $medical_leave = (int) trim($data[3]);
                            $no_pay = (int) trim($data[4]);
                            $year = trim($data[5]) ?: $selected_year;
                        } else {
                            $error_count++;
                            continue;
                        }

                        if ($epf_number) {
                            // Check if record exists
                            $existing = $db->fetch(
                                "SELECT id FROM leave_utilization WHERE CAST({$epf_column} AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(? AS CHAR) COLLATE utf8mb4_unicode_ci AND year = ?",
                                [$epf_number, $year]
                            );

                            $leave_data = [
                                $epf_column => $epf_number,
                                'year' => $year,
                                'annual_leave_days' => $annual_leave,
                                'casual_leave_days' => $casual_leave,
                                'medical_leave_days' => $medical_leave,
                                'no_pay_days' => $no_pay
                            ];

                            if ($existing) {
                                $db->update('leave_utilization', $leave_data, 'id = ?', [$existing['id']]);
                            } else {
                                $db->insert('leave_utilization', $leave_data);
                            }

                            $success_count++;
                        }
                    }
                    fclose($handle);

                    $message = "CSV upload completed! Processed: $row_count rows, Success: $success_count, Errors: $error_count";
                } else {
                    $error = "Error reading CSV file.";
                }
            } else {
                $error = "Please select a valid CSV file.";
            }

        } elseif ($action === 'manual_entry') {
            $epf_number = trim($_POST['epf_number']);
            $annual_leave = (int) $_POST['annual_leave_days'];
            $casual_leave = (int) $_POST['casual_leave_days'];
            $medical_leave = (int) $_POST['medical_leave_days'];
            $no_pay = (int) $_POST['no_pay_days'];
            $year = $_POST['leave_year'];

            if ($epf_number) {
                // Check if record exists
                $existing = $db->fetch(
                    "SELECT id FROM leave_utilization WHERE CAST({$epf_column} AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(? AS CHAR) COLLATE utf8mb4_unicode_ci AND year = ?",
                    [$epf_number, $year]
                );

                $leave_data = [
                    $epf_column => $epf_number,
                    'year' => $year,
                    'annual_leave_days' => $annual_leave,
                    'casual_leave_days' => $casual_leave,
                    'medical_leave_days' => $medical_leave,
                    'no_pay_days' => $no_pay
                ];

                if ($existing) {
                    $db->update('leave_utilization', $leave_data, 'id = ?', [$existing['id']]);
                    $message = "Leave utilization updated successfully for EPF: $epf_number";
                } else {
                    $db->insert('leave_utilization', $leave_data);
                    $message = "Leave utilization added successfully for EPF: $epf_number";
                }
            } else {
                $error = "EPF Number is required.";
            }

        } elseif ($action === 'delete') {
            $id = $_POST['record_id'];
            $db->execute("DELETE FROM leave_utilization WHERE id = ?", [$id]);
            $message = "Leave utilization record deleted successfully.";
        }

    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get leave utilization data for selected year with dynamic column detection
$leave_epf_column = 'epf_number'; // default global variable

try {
    // Check if leave_utilization table exists and determine EPF column name
    $tables = $db->fetchAll("SHOW TABLES LIKE 'leave_utilization'");
    if (empty($tables)) {
        // Table doesn't exist, create empty array
        $leave_data = [];
    } else {
        // Check what columns exist in leave_utilization table
        $columns = $db->fetchAll("SHOW COLUMNS FROM leave_utilization");

        foreach ($columns as $column) {
            if ($column['Field'] === 'epf_no') {
                $leave_epf_column = 'epf_no';
                break;
            } elseif ($column['Field'] === 'employee_epf') {
                $leave_epf_column = 'employee_epf';
                break;
            }
        }

        $leave_data = $db->fetchAll("
            SELECT
                lu.*,
                e.name as employee_name,
                e.designation,
                e.department,
                CASE
                    WHEN e.location_id = 1 THEN '7C'
                    WHEN e.location_id = 2 THEN 'Pannala'
                    WHEN e.location_id = 3 THEN 'Kobeigane'
                    WHEN e.location_id = 4 THEN 'JECOE'
                    WHEN e.location_id = 5 THEN 'Head Office'
                    ELSE 'Unknown'
                END as location
            FROM leave_utilization lu
            LEFT JOIN employees e ON CAST(lu.{$leave_epf_column} AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(e.epf_number AS CHAR) COLLATE utf8mb4_unicode_ci
            WHERE lu.year = ?
            ORDER BY lu.{$leave_epf_column}
        ", [$selected_year]);
    }
} catch (Exception $e) {
    // If there's any error, create empty array and log the error
    error_log("Leave utilization query failed: " . $e->getMessage());
    $leave_data = [];
}

// Get available years (with error handling and ensure 2025-2030 are always available)
try {
    // Get years from data and merge with predefined range
    $data_years = $db->fetchAll("
        SELECT DISTINCT year FROM leave_utilization
        ORDER BY year DESC
    ");

    // Create array of years 2025-2030
    $predefined_years = [];
    for ($year = 2030; $year >= 2025; $year--) {
        $predefined_years[] = ['year' => $year];
    }

    // Merge and remove duplicates
    $all_years = array_merge($predefined_years, $data_years);
    $unique_years = [];
    $seen_years = [];

    foreach ($all_years as $year_data) {
        if (!in_array($year_data['year'], $seen_years)) {
            $unique_years[] = $year_data;
            $seen_years[] = $year_data['year'];
        }
    }

    // Sort by year descending
    usort($unique_years, function($a, $b) {
        return $b['year'] - $a['year'];
    });

    $available_years = $unique_years;

} catch (Exception $e) {
    // Table might not exist, use predefined years
    $available_years = [];
    for ($year = 2030; $year >= 2025; $year--) {
        $available_years[] = ['year' => $year];
    }
}

// Calculate statistics
$total_records = count($leave_data);
$total_annual_leave = array_sum(array_column($leave_data, 'annual_leave_days'));
$total_casual_leave = array_sum(array_column($leave_data, 'casual_leave_days'));
$total_medical_leave = array_sum(array_column($leave_data, 'medical_leave_days'));
$total_no_pay = array_sum(array_column($leave_data, 'no_pay_days'));

$avg_annual_leave = $total_records > 0 ? round($total_annual_leave / $total_records, 1) : 0;
$avg_casual_leave = $total_records > 0 ? round($total_casual_leave / $total_records, 1) : 0;
$avg_medical_leave = $total_records > 0 ? round($total_medical_leave / $total_records, 1) : 0;
$avg_no_pay = $total_records > 0 ? round($total_no_pay / $total_records, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Staff Leave Utilization Management (13.3)</title>
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

        .stats-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .upload-area {
            border: 2px dashed var(--info-color);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
        }

        .leave-table {
            font-size: 0.9em;
        }

        .leave-badge {
            font-size: 0.8em;
            padding: 0.3em 0.6em;
        }

        .csv-template {
            background: #e9ecef;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
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
            padding: 0.5rem;
            font-family: monospace;
            font-size: 0.85em;
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
                        <h1><i class="fas fa-calendar-check me-3"></i>Staff Leave Utilization</h1>
                        <p>Manage and track staff leave utilization data for performance appraisals</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0"><i class="fas fa-calendar-check text-info me-2"></i>Staff Leave Utilization Management</h1>
                        <p class="text-muted mb-0">Upload and manage staff member leave data for performance appraisals (13.3) - Employment Levels 8 & 9 only</p>
                    </div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Performance</a></li>
                            <li class="breadcrumb-item active">Leave Utilization</li>
                        </ol>
                    </nav>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card border-info">
                            <div class="card-body p-3">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-1">
                                            <i class="fas fa-download text-info me-2"></i>Download Staff CSV Template
                                        </h6>
                                        <small class="text-muted">
                                            Get a pre-filled CSV template with all active staff members (Levels 8 & 9) for easy leave data entry.
                                        </small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <select id="quickTemplateYear" class="form-select form-select-sm d-inline-block w-auto me-2">
                                            <option value="2025" <?= $selected_year == '2025' ? 'selected' : '' ?>>2025</option>
                                            <option value="2026" <?= $selected_year == '2026' ? 'selected' : '' ?>>2026</option>
                                            <option value="2027" <?= $selected_year == '2027' ? 'selected' : '' ?>>2027</option>
                                            <option value="2028" <?= $selected_year == '2028' ? 'selected' : '' ?>>2028</option>
                                            <option value="2029" <?= $selected_year == '2029' ? 'selected' : '' ?>>2029</option>
                                            <option value="2030" <?= $selected_year == '2030' ? 'selected' : '' ?>>2030</option>
                                        </select>
                                        <button class="btn btn-info btn-sm" onclick="quickDownloadTemplate()">
                                            <i class="fas fa-download me-1"></i>Download Template
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?= $total_records ?></h3>
                                <small>Staff Records</small>
                                <br><small class="text-white-50">(Levels 8 & 9)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?= $avg_annual_leave ?></h3>
                                <small>Avg Annual Leave</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?= $avg_casual_leave ?></h3>
                                <small>Avg Casual Leave</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?= $avg_medical_leave ?></h3>
                                <small>Avg Medical Leave</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Left Column: Upload & Manual Entry -->
                    <div class="col-md-6">
                        <!-- CSV Upload -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-upload text-info me-2"></i>CSV Upload
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_csv">

                                    <div class="upload-area mb-3">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-info mb-3"></i>
                                        <h6>Upload Staff Leave Utilization CSV</h6>
                                        <input type="file" name="csv_file" accept=".csv" class="form-control mt-3" required>
                                    </div>

                                    <div class="row">
                                        <div class="col-8">
                                            <div class="mb-3">
                                                <label class="form-label">Target Year</label>
                                                <select name="target_year" id="targetYear" class="form-select">
                                                    <option value="2025" <?= $selected_year == '2025' ? 'selected' : '' ?>>2025</option>
                                                    <option value="2026" <?= $selected_year == '2026' ? 'selected' : '' ?>>2026</option>
                                                    <option value="2027" <?= $selected_year == '2027' ? 'selected' : '' ?>>2027</option>
                                                    <option value="2028" <?= $selected_year == '2028' ? 'selected' : '' ?>>2028</option>
                                                    <option value="2029" <?= $selected_year == '2029' ? 'selected' : '' ?>>2029</option>
                                                    <option value="2030" <?= $selected_year == '2030' ? 'selected' : '' ?>>2030</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-outline-info w-100" onclick="downloadTemplate()">
                                                <i class="fas fa-download me-1"></i>Template
                                            </button>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-info w-100">
                                        <i class="fas fa-upload me-1"></i>Upload CSV
                                    </button>
                                </form>

                                <div class="mt-3">
                                    <small class="text-muted"><strong>CSV Format for Staff Members (use Template button above):</strong></small>
                                    <div class="csv-template mt-1">
EPF_Number,Staff_Name,Designation,Department,Annual_Leave,Casual_Leave,Medical_Leave,No_Pay,Year<br>
101,John Doe,Staff,Production,12,5,3,0,2025<br>
102,Jane Smith,Supervisor,Quality,14,7,2,1,2025
                                    </div>
                                    <small class="text-info mt-2 d-block">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Click "Template" to download a pre-filled CSV with all active staff members (Employment Levels 8 & 9) for the selected year.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Manual Entry -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-edit text-success me-2"></i>Manual Entry
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="manual_entry">

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <label class="form-label">EPF Number</label>
                                                <input type="text" name="epf_number" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <label class="form-label">Year</label>
                                                <select name="leave_year" class="form-select">
                                                    <option value="2025" selected>2025</option>
                                                    <option value="2026">2026</option>
                                                    <option value="2027">2027</option>
                                                    <option value="2028">2028</option>
                                                    <option value="2029">2029</option>
                                                    <option value="2030">2030</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <label class="form-label">Annual Leave Days</label>
                                                <input type="number" name="annual_leave_days" class="form-control" min="0" value="0">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <label class="form-label">Casual Leave Days</label>
                                                <input type="number" name="casual_leave_days" class="form-control" min="0" value="0">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <label class="form-label">Medical Leave Days</label>
                                                <input type="number" name="medical_leave_days" class="form-control" min="0" value="0">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <label class="form-label">No Pay Days</label>
                                                <input type="number" name="no_pay_days" class="form-control" min="0" value="0">
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-save me-1"></i>Save Record
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Data Table -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-table text-info me-2"></i>Staff Leave Utilization Data
                                        </h5>
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select form-select-sm" onchange="window.location.href='?year=' + this.value">
                                            <?php foreach ($available_years as $year): ?>
                                                <option value="<?= $year['year'] ?>" <?= $year['year'] == $selected_year ? 'selected' : '' ?>>
                                                    <?= $year['year'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                                <?php if (empty($leave_data)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-calendar-times text-muted" style="font-size: 3em;"></i>
                                        <h5 class="mt-3 text-muted">No Staff Leave Data Found</h5>
                                        <p class="text-muted">Upload staff CSV or add manual entries to get started.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped leave-table mb-0">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>EPF</th>
                                                    <th>Staff Member</th>
                                                    <th>Annual</th>
                                                    <th>Casual</th>
                                                    <th>Medical</th>
                                                    <th>No Pay</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($leave_data as $record): ?>
                                                    <tr>
                                                        <td>
                                                            <strong class="text-primary"><?= htmlspecialchars($record[$leave_epf_column] ?? $record['epf_number'] ?? 'N/A') ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php if ($record['employee_name']): ?>
                                                                <strong><?= htmlspecialchars($record['employee_name']) ?></strong><br>
                                                                <small class="text-muted"><?= htmlspecialchars($record['designation'] ?? '') ?></small>
                                                            <?php else: ?>
                                                                <em class="text-muted">Staff member not found</em>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success leave-badge">
                                                                <?= $record['annual_leave_days'] ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-warning leave-badge">
                                                                <?= $record['casual_leave_days'] ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info leave-badge">
                                                                <?= $record['medical_leave_days'] ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-danger leave-badge">
                                                                <?= $record['no_pay_days'] ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-outline-danger btn-sm"
                                                                    onclick="deleteRecord(<?= $record['id'] ?>, '<?= htmlspecialchars($record[$leave_epf_column] ?? $record['epf_number'] ?? 'N/A') ?>')"
                                                                    title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Back to Performance Dashboard -->
                <div class="d-flex justify-content-between mt-4">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Performance Dashboard
                    </a>
                    <div>
                        <button class="btn btn-outline-info me-2" onclick="exportLeaveData()">
                            <i class="fas fa-download me-1"></i>Export CSV
                        </button>
                        <a href="staff_appraisal.php" class="btn btn-outline-success">
                            <i class="fas fa-clipboard-check me-1"></i>Staff Appraisals
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete leave utilization data for EPF: <strong id="deleteEpf"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="record_id" id="deleteRecordId">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteRecord(recordId, epfNumber) {
            document.getElementById('deleteRecordId').value = recordId;
            document.getElementById('deleteEpf').textContent = epfNumber;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function exportLeaveData() {
            // Simple CSV export functionality
            const table = document.querySelector('.leave-table');
            const rows = table.querySelectorAll('tr');
            let csv = 'EPF_Number,Staff_Name,Annual_Leave,Casual_Leave,Medical_Leave,No_Pay\n';

            for (let i = 1; i < rows.length; i++) { // Skip header
                const cells = rows[i].querySelectorAll('td');
                const epf = cells[0].textContent.trim();
                const name = cells[1].textContent.split('\n')[0].trim();
                const annual = cells[2].textContent.trim();
                const casual = cells[3].textContent.trim();
                const medical = cells[4].textContent.trim();
                const noPay = cells[5].textContent.trim();

                csv += `"${epf}","${name}",${annual},${casual},${medical},${noPay}\n`;
            }

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', `leave_utilization_<?= $selected_year ?>.csv`);
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        function downloadTemplate() {
            const selectedYear = document.getElementById('targetYear').value;
            window.location.href = `?action=download_template&template_year=${selectedYear}`;
        }

        function quickDownloadTemplate() {
            const selectedYear = document.getElementById('quickTemplateYear').value;
            window.location.href = `?action=download_template&template_year=${selectedYear}`;
        }
    </script>
    </div>
</body>
</html>