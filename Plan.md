# Dashboard
Dashboard page (side bar for 15 modules with scroll facility)

## 1. Admin Panel

### 1.1 System configuration

1.1.1 Color Management (Centralized overall system color Management) - (Edit, Save buttons)

1.1.2 Calendar Setup (date and time setup) - (Edit, Save buttons)

### 1.2 Account Types - (Add, Edit, Delete, Save buttons)

1.2.1 user

1.2.2 supervisor

1.2.3 manager

1.2.4 admin

1.2.5 superadmin

### 1.3 Permission Management - (Add, Edit, Delete, Save buttons)
Assign what each 1.2 account type can access. Use a checkbox for this. Five user types should be able to assign access separately.

### 1.4 Locations - (Add, Edit, Delete, Save buttons)

1.4.1 7C

1.4.2 Pannala

1.4.3 Kobeigane

1.4.4 JECOE

1.4.5 Head Office

### 1.5 Employment - (Add, Edit, Delete, Save buttons)

1.5.1 MD

1.5.2 GM

1.5.3 Manager

1.5.4 Assistant Manager

1.5.5 Senior Executive

1.5.6 Executive

1.5.7 Junior Executive

1.5.8 Supervisor

1.5.9 Staff

### 1.6 Password Management - (Password Reset button, Save button)
to reset the existing account's password) show all account list here when we add a employee to employee list "2.2" treat them as a account let them log into the system using default password smarthrm123@@@ and user name is there epf number. Password reset button & default password changing options will needed

### 1.7 Dropdown management - (Add, Edit, Delete, Save buttons)

1.7.1 Dropdown of 1.2

1.7.2 Dropdown of 1.4

1.7.3 Dropdown of 1.5

1.7.4 Dropdown of (Male / Female)

1.7.5 Dropdown of (Employee Meal, Employee Special, Seafood - Foreigner, Chicken -- Foreigner, Veg -- Foreigner, Chicken -- Local , Fish -- Local, Veg -- Local)

1.7.6 Dropdown of ( transport between two plant/ Government/ Banks / Purchasing/ Event / Training / Other)

1.7.7 Dropdown of (Finance, HR, IT,Maintenance, Material Processing, Production, QHS, Supply chain & Logistics)

1.7.8 Dropdown of (Professional (Career & Work),Financial (Money & Compensation),Behavioral (People & Conduct),Environment (Physical Workspace),Policy (Rules & Procedures),Safety (Health & Security),Discrimination (Unfair Treatment),Harassment (Inappropriate Behavior),Communication (Information & Feedback))

1.7.9 Dropdown of (Low, Medium, High, Critical)

1.7.10 Dropdown of (Open, In Progress, Resolved, Closed)
## 2. Employee Data (Add, Edit, Delete, Save buttons)

### 2.1 Employee data form (template download, bulk upload) - (Add, Edit, Delete, Save buttons)

2.1.1 EMP No

2.1.2 Name

2.1.3 Designation

2.1.4 Department (dropdown from 1.7.7)

2.1.5 NIC

2.1.6 Birthday

2.1.7 Age ( auto calculate from birthday 2.1.6)

2.1.8 Joined Date

2.1.9 Service (auto calculate from joined date 2.1.8)

2.1.10 Gender (dropdown from 1.7.4)

2.1.11 Employment (dropdown from 1.7.3)

2.1.12 Location (dropdown from 1.7.2)

2.1.13 Reports to ( assign by searching using epf and then save button)

### 2.2 Employee List - (Add, Edit, Delete, Save buttons)

2.2.1 Show "2.1" form data with filtering options with multiple filtering options.

### 2.3 Data Monitor (Add, Edit, Delete, Save buttons)

2.3.1 Show a Gender (Male vs Female) count graphical chart (location-wise filtering option)

2.3.2 Show a location-wise employee count graphical chart (Gender-wise filtering option)

2.3.3 Show a breakdown of the employee count in different Employment (from 2.2.1) (location-wise filtering option)

2.2.4 Age-wise graphical chart (location-wise filtering option) (age start with 18 -25, 26 -35, 36 -- 45,46-55,55-60, over 60)

2.2.5 Department-wise graphical chart

### 2.4 Organizational Chart (Structure) - (Add, Edit, Delete, Save buttons)

2.4.1 Display Type (dropdown: Tree View / List View)

2.4.1.1 Tree View: Visual diagram with boxes and connecting lines using only 90-degree angles (horizontal and vertical lines only, no diagonal lines). Lines form L-joints, T-joints, or 4-way joints. Lines never overlap boxes.

2.4.1.2 List View: Simple hierarchical text list

2.4.2 Filter by Location (dropdown from 1.7.2 - 7C, Pannala, Kobeigane, JECOE, Head Office, or "All Locations")

2.4.3 Filter by Department (dropdown from 1.7.7 or "All Departments")

2.4.4 Chart Display Rules:

2.4.4.1 Get employee data from 2.2 Employee List

2.4.4.2 Vertical positioning (levels) based on Employment type (from 2.2.1): 
- Level 1: MD (1.5.1)
- Level 2: GM (1.5.2)
- Level 3: Manager (1.5.3)
- Level 4: Assistant Manager (1.5.4)
- Level 5: Senior Executive (1.5.5)
- Level 6: Executive (1.5.6)
- Level 7: Junior Executive (1.5.7)
- Level 8: Supervisor (1.5.8)
- Level 9: Staff Groups (1.5.9)

2.4.4.3 Line connections based on actual "Reports To" relationships (from 2.1.13):
- Each employee box connects with a line to their assigned supervisor/manager's box (based on EPF number in 2.1.13)
- Lines may connect across multiple levels (e.g., an Executive might report directly to a Manager, skipping Assistant Manager and Senior Executive levels)
- Lines may connect horizontally within the same level (e.g., one Manager reporting to another Manager)
- The "Reports To" field determines ALL line connections, not the employment level (from 2.2.1)

2.4.4.4 Each employee shown in separate box except Staff (1.5.9)

2.4.4.5 For Staff: Group all staff reporting to same supervisor (from 2.1.13) in one box showing total count

2.4.5 Employee Box Display (for each box show):

2.4.5.1 Employee Name (from 2.1.2)

2.4.5.2 EPF Number (from 2.1.1)

2.4.5.3 Designation (from 2.1.3)

2.4.5.4 Employment Level (from 2.2.1)

2.4.5.5 Location (from 2.1.12)

2.4.6 Staff Box Display (grouped box):

2.4.6.1 "Staff" label

2.4.6.2 Reports to: [Supervisor Name and EPF from 2.1.13]

2.4.6.3 Total Count: [X Staff Members]

2.4.6.4 Location (from 2.1.12)

2.4.7 Tree View Layout Positioning:

**IMPORTANT:** Vertical level is determined by Employment type (1.5) (2.2.1), but horizontal position and line connections are determined by the "Reports To" field (2.1.13).

2.4.7.1 Level 1 (Top): MD (1.5.1) - Centered at top of chart

2.4.7.2 Level 2: GM (1.5.2) - All GMs positioned at Level 2, connected to whoever they report to via 2.1.13

2.4.7.3 Level 3: Manager (1.5.3) - All Managers positioned at Level 3, connected to whoever they report to via 2.1.13

2.4.7.4 Level 4: Assistant Manager (1.5.4) - All Assistant Managers positioned at Level 4, connected to whoever they report to via 2.1.13

2.4.7.5 Level 5: Senior Executive (1.5.5) - All Senior Executives positioned at Level 5, connected to whoever they report to via 2.1.13

2.4.7.6 Level 6: Executive (1.5.6) - All Executives positioned at Level 6, connected to whoever they report to via 2.1.13

2.4.7.7 Level 7: Junior Executive (1.5.7) - All Junior Executives positioned at Level 7, connected to whoever they report to via 2.1.13

2.4.7.8 Level 8: Supervisor (1.5.8) - All Supervisors positioned at Level 8, connected to whoever they report to via 2.1.13

2.4.7.9 Level 9 (Bottom): Staff Groups (1.5.9) - Staff groups positioned at Level 9, connected to their assigned Supervisor from 2.1.13

2.4.7.10 Vertical Spacing: Equal space between each level (minimum 80px between levels)

2.4.7.11 Horizontal Spacing: Equal space between boxes at same level (minimum 40px between boxes)

2.4.8 Line Connection Rules (90-degree angles only):

**Line connections are based on the "Reports To" field (2.1.13) - NOT on employment level proximity.**

2.4.8.1 Single Report: Straight line from parent box (person specified in Reports To field) to child box
- If parent is on the level directly above: Straight vertical line
- If parent is multiple levels above: Vertical line down from parent, may need horizontal routing, then vertical to child

2.4.8.2 Multiple Reports (one parent, multiple children): 
- Vertical line down from parent box bottom
- Horizontal line across to connect all children (regardless of their levels)
- Vertical lines down to each child box top
- Forms T-joint or 4-way joint where lines meet

2.4.8.3 Line Routing: Lines must not cross or overlap any boxes. Lines may travel horizontally across multiple levels to reach the correct parent from 2.1.13

2.4.8.4 Line Style: Solid lines, 2px width, use color from 1.1.1 Color Management

2.4.8.5 Cross-Level Connections: Lines can connect employees to supervisors multiple levels above (e.g., Executive reporting to GM, skipping Manager and Assistant Manager levels)

2.4.9 Visual Layout Example:

**IMPORTANT NOTE:** This example shows a simplified ideal structure where each level reports to the level directly above. In reality:
- The "Reports To" field (2.1.13) contains the EPF number of each employee's actual supervisor/manager
- This means lines can connect across multiple levels (e.g., an Executive might report directly to a GM)
- Lines can connect within the same level (e.g., one Manager reporting to another Manager)
- The chart must use the actual EPF-based relationships from 2.1.13, not assume level-by-level reporting

Example showing levels only (actual lines will vary based on 2.1.13 data):

```
                         ┌──────────────┐
                         │   MD Box     │  ← Level 1 (MD)
                         └──────┬───────┘
                                │
                         ┌──────┴───────┐
                         │   GM Box     │  ← Level 2 (GM)
                         └──────┬───────┘
                                │
                   ┌────────────┼────────────┐
                   │            │            │
            ┌──────┴──────┐ ┌──┴──────┐ ┌──┴──────┐
            │  Manager 1  │ │Manager 2│ │Manager 3│  ← Level 3 (Manager)
            └──────┬──────┘ └──┬──────┘ └──┬──────┘
                   │            │            │
            ┌──────┴──────┐ ┌──┴──────┐ ┌──┴──────┐
            │ Asst Mgr 1  │ │Asst Mgr2│ │Asst Mgr3│  ← Level 4 (Assistant Manager)
            └──────┬──────┘ └──┬──────┘ └──┬──────┘
                   │            │            │
            ┌──────┴──────┐ ┌──┴──────┐ ┌──┴──────┐
            │ Sr Exec 1   │ │Sr Exec 2│ │Sr Exec 3│  ← Level 5 (Senior Executive)
            └──────┬──────┘ └──┬──────┘ └──┬──────┘
                   │            │            │
            ┌──────┴──────┐ ┌──┴──────┐ ┌──┴──────┐
            │Executive 1  │ │Exec 2   │ │Exec 3   │  ← Level 6 (Executive)
            └──────┬──────┘ └──┬──────┘ └──┬──────┘
                   │            │            │
            ┌──────┴──────┐ ┌──┴──────┐ ┌──┴──────┐
            │ Jr Exec 1   │ │Jr Exec 2│ │Jr Exec 3│  ← Level 7 (Junior Executive)
            └──────┬──────┘ └──┬──────┘ └──┬──────┘
                   │            │            │
            ┌──────┴──────┐ ┌──┴──────┐ ┌──┴──────┐
            │Supervisor 1 │ │Superv 2 │ │Superv 3 │  ← Level 8 (Supervisor)
            └──────┬──────┘ └──┬──────┘ └──┬──────┘
                   │            │            │
            ┌──────┴──────┐ ┌──┴──────┐ ┌──┴──────┐
            │ Staff: 12   │ │Staff: 8 │ │Staff: 5 │  ← Level 9 (Staff Group)
            └─────────────┘ └─────────┘ └─────────┘

Legend:
│ = Vertical line (goes straight up/down)
─ = Horizontal line (goes straight left/right)
┬ = T-joint (one parent, multiple children)
┼ = 4-way joint (line crossing)
┌ ┐ └ ┘ = Box corners (90-degree angles)

Hierarchy Flow: MD → GM → Manager → Asst Manager → Sr Executive → Executive → Jr Executive → Supervisor → Staff
```

