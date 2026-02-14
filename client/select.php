<?php
require_once '../conn.php';

// Check if connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$logged_in_client_id = $_SESSION['user_id'];
$available_projects = [];
$error_message = '';
$success_message = '';

// --- FETCH PROJECTS ---
$projects_stmt = $conn->prepare("SELECT id, name FROM projects WHERE client_id = ? AND deleted_at IS NULL ORDER BY name");
$projects_stmt->bind_param("i", $logged_in_client_id);
if ($projects_stmt->execute()) {
    $result = $projects_stmt->get_result();
    $available_projects = $result->fetch_all(MYSQLI_ASSOC);
}
$projects_stmt->close();

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $task_category = $_POST['task_category'];
    $start_date = $_POST['start_date'];
    $due_date = $_POST['due_date'];
    $priority = $_POST['priority'];
    $estimated_hours = $_POST['estimated_hours'];
    $project_id = $_POST['project_id'] ?? null;

    if (!empty($title) && !empty($start_date) && !empty($due_date) && !empty($priority) && !empty($project_id)) {
        
        // Prepare description
        $full_description = "Category: $task_category\n";
        if (!empty($estimated_hours)) {
            $full_description .= "Estimated Hours: $estimated_hours\n";
        }
        if (!empty($description)) {
            $full_description .= "\nDescription: $description";
        }

        // Insert Task
        $status = 'pending';
        $insert_stmt = $conn->prepare("INSERT INTO tasks (title, description, start_date, due_date, priority, status, project_id, client_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("ssssssii", $title, $full_description, $start_date, $due_date, $priority, $status, $project_id, $logged_in_client_id);

        if ($insert_stmt->execute()) {
            $success_message = "Your task request has been submitted successfully! An admin will review it shortly.";
        } else {
            $error_message = "Database error: " . $conn->error;
        }
        $insert_stmt->close();

    } else {
        $error_message = "Please fill all required fields, including assigning the task to a project.";
    }
}

