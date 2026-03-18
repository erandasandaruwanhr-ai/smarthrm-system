<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM Installation Wizard</title>
    <link href="css/installer.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="installer-container">
        <!-- Header -->
        <div class="installer-header">
            <h1><i class="fas fa-cog me-2"></i>SmartHRM Installation</h1>
            <p>Welcome to the SmartHRM System Installation Wizard</p>
        </div>

        <!-- Progress Indicator -->
        <div class="progress-container">
            <div class="progress-steps">
                <div class="step active" data-step="1">
                    <div class="step-icon">1</div>
                    <div class="step-label">Requirements</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-icon">2</div>
                    <div class="step-label">Database</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-icon">3</div>
                    <div class="step-label">Configuration</div>
                </div>
                <div class="step" data-step="4">
                    <div class="step-icon">4</div>
                    <div class="step-label">Installation</div>
                </div>
                <div class="step" data-step="5">
                    <div class="step-icon">5</div>
                    <div class="step-label">Complete</div>
                </div>
            </div>
        </div>

        <!-- Installation Content -->
        <div class="installer-content">

            <!-- Step 1: Requirements Check -->
            <div id="step-1" class="installation-step">
                <h2><i class="fas fa-check-circle me-2"></i>System Requirements</h2>
                <p>Checking your system to ensure SmartHRM can be installed successfully.</p>

                <div class="requirements-list" id="requirements-list">
                    <!-- Requirements will be populated by JavaScript -->
                    <div class="alert alert-info">
                        <i class="fas fa-spinner fa-spin me-2"></i>Checking requirements...
                    </div>
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="location.reload()">
                        <i class="fas fa-redo me-2"></i>Recheck
                    </button>
                    <button type="button" class="btn btn-primary btn-next" disabled>
                        Next <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>

            <!-- Step 2: Database Configuration -->
            <div id="step-2" class="installation-step" style="display: none;">
                <h2><i class="fas fa-database me-2"></i>Database Configuration</h2>
                <p>Configure your database connection settings.</p>

                <form id="database-form">
                    <div class="two-column">
                        <div class="form-group">
                            <label class="form-label" for="db_host">
                                <i class="fas fa-server me-1"></i>Database Host
                            </label>
                            <input type="text" class="form-control" id="db_host" name="db_host"
                                   value="localhost" required
                                   placeholder="localhost or IP address">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="db_port">
                                <i class="fas fa-plug me-1"></i>Database Port
                            </label>
                            <input type="number" class="form-control" id="db_port" name="db_port"
                                   value="3306" placeholder="3306">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="db_name">
                            <i class="fas fa-database me-1"></i>Database Name
                        </label>
                        <input type="text" class="form-control" id="db_name" name="db_name"
                               value="smarthrm_db" required
                               placeholder="smarthrm_db">
                        <small class="text-muted">Database will be created if it doesn't exist</small>
                    </div>

                    <div class="two-column">
                        <div class="form-group">
                            <label class="form-label" for="db_username">
                                <i class="fas fa-user me-1"></i>Database Username
                            </label>
                            <input type="text" class="form-control" id="db_username" name="db_username"
                                   required placeholder="Database username">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="db_password">
                                <i class="fas fa-lock me-1"></i>Database Password
                            </label>
                            <input type="password" class="form-control" id="db_password" name="db_password"
                                   placeholder="Database password">
                        </div>
                    </div>

                    <div class="button-group">
                        <button type="button" class="btn btn-info btn-test-connection">
                            <i class="fas fa-plug me-2"></i>Test Connection
                        </button>
                    </div>
                </form>

                <div id="config-preview" class="config-preview">
                    <!-- Configuration preview will be shown here -->
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-secondary btn-prev">
                        <i class="fas fa-arrow-left me-2"></i>Previous
                    </button>
                    <button type="button" class="btn btn-primary btn-next" disabled>
                        Next <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>

            <!-- Step 3: Admin Configuration -->
            <div id="step-3" class="installation-step" style="display: none;">
                <h2><i class="fas fa-user-shield me-2"></i>Administrator Account</h2>
                <p>Set up your system administrator account.</p>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    A default admin account (EPF: ADMIN001) will be created with the password you specify below.
                </div>

                <div class="form-group">
                    <label class="form-label" for="company_name">
                        <i class="fas fa-building me-1"></i>Company Name
                    </label>
                    <input type="text" class="form-control" id="company_name" name="company_name"
                           value="PB Pictures" placeholder="Your Company Name">
                </div>

                <div class="two-column">
                    <div class="form-group">
                        <label class="form-label" for="admin_password">
                            <i class="fas fa-key me-1"></i>Admin Password
                        </label>
                        <input type="password" class="form-control" id="admin_password" name="admin_password"
                               required placeholder="Minimum 8 characters">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="admin_password_confirm">
                            <i class="fas fa-key me-1"></i>Confirm Password
                        </label>
                        <input type="password" class="form-control" id="admin_password_confirm" name="admin_password_confirm"
                               required placeholder="Re-enter password">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="base_url">
                        <i class="fas fa-globe me-1"></i>Base URL
                    </label>
                    <input type="url" class="form-control" id="base_url" name="base_url"
                           value="<?php echo 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>/../"
                           placeholder="https://yourdomain.com/smarthrm/">
                    <small class="text-muted">The full URL where SmartHRM will be accessible</small>
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-secondary btn-prev">
                        <i class="fas fa-arrow-left me-2"></i>Previous
                    </button>
                    <button type="button" class="btn btn-success btn-install">
                        <i class="fas fa-download me-2"></i>Install SmartHRM
                    </button>
                </div>
            </div>

            <!-- Step 4: Installation Progress -->
            <div id="step-4" class="installation-step" style="display: none;">
                <h2><i class="fas fa-cog fa-spin me-2"></i>Installing SmartHRM</h2>
                <p>Please wait while we set up your SmartHRM system...</p>

                <div class="installation-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%;"></div>
                    </div>
                    <div class="progress-text">Preparing installation...</div>
                </div>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Do not close this window or navigate away during installation.
                </div>
            </div>

            <!-- Step 5: Installation Complete -->
            <div id="step-5" class="installation-step" style="display: none;">
                <h2><i class="fas fa-check-circle text-success me-2"></i>Installation Complete!</h2>

                <div class="alert alert-success">
                    <h4><i class="fas fa-party-horn me-2"></i>Congratulations!</h4>
                    <p>SmartHRM has been successfully installed on your server.</p>
                </div>

                <div class="config-preview">
                    <h4><i class="fas fa-info-circle me-2"></i>Important Information</h4>

                    <div class="config-item">
                        <span class="config-key">Admin EPF Number:</span>
                        <span class="config-value">ADMIN001</span>
                    </div>

                    <div class="config-item">
                        <span class="config-key">Admin Password:</span>
                        <span class="config-value">As configured in previous step</span>
                    </div>

                    <div class="config-item">
                        <span class="config-key">System URL:</span>
                        <span class="config-value" id="final-url"></span>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <h4><i class="fas fa-shield-alt me-2"></i>Security Recommendations</h4>
                    <ul>
                        <li>Delete or rename the <code>installer</code> directory</li>
                        <li>Change the default admin password after first login</li>
                        <li>Review and configure your server security settings</li>
                        <li>Set up regular database backups</li>
                    </ul>
                </div>

                <div class="button-group">
                    <a href="../dashboard.php" class="btn btn-success btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Login to SmartHRM
                    </a>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="installer-footer">
            <p>&copy; 2024 SmartHRM System. All rights reserved.</p>
        </div>
    </div>

    <script src="js/installer.js"></script>
</body>
</html>