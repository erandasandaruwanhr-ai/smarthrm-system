<?php
require_once '../../../config/config.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/location_access.php';

header('Content-Type: application/json');

$user = getCurrentUser();
$db = new Database();

// Check module permissions
if (!isSuperAdmin() && !hasModulePermission($db, 'training.view')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get requirement ID
$requirement_id = $_GET['id'] ?? '';

if (empty($requirement_id)) {
    echo json_encode(['success' => false, 'message' => 'Requirement ID is required']);
    exit;
}

// Get user location and account type for access control
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;

// Build role-based filter for training data
$tr_filter = '';
$tr_params = [$requirement_id];

if ($account_type === 'superadmin') {
    // No filter
} elseif ($account_type === 'admin') {
    $tr_filter = ' AND tr.location = ?';
    $tr_params[] = $user_location;
} elseif (in_array($account_type, ['manager', 'supervisor'])) {
    $accessible = getAccessibleEmployeesByLocation($db, $user);
    $accessible_epfs = array_column($accessible, 'epf_number');
    if (!empty($accessible_epfs)) {
        $placeholders = str_repeat('?,', count($accessible_epfs) - 1) . '?';
        $tr_filter = " AND tr.epf_number IN ($placeholders)";
        $tr_params = array_merge($tr_params, $accessible_epfs);
    } else {
        $tr_filter = ' AND 1=0';
    }
} else {
    $tr_filter = ' AND tr.epf_number = ?';
    $tr_params[] = $user['epf_number'];
}

try {
    // Get requirement details with role-based filtering
    $requirement = $db->fetch("
        SELECT
            tr.*,
            e.name as employee_full_name,
            e.account_type,
            el.level_name as employment_level,
            l.location_name as location_full_name,
            created_user.name as created_by_name
        FROM training_requirements tr
        LEFT JOIN employees e ON tr.epf_number = e.epf_number
        LEFT JOIN employment_levels el ON e.employment_level_id = el.id
        LEFT JOIN locations l ON e.location_id = l.id
        LEFT JOIN employees created_user ON tr.created_by = created_user.epf_number
        WHERE tr.id = ?" . $tr_filter, $tr_params);

    if (!$requirement) {
        echo json_encode(['success' => false, 'message' => 'Training requirement not found or access denied']);
        exit;
    }

    // Get related training plans if any
    $training_plans = $db->fetchAll("
        SELECT
            tp.*,
            tb.budget_amount,
            tb.add_to_budget
        FROM training_plans tp
        LEFT JOIN training_budget tb ON tp.budget_id = tb.id
        WHERE tp.requirement_id = ?
        ORDER BY tp.created_at DESC
    ", [$requirement_id]);

    // Get training evaluations if any
    $evaluations = $db->fetchAll("
        SELECT
            te.*
        FROM training_evaluations te
        JOIN training_plans tp ON te.training_plan_id = tp.id
        WHERE tp.requirement_id = ?
        ORDER BY te.submitted_at DESC
    ", [$requirement_id]);

    // Get training feedback if any
    $feedback = $db->fetchAll("
        SELECT
            tf.*
        FROM training_feedback tf
        JOIN training_plans tp ON tf.training_plan_id = tp.id
        WHERE tp.requirement_id = ?
        ORDER BY tf.review_date DESC
    ", [$requirement_id]);

    echo json_encode([
        'success' => true,
        'requirement' => $requirement,
        'training_plans' => $training_plans,
        'evaluations' => $evaluations,
        'feedback' => $feedback
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching requirement details: ' . $e->getMessage()]);
}
?>