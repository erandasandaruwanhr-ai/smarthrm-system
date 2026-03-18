# SmartHRM Employee Requests Module - Complete Analysis

## 📁 Module Location
`C:\laragon\www\pbpictures\smarthrmjiffy\modules\requests`

## 📋 Module Overview
The Employee Requests module handles all employee service requests including salary slips, bank documents, service letters, and other administrative requests with a complete workflow system.

---

## 🗂️ PHP Files Structure

### **1. index.php**
**Purpose:** Main dashboard and module navigation hub
**Access Level:** All authenticated users with module permissions

**🔗 Navigation Links:**
- `submit_request.php` - "Submit New Request" card
- `my_requests.php` - "My Requests" card
- `all_requests.php` - "All Requests" card (Admin only)
- `team_requests.php` - "Team Requests" card (Supervisors)
- `reports.php` - "Reports" card (Admin/Manager)

**📊 Dashboard Features:**
- Quick stats cards showing request counts by status
- Recent requests overview
- Status distribution charts
- Access level filtering based on user role

---

### **2. submit_request.php**
**Purpose:** Employee request submission form
**Access Level:** All employees with `employee_requests.submit_request` permission

**🔘 Interactive Buttons & Elements:**
- **Cancel Button** - `onclick="window.location.href='index.php'"` - Returns to module dashboard
- **Submit Request Button** - `type="submit"` - Submits the request form
- **Request Type Dropdown** - Dynamically shows/hides conditional fields
- **Character Counters** - Real-time validation for text areas

**📝 Form Fields:**
- Employee Information (Auto-filled): EPF, Name, Location, Department, Employment Level
- Request Type: Salary Slip Originals, Bank Documents, Service Letter, Other
- Urgency Level: Normal, High, Urgent
- Subject (max 200 characters)
- Request Details (50-2000 characters)
- Reason/Justification (20-500 characters)
- Additional Comments (optional, max 1000 characters)

**🔄 Form Processing:**
- Auto-generates Request ID: `REQ-YYYY-XXXXXX`
- Validates required fields and character limits
- Stores request in `employee_requests` table
- Sets initial status to "Request Submitted"
- Sends notifications to employee and superadmin

**🔗 Navigation Links:**
- Breadcrumb: Dashboard → Employee Requests → Submit Request
- Cancel returns to `index.php`

---

### **3. my_requests.php**
**Purpose:** Employee's personal request history and management
**Access Level:** Employees with `employee_requests.view_own_requests` permission

**🔘 Action Buttons:**
- **New Request Button** - `href="submit_request.php"` - Create new request
- **View Details Button** - `btn-outline-primary` - Shows full request details in modal
- **Cancel Request Button** - `onclick="confirmCancel()"` - Cancels eligible requests
- **Alert Close Buttons** - `btn-close` - Dismisses success/error messages

**📋 Request Management:**
- Displays all requests for current user
- Status-based filtering and color coding
- Cancel functionality for requests in "Request Submitted" or "Documents Pending" status
- Request details modal with full information display

**🔄 POST Actions:**
- Cancel request functionality with status validation
- Automatic page refresh after status changes
- Success/error message handling

**🔗 Navigation Links:**
- Breadcrumb: Dashboard → Employee Requests → My Requests
- Submit new request link

---

### **4. all_requests.php**
**Purpose:** Administrative request management and workflow control
**Access Level:** Admin and Superadmin only

**🔘 Filter Buttons (Radio Button Group):**
- **All Requests** - `btn-outline-primary` - Shows all requests
- **Submitted** - `btn-outline-info` - Request Submitted status
- **Processing** - `btn-outline-secondary` - Processing status
- **Completed** - `btn-outline-success` - Completed status
- **Handovered** - `btn-outline-dark` - Handovered status

**🔘 Request Action Buttons:**
- **View Details Button** - `btn-outline-primary` - Shows request details modal
- **Handle Request Button** - `btn-outline-success` - Opens workflow management modal (Superadmin only)

**🔘 Modal Buttons:**
- **Close Buttons** - `btn-close` - Closes modals
- **Cancel Button** - `btn btn-secondary` - Cancels workflow actions
- **Update Status Button** - `btn btn-success` - Submits status updates

**📊 Administrative Features:**
- Complete request listing with search and filter
- Status workflow management (5-stage system)
- Request assignment and status tracking
- Status history display
- Bulk actions capability

**🔄 Workflow Management:**
- Status progression: Request Submitted → Documents Pending → Processing → Completed → Handovered
- Assignment to team members
- Comments and notes addition
- Status history tracking

**🔗 Navigation Links:**
- Breadcrumb: Dashboard → Employee Requests → All Requests

---

### **5. reports.php**
**Purpose:** Comprehensive reporting and analytics
**Access Level:** Admin, Manager, Superadmin

**🔘 Report Control Buttons:**
- **Generate Report Button** - Processes report criteria and displays results
- **Export Buttons** - Various export format options (CSV, PDF, Excel)
- **Filter Reset Button** - Clears all applied filters
- **Date Range Picker Buttons** - Quick date selection (This Month, Last Month, etc.)

