<?php
/**
 * Permission Configuration for SmartHRM System
 * Defines comprehensive permission matrix for all account types
 */

// Enhanced Account Type Definitions
if (!defined('ENHANCED_ACCOUNT_TYPES')) {
    define('ENHANCED_ACCOUNT_TYPES', [
    1 => [
        'name' => 'User',
        'description' => 'Regular employee with access to own data only',
        'level' => 1,
        'data_access' => 'SELF',
        'color' => 'secondary'
    ],
    2 => [
        'name' => 'Supervisor',
        'description' => 'Team supervisor with access to direct reports',
        'level' => 2,
        'data_access' => 'REPORTS',
        'color' => 'info'
    ],
    3 => [
        'name' => 'Manager',
        'description' => 'Department manager with access to whole department',
        'level' => 3,
        'data_access' => 'DEPARTMENT',
        'color' => 'warning'
    ],
    4 => [
        'name' => 'Admin',
        'description' => 'Location admin with access to all data in their location',
        'level' => 4,
        'data_access' => 'LOCATION',
        'color' => 'primary'
    ],
    5 => [
        'name' => 'Superadmin',
        'description' => 'System administrator with access to all data across all locations',
        'level' => 5,
        'data_access' => 'ALL',
        'color' => 'danger'
    ]
    ]);
}

