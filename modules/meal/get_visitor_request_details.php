<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$db = new Database();

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">Invalid request</div>';
    exit;
}

$request_id = (int)$_GET['id'];

try {
    $query = "SELECT * FROM meal_requests_visitor WHERE id = ?";
    $request = $db->fetch($query, [$request_id]);

    if (!$request) {
        echo '<div class="alert alert-danger">Request not found</div>';
        exit;
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading request details</div>';
    exit;
}
?>

<div class="row">
    <div class="col-md-6">
        <h6>Request Information</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Request ID:</strong></td>
                <td><?php echo htmlspecialchars($request['id']); ?></td>
            </tr>
            <tr>
                <td><strong>Request Date:</strong></td>
                <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td>
                    <?php
                    $status_class = '';
                    switch ($request['status']) {
                        case 'pending': $status_class = 'bg-warning'; break;
                        case 'approved': $status_class = 'bg-success'; break;
                        case 'rejected': $status_class = 'bg-danger'; break;
                    }
                    ?>
                    <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($request['status']); ?></span>
                </td>
            </tr>
            <tr>
                <td><strong>Created:</strong></td>
                <td><?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?></td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6>Requesting Employee</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>EPF Number:</strong></td>
                <td><?php echo htmlspecialchars($request['requesting_emp_number']); ?></td>
            </tr>
            <tr>
                <td><strong>Name:</strong></td>
                <td><?php echo htmlspecialchars($request['requesting_emp_name']); ?></td>
            </tr>
            <tr>
                <td><strong>Location:</strong></td>
                <td><?php echo htmlspecialchars($request['requesting_emp_location']); ?></td>
            </tr>
        </table>
    </div>
</div>

<hr>

<div class="row mb-4">
    <div class="col-12">
        <h6>Visitor Information</h6>
        <div class="card bg-light">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Visitor Names:</strong><br>
                        <span class="text-primary"><?php echo htmlspecialchars($request['visitor_names'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="col-md-4">
                        <strong>Visit Purpose:</strong><br>
                        <span class="text-info"><?php echo htmlspecialchars($request['visit_purpose'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="col-md-4">
                        <strong>Visitor Remarks:</strong><br>
                        <span class="text-muted"><?php echo htmlspecialchars($request['visitor_remarks'] ?: 'None'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<hr>

<h6>Meal Details</h6>

<?php if ($request['breakfast_needed']): ?>
<div class="card mb-3">
    <div class="card-header bg-warning text-dark">
        <h6 class="mb-0"><i class="fas fa-coffee me-2"></i>Breakfast</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <strong>Menu:</strong> <?php echo htmlspecialchars($request['breakfast_menu']); ?>
            </div>
            <div class="col-md-4">
                <strong>Count:</strong> <?php echo number_format($request['breakfast_count']); ?>
            </div>
            <div class="col-md-4">
                <strong>Remarks:</strong> <?php echo htmlspecialchars($request['breakfast_remarks'] ?: 'None'); ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($request['lunch_needed']): ?>
<div class="card mb-3">
    <div class="card-header bg-success text-white">
        <h6 class="mb-0"><i class="fas fa-bowl-food me-2"></i>Lunch</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <strong>Menu:</strong> <?php echo htmlspecialchars($request['lunch_menu']); ?>
            </div>
            <div class="col-md-4">
                <strong>Count:</strong> <?php echo number_format($request['lunch_count']); ?>
            </div>
            <div class="col-md-4">
                <strong>Remarks:</strong> <?php echo htmlspecialchars($request['lunch_remarks'] ?: 'None'); ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($request['dinner_needed']): ?>
<div class="card mb-3">
    <div class="card-header bg-primary text-white">
        <h6 class="mb-0"><i class="fas fa-utensils me-2"></i>Dinner</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <strong>Menu:</strong> <?php echo htmlspecialchars($request['dinner_menu']); ?>
            </div>
            <div class="col-md-4">
                <strong>Count:</strong> <?php echo number_format($request['dinner_count']); ?>
            </div>
            <div class="col-md-4">
                <strong>Remarks:</strong> <?php echo htmlspecialchars($request['dinner_remarks'] ?: 'None'); ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($request['snack1_needed']): ?>
<div class="card mb-3">
    <div class="card-header bg-info text-white">
        <h6 class="mb-0"><i class="fas fa-cookie me-2"></i>Snack 1</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <strong>Count:</strong> <?php echo number_format($request['snack1_count']); ?>
            </div>
            <div class="col-md-6">
                <strong>Remarks:</strong> <?php echo htmlspecialchars($request['snack1_remarks'] ?: 'None'); ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($request['snack2_needed']): ?>
<div class="card mb-3">
    <div class="card-header bg-secondary text-white">
        <h6 class="mb-0"><i class="fas fa-cookie-bite me-2"></i>Snack 2</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <strong>Count:</strong> <?php echo number_format($request['snack2_count']); ?>
            </div>
            <div class="col-md-6">
                <strong>Remarks:</strong> <?php echo htmlspecialchars($request['snack2_remarks'] ?: 'None'); ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>