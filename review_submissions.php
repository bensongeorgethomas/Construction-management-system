<?php
require_once 'conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$admin_id = $_SESSION['user_id'];

// --- HANDLE APPROVE/REJECT ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/csrf.php';
    requireCSRF();
    $conn->begin_transaction();
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'approve') {
            $submission_id = filter_input(INPUT_POST, 'submission_id', FILTER_VALIDATE_INT);
            $task_id = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
            $conn->query("UPDATE task_submissions SET status = 'approved' WHERE id = $submission_id");
            $conn->query("UPDATE tasks SET status = 'completed' WHERE id = $task_id");
            $_SESSION['success'] = "Submission for Task #{$task_id} has been approved.";
        } elseif (isset($_POST['submit_rejection'])) {
            $submission_id = filter_input(INPUT_POST, 'submission_id', FILTER_VALIDATE_INT);
            $task_id = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
            $rejection_reason = trim($_POST['rejection_reason']);
            $stmt_info = $conn->prepare("SELECT ts.user_id, t.title FROM task_submissions ts JOIN tasks t ON ts.task_id = t.id WHERE ts.id = ?");
            $stmt_info->bind_param("i", $submission_id);
            $stmt_info->execute();
            $info = $stmt_info->get_result()->fetch_assoc();
            $worker_id = $info['user_id'];
            $task_title = $info['title'];
            $conn->query("UPDATE task_submissions SET status = 'rejected' WHERE id = $submission_id");
            $conn->query("UPDATE tasks SET status = 'todo' WHERE id = $task_id");
            if ($worker_id && !empty($rejection_reason)) {
                $subject = "Task Rejected: '" . $task_title . "'";
                $stmt_msg = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, task_id, subject, body) VALUES (?, ?, ?, ?, ?)");
                $stmt_msg->bind_param("iiiss", $admin_id, $worker_id, $task_id, $subject, $rejection_reason);
                $stmt_msg->execute();
            }
            $_SESSION['success'] = "Submission has been rejected and feedback sent to the worker.";
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    header("Location: review_submissions.php");
    exit();
}

// --- NEW: Handle worker report actions ---
// --- NEW: Handle worker report actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['report_id'])) {
    // CSRF check is already handled by the main POST block if we merged them, 
    // but since this is a separate block, we should ensure it runs after the main one or include check here.
    // However, the main block exits? No.
    // Wait, the main block covers approvals/rejections. 
    // This block covers 'mark_read' and 'delete_report'.
    // Let's rely on the previous CSRF check if we move this inside or duplicate.
    // Better to handle independently if flow allows.
    // Since the previous block exits on its actions, we can add check here.
    
    // Actually, let's just make sure we check CSRF here too.
    if (!function_exists('validateCSRFToken')) require_once 'includes/csrf.php';
    requireCSRF();

    $report_id = intval($_POST['report_id']);
    $action = $_POST['action'];

    if ($action === 'mark_read') {
        $stmt = $conn->prepare("UPDATE worker_reports SET status = 'reviewed' WHERE id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
    } elseif ($action === 'delete_report') {
        $stmt = $conn->prepare("SELECT file_path FROM worker_reports WHERE id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (!empty($row['file_path']) && file_exists($row['file_path'])) {
                unlink($row['file_path']);
            }
        }
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM worker_reports WHERE id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
    }
    header("Location: review_submissions.php");
    exit();
}

// Fetch new worker reports
$worker_reports = [];
$sql_reports = "SELECT wr.*, u.name as worker_name, c.name as client_name, p.name as project_name FROM worker_reports wr JOIN users u ON wr.worker_id = u.id JOIN users c ON wr.client_id = c.id JOIN projects p ON wr.project_id = p.id WHERE wr.status = 'pending' ORDER BY wr.created_at ASC";
$result_reports = $conn->query($sql_reports);
if ($result_reports) $worker_reports = $result_reports->fetch_all(MYSQLI_ASSOC);

