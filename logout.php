<?php
// Load configuration
require_once 'config.php';
session_name(SESSION_NAME);
session_start();

// Check if a worker is logged in
if (isset($_SESSION['role']) && $_SESSION['role'] === 'worker') {
    
    // Use your standard database connection file
    require_once 'conn.php';

    if ($conn && !$conn->connect_error) {
        $user_id = $_SESSION['user_id'];
        
        // --- NEW: Clear the worker's master login status in the 'users' table ---
        $updateLoginStatusStmt = $conn->prepare("UPDATE users SET login_time = NULL WHERE id = ?");
        $updateLoginStatusStmt->bind_param("i", $user_id);
        $updateLoginStatusStmt->execute();
        $updateLoginStatusStmt->close();
        // --- END NEW ---

        // Update the specific attendance record for this session if the ID exists
        if (isset($_SESSION['attendance_id'])) {
            $attendance_id = $_SESSION['attendance_id'];
            $stmt = $conn->prepare("UPDATE attendance SET logout_time = NOW() WHERE id = ? AND worker_id = ?");
            $stmt->bind_param("ii", $attendance_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        $conn->close();
    }
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to the login page
header("Location: login.php");
exit();
?>