<?php
require_once 'conn.php';

// --- SECURITY & DATABASE ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'conn.php';

// --- DATA FETCHING ---
$user_id = $_SESSION['user_id'];
$user = null;

try {
    $stmt = $conn->prepare("SELECT name, email, phone, address, role, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Profile Fetch Error: " . $e->getMessage());
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Construct.</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="client_style.css">
    <style>
        .profile-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            max-width: 800px;
            margin: 0 auto;
        }
        .profile-header-bg {
            height: 150px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
        }
        .profile-content {
            padding: 0 2rem 2rem;
            position: relative;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            padding: 5px;
            margin-top: -50px;
            box-shadow: var(--shadow-md);
            display: grid;
            place-items: center;
        }
        .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: var(--bg-body);
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: var(--text-muted);
        }
        .profile-info {
            margin-top: 1rem;
        }
        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .profile-role {
            display: inline-block;
            margin-top: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: var(--bg-body);
            color: var(--text-muted);
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        .info-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        .info-group div {
            font-size: 1rem;
            color: var(--text-main);
            font-weight: 500;
            padding: 0.75rem;
            background: var(--bg-body);
            border-radius: 8px;
            border: 1px solid var(--border);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar_client.php'; ?>
        
        <div class="main-content-wrapper">
            <header class="header">
                 <button class="menu-toggle" id="menu-toggle" aria-label="Open sidebar">
                    <i data-lucide="menu"></i>
                </button>
                <h1>My Profile</h1>
                <a href="logout.php" class="logout-btn">Logout</a>
            </header>
            
            <main class="main-content">
                <?php if ($user): ?>
                <div class="profile-card">
                    <div class="profile-header-bg"></div>
                    <div class="profile-content">
                        <div class="profile-avatar">
                            <div class="avatar-placeholder">
                                <i data-lucide="user" width="40" height="40"></i>
                            </div>
                        </div>
                        
                        <div class="profile-info">
                            <h2 class="profile-name"><?= htmlspecialchars($user['name']) ?></h2>
                            <span class="profile-role"><?= htmlspecialchars($user['role']) ?></span>
                        </div>

                        <div class="profile-grid">
                            <div class="info-group">
                                <label>Email Address</label>
                                <div><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                            
                            <div class="info-group">
                                <label>Phone Number</label>
                                <div><?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></div>
                            </div>
                            
                            <div class="info-group">
                                <label>Address</label>
                                <div><?= htmlspecialchars($user['address'] ?? 'Not provided') ?></div>
                            </div>

                            <div class="info-group">
                                <label>Member Since</label>
                                <div><?= date('F j, Y', strtotime($user['created_at'])) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="message-card">
                        <div class="message-body">
                            <p>User details not found. Please contact support.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bottom Nav -->
    <nav class="bottom-nav">
        <a href="client_dashboard.php"><i data-lucide="layout-dashboard"></i><span>Dashboard</span></a>
        <a href="my_projects.php"><i data-lucide="briefcase"></i><span>Projects</span></a>
        <a href="my_tasks.php"><i data-lucide="list-checks"></i><span>Tasks</span></a>
        <a href="messages.php"><i data-lucide="message-square"></i><span>Messages</span></a>
        <a href="profile.php" class="active"><i data-lucide="user"></i><span>Profile</span></a>
    </nav>
    
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
