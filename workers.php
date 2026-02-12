<?php
// Load configuration first (before session_start)
require_once 'config.php';

require_once 'conn.php';
require_once 'includes/csrf.php';

// Admin check
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: dashboard.php");
    exit();
}

// Function to sync workers between users and workers tables
function syncWorkerTables($conn) {
    $conn->begin_transaction();
    try {
        // Find approved workers missing from workers table
        $missing = $conn->query("
            SELECT u.id, u.name
            FROM users u
            LEFT JOIN workers w ON u.id = w.user_id
            WHERE u.role = 'worker' AND u.status = 'approved' AND w.id IS NULL
        ")->fetch_all(MYSQLI_ASSOC);

        foreach ($missing as $user) {
            $stmt = $conn->prepare("
                INSERT INTO workers (user_id, name, role, hourly_rate, specialization, availability_status, status)
                VALUES (?, ?, 'worker', 0.00, '', 'available', 'approved')
            ");
            $stmt->bind_param("is", $user['id'], $user['name']);
            $stmt->execute();
            $stmt->close();
        }

        // Update existing worker records with current user data
        $conn->query("
            UPDATE workers w
            JOIN users u ON w.user_id = u.id
            SET 
                w.name = u.name,
                w.status = u.status
            WHERE u.role = 'worker'
        ");

        $conn->commit();
        return count($missing);
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Sync failed: " . $e->getMessage());
        return false;
    }
}

// Sync tables at the start
syncWorkerTables($conn);



// Delete and notify
if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    requireCSRF();
    $id = intval($_POST['delete_id']);
    $emailSent = false;

    try {
        $conn->begin_transaction();

        // Get worker details
        $stmt = $conn->prepare("
            SELECT w.*, u.email, u.name 
            FROM workers w
            JOIN users u ON w.user_id = u.id
            WHERE w.id = ?
        ");
        if (!$stmt) {
            throw new Exception("Prepare SELECT failed: " . $conn->error);
        }

        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Execute SELECT failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $worker = $result->fetch_assoc();
        $stmt->close();

        if (!$worker) {
            throw new Exception("Worker not found");
        }

// Mark user as rejected
$rejectStmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
$rejectStmt->bind_param("i", $worker['user_id']);
$rejectStmt->execute();
$rejectStmt->close();

// Also mark worker as rejected instead of removing (optional)
$updateWorkerStmt = $conn->prepare("UPDATE workers SET status = 'rejected' WHERE id = ?");
$updateWorkerStmt->bind_param("i", $id);
$updateWorkerStmt->execute();
$updateWorkerStmt->close();
        // Delete from workers table
 $deleteStmt = $conn->prepare("DELETE FROM workers WHERE id = ?");
$deleteStmt->bind_param("i", $id);
$deleteStmt->execute();
$deleteStmt->close();

        // Send email notification
        if (!empty($worker['email'])) {
            $emailFile = __DIR__ . '/email_otp_verification/send_delete_email.php';
            if (file_exists($emailFile)) {
                require_once $emailFile;
                $emailSent = sendDeleteNotification($worker['email'], $worker['name']);
            } else {
                error_log("Email sending file not found: $emailFile");
            }
        }

        // Log admin action
        $admin_id = $_SESSION['user_id'] ?? 0;
        $log = sprintf("Rejected worker #%d (%s)%s", 
            $id, 
            $worker['name'],
            !empty($worker['email']) ? ($emailSent ? " + email sent" : " + email failed") : ""
        );

        $logStmt = $conn->prepare("INSERT INTO admin_actions (admin_id, action) VALUES (?, ?)");
        $logStmt->bind_param("is", $admin_id, $log);
        $logStmt->execute();
        $logStmt->close();

        $conn->commit();

        $params = ['deleted' => 1];
        if (!empty($worker['email'])) {
            $params['emailsent'] = $emailSent ? 1 : 0;
        }
        header("Location: workers.php?" . http_build_query($params));
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Delete operation failed: " . $e->getMessage());
        $_SESSION['error'] = "Failed to delete worker: " . $e->getMessage();
        header("Location: workers.php");
        exit();
    }
}

// Fetch workers
$workers = [];
try {
    // UPDATED QUERY: Removed specialization from the SELECT statement
    $query = "
        SELECT 
            w.id,
            u.id as user_id,
            u.name,
            u.role,
            u.email, 
            u.phone,
            w.hourly_rate,
            w.status,
            u.login_time
        FROM workers w
        JOIN users u ON w.user_id = u.id
        WHERE u.role = 'worker' 
          AND w.status = 'approved'
        ORDER BY u.name ASC
    ";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $workers[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Fetch error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to fetch worker list.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Management - Construct.</title>
    <link href="admin_style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar_admin.php'; ?>

        <div class="main-content">
            <header class="header">
                <h1>Worker Management</h1>
                <div class="user-info">
                    Welcome, <strong><?= htmlspecialchars($_SESSION['name']); ?></strong> | <a href="logout.php">Logout</a>
                </div>
            </header>

            <div class="content-wrapper">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">
                        Worker deleted successfully.
                        <?php if (isset($_GET['emailsent']) && $_GET['emailsent'] == '1'): ?>
                            <div>Email notification sent to worker.</div>
                        <?php elseif (isset($_GET['emailsent']) && $_GET['emailsent'] == '0'): ?>
                            <div style="color: #92400e;">Failed to send email notification.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="table-container">
                    <?php if (!empty($workers)): ?>  
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Activity Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($workers as $worker): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($worker['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($worker['role']) ?></td>
                                        <td><?= htmlspecialchars($worker['email'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($worker['phone'] ?? '-') ?></td>
                                        <td>
                                            <span class="status-badge" style="background-color: #dcfce7; color: #166534;"><?= htmlspecialchars($worker['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($worker['login_time'])): ?>
                                                <span class="status-badge status-active" style="background-color: #dcfce7; color: #15803d; display: inline-flex; align-items: center; gap: 0.5rem;"><span style="width: 8px; height: 8px; background-color: #22c55e; border-radius: 50%;"></span>Active</span>
                                            <?php else: ?>
                                                <span class="status-badge status-inactive" style="background-color: #f3f4f6; color: #4b5563; display: inline-flex; align-items: center; gap: 0.5rem;"><span style="width: 8px; height: 8px; background-color: #9ca3af; border-radius: 50%;"></span>Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <a href="view_attendance.php?worker_id=<?= $worker['user_id'] ?>" 
                                                   class="btn btn-primary" 
                                                   style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">Attendance</a>
                                                <form method="POST" onsubmit="return confirm('ഇവൻ ഇച്ചിരി വിഷയം ആയിരുന്നു')" style="display:inline;">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="delete_id" value="<?= $worker['id'] ?>">
                                                    <button type="submit" class="btn btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 2rem; text-align: center; color: var(--text-medium);">
                            <p>No approved workers found in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>