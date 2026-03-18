# Separate Tables Approach for Anonymous Grievances

## 🎯 Architecture Overview

### Current Structure:
```
grievances (mixed anonymous + non-anonymous) ❌ Complex
├── is_anonymous flag
├── Complex conditional logic
└── Shared workflow confusion
```

### New Structure:
```
grievances (non-anonymous only) ✅ Clean
├── Normal supervisor → manager → resolved workflow
└── Employee details, department hierarchy

anonymous_grievances (anonymous only) ✅ Clean
├── Superadmin → investigation team → resolved workflow
├── No employee identity stored
├── Different status enum (Pending Investigation, Under Investigation, etc.)
└── Separate notes, evidence, and team tables
```

## 🗄️ Database Tables

### 1. `anonymous_grievances`
- **Purpose:** Store anonymous cases separately
- **Status Flow:** Pending Investigation → Under Investigation → Investigation Complete → Resolved/Dismissed
- **Key Fields:** case_id, category, urgency, subject, description, status, assigned_team_id

### 2. `anonymous_investigation_teams`
- **Purpose:** Investigation team assignments for anonymous cases
- **Structure:** team_leader + member1 + member2 + assigned_by (superadmin)

### 3. `anonymous_grievance_notes`
- **Purpose:** Timeline for anonymous cases
- **Action Types:** Submission, Investigation Assignment, Investigation Report, etc.
- **Features:** is_confidential flag for internal superadmin notes

### 4. `anonymous_grievance_evidence`
- **Purpose:** File attachments for anonymous cases

## 🔄 Unified Grievance Module View

### Grievance List (`grievance_list.php`)
```sql
-- Combined query to show both types
SELECT
    'regular' as type,
    id, case_id, employee_name as title, status, submission_date, urgency
FROM grievances
WHERE [user_access_conditions]

UNION ALL

SELECT
    'anonymous' as type,
    id, case_id, 'Anonymous Case' as title, status, submission_date, urgency
FROM anonymous_grievances
WHERE [user_access_conditions]

ORDER BY submission_date DESC
```

### Case Management (`case_management.php`)
- **Route by type:** `?id=14&type=anonymous` or `?id=14&type=regular`
- **Load appropriate data** from correct table
- **Show appropriate workflow** and timeline

## 🎯 Implementation Benefits

### ✅ Clean Separation
- **No more** `is_anonymous` flags
- **No more** complex conditional logic
- **Separate workflows** with appropriate fields

### ✅ Better Performance
- **Smaller tables** for each query type
- **Optimized indexes** for each workflow
- **No mixed queries** with complex WHERE conditions

### ✅ Easier Maintenance
- **Clear data models** for each case type
- **Separate note types** for each workflow
- **Independent status enums**

### ✅ Enhanced Security
- **Complete isolation** of anonymous data
- **Confidential notes** for superadmin only
- **No accidental data leakage**

## 🔧 Migration Steps

### 1. Create New Tables
Run: `create_anonymous_tables.sql`

### 2. Migrate Existing Anonymous Cases
```sql
INSERT INTO anonymous_grievances (...)
SELECT ... FROM grievances WHERE is_anonymous = 1;
```

### 3. Update Application Code
- Modify submission form to route to appropriate table
- Update grievance list to show unified view
- Create separate case management handlers

### 4. Clean Up Old Structure
- Remove `is_anonymous` column from grievances
- Remove anonymous-related ENUMs from regular tables

## 🧪 Testing Plan

### 1. Anonymous Workflow Test
1. Submit anonymous case → `anonymous_grievances` table
2. Superadmin assigns team → `anonymous_investigation_teams`
3. Team adds notes → `anonymous_grievance_notes`
4. Superadmin resolves → Status = 'Resolved'

### 2. Regular Workflow Test
1. Submit regular case → `grievances` table
2. Supervisor review → Manager review → Resolved
3. **Verify:** No impact from anonymous changes

### 3. Unified View Test
1. Check grievance list shows both types
2. Verify correct routing to case management
3. Confirm appropriate access controls

## 💫 Result

**Two completely separate, clean workflows:**
- **Regular cases:** Employee → Supervisor → Manager → Resolved
- **Anonymous cases:** Anonymous → Superadmin → Investigation Team → Resolved

**Unified user experience:**
- Single grievance module interface
- Combined case listing
- Appropriate workflow routing

**Much cleaner architecture! 🎉**