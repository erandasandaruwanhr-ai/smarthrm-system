<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

requirePermission('admin', 'employment_levels_all');

$user = getCurrentUser();
$db = new Database();

if ($_POST) {
    if (isset($_POST['add_employment_level'])) {
        $level_name = trim($_POST['level_name']);
        $description = trim($_POST['description']);
        $level_order = intval($_POST['hierarchy_order']);

        if ($level_name) {
            $result = $db->query("INSERT INTO employment_levels (level_name, description, level_order, created_at) VALUES (?, ?, ?, NOW())",
                                [$level_name, $description, $level_order]);
            if ($result) {
                $success = "Employment level added successfully!";
            } else {
                $error = "Failed to add employment level.";
            }
        } else {
            $error = "Level name is required.";
        }
    }

    if (isset($_POST['edit_employment_level'])) {
        $id = $_POST['employment_level_id'];
        $level_name = trim($_POST['level_name']);
        $description = trim($_POST['description']);
        $level_order = intval($_POST['hierarchy_order']);

        if ($id && $level_name) {
            $result = $db->query("UPDATE employment_levels SET level_name = ?, description = ?, level_order = ?, updated_at = NOW() WHERE id = ?",
                                [$level_name, $description, $level_order, $id]);
            if ($result) {
                $success = "Employment level updated successfully!";
            } else {
                $error = "Failed to update employment level.";
            }
        } else {
            $error = "Level name is required.";
        }
    }

    if (isset($_POST['delete_employment_level'])) {
        $id = $_POST['employment_level_id'];
        if ($id) {
            $result = $db->query("DELETE FROM employment_levels WHERE id = ?", [$id]);
            if ($result) {
                $success = "Employment level deleted successfully!";
            } else {
                $error = "Failed to delete employment level.";
            }
        }
    }
}

$employment_levels = $db->fetchAll("SELECT * FROM employment_levels ORDER BY level_order ASC, level_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Employment Levels Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #007bff 0%, #0056b3 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .sidebar-item {
            margin: 0.5rem 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: white;
        }

        .sidebar-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .top-navbar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: auto;
        }

        .dashboard-content {
            padding: 2rem;
        }

        .hierarchy-badge {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0 ms-2">Employment Levels Management</h5>
            </div>

            <div class="user-info">
                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>
                        <?php echo htmlspecialchars($user['name']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <nav style="--bs-breadcrumb-divider: '>';" aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Admin Panel</a></li>
                    <li class="breadcrumb-item active">Employment Levels</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2"><i class="fas fa-layer-group me-2"></i>Employment Levels Management</h1>
                <div>
                    <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addEmploymentLevelModal">
                        <i class="fas fa-plus me-2"></i>Add Employment Level
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Employment Levels List -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Employment Levels</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Level Name</th>
                                    <th>Description</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($employment_levels)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No employment levels found. Click "Add Employment Level" to get started.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($employment_levels as $level): ?>
                                        <tr>
                                            <td><span class="hierarchy-badge"><?php echo $level['level_order']; ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($level['level_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($level['description'] ?? ''); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($level['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary me-1" onclick="editEmploymentLevel(<?php echo $level['id']; ?>, '<?php echo htmlspecialchars($level['level_name']); ?>', '<?php echo htmlspecialchars($level['description'] ?? ''); ?>', <?php echo $level['level_order']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteEmploymentLevel(<?php echo $level['id']; ?>, '<?php echo htmlspecialchars($level['level_name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Employment Level Modal -->
    <div class="modal fade" id="addEmploymentLevelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Employment Level</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Level Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="level_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hierarchy Order</label>
                            <input type="number" class="form-control" name="hierarchy_order" value="1" min="1">
                            <div class="form-text">Lower numbers indicate higher positions in hierarchy</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_employment_level" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Employment Level Modal -->
    <div class="modal fade" id="editEmploymentLevelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Employment Level</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="employment_level_id" id="edit_employment_level_id">
                        <div class="mb-3">
                            <label class="form-label">Level Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="level_name" id="edit_level_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hierarchy Order</label>
                            <input type="number" class="form-control" name="hierarchy_order" id="edit_hierarchy_order" min="1">
                            <div class="form-text">Lower numbers indicate higher positions in hierarchy</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_employment_level" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Employment Level Modal -->
    <div class="modal fade" id="deleteEmploymentLevelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Delete Employment Level</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="employment_level_id" id="delete_employment_level_id">
                        <p>Are you sure you want to delete the employment level "<strong id="delete_employment_level_name"></strong>"?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This action cannot be undone and may affect employees with this employment level.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_employment_level" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Auto-hide sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.querySelector('.sidebar');
                const toggle = document.getElementById('sidebar-toggle');

                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        function editEmploymentLevel(id, level_name, description, level_order) {
            document.getElementById('edit_employment_level_id').value = id;
            document.getElementById('edit_level_name').value = level_name;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_hierarchy_order').value = level_order;
            new bootstrap.Modal(document.getElementById('editEmploymentLevelModal')).show();
        }

        function deleteEmploymentLevel(id, level_name) {
            document.getElementById('delete_employment_level_id').value = id;
            document.getElementById('delete_employment_level_name').textContent = level_name;
            new bootstrap.Modal(document.getElementById('deleteEmploymentLevelModal')).show();
        }
    </script>
</body>
</html>