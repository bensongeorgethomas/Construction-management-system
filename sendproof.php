<?php
require_once 'conn.php';

// Security: Check if a worker is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];
$error_message = '';
$task = null;

// --- HANDLE THE FORM SUBMISSION (POST REQUEST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/csrf.php';
    requireCSRF();
    $task_id = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
    $notes = trim($_POST['notes'] ?? '');
    $file = $_FILES['proof_file'];

    // --- 1. File Upload Handling (with validation) ---
    require_once __DIR__ . '/includes/file_upload.php';
    
    $validation = validateFileUpload($file, 10485760); // 10MB limit
    if (!$validation['success']) {
        $_SESSION['error'] = "Upload failed: " . $validation['message'];
        header("Location: worker_tasks.php");
        exit();
    }

    $upload_dir = __DIR__ . '/uploads/proof/';
    $result = saveUploadedFile($file['tmp_name'], $upload_dir, $validation['sanitized_name']);
    
    if (!$result['success']) {
        $_SESSION['error'] = $result['message'];
        header("Location: worker_tasks.php");
        exit();
    }
    
    $file_path = 'uploads/proof/' . $result['filename'];

    // --- 2. Database Updates (Transaction) ---
    $conn->begin_transaction();
    try {
        // a) Insert the submission record
        $stmt_sub = $conn->prepare("INSERT INTO task_submissions (task_id, user_id, file_path, notes) VALUES (?, ?, ?, ?)");
        $stmt_sub->bind_param("iiss", $task_id, $current_user_id, $file_path, $notes);
        $stmt_sub->execute();

        // b) Update the task status to 'Review'
        $stmt_task = $conn->prepare("UPDATE tasks SET status = 'Review' WHERE id = ? AND assignee_id = ?");
        $stmt_task->bind_param("ii", $task_id, $current_user_id);
        $stmt_task->execute();

        // c) Notify all admins
        $admins_result = $conn->query("SELECT id FROM users WHERE role = 'admin'");
        if ($admins_result->num_rows > 0) {
            $stmt_notify = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
            $worker_name = $_SESSION['name'] ?? 'A worker';
            $message = "{$worker_name} submitted task #{$task_id} for review.";
            $link = "admin/review_submission.php?task_id={$task_id}";

            while ($admin = $admins_result->fetch_assoc()) {
                $stmt_notify->bind_param("iss", $admin['id'], $message, $link);
                $stmt_notify->execute();
            }
        }

        // Everything succeeded, commit the changes
        $conn->commit();
        $_SESSION['success'] = "Task proof submitted successfully for review!";

    } catch (Exception $e) {
        $conn->rollback(); // Undo database changes
        if (file_exists($file_path)) {
            unlink($file_path); // Delete the uploaded file
        }
        $_SESSION['error'] = "A database error occurred. Please try again. " . $e->getMessage();
    }

    header("Location: worker_tasks.php");
    exit();
}


// --- DISPLAY THE PAGE (GET REQUEST) ---
if (isset($_GET['task_id'])) {
    $task_id = filter_input(INPUT_GET, 'task_id', FILTER_VALIDATE_INT);
    if ($task_id) {
        // Fetch task details and verify it's assigned to the current worker
        $stmt = $conn->prepare("
            SELECT t.id, t.title, p.name as project_name 
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            WHERE t.id = ? AND t.assignee_id = ? AND t.deleted_at IS NULL
        ");
        $stmt->bind_param("ii", $task_id, $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $task = $result->fetch_assoc();

        if (!$task) {
            $error_message = "Task not found or you are not assigned to this task.";
        }
    } else {
        $error_message = "Invalid Task ID provided.";
    }
} else {
    $error_message = "No Task ID specified.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Task Proof - Construct.</title>
    <style>
        :root {
            --primary-color: #f59e0b; --primary-hover-color: #d97706; --dark-bg: #1f2937;
            --light-bg: #f9fafb; --white-bg: #ffffff; --text-dark: #111827; --text-medium: #4b5563;
            --border-color: #e5e7eb; --danger-bg: #fee2e2; --danger-text: #b91c1c;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-bg); color: var(--text-medium); display: flex; }
        .sidebar { width: 260px; background-color: var(--dark-bg); color: #d1d5db; height: 100vh; padding: 1.5rem; display: flex; flex-direction: column; position: fixed; }
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2rem; }
        .form-container { max-width: 700px; margin: 2rem auto; background-color: var(--white-bg); padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .form-header h1 { font-size: 1.8rem; color: var(--text-dark); margin-bottom: 0.5rem; }
        .form-header p { font-size: 1rem; color: var(--text-medium); margin-bottom: 2rem; }
        .form-header span { font-weight: 600; color: var(--primary-color); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        .form-group textarea, .form-group input[type="file"] { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; }
        .form-group input[type="file"] { padding: 0.5rem; }
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; }
        .btn { text-decoration: none; background: var(--primary-color); color: white; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; display: inline-block; transition: background 0.3s; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: var(--primary-hover-color); }
        .btn-secondary { background-color: #d1d5db; color: var(--text-dark); }
        .btn-secondary:hover { background-color: #9ca3af; }
        .error-box { padding: 1rem; background-color: var(--danger-bg); color: var(--danger-text); border: 1px solid #fca5a5; border-radius: 8px; text-align: center; }
    </style>
</head>
<body>
    <aside class="sidebar">
        </aside>

    <div class="main-content">
        <?php if ($task): ?>
            <div class="form-container">
                <div class="form-header">
                    <h1>Submit Proof of Completion</h1>
                    <p>For Task: <span><?= htmlspecialchars($task['title']) ?></span> (Project: <?= htmlspecialchars($task['project_name']) ?>)</p>
                </div>

                <form action="sendproof.php" method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">

                    <div class="form-group">
                        <label for="proof_file">Upload Photo </label>
                        <input type="file" id="proof_file" name="proof_file" accept="image/*,video/*" required>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="5" placeholder="Add any comments for the admin..."></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="worker_tasks.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn">Submit for Review</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="form-container">
                <div class="error-box">
                    <h2>Access Denied</h2>
                    <p><?= $error_message ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>