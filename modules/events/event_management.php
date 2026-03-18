<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user is superadmin
if (!isSuperAdmin()) {
    header('Location: ../../dashboard.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_event'])) {
            // Generate Event ID
            $year = date('Y');
            $lastEvent = $db->fetch("SELECT event_id FROM events WHERE event_id LIKE 'EVT-$year-%' ORDER BY id DESC LIMIT 1");

            if ($lastEvent) {
                $lastNumber = intval(substr($lastEvent['event_id'], -6));
                $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '000001';
            }

            $event_id = "EVT-$year-$newNumber";

            // Insert new event
            $db->insert('events', [
                'event_id' => $event_id,
                'title' => $_POST['title'],
                'description' => $_POST['description'],
                'category' => $_POST['category'],
                'event_date' => $_POST['event_date'],
                'start_time' => $_POST['start_time'],
                'end_time' => $_POST['end_time'],
                'location_id' => !empty($_POST['location_id']) ? $_POST['location_id'] : null,
                'venue_details' => $_POST['venue_details'],
                'status' => $_POST['status']
            ]);

            $success_message = "Event $event_id added successfully!";
        } elseif (isset($_POST['update_event'])) {
            // Update existing event
            $db->update('events', [
                'title' => $_POST['title'],
                'description' => $_POST['description'],
                'category' => $_POST['category'],
                'event_date' => $_POST['event_date'],
                'start_time' => $_POST['start_time'],
                'end_time' => $_POST['end_time'],
                'location_id' => !empty($_POST['location_id']) ? $_POST['location_id'] : null,
                'venue_details' => $_POST['venue_details'],
                'status' => $_POST['status']
            ], 'id = ?', [$_POST['event_id']]);

            $success_message = "Event updated successfully!";
        } elseif (isset($_POST['delete_event'])) {
            // Delete event
            $db->delete('events', 'id = ?', [$_POST['event_id']]);
            $success_message = "Event deleted successfully!";
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get events for display
$events = $db->fetchAll("
    SELECT e.*, l.location_name
    FROM events e
    LEFT JOIN locations l ON e.location_id = l.id
    ORDER BY e.event_date DESC
");

// Get locations for dropdown
$locations = $db->fetchAll("SELECT * FROM locations WHERE is_active = 1 ORDER BY location_name");

// Event categories
$categories = [
    'Training & Development',
    'Welfare & Celebrations',
    'Safety & Health',
    'Quality & KAIZEN',
    'Sports & Recreation',
    'CSR Activities',
    'Cultural Events',
    'Annual Events',
    'Other'
];

// Event statuses
$statuses = ['Pending', 'Done', 'Postponed', 'Cancelled'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Event Management</title>
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

        .form-card, .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .card-header-modern {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 1px solid #dee2e6;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem 2rem;
            margin: -2rem -2rem 2rem -2rem;
        }

        .card-header-modern h5 {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
        }

        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
        }

        .status-pending { background-color: #ffc107; color: #000; }
        .status-done { background-color: #28a745; color: #fff; }
        .status-postponed { background-color: #17a2b8; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }

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
                        <h1><i class="fas fa-calendar-plus me-3"></i>Event Management</h1>
                        <p>Create and manage company events, meetings, and organizational activities</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Event Management</a></li>
                    <li class="breadcrumb-item active">Event Management</li>
                </ol>
            </nav>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3>Event Management</h3>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                        <i class="fas fa-plus me-2"></i>Add Event
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>

            <!-- Events Table -->
            <div class="content-card">
                <div class="card-header-modern">
                    <h5>
                        <i class="fas fa-list me-2"></i>Event Management
                    </h5>
                </div>
                    <?php if (empty($events)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Events Found</h5>
                            <p class="text-muted">Start by adding your first event.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                                <i class="fas fa-plus me-2"></i>Add First Event
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Event ID</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Date & Time</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($event['event_id']); ?></code></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($event['description'], 0, 50)) . (strlen($event['description']) > 50 ? '...' : ''); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($event['category']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('g:i A', strtotime($event['start_time'])); ?> -
                                                    <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($event['location_name'] ?? 'All Locations'); ?>
                                                <?php if ($event['venue_details']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($event['venue_details']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge status-<?php echo strtolower($event['status']); ?>">
                                                    <?php echo $event['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-outline-primary btn-sm" onclick="editEvent(<?php echo $event['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm" onclick="deleteEvent(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['event_id']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Event Modal -->
    <div class="modal fade" id="addEventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="add_event" value="1">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Event Title *</label>
                                    <input type="text" class="form-control" name="title" required maxlength="200">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category *</label>
                                    <select class="form-control" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" maxlength="500"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="event_date" class="form-label">Event Date *</label>
                                    <input type="date" class="form-control" name="event_date" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="start_time" class="form-label">Start Time *</label>
                                    <input type="time" class="form-control" name="start_time" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="end_time" class="form-label">End Time *</label>
                                    <input type="time" class="form-control" name="end_time" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location_id" class="form-label">Location</label>
                                    <select class="form-control" name="location_id">
                                        <option value="">All Locations</option>
                                        <?php foreach ($locations as $location): ?>
                                            <option value="<?php echo $location['id']; ?>"><?php echo htmlspecialchars($location['location_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-control" name="status" required>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?php echo $status; ?>" <?php echo $status === 'Pending' ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="venue_details" class="form-label">Venue Details</label>
                            <input type="text" class="form-control" name="venue_details" placeholder="e.g., Conference Room A, Auditorium">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Add Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Event Modal -->
    <div class="modal fade" id="editEventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body" id="editEventForm">
                        <!-- Form will be populated by JavaScript -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteEventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete event <strong id="deleteEventId"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="delete_event" value="1">
                        <input type="hidden" name="event_id" id="deleteEventIdInput">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Events data for JavaScript
        const eventsData = <?php echo json_encode($events); ?>;
        const locations = <?php echo json_encode($locations); ?>;
        const categories = <?php echo json_encode($categories); ?>;
        const statuses = <?php echo json_encode($statuses); ?>;

        function editEvent(eventId) {
            const event = eventsData.find(e => e.id == eventId);
            if (!event) return;

            const form = document.getElementById('editEventForm');
            form.innerHTML = `
                <input type="hidden" name="update_event" value="1">
                <input type="hidden" name="event_id" value="${event.id}">

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Event ID</label>
                            <input type="text" class="form-control" value="${event.event_id}" disabled>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-control" name="category" required>
                                ${categories.map(cat =>
                                    `<option value="${cat}" ${cat === event.category ? 'selected' : ''}>${cat}</option>`
                                ).join('')}
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Event Title *</label>
                    <input type="text" class="form-control" name="title" value="${event.title}" required maxlength="200">
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="3" maxlength="500">${event.description || ''}</textarea>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Event Date *</label>
                            <input type="date" class="form-control" name="event_date" value="${event.event_date}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Start Time *</label>
                            <input type="time" class="form-control" name="start_time" value="${event.start_time}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">End Time *</label>
                            <input type="time" class="form-control" name="end_time" value="${event.end_time}" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <select class="form-control" name="location_id">
                                <option value="">All Locations</option>
                                ${locations.map(loc =>
                                    `<option value="${loc.id}" ${loc.id == event.location_id ? 'selected' : ''}>${loc.location_name}</option>`
                                ).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-control" name="status" required>
                                ${statuses.map(status =>
                                    `<option value="${status}" ${status === event.status ? 'selected' : ''}>${status}</option>`
                                ).join('')}
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Venue Details</label>
                    <input type="text" class="form-control" name="venue_details" value="${event.venue_details || ''}" placeholder="e.g., Conference Room A, Auditorium">
                </div>
            `;

            new bootstrap.Modal(document.getElementById('editEventModal')).show();
        }

        function deleteEvent(eventId, eventIdString) {
            document.getElementById('deleteEventId').textContent = eventIdString;
            document.getElementById('deleteEventIdInput').value = eventId;
            new bootstrap.Modal(document.getElementById('deleteEventModal')).show();
        }
    </script>
</body>
</html>