<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Function to search employees from the main employees table
function searchEmployees($db, $search_term) {
    $results = [];

    try {
        // Search in the main employees table (linked with locations and users)
        $query = "
            SELECT e.*, l.location_name, u.email, u.username
            FROM employees e
            LEFT JOIN locations l ON e.location_id = l.id
            LEFT JOIN users u ON e.id = u.employee_id
            WHERE (e.epf_number LIKE ? OR e.name LIKE ? OR e.nic LIKE ? OR u.email LIKE ?)
            AND e.is_active = 1
            ORDER BY e.name ASC
            LIMIT 20
        ";

        $search_pattern = "%$search_term%";
        $employees = $db->fetchAll($query, [$search_pattern, $search_pattern, $search_pattern, $search_pattern]);

        foreach ($employees as $employee) {
            // Calculate experience from joined_date
            $experience_years = 1; // Default
            if (!empty($employee['joined_date'])) {
                $joined_date = new DateTime($employee['joined_date']);
                $today = new DateTime();
                $service_diff = $today->diff($joined_date);
                $experience_years = max(1, $service_diff->y); // At least 1 year
            }

            $result = [
                'id' => $employee['id'],
                'emp_number' => $employee['epf_number'],
                'name' => $employee['name'],
                'email' => $employee['email'] ?: $employee['nic'], // Email from users table, fallback to NIC
                'location' => $employee['location_name'] ?: $employee['department'],
                'phone' => 'N/A', // No phone columns available
                'designation' => $employee['designation'],
                'department' => $employee['department'],
                'experience' => $experience_years,
                'date_of_joining' => $employee['joined_date'],
                'salary' => null, // No salary column in employees table
                'source_table' => 'employees'
            ];

            $results[] = $result;
        }

    } catch (Exception $e) {
        // If employees table fails, try fallback approach
        error_log("Employee search error: " . $e->getMessage());
    }

    return $results;
}

$message = '';
$search_results = [];

// Handle search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search') {
    $search_term = trim($_POST['search_term']);

    if (!empty($search_term)) {
        $search_results = searchEmployees($db, $search_term);

        if (empty($search_results)) {
            $message = "No employees found matching '$search_term'";
        } else {
            $message = "Found " . count($search_results) . " employee(s) matching '$search_term'";
        }
    } else {
        $message = "Please enter a search term";
    }
}

