<?php
require_once '../conn.php';

// --- SECURITY & DATABASE ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../conn.php';

// --- DATA FETCHING ---
$user_id = $_SESSION['user_id'];
$tasks = [];

try {
    // Fetch all non-deleted tasks created by the logged-in client
    $stmt = $conn->prepare("
        SELECT t.id, t.title, t.status, t.due_date, p.name AS project_name
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE t.client_id = ? AND t.deleted_at IS NULL
        ORDER BY t.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Client Tasks Fetch Error: " . $e->getMessage());
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - Construct.</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="client_style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar_client.php'; ?>

        <div class="main-content-wrapper">
            <header class="header">
                 <button class="menu-toggle" id="menu-toggle" aria-label="Open sidebar">
                    <i data-lucide="menu"></i>
                </button>
                <h1>My Task Requests</h1>
                <a href="logoutclient.php" class="logout-btn">Logout</a>
            </header>
            
            <main class="main-content">
                <div class="table-wrapper">
                    <table class="projects-table">
                        <thead>
                            <tr>
                                <th>Task Title</th>
                                <th>Project</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($tasks)): ?>
                                <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?= htmlspecialchars($task['title']) ?></td>
                                    <td><?= htmlspecialchars($task['project_name'] ?? 'N/A') ?></td>
                                    <td><?= date('M d, Y', strtotime($task['due_date'])) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower(htmlspecialchars($task['status'])) ?>">
                                            <?= ucfirst(htmlspecialchars($task['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 2rem;">You have not created any tasks yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                 <div class="action-buttons">
                    <a href="select.php" class="btn"><i data-lucide="plus-circle"></i>Add New Task</a>
                </div>
            </main>
        </div>
    </div>

    <!-- THIS IS THE NEW BOTTOM NAVIGATION BAR -->
    <nav class="bottom-nav">
        <a href="client_dashboard.php" class="<?= ($currentPage == 'client_dashboard.php') ? 'active' : '' ?>"><i data-lucide="layout-dashboard"></i><span>Dashboard</span></a>
        <a href="my_projects.php" class="<?= ($currentPage == 'my_projects.php') ? 'active' : '' ?>"><i data-lucide="briefcase"></i><span>Projects</span></a>
        <a href="my_tasks.php" class="<?= ($currentPage == 'my_tasks.php') ? 'active' : '' ?>"><i data-lucide="list-checks"></i><span>Tasks</span></a>
        <a href="messages.php" class="<?= ($currentPage == 'messages.php') ? 'active' : '' ?>"><i data-lucide="message-square"></i><span>Messages</span></a>
        <a href="profile.php" class="<?= ($currentPage == 'profile.php') ? 'active' : '' ?>"><i data-lucide="user"></i><span>Profile</span></a>
    </nav>
    
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
