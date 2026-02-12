<?php
require_once 'conn.php';
require_once 'includes/csrf.php';

// Check if the user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // In a real app, you might redirect to a login page or show an error
    die("Access denied. You must be an admin to view this page.");
}

// Handle approval/rejection actions
if ($conn && !$conn->connect_error && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['worker_id'], $_POST['action'])) {
    requireCSRF();
    $worker_id = intval($_POST['worker_id']);
    $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';

    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'worker'");
    $stmt->bind_param("si", $action, $worker_id);
    $stmt->execute();
    $stmt->close();

    // ✅ Fetch email and send notification
    $email_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $email_stmt->bind_param("i", $worker_id);
    $email_stmt->execute();
    $email_stmt->bind_result($worker_email);
    $email_stmt->fetch();
    $email_stmt->close();

    require_once 'email_otp_verification/send_decision_email.php';
    sendDecisionEmail($worker_email, $action);

    // ✅ Now redirect
    header("Location: approve_workers.php");
    exit();
}


// Get all pending workers
$pending_workers = [];
if ($conn && !$conn->connect_error) {
    $result = $conn->query("SELECT id, name, email FROM users WHERE role = 'worker' AND status = 'pending'");
    if ($result) {
        $pending_workers = $result->fetch_all(MYSQLI_ASSOC);
    }
    // Connection is usually closed at end of script or managed by conn.php if strictly needed, 
    // but here we can leave it open or close it if we are done. 
    // Since we include sidebar which might use DB? No, sidebar is HTML.
    // $conn->close(); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Workers - Construct.</title>
    <link href="admin_style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar_admin.php'; ?>

        <div class="main-content">
            <header class="header">
                <h1>Approve Worker Requests</h1>
                <div class="user-info">
                    Welcome, <strong><?= htmlspecialchars($_SESSION['name']); ?></strong> | <a href="logout.php">Logout</a>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Worker ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_workers)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">No pending worker requests found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pending_workers as $worker): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($worker['id']) ?></td>
                                        <td><strong><?= htmlspecialchars($worker['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($worker['email']) ?></td>
                                        <td class="actions-cell">
                                            <div style="display: flex; gap: 0.5rem;">
                                                <form method="POST" action="approve_workers.php" style="margin:0;">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="worker_id" value="<?= $worker['id'] ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                                                </form>
                                                <form method="POST" action="approve_workers.php" style="margin:0;">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="worker_id" value="<?= $worker['id'] ?>">
                                                    <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                                                </form>
                                            </div>
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
</body>
</html>
