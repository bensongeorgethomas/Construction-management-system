<?php
require_once 'conn.php';
require_once 'includes/csrf.php';

// --- SECURITY & ACCESS CONTROL ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- (NEW) TASK CATEGORY DEFINITIONS ---
// Here we define which categories belong to which project type.
$task_categories = [
    'House' => [
        'Site Work & Foundation', 'Framing & Structural', 'Roofing & Siding', 
        'Plumbing', 'Electrical', 'HVAC', 'Insulation & Drywall', 
        'Interior Finishes (Flooring, Painting)', 'Exterior Work (Landscaping, Driveway)'
    ],
    'School' => [
        'Site Preparation', 'Foundation & Structural Steel', 'Classroom Construction',
        'Auditorium & Gym Construction', 'Electrical & Networking Infrastructure',
        'Plumbing & Fire Safety Systems', 'HVAC & Ventilation', 
        'Interior & Classroom Furnishing', 'Playground & Sports Facilities'
    ],
    'Commercial Building' => [
        'Foundation & Excavation', 'Steel Erection', 'Curtain Wall & Glazing',
        'Commercial HVAC', 'High-Voltage Electrical', 'Fire Suppression Systems',
        'Data Center & Comms Rooms', 'Office Fit-Out', 'Parking Structure'
    ],
    'Other' => [
        'General Task', 'Planning', 'Execution', 'Review', 'Completion'
    ]
];

