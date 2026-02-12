<?php
require_once 'conn.php';

// Check if worker is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$tasks = [];
$equipment_list = []; // <-- Initialize array for equipment

if ($conn && !$conn->connect_error) {
    // --- QUERY 1: FETCH TASKS ---
    $stmt = $conn->prepare("
        SELECT 
            t.id, 
            t.title, 
            t.status,
            t.priority,
            t.due_date,
            p.name AS project_name
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE t.assignee_id = ? AND t.deleted_at IS NULL 
        ORDER BY t.due_date ASC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $tasks = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();

    // --- QUERY 2: FETCH EQUIPMENT ---
    $stmt_eq = $conn->prepare("
        SELECT DISTINCT
            e.id, e.name, e.type, e.status, e.location,
            p.name AS project_name
        FROM equipment e
        LEFT JOIN projects p ON e.project_id = p.id
        WHERE e.deleted_at IS NULL AND (
            e.assigned_to = ? OR 
            e.project_id IN (SELECT DISTINCT project_id FROM tasks WHERE assignee_id = ?)
        )
        ORDER BY e.name ASC
    ");
    $stmt_eq->bind_param("ii", $user_id, $user_id);
    $stmt_eq->execute();
    $result_eq = $stmt_eq->get_result();
    if ($result_eq) {
        $equipment_list = $result_eq->fetch_all(MYSQLI_ASSOC);
    }
    $stmt_eq->close();
    
    $conn->close(); // Close connection after all queries are done
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Construct.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #f59e0b;
            --primary-hover-color: #d97706;
            --dark-bg: #1f2937;
            --light-bg: #f9fafb;
            --white-bg: #ffffff;
            --text-dark: #111827;
            --text-medium: #4b5563;
            --border-color: #e5e7eb;
            --priority-high-bg: #fee2e2;
            --priority-high-text: #b91c1c;
            --priority-medium-bg: #fef3c7;
            --priority-medium-text: #92400e;
            --priority-low-bg: #dcfce7;
            --priority-low-text: #166534;
            --status-available-bg: #dcfce7;
            --status-available-text: #166534;
            --status-in-use-bg: #fef3c7;
            --status-in-use-text: #92400e;
            --status-maintenance-bg: #fee2e2;
            --status-maintenance-text: #991b1b;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-medium);
            display: flex;
            position: relative; 
        }
        .sidebar {
            width: 260px;
            background-color: var(--dark-bg);
            color: #d1d5db;
            height: 100vh;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: transform 0.3s ease-in-out;
        }
        .sidebar .logo { font-size: 1.8rem; font-weight: 800; color: var(--white-bg); margin-bottom: 2rem; }
        .sidebar .logo span { color: var(--primary-color); }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; color: #d1d5db; text-decoration: none; padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 0.5rem; transition: background-color 0.2s, color 0.2s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background-color: var(--primary-color); color: var(--white-bg); }
        .sidebar-nav a svg { width: 20px; height: 20px; }
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2rem; transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem; }
        .header h1 { font-size: 2rem; display: flex; align-items: center; gap: 1rem; }
        .user-info a { color: var(--primary-color); text-decoration: none; font-weight: 600; }
        .table-container { background-color: var(--white-bg); border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); overflow-x: auto; margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        th { background-color: var(--light-bg); font-weight: 600; color: var(--text-dark); font-size: 0.875rem; text-transform: uppercase; }
        tbody tr:hover { background-color: var(--light-bg); }
        tr:last-child td { border-bottom: none; }
        .btn { text-decoration: none; background: var(--primary-color); color: white; padding: 0.4rem 0.8rem; border-radius: 8px; font-weight: 600; display: inline-block; transition: background 0.3s; border: none; cursor: pointer; font-size: 0.875rem; }
        .btn:hover { background: var(--primary-hover-color); }
        .priority-badge, .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 600; font-size: 0.8rem; text-transform: capitalize; display: inline-block; }
        .priority-high { background-color: var(--priority-high-bg); color: var(--priority-high-text); }
        .priority-medium { background-color: var(--priority-medium-bg); color: var(--priority-medium-text); }
        .priority-low { background-color: var(--priority-low-bg); color: var(--priority-low-text); }
        .status-Available { background-color: var(--status-available-bg); color: var(--status-available-text); }
        .status-In-Use { background-color: #fef3c7; color: #92400e; }
        .status-Maintenance { background-color: var(--status-maintenance-bg); color: var(--status-maintenance-text); }

        .menu-toggle { display: none; background: none; border: none; cursor: pointer; padding: 0; }
        .menu-toggle svg { width: 28px; height: 28px; color: var(--text-dark); }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
        body.sidebar-visible .sidebar-overlay { display: block; }
        
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            body.sidebar-visible .sidebar { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
            .menu-toggle { display: block; }
        }
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; }
            .header h1 { font-size: 1.5rem; }
            .user-info { width: 100%; text-align: left; margin-top: 0.5rem; }
            .main-content { padding: 1rem; }
            .table-container { border-radius: 0; box-shadow: none; }
            table { border: 0; }
            thead { display: none; }
            tr { display: block; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
            td { display: block; text-align: right; font-size: 0.9rem; border-bottom: 1px dashed var(--border-color); padding-left: 50%; position: relative; white-space: normal; }
            td:before { content: attr(data-label); position: absolute; left: 1rem; width: calc(50% - 2rem); padding-right: 10px; white-space: nowrap; text-align: left; font-weight: 600; color: var(--text-dark); }
            tr td:last-child { border-bottom: 0; }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay"></div>

    <aside class="sidebar">
        <h2 class="logo">Construct<span>.</span></h2>
        <nav class="sidebar-nav">
            <a href="worker_dashboard.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                Dashboard
            </a>
            <a href="worker_tasks.php" class="active">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                My Tasks & Equipment
            </a>
            <a href="update_profile.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                My Profile
            </a>
            <a href="workermessages.php">Messages</a>
        </nav>
    </aside>

    <div class="main-content">
        <header class="header">
            <h1>
                <button class="menu-toggle">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                My Dashboard
            </h1>
            <div class="user-info">
                Welcome, <strong><?= htmlspecialchars($_SESSION['name'] ?? 'Worker'); ?></strong> | <a href="logout.php">Logout</a>
            </div>
        </header>

        <div class="table-container">
             <h2>My Assigned Tasks</h2>
            <table>
                <thead>
                    <tr>
                        <th>Task Title</th>
                        <th>Project</th>
                        <th>Due Date</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tasks)): ?>
                        <?php foreach($tasks as $task): ?>
                            <tr>
                                <td data-label="Task Title"><?= htmlspecialchars($task['title']) ?></td>
                                <td data-label="Project"><?= htmlspecialchars($task['project_name']) ?></td>
                                <td data-label="Due Date"><?= htmlspecialchars(date("M d, Y", strtotime($task['due_date']))) ?></td>
                                <td data-label="Priority">
                                    <span class="priority-badge priority-<?= strtolower(htmlspecialchars($task['priority'])) ?>">
                                        <?= htmlspecialchars($task['priority']) ?>
                                    </span>
                                </td>
                                <td data-label="Status"><?= htmlspecialchars($task['status']) ?></td>
                                <td data-label="Actions">
                                   <a href="sendproof.php?task_id=<?= $task['id'] ?>" class="btn">
    Upload Submission
</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">You have no assigned tasks.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-container">
            <h2>My Equipment</h2>
            <table>
                <thead>
                    <tr>
                        <th>Equipment Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Assigned Project</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($equipment_list)): ?>
                        <?php foreach($equipment_list as $item): ?>
                            <tr>
                                <td data-label="Equipment"><?= htmlspecialchars($item['name']) ?></td>
                                <td data-label="Type"><?= htmlspecialchars($item['type']) ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?= htmlspecialchars(str_replace(' ', '-', $item['status'])) ?>">
                                        <?= htmlspecialchars($item['status']) ?>
                                    </span>
                                </td>
                                <td data-label="Location"><?= htmlspecialchars($item['location'] ?? 'N/A') ?></td>
                                <td data-label="Project"><?= htmlspecialchars($item['project_name'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No equipment is currently assigned to you or your projects.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebarOverlay = document.querySelector('.sidebar-overlay');
            
            const toggleSidebar = () => {
                document.body.classList.toggle('sidebar-visible');
            };

            if (menuToggle) {
                menuToggle.addEventListener('click', toggleSidebar);
            }
            
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', toggleSidebar);
            }
        });
    </script>
</body>
</html>