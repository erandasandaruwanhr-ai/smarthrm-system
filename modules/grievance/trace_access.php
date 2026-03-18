<?php
// Step-by-step access trace for case management
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>🔍 Access Trace - Case Management</title>
    <style>
        body { font-family: monospace; margin: 20px; }
        .step { margin: 10px 0; padding: 10px; border-left: 4px solid #007bff; background: #f8f9fa; }
        .success { border-left-color: #28a745; background: #d4edda; }
        .error { border-left-color: #dc3545; background: #f8d7da; }
        .warning { border-left-color: #ffc107; background: #fff3cd; }
        pre { background: #e9ecef; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Step-by-Step Access Trace</h1>
    <p><strong>Simulating:</strong> case_management.php?id=1&type=anonymous</p>
    <p><strong>User:</strong> <?php echo htmlspecialchars($user['name']) . ' (EPF: ' . $user['epf_number'] . ')'; ?></p>
    <hr>

    <?php
    // Simulate the exact logic from case_management.php

    echo "<div class='step'>";
    echo "<strong>STEP 1:</strong> Initial Access Check<br>";
    $account_type = $user['account_type'] ?? 'user';
    $initial_access_check = in_array($account_type, ['superadmin', 'admin', 'manager', 'supervisor']);
    echo "Account Type: {$account_type}<br>";
    echo "Initial Access: " . ($initial_access_check ? "✅ PASS" : "❌ FAIL") . "<br>";
    echo "</div>";

    if (!$initial_access_check && isset($_GET['type']) && $_GET['type'] === 'anonymous') {
        echo "<div class='step warning'>";
        echo "<strong>STEP 2:</strong> Anonymous Case Investigation Team Check<br>";

        $temp_grievance_id = $_GET['id'] ?? 1;
        echo "Case ID to check: {$temp_grievance_id}<br>";

        try {
            $table_exists = $db->fetch("SHOW TABLES LIKE 'anonymous_investigation_teams'");
            echo "Table exists: " . ($table_exists ? "✅ YES" : "❌ NO") . "<br>";

            if ($table_exists) {
                $temp_team_check = $db->fetch(
                    "SELECT id FROM anonymous_investigation_teams
                     WHERE anonymous_grievance_id = ? AND (team_leader_epf = ? OR member1_epf = ? OR member2_epf = ?) AND status = 'Active'",
                    [$temp_grievance_id, $user['epf_number'], $user['epf_number'], $user['epf_number']]
                );
                echo "Team check query executed<br>";
                echo "Team check result: " . ($temp_team_check ? "✅ FOUND" : "❌ NOT FOUND") . "<br>";

                if ($temp_team_check) {
                    $initial_access_check = true;
                    echo "Access granted due to investigation team membership<br>";
                    echo "</div>";

                    echo "<div class='step success'>";
                    echo "<strong>STEP 3:</strong> Access Granted - Proceeding<br>";
                    echo "Should continue to load case details<br>";
                    echo "</div>";
                } else {
                    echo "</div>";

                    echo "<div class='step error'>";
                    echo "<strong>STEP 3:</strong> Access Denied<br>";
                    echo "Would redirect to index.php<br>";
                    echo "</div>";
                }
            }
        } catch (Exception $e) {
            echo "Database Error: " . htmlspecialchars($e->getMessage()) . "<br>";
            echo "</div>";

            echo "<div class='step error'>";
            echo "<strong>STEP 3:</strong> Database Error - Access Denied<br>";
            echo "Would redirect to index.php<br>";
            echo "</div>";
        }
    } else if ($initial_access_check) {
        echo "<div class='step success'>";
        echo "<strong>STEP 2:</strong> Admin Access - No Further Checks Needed<br>";
        echo "Proceeding directly to case loading<br>";
        echo "</div>";
    } else {
        echo "<div class='step error'>";
        echo "<strong>STEP 2:</strong> Not Anonymous Case or No Access<br>";
        echo "Would redirect to index.php<br>";
        echo "</div>";
    }

    if ($initial_access_check) {
        echo "<div class='step'>";
        echo "<strong>STEP 4:</strong> Load Case Data<br>";

        $grievance_id = $_GET['id'] ?? 1;
        $case_type = $_GET['type'] ?? 'regular';

        echo "Grievance ID: {$grievance_id}<br>";
        echo "Case Type: {$case_type}<br>";

        try {
            if ($case_type === 'anonymous') {
                $grievance = $db->fetch("SELECT *, 1 as is_anonymous FROM anonymous_grievances WHERE id = ?", [$grievance_id]);
            } else {
                $grievance = $db->fetch("SELECT *, 0 as is_anonymous FROM grievances WHERE id = ?", [$grievance_id]);
            }

            if ($grievance) {
                echo "Case found: " . htmlspecialchars($grievance['case_id']) . "<br>";
                echo "Case status: " . htmlspecialchars($grievance['status']) . "<br>";
                echo "Is anonymous: " . ($grievance['is_anonymous'] ? 'YES' : 'NO') . "<br>";
                echo "</div>";

                echo "<div class='step success'>";
                echo "<strong>STEP 5:</strong> Final Access Check<br>";

                // Final access check logic
                $can_access_case = false;
                $is_investigation_team_member = false;

                if ($grievance['is_anonymous']) {
                    $investigation_team = $db->fetch(
                        "SELECT * FROM anonymous_investigation_teams
                         WHERE anonymous_grievance_id = ? AND (team_leader_epf = ? OR member1_epf = ? OR member2_epf = ?)",
                        [$grievance_id, $user['epf_number'], $user['epf_number'], $user['epf_number']]
                    );
                    $is_investigation_team_member = ($investigation_team !== false);
                    echo "Investigation team member: " . ($is_investigation_team_member ? "✅ YES" : "❌ NO") . "<br>";
                }

                if ($account_type === 'superadmin') {
                    $can_access_case = true;
                    echo "Access reason: Superadmin<br>";
                } elseif ($account_type === 'admin') {
                    $can_access_case = true;
                    echo "Access reason: Admin<br>";
                } else {
                    if ($grievance['is_anonymous'] && $is_investigation_team_member) {
                        $can_access_case = true;
                        echo "Access reason: Investigation team member<br>";
                    } else {
                        echo "Access denied reason: Not investigation team member or not anonymous case<br>";
                    }
                }

                echo "Final access decision: " . ($can_access_case ? "✅ GRANTED" : "❌ DENIED") . "<br>";

                if ($can_access_case) {
                    echo "</div>";

                    echo "<div class='step success'>";
                    echo "<strong>RESULT:</strong> Access Successful<br>";
                    echo "Case should load normally<br>";
                    echo "<a href='case_management.php?id={$grievance_id}&type={$case_type}' target='_blank'>→ Try accessing the actual page</a><br>";
                    echo "</div>";
                } else {
                    echo "</div>";

                    echo "<div class='step error'>";
                    echo "<strong>RESULT:</strong> Access Denied at Final Check<br>";
                    echo "Would redirect to grievance_list.php<br>";
                    echo "</div>";
                }

            } else {
                echo "❌ Case not found<br>";
                echo "</div>";

                echo "<div class='step error'>";
                echo "<strong>RESULT:</strong> Case Not Found<br>";
                echo "Would redirect to grievance_list.php<br>";
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "Error loading case: " . htmlspecialchars($e->getMessage()) . "<br>";
            echo "</div>";
        }
    } else {
        echo "<div class='step error'>";
        echo "<strong>RESULT:</strong> Access Denied at Initial Check<br>";
        echo "Would redirect to index.php<br>";
        echo "</div>";
    }
    ?>

    <hr>
    <h2>🛠️ Manual Test Links</h2>
    <p>Try accessing with different parameters:</p>
    <ul>
        <li><a href="?id=1&type=anonymous" target="_self">This trace with id=1&type=anonymous</a></li>
        <li><a href="case_management.php?id=1&type=anonymous" target="_blank">Actual case_management.php?id=1&type=anonymous</a></li>
        <li><a href="grievance_list.php" target="_blank">Back to grievance list</a></li>
    </ul>

</body>
</html>