# Anonymous Grievance Workflow Setup

## 🎯 Overview
The anonymous grievance workflow has been completely implemented with separate handling from non-anonymous cases:

**Anonymous Workflow:**
1. User submits anonymous grievance
2. Goes directly to **Superadmin only** (bypasses supervisors/managers)
3. Superadmin assigns investigation team
4. Investigation team adds notes and reports
5. Superadmin closes the case

**Non-Anonymous Workflow:** (Unchanged - still working perfectly)
1. User submits grievance → Supervisor → Manager → Resolved

---

## 🔧 Required Database Setup

### 1. Add New Status Values
Run: `http://localhost/pbpictures/smarthrmjiffy/modules/grievance/add_anonymous_status.php`

**Adds:**
- `'Pending Investigation'` - Anonymous cases waiting for team assignment
- `'Under Investigation'` - Anonymous cases with active investigation team

### 2. Add New Action Types
Run: `http://localhost/pbpictures/smarthrmjiffy/modules/grievance/fix_action_type_enum.php`

**Adds:**
- `'Investigation Assignment'`
- `'Investigation Progress'`
- `'Investigation Resolution'`

---

## 🔄 Anonymous Case Workflow

### Step 1: Submission
- User checks "Submit Anonymously"
- System creates case with:
  - `employee_epf` = 'ANONYMOUS'
  - `employee_name` = 'Anonymous'
  - `is_anonymous` = 1
  - `status` = 'Pending Investigation'

### Step 2: Superadmin Assignment
- **Only superadmin** can see anonymous cases
- Superadmin goes to: `investigation_team.php`
- Assigns Team Leader + 2 Members
- Status changes to: `'Under Investigation'`

### Step 3: Investigation
- **Investigation team members** can now access the case
- They can add notes with types:
  - `'Investigation Report'`
  - `'Evidence Added'`
  - `'Investigation Progress'`
  - `'Follow Up'`
- Team members **cannot change status**

### Step 4: Resolution
- **Only superadmin** can mark as `'Resolved'`
- Superadmin reviews investigation team notes
- Closes the case

---

## 👥 Access Control

| User Type | Anonymous Cases | Non-Anonymous Cases |
|-----------|----------------|-------------------|
| **Superadmin** | ✅ Full access, can assign teams, resolve | ✅ Full access |
| **Admin** | ❌ No access | ✅ Full access |
| **Manager** | ✅ Only if investigation team member | ✅ Department cases |
| **Supervisor** | ✅ Only if investigation team member | ✅ Location/subordinate cases |
| **Regular User** | ✅ Only if investigation team member | ❌ Own cases only |

---

## 🧪 Testing Steps

### 1. Test Anonymous Submission
1. Login as regular user (EPF: 342)
2. Go to: `submit_grievance.php`
3. Check "Submit Anonymously"
4. Submit grievance
5. **Verify:** Case shows in superadmin list only

### 2. Test Investigation Assignment
1. Login as superadmin
2. Open anonymous case
3. Use "Assign Investigation Team"
4. **Verify:** Status changes to "Under Investigation"

### 3. Test Investigation Team Access
1. Login as investigation team member
2. **Verify:** Can see and add notes to anonymous case
3. **Verify:** Cannot change case status

### 4. Test Non-Anonymous Still Works
1. Submit regular (non-anonymous) grievance
2. **Verify:** Goes to supervisor → manager workflow
3. **Verify:** Anonymous workflow doesn't affect it

---

## 🚨 Important Notes

- **Database scripts must be run first** before testing
- Non-anonymous workflow is **completely unchanged**
- Investigation team assignment requires superadmin access
- Anonymous cases are **completely isolated** from regular workflow

---

## 🔗 Key Files Modified

- `submit_grievance.php` - Separate anonymous vs non-anonymous submission logic
- `case_management.php` - Anonymous workflow, access control, investigation team access
- `investigation_team.php` - Already existed for team assignment
- Database ENUMs - Added new status and action types

**Anonymous workflow is now fully functional! 🎉**