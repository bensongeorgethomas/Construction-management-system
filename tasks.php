<?php
// Load database & session
require_once 'conn.php';
require_once 'includes/csrf.php';

// Redirect to login if user is not logged in or not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- DATABASE CONNECTION ---
// --- DATABASE CONNECTION ---
require_once 'conn.php';
require_once 'includes/csrf.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- HANDLE ADMIN ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    if (isset($_POST['accept_task'])) {
        $task_id_to_accept = intval($_POST['accept_task']);
        $stmt = $conn->prepare("UPDATE tasks SET status = 'todo' WHERE id = ?");
        $stmt->bind_param("i", $task_id_to_accept);
        $stmt->execute();
        $stmt->close();
        header("Location: tasks.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reason'])) {
    $task_id = intval($_POST['task_id']);
    $reason = trim($_POST['reason']);
    $action = $_POST['action'];

    if (!empty($reason) && $task_id > 0) {
        if ($action === 'reject') {
            $client_id = null;
            $task_title = '';
            $stmt_info = $conn->prepare("SELECT client_id, title FROM tasks WHERE id = ?");
            $stmt_info->bind_param("i", $task_id);
            if ($stmt_info->execute()) {
                $result = $stmt_info->get_result();
                if ($row = $result->fetch_assoc()) {
                    $client_id = $row['client_id'];
                    $task_title = $row['title'];
                }
            }
            $stmt_info->close();

            $stmt_reject = $conn->prepare("UPDATE tasks SET status = 'rejected', admin_notes = ? WHERE id = ?");
            $stmt_reject->bind_param("si", $reason, $task_id);
            $stmt_reject->execute();
            $stmt_reject->close();

            if ($client_id && $task_title) {
                $admin_id = $_SESSION['user_id'];
                $subject = "Update on your task request: '" . $task_title . "'";
                
                $stmt_msg = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, task_id, subject, body) VALUES (?, ?, ?, ?, ?)");
                $stmt_msg->bind_param("iiiss", $admin_id, $client_id, $task_id, $subject, $reason);
                $stmt_msg->execute();
                $stmt_msg->close();
            }

        } elseif ($action === 'delete') {
            $stmt_delete = $conn->prepare("UPDATE tasks SET deleted_at = NOW(), admin_notes = ? WHERE id = ?");
            $stmt_delete->bind_param("si", $reason, $task_id);
            $stmt_delete->execute();
            $stmt_delete->close();
        }
    }
    header("Location: tasks.php");
    exit();
}

// --- FETCH TASK DATA ---
$tasks = [];
$sql = "
    SELECT
        t.id, t.title, t.status, t.priority,
        p.name AS project_name,
        w.name AS worker_name,
        c.name AS client_name
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN users w ON t.assignee_id = w.id
    LEFT JOIN users c ON t.client_id = c.id
    WHERE t.deleted_at IS NULL
    ORDER BY FIELD(t.status, 'pending') DESC, t.id DESC
";
$result = $conn->query($sql);
if (!$result) {
    die("Query failed: " . $conn->error);
}
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}
$conn->close();

