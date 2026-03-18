# Grievance Module Access Levels Summary

## Access Control Matrix

| Role | Access Scope | What They Can See | What They Can Do |
|------|-------------|-------------------|------------------|
| **User (1.2.1)** | Own submissions only | Only their own grievances | View details, submit new grievances |
| **Supervisor (1.2.2)** | Location + Department + Subordinates | Grievances from same location AND department, plus direct subordinates (reports_to field) | Update status, add notes, case management |
| **Manager (1.2.3)** | Department across all locations | All cases from their department across any location | Update status, add notes, resolve cases, case management |
| **Admin (1.2.4)** | All (except anonymous details) | All grievances system-wide | Full case management, cannot assign investigation teams |
| **Superadmin (1.2.5)** | All (including anonymous) | All grievances including anonymous submitter details | Full access, can assign investigation teams |

## Key Access Rules

### Manager Access (Fixed)
- **Department**: Must match manager's department
- **Location**: Can see cases from any location within their department
- **Anonymous Cases**: Can see anonymous cases from their department, but identity remains hidden
- **Workflow Independence**: Can take action regardless of supervisor review status

### Supervisor Access
- **Location**: Must match supervisor's location
- **Department**: Must match supervisor's department
- **Subordinates**: Can see grievances from employees who report to them (based on employees.reports_to field)
- **Anonymous Cases**: Can see anonymous cases if they meet location/department criteria

### Anonymous Case Handling
- **Identity Protection**: Anonymous submitter details only visible to Superadmin
- **Access Rules**: Anonymous cases follow same location/department rules as non-anonymous
- **Workflow**: Anonymous cases have separate investigation team workflow

## SQL Filters Applied

### Manager Filter:
```sql
WHERE employee_department = ?
AND (is_anonymous = 0 OR (is_anonymous = 1 AND employee_epf = ?))
```

### Supervisor Filter:
```sql
WHERE (
    (employee_location = ? AND employee_department = ?) OR
    employee_epf IN (subordinate_epf_list)
)
AND (is_anonymous = 0 OR (is_anonymous = 1 AND employee_epf = ?))
```

## Workflow Process

### Non-Anonymous Cases:
1. Employee submits grievance
2. **Supervisor** can review (parallel process)
3. **Manager** can take action at any time (not dependent on supervisor)
4. Resolution by manager or escalation

### Anonymous Cases:
1. Employee submits anonymous grievance
2. **Superadmin** assigns investigation team
3. Investigation team conducts review
4. **Superadmin** reviews and resolves

## Database Dependencies

- **employees.epf_number**: Primary identifier
- **employees.reports_to**: Used for subordinate access (Plan.md 2.1.13)
- **employees.location_id / location_name**: Location-based access
- **employees.department**: Department-based access
- **grievances.is_anonymous**: Controls identity visibility
- **grievances.employee_location**: Location filter
- **grievances.employee_department**: Department filter

## Testing

Use the test page to verify access:
```
http://localhost/pbpictures/smarthrmjiffy/modules/grievance/test_access.php
```

This page shows:
- Current user's access level
- SQL queries being generated
- List of accessible grievances
- Debug information for troubleshooting