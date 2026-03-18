<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has talent grid permission
if (!isSuperAdmin() && !hasModulePermission($database, 'key_talent.talent_grid')) {
    header('Location: index.php');
    exit();
}

// Get available years
try {
    $years_query = "SELECT DISTINCT year FROM kti_forms ORDER BY year DESC";
    $available_years = $database->fetchAll($years_query);
} catch (Exception $e) {
    $available_years = [];
}

// Get selected year
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (count($available_years) > 0 ? $available_years[0]['year'] : date('Y'));

// Get assessments for the selected year
try {
    $assessments_query = "
        SELECT
            a.*,
            f.year
        FROM kti_assessments a
        JOIN kti_forms f ON a.form_id = f.id
        WHERE f.year = ?";

    $params = [$selected_year];

    // Role-based filtering
    if ($user['account_type'] === 'manager') {
        $assessments_query .= " AND a.manager_epf = ?";
        $params[] = $user['epf_number'];
    }

    $assessments_query .= " ORDER BY a.part_a_total_score DESC, a.part_b_total_score DESC";
    $assessments = $database->fetchAll($assessments_query, $params);

    // Group assessments by 9-box categories
    $talent_grid = [
        'High_High' => [],      // The Unicorn
        'High_Moderate' => [],  // The Veteran
        'High_Low' => [],       // Workhorse
        'Moderate_High' => [],  // The Rocket
        'Moderate_Moderate' => [], // The Backbone
        'Moderate_Low' => [],   // The Settler
        'Low_High' => [],       // Dysfunctional Genius
        'Low_Moderate' => [],   // The Sleeping Giant
        'Low_Low' => []         // The Wake-Up Call
    ];

    foreach ($assessments as $assessment) {
        $key = $assessment['performance_category'] . '_' . $assessment['potential_category'];
        if (isset($talent_grid[$key])) {
            $talent_grid[$key][] = $assessment;
        }
    }

} catch (Exception $e) {
    $assessments = [];
    $talent_grid = [];
    $error_message = "Error loading talent grid: " . $e->getMessage();
}

// Define 9-box metadata
$box_info = [
    'High_High' => [
        'title' => 'THE UNICORN',
        'subtitle' => 'High Potential Talent',
        'description' => 'Ready for promotion',
        'emoji' => '🦄',
        'color' => 'bg-primary text-white',
        'border' => 'border-primary'
    ],
    'High_Moderate' => [
        'title' => 'THE VETERAN',
        'subtitle' => 'Consistent Deliverer',
        'description' => 'Reliable high performer',
        'emoji' => '🎖️',
        'color' => 'bg-info text-white',
        'border' => 'border-info'
    ],
    'High_Low' => [
        'title' => 'WORKHORSE',
        'subtitle' => 'Expert in Role',
        'description' => 'Technical specialist',
        'emoji' => '🐴',
        'color' => 'bg-secondary text-white',
        'border' => 'border-secondary'
    ],
    'Moderate_High' => [
        'title' => 'THE ROCKET',
        'subtitle' => 'Emerging Talent',
        'description' => 'High growth potential',
        'emoji' => '🚀',
        'color' => 'bg-success text-white',
        'border' => 'border-success'
    ],
    'Moderate_Moderate' => [
        'title' => 'THE BACKBONE',
        'subtitle' => 'Solid Citizen',
        'description' => 'Consistent contributor',
        'emoji' => '🏛️',
        'color' => 'bg-light text-dark',
        'border' => 'border-dark'
    ],
    'Moderate_Low' => [
        'title' => 'THE SETTLER',
        'subtitle' => 'Limited Growth',
        'description' => 'Content in current role',
        'emoji' => '🛋️',
        'color' => 'bg-warning text-dark',
        'border' => 'border-warning'
    ],
    'Low_High' => [
        'title' => 'DYSFUNCTIONAL GENIUS',
        'subtitle' => 'Monitor & Coach',
        'description' => 'High potential, low performance',
        'emoji' => '🌀',
        'color' => 'bg-danger text-white',
        'border' => 'border-danger'
    ],
    'Low_Moderate' => [
        'title' => 'THE SLEEPING GIANT',
        'subtitle' => 'Retain & Develop',
        'description' => 'Untapped potential',
        'emoji' => '😴',
        'color' => 'bg-dark text-white',
        'border' => 'border-dark'
    ],
    'Low_Low' => [
        'title' => 'THE WAKE-UP CALL',
        'subtitle' => 'Performance Review',
        'description' => 'Requires immediate attention',
        'emoji' => '⏰',
        'color' => 'bg-danger text-white',
        'border' => 'border-danger'
    ]
];

