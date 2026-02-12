<?php
require_once 'conn.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'conn.php';

// --- DATA FETCHING ---
$user_id = $_SESSION['user_id'];
$messages = [];

try {
    // Fetch all messages for the logged-in client, joining with users to get the sender's name
    $stmt = $conn->prepare("
        SELECT 
            m.subject, 
            m.body, 
            m.created_at,
            u.name AS sender_name 
        FROM messages m
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE m.recipient_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Client Messages Fetch Error: " . $e->getMessage());
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Messages - Construct.</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="client_style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar_client.php'; ?>

        <div class="main-content-wrapper">
            <header class="header">
                <h1>My Messages</h1>
            </header>
            
            <main class="main-content">
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message-card">
                            <div class="message-header">
                                <p class="message-subject"><?= htmlspecialchars($message['subject']) ?></p>
                                <p class="message-meta">
                                    From: <strong><?= htmlspecialchars($message['sender_name'] ?? 'System') ?></strong> | 
                                    Received: <?= date('M d, Y, h:i A', strtotime($message['created_at'])) ?>
                                </p>
                            </div>
                            <div class="message-body">
                                <p><?= nl2br(htmlspecialchars($message['body'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-messages">
                        <p>You have no messages in your inbox.</p>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <nav class="bottom-nav">
        <a href="client_dashboard.php" class="active"><i data-lucide="layout-dashboard"></i><span>Dashboard</span></a>
        <a href="my_projects.php"><i data-lucide="briefcase"></i><span>Projects</span></a>
        <a href="my_tasks.php"><i data-lucide="list-checks"></i><span>Tasks</span></a>
        <a href="messages.php"><i data-lucide="message-square"></i><span>Messages</span></a>
        <a href="profile.php"><i data-lucide="user"></i><span>Profile</span></a>
    </nav>
    
    <script>
        lucide.createIcons();
    </script>
</body>
</html>