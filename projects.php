<?php
require_once 'conn.php';

// Redirect to login if user is not logged in or not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'conn.php';
require_once 'includes/csrf.php';

// Initialize variables
$completed_projects = [];
$active_projects = [];
$success_message = '';
$error_message = '';

// --- HANDLE DELETE REQUEST (must be before queries) ---
if (isset($_POST['delete_project'])) {
    requireCSRF();
    $projectId = intval($_POST['delete_project']);

    try {
        $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->bind_param("i", $projectId);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Project (ID: $projectId) and its related tasks were deleted.";
        } else {
            $_SESSION['error'] = "Failed to delete project.";
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting project: " . $e->getMessage();
    }

    header("Location: projects.php");
    exit();
}

// Check for success/error messages
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Fetch completed projects
$sql_completed = "SELECT p.*, u.name AS client_name 
                  FROM projects p 
                  LEFT JOIN users u ON p.client_id = u.id
                  WHERE p.deleted_at IS NULL AND p.status = 'completed' 
                  ORDER BY p.updated_at DESC";
$result_completed = $conn->query($sql_completed);
while ($row = $result_completed->fetch_assoc()) {
    $completed_projects[] = $row;
}

// Fetch active projects
$sql_active = "SELECT p.*, u.name AS client_name 
               FROM projects p 
               LEFT JOIN users u ON p.client_id = u.id
               WHERE p.deleted_at IS NULL AND p.status = 'active' 
               ORDER BY p.start_date DESC";
$result_active = $conn->query($sql_active);
while ($row = $result_active->fetch_assoc()) {
    $active_projects[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Projects - Construct.</title>
    <link href="admin_style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar_admin.php'; ?>

        <div class="main-content">
            <header class="header">
                <h1>Project Overview</h1>
                <div class="user-info">
                    Welcome, <strong><?= htmlspecialchars($_SESSION['name']); ?></strong> | <a href="logout.php">Logout</a>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="page-actions">
                    <a href="add_project.php" class="btn btn-primary">+ Add Project</a>
                </div>

                <?php if ($success_message): ?><div class="alert alert-success"><?= $success_message ?></div><?php endif; ?>
                <?php if ($error_message): ?><div class="alert alert-danger"><?= $error_message ?></div><?php endif; ?>

                <!-- Project Overview Chart -->
                <div class="card" style="margin-bottom: 2rem;">
                    <h3>Project Progress Overview</h3>
                    <div style="height: 300px;">
                        <canvas id="overviewChart"></canvas>
                    </div>
                </div>

                <div class="section-header">
                    <h2>Current Active Projects</h2>
                    <span class="project-count"><?= count($active_projects) ?></span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Client</th>
                                <th>Description</th>
                                <th>Timeline</th>
                                <th>Progress</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($active_projects)): ?>
                                <?php foreach($active_projects as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['client_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <div style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($row['description'] ?? '') ?>">
                                                <?= htmlspecialchars($row['description'] ?? 'No description') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong>Start:</strong> <?= date('M d, Y', strtotime($row['start_date'])) ?><br>
                                            <strong>End:</strong> <?= date('M d, Y', strtotime($row['end_date'])) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars(number_format((float)$row['completion_percentage'], 2)) ?>%
                                            <div class="progress-container">
                                                <div class="progress-bar" style="width: <?= $row['completion_percentage'] ?>%"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <a href="view_project_details.php?id=<?= $row['id'] ?>" class="btn btn-info" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">View</a>
                                                <form method="POST" action="projects.php" style="display:inline;">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="delete_project" value="<?= $row['id'] ?>">
                                                        <button type="submit" class="btn btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;" onclick="return confirm('Are you sure you want to delete this project?');">Delete</button>
                                                    </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center;">No active projects found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="section-header" style="margin-top: 2rem;">
                    <h2>Completed Projects</h2>
                    <span class="project-count"><?= count($completed_projects) ?></span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Client</th>
                                <th>Description</th>
                                <th>Timeline</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($completed_projects)): ?>
                                <?php foreach($completed_projects as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['client_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <div style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($row['description'] ?? '') ?>">
                                                <?= htmlspecialchars($row['description'] ?? 'No description') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong>Completed:</strong> <?= date('M d, Y', strtotime($row['end_date'])) ?>
                                        </td>
                                        <td>
                                            <a href="view_project_details.php?id=<?= $row['id'] ?>" class="btn btn-info" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center;">No completed projects found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('overviewChart').getContext('2d');
        const projects = <?= json_encode(array_map(function($p) {
            return ['name' => $p['name'], 'progress' => $p['completion_percentage']];
        }, $active_projects)) ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: projects.map(p => p.name),
                datasets: [{
                    label: 'Completion %',
                    data: projects.map(p => p.progress),
                    backgroundColor: '#3b82f6',
                    borderRadius: 4,
                    barPercentage: 0.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: { display: true, text: 'Completion Percentage' }
                    },
                    x: {
                        ticks: { autoSkip: false, maxRotation: 45, minRotation: 0 }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>
</body>
</html>