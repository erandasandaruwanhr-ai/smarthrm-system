<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has form setup permission
if (!isSuperAdmin() && !hasModulePermission($database, 'key_talent.form_setup')) {
    header('Location: index.php');
    exit();
}

// Handle template download
if (isset($_GET['download_template']) && isset($_GET['form_id'])) {
    $form_id = (int)$_GET['form_id'];

    try {
        // Get form details
        $form_query = "SELECT * FROM kti_forms WHERE id = ?";
        $form = $database->fetch($form_query, [$form_id]);

        if (!$form) {
            throw new Exception("Form not found");
        }

        // Get questions for both parts
        $questions_query = "SELECT * FROM kti_questions WHERE form_id = ? ORDER BY part, question_number";
        $questions = $database->fetchAll($questions_query, [$form_id]);

        // Group questions by part
        $parts = ['A' => [], 'B' => []];
        foreach ($questions as $question) {
            $parts[$question['part']][] = $question;
        }

        // Set headers for CSV download (simpler format)
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="KTI_Questions_Template_' . $form['year'] . '.csv"');
        header('Cache-Control: max-age=0');

        // Generate simple CSV format for easier editing
        echo "# KTI Questions Template - " . $form['year'] . " (Form ID: " . $form['id'] . ")\n";
        echo "# INSTRUCTIONS: Edit ONLY the 'Question Text' column. Do NOT modify Part, Question Number, or ID.\n";
        echo "# This template contains questions for the " . $form['year'] . " assessment form only.\n";
        echo "# After editing, save this CSV file and upload it back.\n";
        echo "\n";

        // CSV Header
        echo "Part,Question Number,Question ID,Question Text (EDITABLE)\n";

        // Part A Questions
        foreach ($parts['A'] as $index => $question) {
            $question_text = str_replace('"', '""', $question['question_text']); // Escape quotes for CSV
            echo "A," . ($index + 1) . "," . $question['id'] . ",\"" . $question_text . "\"\n";
        }

        // Part B Questions
        foreach ($parts['B'] as $index => $question) {
            $question_text = str_replace('"', '""', $question['question_text']); // Escape quotes for CSV
            echo "B," . ($index + 1) . "," . $question['id'] . ",\"" . $question_text . "\"\n";
        }
        exit();

    } catch (Exception $e) {
        $error_message = "Error downloading template: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'upload_template') {
                $form_id = (int)$_POST['form_id'];

                if (!isset($_FILES['template_file']) || $_FILES['template_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Please select a valid Excel file to upload");
                }

                $file = $_FILES['template_file'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($file_extension, ['xls', 'xlsx', 'csv'])) {
                    throw new Exception("Please upload an Excel file (.xls, .xlsx) or CSV file");
                }

                // Read and parse the uploaded file
                $file_content = file_get_contents($file['tmp_name']);

                // Parse CSV format
                if ($file_extension === 'csv' || $file_extension === 'xls') {
                    $lines = explode("\n", $file_content);
                    $questions_to_update = [];

                    foreach ($lines as $line) {
                        $line = trim($line);

                        // Skip empty lines and comments
                        if (empty($line) || $line[0] === '#') {
                            continue;
                        }

                        // Skip header row
                        if (strpos($line, 'Part,Question Number') === 0) {
                            continue;
                        }

                        // Parse CSV line: Part,Question Number,Question ID,Question Text
                        $data = str_getcsv($line);

                        if (count($data) >= 4) {
                            $part = trim($data[0]);
                            $question_number = (int)trim($data[1]);
                            $question_id = (int)trim($data[2]);
                            $question_text = trim($data[3]);

                            if (!empty($question_text) && in_array($part, ['A', 'B']) && $question_number > 0) {
                                $questions_to_update[] = [
                                    'id' => $question_id,
                                    'question_text' => $question_text
                                ];

                                error_log("Parsed: ID=$question_id, Text=$question_text");
                            }
                        }
                    }

                    // Debug: Log parsed questions
                    error_log("Questions to update: " . print_r($questions_to_update, true));

                    $total_updated = 0;
                    // Update database with parsed questions (ONLY question text) using question IDs
                    foreach ($questions_to_update as $question_data) {
                        $update_query = "UPDATE kti_questions SET
                            question_text = ?
                            WHERE id = ? AND form_id = ?";

                        $result = $database->execute($update_query, [
                            $question_data['question_text'],
                            $question_data['id'],
                            $form_id
                        ]);

                        if ($result) {
                            $total_updated++;
                            error_log("Updated Question ID " . $question_data['id'] . ": " . $question_data['question_text']);
                        }
                    }

                    $success_message = "✅ Template uploaded successfully! $total_updated question texts updated. Marks and descriptions remain unchanged.";
                } else {
                    throw new Exception("Excel file format not supported. Please use the downloaded template format.");
                }

            } else if ($_POST['action'] === 'delete_form') {
                $form_id = (int)$_POST['form_id'];

                // Get form details for confirmation message
                $form_query = "SELECT year FROM kti_forms WHERE id = ?";
                $form = $database->fetch($form_query, [$form_id]);

                if (!$form) {
                    throw new Exception("Form not found");
                }

                // Delete all related data (questions and assessments)
                $database->execute("DELETE FROM kti_assessment_answers WHERE assessment_id IN (SELECT id FROM kti_assessments WHERE form_id = ?)", [$form_id]);
                $database->execute("DELETE FROM kti_assessments WHERE form_id = ?", [$form_id]);
                $database->execute("DELETE FROM kti_questions WHERE form_id = ?", [$form_id]);
                $database->execute("DELETE FROM kti_forms WHERE id = ?", [$form_id]);

                $success_message = "✅ KTI Assessment Form for year " . $form['year'] . " has been deleted successfully!";

            } else if ($_POST['action'] === 'create_form') {
                $year = (int)$_POST['year'];
                $part_a_title = $_POST['part_a_title'];
                $part_b_title = $_POST['part_b_title'];

                // Create form
                $form_query = "INSERT INTO kti_forms (year, part_a_title, part_b_title, created_by) VALUES (?, ?, ?, ?)";
                $database->execute($form_query, [$year, $part_a_title, $part_b_title, $user['epf_number']]);

                $form_id = $database->lastInsertId();

                // Create default questions for both parts
                $default_questions = [
                    'A' => [
                        'Job Knowledge and Technical Competency',
                        'Quality of Work and Attention to Detail',
                        'Productivity and Time Management',
                        'Problem Solving and Decision Making',
                        'Communication and Interpersonal Skills',
                        'Teamwork and Collaboration',
                        'Initiative and Self-Motivation',
                        'Adaptability and Flexibility',
                        'Leadership and Influence',
                        'Goal Achievement and Results Orientation'
                    ],
                    'B' => [
                        'Strategic Thinking and Vision',
                        'Learning Agility and Growth Mindset',
                        'Innovation and Creative Problem Solving',
                        'Emotional Intelligence and Self-Awareness',
                        'Coaching and People Development',
                        'Change Management and Resilience',
                        'Business Acumen and Commercial Awareness',
                        'Cross-Functional Collaboration',
                        'Risk Management and Decision Quality',
                        'Cultural Leadership and Values Alignment'
                    ]
                ];

                $answer_templates = [
                    1 => ['text' => 'Exceptional', 'percentage' => 10.00],
                    2 => ['text' => 'Strong', 'percentage' => 8.00],
                    3 => ['text' => 'Satisfactory', 'percentage' => 5.00],
                    4 => ['text' => 'Below Expectations', 'percentage' => 3.00],
                    5 => ['text' => 'Unsatisfactory', 'percentage' => 0.00]
                ];

                foreach (['A', 'B'] as $part) {
                    foreach ($default_questions[$part] as $index => $question) {
                        $question_query = "INSERT INTO kti_questions (form_id, part, question_number, question_text,
                                          answer_1_text, answer_1_percentage, answer_2_text, answer_2_percentage,
                                          answer_3_text, answer_3_percentage, answer_4_text, answer_4_percentage,
                                          answer_5_text, answer_5_percentage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                        $database->execute($question_query, [
                            $form_id, $part, $index + 1, $question,
                            $answer_templates[1]['text'], $answer_templates[1]['percentage'],
                            $answer_templates[2]['text'], $answer_templates[2]['percentage'],
                            $answer_templates[3]['text'], $answer_templates[3]['percentage'],
                            $answer_templates[4]['text'], $answer_templates[4]['percentage'],
                            $answer_templates[5]['text'], $answer_templates[5]['percentage']
                        ]);
                    }
                }

                $success_message = "KTI Form for year $year created successfully with default questions!";
            }

            if ($_POST['action'] === 'update_question') {
                $question_id = (int)$_POST['question_id'];
                $question_text = $_POST['question_text'];
                $answer_data = [];

                for ($i = 1; $i <= 5; $i++) {
                    $answer_data["answer_{$i}_text"] = $_POST["answer_{$i}_text"];
                    $answer_data["answer_{$i}_percentage"] = (float)$_POST["answer_{$i}_percentage"];
                }

                $update_query = "UPDATE kti_questions SET question_text = ?,
                                answer_1_text = ?, answer_1_percentage = ?,
                                answer_2_text = ?, answer_2_percentage = ?,
                                answer_3_text = ?, answer_3_percentage = ?,
                                answer_4_text = ?, answer_4_percentage = ?,
                                answer_5_text = ?, answer_5_percentage = ?
                                WHERE id = ?";

                $database->execute($update_query, [
                    $question_text,
                    $answer_data['answer_1_text'], $answer_data['answer_1_percentage'],
                    $answer_data['answer_2_text'], $answer_data['answer_2_percentage'],
                    $answer_data['answer_3_text'], $answer_data['answer_3_percentage'],
                    $answer_data['answer_4_text'], $answer_data['answer_4_percentage'],
                    $answer_data['answer_5_text'], $answer_data['answer_5_percentage'],
                    $question_id
                ]);

                $success_message = "Question updated successfully!";
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get all forms
try {
    $forms_query = "SELECT * FROM kti_forms ORDER BY year DESC";
    $forms = $database->fetchAll($forms_query);
} catch (Exception $e) {
    $forms = [];
}

// Get selected form details
$selected_form = null;
$questions = [];
if (isset($_GET['form_id'])) {
    $form_id = (int)$_GET['form_id'];
    try {
        $form_query = "SELECT * FROM kti_forms WHERE id = ?";
        $selected_form = $database->fetchAll($form_query, [$form_id])[0] ?? null;

        if ($selected_form) {
            $questions_query = "SELECT * FROM kti_questions WHERE form_id = ? ORDER BY part, question_number";
            $questions = $database->fetchAll($questions_query, [$form_id]);
        }
    } catch (Exception $e) {
        $error_message = "Error loading form: " . $e->getMessage();
    }
}

$current_year = max(2025, date('Y')); // Start from 2025 minimum
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - KTI Form Setup</title>
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

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--warning-color);
            margin-bottom: 1rem;
        }

        .question-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
        }

        .question-card.part-a {
            border-left-color: var(--success-color);
        }

        .question-card.part-b {
            border-left-color: var(--info-color);
        }

        .answer-row {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
        }

        .answer-score {
            background: var(--primary-color);
            color: white;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #e1e5e9;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
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

        .part-header {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .part-header.part-b {
            background: linear-gradient(135deg, var(--info-color), #138496);
        }

        .action-buttons .btn {
            margin-left: 0.5rem;
        }

        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: 0;
        }

        .nav-tabs .nav-link.active {
            color: var(--warning-color);
            border-bottom: 3px solid var(--warning-color);
            background: transparent;
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

            .content-card {
                padding: 1rem;
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
                        <h1><i class="fas fa-cogs me-3"></i>KTI Form Setup</h1>
                        <p>Configure assessment questions and scoring criteria for Performance and Potential evaluation</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Tabs Navigation -->
            <div class="content-card">
                <ul class="nav nav-tabs" id="setupTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="forms-tab" data-bs-toggle="tab" data-bs-target="#forms" type="button" role="tab">
                            <i class="fas fa-list me-2"></i>Assessment Forms
                        </button>
                    </li>
                    <?php if ($selected_form): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="questions-tab" data-bs-toggle="tab" data-bs-target="#questions" type="button" role="tab">
                            <i class="fas fa-edit me-2"></i>Edit Questions (<?php echo $selected_form['year']; ?>)
                        </button>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="create-tab" data-bs-toggle="tab" data-bs-target="#create" type="button" role="tab">
                            <i class="fas fa-plus me-2"></i>Create New Form
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="setupTabContent">
                    <!-- Assessment Forms Tab -->
                    <div class="tab-pane fade show active" id="forms" role="tabpanel">
                        <div class="p-4">
                            <h5 class="mb-4"><i class="fas fa-clipboard-list me-2"></i>Existing Assessment Forms</h5>

                            <?php if (!empty($forms)): ?>
                            <div class="row">
                                <?php foreach ($forms as $form): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="form-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="text-warning mb-2">
                                                    <i class="fas fa-calendar me-2"></i><?php echo $form['year']; ?> Assessment Form
                                                </h6>
                                                <p class="small text-muted mb-2">
                                                    <strong>Part A:</strong> <?php echo htmlspecialchars($form['part_a_title']); ?> (<?php echo $form['part_a_total_marks']; ?>%)<br>
                                                    <strong>Part B:</strong> <?php echo htmlspecialchars($form['part_b_title']); ?> (<?php echo $form['part_b_total_marks']; ?>%)
                                                </p>
                                                <small class="text-muted">
                                                    Created: <?php echo date('M d, Y', strtotime($form['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <a href="?form_id=<?php echo $form['id']; ?>" class="btn btn-sm btn-outline-warning" title="Edit Form">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete Form"
                                                        onclick="deleteForm(<?php echo $form['id']; ?>, <?php echo $form['year']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Assessment Forms Available</h5>
                                <p class="text-muted">Create your first KTI assessment form to get started.</p>
                                <button class="btn btn-warning" onclick="document.getElementById('create-tab').click()">
                                    <i class="fas fa-plus me-2"></i>Create New Form
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Edit Questions Tab -->
                    <?php if ($selected_form): ?>
                    <div class="tab-pane fade" id="questions" role="tabpanel">
                        <div class="p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">
                                    <i class="fas fa-edit me-2"></i>Edit Questions - <?php echo $selected_form['year']; ?>
                                </h5>
                                <span class="badge bg-warning text-dark">
                                    <?php echo count($questions); ?> Total Questions
                                </span>
                            </div>

                            <!-- Upload Template Section -->
                            <div class="card mb-4" style="border-left: 4px solid #28a745;">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-upload me-2 text-success"></i>Upload Question Text Updates
                                    </h6>
                                    <p class="text-muted small mb-3">
                                        Upload your edited Excel template to update <strong>question text only</strong>. Marks and descriptions will remain unchanged.
                                    </p>

                                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                                        <input type="hidden" name="action" value="upload_template">
                                        <input type="hidden" name="form_id" value="<?php echo $selected_form['id']; ?>">

                                        <div class="col-md-8">
                                            <label for="template_file" class="form-label">
                                                <i class="fas fa-file-excel me-1"></i>Select Excel Template File
                                            </label>
                                            <input type="file" class="form-control" id="template_file" name="template_file"
                                                   accept=".xls,.xlsx,.csv" required>
                                            <div class="form-text">
                                                Accepted formats: .xls, .xlsx, .csv (Use the downloaded template format)
                                            </div>
                                        </div>

                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="submit" class="btn btn-success me-2">
                                                <i class="fas fa-upload me-1"></i>Upload & Update
                                            </button>
                                            <a href="?download_template=1&form_id=<?php echo $selected_form['id']; ?>"
                                               class="btn btn-outline-primary" target="_blank">
                                                <i class="fas fa-download me-1"></i>Download Template
                                            </a>
                                        </div>
                                    </form>

                                    <div class="mt-3">
                                        <h6 class="text-primary"><i class="fas fa-info-circle me-1"></i>How to use:</h6>
                                        <ol class="small text-muted mb-0">
                                            <li>Click "Download Template" to get the current form in Excel format</li>
                                            <li>Edit <strong>ONLY the question text</strong> in the "Question Text (✅ EDITABLE)" column</li>
                                            <li>Do NOT modify marks, percentages, or answer descriptions</li>
                                            <li>Save the Excel file and upload it here</li>
                                            <li>Only question texts will be updated automatically</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>

                            <?php
                            $part_a_questions = array_filter($questions, function($q) { return $q['part'] === 'A'; });
                            $part_b_questions = array_filter($questions, function($q) { return $q['part'] === 'B'; });
                            ?>

                            <!-- Part A Questions -->
                            <div class="part-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-chart-line me-2"></i>Part A - <?php echo htmlspecialchars($selected_form['part_a_title']); ?>
                                    <span class="float-end"><?php echo count($part_a_questions); ?> Questions</span>
                                </h6>
                            </div>

                            <?php foreach ($part_a_questions as $question): ?>
                            <div class="question-card part-a">
                                <form method="POST" class="question-form">
                                    <input type="hidden" name="action" value="update_question">
                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">
                                            Question <?php echo $question['question_number']; ?>
                                        </label>
                                        <textarea name="question_text" class="form-control" rows="2" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="answer-row">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="fw-bold text-muted">Answer <?php echo $i; ?></small>
                                                    <span class="answer-score"><?php echo $question["answer_{$i}_percentage"]; ?>%</span>
                                                </div>
                                                <input type="text" name="answer_<?php echo $i; ?>_text" class="form-control form-control-sm mb-1"
                                                       value="<?php echo htmlspecialchars($question["answer_{$i}_text"]); ?>" required>
                                                <input type="number" name="answer_<?php echo $i; ?>_percentage" class="form-control form-control-sm"
                                                       step="0.01" min="0" max="10" value="<?php echo $question["answer_{$i}_percentage"]; ?>" required>
                                            </div>
                                        </div>
                                        <?php endfor; ?>
                                    </div>

                                    <div class="d-flex justify-content-end mt-3">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-save me-1"></i>Update Question
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <?php endforeach; ?>

                            <!-- Part B Questions -->
                            <div class="part-header part-b mt-4">
                                <h6 class="mb-0">
                                    <i class="fas fa-rocket me-2"></i>Part B - <?php echo htmlspecialchars($selected_form['part_b_title']); ?>
                                    <span class="float-end"><?php echo count($part_b_questions); ?> Questions</span>
                                </h6>
                            </div>

                            <?php foreach ($part_b_questions as $question): ?>
                            <div class="question-card part-b">
                                <form method="POST" class="question-form">
                                    <input type="hidden" name="action" value="update_question">
                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">
                                            Question <?php echo $question['question_number']; ?>
                                        </label>
                                        <textarea name="question_text" class="form-control" rows="2" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="answer-row">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="fw-bold text-muted">Answer <?php echo $i; ?></small>
                                                    <span class="answer-score"><?php echo $question["answer_{$i}_percentage"]; ?>%</span>
                                                </div>
                                                <input type="text" name="answer_<?php echo $i; ?>_text" class="form-control form-control-sm mb-1"
                                                       value="<?php echo htmlspecialchars($question["answer_{$i}_text"]); ?>" required>
                                                <input type="number" name="answer_<?php echo $i; ?>_percentage" class="form-control form-control-sm"
                                                       step="0.01" min="0" max="10" value="<?php echo $question["answer_{$i}_percentage"]; ?>" required>
                                            </div>
                                        </div>
                                        <?php endfor; ?>
                                    </div>

                                    <div class="d-flex justify-content-end mt-3">
                                        <button type="submit" class="btn btn-sm btn-info">
                                            <i class="fas fa-save me-1"></i>Update Question
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Create New Form Tab -->
                    <div class="tab-pane fade" id="create" role="tabpanel">
                        <div class="p-4">
                            <h5 class="mb-4"><i class="fas fa-plus me-2"></i>Create New Assessment Form</h5>

                            <form method="POST">
                                <input type="hidden" name="action" value="create_form">

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="year" class="form-label">Assessment Year</label>
                                        <select name="year" id="year" class="form-control" required>
                                            <option value="">Select Year</option>
                                            <?php for($y = 2025; $y <= $current_year + 3; $y++): ?>
                                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="part_a_title" class="form-label">Part A Title</label>
                                        <input type="text" name="part_a_title" id="part_a_title" class="form-control"
                                               value="PERFORMANCE ASSESSMENT" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="part_b_title" class="form-label">Part B Title</label>
                                        <input type="text" name="part_b_title" id="part_b_title" class="form-control"
                                               value="POTENTIAL ASSESSMENT" required>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Default Questions:</strong> The system will automatically create 10 default questions each for Performance and Potential assessment with standard 5-point scoring (Exceptional 10%, Strong 8%, Satisfactory 5%, Below Expectations 3%, Unsatisfactory 0%). You can customize these questions after creating the form.
                                </div>

                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-plus me-2"></i>Create Assessment Form
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle form submissions with loading states
        document.querySelectorAll('.question-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
                submitBtn.disabled = true;

                // Re-enable after 3 seconds as fallback
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            });
        });

        // Delete form function
        function deleteForm(formId, year) {
            if (confirm(`⚠️ Are you sure you want to delete the ${year} Assessment Form?\n\nThis will permanently delete:\n• All questions and answers\n• All completed assessments\n• All assessment data\n\nThis action cannot be undone!`)) {
                // Create and submit delete form
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_form">
                    <input type="hidden" name="form_id" value="${formId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Auto-switch to questions tab if form is selected
        <?php if ($selected_form): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const questionsTab = new bootstrap.Tab(document.getElementById('questions-tab'));
            questionsTab.show();
        });
        <?php endif; ?>
    </script>
</body>
</html>