// Default Permission Presets for Each Account Type
if (!defined('PERMISSION_PRESETS')) {
    define('PERMISSION_PRESETS', [
    1 => [ // User
        'dashboard.view',

        // Employee - Self only
        'employee.view_own',
        'employee.edit_own',

        // Meal Management
        'meal.view',
        'meal.employee_request.view',
        'meal.employee_request.submit',
        'meal.employee_request.view_own',
        'meal.employee_request.edit_own',
        'meal.employee_request.delete_own',

        // Transport
        'transport.view',
        'transport.request.view_own',
        'transport.request.submit',
        'transport.request.edit_own',
        'transport.available_rides.view',
        'transport.available_rides.join',

        // Grievance
        'grievance.view',
        'grievance.submit.view',
        'grievance.submit.own',
        'grievance.my_grievances.view',
        'grievance.my_grievances.track',
        'grievance.my_grievances.update',
        'grievance.my_grievances.withdraw',

        // Requests
        'requests.view',
        'requests.submit.view',
        'requests.submit.create',
        'requests.submit.leave',
        'requests.submit.overtime',
        'requests.submit.expense',
        'requests.submit.training',
        'requests.submit.equipment',
        'requests.submit.general',
        'requests.my_requests.view',
        'requests.my_requests.track',
        'requests.my_requests.edit',
        'requests.my_requests.cancel',
        'requests.my_requests.resubmit',

        // Events
        'events.view',
        'events.calendar.view',
        'events.list.view',

        // Medical
        'medical.view',
        'medical.insurance.view_own',

        // Goals, Performance, etc.
        'goals.view',
        'goals.own.manage',
        'performance.view',
        'performance.self_appraisal',
        'jobs.view',
    ],

    2 => [ // Supervisor
        'dashboard.view',

        // Employee - Self + Reports
        'employee.view',
        'employee.view_own',
        'employee.view_reports',
        'employee.edit_own',
        'employee.edit_reports',
        'employee.list.view',
        'employee.list.search',
        'employee.list.filter',
        'employee.org_chart.view',
        'employee.data_monitor.view',

        // Meal Management
        'meal.view',
        'meal.employee_request.view',
        'meal.employee_request.submit',
        'meal.employee_request.view_own',
        'meal.employee_request.edit_own',
        'meal.employee_request.delete_own',
        'meal.employee_request.view_reports',
        'meal.employee_request.manage_reports',
        'meal.employee_counter.view',
        'meal.analytics.view',
        'meal.daily_overview.view',

        // Transport
        'transport.view',
        'transport.request.submit',
        'transport.request.view_own',
        'transport.request.view_reports',
        'transport.request.edit',
        'transport.allocation.view',
        'transport.available_rides.view',
        'transport.available_rides.join',
        'transport.reports.view',

        // Grievance
        'grievance.view',
        'grievance.submit.view',
        'grievance.submit.own',
        'grievance.my_grievances.view',
        'grievance.my_grievances.track',
        'grievance.list.view',
        'grievance.list.view_reports',
        'grievance.case_management.view',
        'grievance.case_management.add_notes',

        // Requests
        'requests.view',
        'requests.submit.create',
        'requests.my_requests.view',
        'requests.approval.view',
        'requests.approval.pending',
        'requests.approval.approve',
        'requests.approval.reject',
        'requests.approval.add_comments',
        'requests.list.view',
        'requests.list.view_reports',
        'requests.reports.view',

        // Events & Others
        'events.view',
        'medical.view',
        'medical.insurance.view_own',
        'goals.view',
        'goals.own.manage',
        'goals.team.manage',
        'performance.view',
        'performance.self_appraisal',
        'performance.team_appraisal',
        'jobs.view',
    ],

    3 => [ // Manager
        'dashboard.view',

        // Employee - Department level
        'employee.view',
        'employee.view_all_department',
        'employee.view_own',
        'employee.view_reports',
        'employee.edit_own',
        'employee.edit_reports',
        'employee.edit_department',
        'employee.add',
        'employee.list.view',
        'employee.list.search',
        'employee.list.filter',
        'employee.org_chart.view',
        'employee.org_chart.navigate',
        'employee.data_monitor.view',
        'employee.data_monitor.department',
        'employee.export',

        // Meal Management
        'meal.view',
        'meal.employee_request.submit',
        'meal.employee_request.view_all_department',
        'meal.employee_request.manage_department',
        'meal.visitor_request.view',
        'meal.visitor_request.submit',
        'meal.visitor_request.manage',
        'meal.employee_counter.view',
        'meal.visitor_counter.view',
        'meal.analytics.view',
        'meal.analytics.charts',
        'meal.analytics.filter',
        'meal.reports.view',
        'meal.reports.generate',
        'meal.reports.department',
        'meal.daily_overview.view',
        'meal.daily_overview.filter',
        'meal.request_details.view',
        'meal.request_details.filter',

        // Transport
        'transport.view',
        'transport.request.submit',
        'transport.request.view_all_department',
        'transport.request.edit',
        'transport.allocation.view',
        'transport.allocation.manage',
        'transport.available_rides.view',
        'transport.reports.view',
        'transport.reports.filter_department',

        // Grievance
        'grievance.view',
        'grievance.submit.own',
        'grievance.list.view_department',
        'grievance.case_management.view',
        'grievance.case_management.assign',
        'grievance.case_management.update_status',
        'grievance.case_management.add_notes',
        'grievance.case_management.escalate',
        'grievance.investigation_team.view',
        'grievance.investigation_team.assign_members',
        'grievance.reports.view',
        'grievance.reports.department',

        // Requests
        'requests.view',
        'requests.submit.create',
        'requests.approval.view',
        'requests.approval.approve',
        'requests.approval.reject',
        'requests.approval.add_comments',
        'requests.approval.delegate',
        'requests.list.view_department',
        'requests.list.filter',
        'requests.reports.view',
        'requests.reports.department',
        'requests.settings.view',
        'requests.settings.approval_workflow',

        // Others
        'events.view',
        'medical.view',
        'medical.insurance.view_own',
        'medical.insurance.view_department',
        'onboarding.view',
        'offboarding.view',
        'offboarding.resignation.approve',
        'training.view',
        'goals.view',
        'goals.department.manage',
        'performance.view',
        'performance.team_appraisal',
        'performance.reports.view',
        'jobs.view',
        'jobs.manage',
    ],

    4 => [ // Admin
        'dashboard.view',

        // Admin Panel
        'admin.view',
        'admin.system_config.view',
        'admin.system_config.edit',
        'admin.account_types.view',
        'admin.account_types.create',
        'admin.account_types.edit',
        'admin.locations.view',
        'admin.locations.create',
        'admin.locations.edit',
        'admin.employment_levels.view',
        'admin.employment_levels.create',
        'admin.employment_levels.edit',
        'admin.password_management.reset',
        'admin.password_management.policy',
        'admin.dropdown_management.view',
        'admin.dropdown_management.edit',

        // Employee - Location level
        'employee.view',
        'employee.view_all_location',
        'employee.add',
        'employee.edit_any_location',
        'employee.delete',
        'employee.bulk_upload',
        'employee.export',
        'employee.template',
        'employee.org_chart.view',
        'employee.org_chart.export',
        'employee.data_monitor.view',
        'employee.data_monitor.location',
        'employee.data_monitor.charts',
        'employee.access.view_all_departments_location',

        // Meal Management - Location level
        'meal.view',
        'meal.employee_request.view_all_location',
        'meal.employee_request.manage_all_location',
        'meal.visitor_request.manage',
        'meal.employee_counter.view',
        'meal.visitor_counter.view',
        'meal.analytics.view',
        'meal.analytics.charts',
        'meal.analytics.export',
        'meal.time_manager.view',
        'meal.time_manager.edit',
        'meal.reports.view',
        'meal.reports.generate',
        'meal.reports.export',
        'meal.daily_overview.view',
        'meal.daily_overview.export',
        'meal.request_details.view',
        'meal.request_details.export',
        'meal.admin.full_access',

        // Transport - Location level
        'transport.view',
        'transport.vehicle.manage',
        'transport.vehicle_pool.view',
        'transport.vehicle_pool.manage',
        'transport.service_providers.view',
        'transport.service_providers.add',
        'transport.service_providers.edit',
        'transport.drivers.view',
        'transport.drivers.manage',
        'transport.driver_pool.manage',
        'transport.request.view_all_location',
        'transport.allocation.view',
        'transport.allocation.manage',
        'transport.allocation.assign_vehicle',
        'transport.allocation.assign_service_provider',
        'transport.reports.view',
        'transport.reports.export',
        'transport.transport.approve_requests',

        // Grievance - Location level
        'grievance.view',
        'grievance.list.view_location',
        'grievance.list.view_all',
        'grievance.case_management.view',
        'grievance.case_management.assign',
        'grievance.case_management.update_status',
        'grievance.case_management.escalate',
        'grievance.case_management.close',
        'grievance.investigation_team.view',
        'grievance.investigation_team.manage',
        'grievance.investigation_team.assign_leader',
        'grievance.investigation_team.assign_members',
        'grievance.anonymous.view_assigned',
        'grievance.anonymous.investigate',
        'grievance.reports.view',
        'grievance.reports.export',
        'grievance.admin.workflow',

        // Requests - Location level
        'requests.view',
        'requests.approval.admin',
        'requests.list.view_location',
        'requests.list.view_all',
        'requests.reports.view',
        'requests.reports.generate',
        'requests.settings.view',
        'requests.settings.request_types',
        'requests.settings.approval_workflow',
        'requests.settings.templates',
        'requests.admin.system_settings',

        // Other modules - Location level
        'events.view',
        'medical.view',
        'medical.camp.manage',
        'medical.insurance.view_location',
        'medical.spectacles.manage',
        'onboarding.view',
        'onboarding.process.manage',
        'offboarding.view',
        'offboarding.resignation.approve',
        'offboarding.exit_interview.manage',
        'offboarding.clearance.manage',
        'training.view',
        'training.programs.manage',
        'goals.view',
        'goals.department.manage',
        'performance.view',
        'performance.reports.view',
        'talent.view',
        'talent.identification.manage',
        'jobs.view',
        'jobs.manage',
        'jobs.templates.manage',
    ],

    5 => 'ALL' // Superadmin gets all permissions
    ]);
}

