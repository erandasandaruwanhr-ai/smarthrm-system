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

// Get notes for this onboarding
$notes = $database->fetchAll("
    SELECT n.*, e.name as created_by_name
    FROM onboarding_notes n
    LEFT JOIN employees e ON n.created_by = e.epf_number
    WHERE n.onboarding_id = ?
    ORDER BY n.created_at DESC
", [$onboarding_id]);
?>

<div class="employee-info mb-4">
    <div class="row">
        <div class="col-md-6">
            <h6><strong>Employee:</strong> <?php echo htmlspecialchars($record['employee_name']); ?></h6>
            <p class="mb-1"><strong>EPF:</strong> <?php echo $record['employee_epf']; ?></p>
        </div>
        <div class="col-md-6">
            <p class="mb-1"><strong>Department:</strong> <?php echo htmlspecialchars($record['employee_department']); ?></p>
            <p class="mb-1"><strong>Progress:</strong> <?php echo $record['completion_percentage']; ?>%</p>
        </div>
    </div>
</div>

<!-- Add New Note -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-plus me-2"></i>Add New Note</h6>
    </div>
    <div class="card-body">
        <form id="noteForm">
            <input type="hidden" name="action" value="add_note">
            <input type="hidden" name="onboarding_id" value="<?php echo $onboarding_id; ?>">
            <div class="mb-3">
                <textarea name="note_text" id="note_text" class="form-control" rows="3"
                          placeholder="Enter your note about this employee's onboarding progress..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Add Note
            </button>
        </form>
    </div>
</div>

<!-- Existing Notes -->
<div class="notes-list">
    <h6><i class="fas fa-clock me-2"></i>Notes History (<?php echo count($notes); ?> notes)</h6>

    <?php if (empty($notes)): ?>
        <div class="text-center py-4 text-muted">
            <i class="fas fa-sticky-note fa-3x mb-3"></i>
            <p>No notes have been added yet.</p>
        </div>
    <?php else: ?>
        <div class="notes-container" style="max-height: 400px; overflow-y: auto;">
            <?php foreach ($notes as $note): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong><?php echo htmlspecialchars($note['created_by_name'] ?? 'Unknown User'); ?></strong>
                                <small class="text-muted ms-2">
                                    <?php echo date('M d, Y \a\t g:i A', strtotime($note['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($note['note_text'])); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="mt-4 text-end">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>

<script>
$(document).ready(function() {
    $('#noteForm').on('submit', function(e) {
        e.preventDefault();

        const noteText = $('#note_text').val().trim();
        if (noteText === '') {
            alert('Please enter a note before submitting.');
            return;
        }

        $.post('onboarding_list.php', $(this).serialize(), function(response) {
            // Reload the notes modal content
            viewNotes(<?php echo $onboarding_id; ?>);
        });
    });
});
</script>