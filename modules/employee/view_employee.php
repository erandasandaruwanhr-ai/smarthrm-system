<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to access employee module
$db = new Database();
if (!hasModulePermission($db, 'employee.view') &&
    !hasModulePermission($db, 'employee.view_own') &&
    !hasModulePermission($db, 'employee.view_all') &&
    !hasAdminAccess()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();

$employee_id = $_GET['id'] ?? null;

if ($employee_id) {
    // Get employee with related information
    $employee = $db->fetch("
        SELECT e.*,
               l.location_name,
               el.level_name as employment_level_name,
               supervisor.name as supervisor_name,
               supervisor.designation as supervisor_designation
        FROM employees e
        LEFT JOIN locations l ON e.location_id = l.id
        LEFT JOIN employment_levels el ON e.employment_level_id = el.id
        LEFT JOIN employees supervisor ON e.reports_to = supervisor.epf_number
        WHERE e.id = ?
    ", [$employee_id]);

    if ($employee) {
        // Get direct reports
        $direct_reports = $db->fetchAll("
            SELECT epf_number, name, designation
            FROM employees
            WHERE reports_to = ? AND is_active = 1
            ORDER BY name
        ", [$employee['epf_number']]);

        // Calculate exact age and service
        $birthday = new DateTime($employee['birthday']);
        $joined_date = new DateTime($employee['joined_date']);
        $today = new DateTime();

        $age_diff = $today->diff($birthday);
        $service_diff = $today->diff($joined_date);
        ?>
        <div class="row">
            <div class="col-md-4 text-center">
                <div class="employee-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                    <?php echo strtoupper(substr($employee['name'], 0, 1)); ?>
                </div>
                <h5><?php echo htmlspecialchars($employee['name']); ?></h5>
                <p class="text-muted"><?php echo htmlspecialchars($employee['designation']); ?></p>
                <span class="badge bg-<?php echo $employee['is_active'] ? 'success' : 'danger'; ?> mb-3">
                    <?php echo $employee['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
            <div class="col-md-8">
                <h6 class="border-bottom pb-2 mb-3">Personal Information</h6>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>EPF Number:</strong></div>
                    <div class="col-sm-8"><code><?php echo htmlspecialchars($employee['epf_number']); ?></code></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>NIC Number:</strong></div>
                    <div class="col-sm-8"><?php echo htmlspecialchars($employee['nic']); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Birthday:</strong></div>
                    <div class="col-sm-8">
                        <?php echo htmlspecialchars($employee['birthday']); ?>
                        <small class="text-muted">
                            (<?php echo date('F j, Y', strtotime($employee['birthday'])); ?> - <?php echo $age_diff->y; ?> years, <?php echo $age_diff->m; ?> months old)
                        </small>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Gender:</strong></div>
                    <div class="col-sm-8"><?php echo htmlspecialchars($employee['gender']); ?></div>
                </div>

                <h6 class="border-bottom pb-2 mb-3 mt-4">Employment Information</h6>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Department:</strong></div>
                    <div class="col-sm-8">
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($employee['department']); ?></span>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Employment Level:</strong></div>
                    <div class="col-sm-8">
                        <span class="badge bg-info"><?php echo htmlspecialchars($employee['employment_level_name']); ?></span>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Location:</strong></div>
                    <div class="col-sm-8">
                        <i class="fas fa-map-marker-alt text-primary me-1"></i>
                        <?php echo htmlspecialchars($employee['location_name']); ?>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Joined Date:</strong></div>
                    <div class="col-sm-8">
                        <?php echo htmlspecialchars($employee['joined_date']); ?>
                        <small class="text-muted">
                            (<?php echo date('F j, Y', strtotime($employee['joined_date'])); ?> - <?php echo $service_diff->y; ?> years, <?php echo $service_diff->m; ?> months service)
                        </small>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Reports To:</strong></div>
                    <div class="col-sm-8">
                        <?php if ($employee['supervisor_name']): ?>
                            <?php echo htmlspecialchars($employee['supervisor_name']); ?>
                            <small class="text-muted">(<?php echo htmlspecialchars($employee['supervisor_designation']); ?>)</small>
                        <?php else: ?>
                            <span class="text-muted">No reporting manager</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($direct_reports)): ?>
                <h6 class="border-bottom pb-2 mb-3 mt-4">Direct Reports</h6>
                <?php foreach ($direct_reports as $report): ?>
                    <div class="row mb-2">
                        <div class="col-sm-4"><?php echo htmlspecialchars($report['epf_number']); ?></div>
                        <div class="col-sm-8">
                            <?php echo htmlspecialchars($report['name']); ?>
                            <small class="text-muted">(<?php echo htmlspecialchars($report['designation']); ?>)</small>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <div class="mt-4">
                    <a href="employee_form.php?id=<?php echo $employee['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit me-1"></i>Edit Employee
                    </a>
                </div>
            </div>
        </div>
        <?php
    } else {
        echo '<div class="alert alert-danger">Employee not found.</div>';
    }
} else {
    echo '<div class="alert alert-danger">Invalid employee ID.</div>';
}
?>