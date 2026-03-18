/**
 * SmartHRM Notification System
 * Auto-adds notification bells to modules and sub-modules
 */

class NotificationSystem {
    constructor() {
        this.notifications = {};
        this.init();
    }

    init() {
        this.addNotificationBellsToActionCards();
        this.loadNotifications();
        this.startNotificationPolling();
    }

    /**
     * Automatically add notification bells to all action cards
     */
    addNotificationBellsToActionCards() {
        const actionCards = document.querySelectorAll('.action-card, .stats-card.action-card');

        actionCards.forEach((card, index) => {
            // Skip if already has a notification bell
            if (card.querySelector('.notification-bell-module')) return;

            // Create notification bell element
            const bellContainer = document.createElement('div');
            bellContainer.className = 'notification-bell-module';
            bellContainer.setAttribute('data-submodule', `action_${index}`);

            // Try to get a better identifier from the card
            const titleElement = card.querySelector('h4');
            if (titleElement) {
                const title = titleElement.textContent.toLowerCase()
                    .replace(/\s+/g, '_')
                    .replace(/[^a-z0-9_]/g, '');
                bellContainer.setAttribute('data-submodule', title);
            }

            bellContainer.innerHTML = `
                <i class="fas fa-bell"></i>
                <span class="notification-count-module hidden" data-count="0">0</span>
            `;

            // Insert the bell at the beginning of the card
            card.style.position = 'relative';
            card.insertBefore(bellContainer, card.firstChild);
        });
    }

    /**
     * Load notifications from server (placeholder for future implementation)
     */
    async loadNotifications() {
        try {
            // This will be implemented when you provide the notification logic
            // For now, no demo notifications - waiting for real implementation
            console.log('Notification system ready - waiting for real notification logic');
        } catch (error) {
            console.log('Notifications not yet implemented:', error.message);
        }
    }

    /**
     * Update notification count for sidebar modules
     * @param {string} type - 'sidebar' for main modules
     * @param {number} moduleId - Module ID (1-15)
     * @param {number} count - Notification count
     */
    updateNotificationCount(type, moduleId, count) {
        const selector = `[data-module="${moduleId}"] .notification-count`;
        const element = document.querySelector(selector);

        if (element) {
            element.textContent = count;
            element.setAttribute('data-count', count);

            if (count > 0) {
                element.classList.remove('hidden');
                element.style.display = 'flex';
            } else {
                element.classList.add('hidden');
                element.style.display = 'none';
            }

            // Add special classes for urgent notifications
            const bellIcon = element.parentElement.querySelector('i');
            if (count > 5) {
                bellIcon.classList.add('notification-bell-urgent');
            } else if (count > 0) {
                bellIcon.classList.add('notification-bell-new');
            }
        }
    }

    /**
     * Update notification count for module sub-pages
     * @param {string} submodule - Submodule identifier
     * @param {number} count - Notification count
     */
    updateModuleNotificationCount(submodule, count) {
        const selector = `[data-submodule="${submodule}"] .notification-count-module`;
        const element = document.querySelector(selector);

        if (element) {
            element.textContent = count;
            element.setAttribute('data-count', count);

            if (count > 0) {
                element.classList.remove('hidden');
                element.style.display = 'flex';
            } else {
                element.classList.add('hidden');
                element.style.display = 'none';
            }

            // Add special classes for urgent notifications
            const bellIcon = element.parentElement.querySelector('i');
            if (count > 5) {
                bellIcon.classList.add('notification-bell-module-urgent');
            } else if (count > 0) {
                bellIcon.classList.add('notification-bell-module-new');
            }
        }
    }

    /**
     * Start polling for notification updates (placeholder)
     */
    startNotificationPolling() {
        // Placeholder for future real-time notification updates
        // setInterval(() => {
        //     this.loadNotifications();
        // }, 30000); // Poll every 30 seconds
    }

    /**
     * Public method to manually update notifications
     */
    setNotification(type, identifier, count) {
        if (type === 'sidebar') {
            this.updateNotificationCount('sidebar', identifier, count);
        } else if (type === 'module') {
            this.updateModuleNotificationCount(identifier, count);
        }
    }

    /**
     * Get current notification counts
     */
    getNotifications() {
        return this.notifications;
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're not on the login page
    if (!window.location.pathname.includes('login')) {
        window.smartHRMNotifications = new NotificationSystem();
    }
});

// Global functions for easy access
window.setNotification = function(type, identifier, count) {
    if (window.smartHRMNotifications) {
        window.smartHRMNotifications.setNotification(type, identifier, count);
    }
};

window.updateSidebarNotification = function(moduleId, count) {
    window.setNotification('sidebar', moduleId, count);
};

window.updateModuleNotification = function(submodule, count) {
    window.setNotification('module', submodule, count);
};