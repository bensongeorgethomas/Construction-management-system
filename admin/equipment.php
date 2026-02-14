<?php
require_once '../conn.php';
require_once '../includes/csrf.php';

// Redirect to login if user is not logged in or not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}


// Handle form submissions for adding, editing, and deleting equipment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRF();
    switch ($_POST['action']) {
        case 'add':
            // Prepare statement for adding new equipment
            $stmt = $conn->prepare("INSERT INTO equipment (name, type, model, serial_number, purchase_date, cost, hourly_rate, status, location, last_maintenance, project_id, assigned_to, task_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            // --- FIX START ---
            // Store results of ternary operations in variables first
            $project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
            $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;
            $task_id = !empty($_POST['task_id']) ? $_POST['task_id'] : null;
            // --- FIX END ---
            
            $stmt->bind_param(
                "sssssddsssiii",
                $_POST['name'],
                $_POST['type'],
                $_POST['model'],
                $_POST['serial_number'],
                $_POST['purchase_date'],
                $_POST['cost'],
                $_POST['hourly_rate'],
                $_POST['status'],
                $_POST['location'],
                $_POST['maintenance_date'],
                $project_id,  // Use the new variable
                $assigned_to, // Use the new variable
                $task_id      // Use the new variable
            );

            if ($stmt->execute()) {
                $success_message = "Equipment added successfully!";
            } else {
                $error_message = "Error adding equipment: " . $stmt->error;
            }
            break;

        case 'edit':
            // Prepare statement for updating existing equipment
            $stmt = $conn->prepare("UPDATE equipment SET name=?, type=?, model=?, serial_number=?, purchase_date=?, cost=?, hourly_rate=?, status=?, location=?, last_maintenance=?, project_id=?, assigned_to=?, task_id=? WHERE id=?");

            // --- FIX START ---
            // Store results of ternary operations in variables first
            $project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
            $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;
            $task_id = !empty($_POST['task_id']) ? $_POST['task_id'] : null;
            // --- FIX END ---

            $stmt->bind_param(
                "sssssddsssiiii",
                $_POST['name'],
                $_POST['type'],
                $_POST['model'],
                $_POST['serial_number'],
                $_POST['purchase_date'],
                $_POST['cost'],
                $_POST['hourly_rate'],
                $_POST['status'],
                $_POST['location'],
                $_POST['maintenance_date'],
                $project_id,  // Use the new variable
                $assigned_to, // Use the new variable
                $task_id,     // Use the new variable
                $_POST['id']
            );

            if ($stmt->execute()) {
                $success_message = "Equipment updated successfully!";
            } else {
                $error_message = "Error updating equipment: " . $stmt->error;
            }
            break;

        case 'delete':
            // Soft delete by setting deleted_at timestamp
            $stmt = $conn->prepare("UPDATE equipment SET deleted_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $_POST['id']);

            if ($stmt->execute()) {
                $success_message = "Equipment deleted successfully!";
            } else {
                $error_message = "Error deleting equipment: " . $stmt->error;
            }
            break;
    }
}

// --- DATA FETCHING ---

// Fetch all active equipment with joined data for display
$equipment_query = "
    SELECT 
        e.*, 
        p.name AS project_name, 
        u.name AS worker_name, 
        t.title AS task_title
    FROM equipment e
    LEFT JOIN projects p ON e.project_id = p.id
    LEFT JOIN users u ON e.assigned_to = u.id
    LEFT JOIN tasks t ON e.task_id = t.id
    WHERE e.deleted_at IS NULL 
    ORDER BY e.name ASC
";
$equipment_result = $conn->query($equipment_query);

// Fetch data for dropdowns
$projects = $conn->query("SELECT id, name FROM projects WHERE status = 'active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$workers = $conn->query("SELECT id, name FROM users WHERE role = 'worker' AND status = 'approved' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$tasks = $conn->query("SELECT id, title FROM tasks WHERE status NOT IN ('completed', 'rejected') ORDER BY title")->fetch_all(MYSQLI_ASSOC);

// Fetch stats for the header cards
$total_equipment = $conn->query("SELECT COUNT(*) as count FROM equipment WHERE deleted_at IS NULL")->fetch_assoc()['count'];
$available_equipment = $conn->query("SELECT COUNT(*) as count FROM equipment WHERE status = 'Available' AND deleted_at IS NULL")->fetch_assoc()['count'];
$in_use_equipment = $conn->query("SELECT COUNT(*) as count FROM equipment WHERE status = 'In Use' AND deleted_at IS NULL")->fetch_assoc()['count'];
$maintenance_equipment = $conn->query("SELECT COUNT(*) as count FROM equipment WHERE status = 'Maintenance' AND deleted_at IS NULL")->fetch_assoc()['count'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Management - Construct.</title>
    <link href="admin_style.css" rel="stylesheet">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background-color: var(--bg-card); margin: auto; padding: 2rem; border: 1px solid var(--border); width: 90%; max-width: 900px; border-radius: var(--radius); box-shadow: var(--shadow-lg); position: relative; animation: slideDown 0.3s ease-out; }
        .close-btn { color: var(--text-light); position: absolute; top: 1.5rem; right: 1.5rem; font-size: 1.5rem; font-weight: bold; cursor: pointer; transition: var(--transition); line-height: 1; }
        .close-btn:hover { color: var(--text-main); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar_admin.php'; ?>
        
        <div class="main-content">
            <header class="header">
                <h1>Equipment Management</h1>
                 <div class="user-info">
                    Welcome, <strong><?= htmlspecialchars($_SESSION['name']); ?></strong>
                    <a href="../logout.php">Logout</a>
                </div>
            </header>

            <div class="content-wrapper">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success" id="success-alert"><?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" id="error-alert"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>

                <div class="dashboard-grid">
                    <div class="card"><h3>Total Equipment</h3><p class="value"><?= $total_equipment ?></p></div>
                    <div class="card"><h3>Available</h3><p class="value"><?= $available_equipment ?></p></div>
                    <div class="card"><h3>In Use</h3><p class="value"><?= $in_use_equipment ?></p></div>
                    <div class="card"><h3>In Maintenance</h3><p class="value"><?= $maintenance_equipment ?></p></div>
                </div>

                <div class="card" style="margin-bottom: 2rem;">
                    <div class="section-header">
                        <h2>Add New Equipment</h2>
                    </div>
                    <form method="POST" action="">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add">
                        <div class="form-grid">
                            <div class="form-group"><label for="name">Equipment Name *</label><input type="text" id="name" name="name" class="form-control" required></div>
                            <div class="form-group"><label for="type">Type *</label><select id="type" name="type" class="form-control" required><option value="">Select Type</option><option>Heavy Machinery</option><option>Power Tools</option><option>Hand Tools</option><option>Safety Equipment</option><option>Transport</option><option>Measuring Tools</option><option>Other</option></select></div>
                            <div class="form-group"><label for="model">Model</label><input type="text" id="model" name="model" class="form-control"></div>
                            <div class="form-group"><label for="serial_number">Serial Number</label><input type="text" id="serial_number" name="serial_number" class="form-control"></div>
                            <div class="form-group"><label for="purchase_date">Purchase Date</label><input type="date" id="purchase_date" name="purchase_date" class="form-control"></div>
                            <div class="form-group"><label for="cost">Cost ($)</label><input type="number" id="cost" name="cost" step="0.01" min="0" class="form-control"></div>
                            <div class="form-group"><label for="hourly_rate">Hourly Rate ($)</label><input type="number" id="hourly_rate" name="hourly_rate" step="0.01" min="0" class="form-control"></div>
                            <div class="form-group"><label for="status">Status *</label><select id="status" name="status" class="form-control" required><option>Available</option><option>In Use</option><option>Maintenance</option><option>Out of Service</option></select></div>
                            <div class="form-group"><label for="location">Location</label><input type="text" id="location" name="location" class="form-control"></div>
                            <div class="form-group"><label for="maintenance_date">Last Maintenance</label><input type="date" id="maintenance_date" name="maintenance_date" class="form-control"></div>
                            <div class="form-group"><label for="project_id">Assigned Project</label><select name="project_id" class="form-control"><option value="">None</option><?php foreach ($projects as $project): ?><option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label for="assigned_to">Assigned Worker</label><select name="assigned_to" class="form-control"><option value="">None</option><?php foreach ($workers as $worker): ?><option value="<?= $worker['id'] ?>"><?= htmlspecialchars($worker['name']) ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label for="task_id">Assigned Task</label><select name="task_id" class="form-control"><option value="">None</option><?php foreach ($tasks as $task): ?><option value="<?= $task['id'] ?>"><?= htmlspecialchars($task['title']) ?></option><?php endforeach; ?></select></div>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Equipment</button>
                    </form>
                </div>

                <div class="table-container">
                    <div class="section-header">
                        <h2>Equipment Inventory</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th><th>Name</th><th>Type</th><th>Status</th><th>Location</th>
                                <th>Project</th><th>Worker</th><th>Task</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($equipment_result && $equipment_result->num_rows > 0): ?>
                                <?php while ($eq = $equipment_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $eq['id'] ?></td>
                                        <td><b><?= htmlspecialchars($eq['name']) ?></b><br><small><?= htmlspecialchars($eq['model'] ?? '') ?></small></td>
                                        <td><?= htmlspecialchars($eq['type']) ?></td>
                                        <td>
                                            <?php
                                                $statusClass = 'status-inactive'; // Default
                                                switch(strtolower($eq['status'])) {
                                                    case 'available': $statusClass = 'status-active'; break;
                                                    case 'in use': $statusClass = 'status-pending'; break;
                                                    case 'maintenance': $statusClass = 'status-pending'; break; // Using pending color (yellow/orange) for maintenance too or maybe inactive
                                                    case 'out of service': $statusClass = 'status-inactive'; break;
                                                }
                                                // Override for specific logic if needed, or mapped to CSS classes
                                            ?>
                                            <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($eq['status']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($eq['location'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($eq['project_name'] ?? 'Unassigned') ?></td>
                                        <td><?= htmlspecialchars($eq['worker_name'] ?? 'Unassigned') ?></td>
                                        <td><?= htmlspecialchars($eq['task_title'] ?? 'Unassigned') ?></td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;" onclick='openEditModal(<?= json_encode($eq) ?>)'>Edit</button>
                                                <form method="POST" onsubmit="return confirm('Are you sure?')" style="margin:0;">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $eq['id'] ?>">
                                                    <button type="submit" class="btn btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="9" style="text-align:center;">No equipment found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEditModal()">&times;</span>
            <div class="section-header">
                <h2>Edit Equipment</h2>
            </div>
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="form-grid">
                    <div class="form-group"><label for="edit-name">Name *</label><input type="text" id="edit-name" name="name" class="form-control" required></div>
                    <div class="form-group"><label for="edit-type">Type *</label><select id="edit-type" name="type" class="form-control" required><option>Heavy Machinery</option><option>Power Tools</option><option>Hand Tools</option><option>Safety Equipment</option><option>Transport</option><option>Measuring Tools</option><option>Other</option></select></div>
                    <div class="form-group"><label for="edit-model">Model</label><input type="text" id="edit-model" name="model" class="form-control"></div>
                    <div class="form-group"><label for="edit-serial_number">Serial Number</label><input type="text" id="edit-serial_number" name="serial_number" class="form-control"></div>
                    <div class="form-group"><label for="edit-purchase_date">Purchase Date</label><input type="date" id="edit-purchase_date" name="purchase_date" class="form-control"></div>
                    <div class="form-group"><label for="edit-cost">Cost ($)</label><input type="number" id="edit-cost" name="cost" step="0.01" min="0" class="form-control"></div>
                    <div class="form-group"><label for="edit-hourly_rate">Hourly Rate ($)</label><input type="number" id="edit-hourly_rate" name="hourly_rate" step="0.01" min="0" class="form-control"></div>
                    <div class="form-group"><label for="edit-status">Status *</label><select id="edit-status" name="status" class="form-control" required><option>Available</option><option>In Use</option><option>Maintenance</option><option>Out of Service</option></select></div>
                    <div class="form-group"><label for="edit-location">Location</label><input type="text" id="edit-location" name="location" class="form-control"></div>
                    <div class="form-group"><label for="edit-maintenance_date">Last Maintenance</label><input type="date" id="edit-maintenance_date" name="maintenance_date" class="form-control"></div>
                    <div class="form-group"><label for="edit-project_id">Project</label><select id="edit-project_id" name="project_id" class="form-control"><option value="">None</option><?php foreach ($projects as $project): ?><option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="edit-assigned_to">Worker</label><select id="edit-assigned_to" name="assigned_to" class="form-control"><option value="">None</option><?php foreach ($workers as $worker): ?><option value="<?= $worker['id'] ?>"><?= htmlspecialchars($worker['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="edit-task_id">Task</label><select id="edit-task_id" name="task_id" class="form-control"><option value="">None</option><?php foreach ($tasks as $task): ?><option value="<?= $task['id'] ?>"><?= htmlspecialchars($task['title']) ?></option><?php endforeach; ?></select></div>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        const editModal = document.getElementById('editModal');

        function openEditModal(equipmentData) {
            // Populate the modal form with the data of the equipment to be edited
            document.getElementById('edit-id').value = equipmentData.id;
            document.getElementById('edit-name').value = equipmentData.name;
            document.getElementById('edit-type').value = equipmentData.type;
            document.getElementById('edit-model').value = equipmentData.model;
            document.getElementById('edit-serial_number').value = equipmentData.serial_number;
            document.getElementById('edit-purchase_date').value = equipmentData.purchase_date;
            document.getElementById('edit-cost').value = equipmentData.cost;
            document.getElementById('edit-hourly_rate').value = equipmentData.hourly_rate;
            document.getElementById('edit-status').value = equipmentData.status;
            document.getElementById('edit-location').value = equipmentData.location;
            document.getElementById('edit-maintenance_date').value = equipmentData.last_maintenance;
            document.getElementById('edit-project_id').value = equipmentData.project_id || '';
            document.getElementById('edit-assigned_to').value = equipmentData.assigned_to || '';
            document.getElementById('edit-task_id').value = equipmentData.task_id || '';
            editModal.classList.add('show');
        }

        function closeEditModal() {
            editModal.classList.remove('show');
        }
        
        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            if (event.target == editModal) {
                closeEditModal();
            }
        }

        // Auto-hide success/error alerts after 5 seconds
        setTimeout(() => {
            const successAlert = document.getElementById('success-alert');
            const errorAlert = document.getElementById('error-alert');
            if (successAlert) successAlert.style.display = 'none';
            if (errorAlert) errorAlert.style.display = 'none';
        }, 5000);
    </script>
</body>
</html>