// Handle manual driver creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_driver') {
    $emp_number = trim($_POST['emp_number']);
    $emp_name = trim($_POST['emp_name']);
    $license_number = trim($_POST['license_number']);
    $license_type = trim($_POST['license_type']);
    $license_expiry = $_POST['license_expiry'] ?? null;
    $experience_years = intval($_POST['experience_years']);
    $status = $_POST['status'] ?? 'active';
    $location = trim($_POST['location']);
    $phone = trim($_POST['phone']);
    $notes = trim($_POST['notes']);

    if (!empty($emp_number) && !empty($emp_name) && !empty($license_number)) {
        try {
            // Get employee data to link and calculate correct experience
            $employee = $db->fetch("SELECT * FROM employees WHERE epf_number = ?", [$emp_number]);
            $emp_id = null;
            $calculated_experience = $experience_years;

            if ($employee) {
                $emp_id = $employee['id'];

                // Calculate correct experience from joined_date
                if (!empty($employee['joined_date'])) {
                    $joined_date = new DateTime($employee['joined_date']);
                    $today = new DateTime();
                    $service_diff = $today->diff($joined_date);
                    $calculated_experience = max(1, $service_diff->y);
                }
            }

            // Check if driver already exists
            $existing = $db->fetch("SELECT id FROM drivers WHERE emp_number = ? OR license_number = ?", [$emp_number, $license_number]);

            if (!$existing) {
                $query = "INSERT INTO drivers (
                    emp_id, emp_number, emp_name, license_number, license_type, license_expiry,
                    experience_years, status, location, phone, notes, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                $db->execute($query, [
                    $emp_id, $emp_number, $emp_name, $license_number, $license_type, $license_expiry,
                    $calculated_experience, $status, $location, $phone, $notes, $user['id']
                ]);

                $experience_message = ($calculated_experience != $experience_years) ?
                    " (Experience corrected from {$experience_years} to {$calculated_experience} years based on service record)" : "";

                $message = "Driver '$emp_name' added successfully!{$experience_message}";
            } else {
                $message = "Error: Driver with EPF '$emp_number' or License '$license_number' already exists.";
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Error: Please fill in EPF Number, Name, and License Number.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Search & Add Drivers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-user-plus me-2"></i>Search & Add Drivers</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Transport Management</a></li>
                            <li class="breadcrumb-item"><a href="driver_pool.php">Driver Pool</a></li>
                            <li class="breadcrumb-item active">Search & Add Drivers</li>
                        </ol>
                    </nav>
                </div>
                <div class="action-buttons">
                    <a href="check_users_table.php" class="btn btn-info">
                        <i class="fas fa-database me-2"></i>Check Tables
                    </a>
                    <a href="driver_pool.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Driver Pool
                    </a>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo strpos($message, 'Error') !== false ? 'danger' : 'success'; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Search Section -->
            <div class="col-lg-8">
                <div class="card border-0 shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search Existing Employees</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="search">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <input type="text" class="form-control form-control-lg" name="search_term"
                                           placeholder="Enter EPF Number, Name, NIC, or Email..." value="<?php echo htmlspecialchars($_POST['search_term'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-search me-2"></i>Search
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php if (!empty($search_results)): ?>
                        <hr>
                        <h6>Search Results - Click "Add as Driver" to auto-fill the form</h6>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>EPF Number</th>
                                        <th>Name</th>
                                        <th>Location</th>
                                        <th>Email/NIC</th>
                                        <th>Experience</th>
                                        <th>Department</th>
                                        <th>Designation</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($search_results as $employee): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($employee['emp_number'] ?? $employee['id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($employee['name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($employee['location'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($employee['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($employee['experience'] ?? '1'); ?> years</td>
                                        <td><?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($employee['designation'] ?? 'N/A'); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-success" onclick="fillDriverForm(
                                                '<?php echo htmlspecialchars($employee['emp_number'] ?? $employee['id']); ?>',
                                                '<?php echo htmlspecialchars($employee['name'] ?? ''); ?>',
                                                '<?php echo htmlspecialchars($employee['location'] ?? ''); ?>',
                                                '<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>',
                                                '<?php echo htmlspecialchars($employee['experience'] ?? '1'); ?>'
                                            )">
                                                <i class="fas fa-plus"></i> Add as Driver
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

            <!-- Manual Driver Creation -->
            <div class="col-lg-4">
                <div class="card border-0 shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Add Driver Manually</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="driverForm">
                            <input type="hidden" name="action" value="create_driver">

                            <div class="mb-3">
                                <label class="form-label">EPF Number * <small class="text-muted">(auto-filled from 2.2.1)</small></label>
                                <input type="text" class="form-control" name="emp_number" id="emp_number" required
                                       placeholder="Enter EPF Number">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Full Name * <small class="text-muted">(auto-filled from 2.2.1)</small></label>
                                <input type="text" class="form-control" name="emp_name" id="emp_name" required
                                       placeholder="Enter Full Name">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">License Number * <small class="text-muted">(manual input)</small></label>
                                <input type="text" class="form-control" name="license_number" required
                                       placeholder="DL123456">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">License Type *</label>
                                <select class="form-select" name="license_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Light Vehicle">Light Vehicle</option>
                                    <option value="Heavy Vehicle">Heavy Vehicle</option>
                                    <option value="Both Heavy & Light Vehicle">Both Heavy & Light Vehicle</option>
                                    <option value="Motorcycle">Motorcycle</option>
                                    <option value="Bus">Bus</option>
                                    <option value="Truck">Truck</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">License Expiry</label>
                                <input type="date" class="form-control" name="license_expiry"
                                       value="<?php echo date('Y-m-d', strtotime('+3 years')); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Experience (Years) <small class="text-muted">(auto-filled from 2.2.1)</small></label>
                                <input type="number" class="form-control" name="experience_years" id="experience_years" min="0" max="50" value="1">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Status *</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Location <small class="text-muted">(auto-filled from 2.2.1)</small></label>
                                <select class="form-select" name="location" id="location">
                                    <option value="Head Office">Head Office</option>
                                    <option value="7C">7C</option>
                                    <option value="Pannala">Pannala</option>
                                    <option value="Kobeigane">Kobeigane</option>
                                    <option value="JECOE">JECOE</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Phone Number <small class="text-muted">(manual input)</small></label>
                                <input type="tel" class="form-control" name="phone" id="phone" placeholder="0771234567">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="2" placeholder="Any additional notes about the driver..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-save me-2"></i>Add Driver
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fillDriverForm(empNumber, name, location, phone, experience) {
            // Auto-fill EPF Number (from 2.2.1)
            document.getElementById('emp_number').value = empNumber || '';

            // Auto-fill Full Name (from 2.2.1)
            document.getElementById('emp_name').value = name || '';

            // Auto-fill Location (from 2.2.1)
            if (location && location !== 'N/A' && location !== '') {
                const locationSelect = document.getElementById('location');
                // Check if the location exists in the dropdown
                for (let option of locationSelect.options) {
                    if (option.value === location) {
                        locationSelect.value = location;
                        break;
                    }
                }
            }

            // Auto-fill Phone Number if available (from 2.2.1)
            if (phone && phone !== 'N/A' && phone !== '') {
                document.getElementById('phone').value = phone;
            }

            // Auto-fill Experience (from 2.2.1)
            if (experience && experience !== 'N/A' && experience !== '') {
                document.getElementById('experience_years').value = experience;
            }

            // Show success message
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show mt-3';
            alertDiv.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>
                Employee data auto-filled from section 2.2.1. Please add <strong>License Number</strong> and select <strong>License Type</strong> to complete.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            // Insert the alert before the form
            const form = document.getElementById('driverForm');
            form.parentNode.insertBefore(alertDiv, form);

            // Scroll to the form
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });

            // Focus on the license number field
            setTimeout(() => {
                document.querySelector('input[name="license_number"]').focus();
            }, 500);
        }

        // Clear any existing alerts when form is submitted
        document.getElementById('driverForm').addEventListener('submit', function() {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => alert.remove());
        });
    </script>
</body>
</html>