<?php
require_once '../../../config/config.php';
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $db = new Database();

    if (!isset($_GET['review_id'])) {
        echo json_encode(['success' => false, 'message' => 'Review ID is required']);
        exit;
    }

    $review_id = $_GET['review_id'];

    // Get review details with training information
    $review = $db->fetch("
        SELECT
            tmc.*,
            tf.trainee_name,
            tf.training_id,
            tf.review_date,
            tp.training_name,
            tp.training_start_date,
            tp.training_cost
        FROM training_managerial_comments tmc
        JOIN training_feedback tf ON tmc.training_feedback_id = tf.id
        JOIN training_plans tp ON tf.training_plan_id = tp.id
        WHERE tmc.id = ?
    ", [$review_id]);

    if ($review) {
        echo json_encode([
            'success' => true,
            'review' => $review
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Review not found'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>