// --- FORM SUBMISSION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireCSRF();
    // Sanitize and retrieve POST data
    $project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
    $assignee_id = filter_input(INPUT_POST, 'assignee_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $due_date = $_POST['due_date'] ?? '';
    $status = $_POST['status'] ?? '';
    $priority = $_POST['priority'] ?? '';
    
    // (NEW) Retrieve new fields
    $category = trim($_POST['category'] ?? '');
    $materials_needed = trim($_POST['materials_needed'] ?? '');
    $cost_estimate = filter_input(INPUT_POST, 'cost_estimate', FILTER_VALIDATE_FLOAT);
    $is_critical = isset($_POST['is_critical']) ? 1 : 0;
    
    // Set NULL for optional fields if empty
    $assignee_id = !empty($assignee_id) ? $assignee_id : NULL;
    $cost_estimate = !empty($cost_estimate) ? $cost_estimate : NULL;
    
    // Validation
    $errors = [];
    if (empty($project_id)) $errors[] = "Project is required.";
    if (empty($title)) $errors[] = "Task title is required.";
    // ... other validations ...

    if (empty($errors)) {
        try {
            // (MODIFIED) Updated SQL Query
            $sql = "INSERT INTO tasks (project_id, assignee_id, title, category, description, materials_needed, cost_estimate, start_date, due_date, status, priority, is_critical)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            // (MODIFIED) Updated bind_param
            $stmt->bind_param(
                "iissssdsissi",
                $project_id,
                $assignee_id,
                $title,
                $category,
                $description,
                $materials_needed,
                $cost_estimate,
                $start_date,
                $due_date,
                $status,
                $priority,
                $is_critical
            );

            if ($stmt->execute()) {
                $_SESSION['success'] = "Task '{$title}' was added successfully!";
                header("Location: tasks.php");
                exit;
            } else {
                 throw new Exception("Execute failed: " . $stmt->error);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to add task: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Task - Construct.</title>
    <style>
        /* Your existing CSS here */
        :root {
            --primary-color: #f59e0b; --primary-hover-color: #d97706; --dark-bg: #1f2937;
            --light-bg: #f9fafb; --white-bg: #ffffff; --text-dark: #111827; --text-medium: #4b5563;
            --text-light: #d1d5db; --border-color: #e5e7eb; --danger-color: #dc2626;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: sans-serif; background-color: var(--light-bg); color: var(--text-medium); display: flex; }
        .sidebar { width: 260px; background-color: var(--dark-bg); color: var(--text-light); height: 100vh; padding: 1.5rem; display: flex; flex-direction: column; position: fixed; flex-shrink: 0; }
        .sidebar .logo { font-size: 1.8rem; font-weight: 800; color: var(--white-bg); margin-bottom: 2rem; text-align: center; }
        .sidebar .logo span { color: var(--primary-color); }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; color: var(--text-light); text-decoration: none; padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 0.5rem; transition: all 0.2s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background-color: var(--primary-color); color: var(--white-bg); }
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header h1 { font-size: 2rem; color: var(--text-dark); }
        .user-info a { color: var(--primary-color); text-decoration: none; font-weight: 600; }
        .form-wrapper { background-color: var(--white-bg); border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); padding: 2.5rem; max-width: 900px; margin: 0 auto; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { font-weight: 600; margin-bottom: 0.5rem; color: var(--text-dark); font-size: 0.9rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); font-size: 1rem; transition: all 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.3); }
        .form-group select[disabled] { background-color: #f3f4f6; cursor: not-allowed; }
        .form-group-checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
        textarea { resize: vertical; min-height: 100px; }
        .form-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover-color); }
        .btn-secondary { background-color: var(--border-color); color: var(--text-dark); }
        .btn-secondary:hover { background-color: #d1d5db; }
    </style>
</head>
<body>
    <aside class="sidebar">
       <h2 class="logo">Construct<span>.</span></h2>
        </aside>

    <div class="main-content">
        <header class="header">
            <h1>Add New Task</h1>
            </header>

        <div class="form-wrapper">
            <?php if (isset($_SESSION['error'])): ?>
                <?php endif; ?>

            <form method="POST" class="form-grid">
                <?= csrfField() ?>
                <div class="form-group full-width">
                    <label for="title">Task Title</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="project_id">Project</label>
                    <select id="project_id" name="project_id" required>
                        <option value="">Select Project</option>
                        <?php
                        // (MODIFIED) Fetch project_type along with id and name
                        $result = $conn->query("SELECT id, name, project_type FROM projects WHERE deleted_at IS NULL ORDER BY name");
                        while ($row = $result->fetch_assoc()) {
                            // (MODIFIED) Add a data-type attribute to store the project type
                            echo "<option value=\"{$row['id']}\" data-type=\"" . htmlspecialchars($row['project_type']) . "\">" . htmlspecialchars($row['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="category">Task Category</label>
                    <select id="category" name="category" required disabled>
                        <option value="">First select a project</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assignee_id">Assign To (Optional)</label>
                     <select id="assignee_id" name="assignee_id">
                        <option value="">Select Worker</option>
                        <?php
                        // Your existing worker query
                        $workerResult = $conn->query("SELECT u.id, u.name FROM users u WHERE u.role = 'worker' AND u.status = 'approved' ORDER BY u.name ASC");
                        while ($worker = $workerResult->fetch_assoc()) {
                           echo "<option value=\"{$worker['id']}\">" . htmlspecialchars($worker['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority" required>
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" required>
                </div>

                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date" required>
                </div>
                
                 <div class="form-group full-width">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"></textarea>
                </div>
                
                <div class="form-group full-width">
                    <label for="materials_needed">Materials Needed (Optional)</label>
                    <textarea id="materials_needed" name="materials_needed" placeholder="e.g., 5 bags of cement, 10ft of 2x4 lumber..."></textarea>
                </div>

                <div class="form-group">
                    <label for="cost_estimate">Estimated Cost (₹) (Optional)</label>
                    <input type="number" id="cost_estimate" name="cost_estimate" step="0.01" placeholder="e.g., 15000.50">
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="todo">To Do</option>
                        <option value="in-progress">In Progress</option>
                        <option value="review">Review</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <div class="form-group form-group-checkbox full-width">
                    <input type="checkbox" id="is_critical" name="is_critical" value="1">
                    <label for="is_critical">This is a critical/blocking task</label>
                </div>

                <div class="form-actions">
                    <a href="tasks.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Add Task</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 1. Define the categories in JavaScript, passed from PHP.
        const categoriesByProjectType = <?= json_encode($task_categories); ?>;

        // 2. Get references to the dropdowns.
        const projectSelect = document.getElementById('project_id');
        const categorySelect = document.getElementById('category');

        // 3. Listen for changes on the project dropdown.
        projectSelect.addEventListener('change', function() {
            // Get the selected project's type from its data-type attribute.
            const selectedOption = this.options[this.selectedIndex];
            const projectType = selectedOption.dataset.type;

            // Clear existing category options.
            categorySelect.innerHTML = '';

            if (projectType && categoriesByProjectType[projectType]) {
                // If a valid project is selected, enable the category dropdown.
                categorySelect.disabled = false;
                
                // Add a default placeholder option.
                let defaultOption = new Option('Select a Category', '');
                categorySelect.add(defaultOption);

                // Populate with new categories.
                categoriesByProjectType[projectType].forEach(function(category) {
                    let option = new Option(category, category);
                    categorySelect.add(option);
                });
            } else {
                // If no project is selected, disable it.
                let disabledOption = new Option('First select a project', '');
                categorySelect.add(disabledOption);
                categorySelect.disabled = true;
            }
        });
    </script>
</body>
</html>