/**
 * Enhanced Permission Matrix - Comprehensive permission structure for all 15 modules
 */
if (!defined('ENHANCED_PERMISSION_MATRIX')) {
    define('ENHANCED_PERMISSION_MATRIX', [
    'dashboard' => [
        'title' => 'Dashboard',
        'icon' => 'fas fa-home',
        'permissions' => [
            'view' => 'View Dashboard'
        ]
    ],

    'admin' => [
        'title' => 'Admin Panel',
        'icon' => 'fas fa-cogs',
        'permissions' => [
            'view' => 'View Admin Panel',

            // System Configuration
            'system_config.view' => 'View System Configuration',
            'system_config.edit' => 'Edit System Configuration',
            'system_config.all' => 'Full System Configuration Access',

            // Account Types
            'account_types.view' => 'View Account Types',
            'account_types.create' => 'Create Account Types',
            'account_types.edit' => 'Edit Account Types',
            'account_types.delete' => 'Delete Account Types',
            'account_types.all' => 'Full Account Types Management',

            // Permissions
            'permissions.view' => 'View Permissions',
            'permissions.edit' => 'Edit Permissions',
            'permissions.assign' => 'Assign Permissions',
            'permissions.central' => 'Access Central Permissions',
            'permissions.all' => 'Full Permission Management',

            // Locations
            'locations.view' => 'View Locations',
            'locations.create' => 'Create Locations',
            'locations.edit' => 'Edit Locations',
            'locations.delete' => 'Delete Locations',
            'locations.hierarchy' => 'Manage Location Hierarchy',
            'locations.all' => 'Full Location Management',

            // Employment Levels
            'employment_levels.view' => 'View Employment Levels',
            'employment_levels.create' => 'Create Employment Levels',
            'employment_levels.edit' => 'Edit Employment Levels',
            'employment_levels.delete' => 'Delete Employment Levels',
            'employment_levels.all' => 'Full Employment Levels Management',

            // Password Management
            'password_management.reset' => 'Reset User Passwords',
            'password_management.policy' => 'Set Password Policies',
            'password_management.force_change' => 'Force Password Change',
            'password_management.all' => 'Full Password Management',

            // Dropdown Management
            'dropdown_management.view' => 'View Dropdown Options',
            'dropdown_management.edit' => 'Edit Dropdown Options',
            'dropdown_management.create' => 'Create Dropdown Options',
            'dropdown_management.all' => 'Full Dropdown Management'
        ]
    ],

    'employee' => [
        'title' => 'Employee Management',
        'icon' => 'fas fa-users',
        'permissions' => [
            // Core Access
            'view' => 'View Employee Module',
            'view_own' => 'View Own Profile',
            'view_reports' => 'View Direct Reports',
            'view_department' => 'View Department Employees',
            'view_location' => 'View Location Employees',
            'view_all' => 'View All Employees',

            // Data Operations
            'add' => 'Add New Employees',
            'edit_own' => 'Edit Own Profile',
            'edit_reports' => 'Edit Direct Reports',
            'edit_department' => 'Edit Department Employees',
            'edit_location' => 'Edit Location Employees',
            'edit_any' => 'Edit Any Employee',
            'delete' => 'Delete Employees',

            // Bulk Operations
            'bulk_upload' => 'Bulk Upload Employees',
            'export' => 'Export Employee Data',
            'template' => 'Download Templates',

            // Organization Chart
            'org_chart.view' => 'View Organization Chart',
            'org_chart.navigate' => 'Navigate Org Structure',
            'org_chart.export' => 'Export Organization Chart',
            'org_chart.print' => 'Print Organization Chart',
            'org_chart.fullscreen' => 'View Fullscreen',

            // Data Monitor
            'data_monitor.view' => 'View Employee Analytics',
            'data_monitor.department' => 'View Department Analytics',
            'data_monitor.location' => 'View Location Analytics',
            'data_monitor.charts' => 'View Analytics Charts',
            'data_monitor.export' => 'Export Analytics Reports',

            // Employee List
            'list.view' => 'View Employee List',
            'list.search' => 'Search Employees',
            'list.filter' => 'Filter Employee List',

            // Access Control
            'access.view_all_locations' => 'View All Locations',
            'access.view_all_departments' => 'View All Departments',
            'access.edit_any' => 'Edit Any Employee',
            'access.delete_any' => 'Delete Any Employee'
        ]
    ],

    'meal' => [
        'title' => 'Meal Management',
        'icon' => 'fas fa-utensils',
        'permissions' => [
            'view' => 'View Meal Module',

            // Employee Requests
            'employee_request.view' => 'View Employee Requests Page',
            'employee_request.submit' => 'Submit Employee Requests',
            'employee_request.view_own' => 'View Own Requests',
            'employee_request.edit_own' => 'Edit Own Requests',
            'employee_request.delete_own' => 'Delete Own Requests',
            'employee_request.view_reports' => 'View Reports Requests',
            'employee_request.view_department' => 'View Department Requests',
            'employee_request.view_location' => 'View Location Requests',
            'employee_request.view_all' => 'View All Requests',
            'employee_request.manage_reports' => 'Manage Reports Requests',
            'employee_request.manage_department' => 'Manage Department Requests',
            'employee_request.manage_location' => 'Manage Location Requests',
            'employee_request.manage_all' => 'Manage All Requests',

            // Visitor Requests
            'visitor_request.view' => 'View Visitor Requests',
            'visitor_request.submit' => 'Submit Visitor Requests',
            'visitor_request.list' => 'View Request List',
            'visitor_request.edit' => 'Edit Requests',
            'visitor_request.delete' => 'Delete Requests',
            'visitor_request.manage' => 'Manage All Visitor Requests',

            // Counters
            'employee_counter.view' => 'View Employee Counter',
            'employee_counter.export' => 'Export Counter Data',
            'visitor_counter.view' => 'View Visitor Counter',
            'visitor_counter.export' => 'Export Visitor Data',

            // Analytics
            'analytics.view' => 'View Analytics',
            'analytics.charts' => 'View Charts',
            'analytics.export' => 'Export Analytics',
            'analytics.filter' => 'Filter Analytics',

            // Time Manager
            'time_manager.view' => 'View Time Settings',
            'time_manager.edit' => 'Edit Time Settings',
            'time_manager.deadlines' => 'Set Deadlines',
            'time_manager.slots' => 'Configure Slots',

            // Reports
            'reports.view' => 'View Reports',
            'reports.generate' => 'Generate Reports',
            'reports.export' => 'Export Reports',
            'reports.daily' => 'Daily Reports',
            'reports.monthly' => 'Monthly Reports',
            'reports.department' => 'Department Reports',
            'reports.location' => 'Location Reports',

            // Daily Overview
            'daily_overview.view' => 'View Daily Overview',
            'daily_overview.filter' => 'Filter Overview',
            'daily_overview.export' => 'Export Overview',

            // Request Details
            'request_details.view' => 'View Request Details',
            'request_details.search' => 'Search Requests',
            'request_details.filter' => 'Filter Details',
            'request_details.export' => 'Export Details',

            // Administrative
            'admin.full_access' => 'Full Administration',
            'admin.bulk_operations' => 'Bulk Operations'
        ]
    ],

    'transport' => [
        'title' => 'Transport Management',
        'icon' => 'fas fa-bus',
        'permissions' => [
            'view' => 'View Transport Module',

            // Vehicle Management
            'vehicle.manage' => 'Manage Vehicles',
            'vehicle_pool.view' => 'View Vehicle Pool',
            'vehicle_pool.manage' => 'Manage Vehicle Pool',

            // Service Providers
            'service_providers.view' => 'View Service Providers',
            'service_providers.add' => 'Add Service Providers',
            'service_providers.edit' => 'Edit Service Providers',
            'service_providers.delete' => 'Delete Service Providers',

            // Drivers
            'drivers.view' => 'View Drivers',
            'drivers.manage' => 'Manage Drivers',
            'driver_pool.manage' => 'Manage Driver Pool',

            // Requests
            'request.submit' => 'Submit Requests',
            'request.view_own' => 'View Own Requests',
            'request.view_reports' => 'View Reports Requests',
            'request.view_department' => 'View Department Requests',
            'request.view_location' => 'View Location Requests',
            'request.view_all' => 'View All Requests',
            'request.edit' => 'Edit Requests',
            'request.cancel' => 'Cancel Requests',
            'request.urgency' => 'Set Urgency',

            // Allocation
            'allocation.view' => 'View Allocations',
            'allocation.manage' => 'Manage Allocations',
            'allocation.assign_vehicle' => 'Assign Vehicles',
            'allocation.assign_service_provider' => 'Assign Service Providers',
            'allocation.start_trip' => 'Start Trips',
            'allocation.complete_trip' => 'Complete Trips',
            'allocation.cancel' => 'Cancel Allocations',

            // Available Rides
            'available_rides.view' => 'View Available Rides',
            'available_rides.join' => 'Join Rides',

            // Reports
            'reports.view' => 'View Reports',
            'reports.charts_tab' => 'View Charts',
            'reports.trips_tab' => 'View Trips',
            'reports.export' => 'Export Reports',
            'reports.filter_location' => 'Filter by Location',
            'reports.view_metrics' => 'View Metrics',

            // Administrative
            'transport.admin' => 'Full Administration',
            'transport.approve_requests' => 'Approve Requests',
            'transport.bulk_allocate' => 'Bulk Allocate'
        ]
    ],

    'grievance' => [
        'title' => 'Grievance Management',
        'icon' => 'fas fa-exclamation-triangle',
        'permissions' => [
            'view' => 'View Grievance Module',

            // Submit Grievance
            'submit.view' => 'View Submit Page',
            'submit.own' => 'Submit Grievances',
            'submit.anonymous' => 'Submit Anonymous',
            'submit.urgency' => 'Set Urgency',
            'submit.category' => 'Select Categories',
            'submit.attachments' => 'Upload Documents',

            // My Grievances
            'my_grievances.view' => 'View My Grievances',
            'my_grievances.track' => 'Track Status',
            'my_grievances.update' => 'Update Grievances',
            'my_grievances.withdraw' => 'Withdraw Grievances',
            'my_grievances.appeal' => 'Appeal Decisions',

            // Grievance List
            'list.view' => 'View List',
            'list.view_reports' => 'View Reports List',
            'list.view_department' => 'View Department List',
            'list.view_location' => 'View Location List',
            'list.view_all' => 'View All Grievances',
            'list.filter' => 'Filter Lists',
            'list.export' => 'Export Lists',

            // Case Management
            'case_management.view' => 'View Case Management',
            'case_management.assign' => 'Assign Investigators',
            'case_management.update_status' => 'Update Status',
            'case_management.add_notes' => 'Add Notes',
            'case_management.escalate' => 'Escalate Cases',
            'case_management.close' => 'Close Cases',
            'case_management.reopen' => 'Reopen Cases',
            'case_management.timeline' => 'View Timeline',

            // Investigation Team
            'investigation_team.view' => 'View Teams',
            'investigation_team.manage' => 'Manage Teams',
            'investigation_team.assign_leader' => 'Assign Leaders',
            'investigation_team.assign_members' => 'Assign Members',
            'investigation_team.view_assignments' => 'View Assignments',
            'investigation_team.conduct' => 'Conduct Investigations',
            'investigation_team.submit_report' => 'Submit Reports',

            // Anonymous Grievances
            'anonymous.view_assigned' => 'View Assigned Cases',
            'anonymous.investigate' => 'Investigate Cases',
            'anonymous.protect_identity' => 'Maintain Anonymity',
            'anonymous.special_handling' => 'Special Handling',

            // Reports
            'reports.view' => 'View Reports',
            'reports.kpi_cards' => 'View KPIs',
            'reports.category_breakdown' => 'Category Analysis',
            'reports.timeline' => 'Timeline Analysis',
            'reports.generate' => 'Generate Reports',
            'reports.export' => 'Export Reports',
            'reports.charts' => 'View Charts',
            'reports.department' => 'Department Reports',
            'reports.location' => 'Location Reports',

            // Administrative
            'admin.full_access' => 'Full Administration',
            'admin.workflow' => 'Configure Workflow',
            'admin.categories' => 'Manage Categories',
            'admin.settings' => 'Manage Settings'
        ]
    ],

    'requests' => [
        'title' => 'Employee Requests',
        'icon' => 'fas fa-clipboard-list',
        'permissions' => [
            'view' => 'View Requests Module',

            // Submit Requests
            'submit.view' => 'View Submit Page',
            'submit.create' => 'Submit Requests',
            'submit.leave' => 'Submit Leave',
            'submit.overtime' => 'Submit Overtime',
            'submit.expense' => 'Submit Expense',
            'submit.training' => 'Submit Training',
            'submit.equipment' => 'Submit Equipment',
            'submit.general' => 'Submit General',
            'submit.attachments' => 'Upload Documents',

            // My Requests
            'my_requests.view' => 'View My Requests',
            'my_requests.track' => 'Track Status',
            'my_requests.edit' => 'Edit Requests',
            'my_requests.cancel' => 'Cancel Requests',
            'my_requests.resubmit' => 'Resubmit Requests',

            // Approval Dashboard
            'approval.view' => 'View Approval Dashboard',
            'approval.pending' => 'View Pending',
            'approval.approve' => 'Approve Requests',
            'approval.reject' => 'Reject Requests',
            'approval.add_comments' => 'Add Comments',
            'approval.delegate' => 'Delegate Authority',
            'approval.bulk_approve' => 'Bulk Approve',
            'approval.supervisor' => 'Supervisor Approval',
            'approval.manager' => 'Manager Approval',
            'approval.admin' => 'Admin Approval',

            // Request Lists
            'list.view' => 'View Request Lists',
            'list.view_reports' => 'View Reports Requests',
            'list.view_department' => 'View Department Requests',
            'list.view_location' => 'View Location Requests',
            'list.view_all' => 'View All Requests',
            'list.filter' => 'Filter Lists',
            'list.export' => 'Export Lists',
            'list.details' => 'View Details',

            // Reports
            'reports.view' => 'View Reports',
            'reports.dashboard' => 'Reports Dashboard',
            'reports.generate' => 'Generate Reports',
            'reports.export' => 'Export Reports',
            'reports.trends' => 'View Trends',
            'reports.approval_time' => 'Approval Analysis',
            'reports.department' => 'Department Reports',
            'reports.location' => 'Location Reports',

            // Settings
            'settings.view' => 'View Settings',
            'settings.request_types' => 'Manage Types',
            'settings.approval_workflow' => 'Configure Workflow',
            'settings.templates' => 'Manage Templates',
            'settings.categories' => 'Manage Categories',
            'settings.notifications' => 'Configure Notifications',
            'settings.deadlines' => 'Set Deadlines',

            // Administrative
            'admin.full_access' => 'Full Administration',
            'admin.system_settings' => 'System Settings',
            'admin.workflow' => 'Configure Workflows'
        ]
    ],

    'events' => [
        'title' => 'Event Calendar',
        'icon' => 'fas fa-calendar',
        'permissions' => [
            'view' => 'View Event Calendar',
            'manage' => 'Manage Events',
            'calendar.view' => 'View Calendar',
            'list.view' => 'View Event List'
        ]
    ],

    'medical' => [
        'title' => 'Medical Management',
        'icon' => 'fas fa-heartbeat',
        'permissions' => [
            'view' => 'View Medical Module',
            'camp.manage' => 'Manage Medical Camps',
            'insurance.view_own' => 'View Own Insurance',
            'insurance.view_reports' => 'View Reports Insurance',
            'insurance.view_department' => 'View Department Insurance',
            'insurance.view_location' => 'View Location Insurance',
            'insurance.view_all' => 'View All Insurance',
            'spectacles.manage' => 'Manage Spectacles Claims'
        ]
    ],

    'onboarding' => [
        'title' => 'Employee Onboarding',
        'icon' => 'fas fa-user-plus',
        'permissions' => [
            'view' => 'View Onboarding Module',
            'process.manage' => 'Manage Process',
            'documents.manage' => 'Manage Documents',
            'evaluation.manage' => 'Manage Evaluations'
        ]
    ],

    'offboarding' => [
        'title' => 'Employee Offboarding',
        'icon' => 'fas fa-user-minus',
        'permissions' => [
            'view' => 'View Offboarding Module',
            'resignation.submit' => 'Submit Resignation',
            'resignation.approve' => 'Approve Resignations',
            'exit_interview.manage' => 'Manage Exit Interviews',
            'clearance.manage' => 'Manage Clearances'
        ]
    ],

    'training' => [
        'title' => 'Training Management',
        'icon' => 'fas fa-graduation-cap',
        'permissions' => [
            'view' => 'View Training Module',
            'programs.manage' => 'Manage Programs',
            'enrollments.manage' => 'Manage Enrollments',
            'evaluations.manage' => 'Manage Evaluations'
        ]
    ],

    'goals' => [
        'title' => 'Goal Setting',
        'icon' => 'fas fa-target',
        'permissions' => [
            'view' => 'View Goals Module',
            'own.manage' => 'Manage Own Goals',
            'team.manage' => 'Manage Team Goals',
            'department.manage' => 'Manage Department Goals'
        ]
    ],

    'performance' => [
        'title' => 'Performance Appraisal',
        'icon' => 'fas fa-chart-line',
        'permissions' => [
            'view' => 'View Performance Module',
            'self_appraisal' => 'Self Appraisal',
            'team_appraisal' => 'Team Appraisal',
            'reports.view' => 'View Performance Reports'
        ]
    ],

    'talent' => [
        'title' => 'Talent Management',
        'icon' => 'fas fa-star',
        'permissions' => [
            'view' => 'View Talent Module',
            'identification.manage' => 'Manage Identification',
            'succession.plan' => 'Succession Planning'
        ]
    ],

    'jobs' => [
        'title' => 'Job Descriptions',
        'icon' => 'fas fa-briefcase',
        'permissions' => [
            'view' => 'View Job Descriptions',
            'manage' => 'Manage Job Descriptions',
            'templates.manage' => 'Manage Templates'
        ]
    ]
    ]);
}