// Fetch pending task submissions
$submissions = [];
$sql = "SELECT s.id, s.task_id, s.file_path, s.notes, s.submitted_at, t.title as task_title, p.name as project_name, u.name as worker_name FROM task_submissions s JOIN tasks t ON s.task_id = t.id JOIN users u ON s.user_id = u.id JOIN projects p ON t.project_id = p.id WHERE s.status = 'pending' ORDER BY s.submitted_at ASC";
$result = $conn->query($sql);
if ($result) {
    $submissions = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Submissions - Construct.</title>
    <link href="admin_style.css" rel="stylesheet">
    <style>
        .submissions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; margin-bottom: 3rem; }
        .submission-card { background-color: var(--bg-card); border-radius: var(--radius); box-shadow: var(--shadow-md); overflow: hidden; display: flex; flex-direction: column; border: 1px solid var(--border); transition: var(--transition); }
        .submission-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
        .media-container { width: 100%; background-color: #f3f4f6; text-align: center; border-bottom: 1px solid var(--border); }
        .media-container img, .media-container video { max-width: 100%; height: 250px; object-fit: cover; }
        .card-content { padding: 1.5rem; flex-grow: 1; }
        .card-content h2 { font-size: 1.25rem; margin-bottom: 0.5rem; color: var(--text-main); font-weight: 700; }
        .card-content .meta { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem; }
        .card-content .meta span { font-weight: 600; color: var(--text-main); }
        .notes { background-color: #f9fafb; border-left: 4px solid var(--primary); padding: 1rem; margin-top: 1rem; border-radius: 4px; font-style: italic; color: var(--text-main); }
        .card-actions { padding: 1rem 1.5rem; background-color: #f9fafb; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 0.5rem; }
        
        .no-items, .no-submissions { text-align: center; padding: 3rem; background: var(--bg-card); border-radius: var(--radius); border: 1px solid var(--border); color: var(--text-muted); }
        .urgency-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-weight: 600; color: white; display: inline-block; font-size: 0.8rem; text-transform: capitalize; }
        
        /* Custom urgency colors if not in admin_style */
        .urgency-badge[style*="high"] { background-color: var(--danger) !important; }
        .urgency-badge[style*="medium"] { background-color: var(--warning) !important; }
        .urgency-badge[style*="low"] { background-color: var(--info) !important; }

        /* Modal Overrides */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; }
        .modal.show { display: flex; }
        .modal-content { background: var(--bg-card); padding: 2rem; border-radius:var(--radius); width: 90%; max-width: 500px; border: 1px solid var(--border); box-shadow: var(--shadow-lg); animation: slideDown 0.3s ease-out; }
        .modal-content textarea { margin-bottom: 0; }
        .modal-actions { margin-top: 1.5rem; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar_admin.php'; ?>

        <div class="main-content">
            <header class="header">
                <h1>Review Center</h1>
                <div class="user-info">
                    Welcome, <strong><?= htmlspecialchars($_SESSION['name']); ?></strong> | <a href="logout.php">Logout</a>
                </div>
            </header>

            <div class="content-wrapper">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <div class="section-header">
                    <h2>New Worker Reports</h2>
                </div>
                
                <?php if (!empty($worker_reports)): ?>
                    <div class="submissions-grid">
                        <?php foreach ($worker_reports as $report): ?>
                            <div class="submission-card">
                                <div class="card-content">
                                    <h2><?= htmlspecialchars($report['report_type']) ?></h2>
                                    <p class="meta">
                                        Reported by: <span><?= htmlspecialchars($report['worker_name']) ?></span><br>
                                        Project: <span><?= htmlspecialchars($report['project_name']) ?></span><br>
                                        Urgency: <span class="urgency-badge" style="background-color: var(--urgency-<?= strtolower($report['urgency']) ?>);"><?= htmlspecialchars($report['urgency']) ?></span>
                                    </p>
                                    <div class="notes"><p>"<?= nl2br(htmlspecialchars($report['description'])) ?>"</p></div>
                                    <?php if($report['file_path']): ?>
                                        <p class="meta" style="margin-top:1rem;">Attachment: <a href="<?= htmlspecialchars($report['file_path']) ?>" target="_blank" style="color: var(--primary); text-decoration: underline;">View File</a></p>
                                    <?php endif; ?>
                                </div>
                                <div class="card-actions">
                                    <a href="contact_supplier.php?report_id=<?= $report['id'] ?>" class="btn btn-info">Contact Supplier</a>
                                    
                                    <form method="POST" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                        <input type="hidden" name="action" value="mark_read">
                                        <button type="submit" class="btn btn-success">Mark as Read</button>
                                    </form>

                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                        <input type="hidden" name="action" value="delete_report">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-items"><p>No new worker reports to review.</p></div>
                <?php endif; ?>

                <div class="section-header" style="margin-top: 3rem;">
                    <h2>Task Submissions for Approval</h2>
                </div>
                <?php if (!empty($submissions)): ?>
                    <div class="submissions-grid">
                        <?php foreach ($submissions as $sub): ?>
                            <div class="submission-card">
                                <div class="media-container">
                                     <a href="<?= htmlspecialchars($sub['file_path']) ?>" target="_blank">
                                        <?php
                                            $file_ext = strtolower(pathinfo($sub['file_path'], PATHINFO_EXTENSION));
                                            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                                echo '<img src="'.htmlspecialchars($sub['file_path']).'" alt="Submission Proof">';
                                            } else {
                                                echo '<div style="padding: 2rem; color: var(--text-muted);">View Attachment</div>';
                                            }
                                        ?>
                                    </a>
                                </div>
                                <div class="card-content">
                                    <h2><?= htmlspecialchars($sub['task_title']) ?></h2>
                                    <p class="meta">
                                        Submitted by: <span><?= htmlspecialchars($sub['worker_name']) ?></span><br>
                                        Project: <span><?= htmlspecialchars($sub['project_name']) ?></span>
                                    </p>
                                    <?php if (!empty($sub['notes'])): ?>
                                        <div class="notes"><p>"<?= nl2br(htmlspecialchars($sub['notes'])) ?>"</p></div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-actions">
                                    <form method="POST" action="review_submissions.php" style="display: inline;" onsubmit="return confirm('Approve this submission?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                                        <input type="hidden" name="task_id" value="<?= $sub['task_id'] ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-danger" onclick="openRejectModal(<?= $sub['id'] ?>, <?= $sub['task_id'] ?>)">Reject</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-submissions">
                        <p>There are no pending submissions to review at this time.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="rejectionModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-top:0;">Reason for Rejection</h2>
            <form id="rejectionForm" method="POST" action="review_submissions.php">
                <?= csrfField() ?>
                <input type="hidden" name="submission_id" id="modal_submission_id">
                <input type="hidden" name="task_id" id="modal_task_id">
                <div class="form-group">
                    <textarea name="rejection_reason" class="form-control" placeholder="Provide clear feedback..." required rows="4"></textarea>
                </div>
                <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 1rem;">
                    <button type="button" class="btn btn-info" onclick="closeRejectModal()" style="background-color: var(--text-light);">Cancel</button>
                    <button type="submit" name="submit_rejection" class="btn btn-danger">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('rejectionModal');
        const submissionIdInput = document.getElementById('modal_submission_id');
        const taskIdInput = document.getElementById('modal_task_id');
        function openRejectModal(submissionId, taskId) {
            submissionIdInput.value = submissionId;
            taskIdInput.value = taskId;
            modal.classList.add('show');
        }
        function closeRejectModal() {
            modal.classList.remove('show');
        }
        window.onclick = function(event) {
            if (event.target == modal) {
                closeRejectModal();
            }
        }
    </script>
</body>
</html>