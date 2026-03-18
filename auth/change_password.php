<?php
session_start();
require_once '../config/config.php';

// Check if user needs to change password
if (!isset($_SESSION['password_change_required']) || !isset($_SESSION['temp_user_id'])) {
    header('Location: login.php');
    exit();
}

$error_message = '';
$success_message = '';
$db = new Database();

if ($_POST) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'New password must be at least 6 characters long.';
    } else {
        // Get current user data
        $user = $db->fetch("SELECT * FROM employees WHERE id = ?", [$_SESSION['temp_user_id']]);

        if ($user) {
            // SECURITY: Only allow password change if account was activated by superadmin
            $password_valid = false;
            if (empty($user['password_hash'])) {
                // BLOCKED: Account not activated by superadmin
                $error = 'Account not activated. Please contact your administrator to activate your account.';
                error_log("PASSWORD_CHANGE BLOCKED: User {$user['epf_number']} tried to change password on unactivated account");
            } else {
                // Check against stored hash (set by superadmin activation)
                $password_valid = password_verify($current_password, $user['password_hash']);
            }

            if ($password_valid) {
                // Update password and remove change requirement
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Check if columns exist
                $columns = $db->fetchAll("DESCRIBE employees");
                $available_columns = array_column($columns, 'Field');

                $update_fields = [
                    'password_hash' => $new_hashed_password,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                // Update password change requirement if column exists
                if (in_array('password_change_required', $available_columns)) {
                    $update_fields['password_change_required'] = 0;
                }

                // Update password reset timestamp if column exists
                if (in_array('password_reset_at', $available_columns)) {
                    $update_fields['password_reset_at'] = null;
                }

                // Build dynamic UPDATE query
                $set_clauses = [];
                $params = [];
                foreach ($update_fields as $field => $value) {
                    $set_clauses[] = "$field = ?";
                    $params[] = $value;
                }
                $params[] = $_SESSION['temp_user_id'];

                $sql = "UPDATE employees SET " . implode(', ', $set_clauses) . " WHERE id = ?";
                $result = $db->query($sql, $params);

                if ($result) {
                    // Get full user data for session
                    $full_user = $db->fetch("
                        SELECT e.*, at.type_name, at.level, l.location_name, el.level_name as employment_name
                        FROM employees e
                        LEFT JOIN account_types at ON e.account_type_id = at.id
                        LEFT JOIN locations l ON e.location_id = l.id
                        LEFT JOIN employment_levels el ON e.employment_level_id = el.id
                        WHERE e.id = ?", [$_SESSION['temp_user_id']]);

                    // Clear temporary session data
                    unset($_SESSION['temp_user_id']);
                    unset($_SESSION['temp_epf_number']);
                    unset($_SESSION['temp_name']);
                    unset($_SESSION['password_change_required']);

                    // Set full session
                    $_SESSION['user_id'] = $full_user['id'];
                    $_SESSION['epf_number'] = $full_user['epf_number'];
                    $_SESSION['name'] = $full_user['name'];
                    $_SESSION['account_type'] = $full_user['type_name'];
                    $_SESSION['account_type_id'] = $full_user['account_type_id'];
                    $_SESSION['account_level'] = $full_user['level'];
                    $_SESSION['location_id'] = $full_user['location_id'];
                    $_SESSION['location_name'] = $full_user['location_name'];
                    $_SESSION['employment_level'] = $full_user['employment_name'];
                    $_SESSION['department'] = $full_user['department'];
                    $_SESSION['reports_to'] = $full_user['reports_to'];
                    $_SESSION['last_activity'] = time();

                    // Redirect to dashboard
                    header('Location: ../dashboard.php?password_changed=1');
                    exit();
                } else {
                    $error_message = 'Failed to update password. Please try again.';
                }
            } else {
                $error_message = 'Current password is incorrect.';
            }
        } else {
            $error_message = 'User not found.';
        }
    }
}

$temp_user_name = $_SESSION['temp_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Change Password Required</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .password-change-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .password-change-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .password-change-body {
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
        .btn-change {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-change:hover {
            transform: translateY(-2px);
        }
        .password-strength-meter {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin-top: 5px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            transition: all 0.3s;
            border-radius: 4px;
        }
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-fair { background: #ffc107; width: 50%; }
        .strength-good { background: #fd7e14; width: 75%; }
        .strength-strong { background: #28a745; width: 100%; }
        .security-requirements {
            background: #f8f9fa;
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
            <div class="col-lg-6 col-md-8">
                <div class="password-change-card">
                    <div class="password-change-header">
                        <h2><i class="fas fa-shield-alt"></i> Password Change Required</h2>
                        <p class="mb-0">Welcome, <?php echo htmlspecialchars($temp_user_name); ?>!</p>
                    </div>
                    <div class="password-change-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Security Notice:</strong> Your password was recently reset by an administrator.
                            For security reasons, you must change your password before accessing the system.
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="passwordForm">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">
                                    <i class="fas fa-lock"></i> Current Password
                                </label>
                                <input type="password" class="form-control" id="current_password" name="current_password"
                                       placeholder="Enter your current password" required>
                                <div class="form-text">This is the password you just used to login</div>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">
                                    <i class="fas fa-key"></i> New Password
                                </label>
                                <input type="password" class="form-control" id="new_password" name="new_password"
                                       placeholder="Enter your new password" minlength="6" required>
                                <div class="password-strength-meter">
                                    <div class="password-strength-bar" id="strength_bar"></div>
                                </div>
                                <div class="form-text">
                                    <small id="strength_text">Minimum 6 characters required</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-check"></i> Confirm New Password
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                       placeholder="Confirm your new password" minlength="6" required>
                                <div class="form-text" id="match_text"></div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-change w-100" id="submit_btn">
                                <i class="fas fa-save"></i> Change Password & Continue
                            </button>
                        </form>

                        <div class="security-requirements">
                            <h6><i class="fas fa-info-circle"></i> Password Requirements:</h6>
                            <ul class="mb-0">
                                <li>Minimum 6 characters long</li>
                                <li>Different from your current password</li>
                                <li>Should be unique and not easily guessable</li>
                                <li>Consider using a mix of letters, numbers, and symbols</li>
                            </ul>
                        </div>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-sign-out-alt"></i>
                                <a href="logout.php" class="text-decoration-none">Logout and return to login</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submit_btn');
        const strengthBar = document.getElementById('strength_bar');
        const strengthText = document.getElementById('strength_text');
        const matchText = document.getElementById('match_text');

        newPasswordInput.addEventListener('input', function() {
            updatePasswordStrength(this.value);
            validateForm();
        });

        confirmPasswordInput.addEventListener('input', function() {
            checkPasswordMatch();
            validateForm();
        });

        function updatePasswordStrength(password) {
            if (password.length === 0) {
                strengthBar.className = 'password-strength-bar';
                strengthText.textContent = 'Minimum 6 characters required';
                return;
            }

            let score = 0;
            if (password.length >= 6) score++;
            if (password.length >= 8) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            const levels = [
                { class: 'strength-weak', text: 'Weak' },
                { class: 'strength-weak', text: 'Weak' },
                { class: 'strength-fair', text: 'Fair' },
                { class: 'strength-good', text: 'Good' },
                { class: 'strength-strong', text: 'Strong' },
                { class: 'strength-strong', text: 'Very Strong' }
            ];

            const level = levels[Math.min(score, 5)];
            strengthBar.className = 'password-strength-bar ' + level.class;
            strengthText.textContent = level.text + ' password';
        }

        function checkPasswordMatch() {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (confirmPassword.length === 0) {
                matchText.textContent = '';
                matchText.className = 'form-text';
                return;
            }

            if (newPassword === confirmPassword) {
                matchText.textContent = '✓ Passwords match';
                matchText.className = 'form-text text-success';
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.className = 'form-text text-danger';
            }
        }

        function validateForm() {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (newPassword.length < 6) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Password too short';
                return;
            }

            if (confirmPassword.length > 0 && newPassword !== confirmPassword) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Passwords do not match';
                return;
            }

            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Change Password & Continue';
        }

        // Prevent form submission if passwords don't match
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return false;
            }

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
                return false;
            }
        });
    </script>
</body>
</html>