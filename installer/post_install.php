<?php
/**
 * SmartHRM Post-Installation Cleanup
 * Run this after successful installation to secure the system
 */

require_once 'config/config.php';

// Check if installation is complete
if (!isInstallationComplete()) {
    header('Location: index.php');
    exit;
}

// Security check - only allow access for a short time after installation
$env_file = dirname(__DIR__) . '/.env';
$install_time = filemtime($env_file);
$current_time = time();

// Only allow access for 1 hour after installation
if ($current_time - $install_time > 3600) {
    die('Post-installation cleanup period has expired. Please delete the installer directory manually.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Post Installation</title>
    <link href="css/installer.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="installer-container">
        <div class="installer-header">
            <h1><i class="fas fa-shield-alt me-2"></i>Post-Installation Security</h1>
            <p>Secure your SmartHRM installation</p>
        </div>

        <div class="installer-content">
            <h2><i class="fas fa-exclamation-triangle text-warning me-2"></i>Important Security Steps</h2>

            <div class="alert alert-warning">
                <h4><i class="fas fa-shield-alt me-2"></i>Security Notice</h4>
                <p>For security reasons, you should complete these steps immediately:</p>
            </div>

            <div class="requirements-list">
                <div class="requirements-list-item requirement-warning" id="delete-installer">
                    <span><i class="fas fa-trash me-2"></i>Delete installer directory</span>
                    <button class="btn btn-sm btn-danger" onclick="deleteInstaller()">
                        <i class="fas fa-trash me-1"></i>Delete Now
                    </button>
                </div>

                <div class="requirements-list-item requirement-warning">
                    <span><i class="fas fa-key me-2"></i>Change default admin password</span>
                    <a href="../login.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-sign-in-alt me-1"></i>Login & Change
                    </a>
                </div>

                <div class="requirements-list-item requirement-warning">
                    <span><i class="fas fa-lock me-2"></i>Review file permissions</span>
                    <span class="status-icon status-warning">!</span>
                </div>

                <div class="requirements-list-item requirement-warning">
                    <span><i class="fas fa-server me-2"></i>Configure SSL/HTTPS (Recommended)</span>
                    <span class="status-icon status-warning">!</span>
                </div>
            </div>

            <div class="config-preview">
                <h4><i class="fas fa-info-circle me-2"></i>Installation Summary</h4>
                <div class="config-item">
                    <span class="config-key">Installation Date:</span>
                    <span class="config-value"><?php echo date('Y-m-d H:i:s', $install_time); ?></span>
                </div>
                <div class="config-item">
                    <span class="config-key">Admin Username:</span>
                    <span class="config-value">ADMIN001</span>
                </div>
                <div class="config-item">
                    <span class="config-key">System URL:</span>
                    <span class="config-value"><?php echo getBaseUrl(); ?></span>
                </div>
                <div class="config-item">
                    <span class="config-key">Database:</span>
                    <span class="config-value">Successfully configured</span>
                </div>
            </div>

            <div class="alert alert-info">
                <h4><i class="fas fa-lightbulb me-2"></i>Next Steps</h4>
                <ol>
                    <li>Delete this installer directory</li>
                    <li>Login and change the default password</li>
                    <li>Configure company settings</li>
                    <li>Add employee records</li>
                    <li>Set up user accounts</li>
                    <li>Configure module permissions</li>
                </ol>
            </div>

            <div class="button-group">
                <a href="../dashboard.php" class="btn btn-success btn-lg">
                    <i class="fas fa-home me-2"></i>Go to SmartHRM
                </a>
            </div>
        </div>

        <div class="installer-footer">
            <p>&copy; 2024 SmartHRM System. Installation completed successfully.</p>
        </div>
    </div>

    <script>
        async function deleteInstaller() {
            if (!confirm('Are you sure you want to delete the installer directory? This cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('includes/cleanup.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_installer' })
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('delete-installer').classList.remove('requirement-warning');
                    document.getElementById('delete-installer').classList.add('requirement-ok');
                    document.getElementById('delete-installer').innerHTML =
                        '<span><i class="fas fa-check me-2"></i>Installer directory deleted</span>' +
                        '<span class="status-icon status-ok">✓</span>';

                    setTimeout(() => {
                        window.location.href = '../dashboard.php';
                    }, 2000);
                } else {
                    alert('Error deleting installer: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        // Auto-redirect after 10 minutes for security
        setTimeout(() => {
            alert('For security reasons, you will be redirected to the login page.');
            window.location.href = '../login.php';
        }, 600000);
    </script>
</body>
</html>