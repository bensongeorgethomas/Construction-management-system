<?php
require_once '../conn.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../conn.php'; // Assumes conn.php is your connection file

// --- DATA FETCHING ---
$user_id = $_SESSION['user_id'];
$projects = [];

try {
    // Fetch all non-deleted projects for the logged-in client
    $stmt = $conn->prepare("
        SELECT id, name, status, description, start_date, end_date, completion_percentage
        FROM projects
        WHERE client_id = ? AND deleted_at IS NULL
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Client Projects Fetch Error: " . $e->getMessage());
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Projects - Construct.</title>
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
                <h1>My Projects</h1>
                <a href="logoutclient.php" class="logout-btn">Logout</a>
            </header>
            
            <main class="main-content">
                <div class="table-wrapper">
                    <table class="projects-table">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Completion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($projects)): ?>
                                <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?= htmlspecialchars($project['name']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower(htmlspecialchars($project['status'])) ?>">
                                            <?= ucfirst(htmlspecialchars($project['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($project['start_date'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($project['end_date'])) ?></td>
                                    <td><?= htmlspecialchars($project['completion_percentage']) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 2rem;">You do not have any projects yet.</td>
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
