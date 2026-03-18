<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

$message = '';
$search_results = [];

// Handle search and import
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'search') {
        $search_term = trim($_POST['search_term']);

        if (!empty($search_term)) {
            try {
                // Search in users table (assuming employees are stored there)
                $search_query = "SELECT id, username, name, email, location
                               FROM users
                               WHERE username LIKE ? OR name LIKE ? OR email LIKE ?
                               LIMIT 20";

                $search_pattern = "%$search_term%";
                $search_results = $db->fetchAll($search_query, [$search_pattern, $search_pattern, $search_pattern]);

                if (empty($search_results)) {
                    $message = "No employees found matching '$search_term'";
                }
            } catch (Exception $e) {
                $message = "Search error: " . $e->getMessage();
            }
        } else {
            $message = "Please enter a search term (EPF number, name, or email)";
        }
    } elseif ($action === 'import') {
        $employee_ids = $_POST['employee_ids'] ?? [];
        $license_numbers = $_POST['license_numbers'] ?? [];
        $license_types = $_POST['license_types'] ?? [];
        $experience_years = $_POST['experience_years'] ?? [];

        $imported_count = 0;
        $errors = [];

        foreach ($employee_ids as $emp_id) {
            if (!empty($license_numbers[$emp_id]) && !empty($license_types[$emp_id])) {
                try {
                    // Get employee details
                    $employee = $db->fetch("SELECT * FROM users WHERE id = ?", [$emp_id]);

                    if ($employee) {
                        // Check if driver already exists
                        $existing = $db->fetch("SELECT id FROM drivers WHERE emp_id = ? OR license_number = ?",
                                             [$emp_id, $license_numbers[$emp_id]]);

                        if (!$existing) {
                            // Insert as driver
                            $query = "INSERT INTO drivers (
                                emp_id, emp_number, emp_name, license_number, license_type,
                                experience_years, status, location, created_by, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW())";

                            $db->execute($query, [
                                $emp_id,
                                $employee['username'] ?? 'N/A',
                                $employee['name'],
                                $license_numbers[$emp_id],
                                $license_types[$emp_id],
                                intval($experience_years[$emp_id] ?? 0),
                                $employee['location'] ?? 'Head Office',
                                $user['id']
                            ]);

                            $imported_count++;
                        } else {
                            $errors[] = "Driver already exists: " . $employee['name'];
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = "Error importing " . $employee['name'] . ": " . $e->getMessage();
                }
            }
        }

        if ($imported_count > 0) {
            $message = "Successfully imported $imported_count driver(s)!";
        }
        if (!empty($errors)) {
            $message .= " Errors: " . implode(", ", $errors);
        }

        // Clear search results after import
        $search_results = [];
    }
}

// Get existing drivers
$existing_drivers = $db->fetchAll("SELECT emp_id, emp_name, license_number FROM drivers ORDER BY emp_name");

$license_types = ['Light Vehicle', 'Heavy Vehicle', 'Motorcycle', 'Bus', 'Truck'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Import Drivers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-user-plus me-2"></i>Import Drivers</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Transport Management</a></li>
                            <li class="breadcrumb-item"><a href="driver_pool.php">Driver Pool</a></li>
                            <li class="breadcrumb-item active">Import Drivers</li>
                        </ol>
                    </nav>
                </div>
                <div class="action-buttons">
                    <a href="driver_pool.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Driver Pool
                    </a>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo strpos($message, 'Error') !== false ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Search Section -->
            <div class="col-lg-6">
                <div class="card border-0 shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search Employees</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="search">

                            <div class="mb-3">
                                <label class="form-label">Search by EPF Number, Name, or Email</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search_term"
                                           placeholder="Enter EPF number, name, or email..." required>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Search Results -->
                        <?php if (!empty($search_results)): ?>
                        <hr>
                        <h6>Search Results (<?php echo count($search_results); ?> found)</h6>

                        <form method="POST" id="importForm">
                            <input type="hidden" name="action" value="import">

                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Select</th>
                                            <th>EPF/Username</th>
                                            <th>Name</th>
                                            <th>Location</th>
                                            <th>License Number*</th>
                                            <th>License Type*</th>
                                            <th>Experience</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($search_results as $employee): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="employee_ids[]" value="<?php echo $employee['id']; ?>"
                                                       onchange="toggleRow(this)">
                                            </td>
                                            <td><?php echo htmlspecialchars($employee['username']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['location'] ?? 'N/A'); ?></td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm"
                                                       name="license_numbers[<?php echo $employee['id']; ?>]"
                                                       placeholder="DL123456" required disabled>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm"
                                                        name="license_types[<?php echo $employee['id']; ?>]" required disabled>
                                                    <option value="">Select Type</option>
                                                    <?php foreach ($license_types as $type): ?>
                                                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm"
                                                       name="experience_years[<?php echo $employee['id']; ?>]"
                                                       min="0" max="50" value="1" disabled>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-download me-2"></i>Import Selected as Drivers
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Current Drivers -->
            <div class="col-lg-6">
                <div class="card border-0 shadow">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Current Drivers (<?php echo count($existing_drivers); ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-sm mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Name</th>
                                        <th>EPF</th>
                                        <th>License</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($existing_drivers)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-3">
                                            <i class="fas fa-user-tie fa-2x text-muted mb-2 d-block"></i>
                                            <span class="text-muted">No drivers yet</span>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($existing_drivers as $driver): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($driver['emp_name']); ?></td>
                                            <td><?php echo htmlspecialchars($driver['emp_id'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($driver['license_number']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleRow(checkbox) {
            const row = checkbox.closest('tr');
            const inputs = row.querySelectorAll('input[type="text"], input[type="number"], select');

            inputs.forEach(input => {
                input.disabled = !checkbox.checked;
                if (!checkbox.checked) {
                    input.value = input.tagName === 'SELECT' ? '' : (input.type === 'number' ? '1' : '');
                }
            });
        }

        // Validate form before submit
        document.getElementById('importForm')?.addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('input[name="employee_ids[]"]:checked');

            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one employee to import as a driver.');
                return false;
            }

            // Validate required fields for selected employees
            let hasErrors = false;
            checkedBoxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const licenseNumber = row.querySelector('input[type="text"]');
                const licenseType = row.querySelector('select');

                if (!licenseNumber.value.trim() || !licenseType.value) {
                    hasErrors = true;
                }
            });

            if (hasErrors) {
                e.preventDefault();
                alert('Please fill in License Number and License Type for all selected employees.');
                return false;
            }
        });
    </script>
</body>
</html>