/**
 * Get all permissions for a specific account type
 * @param int $account_type_id
 * @return array
 */
function getAccountTypePermissions($account_type_id) {
    if ($account_type_id == 5) {
        // Superadmin gets all permissions
        $all_permissions = [];
        foreach (ENHANCED_PERMISSION_MATRIX as $module_key => $module) {
            foreach ($module['permissions'] as $permission_key => $permission_title) {
                $all_permissions[] = $module_key . '.' . $permission_key;
            }
        }
        return $all_permissions;
    }

    return PERMISSION_PRESETS[$account_type_id] ?? [];
}

/**
 * Initialize default permissions for all account types
 * @param Database $db
 */
function initializeDefaultPermissions($db) {
    try {
        // Clear existing permissions
        $db->query("DELETE FROM permission_assignments");

        foreach (ENHANCED_ACCOUNT_TYPES as $account_type_id => $account_info) {
            $permissions = getAccountTypePermissions($account_type_id);

            foreach ($permissions as $permission_key) {
                $db->query(
                    "INSERT INTO permission_assignments (account_type_id, permission_key, is_granted)
                     VALUES (?, ?, 1)",
                    [$account_type_id, $permission_key]
                );
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Failed to initialize default permissions: " . $e->getMessage());
        return false;
    }
}
?>