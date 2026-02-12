<?php
require_once 'conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'conn.php';

// Initialize variables
$total_projects = 0;
$total_tasks = 0;
$total_workers = 0;
$pending_workers_count = 0;
$project_progress = [];

if ($conn && !$conn->connect_error) {
    // Total projects
    $total_projects = $conn->query("SELECT COUNT(*) as count FROM projects WHERE deleted_at IS NULL")->fetch_assoc()['count'];

    // Total tasks
    $total_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE deleted_at IS NULL")->fetch_assoc()['count'];

    // Total active workers
    $total_workers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'worker' AND status = 'approved'")->fetch_assoc()['count'];
    
    // Pending worker approvals
    $pending_workers_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'worker' AND status = 'pending'")->fetch_assoc()['count'];

    // Task Status Counts for Graph
    $task_status_counts = ['pending' => 0, 'in_progress' => 0, 'completed' => 0];
    $sql_tasks = "SELECT status, COUNT(*) as count FROM tasks WHERE deleted_at IS NULL GROUP BY status";
    $result_tasks = $conn->query($sql_tasks);
    if ($result_tasks) {
        while ($row = $result_tasks->fetch_assoc()) {
            $status = strtolower(str_replace(' ', '_', $row['status'])); // Normalize status keys
            if (isset($task_status_counts[$status])) {
                $task_status_counts[$status] = $row['count'];
            }
        }
    }

    // Project progress for active projects
    $sql_projects = "SELECT name, completion_percentage FROM projects WHERE status = 'active' AND deleted_at IS NULL ORDER BY start_date DESC LIMIT 5";
    $result_projects = $conn->query($sql_projects);
    if($result_projects) {
        while ($row = $result_projects->fetch_assoc()) {
            $project_progress[] = $row;
        }
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Construct.</title>
    <link href="admin_style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar_admin.php'; ?>

        <div class="main-content">
            <header class="header">
                <h1>Admin Dashboard</h1>
                <div class="user-info">
                   Welcome, <strong><?php echo $_SESSION['name'] ?? 'Admin'; ?></strong>
                   <a href="logout.php">Logout</a>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="dashboard-grid">
                    <div class="card">
                        <h3>Total Projects</h3>
                        <p class="value"><?= $total_projects ?></p>
                    </div>
                    <div class="card">
                        <h3>Total Tasks</h3>
                        <p class="value"><?= $total_tasks ?></p>
                    </div>
                    <div class="card">
                        <h3>Active Workers</h3>
                        <p class="value"><?= $total_workers ?></p>
                    </div>
                    <div class="card highlight">
                        <a href="approve_workers.php" style="text-decoration: none; color: inherit;">
                            <h3>Pending Approvals</h3>
                            <p class="value"><?= $pending_workers_count ?></p>
                        </a>
                    </div>
                </div>

                <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">
                    <div class="card">
                        <h3>Project Progress</h3>
                        <div style="height: 300px;">
                            <canvas id="projectChart"></canvas>
                        </div>
                    </div>
                    <div class="card">
                        <h3>Task Status</h3>
                        <div style="height: 300px; display: flex; justify-content: center;">
                            <canvas id="taskChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="data-section" style="margin-top: 2rem;">
                    <div class="data-list">
                        <div class="section-header">
                             <h2>Recent Project Details</h2>
                        </div>
                        <ul>
                            <?php if (empty($project_progress)): ?>
                                <li>No active projects.</li>
                            <?php else: ?>
                                <?php foreach ($project_progress as $project): ?>
                                    <li>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                            <strong><?= htmlspecialchars($project['name']) ?></strong>
                                            <span><?= number_format((float)$project['completion_percentage'], 1) ?>%</span>
                                        </div>
                                        <div class="progress-container">
                                            <div class="progress-bar" style="width: <?= $project['completion_percentage'] ?>%;"></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Project Progress Chart
        const projectCtx = document.getElementById('projectChart').getContext('2d');
        const projectData = {
            labels: <?= json_encode(array_column($project_progress, 'name')) ?>,
            datasets: [{
                label: 'Completion %',
                data: <?= json_encode(array_column($project_progress, 'completion_percentage')) ?>,
                backgroundColor: '#f59e0b',
                borderRadius: 4
            }]
        };
        new Chart(projectCtx, {
            type: 'bar',
            data: projectData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, max: 100 }
                },
                plugins: { legend: { display: false } }
            }
        });

        // Task Status Chart
        const taskCtx = document.getElementById('taskChart').getContext('2d');
        new Chart(taskCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed'],
                datasets: [{
                    data: [
                        <?= $task_status_counts['pending'] ?? 0 ?>,
                        <?= $task_status_counts['in_progress'] ?? 0 ?>,
                        <?= $task_status_counts['completed'] ?? 0 ?>
                    ],
                    backgroundColor: ['#f59e0b', '#3b82f6', '#10b981'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    </script>
</body>
</html>