$total_assessments = count($assessments);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - 9-Box Talent Grid</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        .page-header .d-flex {
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

        .breadcrumb {
            background: transparent;
            padding: 0;
        }

        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: white;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .talent-grid {
            display: grid;
            grid-template-columns: 120px repeat(3, 1fr);
            grid-template-rows: 50px repeat(3, 200px);
            gap: 15px;
            margin: 2rem 0;
            font-size: 0.9rem;
        }

        /* Specific grid positioning for 9-box layout using classes */
        .box-Low_High { grid-row: 2; grid-column: 2; } /* Low Performance, High Potential */
        .box-Moderate_High { grid-row: 2; grid-column: 3; } /* Moderate Performance, High Potential */
        .box-High_High { grid-row: 2; grid-column: 4; } /* High Performance, High Potential */

        .box-Low_Moderate { grid-row: 3; grid-column: 2; } /* Low Performance, Moderate Potential */
        .box-Moderate_Moderate { grid-row: 3; grid-column: 3; } /* Moderate Performance, Moderate Potential */
        .box-High_Moderate { grid-row: 3; grid-column: 4; } /* High Performance, Moderate Potential */

        .box-Low_Low { grid-row: 4; grid-column: 2; } /* Low Performance, Low Potential */
        .box-Moderate_Low { grid-row: 4; grid-column: 3; } /* Moderate Performance, Low Potential */
        .box-High_Low { grid-row: 4; grid-column: 4; } /* High Performance, Low Potential */

        .axis-label {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: #2c3e50;
        }

        .axis-label.vertical {
            writing-mode: vertical-lr;
            text-orientation: mixed;
            grid-row: 2 / 5;
            grid-column: 1;
        }

        .axis-label.horizontal {
            grid-row: 5;
            grid-column: 2 / 5;
        }

        .performance-header, .potential-header {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .performance-header.high { background: linear-gradient(135deg, #28a745, #1e7e34); grid-column: 4; }
        .performance-header.moderate { background: linear-gradient(135deg, #ffc107, #e0a800); color: #000; grid-column: 3; }
        .performance-header.low { background: linear-gradient(135deg, #dc3545, #c82333); grid-column: 2; }

        .potential-header.high { background: linear-gradient(135deg, #28a745, #1e7e34); grid-row: 2; }
        .potential-header.moderate { background: linear-gradient(135deg, #ffc107, #e0a800); color: #000; grid-row: 3; }
        .potential-header.low { background: linear-gradient(135deg, #dc3545, #c82333); grid-row: 4; }

        .talent-box {
            background: white;
            border: 3px solid #dee2e6;
            border-radius: 12px;
            padding: 1rem;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .talent-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .box-header {
            text-align: center;
            margin-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
        }

        .box-title {
            font-weight: 700;
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
        }

        .box-subtitle {
            font-size: 0.65rem;
            opacity: 0.8;
            margin-bottom: 0.25rem;
        }

        .box-emoji {
            font-size: 1.5rem;
        }

        .box-count {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .employee-list {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            max-height: 100px;
            overflow-y: auto;
        }

        .employee-item {
            background: rgba(0, 0, 0, 0.05);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .stats-summary {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-item {
            text-align: center;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }

        .summary-item:last-child {
            border-right: none;
        }

        .summary-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .summary-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .legend {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            border-radius: 6px;
            transition: background-color 0.3s ease;
        }

        .legend-item:hover {
            background-color: #f8f9fa;
        }

        .legend-emoji {
            font-size: 1.2rem;
            margin-right: 0.75rem;
        }

        .legend-text {
            flex: 1;
        }

        .legend-title {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 0.1rem;
        }

        .legend-subtitle {
            font-size: 0.75rem;
            color: #6c757d;
        }

        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .action-buttons .btn {
            margin-left: 0.5rem;
        }

        @media (max-width: 1400px) {
            .talent-grid {
                grid-template-columns: 100px repeat(3, 1fr);
                grid-template-rows: 40px repeat(3, 180px);
                font-size: 0.8rem;
            }

            .talent-box {
                min-height: 160px;
                padding: 0.75rem;
            }

            .box-emoji {
                font-size: 1.2rem;
            }
        }

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

            .talent-grid {
                grid-template-columns: 80px repeat(3, 1fr);
                grid-template-rows: 35px repeat(3, 150px);
                gap: 10px;
                font-size: 0.7rem;
            }

            .talent-box {
                min-height: 130px;
                padding: 0.5rem;
            }

            .box-title {
                font-size: 0.65rem;
            }

            .employee-item {
                font-size: 0.6rem;
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
                        <h1><i class="fas fa-th me-3"></i>9-Box Talent Grid</h1>
                        <p>Interactive 9-box talent matrix showing Performance vs Potential positioning</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Year Filter -->
            <div class="filter-card">
                <form method="GET" class="row align-items-center">
                    <div class="col-md-3">
                        <label for="year" class="form-label">Assessment Year</label>
                        <select name="year" id="year" class="form-control" onchange="this.form.submit()">
                            <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo $year['year']; ?>" <?php echo $year['year'] == $selected_year ? 'selected' : ''; ?>>
                                <?php echo $year['year']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-summary">
                            <div class="row text-center">
                                <div class="col-3 summary-item">
                                    <div class="summary-number"><?php echo $total_assessments; ?></div>
                                    <div class="summary-label">Total Assessed</div>
                                </div>
                                <div class="col-3 summary-item">
                                    <div class="summary-number"><?php echo count($talent_grid['High_High'] ?? []); ?></div>
                                    <div class="summary-label">Unicorns 🦄</div>
                                </div>
                                <div class="col-3 summary-item">
                                    <div class="summary-number"><?php echo count($talent_grid['Moderate_High'] ?? []); ?></div>
                                    <div class="summary-label">Rockets 🚀</div>
                                </div>
                                <div class="col-3 summary-item">
                                    <div class="summary-number"><?php echo count($talent_grid['Low_Low'] ?? []); ?></div>
                                    <div class="summary-label">Wake-Up Calls ⏰</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 text-end">
                        <a href="marks_allocations.php?year=<?php echo $selected_year; ?>" class="btn btn-info">
                            <i class="fas fa-table me-2"></i>View Results
                        </a>
                    </div>
                </form>
            </div>

            <?php if (!empty($assessments)): ?>
            <!-- Debug Information -->
            <div class="alert alert-info">
                <h6>Debug Info:</h6>
                <p><strong>Total Assessments:</strong> <?php echo count($assessments); ?></p>
                <p><strong>Grid Distribution:</strong></p>
                <ul>
                <?php foreach ($talent_grid as $key => $employees): ?>
                    <li><?php echo $key; ?>: <?php echo count($employees); ?> employees
                    <?php if (!empty($employees)): ?>
                        <small>(<?php echo implode(', ', array_map(function($e) { return $e['employee_name'] . ' (' . $e['part_a_total_score'] . '%, ' . $e['part_b_total_score'] . '%)'; }, array_slice($employees, 0, 2))); ?>)</small>
                    <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>

            <!-- 9-Box Talent Grid -->
            <div class="content-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-grip me-2"></i><?php echo $selected_year; ?> Talent Matrix
                    </h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="downloadGrid()">
                        <i class="fas fa-download me-1"></i>Export Grid
                    </button>
                </div>

                <div class="talent-grid">
                    <!-- Axis Labels -->
                    <div class="axis-label vertical">
                        <span>POTENTIAL AXIS (Part B Score) →</span>
                    </div>
                    <div class="axis-label horizontal">
                        <span>← PERFORMANCE AXIS (Part A Score)</span>
                    </div>

                    <!-- Column Headers (Performance) -->
                    <div class="performance-header low">LOW (0–40%)</div>
                    <div class="performance-header moderate">MODERATE (41–75%)</div>
                    <div class="performance-header high">HIGH (76–100%)</div>

                    <!-- Row Headers (Potential) -->
                    <div class="potential-header high">HIGH (76–100%)</div>
                    <div class="potential-header moderate">MODERATE (41–75%)</div>
                    <div class="potential-header low">LOW (0–40%)</div>

                    <!-- Talent Boxes - 9-box grid in proper order (High to Low potential, Low to High performance) -->
                    <?php
                    // Correct 9-box positioning:
                    // Row 1 (High Potential): Low_High, Moderate_High, High_High
                    // Row 2 (Mod Potential): Low_Moderate, Moderate_Moderate, High_Moderate
                    // Row 3 (Low Potential): Low_Low, Moderate_Low, High_Low
                    foreach (['Low_High', 'Moderate_High', 'High_High', 'Low_Moderate', 'Moderate_Moderate', 'High_Moderate', 'Low_Low', 'Moderate_Low', 'High_Low'] as $key):
                    ?>
                    <?php
                    $box = $box_info[$key];
                    $employees = $talent_grid[$key] ?? [];
                    $count = count($employees);
                    ?>
                    <div class="talent-box box-<?php echo $key; ?> <?php echo $box['border']; ?>" data-bs-toggle="modal" data-bs-target="#boxModal"
                         onclick="showBoxDetails('<?php echo $key; ?>', '<?php echo $box['title']; ?>', '<?php echo $box['subtitle']; ?>')">
                        <div class="box-count"><?php echo $count; ?></div>
                        <div class="box-header">
                            <div class="box-emoji"><?php echo $box['emoji']; ?></div>
                            <div class="box-title"><?php echo $box['title']; ?></div>
                            <div class="box-subtitle"><?php echo $box['subtitle']; ?></div>
                        </div>
                        <div class="employee-list">
                            <?php foreach (array_slice($employees, 0, 4) as $emp): ?>
                            <div class="employee-item">
                                <?php echo htmlspecialchars($emp['employee_name']); ?>
                                <small class="text-muted">(<?php echo $emp['part_a_total_score']; ?>%, <?php echo $emp['part_b_total_score']; ?>%)</small>
                            </div>
                            <?php endforeach; ?>
                            <?php if ($count > 4): ?>
                            <div class="employee-item text-muted">
                                <small>+<?php echo $count - 4; ?> more...</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Legend -->
            <div class="row">
                <div class="col-md-6">
                    <div class="legend">
                        <h6><i class="fas fa-info-circle me-2"></i>Talent Categories</h6>
                        <?php foreach ($box_info as $key => $info): ?>
                        <div class="legend-item">
                            <div class="legend-emoji"><?php echo $info['emoji']; ?></div>
                            <div class="legend-text">
                                <div class="legend-title"><?php echo $info['title']; ?></div>
                                <div class="legend-subtitle"><?php echo $info['subtitle']; ?> - <?php echo $info['description']; ?></div>
                            </div>
                            <div class="badge bg-secondary"><?php echo count($talent_grid[$key] ?? []); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="legend">
                        <h6><i class="fas fa-lightbulb me-2"></i>Action Recommendations</h6>
                        <div class="legend-item">
                            <div class="legend-emoji">🦄</div>
                            <div class="legend-text">
                                <div class="legend-title">Unicorns & Rockets</div>
                                <div class="legend-subtitle">Promote, develop leadership skills, succession planning</div>
                            </div>
                        </div>
                        <div class="legend-item">
                            <div class="legend-emoji">🎖️</div>
                            <div class="legend-text">
                                <div class="legend-title">Veterans & Backbone</div>
                                <div class="legend-subtitle">Retain, recognize contributions, lateral development</div>
                            </div>
                        </div>
                        <div class="legend-item">
                            <div class="legend-emoji">🌀</div>
                            <div class="legend-text">
                                <div class="legend-title">High Potential Issues</div>
                                <div class="legend-subtitle">Coach performance, remove barriers, provide support</div>
                            </div>
                        </div>
                        <div class="legend-item">
                            <div class="legend-emoji">⏰</div>
                            <div class="legend-text">
                                <div class="legend-title">Performance Concerns</div>
                                <div class="legend-subtitle">Performance improvement plans, coaching, or transition</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- Empty State -->
            <div class="content-card">
                <div class="text-center py-5">
                    <i class="fas fa-th fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Talent Grid Data Available</h5>
                    <p class="text-muted">
                        No assessments have been completed for <?php echo $selected_year; ?>.
                        Complete some assessments to see the talent matrix.
                    </p>
                    <?php if ($user['account_type'] === 'manager' || $user['account_type'] === 'superadmin'): ?>
                    <a href="assessment_form.php" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Start Assessment
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Box Details Modal -->
    <div class="modal fade" id="boxModal" tabindex="-1" aria-labelledby="boxModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="boxModalLabel">Box Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store talent grid data for modal
        const talentGridData = <?php echo json_encode($talent_grid); ?>;

        function showBoxDetails(boxKey, title, subtitle) {
            const employees = talentGridData[boxKey] || [];
            const modal = document.getElementById('modalBody');

            let html = `
                <h6>${title} - ${subtitle}</h6>
                <p class="text-muted mb-3">Total Employees: ${employees.length}</p>
            `;

            if (employees.length > 0) {
                html += '<div class="table-responsive"><table class="table table-sm table-striped">';
                html += '<thead><tr><th>Employee</th><th>Designation</th><th>Department</th><th>Performance</th><th>Potential</th><th>Manager</th></tr></thead><tbody>';

                employees.forEach(emp => {
                    html += `
                        <tr>
                            <td><strong>${emp.employee_name}</strong><br><small class="text-muted">${emp.employee_epf}</small></td>
                            <td>${emp.employee_designation}</td>
                            <td>${emp.employee_department}</td>
                            <td><span class="badge bg-primary">${emp.part_a_total_score}%</span></td>
                            <td><span class="badge bg-info">${emp.part_b_total_score}%</span></td>
                            <td><small>${emp.manager_name}</small></td>
                        </tr>
                    `;
                });

                html += '</tbody></table></div>';
            } else {
                html += '<p class="text-center text-muted py-3">No employees in this category</p>';
            }

            modal.innerHTML = html;
        }

        function downloadGrid() {
            // This would implement grid export functionality
            alert('Grid export functionality would be implemented here.');
        }

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>