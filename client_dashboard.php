<?php
require_once 'conn.php';

// --- SECURITY & ACCESS CONTROL ---

if (!isset($_SESSION['user_id'])) {

  header("Location: login.php");

  exit();

}



// --- DATA FETCHING ---

$user_id = $_SESSION['user_id'];

$client = null;

$stats = ['projects' => 0, 'workers' => 0, 'equipment' => 0];

$projects = [];



try {

  // Fetch client details

  $stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");

  $stmt->bind_param("i", $user_id);

  $stmt->execute();

  $result = $stmt->get_result();

  $client = $result->fetch_assoc();

  $stmt->close();



  if (!$client) {

    session_destroy();

    header("Location: login.php");

    exit();

  }



  // Fetch total projects for the client

  $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM projects WHERE client_id = ? AND deleted_at IS NULL");

  $stmt->bind_param("i", $user_id);

  $stmt->execute();

  $res = $stmt->get_result();

  if ($res) $stats['projects'] = $res->fetch_assoc()['count'];

  $stmt->close();



  // Fetch total available workers (this is a site-wide stat)

  $res = $conn->query("SELECT COUNT(*) AS count FROM workers WHERE status = 'approved'");

  if ($res) $stats['workers'] = $res->fetch_assoc()['count'];



  // Fetch total equipment (this is a site-wide stat)

  $res = $conn->query("SELECT COUNT(*) AS count FROM equipment WHERE deleted_at IS NULL");

  if ($res) $stats['equipment'] = $res->fetch_assoc()['count'];



  // Fetch recent projects for the client

  $stmt = $conn->prepare("

    SELECT id, name, status, start_date, end_date

    FROM projects

    WHERE client_id = ? AND deleted_at IS NULL

    ORDER BY created_at DESC

    LIMIT 5

  ");

  $stmt->bind_param("i", $user_id);

  $stmt->execute();

  $projects_result = $stmt->get_result();

  if ($projects_result) {

    // Corrected: Only one loop is needed to fetch the data

    while ($row = $projects_result->fetch_assoc()) {

      $projects[] = $row;

    }

  }

  $stmt->close();



} catch (Exception $e) {

  error_log("Dashboard Data Fetch Error: " . $e->getMessage());

  $_SESSION['error'] = "Could not load dashboard data. Please try again later.";

}



$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - Construct.</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="client_style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar_client.php'; ?>
        <div class="overlay" id="overlay"></div>
        <div class="main-content-wrapper">
            <header class="header">
                <button class="menu-toggle" id="menu-toggle" aria-label="Open sidebar">
                    <i data-lucide="menu"></i>
                </button>
                <h1>Client Dashboard</h1>
                <a href="logout.php" class="logout-btn">Logout</a>
            </header>
            
            <main class="main-content">
                <div class="welcome-section">
                    <h2>Welcome back, <?= htmlspecialchars($client['name'] ?? 'Client') ?>!</h2>
                    <p>Here's a summary of your construction projects and activities.</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Projects</h3>
                        <p><?= $stats['projects'] ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Available Workers</h3>
                        <p><?= $stats['workers'] ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Equipment</h3>
                        <p><?= $stats['equipment'] ?></p>
                    </div>
                </div>

                <h3 class="section-header">Recent Projects</h3>
                <div class="table-wrapper">
                    <table class="projects-table">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>End Date</th>
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
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 2rem; white-space: normal;">No recent projects found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($stats['projects'] == 0): ?>
                <div style="margin-bottom: 2rem; padding: 1rem; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 10px; color: #856404;">
                    <strong>No projects selected yet.</strong> Click the button below to add a new task.
                </div>
                <?php endif; ?>

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
