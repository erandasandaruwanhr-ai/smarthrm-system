<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

requirePermission('admin', 'dropdown_management_all');

$user = getCurrentUser();
$db = new Database();

// Define dropdown categories based on Plan.md
$dropdown_categories = [
    'account_types' => [
        'title' => 'Account Types (1.2)',
        'description' => 'User account types and access levels',
        'source_table' => 'account_types',
        'source_column' => 'type_name'
    ],
    'locations' => [
        'title' => 'Locations (1.4)',
        'description' => 'Company office locations',
        'source_table' => 'locations',
        'source_column' => 'location_name'
    ],
    'employment_levels' => [
        'title' => 'Employment Levels (1.5)',
        'description' => 'Employee hierarchy positions',
        'source_table' => 'employment_levels',
        'source_column' => 'level_name'
    ],
    'gender' => [
        'title' => 'Gender Options',
        'description' => 'Gender selection options',
        'predefined' => ['Male', 'Female']
    ],
    'meal_types' => [
        'title' => 'Meal Types',
        'description' => 'Available meal options',
        'predefined' => [
            'Employee Meal',
            'Employee Special',
            'Seafood - Foreigner',
            'Chicken - Foreigner',
            'Veg - Foreigner',
            'Chicken - Local',
            'Fish - Local',
            'Veg - Local'
        ]
    ],
    'transport_types' => [
        'title' => 'Transport Types',
        'description' => 'Transport request categories',
        'predefined' => [
            'Transport between two plant',
            'Government',
            'Banks',
            'Purchasing',
            'Event',
            'Training',
            'Other'
        ]
    ],
    'departments' => [
        'title' => 'Departments',
        'description' => 'Company departments',
        'predefined' => [
            'Finance',
            'HR',
            'IT',
            'Maintenance',
            'Material Processing',
            'Production',
            'QHS',
            'Supply chain & Logistics'
        ]
    ]
];

if ($_POST) {
    if (isset($_POST['add_dropdown_item'])) {
        $category = $_POST['category'];
        $item_name = trim($_POST['item_name']);
        $description = trim($_POST['description']);

        if ($category && $item_name) {
            $result = $db->query("INSERT INTO dropdown_items (category, item_name, description, created_at) VALUES (?, ?, ?, NOW())",
                                [$category, $item_name, $description]);
            if ($result) {
                $success = "Dropdown item added successfully!";
            } else {
                $error = "Failed to add dropdown item.";
            }
        } else {
            $error = "Category and item name are required.";
        }
    }

    if (isset($_POST['edit_dropdown_item'])) {
        $id = $_POST['item_id'];
        $item_name = trim($_POST['item_name']);
        $description = trim($_POST['description']);

        if ($id && $item_name) {
            $result = $db->query("UPDATE dropdown_items SET item_name = ?, description = ?, updated_at = NOW() WHERE id = ?",
                                [$item_name, $description, $id]);
            if ($result) {
                $success = "Dropdown item updated successfully!";
            } else {
                $error = "Failed to update dropdown item.";
            }
        } else {
            $error = "Item name is required.";
        }
    }

    if (isset($_POST['delete_dropdown_item'])) {
        $id = $_POST['item_id'];
        if ($id) {
            $result = $db->query("DELETE FROM dropdown_items WHERE id = ?", [$id]);
            if ($result) {
                $success = "Dropdown item deleted successfully!";
            } else {
                $error = "Failed to delete dropdown item.";
            }
        }
    }
}

$selected_category = isset($_GET['category']) ? $_GET['category'] : 'gender';

// Get custom dropdown items for selected category
$custom_items = $db->fetchAll("SELECT * FROM dropdown_items WHERE category = ? ORDER BY item_name ASC", [$selected_category]);

// Get predefined items for selected category
$predefined_items = isset($dropdown_categories[$selected_category]['predefined'])
                   ? $dropdown_categories[$selected_category]['predefined']
                   : [];

