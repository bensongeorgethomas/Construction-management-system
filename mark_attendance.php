<?php
require_once 'conn.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/csrf.php';
    requireCSRF();
    $worker_id = filter_input(INPUT_POST, 'worker_id', FILTER_VALIDATE_INT);
    $status = trim($_POST['status'] ?? '');
    $today = date('Y-m-d');

    if (!$worker_id || empty($status)) {
        $_SESSION['error'] = 'Invalid input.';
        header("Location: view_attendance.php");
        exit();
    }

    // Whitelist allowed status values
    $allowed_statuses = ['present', 'absent', 'late', 'half_day'];
    if (!in_array($status, $allowed_statuses)) {
        $_SESSION['error'] = 'Invalid status value.';
        header("Location: view_attendance.php");
        exit();
    }

    // Check if already marked today (prepared statement)
    $check = $conn->prepare("SELECT id FROM attendance WHERE worker_id = ? AND date = ?");
    $check->bind_param("is", $worker_id, $today);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        $check->close();
        $_SESSION['error'] = 'Attendance already marked for today.';
        header("Location: view_attendance.php");
        exit();
    }
    $check->close();

    // Insert attendance (prepared statement)
    $stmt = $conn->prepare("INSERT INTO attendance (worker_id, date, status) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $worker_id, $today, $status);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Attendance marked successfully.';
    } else {
        $_SESSION['error'] = 'Failed to mark attendance. Please try again.';
    }
    $stmt->close();
    
    header("Location: view_attendance.php");
    exit();
}

$conn->close();
?>
