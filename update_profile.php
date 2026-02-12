<?php
// Load configuration and database (conn.php handles session_start)
require_once 'conn.php';

// Check if worker is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
header("Location: login.php");
exit();
}

$user_id = $_SESSION['user_id'];
$upload_dir = 'uploads/worker_photos/';
$message = "";
$message_type = ""; // 'success' or 'error'

// Initialize user array to prevent errors if DB connection fails
$user = [];

if ($conn && !$conn->connect_error) {
// Handle profile update if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    
    // Fetch current photo name first to avoid overwriting it if no new photo is uploaded
    $photo_stmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $photo_stmt->bind_param("i", $user_id);
    $photo_stmt->execute();
    $photo_result = $photo_stmt->get_result();
    $current_user = $photo_result->fetch_assoc();
    $profile_photo = $current_user['profile_photo'] ?? null; // Keep old photo by default
    $photo_stmt->close();

    // Handle photo upload if a file was submitted
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['profile_photo']['tmp_name'];
        $name = basename($_FILES['profile_photo']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            $new_name = 'worker_' . $user_id . '_' . time() . '.' . $ext;
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                // Delete old photo if it exists
                if ($profile_photo && file_exists($upload_dir . $profile_photo)) {
                    unlink($upload_dir . $profile_photo);
                }
                $profile_photo = $new_name; // Set the new photo name
            } else {
                $message = "Failed to upload photo.";
                $message_type = 'error';
            }
        } else {
            $message = "Invalid file type. Allowed: jpg, jpeg, png, gif.";
            $message_type = 'error';
        }
    }

    // Update user data in the database if no upload error occurred
    if (empty($message_type)) {
        $update_stmt = $conn->prepare("UPDATE users SET phone = ?, address = ?, profile_photo = ? WHERE id = ?");
        $update_stmt->bind_param("sssi", $phone, $address, $profile_photo, $user_id);

        if ($update_stmt->execute()) {
            $message = "Profile updated successfully.";
            $message_type = 'success';
        } else {
            $message = "Error updating profile: " . $update_stmt->error;
            $message_type = 'error';
        }
        $update_stmt->close();
    }
}

// Fetch worker info for display
$stmt = $conn->prepare("SELECT name, email, phone, address, profile_photo FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$conn->close();
} else {
$message = "Database connection failed.";
$message_type = "error";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Update Profile - Construct.</title>
<!-- Google Fonts -->
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
        --success-bg: #dcfce7;
        --success-text: #166534;
        --danger-bg: #fee2e2;
        --danger-text: #b91c1c;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--light-bg);
        color: var(--text-medium);
        display: flex;
        position: relative; /* For sidebar positioning */
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
    .sidebar .logo {
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--white-bg);
        margin-bottom: 2rem;
    }
    .sidebar .logo span { color: var(--primary-color); }
    .sidebar-nav a {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #d1d5db;
        text-decoration: none;
        padding: 0.85rem 1rem;
        border-radius: 8px;
        margin-bottom: 0.5rem;
        transition: background-color 0.2s, color 0.2s;
    }
    .sidebar-nav a:hover, .sidebar-nav a.active {
        background-color: var(--primary-color);
        color: var(--white-bg);
    }
    .sidebar-nav a svg { width: 20px; height: 20px; }
    .main-content {
        margin-left: 260px;
        width: calc(100% - 260px);
        padding: 2rem;
        transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out;
    }
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    .header h1 {
        font-size: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .user-info a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 600;
    }
    .form-container {
        background-color: var(--white-bg);
        padding: 2.5rem;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        max-width: 800px;
        margin: 0 auto;
    }
    .form-container h2 {
        margin-bottom: 2rem;
        font-size: 1.8rem;
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-dark);
    }
    .form-group input, .form-group textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        font-size: 1rem;
        font-family: 'Inter', sans-serif;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-group input:focus, .form-group textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.3);
    }
    textarea {
        min-height: 100px;
        resize: vertical;
    }
    .profile-photo-group {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        flex-wrap: wrap; /* Allow wrapping on small screens */
    }
    .profile-photo-preview {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        background-color: var(--border-color);
    }
    .form-actions {
        margin-top: 2rem;
        display: flex;
        justify-content: flex-end;
    }
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    .btn-primary {
        background-color: var(--primary-color);
        color: var(--white-bg);
    }
    .btn-primary:hover {
        background-color: var(--primary-hover-color);
    }
    .message {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        text-align: center;
        font-weight: 500;
    }
    .message.success {
        background-color: var(--success-bg);
        color: var(--success-text);
    }
    .message.error {
        background-color: var(--danger-bg);
        color: var(--danger-text);
    }

    /* --- Styles for Responsiveness --- */
    .menu-toggle {
        display: none;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
    }
    .menu-toggle svg {
        width: 28px;
        height: 28px;
        color: var(--text-dark);
    }
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 999;
    }
    body.sidebar-visible .sidebar-overlay {
        display: block;
    }
    
    /* --- Media Queries for Responsive Design --- */
    @media (max-width: 1024px) {
        .sidebar {
            transform: translateX(-100%);
        }
        body.sidebar-visible .sidebar {
            transform: translateX(0);
        }
        .main-content {
            margin-left: 0;
            width: 100%;
        }
        .menu-toggle {
            display: block;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 1rem;
        }
        .header {
            flex-wrap: wrap;
        }
        .header h1 {
            font-size: 1.5rem;
        }
        .form-container {
            padding: 1.5rem;
        }
        .form-container h2 {
            font-size: 1.5rem;
        }
        .form-actions {
            justify-content: center;
        }
        .btn {
            width: 100%;
        }
    }
</style>
</head>
<body>
<!-- Added for responsiveness -->
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
      <a href="worker_profile.php" class="active">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            My Profile
        </a>
          <a href="workermessages.php">Messages</a>
    </nav>
</aside>

<div class="main-content">
    <header class="header">
        <h1>
            <!-- Added for responsiveness -->
            <button class="menu-toggle">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
            Update Profile
        </h1>
        <div class="user-info">
            Welcome, <strong><?= htmlspecialchars($_SESSION['name'] ?? 'Worker'); ?></strong> | <a href="logout.php">Logout</a>
        </div>
    </header>

    <div class="form-container">
        <h2>Your Profile Information</h2>
        
        <?php if ($message): ?>
            <p class="message <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group profile-photo-group">
                <img src="<?= ($user['profile_photo'] && file_exists($upload_dir . $user['profile_photo'])) ? $upload_dir . htmlspecialchars($user['profile_photo']) : 'https://placehold.co/80x80/e2e8f0/334155?text=Photo' ?>" alt="Profile Photo" class="profile-photo-preview">
                <div>
                    <label for="profile_photo">Change Profile Photo</label>
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
                </div>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </div>
        </form>
    </div>
</div>

<!-- Added JavaScript for sidebar toggle -->
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