// Get items from source table if applicable
$source_items = [];
if (isset($dropdown_categories[$selected_category]['source_table'])) {
    $table = $dropdown_categories[$selected_category]['source_table'];
    $column = $dropdown_categories[$selected_category]['source_column'];
    $source_data = $db->fetchAll("SELECT {$column} FROM {$table} ORDER BY {$column} ASC");
    $source_items = array_column($source_data, $column);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Dropdown Management</title>
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

        .category-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .category-card.active {
            border-color: #007bff;
            background: #f8f9fa;
        }

        .item-badge {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            margin: 0.25rem 0.25rem 0.25rem 0;
            display: inline-block;
        }

        .source-badge {
            background: #28a745;
        }

        .predefined-badge {
            background: #ffc107;
            color: #000;
        }

        .custom-badge {
            background: #17a2b8;
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
                <h5 class="mb-0 ms-2">Dropdown Management</h5>
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
                    <li class="breadcrumb-item active">Dropdown Management</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2"><i class="fas fa-list me-2"></i>Dropdown Management</h1>
                <div>
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

            <div class="row">
                <!-- Category Selection -->
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-folder me-2"></i>Dropdown Categories</h6>
                        </div>
                        <div class="card-body p-2">
                            <?php foreach ($dropdown_categories as $key => $category): ?>
                                <div class="category-card <?php echo $selected_category == $key ? 'active' : ''; ?>"
                                     onclick="location.href='?category=<?php echo $key; ?>'">
                                    <h6 class="mb-1"><?php echo $category['title']; ?></h6>
                                    <small class="text-muted"><?php echo $category['description']; ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Category Content -->
                <div class="col-md-8">
                    <?php if ($selected_category): ?>
                        <div class="card shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-list me-2"></i><?php echo $dropdown_categories[$selected_category]['title']; ?>
                                </h6>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                    <i class="fas fa-plus me-1"></i>Add Custom Item
                                </button>
                            </div>
                            <div class="card-body">
                                <!-- Source Items (from other tables) -->
                                <?php if (!empty($source_items)): ?>
                                    <h6 class="text-success mb-3">
                                        <i class="fas fa-database me-2"></i>From <?php echo ucfirst(str_replace('_', ' ', $dropdown_categories[$selected_category]['source_table'])); ?>
                                    </h6>
                                    <div class="mb-4">
                                        <?php foreach ($source_items as $item): ?>
                                            <span class="item-badge source-badge"><?php echo htmlspecialchars($item); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Predefined Items -->
                                <?php if (!empty($predefined_items)): ?>
                                    <h6 class="text-warning mb-3">
                                        <i class="fas fa-star me-2"></i>Predefined Options
                                    </h6>
                                    <div class="mb-4">
                                        <?php foreach ($predefined_items as $item): ?>
                                            <span class="item-badge predefined-badge"><?php echo htmlspecialchars($item); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Custom Items -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-info mb-0">
                                        <i class="fas fa-plus-circle me-2"></i>Custom Items
                                    </h6>
                                </div>

                                <?php if (!empty($custom_items)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Item Name</th>
                                                    <th>Description</th>
                                                    <th>Created At</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($custom_items as $item): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                                        <td>
                                                            <button class="btn btn-sm btn-primary me-1" onclick="editItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>', '<?php echo htmlspecialchars($item['description']); ?>')">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                        <p class="text-muted">No custom items found. Click "Add Custom Item" to get started.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Custom Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="category" value="<?php echo $selected_category; ?>">
                        <div class="mb-3">
                            <label class="form-label">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="item_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_dropdown_item" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div class="modal fade" id="editItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="item_id" id="edit_item_id">
                        <div class="mb-3">
                            <label class="form-label">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="item_name" id="edit_item_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_dropdown_item" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Item Modal -->
    <div class="modal fade" id="deleteItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Delete Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="item_id" id="delete_item_id">
                        <p>Are you sure you want to delete the item "<strong id="delete_item_name"></strong>"?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This action cannot be undone.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_dropdown_item" class="btn btn-danger">
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

        function editItem(id, name, description) {
            document.getElementById('edit_item_id').value = id;
            document.getElementById('edit_item_name').value = name;
            document.getElementById('edit_description').value = description;
            new bootstrap.Modal(document.getElementById('editItemModal')).show();
        }

        function deleteItem(id, name) {
            document.getElementById('delete_item_id').value = id;
            document.getElementById('delete_item_name').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteItemModal')).show();
        }
    </script>
</body>
</html>