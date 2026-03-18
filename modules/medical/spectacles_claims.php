<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($db, 'medical.spectacles_claims')) {
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
    try {
        if (isset($_POST['submit_spectacles_claim'])) {
            $epf_number = $_POST['epf_number'];
            $coverage_period = $_POST['coverage_period'];
            $purchase_date = $_POST['purchase_date'];
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

            if (empty($purchase_date)) {
                throw new Exception("Purchase date is required.");
            }

            // Validate purchase date
            $purchaseDateTime = strtotime($purchase_date);
            if (!$purchaseDateTime) {
                throw new Exception("Invalid purchase date format.");
            }

            // Extract start year from coverage period
            $startYear = explode('-', $coverage_period)[0];

            // Insert spectacles claim
            $db->insert('spectacles_claims', [
                'epf_number' => $epf_number,
                'year' => $startYear,
                'coverage_period' => $coverage_period,
                'purchase_date' => $purchase_date,
                'requested_amount' => $requested_amount,
                'rejected_amount' => $rejected_amount,
                'claimed_amount' => $claimed_amount
            ]);

            $success_message = "Spectacles claim submitted successfully for " . $epf_number . " - Rs. " . number_format($claimed_amount, 2);

        } elseif (isset($_POST['update_spectacles_claim'])) {
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

            // Update spectacles claim
            $db->update('spectacles_claims', [
                'requested_amount' => $requested_amount,
                'rejected_amount' => $rejected_amount,
                'claimed_amount' => $claimed_amount,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$claim_id]);

            $success_message = "Spectacles claim updated successfully - Rs. " . number_format($claimed_amount, 2);

        } elseif (isset($_POST['delete_spectacles_claim'])) {
            $claim_id = $_POST['claim_id'];
            $db->delete('spectacles_claims', 'id = ?', [$claim_id]);
            $success_message = "Spectacles claim deleted successfully.";
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get employees based on user role
$employees = [];
$claimsQuery = "";
$claimsParams = [];

if ($user['account_type'] === 'superadmin') {
    // Superadmin sees all employees
    $employees = $db->fetchAll("SELECT epf_number, name, gender, age, l.location_name FROM employees e LEFT JOIN locations l ON e.location_id = l.id WHERE e.is_active = 1 ORDER BY e.name");
    $claimsQuery = "SELECT sc.*, e.name, e.gender, e.age, l.location_name FROM spectacles_claims sc JOIN employees e ON sc.epf_number = e.epf_number LEFT JOIN locations l ON e.location_id = l.id ORDER BY sc.coverage_period DESC, sc.purchase_date DESC";
} elseif ($user['account_type'] === 'admin') {
    // Admin sees employees from their location
    $employees = $db->fetchAll("SELECT e.epf_number, e.name, e.gender, e.age, l.location_name FROM employees e JOIN locations l ON e.location_id = l.id WHERE l.location_name = ? AND e.is_active = 1 ORDER BY e.name", [$user['location']]);
    $claimsQuery = "SELECT sc.*, e.name, e.gender, e.age, l.location_name FROM spectacles_claims sc JOIN employees e ON sc.epf_number = e.epf_number LEFT JOIN locations l ON e.location_id = l.id WHERE l.location_name = ? ORDER BY sc.coverage_period DESC, sc.purchase_date DESC";
    $claimsParams = [$user['location']];
} else {
    // Regular user sees only their own data
    $employees = $db->fetchAll("SELECT epf_number, name, gender, age, l.location_name FROM employees e LEFT JOIN locations l ON e.location_id = l.id WHERE e.epf_number = ?", [$user['epf_number']]);
    $claimsQuery = "SELECT sc.*, e.name, e.gender, e.age, l.location_name FROM spectacles_claims sc JOIN employees e ON sc.epf_number = e.epf_number LEFT JOIN locations l ON e.location_id = l.id WHERE sc.epf_number = ? ORDER BY sc.coverage_period DESC, sc.purchase_date DESC";
    $claimsParams = [$user['epf_number']];
}

// Get spectacles claims with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$totalClaims = 0;
$claims = [];

try {
    // Get total count
    $countQuery = str_replace("SELECT sc.*, e.name, e.gender, e.age, l.location_name FROM", "SELECT COUNT(*) as total FROM", $claimsQuery);
    $totalResult = $db->fetch($countQuery, $claimsParams);
    $totalClaims = $totalResult['total'];

    // Get claims with limit
    $claims = $db->fetchAll($claimsQuery . " LIMIT $limit OFFSET $offset", $claimsParams);
} catch (Exception $e) {
    error_log("Spectacles claims query error: " . $e->getMessage());
}

$totalPages = ceil($totalClaims / $limit);

// Calculate statistics
$totalSpectaclesClaimed = 0;
$currentYearClaimed = 0;
try {
    $totalStats = $db->fetch($claimsQuery ? str_replace("SELECT sc.*, e.name, e.gender, e.age, l.location_name FROM", "SELECT SUM(sc.claimed_amount) as total FROM", str_replace(" ORDER BY sc.coverage_period DESC, sc.purchase_date DESC", "", $claimsQuery)) : "SELECT 0 as total", $claimsParams);
    $totalSpectaclesClaimed = $totalStats['total'] ?? 0;

    $currentYearStats = $db->fetch($claimsQuery ? str_replace("SELECT sc.*, e.name, e.gender, e.age, l.location_name FROM", "SELECT SUM(sc.claimed_amount) as total FROM", str_replace(" ORDER BY sc.coverage_period DESC, sc.purchase_date DESC", " AND sc.coverage_period = ?", $claimsQuery)) : "SELECT 0 as total", array_merge($claimsParams, [$currentCoveragePeriod]));
    $currentYearClaimed = $currentYearStats['total'] ?? 0;
} catch (Exception $e) {
    error_log("Spectacles statistics error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Spectacles Claims</title>
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
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            border-left: 4px solid;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .stats-card.info { border-left-color: var(--info-color); }
        .stats-card.success { border-left-color: var(--success-color); }
        .stats-card.primary { border-left-color: var(--primary-color); }

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

        .spectacles-header {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .amount-cell {
            text-align: right;
            font-family: monospace;
        }

        .purchase-date {
            color: #495057;
            font-weight: 500;
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
                        <h1><i class="fas fa-glasses me-3"></i>Spectacles Claims</h1>
                        <p>Submit claims for optical expenses and eyewear reimbursements</p>
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
                    <li class="breadcrumb-item active">Spectacles Claims</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4 mb-0">Claims Management</h2>
                <div>
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

            <!-- Coverage Info -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6><i class="fas fa-info-circle me-2"></i>Spectacles Claims Information</h6>
                                <p class="mb-0">
                                    <strong>Separate from medical insurance</strong> - Dedicated spectacles purchase claims system |
                                    Coverage: <strong>Feb 9, <?php echo $currentYear; ?> - Feb 8, <?php echo $currentYear + 1; ?></strong>
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <span class="badge bg-primary px-3 py-2">Year <?php echo $currentYear; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="stats-card info">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-info bg-opacity-10 text-info rounded-circle p-3">
                                    <i class="fas fa-glasses fa-lg"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4 class="fw-bold mb-0">Rs. <?php echo number_format($currentYearClaimed, 2); ?></h4>
                                <p class="text-muted mb-0">Current Year Claims</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="stats-card success">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-success bg-opacity-10 text-success rounded-circle p-3">
                                    <i class="fas fa-chart-line fa-lg"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4 class="fw-bold mb-0">Rs. <?php echo number_format($totalSpectaclesClaimed, 2); ?></h4>
                                <p class="text-muted mb-0">Total Claims (All Years)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="stats-card primary">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                                    <i class="fas fa-file-medical fa-lg"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4 class="fw-bold mb-0"><?php echo number_format($totalClaims); ?></h4>
                                <p class="text-muted mb-0">Total Claims Records</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (in_array($user['account_type'], ['admin', 'superadmin'])): ?>
            <!-- Spectacles Claim Entry Form -->
            <div class="form-card">
                <h4 class="mb-4"><i class="fas fa-plus me-2"></i>Submit Spectacles Claim</h4>
                <form method="post" class="row g-3">
                    <input type="hidden" name="submit_spectacles_claim" value="1">

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
                        <label for="purchase_date" class="form-label">Purchase Date *</label>
                        <input type="date" class="form-control" name="purchase_date" required>
                        <div class="form-text">Invoice date of spectacles purchase</div>
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
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-glasses me-2"></i>Submit Spectacles Claim
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-2"></i>Reset
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Spectacles Claims Table -->
            <div class="table-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4><i class="fas fa-table me-2"></i>Spectacles Claims Records</h4>
                    <?php if ($totalClaims > 0): ?>
                        <span class="badge bg-info"><?php echo number_format($totalClaims); ?> Claims</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($claims)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-glasses fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Spectacles Claims Found</h5>
                        <p class="text-muted">
                            <?php if ($user['account_type'] !== 'user'): ?>
                                Start by submitting spectacles claims for employees.
                            <?php else: ?>
                                No spectacles claims have been submitted for your account yet.
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
                                    <th>Purchase Date *</th>
                                    <th>Requested</th>
                                    <th>Rejected</th>
                                    <th>Claimed</th>
                                    <th>Location</th>
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
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($claim['coverage_period']); ?></span>
                                            <br><small class="text-muted">Feb 9, <?php echo explode('-', $claim['coverage_period'])[0]; ?> - Feb 8, <?php echo explode('-', $claim['coverage_period'])[1]; ?></small>
                                        </td>
                                        <td class="purchase-date">
                                            <strong class="text-success"><?php echo date('M j, Y', strtotime($claim['purchase_date'])); ?></strong>
                                            <br><small class="text-muted">Purchase Invoice Date</small>
                                        </td>
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
                                            <?php if (in_array($user['account_type'], ['admin', 'superadmin'])): ?>
                                                <button class="btn btn-outline-primary btn-sm" onclick="editClaim(<?php echo htmlspecialchars(json_encode($claim)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm" onclick="deleteClaim(<?php echo $claim['id']; ?>, '<?php echo htmlspecialchars($claim['name']); ?>')">
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

    <!-- Edit Claim Modal -->
    <?php if (in_array($user['account_type'], ['admin', 'superadmin'])): ?>
    <div class="modal fade" id="editClaimModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Spectacles Claim</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body" id="editClaimForm">
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteClaimModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this spectacles claim for <strong id="deleteClaimName"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="delete_spectacles_claim" value="1">
                        <input type="hidden" name="claim_id" id="deleteClaimId">
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
        <?php if (in_array($user['account_type'], ['admin', 'superadmin'])): ?>
        function editClaim(claim) {
            const form = document.getElementById('editClaimForm');
            form.innerHTML = `
                <input type="hidden" name="update_spectacles_claim" value="1">
                <input type="hidden" name="claim_id" value="${claim.id}">

                <div class="mb-3">
                    <label class="form-label">Employee</label>
                    <input type="text" class="form-control" value="${claim.epf_number} - ${claim.name}" disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label">Coverage Period</label>
                    <input type="text" class="form-control" value="${claim.coverage_period} (Feb 9, ${claim.coverage_period.split('-')[0]} - Feb 8, ${claim.coverage_period.split('-')[1]})" disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label"><strong>Purchase Date *</strong></label>
                    <input type="text" class="form-control text-success fw-bold" value="${claim.purchase_date}" disabled>
                    <small class="text-muted">Invoice date of spectacles purchase</small>
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

            new bootstrap.Modal(document.getElementById('editClaimModal')).show();
        }

        function deleteClaim(claimId, employeeName) {
            document.getElementById('deleteClaimName').textContent = employeeName;
            document.getElementById('deleteClaimId').value = claimId;
            new bootstrap.Modal(document.getElementById('deleteClaimModal')).show();
        }
        <?php endif; ?>

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
    </script>
</body>
</html>