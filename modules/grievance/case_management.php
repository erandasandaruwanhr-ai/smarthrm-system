<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/location_access.php';

$user = getCurrentUser();
$db = new Database();

// Check access permissions - Simplified for anonymous cases (superadmin only)
$account_type = $user['account_type'] ?? 'user';
$initial_access_check = in_array($account_type, ['superadmin', 'admin', 'manager', 'supervisor']);

if (!$initial_access_check) {
    header('Location: index.php');
    exit();
}

$grievance_id = $_GET['id'] ?? null;
$case_type = $_GET['type'] ?? 'regular'; // Default to regular if not specified

if (!$grievance_id) {
    header('Location: grievance_list.php');
    exit();
}

// Get grievance details from appropriate table
if ($case_type === 'anonymous') {
    $grievance = $db->fetch("SELECT *, 1 as is_anonymous FROM anonymous_grievances WHERE id = ?", [$grievance_id]);
    $table_prefix = 'anonymous_';

    // Add null employee fields for anonymous cases to prevent undefined key errors
    $grievance['employee_name'] = null;
    $grievance['employee_epf'] = null;
    $grievance['employee_location'] = null;
    $grievance['employee_department'] = null;
} else {
    $grievance = $db->fetch("SELECT *, 0 as is_anonymous FROM grievances WHERE id = ?", [$grievance_id]);
    $table_prefix = '';
}

if (!$grievance) {
    header('Location: grievance_list.php');
    exit();
}

// Check if user has permission to access this specific grievance
$can_access_case = false;

if ($account_type === 'superadmin') {
    $can_access_case = true;
} elseif ($account_type === 'admin') {
    // Admin: Can access cases from employees in their accessible employee list (location-based)
    if ($grievance['is_anonymous']) {
        $can_access_case = true; // Admins can access anonymous cases (location unknown)
    } else {
        // For regular grievances, check if employee is accessible to this admin
        $accessible_employees = getAccessibleEmployeesByLocation($db, $user);
        $accessible_epf_numbers = array_column($accessible_employees, 'epf_number');
        $can_access_case = in_array($grievance['employee_epf'], $accessible_epf_numbers);
    }
} elseif ($account_type === 'manager') {
    // Manager: Can access non-anonymous cases following hierarchical reporting structure
    if (!$grievance['is_anonymous']) {
        // First check if case is from their department (direct department cases)
        $can_access_case = (($grievance['employee_department'] ?? '') === $user['department']);

        // Also check if they're in the hierarchical reporting chain
        if (!$can_access_case) {
            // Get the submitter's supervisor
            $submitter = $db->fetch("SELECT reports_to FROM employees WHERE epf_number = ?", [$grievance['employee_epf']]);

            if ($submitter && $submitter['reports_to']) {
                // Get the supervisor details
                $supervisor = $db->fetch("SELECT reports_to FROM employees WHERE epf_number = ?", [$submitter['reports_to']]);

                // Check if this manager is the supervisor's manager (supervisor reports to this manager)
                if ($supervisor && $supervisor['reports_to'] === $user['epf_number']) {
                    $can_access_case = true;
                }
            }
        }
    }
} elseif ($account_type === 'supervisor') {
    // Supervisor: Can access non-anonymous cases from their area
    if (!$grievance['is_anonymous']) {
        // Check if grievance is in supervisor's location and department
        if (($grievance['employee_location'] ?? '') === $user['location'] &&
            ($grievance['employee_department'] ?? '') === $user['department']) {
            $can_access_case = true;
        }

        // Also check if the grievance submitter reports to this supervisor
        if (!$can_access_case) {
            $submitter = $db->fetch("SELECT reports_to FROM employees WHERE epf_number = ?", [$grievance['employee_epf']]);
            if ($submitter && $submitter['reports_to'] === $user['epf_number']) {
                $can_access_case = true;
            }
        }
    }
} else {
    // Regular users: Can access non-anonymous cases they submitted
    if (!$grievance['is_anonymous'] && isset($grievance['employee_epf']) && $grievance['employee_epf'] === $user['epf_number']) {
        $can_access_case = true;
    }
}

// UNIVERSAL SUBORDINATE ACCESS: Anyone with direct reports should see those reports' cases
// This handles cases where account_type doesn't match but user has supervisory role
if (!$can_access_case && !$grievance['is_anonymous']) {
    $subordinate_check = $db->fetch("SELECT epf_number FROM employees WHERE reports_to = ? AND epf_number = ? AND is_active = 1", [$user['epf_number'], $grievance['employee_epf']]);
    if ($subordinate_check) {
        $can_access_case = true;
    }
}

// Anonymous cases: Only superadmin can access
if ($grievance['is_anonymous'] && $account_type !== 'superadmin') {
    $can_access_case = false;
}

if (!$can_access_case) {
    header('Location: grievance_list.php');
    exit();
}

// Define workflow logic for status transitions - MOVED HERE TO FIX UNDEFINED VARIABLE ERROR
$current_status = $grievance['status'];
$available_statuses = []; // Initialize as empty array

// Workflow rules based on current status, user role, and case type (anonymous vs non-anonymous)
if ($grievance['is_anonymous']) {
    // Anonymous case workflow - SIMPLIFIED: Opened → Reviewing → Action → Resolved
    if ($account_type === 'superadmin') {
        // Superadmin can set any of the simplified status options
        $available_statuses = ['Opened', 'Reviewing', 'Action', 'Resolved'];
    } else {
        // Only superadmins can manage anonymous cases - others get read-only access
        $available_statuses = [$current_status];
    }
} else {
    // Non-anonymous case workflow - Normal process
    if ($account_type === 'superadmin') {
        // Superadmin can set any status for non-anonymous cases
        $available_statuses = ['Open', 'Under Supervisory Review', 'Under Managerial Review', 'Resolved'];
    } elseif ($account_type === 'admin') {
        // Admin can manage most statuses
        $available_statuses = ['Open', 'Under Supervisory Review', 'Under Managerial Review', 'Resolved'];
    } elseif ($account_type === 'supervisor') {
        // Supervisor workflow - can move from Open to Under Review, then to Managerial Review
        if ($current_status === 'Open') {
            $available_statuses = ['Open', 'Under Supervisory Review'];
        } elseif ($current_status === 'Under Supervisory Review') {
            $available_statuses = ['Under Supervisory Review', 'Under Managerial Review'];
        } else {
            $available_statuses = [$current_status]; // Can only keep current status
        }
    } elseif ($account_type === 'manager') {
        // Manager can take action on any case that reaches them or is already under their review
        if ($current_status === 'Under Managerial Review') {
            $available_statuses = ['Under Managerial Review', 'Resolved'];
        } elseif ($current_status === 'Resolved') {
            $available_statuses = ['Resolved'];
        } else {
            // Manager can also directly take action on Open cases in their department
            $available_statuses = [$current_status, 'Under Managerial Review'];
        }
    } else {
        // Regular users cannot change status
        $available_statuses = [$current_status];
    }
}

