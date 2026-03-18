<?php
// Team Requests - Redirect to All Requests
// Note: With the new 5-stage workflow system, all request management
// is handled by superadmins in all_requests.php
// No supervisor/manager approval is needed

header('Location: all_requests.php');
exit();
?>