// Task categories (unchanged)
$task_categories = [
    'construction' => ['title' => 'Construction Work', 'icon' => 'hammer'],
    'electrical' => ['title' => 'Electrical Tasks', 'icon' => 'zap'],
    'plumbing' => ['title' => 'Plumbing Tasks', 'icon' => 'droplets'],
    'painting' => ['title' => 'Painting & Finishing', 'icon' => 'paintbrush'],
    'inspection' => ['title' => 'Inspection & Quality Control', 'icon' => 'search'],
    'cleaning' => ['title' => 'Cleaning & Maintenance', 'icon' => 'broom']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Task - Construct Pro</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Keeping original styles as requested */
        :root {
            --primary: #005A9C;
            --light-gray: #f4f7f9;
            --medium-gray: #e1e8ed;
            --dark-gray: #657786;
            --text-color: #14171a;
            --border-radius: 12px;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-gray); color: var(--text-color); }
        .container { max-width: 1100px; margin: 0 auto; padding: 2rem; }
        .header { text-align: center; margin-bottom: 3rem; }
        .header h1 { font-size: 2.5rem; color: var(--primary); margin-bottom: 0.5rem; }
        .header p { color: var(--dark-gray); }
        .back-btn { display: inline-flex; align-items: center; gap: 0.5rem; color: var(--dark-gray); text-decoration: none; margin-bottom: 2rem; font-weight: 500; }
        .back-btn:hover { color: var(--primary); }
        .task-categories-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 3rem; }
        .task-category-card { background: #fff; border: 1px solid var(--medium-gray); border-radius: var(--border-radius); padding: 1.5rem; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .task-category-card:hover, .task-category-card.selected { transform: translateY(-5px); box-shadow: var(--shadow); border-color: var(--primary); }
        .task-category-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .task-category-icon { background: var(--primary); color: white; border-radius: 8px; width: 40px; height: 40px; display: grid; place-items: center; }
        .task-category-title { font-size: 1.1rem; font-weight: 600; }
        .form-container { background: #fff; border: 1px solid var(--medium-gray); border-radius: var(--border-radius); padding: 2rem; }
        .form-title { font-size: 1.8rem; text-align: center; margin-bottom: 2rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--medium-gray); border-radius: 8px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0, 90, 156, 0.1); }
        .submit-btn { background-color: var(--primary); color: white; padding: 1rem 2rem; border: none; border-radius: 50px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background-color 0.2s ease; display: block; margin: 2rem auto 0; }
        .submit-btn:hover { background-color: #004170; }
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid; }
        .message.success { background-color: #eaf7f0; border-color: #a3d9b8; color: #1e663c; }
        .message.error { background-color: #fceeee; border-color: #f6c0c0; color: #a53232; }
        .notice-box { background-color: #fff; border: 1px solid var(--medium-gray); border-left: 5px solid var(--primary); border-radius: var(--border-radius); padding: 2rem; text-align: center; box-shadow: var(--shadow); }
        .notice-box h2 { font-size: 1.5rem; color: var(--primary); margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.75rem; }
        .notice-box p { color: var(--dark-gray); line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <a href="client_dashboard.php" class="back-btn"><i data-lucide="arrow-left" width="18"></i> Back to Dashboard</a>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><?= htmlspecialchars($success_message) ?></div>
        <?php elseif (!empty($error_message)): ?>
            <div class="message error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <?php if (!empty($available_projects)): ?>
        
            <div class="header">
                <h1>Create New Task</h1>
                <p>Select a category and fill out the task details to get started.</p>
            </div>

            <div class="task-categories-grid">
                <?php foreach ($task_categories as $key => $category): ?>
                <div class="task-category-card" onclick="selectTaskCategory('<?= $key ?>', this)">
                    <div class="task-category-header">
                        <div class="task-category-icon"> <i data-lucide="<?= $category['icon'] ?>" width="20" height="20"></i> </div>
                        <h3 class="task-category-title"><?= $category['title'] ?></h3>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="form-container">
                <h2 class="form-title">Task Details</h2>
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label" for="title">Task Title *</label>
                            <input type="text" id="title" name="title" class="form-input" required>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label" for="project_id">Assign to Project *</label>
                            <select id="project_id" name="project_id" class="form-select" required>
                                <option value="">Select a Project</option>
                                <?php foreach ($available_projects as $project): ?>
                                <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="task_category">Task Category *</label>
                            <select name="task_category" id="task_category" class="form-select" required>
                                <option value="">Select a category</option>
                                <?php foreach ($task_categories as $category): ?>
                                <option value="<?= $category['title'] ?>"><?= $category['title'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="priority">Priority *</label>
                            <select id="priority" name="priority" class="form-select" required>
                                <option value="">Select priority</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="start_date">Start Date *</label>
                            <input type="date" id="start_date" name="start_date" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="due_date">Due Date *</label>
                            <input type="date" id="due_date" name="due_date" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="estimated_hours">Estimated Hours</label>
                            <input type="number" id="estimated_hours" name="estimated_hours" class="form-input" step="0.5" min="0">
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label" for="description">Task Description</label>
                            <textarea id="description" name="description" class="form-textarea" placeholder="Describe the task requirements and details..."></textarea>
                        </div>
                    </div>
                    <button type="submit" name="create_task" class="submit-btn">Create Task</button>
                </form>
            </div>

        <?php else: ?>

            <div class="notice-box">
                <h2><i data-lucide="alert-triangle"></i> Project Required</h2>
                <p>You must have at least one active project before you can create a new task.</p>
                <p>Please contact an administrator to have a new project set up for you.</p>
            </div>

        <?php endif; ?>
    </div>

    <script>
        function selectTaskCategory(categoryKey, card) {
            document.querySelectorAll('.task-category-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            let taskCategorySelect = document.getElementById('task_category');
            let selectedOption = Array.from(taskCategorySelect.options).find(opt => opt.value === card.querySelector('.task-category-title').innerText);
            if (selectedOption) {
                taskCategorySelect.value = selectedOption.value;
            }
            document.querySelector('.form-container').scrollIntoView({ behavior: 'smooth' });
        }

        lucide.createIcons();

        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('start_date').setAttribute('min', today);
            document.getElementById('due_date').setAttribute('min', today);
            
            document.getElementById('start_date').addEventListener('change', function() {
                document.getElementById('due_date').setAttribute('min', this.value);
            });
        });
    </script>
</body>
</html>
