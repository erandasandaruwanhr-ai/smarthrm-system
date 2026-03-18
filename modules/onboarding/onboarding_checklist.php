<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

// Check if user is superadmin
$user = getCurrentUser();
if ($user['account_type'] !== 'superadmin') {
    exit('Access denied');
}

$database = new Database();
$onboarding_id = $_GET['id'] ?? 0;

// Get onboarding record
$onboarding = $database->fetchAll("SELECT * FROM onboarding_tracker WHERE id = ?", [$onboarding_id]);
if (empty($onboarding)) {
    exit('Onboarding record not found');
}

$record = $onboarding[0];

$checklist_items = [
    'safety_induction' => [
        'label' => 'Safety Induction',
        'icon' => 'fas fa-shield-alt',
        'description' => 'Employee has completed workplace safety orientation'
    ],
    'code_of_conduct' => [
        'label' => 'Code of Conduct',
        'icon' => 'fas fa-book',
        'description' => 'Employee has read and acknowledged company code of conduct'
    ],
    'training_evaluation' => [
        'label' => 'Training Evaluation',
        'icon' => 'fas fa-graduation-cap',
        'description' => 'Employee has completed required training programs',
        'allow_na' => true
    ],
    'performance_evaluation' => [
        'label' => 'Probation Evaluation',
        'icon' => 'fas fa-star',
        'description' => 'Probationary period assessment has been conducted'
    ],
    'agreement' => [
        'label' => 'Employment Agreement',
        'icon' => 'fas fa-handshake',
        'description' => 'Employment contract has been signed'
    ],
    'non_compete_agreement' => [
        'label' => 'Non-Compete Agreement',
        'icon' => 'fas fa-ban',
        'description' => 'Non-compete clause has been acknowledged'
    ],
    'medical_insurance_letter' => [
        'label' => 'Medical Insurance Letter',
        'icon' => 'fas fa-heartbeat',
        'description' => 'Medical insurance documentation completed'
    ],
    'confirmation_letter' => [
        'label' => 'Confirmation Letter',
        'icon' => 'fas fa-certificate',
        'description' => 'Employment confirmation letter issued'
    ]
];
?>

<div class="employee-info mb-4">
    <div class="row">
        <div class="col-md-6">
            <h6><strong>Employee:</strong> <?php echo htmlspecialchars($record['employee_name']); ?></h6>
            <p class="mb-1"><strong>EPF:</strong> <?php echo $record['employee_epf']; ?></p>
            <p class="mb-1"><strong>Department:</strong> <?php echo htmlspecialchars($record['employee_department']); ?></p>
        </div>
        <div class="col-md-6">
            <p class="mb-1"><strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($record['onboarding_start_date'])); ?></p>
            <p class="mb-1"><strong>Completion:</strong> <?php echo $record['completion_percentage']; ?>%</p>
            <p class="mb-1"><strong>Status:</strong>
                <?php if ($record['is_completed']): ?>
                    <span class="badge bg-success">Completed</span>
                <?php else: ?>
                    <span class="badge bg-warning">In Progress</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<div class="progress mb-4" style="height: 25px;" id="progress-container-<?php echo $record['id']; ?>">
    <div class="progress-bar <?php echo $record['completion_percentage'] == 100 ? 'bg-success' : 'bg-primary'; ?>"
         id="progress-<?php echo $record['id']; ?>"
         style="width: <?php echo $record['completion_percentage']; ?>%">
        <?php echo $record['completion_percentage']; ?>%
    </div>
</div>

