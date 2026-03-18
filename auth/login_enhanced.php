<?php
session_start();
require_once '../config/config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit();
}

$error_message = '';
$db = new Database();

if ($_POST) {
    $epf_number = trim($_POST['epf_number']);
    $password = $_POST['password'];

    if (empty($epf_number) || empty($password)) {
        $error_message = 'Please enter both EPF number and password.';
    } else {
        // Check if table has password_change_required column
        $columns = $db->fetchAll("DESCRIBE employees");
        $has_password_change_column = false;
        foreach ($columns as $column) {
            if ($column['Field'] === 'password_change_required') {
                $has_password_change_column = true;
                break;
            }
        }

        // Fetch user from database with proper SQL syntax
        if ($has_password_change_column) {
            $sql = "SELECT e.*, e.password_change_required,
                    at.type_name, at.level, l.location_name, el.level_name as employment_name
                    FROM employees e
                    LEFT JOIN account_types at ON e.account_type_id = at.id
                    LEFT JOIN locations l ON e.location_id = l.id
                    LEFT JOIN employment_levels el ON e.employment_level_id = el.id
                    WHERE e.epf_number = ?";
        } else {
            $sql = "SELECT e.*, 0 as password_change_required,
                    at.type_name, at.level, l.location_name, el.level_name as employment_name
                    FROM employees e
                    LEFT JOIN account_types at ON e.account_type_id = at.id
                    LEFT JOIN locations l ON e.location_id = l.id
                    LEFT JOIN employment_levels el ON e.employment_level_id = el.id
                    WHERE e.epf_number = ?";
        }

        // First check user exists and get their status
        $user_check = $db->fetch($sql, [$epf_number]);

        // Log the login attempt for debugging
        error_log("LOGIN_ENHANCED ATTEMPT: EPF=$epf_number, User found: " . ($user_check ? 'YES' : 'NO') .
                  ($user_check ? ', is_active=' . $user_check['is_active'] : ''));

        // Only proceed if user is active
        if (!$user_check || $user_check['is_active'] != 1) {
            $error_message = 'Invalid EPF number or account is inactive.';
            $user = null;
        } else {
            $user = $user_check;
        }

        if ($user && $user['is_active'] == 1) {
            // SECURITY: Only allow login if account has been explicitly activated by superadmin
            $password_valid = false;

            if (empty($user['password_hash'])) {
                // BLOCKED: No login allowed with empty password_hash
                $error_message = 'Account not activated. Please contact your administrator to activate your account.';
                error_log("LOGIN_ENHANCED BLOCKED: User {$epf_number} tried to login with unactivated account (empty password_hash)");
            } else {
                // Verify existing password (set by superadmin activation)
                $password_valid = password_verify($password, $user['password_hash']);

                // Also check if they're trying the default password
                if (!$password_valid && $password === DEFAULT_PASSWORD) {
                    $password_valid = password_verify(DEFAULT_PASSWORD, $user['password_hash']);
                }

                if (!$password_valid) {
                    error_log("LOGIN_ENHANCED FAILED: User {$epf_number} used wrong password");
                }
            }

            if ($password_valid) {
                // Check if password change is required
                if ($user['password_change_required']) {
                    // Store user data in session for password change page
                    $_SESSION['temp_user_id'] = $user['id'];
                    $_SESSION['temp_epf_number'] = $user['epf_number'];
                    $_SESSION['temp_name'] = $user['name'];
                    $_SESSION['password_change_required'] = true;

                    // Redirect to password change page
                    header('Location: change_password.php');
                    exit();
                } else {
                    // Normal login - set full session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['epf_number'] = $user['epf_number'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['account_type'] = $user['type_name'];
                    $_SESSION['account_type_id'] = $user['account_type_id'];
                    $_SESSION['account_level'] = $user['level'];
                    $_SESSION['location_id'] = $user['location_id'];
                    $_SESSION['location_name'] = $user['location_name'];
                    $_SESSION['employment_level'] = $user['employment_name'];
                    $_SESSION['department'] = $user['department'];
                    $_SESSION['reports_to'] = $user['reports_to'];
                    $_SESSION['last_activity'] = time();

                    // Redirect to dashboard
                    header('Location: ../dashboard.php');
                    exit();
                }
            } else {
                $error_message = 'Invalid password.';
            }
        } else {
            $error_message = 'Invalid EPF number or account is inactive.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
        }
        .default-credentials {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid #28a745;
            margin-top: 1rem;
        }
        .enhanced-notice {
            background: #e7f3ff;
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid #007bff;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="login-card">
                    <div class="login-header">
                        <h2><i class="fas fa-building"></i> SmartHRM</h2>
                        <p class="mb-0">Enhanced HR Management System</p>
                    </div>
                    <div class="login-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['password_changed'])): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Password changed successfully! Please login with your new password.
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['timeout'])): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-clock"></i> Your session has expired. Please login again.
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="epf_number" class="form-label">
                                    <i class="fas fa-id-card"></i> EPF Number
                                </label>
                                <input type="text" class="form-control" id="epf_number" name="epf_number"
                                       placeholder="Enter your EPF number" value="<?php echo isset($_POST['epf_number']) ? htmlspecialchars($_POST['epf_number']) : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password"
                                       placeholder="Enter your password" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-login w-100">
                                <i class="fas fa-sign-in-alt"></i> Sign In
                            </button>
                        </form>

                        <div class="enhanced-notice">
                            <h6><i class="fas fa-shield-alt"></i> Enhanced Security:</h6>
                            <small class="text-muted">If your password was recently reset by an administrator, you'll be asked to change it after logging in.</small>
                        </div>

                        <div class="default-credentials">
                            <h6><i class="fas fa-info-circle"></i> Default Login Credentials:</h6>
                            <p class="mb-1"><strong>EPF:</strong> Your EPF Number</p>
                            <p class="mb-0"><strong>Password:</strong> <?php echo htmlspecialchars(DEFAULT_PASSWORD); ?></p>
                            <small class="text-muted">Contact your administrator if you don't have access.</small>
                        </div>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <a href="login.php" class="text-decoration-none">← Use Original Login</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>