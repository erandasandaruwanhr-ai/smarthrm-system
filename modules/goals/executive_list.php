<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to Executive List
if (!isSuperAdmin() && !hasModulePermission($db, 'goals.executive_list')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get executive employees (employment levels 1.5.3, 1.5.4, 1.5.5, 1.5.6, 1.5.7)
$executives = $db->fetchAll("
    SELECT
        e.epf_number,
        e.name,
        e.designation,
        e.department,
        l.location_name as location,
        e.joined_date,
        TIMESTAMPDIFF(YEAR, e.joined_date, CURDATE()) as service_years,
        e.reports_to as supervisor_epf,
        s.name as supervisor_name,
        e.employment_level_id,
        el.level_name as employment_level
    FROM employees e
    LEFT JOIN employees s ON e.reports_to = s.epf_number
    LEFT JOIN employment_levels el ON e.employment_level_id = el.id
    LEFT JOIN locations l ON e.location_id = l.id
    WHERE e.is_active = 1
    AND e.employment_level_id IN (3, 4, 5, 6, 7)
    ORDER BY e.employment_level_id, e.name
");

// Function to get the proper supervisor for hierarchy
function getProperSupervisor($db, $employee_epf, $supervisor_epf, $employment_level_id) {
    // Start with the direct supervisor and traverse up the hierarchy
    $current_supervisor_epf = $supervisor_epf;
    $max_depth = 5; // Prevent infinite loops
    $depth = 0;

    while ($current_supervisor_epf && $depth < $max_depth) {
        $supervisor = $db->fetch("
            SELECT epf_number, name, employment_level_id, reports_to
            FROM employees
            WHERE epf_number = ? AND is_active = 1
        ", [$current_supervisor_epf]);

        if (!$supervisor) {
            break; // Supervisor not found or inactive
        }

        // If we found a Manager (3) or GM (2), this is the proper supervisor
        if (in_array($supervisor['employment_level_id'], [2, 3])) {
            return [
                'epf' => $supervisor['epf_number'],
                'name' => $supervisor['name']
            ];
        }

        // Move up the hierarchy to the next level supervisor
        $current_supervisor_epf = $supervisor['reports_to'];
        $depth++;
    }

    // If we couldn't find a Manager/GM in the hierarchy
    return ['epf' => '', 'name' => ''];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Executive Appraisal List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --sidebar-width: 280px;
        }

        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .dashboard-content {
            background: #f8f9fa;
            padding: 2rem;
            min-height: calc(100vh - 40px);
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50px, -50px);
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
            position: relative;
            z-index: 2;
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .page-header-logo {
            height: 60px;
            width: auto;
            background: rgba(255, 255, 255, 0.9);
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 2;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .employment-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .level-1-5-3 { background-color: #dc3545; }
        .level-1-5-4 { background-color: #fd7e14; }
        .level-1-5-5 { background-color: #ffc107; color: #212529; }
        .level-1-5-6 { background-color: #20c997; }
        .level-1-5-7 { background-color: #0dcaf0; color: #212529; }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .dashboard-content {
                padding: 1rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-users me-3"></i>Executive Appraisal List</h1>
                        <p>Executives eligible for Goal Setting (Employment Levels: 1.5.3 - 1.5.7)</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Executive Employees
                    </h5>
                    <span class="badge bg-primary"><?= count($executives) ?> executives</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="executiveTable" class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>EPF No</th>
                                    <th>Name</th>
                                    <th>Designation</th>
                                    <th>Department</th>
                                    <th>Location</th>
                                    <th>Joining Date</th>
                                    <th>Service Years</th>
                                    <th>Supervisor EPF</th>
                                    <th>Supervisor Name</th>
                                    <th>Employment Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($executives as $executive):
                                    $proper_supervisor = getProperSupervisor($db, $executive['epf_number'], $executive['supervisor_epf'], $executive['employment_level_id']);
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($executive['epf_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($executive['name']) ?></td>
                                    <td><?= htmlspecialchars($executive['designation']) ?></td>
                                    <td><?= htmlspecialchars($executive['department']) ?></td>
                                    <td><?= htmlspecialchars($executive['location']) ?></td>
                                    <td><?= date('Y-m-d', strtotime($executive['joined_date'])) ?></td>
                                    <td><?= $executive['service_years'] ?> years</td>
                                    <td>
                                        <?php if ($proper_supervisor['epf']): ?>
                                            <strong><?= htmlspecialchars($proper_supervisor['epf']) ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($proper_supervisor['name']): ?>
                                            <?= htmlspecialchars($proper_supervisor['name']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge employment-badge level-<?= $executive['employment_level_id'] ?>">
                                            <?= htmlspecialchars($executive['employment_level'] ?? 'Level ' . $executive['employment_level_id']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Employment Level Legend -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Employment Level Legend</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <span class="badge employment-badge level-1-5-3">1.5.3</span> Senior Executive
                                </div>
                                <div class="col-md-2 mb-2">
                                    <span class="badge employment-badge level-1-5-4">1.5.4</span> Assistant Manager
                                </div>
                                <div class="col-md-2 mb-2">
                                    <span class="badge employment-badge level-1-5-5">1.5.5</span> Manager
                                </div>
                                <div class="col-md-2 mb-2">
                                    <span class="badge employment-badge level-1-5-6">1.5.6</span> Executive
                                </div>
                                <div class="col-md-2 mb-2">
                                    <span class="badge employment-badge level-1-5-7">1.5.7</span> Junior Executive
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Goal Setting
                        </a>
                        <?php if (isSuperAdmin()): ?>
                        <a href="form_setup.php" class="btn btn-primary">
                            <i class="fas fa-cogs me-2"></i>Setup Goal Setting Forms
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#executiveTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[9, 'asc'], [1, 'asc']], // Order by employment level, then name
                columnDefs: [
                    {
                        targets: [5], // Joining Date column
                        type: 'date'
                    }
                ]
            });
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>