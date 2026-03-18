<?php
/**
 * NOTIFICATION INTEGRATION EXAMPLES
 * Copy these code snippets into your module files to enable notifications
 * These examples show how to integrate notifications WITHOUT modifying existing functionality
 */

require_once '../includes/notification_system.php';

// Initialize notification system
$notifications = getNotificationSystem();

/*
============================================================================
EMPLOYEE MODULE INTEGRATION EXAMPLES
============================================================================
*/

// Example 1: When adding a new employee
// Add this code AFTER successfully inserting employee in the database
/*
// In your employee add/create process file:
if ($employee_added_successfully) {
    // Your existing success code here...

    // ADD THIS: Send notification
    $notifications = getNotificationSystem();
    $notifications->notifyEmployeeAdded($employee_name, $employee_epf);
}
*/

// Example 2: When updating employee information
/*
// In your employee update process file:
if ($employee_updated_successfully) {
    // Your existing success code here...

    // ADD THIS: Send notification
    $notifications = getNotificationSystem();
    $current_user_name = $_SESSION['name'] ?? 'Admin';
    $notifications->notifyEmployeeUpdated($employee_name, $current_user_name);
}
*/

/*
============================================================================
MEAL MODULE INTEGRATION EXAMPLES
============================================================================
*/

// Example 3: When a meal request is submitted
/*
// In your meal request submission file:
if ($meal_request_submitted_successfully) {
    // Your existing success code here...

    // ADD THIS: Send notification
    $notifications = getNotificationSystem();
    $notifications->notifyMealRequest($requestor_name, 'employee'); // or 'visitor'
}
*/

// Example 4: When approving a meal request
/*
// In your meal approval process file:
if ($meal_request_approved_successfully) {
    // Your existing success code here...

    // ADD THIS: Send notification
    $notifications = getNotificationSystem();
    $approver_name = $_SESSION['name'] ?? 'Admin';
    $notifications->notifyMealRequestApproved($requestor_name, $approver_name);
}
*/

/*
============================================================================
TRANSPORT MODULE INTEGRATION EXAMPLES
============================================================================
*/

// Example 5: When a transport request is submitted
/*
// In your transport request submission file:
if ($transport_request_submitted_successfully) {
    // Your existing success code here...

    // ADD THIS: Send notification
    $notifications = getNotificationSystem();
    $notifications->notifyTransportRequest($requestor_name, $destination);
}
*/

// Example 6: When vehicle needs maintenance
/*
// In your vehicle management file:
if ($maintenance_required) {
    // Your existing maintenance logging code here...

    // ADD THIS: Send notification
    $notifications = getNotificationSystem();
    $notifications->notifyVehicleMaintenance($vehicle_number, $maintenance_issue);
}
*/

/*
============================================================================
GRIEVANCE MODULE INTEGRATION EXAMPLES
============================================================================
*/

// Example 7: When a new grievance is submitted
/*
// In your grievance submission file:
if ($grievance_submitted_successfully) {
    // Your existing success code here...

    // ADD THIS: Send notification
    $notifications = getNotificationSystem();
    $is_anonymous = isset($anonymous) && $anonymous == true;
    $notifications->notifyNewGrievance($grievance_type, $is_anonymous);
}
*/

// Example 8: When grievance status is updated
/*
// In your grievance status update file:
if ($status_updated_successfully) {
    // Your existing success code here...

    // ADD THIS: Send notification
    $notifications = getNotificationSystem();
    $assigned_investigator = isset($investigator_name) ? $investigator_name : null;
    $notifications->notifyGrievanceStatusUpdate($grievance_id, $new_status, $assigned_investigator);
}
*/

/*
============================================================================
ADMIN MODULE INTEGRATION EXAMPLES
============================================================================
*/

// Example 9: When password is reset
/*
// In your password reset file:
if ($password_reset_successfully) {
    // Your existing success code here...

    // ADD THIS: Send notification
    $notifications = getNotificationSystem();
    $admin_name = $_SESSION['name'] ?? 'Admin';
    $notifications->notifyPasswordReset($employee_name, $admin_name);
}
*/

// Example 10: When user permissions are changed
/*
// In your user role/permission update file:
if ($permissions_updated_successfully) {
    // Your existing success code here...

    // ADD THIS: Send notification
    $notifications = getNotificationSystem();
    $admin_name = $_SESSION['name'] ?? 'Admin';
    $notifications->notifyPermissionChange($employee_name, $new_role, $admin_name);
}
*/

/*
============================================================================
CUSTOM NOTIFICATION EXAMPLES
============================================================================
*/

// Example 11: Custom notification for specific needs
/*
$notifications = getNotificationSystem();
$notifications->sendNotification(
    "Custom Notification Title",
    "Description of what happened",
    "employee", // or "meal", "transport", "grievance", "admin", "security", "system"
    [
        'action_needed' => true, // or false
        'module_link' => 'path/to/your/module.php',
        'target_users' => [123, 456], // specific user IDs
        'target_roles' => ['admin', 'manager'], // or specific roles
        'priority' => 'urgent', // 'low', 'medium', 'high', 'urgent'
        'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')) // optional expiry
    ]
);
*/

/*
============================================================================
INTEGRATION STEPS FOR EACH MODULE
============================================================================

1. EMPLOYEE MODULE:
   - Copy notification code into employee add/update/delete processes
   - Test by adding a new employee and check dashboard notifications

2. MEAL MODULE:
   - Add notifications to meal request submission
   - Add notifications to meal approval/rejection processes
   - Test by submitting and approving meal requests

3. TRANSPORT MODULE:
   - Add notifications to transport request submission
   - Add notifications to vehicle status updates
   - Test by requesting transport and updating vehicle status

4. GRIEVANCE MODULE:
   - Add notifications to grievance submission (both regular and anonymous)
   - Add notifications to status updates and assignments
   - Test by submitting grievances and updating their status

5. ADMIN MODULE:
   - Add notifications to password resets
   - Add notifications to permission/role changes
   - Add notifications for system maintenance
   - Test by resetting passwords and changing user roles

============================================================================
TESTING YOUR INTEGRATION
============================================================================

After adding notification code to any module:

1. Perform the action (add employee, submit request, etc.)
2. Go to Dashboard > Recent Activities tab
3. Check if the notification appears
4. Click the action button to test module linking
5. Verify the notification count updates correctly

============================================================================
*/

?>