**📊 Report Features:**
- Request status analytics
- Response time analysis
- Department/location breakdowns
- Trend analysis and charts
- Custom date range reporting
- Employee performance metrics

**🔄 Interactive Elements:**
- Dynamic chart generation
- Real-time filter application
- Export functionality
- Print options

---

### **6. team_requests.php**
**Purpose:** Supervisor/Manager team request oversight
**Access Level:** Supervisors and Managers

**🔘 Team Management Buttons:**
- **Review Request Button** - Opens request review interface
- **Approve/Reject Buttons** - Workflow decision actions
- **Forward Request Button** - Escalates to higher authority
- **Add Comments Button** - Adds supervisor notes

**📋 Team Features:**
- Team member request overview
- Approval workflow for direct reports
- Escalation capability
- Performance tracking

---

## 🔄 Workflow System

### **5-Stage Request Lifecycle:**
1. **Request Submitted** - Initial submission by employee
2. **Documents Pending** - Awaiting additional documentation
3. **Processing** - Under review and processing
4. **Completed** - Request fulfilled
5. **Handovered** - Final closure with delivery

### **Status Transitions:**
- Employees can cancel requests in stages 1-2
- Admins can move requests through all workflow stages
- Automatic notifications sent on status changes
- Complete audit trail maintained

---

## 🗄️ Database Integration

### **Primary Tables:**
- `employee_requests` - Main request storage
- `request_status_history` - Status change tracking
- `request_attachments` - File storage (planned)
- `request_workflow` - Workflow rules and assignments

### **Data Flow:**
- Form submission → Database storage → Notification dispatch → Workflow assignment

---

## 🔐 Permission Structure

### **Access Levels:**
- **All Employees:** Submit and view own requests
- **Supervisors:** Team request oversight
- **Managers:** Department reporting and oversight
- **Admin:** Full request management
- **Superadmin:** Complete workflow control and system configuration

### **Permission Keys:**
- `employee_requests.submit_request`
- `employee_requests.view_own_requests`
- `employee_requests.view_team_requests`
- `employee_requests.manage_all_requests`
- `employee_requests.generate_reports`

---

## 🔗 Module URLs

### **Direct Access URLs:**
```
http://localhost/pbpictures/smarthrmjiffy/modules/requests/
http://localhost/pbpictures/smarthrmjiffy/modules/requests/submit_request.php
http://localhost/pbpictures/smarthrmjiffy/modules/requests/my_requests.php
http://localhost/pbpictures/smarthrmjiffy/modules/requests/all_requests.php
http://localhost/pbpictures/smarthrmjiffy/modules/requests/team_requests.php
http://localhost/pbpictures/smarthrmjiffy/modules/requests/reports.php
```

### **Pre-filled Form URLs:**
```
submit_request.php?type=Salary%20Slip%20Originals
submit_request.php?type=Service%20Letter
submit_request.php?type=Bank%20Documents%20Fillup
```

---

## 📱 Responsive Features

### **Mobile Optimization:**
- Bootstrap 5 responsive design
- Collapsible sidebar navigation
- Mobile-friendly buttons and forms
- Touch-optimized interactions
- Responsive tables and modals

### **Cross-Browser Compatibility:**
- Modern JavaScript ES6+ features
- CSS Grid and Flexbox layouts
- Font Awesome icons
- Bootstrap component system

---

## 🔧 Technical Implementation

### **Frontend Technologies:**
- Bootstrap 5.1.3 - UI framework
- Font Awesome 6.0.0 - Icons
- JavaScript ES6+ - Interactive functionality
- CSS3 - Custom styling and animations

### **Backend Technologies:**
- PHP 8+ - Server-side processing
- MySQL/MariaDB - Database storage
- Custom Database class - ORM functionality
- Session management - User authentication

### **JavaScript Functions:**
- Character count validation
- Form submission validation
- Modal management
- Status filtering
- Export functionality
- Real-time updates

---

## 💾 File Storage & Attachments

### **Planned Features:**
- File upload capability (currently disabled)
- Document attachment system
- File type validation (PDF, JPG, PNG, DOCX, XLSX)
- File size limits (10MB per file, 5 files max)
- Secure file storage and retrieval

---

## 📊 Analytics & Reporting

### **Available Reports:**
- Request volume trends
- Status distribution analysis
- Department performance metrics
- Response time analytics
- Employee request patterns
- Approval rate statistics

### **Export Formats:**
- CSV for data analysis
- PDF for formal reports
- Excel for advanced manipulation
- Print-friendly formats

---

## 🔔 Notification System

### **Notification Triggers:**
- New request submission
- Status changes
- Assignment notifications
- Deadline reminders
- Completion notifications

### **Notification Recipients:**
- Request submitter
- Assigned handlers
- Supervisors and managers
- System administrators

---

This comprehensive analysis covers all functional elements, buttons, links, and features within the SmartHRM Employee Requests module as implemented in your local development environment.