function getStatusClass($status) {
    switch ($status) {
        case 'pending': return 'status-pending';
        case 'in-progress': return 'status-in-progress';
        case 'completed': return 'status-completed';
        case 'rejected': return 'status-rejected';
        case 'todo': return 'status-todo';
        default: return 'status-default';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tasks - Construct.</title>
    <link href="admin_style.css" rel="stylesheet">
    <style>
        /* Specific Styles for Modal */
        .modal-overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background-color: rgba(0, 0, 0, 0.6); 
            display: none; justify-content: center; align-items: center; 
            z-index: 1000; 
            backdrop-filter: blur(4px);
        }
        .modal-content { 
            background-color: var(--bg-card); 
            padding: 2rem; border-radius: 16px; 
            width: 90%; max-width: 500px; 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border);
        }
        .modal-content h2 { margin-bottom: 1rem; color: var(--text-main); font-size: 1.5rem; }
        .modal-content textarea { 
            width: 100%; min-height: 120px; padding: 1rem; 
            border: 1px solid var(--border); border-radius: 12px; 
            resize: vertical; margin-bottom: 1.5rem; 
            font-family: inherit;
            background-color: var(--bg-body);
            color: var(--text-main);
        }
        .modal-content textarea:focus {
            outline: none; border-color: var(--primary); ring: 2px solid var(--primary);
        }
        .modal-actions { display: flex; justify-content: flex-end; gap: 1rem; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar_admin.php'; ?>

        <div class="main-content">
            <header class="header">
                <h1>Task Management</h1>
                <div class="user-info">
                    Welcome, <strong><?= htmlspecialchars($_SESSION['name']); ?></strong> | <a href="logout.php">Logout</a>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="page-actions">
                    <a href="add_newtask.php" class="btn btn-primary">+ Add New Task</a>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Requested By</th>
                                <th>Project</th>
                                <th>Details</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($tasks)): ?>
                                <?php foreach($tasks as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['client_name'] ?? 'Admin') ?></td>
                                        <td><?= htmlspecialchars($row['project_name'] ?? 'N/A') ?></td>
                                        <td>
                                            Assignee: <?= htmlspecialchars($row['worker_name'] ?? 'Unassigned') ?><br>
                                            <small style="color: var(--text-light);">Priority: <?= ucfirst($row['priority']) ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= getStatusClass($row['status']) ?>">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                                <?php if ($row['status'] === 'pending'): ?>
                                                    <form method="POST" action="tasks.php" style="display:inline;">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="accept_task" value="<?= $row['id'] ?>">
                                                        <button type="submit" class="btn btn-success" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">Accept</button>
                                                    </form>
                                                    <button type="button" class="btn btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;" onclick="openReasonModal('<?= $row['id'] ?>', 'reject')">Reject</button>
                                                <?php else: ?>
                                                    <a href="edit_task.php?id=<?= $row['id'] ?>" class="btn btn-info" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">Edit</a>
                                                    <button type="button" class="btn btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;" onclick="openReasonModal('<?= $row['id'] ?>', 'delete')">Delete</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No tasks found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Reason Modal -->
    <div id="reasonModal" class="modal-overlay">
        <div class="modal-content">
            <h2 id="modalTitle">Reason</h2>
            <form method="POST" action="tasks.php">
                <?= csrfField() ?>
                <input type="hidden" name="task_id" id="modalTaskId">
                <input type="hidden" name="action" id="modalAction">
                <textarea name="reason" placeholder="Enter reason..." required></textarea>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" style="background-color: var(--text-light); color: white;" onclick="closeReasonModal()">Cancel</button>
                    <button type="submit" name="submit_reason" id="modalSubmitBtn" class="btn btn-danger">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const reasonModal = document.getElementById('reasonModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalTaskId = document.getElementById('modalTaskId');
        const modalAction = document.getElementById('modalAction');
        const modalSubmitBtn = document.getElementById('modalSubmitBtn');

        function openReasonModal(taskId, action) {
            modalTaskId.value = taskId;
            modalAction.value = action;
            if (action === 'reject') {
                modalTitle.textContent = 'Reason for Rejection';
                modalSubmitBtn.textContent = 'Confirm Rejection';
                modalSubmitBtn.className = 'btn btn-danger';
            } else if (action === 'delete') {
                modalTitle.textContent = 'Reason for Deletion';
                modalSubmitBtn.textContent = 'Confirm Deletion';
                modalSubmitBtn.className = 'btn btn-danger';
            }
            reasonModal.style.display = 'flex';
        }

        function closeReasonModal() {
            reasonModal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == reasonModal) {
                closeReasonModal();
            }
        }
    </script>
</body>
</html>
