-- =====================================================
-- Clean up duplicate records in training tables
-- =====================================================

-- 1. First, let's see what duplicates exist
SELECT 'Checking for duplicate managerial comments...' as step;

SELECT
    training_feedback_id,
    COUNT(*) as duplicate_count
FROM training_managerial_comments
GROUP BY training_feedback_id
HAVING COUNT(*) > 1;

-- 2. Check for duplicate feedback records
SELECT 'Checking for duplicate feedback records...' as step;

SELECT
    training_plan_id,
    trainee_name,
    evaluator_name,
    review_date,
    COUNT(*) as duplicate_count
FROM training_feedback
GROUP BY training_plan_id, trainee_name, evaluator_name, review_date
HAVING COUNT(*) > 1;

-- 3. Remove duplicate managerial comments (keep only the latest one)
DELETE tmc1 FROM training_managerial_comments tmc1
INNER JOIN training_managerial_comments tmc2
WHERE
    tmc1.id < tmc2.id
    AND tmc1.training_feedback_id = tmc2.training_feedback_id;

-- 4. Remove duplicate feedback records (keep only the latest one)
DELETE tf1 FROM training_feedback tf1
INNER JOIN training_feedback tf2
WHERE
    tf1.id < tf2.id
    AND tf1.training_plan_id = tf2.training_plan_id
    AND tf1.trainee_name = tf2.trainee_name
    AND tf1.evaluator_name = tf2.evaluator_name
    AND tf1.review_date = tf2.review_date;

-- 5. Verify cleanup
SELECT 'Verification - checking for remaining duplicates...' as step;

SELECT
    training_feedback_id,
    COUNT(*) as duplicate_count
FROM training_managerial_comments
GROUP BY training_feedback_id
HAVING COUNT(*) > 1;

SELECT
    training_plan_id,
    COUNT(*) as duplicate_count
FROM training_feedback
GROUP BY training_plan_id
HAVING COUNT(*) > 1;