// Handle form submissions
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for duplicate submission using token
    $form_token = $_POST['form_token'] ?? '';
    $session_key = 'last_form_token_' . $grievance_id;

    if (empty($form_token)) {
        $error_message = "Invalid form submission. Please refresh the page.";
    } elseif (isset($_SESSION[$session_key]) && $_SESSION[$session_key] === $form_token) {
        $error_message = "Duplicate submission prevented. Please refresh the page.";
    } else {
        $_SESSION[$session_key] = $form_token;

        try {
            $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'update_status':
                $new_status = $_POST['new_status'];
                $note = $_POST['note'] ?? '';

                // Validate status transition
                if (!in_array($new_status, $available_statuses)) {
                    throw new Exception('Invalid status transition');
                }

                // Update grievance status and timestamp
                if ($grievance['is_anonymous']) {
                    $db->update('anonymous_grievances', [
                        'status' => $new_status,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$grievance_id]);
                } else {
                    $db->update('grievances', [
                        'status' => $new_status,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$grievance_id]);
                }

                // Determine action type based on new status and case type
                if ($grievance['is_anonymous']) {
                    $action_type = match($new_status) {
                        'Opened' => 'Submission',
                        'Reviewing' => 'Superadmin Review',
                        'Action' => 'Action Taken',
                        'Resolved' => 'Resolution',
                        // Legacy support for old statuses
                        'Pending Team Assignment' => 'Superadmin Review',
                        'Team Investigation' => 'Investigation Progress',
                        'Investigation Complete' => 'Investigation Report',
                        'HR Review' => 'HR Review',
                        'Pending Investigation' => 'Submission',
                        'Under Investigation' => 'Investigation Progress',
                        'Dismissed' => 'Resolution',
                        default => 'Superadmin Review'
                    };
                } else {
                    $action_type = match($new_status) {
                        'Under Supervisory Review' => 'Supervisor Review',
                        'Under Managerial Review' => 'Manager Review',
                        'Resolved' => 'Resolution',
                        default => 'Status Update'
                    };
                }

                // Add note with proper action type - Create detailed status change note
                // Create descriptive status change message
                if (!empty($note)) {
                    $note_text = $note;
                } else {
                    $status_messages = [
                        'Opened' => 'Case opened and ready for superadmin review',
                        'Reviewing' => 'Case under superadmin review',
                        'Action' => 'Action being taken to address the grievance',
                        'Resolved' => 'Case resolved and closed',
                        // Legacy support for old statuses
                        'Pending Team Assignment' => 'Case awaiting investigation team assignment',
                        'Team Investigation' => 'Investigation team assigned and investigation started',
                        'Investigation Complete' => 'Investigation completed by team',
                        'HR Review' => 'Case under HR review'
                    ];
                    $note_text = $status_messages[$new_status] ?? "Status changed to: $new_status";
                }
                if ($grievance['is_anonymous']) {
                    $note_data = [
                        'anonymous_grievance_id' => $grievance_id,
                        'user_epf' => $user['epf_number'],
                        'user_name' => $user['name'],
                        'action_type' => $action_type,
                        'note' => $note_text
                    ];
                    $db->insert('anonymous_grievance_notes', $note_data);
                } else {
                    $note_data = [
                        'grievance_id' => $grievance_id,
                        'user_epf' => $user['epf_number'],
                        'user_name' => $user['name'],
                        'action_type' => $action_type,
                        'note' => $note_text
                    ];
                    $db->insert('grievance_notes', $note_data);
                }

                $success_message = "Status updated successfully to: $new_status";
                $grievance['status'] = $new_status;

                // Redirect to prevent form resubmission on refresh
                $redirect_url = "case_management.php?id=$grievance_id";
                if ($grievance['is_anonymous']) {
                    $redirect_url .= "&type=anonymous";
                }
                $redirect_url .= "&success=" . urlencode($success_message);
                header("Location: $redirect_url");
                exit();
                break;

            case 'assign_investigators':
                if ($account_type === 'superadmin' && $grievance['is_anonymous']) {
                    $team_leader_epf = $_POST['team_leader_epf'];
                    $member1_epf = $_POST['member1_epf'] ?? null;
                    $member2_epf = $_POST['member2_epf'] ?? null;

                    // Get employee names
                    $team_leader = $db->fetch("SELECT name FROM employees WHERE epf_number = ?", [$team_leader_epf]);
                    $member1 = !empty($member1_epf) ? $db->fetch("SELECT name FROM employees WHERE epf_number = ?", [$member1_epf]) : null;
                    $member2 = !empty($member2_epf) ? $db->fetch("SELECT name FROM employees WHERE epf_number = ?", [$member2_epf]) : null;

                    if ($team_leader) {
                        // Remove existing assignments
                        $db->delete('anonymous_investigation_teams', 'anonymous_grievance_id = ?', [$grievance_id]);

                        // Add new assignment
                        $assignment_data = [
                            'anonymous_grievance_id' => $grievance_id,
                            'team_leader_epf' => $team_leader_epf,
                            'team_leader_name' => $team_leader['name'],
                            'member1_epf' => $member1_epf,
                            'member1_name' => $member1['name'] ?? null,
                            'member2_epf' => $member2_epf,
                            'member2_name' => $member2['name'] ?? null,
                            'assigned_by' => $user['epf_number'],
                            'assigned_date' => date('Y-m-d H:i:s')
                        ];
                        $db->insert('anonymous_investigation_teams', $assignment_data);

                        // Update grievance status based on case type
                        if ($grievance['is_anonymous']) {
                            $db->update('anonymous_grievances', ['status' => 'Under Investigation'], 'id = ?', [$grievance_id]);
                            $grievance['status'] = 'Under Investigation';
                        } else {
                            $db->update('grievances', ['status' => 'Under Supervisory Review'], 'id = ?', [$grievance_id]);
                            $grievance['status'] = 'Under Supervisory Review';
                        }

                        // Add note
                        if ($grievance['is_anonymous']) {
                            $note_data = [
                                'anonymous_grievance_id' => $grievance_id,
                                'user_epf' => $user['epf_number'],
                                'user_name' => $user['name'],
                                'action_type' => 'Investigation Assignment',
                                'note' => "Investigation team assigned. Team Leader: {$team_leader['name']} ($team_leader_epf)"
                            ];
                            $db->insert('anonymous_grievance_notes', $note_data);
                        } else {
                            $note_data = [
                                'grievance_id' => $grievance_id,
                                'user_epf' => $user['epf_number'],
                                'user_name' => $user['name'],
                                'action_type' => 'Assignment',
                                'note' => "Investigation team assigned. Team Leader: {$team_leader['name']} ($team_leader_epf)"
                            ];
                            $db->insert('grievance_notes', $note_data);
                        }

                        $success_message = "Investigation team assigned successfully";
                        // Status already updated above

                        // Redirect to prevent form resubmission on refresh
                        $redirect_url = "case_management.php?id=$grievance_id";
                        if ($grievance['is_anonymous']) {
                            $redirect_url .= "&type=anonymous";
                        }
                        $redirect_url .= "&success=" . urlencode($success_message);
                        header("Location: $redirect_url");
                        exit();
                    } else {
                        $error_message = "Team leader not found";
                    }
                }
                break;

            case 'add_note':
                $note = $_POST['note'];
                $note_type = $_POST['note_type'];

                // Auto-advance status based on note type and user role
                $auto_update_status = false;
                $new_auto_status = null;

                if ($note_type === 'Supervisor Review' && $account_type === 'supervisor') {
                    if ($current_status === 'Open') {
                        $new_auto_status = 'Under Supervisory Review';
                        $auto_update_status = true;
                    }
                } elseif ($note_type === 'Manager Review' && $account_type === 'manager') {
                    if (in_array($current_status, ['Open', 'Under Supervisory Review'])) {
                        $new_auto_status = 'Under Managerial Review';
                        $auto_update_status = true;
                    }
                }

                // Update status if auto-progression is triggered
                if ($auto_update_status && $new_auto_status) {
                    if ($grievance['is_anonymous']) {
                        $db->update('anonymous_grievances', [
                            'status' => $new_auto_status,
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$grievance_id]);
                    } else {
                        $db->update('grievances', [
                            'status' => $new_auto_status,
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$grievance_id]);
                    }

                    $grievance['status'] = $new_auto_status;
                    $current_status = $new_auto_status;
                }

                if ($grievance['is_anonymous']) {
                    $note_data = [
                        'anonymous_grievance_id' => $grievance_id,
                        'user_epf' => $user['epf_number'],
                        'user_name' => $user['name'],
                        'action_type' => $note_type,
                        'note' => $note
                    ];
                    $db->insert('anonymous_grievance_notes', $note_data);
                } else {
                    $note_data = [
                        'grievance_id' => $grievance_id,
                        'user_epf' => $user['epf_number'],
                        'user_name' => $user['name'],
                        'action_type' => $note_type,
                        'note' => $note
                    ];
                    $db->insert('grievance_notes', $note_data);
                }

                if ($auto_update_status) {
                    $success_message = "Note added and status automatically updated to: $new_auto_status";

                } else {
                    $success_message = "Note added successfully";
                }

                // Redirect to prevent form resubmission on refresh
                $redirect_url = "case_management.php?id=$grievance_id";
                if ($grievance['is_anonymous']) {
                    $redirect_url .= "&type=anonymous";
                }
                $redirect_url .= "&success=" . urlencode($success_message);

                if (!headers_sent()) {
                    header("Location: $redirect_url");
                    exit();
                } else {
                    // If headers already sent, use JavaScript redirect as fallback
                    echo "<script>window.location.href = '$redirect_url';</script>";
                    exit();
                }
                break;

            case 'auto_fix_status':
                // Auto-fix status based on existing notes
                $fixed_status = null;
                $fix_reason = '';

                if ($current_status === 'Open' && !$grievance['is_anonymous']) {
                    // Check for supervisor review notes (only for non-anonymous cases)
                    $supervisor_review = $db->fetch("SELECT * FROM grievance_notes WHERE grievance_id = ? AND action_type = 'Supervisor Review' ORDER BY created_at DESC LIMIT 1", [$grievance_id]);
                    if ($supervisor_review) {
                        $fixed_status = 'Under Supervisory Review';
                        $fix_reason = 'Found existing supervisor review by ' . $supervisor_review['user_name'];
                    }
                }

                if ($fixed_status) {
                    if ($grievance['is_anonymous']) {
                        $db->update('anonymous_grievances', [
                            'status' => $fixed_status,
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$grievance_id]);
                    } else {
                        $db->update('grievances', [
                            'status' => $fixed_status,
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$grievance_id]);
                    }

                    // Add system note
                    if ($grievance['is_anonymous']) {
                        $note_data = [
                            'anonymous_grievance_id' => $grievance_id,
                            'user_epf' => $user['epf_number'],
                            'user_name' => $user['name'],
                            'action_type' => 'Status Update',
                            'note' => "Status automatically corrected to: $fixed_status. Reason: $fix_reason"
                        ];
                        $db->insert('anonymous_grievance_notes', $note_data);
                    } else {
                        $note_data = [
                            'grievance_id' => $grievance_id,
                            'user_epf' => $user['epf_number'],
                            'user_name' => $user['name'],
                            'action_type' => 'Status Update',
                            'note' => "Status automatically corrected to: $fixed_status. Reason: $fix_reason"
                        ];
                        $db->insert('grievance_notes', $note_data);
                    }

                    $success_message = "Status automatically fixed to: $fixed_status";
                    $grievance['status'] = $fixed_status;
                    $current_status = $fixed_status;

                    // Redirect to prevent form resubmission on refresh
                    $redirect_url = "case_management.php?id=$grievance_id";
                    if ($grievance['is_anonymous']) {
                        $redirect_url .= "&type=anonymous";
                    }
                    $redirect_url .= "&success=" . urlencode($success_message);
                    header("Location: $redirect_url");
                    exit();
                } else {
                    $error_message = "No automatic status correction needed";
                }
                break;

            case 'resolve_case':
                // Only managers and above can resolve cases
                if (!in_array($account_type, ['superadmin', 'admin', 'manager'])) {
                    throw new Exception('You do not have permission to resolve cases');
                }

                $resolution = $_POST['resolution'];

                // Update grievance
                if ($grievance['is_anonymous']) {
                    $db->update('anonymous_grievances', [
                        'status' => 'Resolved',
                        'resolution' => $resolution
                    ], 'id = ?', [$grievance_id]);
                } else {
                    $db->update('grievances', [
                        'status' => 'Resolved',
                        'resolution' => $resolution
                    ], 'id = ?', [$grievance_id]);
                }

                // Add note
                if ($grievance['is_anonymous']) {
                    $note_data = [
                        'anonymous_grievance_id' => $grievance_id,
                        'user_epf' => $user['epf_number'],
                        'user_name' => $user['name'],
                        'action_type' => 'Resolution',
                        'note' => "Case resolved: $resolution"
                    ];
                    $db->insert('anonymous_grievance_notes', $note_data);
                } else {
                    $note_data = [
                        'grievance_id' => $grievance_id,
                        'user_epf' => $user['epf_number'],
                        'user_name' => $user['name'],
                        'action_type' => 'Resolution',
                        'note' => "Case resolved: $resolution"
                    ];
                    $db->insert('grievance_notes', $note_data);
                }

                $success_message = "Case resolved successfully";
                $grievance['status'] = 'Resolved';
                $grievance['resolution'] = $resolution;

                // Redirect to prevent form resubmission on refresh
                $redirect_url = "case_management.php?id=$grievance_id";
                if ($grievance['is_anonymous']) {
                    $redirect_url .= "&type=anonymous";
                }
                $redirect_url .= "&success=" . urlencode($success_message);
                header("Location: $redirect_url");
                exit();
                break;
        }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Refresh grievance data after potential updates from redirect
if ($case_type === 'anonymous') {
    $grievance = $db->fetch("SELECT *, 1 as is_anonymous FROM anonymous_grievances WHERE id = ?", [$grievance_id]);
} else {
    $grievance = $db->fetch("SELECT *, 0 as is_anonymous FROM grievances WHERE id = ?", [$grievance_id]);
}

// Get case notes
if ($grievance['is_anonymous']) {
    $notes = $db->fetchAll("SELECT * FROM anonymous_grievance_notes WHERE anonymous_grievance_id = ? ORDER BY created_at ASC, id ASC", [$grievance_id]);
} else {
    $notes = $db->fetchAll("SELECT * FROM grievance_notes WHERE grievance_id = ? ORDER BY created_at ASC, id ASC", [$grievance_id]);
}

// Check if status should be auto-updated based on existing notes
$should_auto_update = false;
$suggested_status = null;

if ($current_status === 'Open') {
    // Check if there's a supervisor review note but status is still Open
    foreach ($notes as $note) {
        if ($note['action_type'] === 'Supervisor Review') {
            $should_auto_update = true;
            $suggested_status = 'Under Supervisory Review';
            break;
        }
    }
}

// Investigation team functionality removed for simplified anonymous workflow
$investigation_team = null;

// Get evidence files
if ($grievance['is_anonymous']) {
    $evidence_files = $db->fetchAll("SELECT * FROM anonymous_grievance_evidence WHERE anonymous_grievance_id = ?", [$grievance_id]);
} else {
    $evidence_files = $db->fetchAll("SELECT * FROM grievance_evidence WHERE grievance_id = ?", [$grievance_id]);
}

// Get employees for assignment (superadmin only)
$employees = [];
if ($account_type === 'superadmin') {
    $employees = $db->fetchAll("SELECT epf_number, name, designation FROM employees WHERE is_active = 1 ORDER BY name");
}

// Workflow logic has been moved earlier to fix undefined variable error

// Define available note types - ONLY NOTE ADD and EVIDENCE (OTHER removed)
if ($grievance['is_anonymous'] && $account_type === 'superadmin') {
    // Superadmin note types for anonymous cases - only NOTE ADD and EVIDENCE
    $note_types = ['NOTE ADD', 'EVIDENCE'];
} elseif ($grievance['is_anonymous']) {
    // Non-superadmin users cannot add notes to anonymous cases
    $note_types = [];
} else {
    // Note types for non-anonymous cases - only NOTE ADD and EVIDENCE
    $note_types = ['NOTE ADD', 'EVIDENCE'];
}

// Generate unique form token
$form_token = uniqid('form_', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Case Management</title>
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
    <style>
        .case-info-card {
            border-left: 5px solid #007bff;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }

        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }

        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -0.75rem;
            top: 0.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #007bff;
            border: 3px solid #fff;
            box-shadow: 0 0 0 3px #dee2e6;
        }

        .evidence-file {
            border: 1px dashed #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .evidence-file:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-gavel me-3"></i>Case Management</h1>
                        <p>Manage investigation cases and resolution processes</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Grievance Management</a></li>
                    <li class="breadcrumb-item active">Case Management</li>
                </ol>
            </nav>

            <!-- Auto-fix status alert -->
            <?php if ($should_auto_update && $suggested_status): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Status Mismatch Detected!</strong>
                There's a supervisor review in the timeline but status is still "<?php echo $current_status; ?>".
                Status should be "<?php echo $suggested_status; ?>".
                <form method="POST" style="display: inline-block; margin-left: 10px;">
                    <input type="hidden" name="action" value="auto_fix_status">
                    <button type="submit" class="btn btn-sm btn-warning">
                        <i class="fas fa-magic me-1"></i>Auto-Fix Status
                    </button>
                </form>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

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

            <div class="row">
                <!-- Case Information -->
                <div class="col-lg-8">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Case Information</h5>
                                <span class="badge status-badge <?php
                                echo match($grievance['status']) {
                                    'Open' => 'bg-warning text-dark',
                                    'Under Supervisory Review' => 'bg-info',
                                    'Under Managerial Review' => 'bg-primary',
                                    'Resolved' => 'bg-success',
                                    default => 'bg-secondary'
                                };
                                ?>">
                                    <?php echo htmlspecialchars($grievance['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Case Details</h6>
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td><strong>Case ID:</strong></td>
                                            <td><?php echo htmlspecialchars($grievance['case_id']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Category:</strong></td>
                                            <td><?php echo htmlspecialchars($grievance['category']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Urgency:</strong></td>
                                            <td>
                                                <span class="badge <?php
                                                echo match($grievance['urgency']) {
                                                    'Critical' => 'bg-danger',
                                                    'High' => 'bg-warning text-dark',
                                                    'Medium' => 'bg-info',
                                                    'Low' => 'bg-success'
                                                };
                                                ?>">
                                                    <?php echo htmlspecialchars($grievance['urgency']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Submitted:</strong></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($grievance['submission_date'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6><?php echo $grievance['is_anonymous'] ? 'Submitter Information' : 'Employee Information'; ?></h6>
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td><strong>Submitter:</strong></td>
                                            <td>
                                                <?php if ($grievance['is_anonymous']): ?>
                                                    Anonymous
                                                    <span class="badge bg-secondary ms-1">Anonymous</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($grievance['employee_name'] ?? 'Unknown'); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>EPF:</strong></td>
                                            <td>
                                                <?php if ($grievance['is_anonymous']): ?>
                                                    ***
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($grievance['employee_epf'] ?? 'Unknown'); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Location:</strong></td>
                                            <td><?php
                                                if ($grievance['is_anonymous']) {
                                                    echo htmlspecialchars($grievance['submitter_location'] ?? 'Not disclosed');
                                                } else {
                                                    echo htmlspecialchars($grievance['employee_location'] ?? 'Unknown');
                                                }
                                            ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Department:</strong></td>
                                            <td><?php
                                                if ($grievance['is_anonymous']) {
                                                    echo htmlspecialchars($grievance['submitter_department'] ?? 'Not disclosed');
                                                } else {
                                                    echo htmlspecialchars($grievance['employee_department'] ?? 'Unknown');
                                                }
                                            ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="mt-3">
                                <h6>Subject</h6>
                                <p class="mb-2"><?php echo htmlspecialchars($grievance['subject']); ?></p>

                                <h6>Description</h6>
                                <p><?php echo nl2br(htmlspecialchars($grievance['description'])); ?></p>

                                <?php if (!empty($grievance['incident_date'])): ?>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <h6>Incident Date</h6>
                                        <p><?php echo date('d/m/Y', strtotime($grievance['incident_date'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Incident Location</h6>
                                        <p><?php echo htmlspecialchars($grievance['incident_location']); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($grievance['witnesses'])): ?>
                                <div class="mt-3">
                                    <h6>Witnesses</h6>
                                    <p><?php echo nl2br(htmlspecialchars($grievance['witnesses'])); ?></p>
                                </div>
                                <?php endif; ?>

                                <?php if ($grievance['status'] === 'Resolved' && !empty($grievance['resolution'])): ?>
                                <div class="mt-3 p-3 bg-success bg-opacity-10 rounded">
                                    <h6 class="text-success"><i class="fas fa-check-circle me-2"></i>Resolution</h6>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($grievance['resolution'])); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Workflow Status Display -->
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-route me-2"></i>Workflow Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="workflow-steps">
                                <?php
                                // Dynamically determine the actual status based on notes
                                $actual_status = $current_status;

                                if (!$grievance['is_anonymous']) {
                                    // Regular case status determination
                                    if ($current_status === 'Open') {
                                        // Check if there are supervisor reviews
                                        foreach ($notes as $note) {
                                            if ($note['action_type'] === 'Supervisor Review') {
                                                $actual_status = 'Under Supervisory Review';
                                                break;
                                            }
                                        }
                                    }
                                } else {
                                    // Anonymous case status determination - use status from database directly
                                    $actual_status = $grievance['status'];
                                }

                                if ($grievance['is_anonymous']) {
                                    // Anonymous case workflow - SIMPLIFIED: Opened → Reviewing → Action → Resolved
                                    $workflow_steps = [
                                        'Opened' => ['icon' => 'fas fa-folder-open', 'label' => 'Opened'],
                                        'Reviewing' => ['icon' => 'fas fa-eye', 'label' => 'Reviewing'],
                                        'Action' => ['icon' => 'fas fa-cog', 'label' => 'Action'],
                                        'Resolved' => ['icon' => 'fas fa-check-circle', 'label' => 'Resolved']
                                    ];
                                } else {
                                    // Regular case workflow
                                    $workflow_steps = [
                                        'Open' => ['icon' => 'fas fa-play-circle', 'label' => 'Open'],
                                        'Under Supervisory Review' => ['icon' => 'fas fa-user-check', 'label' => 'Supervisory Review'],
                                        'Under Managerial Review' => ['icon' => 'fas fa-user-tie', 'label' => 'Managerial Review'],
                                        'Resolved' => ['icon' => 'fas fa-check-circle', 'label' => 'Resolved']
                                    ];
                                }

                                $workflow_order = array_keys($workflow_steps);
                                $current_step_index = array_search($actual_status, $workflow_order);
                                if ($current_step_index === false) $current_step_index = 0;

                                // Debug: Show current status for troubleshooting
                                if (isset($_GET['debug'])) {
                                    echo "<!-- DEBUG: actual_status = '$actual_status', current_step_index = $current_step_index -->\n";
                                    echo "<!-- DEBUG: workflow_order = " . json_encode($workflow_order) . " -->\n";
                                }
                                ?>

                                <div class="d-flex justify-content-between align-items-center position-relative">
                                    <!-- Workflow Line -->
                                    <div class="workflow-line position-absolute w-100" style="height: 2px; background: #dee2e6; top: 50%; left: 0; z-index: 1;"></div>

                                    <?php foreach ($workflow_steps as $step_status => $step_info): ?>
                                    <?php
                                    $step_index = array_search($step_status, $workflow_order);
                                    $is_current = ($step_status === $actual_status);
                                    $is_completed = ($step_index !== false && $current_step_index !== false && $step_index < $current_step_index);
                                    $step_class = $is_current ? 'text-primary' : ($is_completed ? 'text-success' : 'text-muted');
                                    $bg_class = $is_current ? 'bg-primary' : ($is_completed ? 'bg-success' : 'bg-light');
                                    ?>
                                    <div class="text-center position-relative" style="z-index: 2;">
                                        <div class="workflow-step mb-2">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center <?php echo $bg_class; ?> <?php echo $is_current || $is_completed ? 'text-white' : 'text-muted'; ?>" style="width: 50px; height: 50px;">
                                                <i class="<?php echo $step_info['icon']; ?> fa-lg"></i>
                                            </div>
                                        </div>
                                        <small class="<?php echo $step_class; ?> fw-bold"><?php echo $step_info['label']; ?></small>
                                        <?php if ($is_current): ?>
                                        <div><span class="badge bg-primary mt-1">Current</span></div>
                                        <?php elseif ($is_completed): ?>
                                        <div><span class="badge bg-success mt-1">Completed</span></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Evidence Files -->
                    <?php if (!empty($evidence_files)): ?>
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-paperclip me-2"></i>Evidence Files</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($evidence_files as $file): ?>
                                <div class="col-md-4">
                                    <div class="evidence-file">
                                        <i class="fas fa-file fa-2x text-muted mb-2"></i>
                                        <p class="mb-1 small"><strong><?php echo htmlspecialchars($file['file_name']); ?></strong></p>
                                        <p class="mb-2 text-muted small"><?php echo strtoupper($file['file_type']); ?> - <?php echo number_format($file['file_size'] / 1024, 1); ?> KB</p>
                                        <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-download me-1"></i>Download
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Case Timeline -->
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-history me-2"></i>Case Timeline</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($notes)): ?>
                            <p class="text-muted text-center py-4">No notes or actions recorded yet</p>
                            <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($notes as $note): ?>
                                <div class="timeline-item">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($note['action_type']); ?></h6>
                                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                                                    <small class="text-muted">
                                                        by <?php echo htmlspecialchars($note['user_name']); ?> (<?php echo htmlspecialchars($note['user_epf']); ?>)
                                                        on <?php echo date('d/m/Y H:i', strtotime($note['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Management Actions -->
                <div class="col-lg-4">
                    <!-- Status Management -->
                    <?php
                    // Check if user can manage status based on role and case status
                    $can_manage_status = true;

                    if ($account_type === 'supervisor') {
                        // Supervisors cannot manage status once case is in Managerial Review or Resolved
                        if (in_array($current_status, ['Under Managerial Review', 'Resolved'])) {
                            $can_manage_status = false;
                        }
                    }
                    ?>

                    <?php if ($can_manage_status): ?>
                    <div class="form-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-cog me-2"></i>Status Management</h5>
                            <small class="text-muted">
                                <?php
                                // Dynamic warning messages based on user role and case status
                                $access_message = '';
                                $message_class = 'text-muted';

                                if ($account_type === 'superadmin') {
                                    $access_message = '🔧 Full access - Can manage all cases and assign investigation teams';
                                    $message_class = 'text-info';
                                } elseif ($account_type === 'admin') {
                                    $access_message = '🛡️ Admin access - Can manage all cases system-wide';
                                    $message_class = 'text-info';
                                } elseif ($account_type === 'manager') {
                                    switch ($current_status) {
                                        case 'Open':
                                            $access_message = '⚠️ Case is still open - Awaiting supervisor review before managerial action';
                                            $message_class = 'text-warning';
                                            break;
                                        case 'Under Supervisory Review':
                                            $access_message = '⚠️ Case is under supervisor review - Will be available for managerial action once forwarded';
                                            $message_class = 'text-warning';
                                            break;
                                        case 'Under Managerial Review':
                                            $access_message = '✅ Ready for action - You can now add notes and resolve this case';
                                            $message_class = 'text-success';
                                            break;
                                        case 'Resolved':
                                            $access_message = '✅ Case resolved - You can add follow-up notes or close the case';
                                            $message_class = 'text-success';
                                            break;
                                        default:
                                            $access_message = 'ℹ️ Case status: ' . $current_status;
                                            $message_class = 'text-muted';
                                    }
                                } elseif ($account_type === 'supervisor') {
                                    switch ($current_status) {
                                        case 'Open':
                                            $access_message = '✅ Ready for review - You can add supervisor review and forward to management';
                                            $message_class = 'text-success';
                                            break;
                                        case 'Under Supervisory Review':
                                            $access_message = '🔄 Under your review - You can continue reviewing and forward to management';
                                            $message_class = 'text-primary';
                                            break;
                                        case 'Under Managerial Review':
                                            $access_message = '🔒 Forwarded to management - Supervisors can no longer add notes or make changes';
                                            $message_class = 'text-warning';
                                            break;
                                        case 'Resolved':
                                            $access_message = '✅ Case resolved by management - You can view final outcome';
                                            $message_class = 'text-success';
                                            break;
                                        default:
                                            $access_message = 'ℹ️ Case status: ' . $current_status;
                                            $message_class = 'text-muted';
                                    }
                                } else {
                                    // Regular user
                                    switch ($current_status) {
                                        case 'Open':
                                            $access_message = '⏳ Your case is open - Awaiting supervisor review';
                                            $message_class = 'text-warning';
                                            break;
                                        case 'Under Supervisory Review':
                                            $access_message = '🔄 Under supervisor review - Your case is being reviewed by your supervisor';
                                            $message_class = 'text-primary';
                                            break;
                                        case 'Under Managerial Review':
                                            $access_message = '🔄 Under management review - Your case is being reviewed by management';
                                            $message_class = 'text-primary';
                                            break;
                                        case 'Resolved':
                                            $access_message = '✅ Your case has been resolved - You can view the resolution details';
                                            $message_class = 'text-success';
                                            break;
                                        default:
                                            $access_message = 'ℹ️ Case status: ' . $current_status;
                                            $message_class = 'text-muted';
                                    }
                                }
                                ?>
                                <span class="<?php echo $message_class; ?>"><?php echo $access_message; ?></span>
                            </small>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="form_token" value="<?php echo $form_token; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Change Status</label>
                                    <select class="form-select" name="new_status" required>
                                        <?php foreach ($available_statuses as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php echo $status === $grievance['status'] ? 'selected' : ''; ?>>
                                            <?php
                                            // Display user-friendly status name
                                            if ($grievance['is_anonymous']) {
                                                $status_labels = [
                                                    'Opened' => 'Opened',
                                                    'Pending Team Assignment' => 'Pending Team Assignment',
                                                    'Team Investigation' => 'Team Investigation',
                                                    'Investigation Complete' => 'Investigation Complete',
                                                    'HR Review' => 'HR Review',
                                                    'Resolved' => 'Resolved'
                                                ];
                                            } else {
                                                $status_labels = [
                                                    'Open' => 'Open',
                                                    'Under Supervisory Review' => 'Supervisory Review',
                                                    'Under Managerial Review' => 'Managerial Review',
                                                    'Resolved' => 'Resolved'
                                                ];
                                            }
                                            echo $status_labels[$status] ?? $status;
                                            ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php
                                    // Contextual help for status changes
                                    $status_help = '';
                                    $help_class = 'text-muted';

                                    if ($account_type === 'supervisor') {
                                        if ($current_status === 'Open') {
                                            $status_help = '💡 Tip: Change to "Supervisory Review" after adding your review notes';
                                            $help_class = 'text-info';
                                        } elseif ($current_status === 'Under Supervisory Review') {
                                            $status_help = '💡 Tip: Change to "Managerial Review" to forward to management';
                                            $help_class = 'text-info';
                                        }
                                    } elseif ($account_type === 'manager') {
                                        if ($current_status === 'Under Managerial Review') {
                                            $status_help = '💡 Tip: Change to "Resolved" after adding resolution details';
                                            $help_class = 'text-success';
                                        }
                                    } elseif (in_array($account_type, ['admin', 'superadmin'])) {
                                        if ($grievance['is_anonymous']) {
                                            $status_help = '💡 Admin: Move through Investigation → HR Review → Resolved as needed';
                                        } else {
                                            $status_help = '💡 Admin: You can change to any status as needed';
                                        }
                                        $help_class = 'text-info';
                                    }

                                    if ($status_help): ?>
                                    <small class="<?php echo $help_class; ?>"><?php echo $status_help; ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Note</label>
                                    <textarea class="form-control" name="note" rows="3" placeholder="Add a note about this status change..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-save me-2"></i>Update Status
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="form-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-cog me-2"></i>Status Management</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Case Forwarded:</strong> This case has been forwarded to management. Supervisors can no longer change the status or add notes.
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Current Status</label>
                                <div class="form-control bg-light"><?php echo htmlspecialchars($current_status); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Investigation Assignment (Anonymous cases only, Superadmin only) -->
                    <?php if ($account_type === 'superadmin' && $grievance['is_anonymous'] && in_array($grievance['status'], ['Pending Investigation', 'Pending Team Assignment'])): ?>
                    <div class="form-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-users me-2"></i>Assign Investigation Team</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="assign_investigators">
                                <input type="hidden" name="form_token" value="<?php echo $form_token; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Team Leader <span class="text-danger">*</span></label>
                                    <select class="form-select" name="team_leader_epf" required>
                                        <option value="">Select Team Leader</option>
                                        <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['epf_number']; ?>">
                                            <?php echo htmlspecialchars($employee['name']); ?> - <?php echo htmlspecialchars($employee['designation']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Member 1</label>
                                    <select class="form-select" name="member1_epf">
                                        <option value="">Select Member 1 (Optional)</option>
                                        <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['epf_number']; ?>">
                                            <?php echo htmlspecialchars($employee['name']); ?> - <?php echo htmlspecialchars($employee['designation']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Member 2</label>
                                    <select class="form-select" name="member2_epf">
                                        <option value="">Select Member 2 (Optional)</option>
                                        <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['epf_number']; ?>">
                                            <?php echo htmlspecialchars($employee['name']); ?> - <?php echo htmlspecialchars($employee['designation']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm w-100">
                                    <i class="fas fa-user-plus me-2"></i>Assign Team
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Current Investigation Team -->
                    <?php if ($investigation_team): ?>
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-shield-alt me-2"></i>Investigation Team</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>Team Leader:</strong><br>
                                <?php echo htmlspecialchars($investigation_team['team_leader_name']); ?>
                                <small class="text-muted">(<?php echo htmlspecialchars($investigation_team['team_leader_epf']); ?>)</small>
                            </div>
                            <?php if ($investigation_team['member1_name']): ?>
                            <div class="mb-2">
                                <strong>Member 1:</strong><br>
                                <?php echo htmlspecialchars($investigation_team['member1_name']); ?>
                                <small class="text-muted">(<?php echo htmlspecialchars($investigation_team['member1_epf']); ?>)</small>
                            </div>
                            <?php endif; ?>
                            <?php if ($investigation_team['member2_name']): ?>
                            <div class="mb-2">
                                <strong>Member 2:</strong><br>
                                <?php echo htmlspecialchars($investigation_team['member2_name']); ?>
                                <small class="text-muted">(<?php echo htmlspecialchars($investigation_team['member2_epf']); ?>)</small>
                            </div>
                            <?php endif; ?>
                            <small class="text-muted">
                                Assigned on <?php echo date('d/m/Y H:i', strtotime($investigation_team['assigned_date'])); ?>
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Add Note -->
                    <?php
                    // Check if user can add notes based on role and case status
                    $can_add_notes = true;

                    if ($account_type === 'supervisor') {
                        // Supervisors cannot add notes once case is in Managerial Review or Resolved
                        if (in_array($current_status, ['Under Managerial Review', 'Resolved'])) {
                            $can_add_notes = false;
                        }
                    }

                    // Also check if note_types array is empty (for anonymous cases by non-superadmin)
                    if (empty($note_types)) {
                        $can_add_notes = false;
                    }
                    ?>

                    <?php if ($can_add_notes): ?>
                    <div class="form-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-sticky-note me-2"></i>Add Note</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_note">
                                <input type="hidden" name="form_token" value="<?php echo $form_token; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Note Type</label>
                                    <select class="form-select" name="note_type" required>
                                        <?php foreach ($note_types as $type): ?>
                                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Note</label>
                                    <textarea class="form-control" name="note" rows="4" placeholder="Add your note here..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-info btn-sm w-100">
                                    <i class="fas fa-plus me-2"></i>Add Note
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="form-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-sticky-note me-2"></i>Add Note</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php if ($account_type === 'supervisor' && in_array($current_status, ['Under Managerial Review', 'Resolved'])): ?>
                                    <strong>Note:</strong> This case has been forwarded to management. Supervisors can no longer add notes or make changes.
                                <?php elseif (empty($note_types)): ?>
                                    <strong>Note:</strong> You do not have permission to add notes to this case.
                                <?php else: ?>
                                    <strong>Note:</strong> You cannot add notes at this time.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Resolve Case -->
                    <?php if ($grievance['status'] !== 'Resolved' && $grievance['status'] !== 'Closed' &&
                              in_array($account_type, ['superadmin', 'admin', 'manager'])): ?>
                    <div class="form-card">
                        <div class="card-header-modern">
                            <h5 class="text-success"><i class="fas fa-check-circle me-2"></i>Resolve Case</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="resolve_case">
                                <input type="hidden" name="form_token" value="<?php echo $form_token; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Resolution Details</label>
                                    <textarea class="form-control" name="resolution" rows="4" placeholder="Enter the resolution details..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm w-100" onclick="return confirm('Are you sure you want to resolve this case?')">
                                    <i class="fas fa-check me-2"></i>Resolve Case
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Workflow Information -->
                    <?php if ($account_type === 'supervisor' || $account_type === 'manager'): ?>
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-route me-2"></i>Workflow Process</h5>
                        </div>
                        <div class="card-body">
                            <div class="workflow-steps">
                                <?php if ($grievance['is_anonymous']): ?>
                                    <p><strong>Anonymous Case Workflow:</strong></p>
                                    <ol>
                                        <li>Employee submits anonymous grievance</li>
                                        <li>Superadmin assigns investigation team</li>
                                        <li>Investigation team conducts review</li>
                                        <li>Superadmin reviews and resolves</li>
                                    </ol>
                                <?php else: ?>
                                    <p><strong>Non-Anonymous Case Workflow:</strong></p>
                                    <ol>
                                        <li>Employee submits grievance</li>
                                        <li>Supervisor can review (optional parallel process)</li>
                                        <li><strong>Manager can take action at any time</strong></li>
                                        <li>Resolution by manager or escalation</li>
                                    </ol>
                                    <div class="alert alert-info mt-2">
                                        <small><i class="fas fa-info-circle me-1"></i>
                                        <?php if ($account_type === 'manager'): ?>
                                            As a manager, you can take action on this case regardless of supervisor review status.
                                        <?php else: ?>
                                            Supervisors handle initial review. Managers can take action at any stage.
                                        <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Access Level Information -->
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-shield-alt me-2"></i>Access Level: <?php echo ucfirst($account_type); ?></h5>
                        </div>
                        <div class="card-body">
                            <small class="text-muted">
                                <?php
                                echo match($account_type) {
                                    'superadmin' => 'Full access to all grievances and case management features including anonymous case investigation assignment.',
                                    'admin' => 'Access to all grievances and case management features. Can update status, add notes, and resolve cases.',
                                    'manager' => 'Access to ALL department cases in your location (' . htmlspecialchars($user['location'] ?? 'Unknown') . '). Can take action regardless of supervisor review status. Can update status, add notes, and resolve cases.',
                                    'supervisor' => 'Access to grievances from your location, department, and direct subordinates. Can update status and add notes. Cases may also be visible to managers.',
                                    default => 'View access only.'
                                };
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Auto-refresh page after status update to show changes
        <?php if ($success_message): ?>
        setTimeout(function() {
            // Remove the success message after 3 seconds and refresh
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }
        }, 3000);
        <?php endif; ?>

        // Clear URL parameters after success message is displayed
        <?php if ($success_message): ?>
        if (window.history && window.history.pushState) {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?id=<?php echo $grievance_id; ?><?php echo $grievance['is_anonymous'] ? '&type=anonymous' : ''; ?>";
            window.history.pushState({path: cleanUrl}, '', cleanUrl);
        }
        <?php endif; ?>

        // Status update confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const statusForm = document.querySelector('form[action*="update_status"]');
            if (statusForm) {
                statusForm.addEventListener('submit', function(e) {
                    const newStatus = document.querySelector('select[name="new_status"]').value;
                    const currentStatus = '<?php echo $grievance['status']; ?>';

                    if (newStatus !== currentStatus) {
                        const confirmed = confirm(`Are you sure you want to change status from "${currentStatus}" to "${newStatus}"?`);
                        if (!confirmed) {
                            e.preventDefault();
                        }
                    }
                });
            }

            // Auto-expand timeline entries
            const timelineItems = document.querySelectorAll('.timeline-item');
            timelineItems.forEach(item => {
                item.addEventListener('click', function() {
                    const cardBody = this.querySelector('.card-body');
                    cardBody.style.transition = 'all 0.3s ease';
                    cardBody.classList.toggle('expanded');
                });
            });
        });
    </script>
</body>
</html>