2.4.10 Export Options (buttons):

2.4.10.1 Export as PDF

2.4.10.2 Export as PNG Image

2.4.10.3 Print Chart

## 3. Meal Management

### 3.1 Meal Request Form 1 (for employees' daily meal) - (Save button)

3.1.1 EMP Number (autofill from the login details)

3.1.2 EMP Name (autofill from the login details)

3.1.3 EMP Location (autofill from the login details)

3.1.4 Meal Type (default to "Employee Meal" from "1.7.5)

3.1.5 Date (gives Select a 1-day -- 1-week from a calendar)

3.1.6 Breakfast -- (checkbox)

3.1.6.1 Count = 1

3.1.6.2 Countx = (manually enter any amount)

3.1.7 Snack 1

3.1.7.1 Countx = (manually enter any amount)

3.1.8 Lunch - (checkbox)

3.1.8.1 Count = 1

3.1.8.2 Countx = (manually enter any amount)

3.1.9 Snack 2 - (checkbox)

3.1.9.1 Count = 1

3.1.9.2 Countx = (manually enter any amount)

3.1.10 Dinner - (checkbox)

3.1.10.1 Count = 1

3.1.10.2 Countx = (manually enter any amount)

3.1.11 Snack 3 - (checkbox)

3.1.11.1 Countx = (manually enter any amount)

### 3.2 Meal Request Form 2 (for Visitors) - (Save button)

3.2.1 Requesting Person EMP No (autofill from the login details)

3.2.2 Requesting Person EMP Name (autofill from the login details)

3.2.3 Requesting Person EMP Location (autofill from the login details)

3.2.4 Breakfast Needed (checkbox)

3.2.4.1 Breakfast Menu (dropdown from "1.7.5" except "Employee Meal.")

3.2.4.2 Countxx = (manually enter any amount)

3.2.4.3 Special Remarks if any

3.2.5 Lunch Needed (checkbox)

3.2.5.1 Lunch Menu (dropdown from "1.7.5" except "Employee Meal.")

3.2.5.2 Countxx = (manually enter any amount)

3.2.5.3 Special Remarks if any

3.2.6 Dinner Needed (checkbox)

3.2.6.1 Dinner Menu (dropdown from "1.7.5" except "Employee Meal.")

3.2.6.2 Countxx = (manually enter any amount)

3.2.6.3 Special Remarks if any

3.2.7 Snack 1 Needed (checkbox)

3.2.7.1 Countxx = (manually enter any amount)

3.2.7.2 Special Remarks if any

3.2.8 Snack 2 Needed (checkbox)

3.2.8.1 Countxx = (manually enter any amount)

3.2.8.2 Special Remarks if any

### 3.3 Employee Meal Counter (counts 3.1 forms totals as follows)

#### 3.3.1 Breakfast

| | 7C | JECOE | Head Office | Pannala | Kobeigane |
|---|---|---|---|---|---|
| Breakfast | Total of Count | Total of Count | Total of Count | Total of Count | Total of Count |
| | Total of Countx | Total of Countx | Total of Countx | Total of Countx | Total of Countx |
| **Total** | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx |

#### 3.3.2 Lunch

| | 7C | JECOE | Head Office | Pannala | Kobeigane |
|---|---|---|---|---|---|
| Lunch | Total of Count | Total of Count | Total of Count | Total of Count | Total of Count |
| | Total of Countx | Total of Countx | Total of Countx | Total of Countx | Total of Countx |
| **Total** | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx |

#### 3.3.3 Snack 2

| | 7C | JECOE | Head Office | Pannala | Kobeigane |
|---|---|---|---|---|---|
| Snack 2 | Total of Count | Total of Count | Total of Count | Total of Count | Total of Count |
| | Total of Countx | Total of Countx | Total of Countx | Total of Countx | Total of Countx |
| **Total** | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx |

#### 3.3.4 Dinner

| | 7C | JECOE | Head Office | Pannala | Kobeigane |
|---|---|---|---|---|---|
| Dinner | Total of Count | Total of Count | Total of Count | Total of Count | Total of Count |
| | Total of Countx | Total of Countx | Total of Countx | Total of Countx | Total of Countx |
| **Total** | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx |

#### 3.3.5 Snack 3

| | 7C | JECOE | Head Office | Pannala | Kobeigane |
|---|---|---|---|---|---|
| Snack 3 | Total of Count | Total of Count | Total of Count | Total of Count | Total of Count |
| | Total of Countx | Total of Countx | Total of Countx | Total of Countx | Total of Countx |
| **Total** | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx | Total of Count + Total of Countx |

#### 3.3.6 Snack 1

| | 7C | JECOE | Head Office | Pannala | Kobeigane |
|---|---|---|---|---|---|
| Snack 1 | Total of Countxx | Total of Countxx | Total of Countxx | Total of Countxx | Total of Countxx |

### 3.4 Visitor Meal Counter -- (Count "3.2" form 2 Visitors Meal As follows)

#### 3.4.1 Breakfast

| | 7C | JECOE | Head Office | Pannala | Kobeigane |
|---|---|---|---|---|---|
| Breakfast | Total of Countxx | Total of Countxx | Total of Countxx | Total of Countxx | Total of Countxx |

#### 3.4.2 Lunch

| | 7C | JECOE | Head Office | Pannala | Kobeigane |
|---|---|---|---|---|---|
| Lunch | Total of Countxx | Total of Countxx | Total of Countxx | Total of Countxx | Total of Countxx |

#### 3.4.3 Dinner

| | 7C | JECOE | Head Office | Pannala | Kobeigane |
|---|---|---|---|---|---|
| Dinner | Total of Countxx | Total of Countxx | Total of Countxx | Total of Countxx | Total of Countxx |

#### 3.4.4 Snack 1

| | 7C | JECOE | Head Office | Pannala | Kobeigane |
|---|---|---|---|---|---|
| Snack 1 | Total of Countxx | Total of Countxx | Total of Countxx | Total of Countxx | Total of Countxx |

#### 3.4.5 Snack 2

| | 7C | JECOE | Head Office | Pannala | Kobeigane |
|---|---|---|---|---|---|
| Snack 2 | Total of Countxx | Total of Countxx | Total of Countxx | Total of Countxx | Total of Countxx |

### 3.5 Visitor meal request view - (Edit, Delete buttons)
Show every detail of "3.2" form 2 as a list

### 3.6 Graphical charts from 3.3 -- 3.4 tables

### 3.7 Meal request Time manager - (Edit, Save buttons)

3.7.1 Set a Time for Order finish date &times in form 1 (breakfast, lunch, dinner, snack 1, snack 2, snack 3 )

3.7.2 Set a time for the order finish date & time in form 2 ((breakfast, lunch, dinner, snack 1, snack 2)

## 4. Transport

### 4.1 Vehicle Register form - (Add, Edit, Delete, Save buttons)

4.1.1 Vehicle Type (van/car)

4.1.2 Plate No

4.1.3 Seat Capacity

4.1.4 Allocating location (dropdown from "1.4")

### 4.2 Vehicle Pool - (Edit button for vehicle in/out switch)

4.2.1 show vehicle list assign to each location ( use 4.1)

4.2.2 Vehicle in/out switch

### 4.3 Transport Request Form - (Save button)

4.3.1 Requesting EMP EPF no (get from login account details)

4.3.2 Requesting EMP Name

4.3.3 Requesting EMP Location

4.3.4 Needed for whom (myself/Team)

4.3.5 Needed for What (dropdown of 1.7.6)

4.3.6 Describe the trip (example -- head office to pannala)

4.3.7 Passenger Capacity (manual enterprise)

### 4.4 Driver pool - (Add, Delete buttons, Edit button for on/off switch)

4. 3.1 add and save drivers from the employee list (2.2) for the different 5 locations (1.4)

4.3.2 Mark driver is on duty or not ( on / off switch)

### 4.5 Transport Allocation - (Assign button, Save button)
overview of the request and allocation of drivers and vehicles from the vehicle pool (4.2 only in vehicles should show here) & the Driver pool (4.4 only on drivers should show here). Need facilities to assign many requests to 1 vehicle


## 5. Grievance Module
5.0.1 anonymous
5.0.2 non-anonymous
### 5.1 Grievance Submission Form - (Save, Submit buttons)

**Form Fields:**

5.1.1 Case ID (Auto-generated: GRV-YYYY-XXXXXX)

5.1.2 Employee EPF (autofill from login - `$user['epf_number']`)

5.1.3 Employee Name (autofill from login - `$user['name']`)

5.1.4 Employee Location (autofill from login - `$user['location_name']`)

5.1.5 Employee Department (autofill from login - `$user['department']`)

5.1.6 Anonymity Toggle (5.0.1 or 5.0.2checkbox - `is_anonymous` BOOLEAN - identity hidden from all except Superadmin"1.2.5")

5.1.7 Submission Date & Time (auto-captured - `submission_date` DATETIME)

5.1.8 Grievance Category (dropdown - 1.7.8 ):

5.1.9 Urgency Level (dropdown - 1.7.9):

5.1.10 Subject (text input, VARCHAR(200) - max 200 characters)

5.1.11 Detailed Description (textarea, TEXT - min 50, max 2000 characters with character counter)

5.1.12 Incident Date (date picker - DATE field)

5.1.13 Incident Location (dropdown - VARCHAR(50))

5.1.14 Witnesses (textarea - TEXT field for manual input or search)

5.1.15 Evidence Upload:
- Max 5 files
- Max 10MB per file
- Supported formats: PDF, JPG, JPEG, PNG, DOCX
- Stored in `grievance_evidence` table
- Fields: id, grievance_id, file_name, file_path, file_type, file_size, uploaded_at

**Database Structure:**

Main Table: `grievances`
```
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- case_id (VARCHAR(20), UNIQUE)
- employee_epf (VARCHAR(20), NOT NULL)
- employee_name (VARCHAR(100), NOT NULL)
- employee_location (VARCHAR(50))
- employee_department (VARCHAR(50))
- is_anonymous (BOOLEAN, DEFAULT FALSE)
- submission_date (DATETIME, NOT NULL)
- category (ENUM - 9 categories)
- urgency (ENUM - Low/Medium/High/Critical)
- subject (VARCHAR(200), NOT NULL)
- description (TEXT, NOT NULL)
- incident_date (DATE)
- incident_location (VARCHAR(50))
- witnesses (TEXT)
- status (ENUM: Open, Under Supervisory Review, Under Managerial Review, In Progress, Resolved, Closed, Reopened)
- assigned_investigator (INT)
- resolution (TEXT)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

Indexes:
- idx_employee_epf
- idx_status
- idx_category
- idx_urgency
- idx_submission_date

Supporting Table: `grievance_evidence`
```
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- grievance_id (INT, NOT NULL)
- file_name (VARCHAR(255))
- file_path (VARCHAR(500))
- file_type (VARCHAR(50))
- file_size (INT)
- uploaded_at (TIMESTAMP)
```

### 5.2 Case Management - (Assign, Update Status, Add Notes, Save buttons)

5.2.1 anonymous case handling - Manual Assignment (Superadmin only 5.1.6) - workfloor (raised by employee, assigned to the investigating team, report reviewed by superadmin, final decision by superadmin & resolved) 
- for the cases checked as anonymous "5.1.6", Superadmin can manually assign the case to the employees (investigating team (team Leader & two more members)) by searching the employees name or EPF number.
5.2.1.1 assigned users can view the case and do upload investigate report by team leader assigned (only he can upload the report)
5.2.1.2 review by the superadmin (only he can review the report)
5.2.1.3 final decision and case closed with remark (varcha: 500 max) 

this is the workflow for the anonymous cases and no anyone can see this than the grievence raised employee and the superadmin and assigned team. make sure to change the case status to as per the workfloor (raised by employee, assigned to the investigating team, report reviewed by superadmin, final decision by superadmin & resolved)

5.2.2 non-anonymous case handling - Auto Assignment (based on department) - workfloor (raised by employee, reporting supervisor review "1.2.2", reporting supervisor action, reporting supervisor's supervisor (manager "1.2.3") add extra note if need and close the case with remark (varcha: 500 max)). (superadmin can see the whole details)


### 5.3 Grievance List - (Filter, Edit, buttons)

5.3.1 Filters:
- Category (9 ENUM values)
- Status (7 ENUM values)
- Urgency (4 ENUM values)
- Location (from employee_location)
- Date range (submission_date)
- Assigned investigator (assigned_investigator ID)

5.3.2 Display Columns:
- Case ID (case_id)
- Category
- Status
- Priority Score (calculated)
- Urgency
- Days Open (DATEDIFF(NOW(), submission_date))
- SLA Status (calculated)
- Assigned To (JOIN with users table)
- Location (employee_location)
- Submission Date

5.3.3 Priority Score Calculation
- Formula: Category Weight × Days Outstanding
- Calculated on query, not stored

5.3.4 SLA Status Indicator
- Green/Yellow/Orange/Red based on urgency and days open
- Calculated dynamically

### 5.6 Reports

5.4.1 KPI Cards:
- Total Open Cases (COUNT WHERE status IN ('Open', 'In Progress', 'Under Supervisory Review', 'Under Managerial Review'))
- Overdue Cases (based on SLA rules)
- Average Resolution Time (AVG(DATEDIFF(updated_at, submission_date) WHERE status = 'Resolved'))
- SLA Compliance Rate (percentage)

5.4.2 Category Breakdown Chart
- GROUP BY category
- Filter by employee_location
- Show count and percentage

5.4.3 Case Timeline View
- Query case_notes joined with grievances
- Chronological display
- Include all timestamps and actions

5.4.4 Monthly Summary Report
- Cases by category, location, resolution rates
- Export as PDF

### Security Features
- Anonymity protection: `is_anonymous` flag
- Only Superadmin can view anonymous submitter details
- Access control based on user roles
- File upload security checks
- SQL injection prevention (prepared statements)

### Category Examples (Built-in Help Modal)
Each category includes description and examples:
- Professional: Training denial, unfair evaluations, workload issues
- Financial: Missing overtime, salary discrepancies, benefit errors
- Behavioral: Manager belittling, unprofessional conduct, favoritism
- Environment: Broken AC, poor lighting, inadequate facilities
- Policy: Denied vacation, inconsistent application, unclear procedures
- Safety: Unsafe conditions, missing equipment, health hazards
- Discrimination: Age/gender/race bias, religious discrimination
- Harassment: Intimidation, sexual harassment, bullying
- Communication: Lack of communication, missing information, no feedback

## 6. Employee Requests

## 6.1 Request Types - (Add, Edit, Delete, Save buttons)
6.1.1 Salary Slip Originals | 6.1.2 Bank Documents Fillup | 6.1.3 Service Letter | 6.1.4 Training | 6.1.5 Other

## 6.2 Request Submission Form - (Save, Submit buttons)
6.2.1 Request ID (Auto: REQ-YYYY-XXXXXX) | 6.2.2-6.2.6 Employee EPF, Name, Location, Department, Employment (autofill from login/2.2.1) | 6.2.7 Submission Date/Time (auto from 1.1.2) | 6.2.8 Request Type (dropdown from 6.1) | 6.2.9 Subject (max 200 chars) | 6.2.10 Details (50-2000 chars) | 6.2.11-6.2.12 Start/End Date (calendar 1.1.2, for Training) | 6.2.13 Reason (20-500 chars) | 6.2.14 Documents - optional (max 5 files, 10MB each: PDF/JPG/PNG/DOCX/XLSX) | 6.2.15 Additional Comments (max 1000 chars, optional) | 6.2.16 Urgency (Normal/High/Urgent)

## 6.3 Approval Workflow - (request raised, pending, processing, completed, handovered)

### 6.3.1 Workflow Stages
6.3.1.1 Request Submitted - when the request is requested by employee
6.3.1.2 documents pending - superadmin is not received the documents yet from the employee
6.3.1.3 processing  superadmin is processing the request
6.3.1.4 completed - superadmin is completed the request
6.3.1.5 handovered - superadmin is handovered the request to the employee or guardroom to send to employee)

### 6.3.3 Workflow Bypass (Superadmin 1.2.5 only)
6.3.3.1 Modify workflow stages

## 6.4 Notification - 
6.4.1 Request Submitted → Employee (confirmation) to superadmin
6.4.2 Documents Pending → Superadmin to employee
6.4.3 Processing → Superadmin to employee
6.4.4 Completed → Superadmin to employee
6.4.5 Handovered → Superadmin to employee

## 6.5 Request Management Dashboard - (Filter, Sort, Export buttons)

### 6.5.1 Dashboard Access Rules (Role-Based Data Display)
6.5.1.1 User (1.2.1) - Own requests only
6.5.1.2 Supervisor (1.2.2) - Own + direct reports (2.1.13 Reports To = their EPF)
6.5.1.3 Manager (1.2.3) - Own + direct reports + hierarchy (via 2.1.13) department, location or anyone who reports to this manager or anyone who reports to those supervisors who reports to this  manaeger,
6.5.1.4 Admin (1.2.4) - All in assigned location (2.1.12), review section, location
6.5.1.5 Superadmin (1.2.5) - All requests, all detail from all location all department
### 6.5.2 Dashboard Layout

#### 6.5.2.1 KPI Cards (get data from 2.2.1)
Total Requests (All Time), Pending, processing (This Month), handovered (This Month)


#### 6.5.2.3 Tab Navigation


#### 6.5.2.4 Charts & Analytics (Admin/Superadmin)


### 6.5.3 Filtering (Role-Based Access)


### 6.5.4 Sorting


### 6.5.5 Export (Role-Based Visibility)


## 6.6 Reports & Analytics - (Generate Report, Export buttons)

### 6.6.1 Request Summary Report


### 6.6.2 Request Details Report


## 7. Event Calendar

# Module 7: EVENT CALENDAR
## Event Showcase and Management

### Module Overview
Simple event showcase where superadmin displays planned company events with status tracking.

### Navigation Tabs
7.1. Event Management | 7.2. Calendar View | 7.3. Event List

---

## 7.1. Event Management - (Add, Edit, Delete, Save - Superadmin 1.2.5 only)

### 7.1.1. Add Event Form
- **7.1.1.1. Basic Information:**
  - Event ID (Auto: EVT-YYYY-XXXXXX)
  - Event Title (200 characters)
  - Event Description (500 characters)
  - Event Category (dropdown):
    * Training & Development
    * Welfare & Celebrations
    * Safety & Health
    * Quality & KAIZEN
    * Sports & Recreation
    * CSR Activities
    * Cultural Events
    * Annual Events
    * Other

- **7.1.1.2. Event Date & Time:**
  - Event Date (calendar from 1.1.2)
  - Start Time (from 1.1.2)
  - End Time (from 1.1.2)

- **7.1.1.3. Location:**
  - Location (dropdown from 1.7.2 or all)
  - Venue Details (text field)

- **7.1.1.4. Status:** 
  - Pending (default)
  - Done
  - Postponed
  - Cancelled

### 7.1.2. Edit Event (superadmin)
- Search event by ID or Title 
- Modify any field
- Update status
- Save changes

### 7.1.3. Delete Event (superadmin)
- Select event
- Confirmation required
- Permanent deletion


## 7.2. Calendar View

### 7.2.1. Display Options
- **7.2.1.1. Monthly Calendar:**
  - Traditional month view
  - Events shown on dates
  - Color-coded by status:
    * Pending: Yellow
    * Done: Green
    * Postponed: Blue
    * Cancelled: Red

- **7.2.1.2. List View:**
  - All events in chronological order
  - Sortable by date/status/category

### 7.2.2. Filtering
- Filter by Date Range (from 1.1.2)
- Filter by Status
- Filter by Category
- Filter by Location (from 1.7.2)

---

## 7.3. Event List

### 7.3.1. Event Display Table
- **Columns:**
  - Event ID
  - Event Title
  - Category
  - Date & Time
  - Location (from 1.7.2)
  - Status (Pending/Done/Postponed/Cancelled)
  - Actions (Edit/Delete)

### 7.3.2. Table Features
- Search by Title or ID
- Sort by any column
- Pagination (25/50/100 per page)
- Export to Excel/PDF

### 7.3.3. Status Update
- Click status dropdown
- Select new status
- Auto-save
- Color updates immediately

---

## Cross-Module Integration

### Input Dependencies:
- **1.1.2** - Calendar Setup for dates
- **1.2.5** - Superadmin access only but users can view the list (1.2.1)
- **1.7.2** - Location dropdown (1.7.2)

### Access Rules:
- Only Superadmin (1.2.5) can add/edit/delete events
- All users can view event calendar and list

---

## Implementation Priority: 7 (Medium)

---
## 8. Medical

# Module 8: Medical
# 8.3 Medical Insurance (OPD)

### 8.3.1 Medical Insurance Calendar System

8.3.1.1 Annual Coverage Period: February 9 (Year 1) to February 8 (Year 2)

Example: 2026 Feb 9 - 2027 Feb 8

8.3.1.2 Month Breakdown for Each Year (13 months):

| Order | Month | Coverage Range |
|---|---|---|
| 1 | February (Start) | Feb 9 – Feb 28/29 |
| 2 | March | Full Month |
| 3 | April | Full Month |
| 4 | May | Full Month |
| 5 | June | Full Month |
| 6 | July | Full Month |
| 7 | August | Full Month |
| 8 | September | Full Month |
| 9 | October | Full Month |
| 10 | November | Full Month |
| 11 | December | Full Month |
| 12 | January | Full Month |
| 13 | February (End) | Feb 1 – Feb 8 |

8.3.1.3 Year Pool (dropdown): 2023, 2024, 2025, 2026, 2027, 2028, 2029, 2030
- Superadmin (1.2.5) can add new years

8.3.1.4 Month Pool (for reference): 13 months as defined in 8.3.1.2
- February (9-28/29), March, April, May, June, July, August, September, October, November, December, January, February (1-8)

### 8.3.2 Allocating Limit for All Employees

8.3.2.1 Select year (year picker dropdown from 1.1.2)

8.3.2.2 Allocating Limit for all employees (125,000/= for 2026 this year) - this will be allocated to all employees

### 8.3.3 Medical Insurance Monthly Claim Form

8.3.3.1 Select year (year picker dropdown from 1.1.2) - (Allocated Limit: Get From 8.3.2.2)

8.3.3.2 Column 1 - EPF number (autofill from 2.2.1) show all employees

8.3.3.3 Column 2 - Name (autofill from 2.2.1) show all employees

8.3.3.4 Column 3 - Gender (autofill from 2.2.1) show all employees

8.3.3.5 Column 4 - Age (autofill from 2.2.1) show all employees

8.3.3.6 Column 5 - Location (autofill from 2.2.1) show all employees

8.3.3.7 Column 6 - Month (Dropdown from 8.3.1.4)

8.3.3.8 Column 7 - Requested Claim Amount (Manual Input)

8.3.3.9 Column 8 - Rejected Claim Amount (if available: Manual Input)

8.3.3.10 Column 9 - Claimed Amount (Manual Input)

8.3.3.11 Template download, upload, export, print

### 8.3.4 Balance Checker

8.3.4.1 Total Allocated Limit: Get from 8.3.2.2 (125,000/=)

8.3.4.2 Total Claimed Amount: Sum all months from 8.3.3.10 (if some months are not filled, sum filled only: 100,000/=)

8.3.4.3 Balance: (8.3.4.1 - 8.3.4.2 = 25,000/=)

8.3.4.4 Access Permissions:
- Each user (1.2.1) can see their balance
- Superadmin (1.2.5) can see all users balance
- Admin (1.2.4) can see all users balance under their location (if admin location is Pannala (1.4.2) then he can see all users balance under Pannala location)

### 8.3.5 Spectacles Claim Record (this is seperate from above)

8.3.5.1 EPF number (autofill from 2.2.1) show all employees

8.3.5.2 Name (autofill from 2.2.1) show all employees

8.3.5.3 Gender (autofill from 2.2.1) show all employees

8.3.5.4 Age (autofill from 2.2.1) show all employees

8.3.5.5 Location (autofill from 2.2.1) show all employees

8.3.5.6 Year (year picker dropdown from 1.1.2)

8.3.5.7 Month (Dropdown from 8.3.1.4)

8.3.5.8 Spectacles Purchase date (invoice Date - date picker from 1.1.2)

8.3.5.9 Requested Claim Amount (Manual Input)

8.3.5.10 Rejected Claim Amount (if available: Manual Input)

8.3.5.11 Claimed Amount (Manual Input)

8.3.5.13 All claimant spectacles details should be visible to:
- Admins (1.2.4): all details from same location where admin is
- Superadmins (1.2.5): all location all details
- Users (1.2.1): only his own

---

## Cross-Module Integration

### Input Dependencies:
- **1.1.2** - Calendar Setup
- **1.2.1** - User
- **1.2.4** - Admin
- **1.2.5** - Superadmin
- **1.4.2** - Pannala (example location)
- **1.7.2** - Location dropdown
- **2.2.1** - Employee List

### Access Rules:
- Superadmin (1.2.5): Full access, configure limits, view all data
- Admin (1.2.4): View data for their location only
- User (1.2.1): View only their own data

---

## Implementation Priority: 8 (Medium-High)

## 9. Onboarding (tracker) this is to superadmin to track the onboarding process of the employee.

- **9.1** safety induction
- **9.2** Select (checkbox) Absorbed from casual cardre / New Hire
- **9.3** CODE of CONDUCT(checkbox)
- **9.4** Training Evaluvation (checkbox)
- **9.5** Performance Evaluation(checkbox)
- **9.6** Aggreement (checkbox)
- **9.7** Non compete aggrement (checkbox)
- **9.8** Medical Insurance Letter (checkbox)
- **9.9** Confirmation Letter (checkbox)

## 10. Offboarding

10.1 Resignation Letter (form)
10.1.1 Employee Name (Autofil from loged account from 2.2.1)
10.1.2 Employee ID (Autofil from loged account from 2.2.2)
10.1.3 Designation (Autofil from loged account from 2.2.3)
10.1.4 Department (Autofil from loged account from 2.2.4)
10.1.5 Location (Autofil from loged account from 2.2.5)
10.1.6 Date of Resignation (Date picker from calendar)
10.1.7 Reason for Resignation (Manual Input)
10.1.8 Last Working Day (Date picker from calendar)
10.1.9 Submit

10.2 Resignation Accept (manager 1.2.3 from the 2.2 ) should be able to view the full details of the resignation letter and then accept or reject
10.2.1 Accept
10.2.2 Reject

10.3 If 10.2 = Accept "10.20.1" then show Exit Interview (form) to resignation submitter
10.3.1 Employee Name (Autofil from 10.1)
10.3.2 Employee ID (Autofil from 10.1)
10.3.3 Designation (Autofil from 10.1)
10.3.4 Department (Autofil from 10.1)
10.3.5 Location (Autofil from 10.1)
10.3.6 Date of Exit (Autofil from 10.1)
10.3.7 Reason for Exit (Autofil from 10.1)
10.3.8 What Did You Like About Working Here? (Manual Input)
10.3.9 What Did You Dislike About Working Here? (Manual Input)
10.3.10 What Would You Change About Working Here? (Manual Input)
10.3.11 What Would You Recommend to Improve Working Here? (Manual Input)
10.3.12 Most Supportive Person you worked with (Search option by name and emp 2.2 list)
10.3.13 Submit

10.4 clearance form (form)
10.4.1 Employee Name (Autofil from 10.1)
10.4.2 Employee ID (Autofil from 10.1)
10.4.3 Designation (Autofil from 10.1)
10.4.4 Department (Autofil from 10.1)
10.4.5 Location (Autofil from 10.1)
10.4.6 Date of Exit (Autofil from 10.1)
10.4.7 Reason for Exit (Autofil from 10.1)
10.4.8 IT Assets "cleared" (Manual Input)
10.4.9 Finance Clearance "cleared" (manual input)
10.4.10 HR Clearance "cleared" (manual input).
10.4.11 Stores "cleared" (manual input)
10.4.12 Location Clearance "cleared" (manual input)
10.4.13 Clearance evedance (PDF/JPGE Uplaod)
10.4.14 Submit

10.5 Final verifycation form superadmin after check the clearance form. (cleared (yes /no))

10.6 Good Bye! Notification, autogenerated after getting clearing for the form,
Dear "Name of the employee 2.2 ", your resignation has been Accepted and we have reviewed your exit process & it was completed. thank you for your "service 2.2" years service.

## 11. Training
11.1. Training Requirement (form)  show exising requirements & add new button with template download button gor bulk upload and bulk upload button
11.1.1 Year
11.1.2 Training Requirement
11.1.3 Training Type (awareness/certificate/Diploma/other)
11.1.4 Proposed Period for Training (1st Quarter / 2nd Quarter / 3rd Quarter / 4th Quarter)
11.1.5 Epf No (the required person)
11.1.6 Name (the required person)
11.1.7 Location (the requied person)
11.1.8 Department (the requied person)
11.1.9 Submit

11.2 Budget Prepare
11.2.1 Budget Year (show / load all data from match to this from 11.1.1)
11.2.2 show all the requiments from 11.1 from same year match to 11.2.2
11.2.3 show each row infront add to budget checkbox
11.2.4 Budget Amount (Manual Input)
11.2.5 Budget Approved by (Manager)

11.3 Training Budget (export button to export all details)
11.3.1 Budget Year (show / load all data from match to this from 11.2.2)
11.3.2 show all the add to budget checked from 11.2 from same year match to 11.3.1

11.4 Training Plan (export button to export all details)
11.4.1 show Training Name
11.4.1 Training Institute (add Manual)
11.4.2 Trainee EPF
11.4.3 Trainee Name
11.4.4 Training Cost
11.4.5 Training Start Date
11.4.6 Budgeted cost exceed percentage

11.5 Training Evaluation Form
11.5.1 Training Name (autofill from 11.4.1)
11.5.1.1 Training Institute (autofill from 11.4.1)
11.5.1.2 Training Start Date (autofill from 11.4.1)
11.5.1.3 Training End Date (autofill from 11.4.1)
11.5.1.4 Trainee EPF (autofill from 11.4.1)
11.5.1.5 Trainee Name (autofill from 11.4.1)
11.5.2 Logistics and Organization -##Before diving into the content, check if the environment was conducive to learning.## 
11.5.2.1  Registration process - Was it easy to sign up? (Y/N)
11.5.2.2 Environment: Was the room (or virtual platform) comfortable and functional?
11.5.2.3 Duration: Was the session too long, too short, or just right?
11.5.3 Content and Relevance
11.5.3.1 Objectives: Were the learning goals clearly stated and met? (Y/N)
11.5.3.2 Applicability: How relevant is this to your current role?
11.5.3.3 Pacing: Was the speed of delivery appropriate for the complexity of the topic?
11.5.3.4 Balance: Was there a good mix of theory and practical/hands-on exercises?
11.5.4 Instructor Effectiveness + A great curriculum can be ruined by poor delivery, and vice-versa.
11.5.4.1 Subject Matter Expertise: Did the trainer seem knowledgeable? (Y/N)
11.5.4.2 Engagement: Did they encourage participation and answer questions clearly? (Y/N)
Clarity: Was the presentation easy to follow? (Y/N , N/A)
11.5.5 Impact and Future Action (ROI) Use a scale (1-5) for these
11.5.5.1 QuestionRating (1-5)I can apply what I learned to my job immediately.
11.5.5.2 This training will improve my productivity/performance.
11.5.5.3 I would recommend this session to a colleague.
11.5.6 5. Open-Ended Feedback
11.5.6.1 What was the most valuable part of the training? 
11.5.6.2 What could be improved? 
11.5.6.3 Any other comments or suggestions?

11.6 Training Feedback & EffectivenessPurpose: To validate the transfer of knowledge and determine the return on investment (ROI) for the organization.
11.6.1 Administrative Reference
11.6.1.1 Training ID: (Linked to 11.4.1)
11.6.1.2 Trainee Name: (Autofill)
11.6.1.3 Evaluator Name: (Supervisor/Manager Name)
11.6.1.4 Review Date: (Typically 30–90 days post-training)
11.6.2 Post-Training Competency AssessmentTo be completed by the Supervisor based on observed performance.Ref #Competency CriteriaRating (1-5)
11.6.2.1 Skill Transfer: The employee demonstrates the new skills in their daily tasks.
11.6.2.2 Performance Improvement: There is a noticeable increase in quality or speed of work.
11.6.2.3 Knowledge Sharing: The employee has shared key takeaways with the team.
11.6.2.4 Autonomy: The employee requires less supervision on this specific topic than before.
11.6.3 Operational Impact
11.6.3.1 Critical Gap Closure: Has the specific skill gap identified in the training request been closed? (Y/N)11.6.3.2 Productivity Change: Since the training, has the employee’s output: (Increased / Remained Constant / Decreased)
11.6.3.3 Error Reduction: Has there been a decrease in errors related to this subject matter? (Y/N / N/A)
11.6.3.4 Submit for Manegerial Comments & Action Plan (11.7)

11.7 Managerial Comments & Action Plan - show a list for each submitted feedback 11.6 & all Evaluations submitted 11.5 . manager can view the data by pressing view button. then add his followings 
11.7.1 Supervisor Observations: Brief description of how the employee has applied the training.
11.7.2 Further Support Required: Does the employee need additional coaching or advanced training?
11.7.3 Overall Effectiveness Result: Was this training a cost-effective use of company resources? (Y/N)
11.7.4 Sign-off
11.7.5 Supervisor Name: (autofill)
11.7.6 Date: (timelaps mark auto)

11.8 Training Tracker
11.8.1 Training ID: (Linked to 11.4.1)
11.8.2 Trainee Name: (Autofill)
11.8.3 Training Title: (Autofill)
11.8.4 Training Date: (Autofill)
11.8.5 Status: (Pending / Completed / Cancelled)
11.8.6 Evaluation  Submitted: if completed (Y/N), if cancelled (N/A)
11.8.7 Feedback Submitted: if completed (Y/N), if cancelled (N/A)
11.8.8 Managerial Comments & Action Plan Submitted: if completed (Y/N), if cancelled (N/A)

11.9.A List of Evaluation (export button to export all details)

11.9.B List of Feedback (export button to export all details)


## 12. Goal Setting

12.1 KPI Graphs by department wise (just keep space on top of all followings will be implemented after the whole module of 12 Goal Setting)

12.2 Executive Appraisal List( this is just a list that will work on the whole module of 12 Goal Setting) shows all employees exist in 2.2 list (but only show the employees who has the employement level(1.5)match with (1.5.7 / 1.5.6 / 1.5.5 / 1.5.4 / 1.5.3))
12.2.1 EPF No (autofill by 2.2)
12.2.2 Name (autofill by 2.2)
12.2.3 Designation (autofill by 2.2)
12.2.4 Department (autofill by 2.2)
12.2.5 Location (autofill by 2.2)
12.2.6 Joining Date (autofill by 2.2)
12.2.7 Service years (autofill by 2.2)
12.2.8 Immediate Supervisor / Department Head EPF No (autofill by 2.2) ## if the employ report to a Executive (1.5.6) get his supervisor's supervisor. always 12.2.8 should be match with one of these (1.5.2 /1.5.3) according to hierachy.
12.2.9 Immediate Supervisor / Department Head Name (autofill by 2.2) ## if the employ report to a superisor (1.5.6) get his supervisor's supervisor. always 12.2.8 should be match with one of these (1.5.2/1.5.3) according to hierachy.

12.3 Goal Setting Form Setup Page (this is where we create each year appraisal form for staff level)
12.3.1 Year (Select Manually by Dropdown) Period 01 January - 31 December. after selecting the year, the system will show the following settup form where superadmin can settup that selected year form and allocations.
12.3.2 Select Goal Setting Period (dropdown of 01.01. Year - (Selected Year in 12.3.1) 31.12. Year  - (Selected Year in 12.3.1))
12.3.3 Goal Setting Form (following is the form)

| Goal S/N # | Main Goals | Activities | Measurement Criteria *(How to Measure the Outcome)* / Eg. Target / Time Deadline | Weightage *(%)* | Mid-Year Progress *(YS / IP / C)* | Achieved % | Self-Rating | Supervisor Rating | Final Rating |
|:---:|---|---|---|:---:|:---:|:---:|:---:|:---:|:---:|
| **12.3.3.1** | | | | | | | | | |
|12.3.3.1.1 | | | | | | | | | |
|12.3.3.1.2 | | | | | | | | | |
|12.3.3.1.3 | | | | | | | | | |
|12.3.3.1.4 | | | | | | | | | |
|12.3.3.1.5 | | | | | | | | | |
|12.3.3.1.6 | | | | | | | | | |
| **12.3.3.2** | | | | | | | | | |
|12.3.3.2.1 | | | | | | | | | |
|12.3.3.2.2 | | | | | | | | | |
|12.3.3.2.3 | | | | | | | | | |
|12.3.3.2.4 | | | | | | | | | |
|12.3.3.2.5 | | | | | | | | | |
|12.3.3.2.6 | | | | | | | | | |
| **12.3.3.3** | | | | | | | | | |
|12.3.3.3.1 | | | | | | | | | |
|12.3.3.3.2 | | | | | | | | | |
|12.3.3.3.3 | | | | | | | | | |
|12.3.3.3.4 | | | | | | | | | |
|12.3.3.3.5 | | | | | | | | | |
|12.3.3.3.6 | | | | | | | | | |
| **12.3.3.4** | | | | | | | | | |
|12.3.3.4.1 | | | | | | | | | |
|12.3.3.4.2 | | | | | | | | | |
|12.3.3.4.3 | | | | | | | | | |
|12.3.3.4.4 | | | | | | | | | |
|12.3.3.4.5 | | | | | | | | | |
|12.3.3.4.6 | | | | | | | | | |
| **12.3.3.5** | | | | | | | | | |
|12.3.3.5.1 | | | | | | | | | |
|12.3.3.5.2 | | | | | | | | | |
|12.3.3.5.3 | | | | | | | | | |
|12.3.3.5.4 | | | | | | | | | |
|12.3.3.5.5 | | | | | | | | | |
|12.3.3.5.6 | | | | | | | | | |
| **12.3.3.6** | | | | | | | | | |
|12.3.3.5.1 | | | | | | | | | |
|12.3.3.5.2 | | | | | | | | | |
|12.3.3.5.3 | | | | | | | | | |
|12.3.3.5.4 | | | | | | | | | |
|12.3.3.5.5 | | | | | | | | | |
|12.3.3.5.6 | | | | | | | | | |
| | | | **TOTAL** | **100%** | | | | | |

---

## 12.3.4 Agreement on Goals
*(At the commencement of the New Appraisal Year)*

| | Date| Time |
|---|---|---|
checkbox | **Employee Name** | `___________` | `____________` | (get time and date when checkbox pressing.)
checkbox | **Manager Name**  | `___________` | `____________` | (get time and date when checkbox pressing.)

---

##12.3.5 Mid-Year Review Progress

**Overall Progress Status** *(tick applicable)*:

- [ ] Progressing Well
- [ ] Need Improvements
- [ ] Below Expectations


| | Date| Time |
|---|---|---|
checkbox | **Employee Name** | `___________` | `____________` | (get time and date when checkbox pressing.)
checkbox | **Manager Name**  | `___________` | `____________` | (get time and date when checkbox pressing.)

---

##12.3.6 Final Performance Evaluation
*(End of the Appraisal Year — tick in front of the applicable final score)*

| Final Grading (√) | Description | Rating Band |
|:---:|---|---|
| &nbsp; | **A – Excellent** &nbsp; Successfully achieved objectives | 100% |
| &nbsp; | **B – Good** &nbsp; Met objectives | 80% – 99% |
| &nbsp; | **C – Average** &nbsp; Fell short of achieving objectives | 50% – 79% |
| &nbsp; | **D – Poor** &nbsp; Well short of achieving objectives | Below 49% |

---

## 12.3.7 Agreement on Final Performance Grading
*(As per the final achievements of agreed deliverable goals at the commencement of the New Appraisal Year)*

| | Date| Time |
|---|---|---|
checkbox | **Employee Name** | `___________` | `____________` | (get time and date when checkbox pressing.)
checkbox | **Manager Name**  | `___________` | `____________` | (get time and date when checkbox pressing.)


**12.4** KPI Forms (this is where we fill & submit appraisal form datas each year for staff level)
**12.4.1** Year (Select Manually by Dropdown) system search for matching year for 12.4.1 from 12.3.1. then the system will show the following form where the their Immediate Supervisor / Department Head EPF (12.2.8) & Immediate Supervisor / Department Head Name (12.2.9) can fill the appraisal form and submit it.
**12.4.2** EPF No of Manager's  - Get Logedin user data(epf / name), then check if that data match to 12.2.8 from 12.2, if this is true, the show follow 12.4.3
**12.4.3** Name of Manager's (autofill by 12.2.9 and loged acount data matching)
**12.4.4** EPF No of Employee's (autofill by 12.2.1)
**12.4.5** Name of Employee's (autofill by 12.2.2)
**12.4.6** Designation of Employee's (autofill by 12.2.3)
**12.4.7** Department of Employee's (autofill by 12.2.4)
**12.4.8** Location of Employee's (autofill by 12.2.5)
**12.4.9** Joining Date of Employee's (autofill by 12.2.6)
**12.4.10** Service years of Employee's (autofill by 12.2.7)
**12.4.11** Immediate Supervisor / Department Head EPF No of Employee's (autofill by 12.2.8)
**12.4.12** Immediate Supervisor / Department Head Name of Employee's (autofill by 12.2.9)
**12.4.13** show the form from 12.3.2 to  12.3.4 
**12.4.14** Submit Button (when click this button, the system will save all the data to a db table and show the basic details in a list of 12.5)

**12.5** KPI completation Tracker (this work as a automated. works in linke with 12.4/12.6/12.7)
**12.5.1** Year (select manually by dropdown (only shows the created years from 12.3))
**12.5.1** Epf|Name|Department|Manager Name|Form Saved (Y/N)| Mid-Year Progress(Y/N)|Final Performance Evaluation (Y/N)| Final Grade|view details Button| ( no edit option fully automated - delete buttton needed for superadmin.)

**12.6** Mid-Year Progress (this is to update the | Mid-Year Progress *(YS / IP / C)*)| - from 12.3.3 - Need Active button to superadmin ( active / closed) - if active always check available year from 12.2
**12.6.1** Mid-Year Progress Status (select manually by dropdown (YS / IP / C))
shows the 12.4 - 12.4.14 with same form and data saved to db. (only the  Mid-Year Progress *(YS / IP / C)* column should be able to edit )
**12.6.2** show the **12.3.5**
**12.6.3** Save Mid-Year Progress Button (when click this button, the system will save all the data to a the same db table (only the newly added data) and show the basic details in a list of 12.5 in Mid-Year Progress(Y/N))

**12.7** Final Performance Evaluation (this is to update the | Final Performance Evaluation (Y/N)| - from 12.3.3) - Need Active button to superadmin ( active / closed) - if active always check available year from 12.2
**12.7.1** Final Performance Evaluation Status (|ACHIEVED %| SELF-RATING | SUPERVISOR RATING | FINAL RATING|=)
shows the 12.4 - 12.4.14 with same form and data saved to db. (only the  Mid-Year Progress *(|ACHIEVED %| SELF-RATING | SUPERVISOR RATING | FINAL RATING|=* columns should be able to edit ))
**12.7.2** show the **12.3.5** from 12.6 (not editable just show)
**12.7.3** show the **12.3.5** from 12.3.7 (editable)
**12.7.4** Save Final Performance Evaluation Button (when click this button, the system will save all the data to a the same db table (only the newly added data) and show the basic details in a list of 12.5 in Final Performance Evaluation (Y/N))

**12.8** Report & Analytics (this is to show the report and analytics of the performance appraisal)



## 13. Performance appraisal
13.1 Staff Appraisal List( this is just a list that will work on the whole module of 13 Performance appraisal) shows all employees exist in 2.2 list (but only show the employees who has the employement level(1.5)match with (1.5.9 or 1.5.8))
13.1.1 EPF No (autofill by 2.2)
13.1.2 Name (autofill by 2.2)
13.1.3 Designation (autofill by 2.2)
13.1.4 Department (autofill by 2.2)
13.1.5 Location (autofill by 2.2)
13.1.6 Joining Date (autofill by 2.2)
13.1.7 Service years (autofill by 2.2)
13.1.8 Immediate Supervisor / Department Head EPF No (autofill by 2.2) ## if the employ report to a superisor (1.5.8) get his supervisor's supervisor. always 13.1.8 should be match with one of these (1.5.2/1.5.3/1.5.4/1.5.5/1.5.6/1.5.7) according to hierachy.
13.1.9 Immediate Supervisor / Department Head Name (autofill by 2.2) ## if the employ report to a superisor (1.5.8) get his supervisor's supervisor. always 13.1.8 should be match with one of these (1.5.2/1.5.3/1.5.4/1.5.5/1.5.6/1.5.7) according to hierachy.

13.2 Executive Appraisal List( this is just a list that will work on the whole module of 13 Performance appraisal) shows all employees exist in 2.2 list (but only show the employees who has the employement level(1.5)match with (1.5.7 / 1.5.6 / 1.5.5 / 1.5.4 / 1.5.3))
13.2.1 EPF No (autofill by 2.2)
13.2.2 Name (autofill by 2.2)
13.2.3 Designation (autofill by 2.2)
13.2.4 Department (autofill by 2.2)
13.2.5 Location (autofill by 2.2)
13.2.6 Joining Date (autofill by 2.2)
13.2.7 Service years (autofill by 2.2)
13.2.8 Immediate Supervisor / Department Head EPF No (autofill by 2.2) ## if the employ report to a superisor (1.5.8) get his supervisor's supervisor. always 13.2.8 should be match with one of these (1.5.2/1.5.3/1.5.4/1.5.5/1.5.6/1.5.7) according to hierachy.
13.2.9 Immediate Supervisor / Department Head Name (autofill by 2.2) ## if the employ report to a superisor (1.5.8) get his supervisor's supervisor. always 13.2.8 should be match with one of these (1.5.2/1.5.3/1.5.4/1.5.5/1.5.6/1.5.7) according to hierachy.

13.3 Leave Utilization List - Selection (Year) (staff appraisal List should be loaded to follow) ## this form should be downloaded in a csv file, manually fill the leave utilization & no pay days count and upload the csv file to the systemt and then save that for selected year.
13.3.0 Year
13.3.1 Epf No (get from 13.1.1)
13.3.2 Name (get from 13.1.1)
13.3.3 Annual Leave Days
13.3.4 Casual Leave Days
13.3.5 Medical Leave Days
13.3.6 No Pay Days

**13.4** Staff Appraisal Form Settup page (this is where we create each year appraisal form for staff level)
**13.4.1** Year (Select Manually by Dropdown) Period 01 January - 31 December. after selecting the year, the system will show the following settup form where superadmin can settup that selected year form and allocations.
**13.4.1.1** Select Appraisal Period (dropdown of 01.01. Year - (Selected Year in 13.4.1) 31.12. Year  - (Selected Year in 13.4.1))
**13.4.2** Question & Mark Allocation
### 13.4.2.1 Category 1 — Job Knowledge and Skills
**13.4.2.1.1** Question 1 — Employee has required level of knowledge on techniques and products (answer checkbox (1/2/3/4/5))
**13.4.2.1.2** Question 2 — Employee is able to plan and organize job tasks to complete the workload on time (answer checkbox (1/2/3/4/5))
**13.4.2.1.3** Question 3 — Employee performs the tasks efficiently and effectively with minimal instruction (answer checkbox (1/2/3/4/5))
**13.4.2.1.4** Remark (answer text box)
### 13.4.2.2 Category 2 — Creativity / Innovation
**13.4.2.2.1** Question 1 — Employee initiates new ideas and methods that contribute to the success of the department/organization (answer checkbox (1/2/3/4/5))
**13.4.2.2.2** Remark (answer text box)
### 13.4.2.3 Category 3 — Awareness of Quality
**13.4.2.3.1** Question 1 — Employee is having sufficient knowledge on Quality, Health & Safety and Environment regulations & standards (answer checkbox (1/2/3/4/5))
**13.4.2.3.2** Question 2 — Employee ensures that the work meets the quality standards (answer checkbox (1/2/3/4/5))
**13.4.2.3.3** Remark (answer text box)
### 13.4.2.4 Category 4 — Goal Achievement
**13.4.2.4.1** Question 1 — Employee is able to take the responsibility and ownership of work (answer checkbox (1/2/3/4/5))
**13.4.2.4.2** Remark (answer text box)
### 13.4.2.5 Category 5 — Teamwork / Commitment & Communication
**13.4.2.5.1** Question 1 — Employee is able to work in a team in order to achieve team targets and individual objectives (answer checkbox (1/2/3/4/5))
**13.4.2.5.2** Question 2 — Employee acts as a team member; this is reflected in a pleasant and constructive cooperation with peers and subordinates (answer checkbox (1/2/3/4/5))
**13.4.2.5.3** Question 3 — Employee is able to earn the respect and trust of the team and superiors (answer checkbox (1/2/3/4/5))
**13.4.2.5.4** Question 4 — Employee communicates in a respectful way towards colleagues and others (answer checkbox (1/2/3/4/5))
**13.4.2.5.5** Remark (answer text box)
### 13.4.2.6 Category 6 — Cultural Awareness and Adherence
**13.4.2.6.1** Question 1 — Employee has knowledge, adherence & compliance with the working principles of the company (answer checkbox (1/2/3/4/5))
**13.4.2.6.2** Question 2 — Employee is having a positive attitude towards work and the organization (answer checkbox (1/2/3/4/5))
**13.4.2.6.3** Question 3 — Employee acts according to basic values of respect and honesty (answer checkbox (1/2/3/4/5))
**13.4.2.6.4** Question 4 — Employee is able to adapt to the organizational culture (answer checkbox (1/2/3/4/5))
**13.4.2.6.5** Question 5 — Employee shows a pro-active attitude in order to learn, accomplish new tasks and to face (new) challenges (answer checkbox (1/2/3/4/5))
**13.4.2.6.6** Question 6 — Employee is well informed about and respects the company policy and complies with internal procedures and guidelines (answer checkbox (1/2/3/4/5))
**13.4.2.6.7** Remark (answer text box)
### 13.4.2.7 Category 7 — Safety Consciousness
**13.4.2.7.1** Question 1 — Employee works according to Jiffy safety instructions and regulations with respect to his/her own safety and the safety of others (answer checkbox (1/2/3/4/5))
**13.4.2.7.2** Question 2 — Employee correctly uses personal protection (safety shoes, ear-plugs etc.) (answer checkbox (1/2/3/4/5))
**13.4.2.7.3** Question 3 — Employee uses the equipment/machinery with care (answer checkbox (1/2/3/4/5))
**13.4.2.7.4** Question 4 — Employee complies with environmental standards and procedures (answer checkbox (1/2/3/4/5))
**13.4.2.7.5** Remark (answer text box)
### 13.4.2.8 Category 8 — Discipline
**13.4.2.8.1** Question 1 — Employee is attending to work regularly and punctually (answer checkbox (1/2/3/4/5))
**13.4.2.8.2** Question 2 — Employee is maintaining a clean and tidy workplace, not only the own working space but also the joint space (answer checkbox (1/2/3/4/5))
**13.4.2.8.3** Question 3 — Has the employee been accused of a *non-serious offence* during the appraisal period? (answer checkbox (Yes/No))
**13.4.2.8.4** Question 4 — Has the employee been accused of a *serious offence* during the appraisal period? (answer checkbox (Yes/No))
**13.4.2.8.5** Remark — If yes, please brief the incident & any other remarks (answer text box)
### 13.4.2.9 Additional Information
**13.4.2.9.1** Additional Comments (answer text box)
**13.4.2.9.2** Special Talents (if any) (answer text box)
**13.4.2.9.3** Appraisee's Future Expectations *(as agreed upon by employee and manager)* (answer text box)

13.5 Staff Appraisal Form (this is where we fill & submit appraisal form datas each year for staff level)
13.5.1 Appraisal Year (Select Manually by Dropdown)
13.5.2 system search for matching year for 13.5.1 from 13.4.1, then the system will show the following form where the their Immediate Supervisor / Department Head EPF (13.1.8) & Immediate Supervisor / Department Head Name (13.1.9) can fill the appraisal form and submit it.
13.5.3 EPF of appraiser's  - Get Logedin user data(epf / name), then check if that data match to 13.1.8 from 13.1, if this is true, the sow follow 13.5.4.
13.5.4 Name of appraiser's - show 13.5.3 loged users Name 13.1.9
13.5.5 EPF of appraisee - show 13.1.1 (check if the 13.5.3 matches with 13.1.8, if yes show dropdown of all 13.1.1 that has matching 13.1.8 as his Immediate Supervisor / Department Head EPF No )
13.5.6 Name of appraisee - load if matching name for 13.5.5 from 13.1
13.5.7 Designation of appraisee - load if matching name for 13.5.5 from 13.1
13.5.8 Department of appraisee - load if matching name for 13.5.5 from 13.1
13.5.9 Location of appraisee - load if matching name for 13.5.5 from 13.1
13.5.10 Joining Date of appraisee - load if matching name for 13.5.5 from 13.1
13.5.11 Service years of appraisee - load if matching name for 13.5.5 from 13.1
13.5.12 Annual Leave Utilization ((Load from 13.3 if year of 13.3.0 match with 13.5.1) & (if 13.5.5 match with 13.3.1))
13.5.13 Casual Leave Utilization ((Load from 13.3 if year of 13.3.0 match with 13.5.1) & (if 13.5.5 match with 13.3.1))
13.5.14 Medical Leave Utilization ((Load from 13.3 if year of 13.3.0 match with 13.5.1) & (if 13.5.5 match with 13.3.1))
13.5.15 No Pay Days ((Get from 13.3 if year of 13.3.0 match with 13.5.1) & (if 13.5.5 match with 13.3.1))
13.5.16 show form from 13.4.1 - 13.4.2.9.3
13.5.17 Submit Button
## save these form all data to a db table ( 13.5 - 13.5.17 to a db including all data from 13.4.1 - 13.4.2.9.3 in 13.5.16)##

## 13.6 Staff Appraisal Marks Data
13.6.1 Year (Select Manually by Dropdown)
13.6.2 Show following data related to selected year in 13.6.1 from the above said db table"## save these form all data to a db table ( 13.5 - 13.5.17 to a db including all data from 13.4.1 - 13.4.2.9.3 in 13.5.16)##"
13.6.3 EPF No of Appraisee
13.6.4 Name of Appraisee
13.6.4 Designation of Appraisee
13.6.5 Location
13.6.6 Name of Appraiser
13.6.7 View Details Button (shows Each data from the db table like 13.5.1 - 13.5.17 we submitted to db table)
13.6.8 Bulk Data Download Button (download all data from the db table like 13.5.1 - 13.5.17 we submitted to db table)

13.7 Executive Appraisal Form Setup
13.7.1 Year (Select Manually by Dropdown) Period 01 January - 31 December. after selecting the year, the system will show the following settup form where superadmin can settup that selected year form and allocations.
**13.7.1.1** Select Appraisal Period (dropdown of 01.01. Year - (Selected Year in 13.4.1) 31.12. Year  - (Selected Year in 13.4.1))
**13.7.2** Question & Mark Allocation(13.7.2 -13.7.4)

Rating|	Description
5|	Outstanding: Consistently exceeds expectations.
4|	Exceeds Expectations: Often performs above requirements.
3|	Meets Expectations: Consistently meets job standards.
2|	Needs Improvement: Performance below expectations.
1|	Unsatisfactory: Performance significantly below expectations.

### 13.7.2.1 Category 1 — Competency Evaluation 
Every job has their specific competences. These are evaluated on a yearly end individual basis, looking at the relevant competences of the role, please rate your employee per competence and provide additional comments, using the rating below. The Competency skills can be adjusted where needed.
**13.7.2.1.1** Competency 1 — Technical Skills | (Rating checkbox (1/2/3/4/5)) | (Comments)
**13.7.2.1.2** Competency 2 — Communication Skills | (Rating checkbox (1/2/3/4/5)) | (Comments)
**13.7.2.1.3** Competency 3 — Teamwork | (Rating checkbox (1/2/3/4/5)) | (Comments)
**13.7.2.1.4** Competency 4 — Leadership - if applicable | (Rating checkbox (1/2/3/4/5)) | (Comments)
**13.7.2.1.5** Competency 5 — Problem-Solving | (Rating checkbox (1/2/3/4/5)) | (Comments)
**13.7.2.1.6** Competency 6 — Adaptability | (Rating checkbox (1/2/3/4/5)) | (Comments)
**13.7.2.1.7** Competency 7 — Time Management | (Rating checkbox (1/2/3/4/5)) | (Comments)
**13.7.2.1.8** Competency 8 — Customer Focus | (Rating checkbox (1/2/3/4/5)) | (Comments)
**13.7.2.1.4** Remark (answer text box)
### 13.7.2.2 Category 2 — Achievements
##" Please take a moment to reflect on your key achievements or proud moments over the past period, specifically in relation to your competencies." ##
**13.7.2.2.1** Achievement 1 — Text Box
**13.7.2.2.2** Achievement 2 — Text Box
**13.7.2.2.3** Achievement 3 — Text Box
### 13.7.2.3 Category 3 — Areas for competencies development
Please identify key competencies that need improvement and propose actionable recommendations for development.
**13.7.2.3.1** Competency 1 - Tex Box
**13.7.2.3.2** Development Plan - Tex Box (for 13.7.2.3.1 )
**13.7.2.3.3** Competency 2 - Tex Box
**13.7.2.3.4** Development Plan - Tex Box (for 13.7.2.3.3 )
### 13.7.2.4 Category 4 — Employee's Fulfillment of Values, Attitudes, and Behaviors – The Jiffy way
##"Evaluate the employee's adherence to key company values, attitudes, and behaviors. Rate the values, attitudes and behaviors with a score. A score of 1 is the lowest, a score of 5 is the highest."##
**13.7.2.4.1** Core values 1 - Respectful - We are open and inviting | How I rate myself (1-5) | Rating by Manager (1-5)
**13.7.2.4.2** Core values 2 - Passionate - We care | How I rate myself (1-5) | Rating by Manager (1-5)
**13.7.2.4.3** Core values 3 - Reliable - We deliver | How I rate myself (1-5) | Rating by Manager (1-5)
**13.7.2.5.1** Attitudes and behaviors 1 - I do as I say and keep my promises | How I rate myself (1-5) | Rating by Manager (1-5)
**13.7.2.5.2** Attitudes and behaviors 2 - I trust people and am loyal to decisions made | How I rate myself (1-5) | Rating by Manager (1-5)
**13.7.2.5.3** Attitudes and behaviors 3 - I seek continuous improvements and innovations | How I rate myself (1-5) | Rating by Manager (1-5)
**13.7.2.5.4** Attitudes and behaviors 4 - I work together for a common goal and build relationships internally and externally | How I rate myself (1-5) | Rating by Manager (1-5)
**13.7.2.5.5** Attitudes and behaviors 5 - I make decisions based on facts, teamwork, and involvement | How I rate myself (1-5) | Rating by Manager (1-5)
**13.7.2.5.6** Attitudes and behaviors 6 - I communicate properly and welcome constructive feedback | How I rate myself (1-5) | Rating by Manager (1-5)
**13.7.2.5.7** Attitudes and behaviors 7 - I follow the working principles and share my knowledge and experience | How I rate myself (1-5) | Rating by Manager (1-5)
**13.7.2.5.8** Attitudes and behaviors 8 - I focus on customer satisfaction and take responsibility | How I rate myself (1-5) | Rating by Manager (1-5)


**13.7.2.6** Objectives planning and evaluation
This section should be used to set your personal objectives in relation to your position. In the next performance evaluation assessment, these objectives will be evaluated by your Manager/Supervisor. In case your eligible for our bonus program, your target document will be an addendum to this performance evaluation assessment.

Note: all objectives should be formulated using the SMART criteria:
S – Specific: Clearly define the objective, avoiding ambiguity. Focus on what needs to be achieved.
Example: "Increase sales by 10% in the North Region."
M – Measurable: Ensure the objective can be quantified or measured to track progress.
Example: "Achieve 90% customer satisfaction in post-service surveys."
A – Achievable: Set realistic and attainable goals considering available resources and constraints.
Example: "Train 15 employees on the new CRM software within three months."
R – Relevant: Align the objective with broader company or team goals.
Example: "Launch a new product that meets identified customer needs and aligns with our strategic goals."
T – Time-bound: Define a specific timeframe or deadline for achieving the goal.
Example: "Complete the project proposal by December 31, 2024."

**13.7.2.6.1** Objective 1 - Text Box
**13.7.2.6.2** Evaluation (answer text box)
**13.7.2.6.3** Objective 2 - Text Box
**13.7.2.6.2** Evaluation (answer text box)
**13.7.2.6.4** Objective 3 - Text Box
**13.7.2.6.2** Evaluation (answer text box)

**13.7.2.7** Development and training
##"This section should list specific requirements for any training or development. These activities are not restricted to training courses, and may include attachments, projects, coaching, planned experience or any other suitable activity that will enhance the skills, knowledge and behavior required in the employee’s position or for his/her further development."##
**13.7.2.7.1** Text Box


**13.7.2.8** Future Growth
##"This section should document any areas within the department or company where the employee expresses a particular interest in growing, contributing, or gaining additional experience."##
**13.7.2.8.1** Text Box

**13.7.2.9** Feedback on Manager’s/Supervisor’s performance
How would you evaluate your Manager’s/Supervisor’s performance during this past period? Please share specific feedback on their leadership, communication, support, and overall management style.
**13.7.2.9.1** Improvement areas Manager/Supervisor - Text Box

**13.7.3.1** Other areas of discussion/reflection/feedback Manager.
This section is intended to record any additional points or topics discussed during the performance evaluation that are not covered under the specified sections above.
**13.7.3.1.1** Point 1 - Text Box
**13.7.3.1.2** Point 2 - Text Box
**13.7.3.1.3** Point 3 - Text Box

**13.7.3.2** Compliance 
**13.7.3.2.1** Have you ever been in a situation described in the code of conduct directive that made you feel oppressed? - Y/N | Comment (text Box)
**13.7.3.2.2** Do you know whether other employees have been in such a situation? - Y/N | Comment (text Box)
**13.7.3.2.3** If one of the above questions is answered “yes”, is the local Compliance Manager informed about this? - Y/N | Comment (text Box)
**13.7.4 Save Button

13.8 Executive Appraisal Submition Form (this is where we fill & submit appraisal form datas each year for staff level)
13.8.1 info button (top right of the page, content (Introduction

Welcome to the Performance Evaluation Assessment
This document is designed to facilitate an in-depth evaluation of your performance over the past year and to identify opportunities for growth and development. Performance evaluations are crucial for personal advancement, employee motivation, and overall team success.
As part of this process, you will review your achievements, reflect on areas for improvement, and collaborate with your Manager/Supervisor to set clear, actionable goals for the future. 

This assessment is to guide you through key areas, ensuring meaningful self-reflection and discussions that contribute to your professional growth.

How to Use This Assessment
•	Reflection (Employee): Take time to consider your past performance, achievements, and challenges as you complete your sections of the assessment.
•	Engagement (Both): Use the evaluation as a platform for open conversation. The manager will provide feedback, and both parties can share insights.
•	Objective/Goal Setting (Both): Collaboratively define clear, measurable goals. The employee should outline aspirations, while the manager provides guidance and aligns goals with team or company objectives.
•	Future Growth (Employee): Highlight areas where you want to develop or advance within the company. The manager can offer insights or suggestions to align these aspirations with organizational needs.
•	Follow-Up (Manager): The manager will schedule follow-up meetings to review progress and ensure continued support for agreed-upon actions.

Section Responsibilities
•	Achievements and Reflection: To be completed by the employee.
•	Performance Feedback and Recommendations: To be completed by the manager with employee input during the discussion.
•	Objectives/Goals and Development Plans: A shared section completed collaboratively.
The goal of this performance evaluation is to inspire future success, enhance open communication, and align your career path with organizational goals. Use this opportunity to share your aspirations, strengthen collaboration with your Manager/Supervisor, and take charge of your professional development.
))
13.8.1 Appraisal Year (Select Manually by Dropdown)
13.8.2 system search for matching year for 13.8.1 from 13.4.1, then the system will show the following form where the their Immediate Supervisor / Department Head EPF (13.2.8) & Immediate Supervisor / Department Head Name (13.2.9) can fill the appraisal form and submit it.
13.8.3 EPF of appraiser's  - Get Logedin user data(epf / name), then check if that data match to 13.2.8 from 13.2, if this is true, the sow follow 13.8.4.
13.8.4 Name of appraiser's - show 13.8.3 loged users Name 13.2.9
13.8.5 EPF of appraisee - show 13.2.1 (check if the 13.8.3 matches with 13.2.8, if yes show dropdown of all 13.2.1 that has matching 13.2.8 as his Immediate Supervisor / Department Head EPF No )
13.8.6 Name of the Appraisee - load if matching name for 13.8.5 from 13.2
13.8.7 Designation of the Appraisee - load if matching name for 13.8.5 from 13.2
13.8.8 Department of the Appraisee - load if matching name for 13.8.5 from 13.2
13.8.9 Location of the Appraisee - load if matching name for 13.8.5 from 13.2
13.8.10 joining Date
13.8.11 Service years of appraise
13.8.12 show form from 13.7 to 13.7.3.2.3
13.8.13 Save Button
## save these form all data to a db table ( 13.8 - 13.8.13 to a db including all data from 13.7 - 13.7.3.2.3 in 13.8.12)##

## 13.9 Executive Appraisal Marks Data
13.9.1 Year (Select Manually by Dropdown)
13.9.2 Show following data related to selected year in 13.9.1 from the above said db table"## save these form all data to a db table ( 13.8 - 13.8.13 to a db including all data from 13.7 - 13.7.3.2.3 in 13.8.12)##"
13.9.3 EPF No of Appraisee
13.9.4 Name of Appraisee
13.9.4 Designation of Appraisee
13.9.5 Location
13.9.6 Name of Appraiser
13.9.7 View Details Button (shows Each data from the db table like 13.5.1 - 13.5.17 we submitted to db table)
13.9.8 Bulk Data Download Button (download all data from the db table like 13.5.1 - 13.5.17 we submitted to db table)

## 13.10 Reports and analyrics (two tab in same page for staff & executives)

## 14. Key Talent identification
14.1 Talent Candidate List ( this is just a list that will work on the whole module of 14 Key talen identification) shows all employees exist in 2.2 list
14.1.1 EPF No (autofill by 2.2)
14.1.2 Name (autofill by 2.2)
14.1.3 Designation (autofill by 2.2)
14.1.4 Department (autofill by 2.2)
14.1.5 Location (autofill by 2.2)
14.1.6 Joining Date (autofill by 2.2)
14.1.7 Service years (autofill by 2.2)
14.1.8 Manager EPF No (autofill by 2.2) ## if the employ report to a superisor (1.2.2) get his supervisor i mean manager here (1.2.3). anyhow this should shown only that 1.2.3 account type releated to this employee according to hierachy.
14.1.9 Manager Name (autofill by 2.2) (autofill by 2.2) ## if the employ report to a superisor (1.2.2) get his supervisor i mean manager here (1.2.3). anyhow this should shown only that 1.2.3 account type releated to this employee according to hierachy

14.2 KTI form Settup page
14.2.1 Year (Select Manually by Dropdown) Period 01 January - 31 December. after selecting the year, the system will show the following settup form where superadmin can settup that selected year form and allocations.
14.2.2 Question & Mark Allocation
14.2.2.1 Part A PERFORMANCE ASSESSMENT - Total Marks 100% 
### 14.2.2.1 Part A — PERFORMANCE ASSESSMENT (Total Marks: 100%)
**14.2.2.1.1** Q1 — need to edit
- **14.2.2.1.1.0** Assessment Question — need to edit
- **14.2.2.1.1.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.1.1.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.1.1.3** Answer 3 — 5% — Satisfactory — ("        ") need to edit
- **14.2.2.1.1.4** Answer 4 — 3% — Needs Improvement — ("        ") need to edit
- **14.2.2.1.1.5** Answer 5 — 0% — Poor — ("        ") need to edit
**14.2.2.1.2** Q2 — need to edit
- **14.2.2.1.2.0** Assessment Question — need to edit
- **14.2.2.1.2.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.1.2.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.1.2.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.1.2.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.1.2.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.1.3** Q3 — need to edit
- **14.2.2.1.3.0** Assessment Question — need to edit
- **14.2.2.1.3.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.1.3.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.1.3.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.1.3.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.1.3.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.1.4** Q4 — need to edit
- **14.2.2.1.4.0** Assessment Question — need to edit
- **14.2.2.1.4.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.1.4.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.1.4.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.1.4.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.1.4.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.1.5** Q5 — need to edit
- **14.2.2.1.5.0** Assessment Question — need to edit
- **14.2.2.1.5.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.1.5.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.1.5.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.1.5.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.1.5.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.1.6** Q6 — need to edit
- **14.2.2.1.6.0** Assessment Question — need to edit
- **14.2.2.1.6.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.1.6.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.1.6.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.1.6.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.1.6.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.1.7** Q7 — need to edit
- **14.2.2.1.7.0** Assessment Question — need to edit
- **14.2.2.1.7.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.1.7.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.1.7.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.1.7.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.1.7.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.1.8** Q8 — need to edit
- **14.2.2.1.8.0** Assessment Question — need to edit
- **14.2.2.1.8.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.1.8.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.1.8.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.1.8.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.1.8.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.1.9** Q9 — need to edit
- **14.2.2.1.9.0** Assessment Question — need to edit
- **14.2.2.1.9.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.1.9.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.1.9.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.1.9.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.1.9.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.1.10** Q10 — need to edit
- **14.2.2.1.10.0** Assessment Question — need to edit
- **14.2.2.1.10.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.1.10.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.1.10.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.1.10.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.1.10.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.1.11** Sub Total of All Questions (auto)

### 14.2.2.2 Part B — POTENTIAL ASSESSMENT (Total Marks: 100%)
**14.2.2.2.1** Q1 — need to edit
- **14.2.2.2.1.0** Assessment Question — need to edit
- **14.2.2.2.1.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.2.1.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.2.1.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.2.1.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.2.1.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.2.2** Q2 — need to edit
- **14.2.2.2.2.0** Assessment Question — need to edit
- **14.2.2.2.2.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.2.2.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.2.2.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.2.2.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.2.2.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.2.3** Q3 — need to edit
- **14.2.2.2.3.0** Assessment Question — need to edit
- **14.2.2.2.3.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.2.3.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.2.3.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.2.3.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.2.3.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.2.4** Q4 — need to edit
- **14.2.2.2.4.0** Assessment Question — need to edit
- **14.2.2.2.4.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.2.4.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.2.4.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.2.4.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.2.4.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.2.5** Q5 — need to edit
- **14.2.2.2.5.0** Assessment Question — need to edit
- **14.2.2.2.5.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.2.5.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.2.5.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.2.5.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.2.5.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.2.6** Q6 — need to edit
- **14.2.2.2.6.0** Assessment Question — need to edit
- **14.2.2.2.6.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.2.6.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.2.6.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.2.6.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.2.6.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.2.7** Q7 — need to edit
- **14.2.2.2.7.0** Assessment Question — need to edit
- **14.2.2.2.7.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.2.7.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.2.7.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.2.7.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.2.7.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.2.8** Q8 — need to edit
- **14.2.2.2.8.0** Assessment Question — need to edit
- **14.2.2.2.8.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.2.8.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.2.8.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.2.8.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.2.8.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.2.9** Q9 — need to edit
- **14.2.2.2.9.0** Assessment Question — need to edit
- **14.2.2.2.9.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.2.9.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.2.9.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.2.9.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.2.9.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.2.10** Q10 — need to edit
- **14.2.2.2.10.0** Assessment Question — need to edit
- **14.2.2.2.10.1** Answer 1 — 10% — Exceptional — ("        ") need to edit
- **14.2.2.2.10.2** Answer 2 — 8% — Good — ("        ") need to edit
- **14.2.2.2.10.3** Answer 3 — 6% — Satisfactory — ("        ") need to edit
- **14.2.2.2.10.4** Answer 4 — 4% — Needs Improvement — ("        ") need to edit
- **14.2.2.2.10.5** Answer 5 — 2% — Poor — ("        ") need to edit
**14.2.2.2.11** Sub Total of All Questions (auto)

## 14.2.3 Part A — PERFORMANCE ASSESSMENT Total Mark (auto)
*(Example: 75% out of 100%)*
## 14.2.4 Part B — POTENTIAL ASSESSMENT Total Mark (auto)
*(Example: 25% out of 100%)*

# 14.3 Key Talent Identification Form
Should show the set-up form from 14.2 by selecting the year. Each year has its own form.

## 14.3.1 Select Year
Selected year form should be loaded with ability to select answers.

## 14.3.2 KEY TALENT IDENTIFICATION FORM

### Employee & Manager Details

- **14.3.4** Manager EPF Number — autofill with logged user; also check included in 14.1 list in Manager column of the db
- **14.3.5** Manager Name — autofill with logged user; also check included in 14.1 list as a Manager
- **14.3.6** Employee EPF Number — show a dropdown of the employees under the manager (14.3.4 / 14.3.5); get data from 14.1
- **14.3.7** Employee Name — autofill with selected employee number from 14.1 list
- **14.3.8** Designation — autofill with selected employee number from 14.1 list
- **14.3.9** Department — autofill with selected employee number from 14.1 list
- **14.3.10** Location — autofill with selected employee number from 14.1 list
- **14.3.11** Joining Date — autofill with selected employee number from 14.1 list
- **14.3.12** Service Years — autofill with selected employee number from 14.1 list

### Assessment

- **14.3.13** — Show all from 14.2.2.1 to 14.2.2.2.10.5 with the selected year form to collect inputs

### Submission

- **14.3.14** Submit Button (record Manager Name, EPF, Date , Time of submition)

14.4 Mark Allocations List - should show the submitted data of employees in rows, that submitted to the selected year form data saved to the DB using 14.3 Key Talent Identification Form. with Part A Total Marks column (Example: 75%), B Total Marks column  (Example: 25%) to identify there marks given by the manager.

14.5 - Box Grid Dashboard (need to select the year to show the data relavant to that year. show the total count inside of the following boxes.)

```
P
O
T    HIGH        ┌──────────────────────┬──────────────────────┬──────────────────────┐
E   (76–100%)    │  DYSFUNCTIONAL       │    THE ROCKET        │    THE UNICORN       │
N                │  GENIUS              │                      │                      │
T                │  Monitor & Coach     │   Emerging Talent    │ High Potential Talent│
I                │                      │                      │                      │
A                │  [Part A:  0– 40%]   │  [Part A: 41– 75%]   │  [Part A: 76–100%]   │
L                │  [Part B: 76–100%]   │  [Part B: 76–100%]   │  [Part B: 76–100%]   │
                 ├──────────────────────┼──────────────────────┼──────────────────────┤
A   MODERATE     │   THE SLEEPING       │   THE BACKBONE       │   THE VETERAN        │
X   (41–75%)     │   GIANT              │                      │                      │
I                │   Retain & Develop   │    Solid Citizen     │ Consistent Deliverer │
S                │                      │                      │                      │
                 │  [Part A:  0– 40%]   │  [Part A: 41– 75%]   │  [Part A: 76–100%]   │
                 │  [Part B: 41– 75%]   │  [Part B: 41– 75%]   │  [Part B: 41– 75%]   │
                 ├──────────────────────┼──────────────────────┼──────────────────────┤
      LOW        │   THE WAKE-UP        │   THE SETTLER        │    WORKHORSE         │
    (0–40%)      │   CALL               │                      │                      │
                 │  Performance Review  │   Limited Growth     │    Expert in Role    │
                 │                      │                      │                      │
                 │  [Part A:  0– 40%]   │  [Part A: 41– 75%]   │  [Part A: 76–100%]   │
                 │  [Part B:  0– 40%]   │  [Part B:  0– 40%]   │  [Part B:  0– 40%]   │
                 └──────────────────────┴──────────────────────┴──────────────────────┘
                      LOW (0–40%)            MODERATE (41–75%)        HIGH (76–100%)
                 ◄──────────────────────────────────────────────────────────────────────
                                  PERFORMANCE AXIS (Part A Score)
```
14.6 info button
🦄 The Unicorn — High Perf + High Pot
🚀 The Rocket — High Perf + Mod Pot
🌀 Dysfunctional Genius — High Perf + Low Pot
🎖️ The Veteran — Mod Perf + High Pot
🏛️ The Backbone — Mod Perf + Mod Pot
😴 The Sleeping Giant — Mod Perf + Low Pot
🐴 Workhorse — Low Perf + High Pot
🛋️ The Settler — Low Perf + Mod Pot
⏰ The Wake-Up Call — Low Perf + Low Pot

14.7 Need download option for the list of above breakdown, like details of each employeee that come to each nine categories category wise.

## 15. Skill Matrix

15.1 Employee List
15.1 Talent Candidate List ( this is just a list that will work on the whole module of 15 Key talen identification) shows all employees exist in 2.2 list
15.1.1 EPF No (autofill by 2.2)
15.1.2 Name (autofill by 2.2)
15.1.3 Designation (autofill by 2.2)
15.1.4 Department (autofill by 2.2)
15.1.5 Location (autofill by 2.2)
15.1.6 Manager EPF No (autofill by 2.2) ## if the employ report to a superisor (1.2.2) get his supervisor i mean manager here (1.2.3). anyhow this should shown only that 1.2.3 account type releated to this employee according to hierachy.
15.1.7 Manager Name (autofill by 2.2) (autofill by 2.2) ## if the employ report to a superisor (1.2.2) get his supervisor i mean manager here (1.2.3). anyhow this should shown only that 1.2.3 account type releated to this employee according to hierachy

15.2 Skill Matrix Form Setup
15.2.1 Year (Select Manually by Dropdown) Period 01 January - 31 December. after selecting the year, the system will show the following settup form where superadmin can settup that selected year form and allocations.
15.2.2 Question & Mark Allocation
15.2.2.1 Technical Skills (skills 1 - 5) need five skills under this
15.2.2.2 Leadership & Management (skills 1 - 5) need five skills under this
15.2.2.3 Communication & Interpersonal Skills (skills 1 - 5) need five skills under this
15.2.2.4 Adaptability & Learning Agility (skills 1 - 5) need five skills under this
15.2.2.5 Innovation & Creativity (skills 1 - 5) need five skills under this
15.2.2.6 Problem-Solving & Critical Thinking (skills 1 - 5) need five skills under this

15.3 Skill Matrix Form
# 15.3 Key Talent Identification Form
Should show the set-up form from 15.2 by selecting the year. Each year has its own form.

## 15.3.1 Select Year
Selected year form should be loaded with ability to select answers.

## 15.3.2 Employee & Manager Details

- **15.3.3** Manager EPF Number — autofill with logged user; also check included in 15.1 list in Manager column of the db
- **15.3.4** Manager Name — autofill with logged user; also check included in 15.1 list as a Manager
- **15.3.5** Employee EPF Number — show a dropdown of the employees under the manager (15.3.4 / 15.3.5); get data from 15.1
- **15.3.6** Employee Name — autofill with selected employee number from 15.1 list
- **15.3.7** Designation — autofill with selected employee number from 15.1 list
- **15.3.8** Department — autofill with selected employee number from 15.1 list
- **15.3.9** Location — autofill with selected employee number from 15.1 list
- **15.3.10** Joining Date — autofill with selected employee number from 15.1 list
- **15.3.11** Service Years — autofill with selected employee number from 15.1 list

### Assessment

- **15.3.12** — Show all from 15.2 to 15.2.2.6 with the selected year form to collect inputs
15.3.12.1 Target (Each Skill should have this and can be rate 1-5)
15.3.12.2 Current Status (Each Skill should have this and can be rate 1-5)
15.3.12.3 Gap (Each Skill should have this and can be rate 1-5)
### Submission

- **15.3.13** Submit Button (record Manager Name, EPF, Date , Time of submition)

15.4 Skill Matrix Submited Data
15.4.1 Select Year
15.4.2 Show submitted data (15.3.5, 15.3.6 & 15.3.12)
15.4.3 Target avg
15.4.4 Current Status avg
15.4.5 Gap avg
15.4.6 Percentage ((15.4.4 / 14.4.3)*%)
15.4.3 Filter
15.4.4 Export to Excel

## UI/UX Design Standards & Requirements

### **Consistency Requirements for All Modules**

All modules in the SmartHRM system MUST follow these standardized design patterns:

#### **1. File Structure Requirements**
- Each module must have an `index.php` file as the main entry point
- All modules must use the same template structure found in `/includes/module_template.php`
- Include the shared sidebar using `<?php include '../../includes/sidebar.php'; ?>`
- Include the shared CSS using `<link href="../../assets/css/style.css" rel="stylesheet">`

#### **2. Header Structure (Required for every module)**
```php
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="[MODULE_ICON] me-2"></i>[MODULE_NAME]</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">[MODULE_NAME]</li>
                </ol>
            </nav>
        </div>
        <div class="action-buttons">
            <a href="../../dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>
```

#### **3. Statistics Cards (When applicable)**
Use the following structure for displaying module statistics:
```php
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="icon bg-[COLOR]-light text-[COLOR]">
                <i class="fas fa-[ICON]"></i>
            </div>
            <h3>[NUMBER]</h3>
            <p>[DESCRIPTION]</p>
        </div>
    </div>
</div>
```

#### **4. Feature Cards Structure (Required for all modules)**
All module features must use the consistent module card design:
```php
<div class="col-lg-4 col-md-6">
    <div class="module-card">
        <div class="module-icon bg-[COLOR]-light text-[COLOR]">
            <i class="fas fa-[ICON]"></i>
        </div>
        <h5>[FEATURE_NAME]</h5>
        <p>[FEATURE_DESCRIPTION]</p>
        <a href="[FEATURE_URL]" class="btn btn-[COLOR]">
            <i class="fas fa-[ICON] me-2"></i>[ACTION_TEXT]
        </a>
    </div>
</div>
```

#### **5. Color Scheme Standards**
- **Primary Blue:** `#007bff` - Main brand color
- **Success Green:** `#28a745` - Positive actions, success states
- **Warning Yellow:** `#ffc107` - Warnings, pending states
- **Danger Red:** `#dc3545` - Errors, delete actions
- **Info Cyan:** `#17a2b8` - Information, secondary actions
- **Secondary Gray:** `#6c757d` - Supporting elements

#### **6. Navigation Requirements**
- **Sidebar Navigation:** Fixed left sidebar with all 15 modules
- **Breadcrumb Navigation:** Always show path from Dashboard > Current Module
- **Back Button:** Every module must have a "Back to Dashboard" button
- **Module Links:** All inter-module navigation should be clearly labeled

#### **7. Responsive Design Requirements**
- **Desktop:** Sidebar visible, full feature cards
- **Tablet:** Sidebar collapsible, responsive grid
- **Mobile:** Hamburger menu, stacked layout

#### **8. Typography Standards**
- **Headings:** Use h1 for module titles, h5 for feature names
- **Icons:** FontAwesome 6.0.0+ for all icons
- **Font:** System font stack (-apple-system, BlinkMacSystemFont, "Segoe UI")

#### **9. Interactive Elements**
- **Buttons:** Consistent padding, hover effects, icon + text
- **Cards:** Hover lift effect, consistent shadow
- **Links:** Consistent color scheme, hover states

#### **10. Required CSS Classes**
Every module must use these standardized classes:
- `.page-header` - Module header section
- `.stats-card` - Statistics display cards
- `.module-card` - Feature cards
- `.action-buttons` - Button groups
- `.main-content` - Main content area

#### **11. Database Integration Standards**
Every module index page should:
- Connect to database using `new Database()`
- Display relevant statistics in stats cards
- Handle database errors gracefully
- Show loading states when applicable

#### **12. Security Standards**
Every module must:
- Include `require_once '../../includes/auth_check.php';`
- Check user permissions appropriate for module access
- Sanitize all output with `htmlspecialchars()`
- Use prepared statements for database queries

#### **13. Error Handling Standards**
- Database connection errors: Graceful fallback
- Permission errors: Redirect to appropriate page
- File not found: Show user-friendly message
- Form validation: Clear error messages

#### **14. Performance Standards**
- Use shared CSS/JS files (no inline styles/scripts)
- Optimize database queries (use LIMIT when needed)
- Implement pagination for large datasets
- Use appropriate HTTP caching headers

### **Module Implementation Checklist**

Before marking any module as "complete", ensure it meets ALL these requirements:

- [ ] Uses standardized file structure
- [ ] Includes proper page header with breadcrumb
- [ ] Displays relevant statistics in stats cards
- [ ] Uses module-card design for features
- [ ] Includes back navigation button
- [ ] Follows consistent color scheme
- [ ] Is responsive on all devices
- [ ] Includes proper error handling
- [ ] Uses prepared database statements
- [ ] Has appropriate permission checks
- [ ] Uses consistent typography and spacing
- [ ] Includes hover effects and transitions

### **Future Enhancement Guidelines**

When adding new features to any module:

1. **Maintain Consistency:** All new features must follow existing design patterns
2. **Mobile First:** Design for mobile, enhance for desktop
3. **Accessibility:** Include proper ARIA labels and keyboard navigation
4. **Performance:** Optimize for fast loading and smooth interactions
5. **Security:** Always validate input and escape output
6. **Documentation:** Update this plan when adding new patterns

This standardization ensures a professional, cohesive user experience across all 15 modules of the SmartHRM system.


1. User: See only their own training data (tr.epf_number = user_epf)
  2. Supervisor: See their own + direct reports' training data
  (tr.epf_number IN accessible_epfs)
  3. Manager: See anyone in their hierarchy + reports to their reports
  (tr.epf_number IN accessible_epfs)
  4. Admin: See all training data for their location (tr.location =
  user_location)
  5. SuperAdmin: See all training data from all locations (no filter)