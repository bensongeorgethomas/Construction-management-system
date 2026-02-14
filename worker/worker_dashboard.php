<?php
require_once '../conn.php';

// Check session expiration
if (isset($_SESSION['expire_time']) && time() > $_SESSION['expire_time']) {
    header("Location: ../logout.php");
    exit();
}

// Your DB connection file
// Database connection
require_once '../conn.php';

$user_id = $_SESSION['user_id'];
$upload_dir = 'uploads/worker_photos/';

// Initialize variables
$user = [];
$total_tasks = 0;
$total_projects = 0;
$total_equipment = 0;
$total_hours_display = '0 hours, 0 minutes';
$login_time_for_timer = null;

if ($conn && !$conn->connect_error) {
    // Fetch worker info
    $stmt = $conn->prepare("SELECT name, email, phone, address, profile_photo FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Total tasks assigned
    $stmt_tasks = $conn->prepare("SELECT COUNT(*) AS count FROM tasks WHERE assignee_id = ? AND deleted_at IS NULL");
    $stmt_tasks->bind_param("i", $user_id);
    $stmt_tasks->execute();
    $total_tasks = $stmt_tasks->get_result()->fetch_assoc()['count'];
    $stmt_tasks->close();

    // Total projects
    $stmt_projects = $conn->prepare("SELECT COUNT(DISTINCT project_id) AS count FROM tasks WHERE assignee_id = ? AND deleted_at IS NULL");
    $stmt_projects->bind_param("i", $user_id);
    $stmt_projects->execute();
    $total_projects = $stmt_projects->get_result()->fetch_assoc()['count'];
    $stmt_projects->close();

    // Total equipment
    $total_equipment = $conn->query("SELECT COUNT(*) AS count FROM equipment WHERE deleted_at IS NULL")->fetch_assoc()['count'];

    // Calculate total hours worked from completed sessions
    $stmt_hours = $conn->prepare("
        SELECT SUM(TIME_TO_SEC(TIMEDIFF(logout_time, login_time))) AS total_seconds 
        FROM attendance 
        WHERE worker_id = ? AND logout_time IS NOT NULL
    ");
    $stmt_hours->bind_param("i", $user_id);
    $stmt_hours->execute();
    $row = $stmt_hours->get_result()->fetch_assoc();
    $total_seconds_worked = $row['total_seconds'] ?? 0;
    $total_hours = floor($total_seconds_worked / 3600);
    $total_minutes = floor(($total_seconds_worked % 3600) / 60);
    $total_hours_display = sprintf('%d hours, %d minutes', $total_hours, $total_minutes);
    $stmt_hours->close();

    // Get the login_time for the current active session
    if (isset($_SESSION['attendance_id'])) {
        $stmt_active = $conn->prepare("SELECT login_time FROM attendance WHERE id = ?");
        $stmt_active->bind_param("i", $_SESSION['attendance_id']);
        $stmt_active->execute();
        $active_timer_record = $stmt_active->get_result()->fetch_assoc();
        if ($active_timer_record) {
            $login_time_for_timer = $active_timer_record['login_time'];
        }
        $stmt_active->close();
    }
    
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Dashboard - Construct.</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #f59e0b; --primary-hover-color: #d97706; --dark-bg: #1f2937; --light-bg: #f9fafb; --white-bg: #ffffff; --text-dark: #111827; --text-medium: #4b5563; --border-color: #e5e7eb; --danger-color: #ef4444; --danger-hover-color: #dc2626; --success-color: #10b981; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-bg); color: var(--text-medium); display: flex; position: relative; }
        .sidebar { width: 260px; background-color: var(--dark-bg); color: #d1d5db; height: 100vh; padding: 1.5rem; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 1000; transition: transform 0.3s ease-in-out; }
        .sidebar .logo { font-size: 1.8rem; font-weight: 800; color: var(--white-bg); margin-bottom: 2rem; }
        .sidebar .logo span { color: var(--primary-color); }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; color: #d1d5db; text-decoration: none; padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 0.5rem; transition: background-color 0.2s, color 0.2s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background-color: var(--primary-color); color: var(--white-bg); }
        .sidebar-nav a svg { width: 20px; height: 20px; }
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2rem; transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem; }
        .header h1 { font-size: 2rem; display: flex; align-items: center; gap: 1rem; }
        .user-info a { color: var(--primary-color); text-decoration: none; font-weight: 600; }
        .profile-header { background-color: var(--white-bg); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 1.5rem; margin-bottom: 2rem; }
        .profile-photo { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-color); flex-shrink: 0; }
        .profile-info h2 { font-size: 1.8rem; color: var(--text-dark); }
        .profile-info p { margin-top: 0.25rem; word-break: break-all; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .card { background-color: var(--white-bg); padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .card h3 { font-size: 1rem; color: var(--text-medium); margin-bottom: 0.5rem; }
        .card .value { font-size: 2.5rem; font-weight: 700; color: var(--text-dark); }
        .menu-toggle { display: none; background: none; border: none; cursor: pointer; padding: 0; }
        .menu-toggle svg { width: 28px; height: 28px; color: var(--text-dark); }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
        body.sidebar-visible .sidebar-overlay { display: block; }
        .total-hours { font-size: 1.25rem; font-weight: 600; color: var(--text-dark); margin-bottom: 1rem; }
        .total-hours span { font-weight: 400; font-size: 1rem; color: var(--text-medium); }
        .timer-display { font-family: monospace; font-size: 2rem; font-weight: 700; color: var(--success-color); }
        @media (max-width: 1024px) { .sidebar { transform: translateX(-100%); } body.sidebar-visible .sidebar { transform: translateX(0); } .main-content { margin-left: 0; width: 100%; } .menu-toggle { display: block; } }
        @media (max-width: 768px) { .main-content { padding: 1rem; } .header { flex-wrap: wrap; } .header h1 { font-size: 1.5rem; } .profile-header { flex-direction: column; text-align: center; padding: 1.5rem; } .profile-info h2 { font-size: 1.5rem; } .card .value { font-size: 2rem; } }
    </style>
</head>
<body>
    <div class="sidebar-overlay"></div>
    <?php include '../includes/sidebar_worker.php'; ?>

    <div class="main-content">
        <header class="header">
            <h1><button class="menu-toggle"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></button> Worker Dashboard</h1>
            <div class="user-info">Welcome, <strong><?= htmlspecialchars($_SESSION['name'] ?? 'Worker'); ?></strong> | <a href="../logout.php">Logout</a></div>
        </header>

        <div class="profile-header">
            <img src="<?= ($user['profile_photo'] && file_exists($upload_dir . $user['profile_photo'])) ? $upload_dir . htmlspecialchars($user['profile_photo']) : 'https://placehold.co/100x100/e2e8f0/334155?text=Photo' ?>" alt="Profile Photo" class="profile-photo">
            <div class="profile-info">
                <h2><?= htmlspecialchars($user['name'] ?? 'N/A') ?></h2>
                <p><?= htmlspecialchars($user['email'] ?? 'N/A') ?></p>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <h3>Assigned Tasks</h3>
                <p class="value"><?= $total_tasks ?></p>
            </div>
            <div class="card">
                <h3>Active Projects</h3>
                <p class="value"><?= $total_projects ?></p>
            </div>
            <div class="card">
                <h3>Available Equipment</h3>
                <p class="value"><?= $total_equipment ?></p>
            </div>
            
            <div class="card">
                <h3>My Attendance</h3>
                <p class="total-hours"><?= $total_hours_display ?> <span>(Total Completed)</span></p>
                <h4>Current Session</h4>
                <p id="timerDisplay" class="timer-display" data-start-time="<?= htmlspecialchars($login_time_for_timer ?? '') ?>">
                    Loading...
                </p>
                <small>Timer stops automatically when you log out.</small>
            </div>
        </div>
        
        <div style="background-color: #fff; padding: 2rem; border-radius: 12px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
            <h2 style="color: #111827; font-size: 1.5rem; margin-bottom: 1rem;">Have an issue to report?</h2>
            <p style="margin-bottom: 1.5rem;">Report damaged equipment, safety hazards, or injuries directly to the admin.</p>
            <a href="worker_report.php" style="background-color: #f59e0b; color: white; padding: 0.8rem 2rem; border-radius: 8px; text-decoration: none; font-weight: 600;">File a Report</a>
        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const timerDisplay = document.getElementById('timerDisplay');
        
        if (timerDisplay && timerDisplay.dataset.startTime) {
            const startTimeString = timerDisplay.dataset.startTime;
            // Ensure compatibility with different browser parsers by replacing space with 'T'
            const startTime = new Date(startTimeString.replace(' ', 'T'));

            function updateTimerDisplay() {
                const now = new Date();
                // Get time difference in seconds
                const elapsed = Math.floor((now.getTime() - startTime.getTime()) / 1000);

                // Handle potential clock sync issues where elapsed might be negative
                if (elapsed < 0) {
                    timerDisplay.textContent = '00:00:00';
                    return;
                }

                const hours = Math.floor(elapsed / 3600);
                const minutes = Math.floor((elapsed % 3600) / 60);
                const seconds = elapsed % 60;
                
                timerDisplay.textContent = 
                    `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }

            setInterval(updateTimerDisplay, 1000);
            updateTimerDisplay(); // Run once immediately
        } else if (timerDisplay) {
            timerDisplay.textContent = "Not Clocked In";
            timerDisplay.style.color = "var(--text-medium)";
            timerDisplay.style.fontSize = "1.5rem";
        }

        // Mobile sidebar toggle
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebarOverlay = document.querySelector('.sidebar-overlay');
        
        if (menuToggle && sidebarOverlay) {
            menuToggle.addEventListener('click', () => {
                document.body.classList.toggle('sidebar-visible');
            });

            sidebarOverlay.addEventListener('click', () => {
                document.body.classList.remove('sidebar-visible');
            });
        }
    });
    </script>
</body>
</html>