<div class="checklist-items">
    <?php foreach ($checklist_items as $field => $item): ?>
        <div class="card mb-3">
            <div class="card-body">
                <?php if (isset($item['allow_na']) && $item['allow_na']): ?>
                    <!-- Special handling for items that can be N/A -->
                    <div class="d-flex align-items-start">
                        <i class="<?php echo $item['icon']; ?> text-primary me-3 mt-1"></i>
                        <div class="flex-grow-1">
                            <h6 class="mb-2"><?php echo $item['label']; ?></h6>
                            <small class="text-muted d-block mb-3"><?php echo $item['description']; ?></small>

                            <!-- Two radio button options -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio"
                                               name="<?php echo $field; ?>_option_<?php echo $record['id']; ?>"
                                               id="<?php echo $field; ?>_na_<?php echo $record['id']; ?>"
                                               value="na"
                                               <?php echo (isset($record[$field . '_na']) && $record[$field . '_na']) ? 'checked' : ''; ?>
                                               onchange="toggleApplicable(<?php echo $record['id']; ?>, '<?php echo $field; ?>', false)">
                                        <label class="form-check-label" for="<?php echo $field; ?>_na_<?php echo $record['id']; ?>">
                                            <span class="badge bg-secondary me-1">N/A</span>Not Applicable
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio"
                                               name="<?php echo $field; ?>_option_<?php echo $record['id']; ?>"
                                               id="<?php echo $field; ?>_applicable_<?php echo $record['id']; ?>"
                                               value="applicable"
                                               <?php echo (!isset($record[$field . '_na']) || !$record[$field . '_na']) ? 'checked' : ''; ?>
                                               onchange="toggleApplicable(<?php echo $record['id']; ?>, '<?php echo $field; ?>', true)">
                                        <label class="form-check-label" for="<?php echo $field; ?>_applicable_<?php echo $record['id']; ?>">
                                            <span class="badge bg-primary me-1">Track</span>Applicable
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Completion checkbox (shown only when applicable) -->
                            <div class="<?php echo (isset($record[$field . '_na']) && $record[$field . '_na']) ? 'd-none' : ''; ?>"
                                 id="<?php echo $field; ?>_completion_<?php echo $record['id']; ?>">
                                <div class="form-check p-3 bg-light rounded">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           id="<?php echo $field; ?>_completed_<?php echo $record['id']; ?>"
                                           <?php echo $record[$field] ? 'checked' : ''; ?>
                                           onchange="updateChecklist(<?php echo $record['id']; ?>, '<?php echo $field; ?>', this)">
                                    <label class="form-check-label" for="<?php echo $field; ?>_completed_<?php echo $record['id']; ?>">
                                        <strong><i class="fas fa-check-circle me-2"></i>Mark as Completed</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Regular checklist item -->
                    <div class="form-check d-flex align-items-start">
                        <input type="checkbox"
                               class="form-check-input me-3 mt-1"
                               id="<?php echo $field; ?>_<?php echo $record['id']; ?>"
                               <?php echo $record[$field] ? 'checked' : ''; ?>
                               onchange="updateChecklist(<?php echo $record['id']; ?>, '<?php echo $field; ?>', this)">
                        <div class="flex-grow-1">
                            <label class="form-check-label d-flex align-items-center" for="<?php echo $field; ?>_<?php echo $record['id']; ?>">
                                <i class="<?php echo $item['icon']; ?> text-primary me-2"></i>
                                <div>
                                    <h6 class="mb-1"><?php echo $item['label']; ?></h6>
                                    <small class="text-muted"><?php echo $item['description']; ?></small>
                                </div>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($record['is_completed'] && $record['completion_date']): ?>
    <div class="alert alert-success mt-4">
        <i class="fas fa-check-circle me-2"></i>
        <strong>Onboarding Completed!</strong><br>
        Completed on: <?php echo date('M d, Y', strtotime($record['completion_date'])); ?>
    </div>
<?php endif; ?>

<div class="mt-4 text-end">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>

<script>
function toggleApplicable(onboardingId, fieldName, isApplicable) {
    const completionDiv = document.getElementById(fieldName + '_completion_' + onboardingId);

    if (isApplicable) {
        // Show the completion checkbox
        completionDiv.classList.remove('d-none');
    } else {
        // Hide the completion checkbox and clear it if checked
        completionDiv.classList.add('d-none');
        const checkbox = document.getElementById(fieldName + '_completed_' + onboardingId);
        if (checkbox.checked) {
            checkbox.checked = false;
            updateChecklist(onboardingId, fieldName, checkbox);
        }
    }

    // Update N/A status in database
    $.post('onboarding_list.php', {
        action: 'update_na_status',
        onboarding_id: onboardingId,
        field_name: fieldName + '_na',
        field_value: isApplicable ? 0 : 1
    }, function(response) {
        const data = JSON.parse(response);
        if (data.success) {
            // Update progress bar with new completion percentage
            if (data.completion_percentage !== undefined) {
                const progressBar = document.getElementById('progress-' + onboardingId);
                if (progressBar) {
                    progressBar.style.width = data.completion_percentage + '%';
                    progressBar.textContent = data.completion_percentage + '%';

                    if (data.completion_percentage == 100) {
                        progressBar.classList.remove('bg-primary');
                        progressBar.classList.add('bg-success');
                    } else {
                        progressBar.classList.remove('bg-success');
                        progressBar.classList.add('bg-primary');
                    }
                }
            }

            const statusText = isApplicable ? 'applicable for tracking' : 'not applicable';
            showAlert('success', `Training evaluation marked as ${statusText}!`);
        }
    });
}
</script>