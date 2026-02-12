<?php
require_once 'conn.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

// Get the ID of the admin the worker is chatting with from the URL
$conversation_partner_id = filter_input(INPUT_GET, 'with', FILTER_VALIDATE_INT);

// --- MARK MESSAGES AS READ ---
// When a conversation is opened, update the 'read_at' timestamp for incoming messages
if ($conversation_partner_id) {
    $stmt_read = $conn->prepare("UPDATE messages SET read_at = NOW() WHERE sender_id = ? AND recipient_id = ? AND read_at IS NULL");
    $stmt_read->bind_param("ii", $conversation_partner_id, $current_user_id);
    $stmt_read->execute();
}

// --- FETCH CONVERSATION LIST ---
$conversation_list = [];
$conv_sql = "
    SELECT u.id, u.name, u.role,
        (SELECT subject FROM messages WHERE (sender_id = u.id AND recipient_id = {$current_user_id}) OR (recipient_id = u.id AND sender_id = {$current_user_id}) ORDER BY created_at DESC LIMIT 1) as last_subject,
        (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND recipient_id = {$current_user_id} AND read_at IS NULL) as unread_count
    FROM users u
    JOIN messages m ON m.sender_id = u.id OR m.recipient_id = u.id
    WHERE (m.sender_id = {$current_user_id} OR m.recipient_id = {$current_user_id}) AND u.id != {$current_user_id}
    GROUP BY u.id
    ORDER BY MAX(m.created_at) DESC
";
$conversation_list_result = $conn->query($conv_sql);
if($conversation_list_result) $conversation_list = $conversation_list_result->fetch_all(MYSQLI_ASSOC);


