<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($db, 'medical.monthly_claims')) {
    header('Location: ../../dashboard.php');
    exit;
}

$success_message = '';
$error_message = '';

// Medical insurance months (8.3.1.4)
$medicalMonths = [
    'February (9-28/29)',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
    'January',
    'February (1-8)'
];

// Available coverage periods
$availablePeriods = [
    '2023-2024' => '2023-2024 (Feb 9, 2023 - Feb 8, 2024)',
    '2024-2025' => '2024-2025 (Feb 9, 2024 - Feb 8, 2025)',
    '2025-2026' => '2025-2026 (Feb 9, 2025 - Feb 8, 2026)',
    '2026-2027' => '2026-2027 (Feb 9, 2026 - Feb 8, 2027)',
    '2027-2028' => '2027-2028 (Feb 9, 2027 - Feb 8, 2028)',
    '2028-2029' => '2028-2029 (Feb 9, 2028 - Feb 8, 2029)',
    '2029-2030' => '2029-2030 (Feb 9, 2029 - Feb 8, 2030)',
    '2030-2031' => '2030-2031 (Feb 9, 2030 - Feb 8, 2031)'
];
$currentYear = date('Y');
$currentCoveragePeriod = $currentYear . '-' . ($currentYear + 1);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log POST data
    error_log("POST data: " . print_r($_POST, true));

    try {
        if (isset($_POST['submit_claim'])) {
            // Check for both new and old field names for backward compatibility
            $epf_number = $_POST['epf_number'] ?? '';
            $coverage_period = $_POST['coverage_period'] ?? $_POST['year'] ?? '';
            $month = $_POST['month'] ?? '';

            // Convert old year format to coverage_period if needed
            if (!empty($_POST['year']) && empty($_POST['coverage_period'])) {
                $year = $_POST['year'];
                $coverage_period = $year . '-' . ($year + 1);
            }

            // Additional validation
            if (empty($epf_number) || empty($coverage_period) || empty($month)) {
                throw new Exception("EPF Number, Coverage Period, and Month are required. Got: EPF='$epf_number', Period='$coverage_period', Month='$month'");
            }
            $requested_amount = floatval($_POST['requested_amount']);
            $rejected_amount = floatval($_POST['rejected_amount'] ?? 0);
            $claimed_amount = floatval($_POST['claimed_amount']);

            // Validation
            if ($requested_amount < 0 || $rejected_amount < 0 || $claimed_amount < 0) {
                throw new Exception("Amounts cannot be negative.");
            }

            if ($claimed_amount > $requested_amount) {
                throw new Exception("Claimed amount cannot exceed requested amount.");
            }

            // Validate and extract start year from coverage period
            if (!preg_match('/^\d{4}-\d{4}$/', $coverage_period)) {
                throw new Exception("Invalid coverage period format. Expected format: YYYY-YYYY");
            }
            $startYear = explode('-', $coverage_period)[0];

            // Ensure start year is numeric
            if (!is_numeric($startYear)) {
                throw new Exception("Invalid year extracted from coverage period.");
            }

            // Check for duplicate entry
            $existing = $db->fetch("SELECT id FROM medical_claims WHERE epf_number = ? AND coverage_period = ? AND month = ?", [$epf_number, $coverage_period, $month]);

            if ($existing) {
                // Update existing claim
                $db->update('medical_claims', [
                    'requested_amount' => $requested_amount,
                    'rejected_amount' => $rejected_amount,
                    'claimed_amount' => $claimed_amount,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$existing['id']]);
                $success_message = "Claim updated successfully for " . $epf_number . " - " . $month . " " . $year;
            } else {
                // Insert new claim
                $db->insert('medical_claims', [
                    'epf_number' => $epf_number,
                    'year' => $startYear,
                    'coverage_period' => $coverage_period,
                    'month' => $month,
                    'requested_amount' => $requested_amount,
                    'rejected_amount' => $rejected_amount,
                    'claimed_amount' => $claimed_amount
                ]);
                $success_message = "New claim submitted successfully for " . $epf_number . " - " . $month . " (" . $coverage_period . ")";
            }

        } elseif (isset($_POST['update_medical_claim'])) {
            $claim_id = $_POST['claim_id'];
            $requested_amount = floatval($_POST['requested_amount']);
            $rejected_amount = floatval($_POST['rejected_amount'] ?? 0);
            $claimed_amount = floatval($_POST['claimed_amount']);

            // Validation
            if ($requested_amount < 0 || $rejected_amount < 0 || $claimed_amount < 0) {
                throw new Exception("Amounts cannot be negative.");
            }

            if ($claimed_amount > $requested_amount) {
                throw new Exception("Claimed amount cannot exceed requested amount.");
            }

            // Update medical claim
            $db->update('medical_claims', [
                'requested_amount' => $requested_amount,
                'rejected_amount' => $rejected_amount,
                'claimed_amount' => $claimed_amount,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$claim_id]);

            $success_message = "Medical claim updated successfully - Rs. " . number_format($claimed_amount, 2);

        } elseif (isset($_POST['delete_medical_claim'])) {
            $claim_id = $_POST['claim_id'];
            $db->delete('medical_claims', 'id = ?', [$claim_id]);
            $success_message = "Medical claim deleted successfully.";

        } elseif (isset($_POST['bulk_upload'])) {
            // Handle bulk upload (CSV processing)
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $csvFile = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($csvFile, 'r');

                if ($handle !== false) {
                    $row = 0;
                    $successful = 0;
                    $errors = [];

                    // Skip header row
                    fgetcsv($handle);

                    while (($data = fgetcsv($handle)) !== false) {
                        $row++;
                        try {
                            if (count($data) >= 6) {
                                $epf = trim($data[0]);
                                $field2 = trim($data[1]); // Could be coverage_period or year
                                $month = trim($data[2]);

                                // Handle both old year format and new coverage_period format
                                if (preg_match('/^\d{4}$/', $field2)) {
                                    // Old format: just year
                                    $coverage_period = $field2 . '-' . ($field2 + 1);
                                    $startYear = $field2;
                                } elseif (preg_match('/^\d{4}-\d{4}$/', $field2)) {
                                    // New format: coverage period
                                    $coverage_period = $field2;
                                    $startYear = explode('-', $coverage_period)[0];
                                } else {
                                    throw new Exception("Invalid year/coverage period format: '$field2'. Expected YYYY or YYYY-YYYY");
                                }
                                $requested = floatval($data[3]);
                                $rejected = floatval($data[4]);
                                $claimed = floatval($data[5]);

                                // Validate employee exists
                                $employee = $db->fetch("SELECT epf_number FROM employees WHERE epf_number = ?", [$epf]);
                                if (!$employee) {
                                    throw new Exception("Employee EPF $epf not found");
                                }

                                // Check for duplicate
                                $existing = $db->fetch("SELECT id FROM medical_claims WHERE epf_number = ? AND coverage_period = ? AND month = ?", [$epf, $coverage_period, $month]);

                                if ($existing) {
                                    $db->update('medical_claims', [
                                        'requested_amount' => $requested,
                                        'rejected_amount' => $rejected,
                                        'claimed_amount' => $claimed,
                                        'updated_at' => date('Y-m-d H:i:s')
                                    ], 'id = ?', [$existing['id']]);
                                } else {
                                    $db->insert('medical_claims', [
                                        'epf_number' => $epf,
                                        'year' => $startYear,
                                        'coverage_period' => $coverage_period,
                                        'month' => $month,
                                        'requested_amount' => $requested,
                                        'rejected_amount' => $rejected,
                                        'claimed_amount' => $claimed
                                    ]);
                                }
                                $successful++;
                            }
                        } catch (Exception $e) {
                            $errors[] = "Row $row: " . $e->getMessage();
                        }
                    }
                    fclose($handle);

                    $success_message = "$successful claims processed successfully.";
                    if (!empty($errors)) {
                        $error_message = "Some errors occurred: " . implode("; ", array_slice($errors, 0, 3));
                    }
                }
            } else {
                $error_message = "Please select a valid CSV file.";
            }
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get current allocation
$currentAllocation = 125000.00;
try {
    $allocation = $db->fetch("SELECT allocation_limit FROM medical_allocations WHERE coverage_period = ?", [$currentCoveragePeriod]);
    if ($allocation) {
        $currentAllocation = $allocation['allocation_limit'];
    }
} catch (Exception $e) {
    error_log("Failed to get allocation: " . $e->getMessage());
}

// Get employees based on user role
$employees = [];
$claimsQuery = "";
$claimsParams = [];

if ($user['account_type'] === 'superadmin') {
    // Superadmin sees all employees
    $employees = $db->fetchAll("SELECT epf_number, name, gender, age, l.location_name FROM employees e LEFT JOIN locations l ON e.location_id = l.id WHERE e.is_active = 1 ORDER BY e.name");
    $claimsQuery = "SELECT mc.*, e.name, e.gender, e.age, l.location_name FROM medical_claims mc JOIN employees e ON mc.epf_number = e.epf_number LEFT JOIN locations l ON e.location_id = l.id ORDER BY mc.coverage_period DESC, mc.epf_number";
} elseif ($user['account_type'] === 'admin') {
    // Admin sees employees from their location
    $employees = $db->fetchAll("SELECT e.epf_number, e.name, e.gender, e.age, l.location_name FROM employees e JOIN locations l ON e.location_id = l.id WHERE l.location_name = ? AND e.is_active = 1 ORDER BY e.name", [$user['location']]);
    $claimsQuery = "SELECT mc.*, e.name, e.gender, e.age, l.location_name FROM medical_claims mc JOIN employees e ON mc.epf_number = e.epf_number LEFT JOIN locations l ON e.location_id = l.id WHERE l.location_name = ? ORDER BY mc.coverage_period DESC, mc.epf_number";
    $claimsParams = [$user['location']];
} else {
    // Regular user sees only their own data
    $employees = $db->fetchAll("SELECT epf_number, name, gender, age, l.location_name FROM employees e LEFT JOIN locations l ON e.location_id = l.id WHERE e.epf_number = ?", [$user['epf_number']]);
    $claimsQuery = "SELECT mc.*, e.name, e.gender, e.age, l.location_name FROM medical_claims mc JOIN employees e ON mc.epf_number = e.epf_number LEFT JOIN locations l ON e.location_id = l.id WHERE mc.epf_number = ? ORDER BY mc.coverage_period DESC, mc.created_at DESC";
    $claimsParams = [$user['epf_number']];
}

// Get claims with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$totalClaims = 0;
$claims = [];

try {
    // Get total count
    $countQuery = str_replace("SELECT mc.*, e.name, e.gender, e.age, l.location_name FROM", "SELECT COUNT(*) as total FROM", $claimsQuery);
    $totalResult = $db->fetch($countQuery, $claimsParams);
    $totalClaims = $totalResult['total'];

    // Get claims with limit
    $claims = $db->fetchAll($claimsQuery . " LIMIT $limit OFFSET $offset", $claimsParams);
} catch (Exception $e) {
    error_log("Claims query error: " . $e->getMessage());
}

$totalPages = ceil($totalClaims / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Monthly Claims</title>
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

        .month-badge {
            background: var(--info-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-size: 0.8rem;
        }

        .amount-cell {
            text-align: right;
            font-family: monospace;
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
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-file-medical me-3"></i>Monthly Claims</h1>
                        <p>Submit and manage monthly medical insurance claims and reimbursements</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Medical Insurance</a></li>
                    <li class="breadcrumb-item active">Monthly Claims</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4 mb-0">Claims Management</h2>
                <div>
                    <?php if (in_array($user['account_type'], ['admin', 'superadmin'])): ?>
                    <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                        <i class="fas fa-upload me-2"></i>Bulk Upload
                    </button>
                    <button class="btn btn-primary me-2" onclick="downloadTemplate()">
                        <i class="fas fa-download me-2"></i>Template
                    </button>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>

            <!-- Success/Error Messages -->
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

            <!-- Allocation Info -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Medical Insurance Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Current Year Allocation:</strong> Rs. <?php echo number_format($currentAllocation, 2); ?> per employee
                            </div>
                            <div class="col-md-6">
                                <strong>Coverage Period:</strong> February 9, <?php echo $currentYear; ?> - February 8, <?php echo $currentYear + 1; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (in_array($user['account_type'], ['admin', 'superadmin'])): ?>
            <!-- Claim Entry Form -->
            <div class="form-card">
                <h4 class="mb-4"><i class="fas fa-plus me-2"></i>Submit Medical Claim</h4>
                <form method="post" class="row g-3">
                    <input type="hidden" name="submit_claim" value="1">

                    <div class="col-md-3">
                        <label for="epf_number" class="form-label">Employee *</label>
                        <select class="form-control" name="epf_number" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['epf_number']; ?>">
                                    <?php echo $employee['epf_number']; ?> - <?php echo htmlspecialchars($employee['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="coverage_period" class="form-label">Coverage Period *</label>
                        <select class="form-control" name="coverage_period" required>
                            <?php foreach ($availablePeriods as $period => $display): ?>
                                <option value="<?php echo $period; ?>" <?php echo $period == $currentCoveragePeriod ? 'selected' : ''; ?>>
                                    <?php echo $display; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="month" class="form-label">Month *</label>
                        <select class="form-control" name="month" required>
                            <option value="">Select Month</option>
                            <?php foreach ($medicalMonths as $month): ?>
                                <option value="<?php echo $month; ?>"><?php echo $month; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="requested_amount" class="form-label">Requested (Rs.) *</label>
                        <input type="number" class="form-control" name="requested_amount" step="0.01" min="0" required>
                    </div>

                    <div class="col-md-2">
                        <label for="rejected_amount" class="form-label">Rejected (Rs.)</label>
                        <input type="number" class="form-control" name="rejected_amount" step="0.01" min="0" value="0">
                    </div>

                    <div class="col-md-2">
                        <label for="claimed_amount" class="form-label">Claimed (Rs.) *</label>
                        <input type="number" class="form-control" name="claimed_amount" step="0.01" min="0" required>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Submit Claim
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-2"></i>Reset
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Claims Table -->
            <div class="content-card">
                <div class="card-header-modern">
                    <h5><i class="fas fa-table me-2"></i>Medical Claims Records</h5>
                </div>
                <?php if ($totalClaims > 0): ?>
                    <div class="mb-3">
                        <span class="badge bg-primary"><?php echo number_format($totalClaims); ?> Claims</span>
                    </div>
                <?php endif; ?>

                <?php if (empty($claims)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Claims Found</h5>
                        <p class="text-muted">
                            <?php if ($user['account_type'] !== 'user'): ?>
                                Start by submitting medical claims for employees.
                            <?php else: ?>
                                No medical claims have been submitted for your account yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>EPF Number</th>
                                    <th>Employee Name</th>
                                    <th>Coverage Period</th>
                                    <th>Month</th>
                                    <th>Requested</th>
                                    <th>Rejected</th>
                                    <th>Claimed</th>
                                    <th>Location</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($claims as $claim): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($claim['epf_number']); ?></code></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($claim['name']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($claim['gender']); ?>, Age: <?php echo $claim['age']; ?>
                                            </small>
                                        </td>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($claim['coverage_period']); ?></span><br><small class="text-muted">Feb 9, <?php echo explode('-', $claim['coverage_period'])[0]; ?> - Feb 8, <?php echo explode('-', $claim['coverage_period'])[1]; ?></small></td>
                                        <td><span class="month-badge"><?php echo htmlspecialchars($claim['month']); ?></span></td>
                                        <td class="amount-cell">Rs. <?php echo number_format($claim['requested_amount'], 2); ?></td>
                                        <td class="amount-cell">
                                            <?php if ($claim['rejected_amount'] > 0): ?>
                                                <span class="text-danger">Rs. <?php echo number_format($claim['rejected_amount'], 2); ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="amount-cell">
                                            <strong class="text-success">Rs. <?php echo number_format($claim['claimed_amount'], 2); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($claim['location_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($claim['created_at'])); ?>
                                            <br><small class="text-muted"><?php echo date('g:i A', strtotime($claim['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php if (in_array($user['account_type'], ['admin', 'superadmin'])): ?>
                                                <button class="btn btn-outline-primary btn-sm" onclick="editMedicalClaim(<?php echo htmlspecialchars(json_encode($claim)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm" onclick="deleteMedicalClaim(<?php echo $claim['id']; ?>, '<?php echo htmlspecialchars($claim['name']); ?>', '<?php echo htmlspecialchars($claim['month']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div>
                                Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $limit, $totalClaims)); ?> of <?php echo number_format($totalClaims); ?> claims
                            </div>
                            <nav>
                                <ul class="pagination mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>

                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    for ($p = $startPage; $p <= $endPage; $p++):
                                    ?>
                                        <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bulk Upload Modal -->
    <?php if (in_array($user['account_type'], ['admin', 'superadmin'])): ?>
    <div class="modal fade" id="bulkUploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Upload Medical Claims</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="bulk_upload" value="1">

                        <div class="mb-3">
                            <label for="csv_file" class="form-label">Select CSV File</label>
                            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                            <div class="form-text">
                                Upload a CSV file with columns: EPF Number, Coverage Period, Month, Requested Amount, Rejected Amount, Claimed Amount
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>CSV Format Requirements:</h6>
                            <ul class="mb-0">
                                <li>First row should contain headers</li>
                                <li>EPF numbers must exist in the system</li>
                                <li>Use exact month names as listed in the form</li>
                                <li>Amounts should be numeric (without commas)</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload me-2"></i>Upload Claims
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Medical Claim Modal -->
    <div class="modal fade" id="editMedicalClaimModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Medical Claim</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body" id="editMedicalClaimForm">
                        <!-- Form will be populated by JavaScript -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Claim
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Medical Claim Modal -->
    <div class="modal fade" id="deleteMedicalClaimModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this medical claim for <strong id="deleteMedicalClaimName"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="delete_medical_claim" value="1">
                        <input type="hidden" name="claim_id" id="deleteMedicalClaimId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function downloadTemplate() {
            // Create CSV template
            const template = [
                ['EPF Number', 'Coverage Period', 'Month', 'Requested Amount', 'Rejected Amount', 'Claimed Amount'],
                ['484', '2026-2027', 'March', '5000.00', '0.00', '5000.00'],
                ['443', '2026-2027', 'April', '3500.50', '500.50', '3000.00']
            ];

            let csvContent = template.map(row => row.join(',')).join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'medical_claims_template.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Auto-calculate claimed amount
        document.querySelector('input[name="requested_amount"]')?.addEventListener('input', function() {
            const requested = parseFloat(this.value) || 0;
            const rejected = parseFloat(document.querySelector('input[name="rejected_amount"]').value) || 0;
            const claimed = Math.max(0, requested - rejected);
            document.querySelector('input[name="claimed_amount"]').value = claimed.toFixed(2);
        });

        document.querySelector('input[name="rejected_amount"]')?.addEventListener('input', function() {
            const requested = parseFloat(document.querySelector('input[name="requested_amount"]').value) || 0;
            const rejected = parseFloat(this.value) || 0;
            const claimed = Math.max(0, requested - rejected);
            document.querySelector('input[name="claimed_amount"]').value = claimed.toFixed(2);
        });

        // Edit medical claim function
        function editMedicalClaim(claim) {
            const form = document.getElementById('editMedicalClaimForm');
            form.innerHTML = `
                <input type="hidden" name="update_medical_claim" value="1">
                <input type="hidden" name="claim_id" value="${claim.id}">

                <div class="mb-3">
                    <label class="form-label">Employee</label>
                    <input type="text" class="form-control" value="${claim.epf_number} - ${claim.name}" disabled>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Coverage Period</label>
                        <input type="text" class="form-control" value="${claim.coverage_period}" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Month</label>
                        <input type="text" class="form-control" value="${claim.month}" disabled>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Requested Amount (Rs.) *</label>
                        <input type="number" class="form-control" name="requested_amount" step="0.01" min="0" value="${claim.requested_amount}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Rejected Amount (Rs.)</label>
                        <input type="number" class="form-control" name="rejected_amount" step="0.01" min="0" value="${claim.rejected_amount}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Claimed Amount (Rs.) *</label>
                        <input type="number" class="form-control" name="claimed_amount" step="0.01" min="0" value="${claim.claimed_amount}" required>
                    </div>
                </div>
            `;

            new bootstrap.Modal(document.getElementById('editMedicalClaimModal')).show();
        }

        // Delete medical claim function
        function deleteMedicalClaim(claimId, employeeName, month) {
            document.getElementById('deleteMedicalClaimName').textContent = employeeName + ' - ' + month;
            document.getElementById('deleteMedicalClaimId').value = claimId;
            new bootstrap.Modal(document.getElementById('deleteMedicalClaimModal')).show();
        }
    </script>
</body>
</html>