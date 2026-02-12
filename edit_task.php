<?php
require_once 'conn.php';

// --- SECURITY & ACCESS CONTROL ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- TASK CATEGORY DEFINITIONS ---
$task_categories = [
    'House' => ['Site Work & Foundation', 'Framing & Structural', 'Roofing & Siding', 'Plumbing', 'Electrical', 'HVAC', 'Insulation & Drywall', 'Interior Finishes (Flooring, Painting)', 'Exterior Work (Landscaping, Driveway)'],
    'School' => ['Site Preparation', 'Foundation & Structural Steel', 'Classroom Construction', 'Auditorium & Gym Construction', 'Electrical & Networking Infrastructure', 'Plumbing & Fire Safety Systems', 'HVAC & Ventilation', 'Interior & Classroom Furnishing', 'Playground & Sports Facilities'],
    'Commercial Building' => ['Foundation & Excavation', 'Steel Erection', 'Curtain Wall & Glazing', 'Commercial HVAC', 'High-Voltage Electrical', 'Fire Suppression Systems', 'Data Center & Comms Rooms', 'Office Fit-Out', 'Parking Structure'],
    'Other' => ['General Task', 'Planning', 'Execution', 'Review', 'Completion']
];

$task = null;
$task_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// --- HANDLE FORM SUBMISSION (UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task_id'])) {
    require_once 'includes/csrf.php';
    requireCSRF();
    $task_id = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
    $project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
    $assignee_id = filter_input(INPUT_POST, 'assignee_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $due_date = $_POST['due_date'] ?? '';
    $status = $_POST['status'] ?? '';
    $priority = $_POST['priority'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $materials_needed = trim($_POST['materials_needed'] ?? '');
    $cost_estimate = filter_input(INPUT_POST, 'cost_estimate', FILTER_VALIDATE_FLOAT);
    $is_critical = isset($_POST['is_critical']) ? 1 : 0;
    
    $assignee_id = !empty($assignee_id) ? $assignee_id : NULL;
    $cost_estimate = !empty($cost_estimate) ? $cost_estimate : NULL;
    
    try {
        $sql = "UPDATE tasks SET 
                    project_id = ?, assignee_id = ?, title = ?, category = ?, description = ?, 
                    materials_needed = ?, cost_estimate = ?, start_date = ?, due_date = ?, 
                    status = ?, priority = ?, is_critical = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        // --- FIXED: Corrected the type string to have 13 characters for 13 placeholders ---
        $stmt->bind_param(
            "iissssdsissii",
            $project_id, $assignee_id, $title, $category, $description,
            $materials_needed, $cost_estimate, $start_date, $due_date,
            $status, $priority, $is_critical,
            $task_id
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = "Task '{$title}' was updated successfully!";
            header("Location: tasks.php");
            exit;
        } else {
             throw new Exception("Execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update task: " . $e->getMessage();
    }
}

// --- FETCH TASK DATA FOR THE FORM (GET REQUEST) ---
if ($task_id) {
    $stmt = $conn->prepare("
        SELECT t.*, p.project_type 
        FROM tasks t 
        JOIN projects p ON t.project_id = p.id
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    $stmt->close();

    if (!$task) {
        die("Task not found.");
    }
} else {
    die("No task ID provided.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Task - Construct.</title>
    <style>
        :root {
            --primary-color: #f59e0b; --dark-bg: #1f2937; --light-bg: #f9fafb;
            --white-bg: #ffffff; --text-dark: #111827; --text-medium: #4b5563;
        }
        body { font-family: sans-serif; background-color: var(--light-bg); display: flex; }
         .sidebar {
            width: 260px;
            background-color: var(--dark-bg);
            color: #d1d5db;
            height: 100vh;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            position: fixed;
        }
        .sidebar .logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--white-bg);
            margin-bottom: 2rem;
        }
        .sidebar .logo span { color: var(--primary-color); }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #d1d5db;
            text-decoration: none;
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: background-color 0.2s, color 0.2s;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background-color: var(--primary-color);
            color: var(--white-bg);
        }
        .sidebar-nav a svg { width: 20px; height: 20px; }
        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 2rem;
        }
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2rem; }
        .header h1 { font-size: 2rem; color: var(--text-dark); margin-bottom: 2rem; }
        .form-wrapper { background-color: var(--white-bg); border-radius: 12px; padding: 2.5rem; max-width: 900px; margin: 0 auto; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { font-weight: 600; margin-bottom: 0.5rem; font-size: 0.9rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #e5e7eb; font-size: 1rem; }
        .form-group-checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
        textarea { resize: vertical; min-height: 100px; }
        .form-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; text-decoration: none; cursor: pointer; }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-secondary { background-color: #e5e7eb; color: var(--text-dark); }
    </style>
</head>
<body>
    <div class="main-content">
        <header class="header"><h1>Edit Task: <?= htmlspecialchars($task['title']) ?></h1></header>
        <div class="form-wrapper">
            <form method="POST" class="form-grid">
                <?= csrfField() ?>
                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">

                <div class="form-group full-width">
                    <label for="title">Task Title</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($task['title']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="project_id">Project</label>
                    <select id="project_id" name="project_id" required>
                        <?php
                        $result = $conn->query("SELECT id, name, project_type FROM projects WHERE deleted_at IS NULL ORDER BY name");
                        while ($row = $result->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" data-type="<?= htmlspecialchars($row['project_type']) ?>" <?= ($row['id'] == $task['project_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="category">Task Category</label>
                    <select id="category" name="category" required>
                        </select>
                </div>

                <div class="form-group">
                    <label for="assignee_id">Assign To (Optional)</label>
                    <select id="assignee_id" name="assignee_id">
                        <option value="">Select Worker</option>
                        <?php
                        $workerResult = $conn->query("SELECT u.id, u.name FROM users u WHERE u.role = 'worker' AND u.status = 'approved' ORDER BY u.name ASC");
                        while ($worker = $workerResult->fetch_assoc()): ?>
                            <option value="<?= $worker['id'] ?>" <?= ($worker['id'] == $task['assignee_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($worker['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority" required>
                        <option value="low" <?= ($task['priority'] == 'low') ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= ($task['priority'] == 'medium') ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= ($task['priority'] == 'high') ? 'selected' : '' ?>>High</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($task['start_date']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date" value="<?= htmlspecialchars($task['due_date']) ?>" required>
                </div>
                
                <div class="form-group full-width">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?= htmlspecialchars($task['description']) ?></textarea>
                </div>
                
                <div class="form-group full-width">
                    <label for="materials_needed">Materials Needed</label>
                    <textarea id="materials_needed" name="materials_needed"><?= htmlspecialchars($task['materials_needed']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="cost_estimate">Estimated Cost (₹)</label>
                    <input type="number" id="cost_estimate" name="cost_estimate" step="0.01" value="<?= htmlspecialchars($task['cost_estimate']) ?>">
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="todo" <?= ($task['status'] == 'todo') ? 'selected' : '' ?>>To Do</option>
                        <option value="in-progress" <?= ($task['status'] == 'in-progress') ? 'selected' : '' ?>>In Progress</option>
                        <option value="review" <?= ($task['status'] == 'review') ? 'selected' : '' ?>>Review</option>
                        <option value="completed" <?= ($task['status'] == 'completed') ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
<div class="form-actions">
                    <a href="tasks.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const categoriesByProjectType = <?= json_encode($task_categories); ?>;
        const projectSelect = document.getElementById('project_id');
        const categorySelect = document.getElementById('category');
        const currentlySelectedCategory = "<?= htmlspecialchars($task['category']) ?>";

        function populateCategories() {
            const selectedOption = projectSelect.options[projectSelect.selectedIndex];
            const projectType = selectedOption.dataset.type;

            categorySelect.innerHTML = '';

            if (projectType && categoriesByProjectType[projectType]) {
                categorySelect.disabled = false;
                let defaultOption = new Option('Select a Category', '');
                categorySelect.add(defaultOption);

                categoriesByProjectType[projectType].forEach(function(category) {
                    let option = new Option(category, category);
                    if (category === currentlySelectedCategory) {
                        option.selected = true;
                    }
                    categorySelect.add(option);
                });
            } else {
                let disabledOption = new Option('First select a project', '');
                categorySelect.add(disabledOption);
                categorySelect.disabled = true;
            }
        }

        projectSelect.addEventListener('change', populateCategories);
        
        // Initial population when the page loads
        populateCategories();
    });
    </script>
</body>
</html>