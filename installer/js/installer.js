/**
 * SmartHRM Installer JavaScript
 * Handles installation process and user interactions
 */

class SmartHRMInstaller {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 5;
        this.requirements = {};
        this.config = {};
        this.init();
    }

    init() {
        this.bindEvents();
        this.updateProgress();

        // Auto-check requirements on load if on requirements step
        if (this.currentStep === 1) {
            this.checkRequirements();
        }
    }

    bindEvents() {
        // Next/Previous button handlers
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-next')) {
                this.nextStep();
            }
            if (e.target.classList.contains('btn-prev')) {
                this.prevStep();
            }
            if (e.target.classList.contains('btn-install')) {
                this.startInstallation();
            }
            if (e.target.classList.contains('btn-test-connection')) {
                this.testDatabaseConnection();
            }
        });

        // Form validation on input
        document.addEventListener('input', (e) => {
            if (e.target.classList.contains('form-control')) {
                this.validateField(e.target);
            }
        });

        // Auto-fill database form
        document.addEventListener('change', (e) => {
            if (e.target.name && e.target.name.startsWith('db_')) {
                this.updateConfigPreview();
            }
        });
    }

    async checkRequirements() {
        this.showLoading('Checking system requirements...');

        try {
            const response = await fetch('includes/requirements_check.php');
            const data = await response.json();

            this.requirements = data;
            this.displayRequirements();
            this.hideLoading();

        } catch (error) {
            this.showAlert('Error checking requirements: ' + error.message, 'danger');
            this.hideLoading();
        }
    }

    displayRequirements() {
        const container = document.getElementById('requirements-list');
        if (!container) return;

        let html = '';
        let allPassed = true;

        for (const [key, req] of Object.entries(this.requirements)) {
            const statusClass = req.status === 'ok' ? 'requirement-ok' :
                               req.status === 'warning' ? 'requirement-warning' : 'requirement-error';

            const iconClass = req.status === 'ok' ? 'status-ok' :
                             req.status === 'warning' ? 'status-warning' : 'status-error';

            const icon = req.status === 'ok' ? '✓' :
                        req.status === 'warning' ? '⚠' : '✗';

            if (req.status === 'error') allPassed = false;

            html += `
                <li class="requirements-list-item ${statusClass}">
                    <span>${req.name}</span>
                    <span class="status-icon ${iconClass}">${icon}</span>
                </li>
                ${req.message ? `<div class="requirement-message">${req.message}</div>` : ''}
            `;
        }

        container.innerHTML = html;

        // Enable/disable next button based on requirements
        const nextBtn = document.querySelector('.btn-next');
        if (nextBtn) {
            nextBtn.disabled = !allPassed;
            if (allPassed) {
                nextBtn.classList.remove('btn-secondary');
                nextBtn.classList.add('btn-primary');
            }
        }
    }

    async testDatabaseConnection() {
        const form = document.getElementById('database-form');
        const formData = new FormData(form);

        this.showLoading('Testing database connection...');

        try {
            const response = await fetch('includes/test_connection.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showAlert('Database connection successful!', 'success');
                document.querySelector('.btn-next').disabled = false;
            } else {
                this.showAlert('Database connection failed: ' + result.message, 'danger');
            }

        } catch (error) {
            this.showAlert('Error testing connection: ' + error.message, 'danger');
        } finally {
            this.hideLoading();
        }
    }

    updateConfigPreview() {
        const preview = document.getElementById('config-preview');
        if (!preview) return;

        const formData = new FormData(document.getElementById('database-form'));
        let html = '<h4>Configuration Preview</h4>';

        for (const [key, value] of formData.entries()) {
            if (key.startsWith('db_') && value) {
                const displayKey = key.replace('db_', '').toUpperCase();
                html += `
                    <div class="config-item">
                        <span class="config-key">${displayKey}:</span>
                        <span class="config-value">${key === 'db_password' ? '••••••••' : value}</span>
                    </div>
                `;
            }
        }

        preview.innerHTML = html;
    }

    validateField(field) {
        const value = field.value.trim();
        const name = field.name;
        let isValid = true;
        let message = '';

        // Reset classes
        field.classList.remove('error', 'success');
        this.hideFieldMessage(field);

        // Validation rules
        switch (name) {
            case 'db_host':
                if (!value) {
                    isValid = false;
                    message = 'Database host is required';
                }
                break;

            case 'db_name':
                if (!value) {
                    isValid = false;
                    message = 'Database name is required';
                } else if (!/^[a-zA-Z0-9_]+$/.test(value)) {
                    isValid = false;
                    message = 'Database name can only contain letters, numbers, and underscores';
                }
                break;

            case 'db_username':
                if (!value) {
                    isValid = false;
                    message = 'Database username is required';
                }
                break;

            case 'admin_password':
                if (!value) {
                    isValid = false;
                    message = 'Admin password is required';
                } else if (value.length < 8) {
                    isValid = false;
                    message = 'Password must be at least 8 characters long';
                }
                break;

            case 'admin_password_confirm':
                const originalPassword = document.querySelector('[name="admin_password"]').value;
                if (!value) {
                    isValid = false;
                    message = 'Please confirm your password';
                } else if (value !== originalPassword) {
                    isValid = false;
                    message = 'Passwords do not match';
                }
                break;
        }

        // Apply validation result
        if (!isValid) {
            field.classList.add('error');
            this.showFieldMessage(field, message, 'error');
        } else if (value) {
            field.classList.add('success');
        }

        return isValid;
    }

    showFieldMessage(field, message, type) {
        let messageEl = field.parentNode.querySelector('.error-message, .success-message');

        if (!messageEl) {
            messageEl = document.createElement('div');
            messageEl.className = type === 'error' ? 'error-message' : 'success-message';
            field.parentNode.appendChild(messageEl);
        }

        messageEl.textContent = message;
        messageEl.style.display = 'block';
    }

    hideFieldMessage(field) {
        const messageEl = field.parentNode.querySelector('.error-message, .success-message');
        if (messageEl) {
            messageEl.style.display = 'none';
        }
    }

    async startInstallation() {
        const form = document.getElementById('database-form');
        const formData = new FormData(form);

        // Show progress
        document.getElementById('installation-step').style.display = 'block';
        this.currentStep = 4;
        this.updateProgress();

        const progressBar = document.querySelector('.progress-fill');
        const progressText = document.querySelector('.progress-text');

        const steps = [
            { text: 'Creating database...', progress: 20 },
            { text: 'Creating tables...', progress: 40 },
            { text: 'Inserting data...', progress: 60 },
            { text: 'Setting up permissions...', progress: 80 },
            { text: 'Finalizing installation...', progress: 100 }
        ];

        try {
            for (let i = 0; i < steps.length; i++) {
                const step = steps[i];
                progressText.textContent = step.text;
                progressBar.style.width = step.progress + '%';

                await this.delay(1000); // Simulate work

                // Make actual API call for the step
                const response = await fetch('includes/install_step.php', {
                    method: 'POST',
                    body: JSON.stringify({ step: i + 1, ...Object.fromEntries(formData) }),
                    headers: { 'Content-Type': 'application/json' }
                });

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.message);
                }
            }

            // Installation complete
            this.showSuccess();

        } catch (error) {
            this.showAlert('Installation failed: ' + error.message, 'danger');
            progressText.textContent = 'Installation failed';
        }
    }

    showSuccess() {
        document.getElementById('success-step').style.display = 'block';
        this.currentStep = 5;
        this.updateProgress();
    }

    nextStep() {
        if (this.currentStep < this.totalSteps) {
            // Validate current step
            if (!this.validateCurrentStep()) {
                return;
            }

            document.getElementById(`step-${this.currentStep}`).style.display = 'none';
            this.currentStep++;
            document.getElementById(`step-${this.currentStep}`).style.display = 'block';
            this.updateProgress();

            // Add fade-in animation
            document.getElementById(`step-${this.currentStep}`).classList.add('fade-in');
        }
    }

    prevStep() {
        if (this.currentStep > 1) {
            document.getElementById(`step-${this.currentStep}`).style.display = 'none';
            this.currentStep--;
            document.getElementById(`step-${this.currentStep}`).style.display = 'block';
            this.updateProgress();
        }
    }

    validateCurrentStep() {
        switch (this.currentStep) {
            case 1:
                // Requirements check
                return !Object.values(this.requirements).some(req => req.status === 'error');

            case 2:
                // Database configuration
                const form = document.getElementById('database-form');
                const inputs = form.querySelectorAll('.form-control[required]');
                let isValid = true;

                inputs.forEach(input => {
                    if (!this.validateField(input)) {
                        isValid = false;
                    }
                });

                return isValid;

            default:
                return true;
        }
    }

    updateProgress() {
        // Update step indicators
        for (let i = 1; i <= this.totalSteps; i++) {
            const stepEl = document.querySelector(`[data-step="${i}"]`);
            if (!stepEl) continue;

            stepEl.classList.remove('active', 'completed');

            if (i < this.currentStep) {
                stepEl.classList.add('completed');
            } else if (i === this.currentStep) {
                stepEl.classList.add('active');
            }
        }
    }

    showLoading(message) {
        // Create or update loading indicator
        let loading = document.getElementById('loading-indicator');
        if (!loading) {
            loading = document.createElement('div');
            loading.id = 'loading-indicator';
            loading.className = 'alert alert-info';
            loading.innerHTML = '<span class="spinner"></span><span id="loading-message"></span>';
            document.querySelector('.installer-content').prepend(loading);
        }

        document.getElementById('loading-message').textContent = message;
        loading.style.display = 'block';
    }

    hideLoading() {
        const loading = document.getElementById('loading-indicator');
        if (loading) {
            loading.style.display = 'none';
        }
    }

    showAlert(message, type) {
        // Remove existing alerts
        document.querySelectorAll('.alert').forEach(alert => {
            if (alert.id !== 'loading-indicator') {
                alert.remove();
            }
        });

        const alert = document.createElement('div');
        alert.className = `alert alert-${type} fade-in`;
        alert.textContent = message;

        document.querySelector('.installer-content').prepend(alert);

        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => alert.remove(), 5000);
        }
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Initialize installer when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new SmartHRMInstaller();
});