// --- FETCH MESSAGES FOR THE SELECTED CONVERSATION ---
$messages = [];
if ($conversation_partner_id) {
    $stmt_msg = $conn->prepare("
        SELECT m.*, u_sender.name as sender_name 
        FROM messages m
        JOIN users u_sender ON m.sender_id = u_sender.id
        WHERE (m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt_msg->bind_param("iiii", $current_user_id, $conversation_partner_id, $conversation_partner_id, $current_user_id);
    $stmt_msg->execute();
    $result_msg = $stmt_msg->get_result();
    if($result_msg) {
        $messages = $result_msg->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Messages - Construct.</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #f59e0b; --primary-hover-color: #d97706; --dark-bg: #1f2937; --light-bg: #f9fafb; --white-bg: #ffffff; --text-dark: #111827; --text-medium: #4b5563; --border-color: #e5e7eb; --danger-color: #ef4444; --danger-hover-color: #dc2626; --success-color: #10b981; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-bg); color: var(--text-medium); display: flex; position: relative; }
        
        /* Sidebar CSS (Copied from worker_dashboard.php) */
        .sidebar { width: 260px; background-color: var(--dark-bg); color: #d1d5db; height: 100vh; padding: 1.5rem; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 1000; transition: transform 0.3s ease-in-out; }
        .sidebar .logo { font-size: 1.8rem; font-weight: 800; color: var(--white-bg); margin-bottom: 2rem; }
        .sidebar .logo span { color: var(--primary-color); }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; color: #d1d5db; text-decoration: none; padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 0.5rem; transition: background-color 0.2s, color 0.2s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background-color: var(--primary-color); color: var(--white-bg); }
        .sidebar-nav a svg { width: 20px; height: 20px; }
        
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2rem; transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem; }
        .header h1 { font-size: 2rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0; }
        .user-info a { color: var(--primary-color); text-decoration: none; font-weight: 600; }
        
        .menu-toggle { display: none; background: none; border: none; cursor: pointer; padding: 0; }
        .menu-toggle svg { width: 28px; height: 28px; color: var(--text-dark); }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
        body.sidebar-visible .sidebar-overlay { display: block; }
        @media (max-width: 1024px) { .sidebar { transform: translateX(-100%); } body.sidebar-visible .sidebar { transform: translateX(0); } .main-content { margin-left: 0; width: 100%; } .menu-toggle { display: block; } }

        /* Messaging Specific CSS */
        .messaging-container { display: flex; height: calc(100vh - 9rem); background: var(--white-bg); border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .conversations-list { width: 35%; border-right: 1px solid var(--border-color); overflow-y: auto; }
        .conversation { display: block; text-decoration: none; color: inherit; }
        .convo-inner { display: flex; align-items: center; padding: 1rem; cursor: pointer; border-bottom: 1px solid var(--border-color); gap: 10px; transition: background-color 0.2s; }
        .conversation:hover .convo-inner, .conversation.active .convo-inner { background-color: #f9fafb; }
        .conversation.unread strong { color: var(--text-dark); }
        .unread-dot { flex-shrink: 0; background-color: var(--primary-color); width: 10px; height: 10px; border-radius: 50%; }
        .chat-window { width: 65%; display: flex; flex-direction: column; }
        .chat-body { flex-grow: 1; padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column-reverse; background-color: #f9fafb; }
        .message { max-width: 80%; padding: 0.85rem 1.25rem; border-radius: 18px; margin-bottom: 1rem; line-height: 1.5; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .message.sent { background-color: var(--primary-color); color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .message.received { background-color: var(--white-bg); color: var(--text-dark); align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid var(--border-color); }
        .message strong { display: block; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 6px; margin-bottom: 6px; font-size: 0.85rem; font-weight: 600; opacity: 0.9; }
        .message.sent strong { border-bottom-color: rgba(255,255,255,0.2); }
        .message-body { font-size: 0.95rem; }
        .message-time { font-size: 0.75rem; text-align: right; margin-top: 4px; opacity: 0.7; }
        .task-link {
            display: inline-block;
            margin-top: 0.75rem;
            padding: 0.5rem 1rem;
            background-color: rgba(0,0,0,0.05);
            border-radius: 8px;
            text-decoration: none;
            color: inherit;
            font-weight: 600;
            font-size: 0.85rem;
            transition: background-color 0.2s;
        }
        .message.sent .task-link { background-color: rgba(255,255,255,0.2); color: white; }
        .message.sent .task-link:hover { background-color: rgba(255,255,255,0.3); }
        
        @media (max-width: 768px) {
            .messaging-container { flex-direction: column; height: auto; }
            .conversations-list { width: 100%; height: 200px; border-right: none; border-bottom: 1px solid var(--border-color); }
            .chat-window { width: 100%; height: 500px; }
            .header h1 { font-size: 1.5rem; }
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
            <a href="worker_tasks.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                My Tasks & Equipment
            </a>
            <a href="update_profile.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                My Profile
            </a>
            <a href="workermessages.php" class="active">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                Messages
            </a>
        </nav>
    </aside>

    <div class="main-content">
        <header class="header">
            <h1><button class="menu-toggle"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></button> My Messages</h1>
            <div class="user-info">Welcome, <strong><?= htmlspecialchars($_SESSION['name'] ?? 'Worker'); ?></strong> | <a href="logout.php">Logout</a></div>
        </header>

        <div class="messaging-container">
            <div class="conversations-list">
                <?php foreach($conversation_list as $convo): ?>
                    <a href="workermessages.php?with=<?= $convo['id'] ?>" class="conversation <?= $convo['id'] == $conversation_partner_id ? 'active' : '' ?> <?= $convo['unread_count'] > 0 ? 'unread' : '' ?>">
                        <div class="convo-inner">
                            <?php if ($convo['unread_count'] > 0): ?><div class="unread-dot"></div><?php else: ?><div style="width:10px; flex-shrink:0;"></div><?php endif; ?>
                            <div>
                                <strong><?= htmlspecialchars($convo['name']) ?> (Admin)</strong>
                                <p><small><?= htmlspecialchars($convo['last_subject']) ?></small></p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="chat-window">
                <?php if ($conversation_partner_id): ?>
                    <div class="chat-body">
                        <div> <?php foreach (array_reverse($messages) as $msg): ?>
                            <div class="message <?= $msg['sender_id'] == $current_user_id ? 'sent' : 'received' ?>">
                                <strong><?= htmlspecialchars($msg['subject']) ?></strong>
                                <div class="message-body">
                                    <?= nl2br(htmlspecialchars($msg['body'])) ?>
                                    
                                    <?php if (!empty($msg['task_id'])): ?>
                                        <a href="worker_tasks.php#task-<?= $msg['task_id'] ?>" class="task-link">
                                            ➔ View Task & Resubmit
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="message-time"><?= date('M d, h:i A', strtotime($msg['created_at'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- Note: No input form here as per original design, just viewing messages. 
                         If the user wants a reply form, they will ask. -->
                    <?php else: ?>
                    <div style="text-align:center; padding: 5rem 1rem; color: var(--text-medium);">
                        <svg style="width: 64px; height: 64px; margin-bottom: 1rem; opacity: 0.5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                        <h3>Select a conversation</h3>
                        <p>Choose an admin from the list to view your messages.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>