<?php
// Load configuration FIRST (includes secure session settings)
require_once __DIR__ . '/config.php';

// Initialize variables
$error = '';
$conn = null;

// CSRF Protection
require_once 'includes/csrf.php';

try {
    // Load database connection (depends on config.php for DB constants)
    require_once 'conn.php';
    
    // Check if connection was successful
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed. Please try again later.");
    }


// Process login form if submitted
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
         requireCSRF();
         // Validate input fields
        if (empty($_POST['email']) || empty($_POST['password'])) {
            throw new Exception("Please fill in both email and password fields.");
        }

        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Prepare and execute query for all user roles
        $stmt = $conn->prepare("SELECT id, name, email, password, role, status, is_rejected FROM users WHERE email = ?");
        if (!$stmt) {
            throw new Exception("Database error. Please try again.");
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Check if user exists and password is correct
        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception("Invalid email or password.");
        }

        // Check account status for all roles
        if ($user['is_rejected'] == 1) {
            throw new Exception("Your account has been removed or rejected by the admin.");
        }

        // Pending check: Applies to admin, worker, and supplier. Clients are allowed.
        if ($user['status'] == 'pending' && $user['role'] !== 'client') {
            throw new Exception("Your account is still pending approval by the admin.");
        }

        if ($user['status'] == 'rejected') {
            throw new Exception("Your account has been rejected.");
        }

        // Set standardized session variables for all roles
        $_SESSION = [
            'user_id' => $user['id'], // Standardized session key
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'logged_in' => true
        ];

        // If the user is a worker, manage their status and attendance.
        if ($user['role'] === 'worker') {
            date_default_timezone_set('Asia/Kolkata');
            $currentHour = (int)date('G'); 
            
            if ($currentHour < 9 || $currentHour >= 23) {
                throw new Exception("Login for workers is only permitted between 9:00 AM and 4:00 PM.");
            }

            // Set the worker's master login status in the 'users' table
            $updateLoginStatusStmt = $conn->prepare("UPDATE users SET login_time = NOW() WHERE id = ?");
            $updateLoginStatusStmt->bind_param("i", $user['id']);
            $updateLoginStatusStmt->execute();
            $updateLoginStatusStmt->close();

            // Clean up any previous open sessions for this worker
            $cleanupStmt = $conn->prepare("UPDATE attendance SET logout_time = NOW() WHERE worker_id = ? AND logout_time IS NULL");
            $cleanupStmt->bind_param("i", $user['id']);
            $cleanupStmt->execute();
            $cleanupStmt->close();

            // Create a new record in the attendance table for the new session
            $stmt = $conn->prepare("INSERT INTO attendance (worker_id, login_time) VALUES (?, NOW())");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            
            // Get the ID of this new attendance record and store in session
            $_SESSION['attendance_id'] = $conn->insert_id;
            $stmt->close();
        }

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Redirect based on role
        $redirectMap = [
            'admin'    => 'admin/dashboard.php',
            'client'   => 'client/client_dashboard.php',
            'worker'   => 'worker/worker_dashboard.php',
            'supplier' => 'supplier/supplier_dashboard.php'
        ];

        if (isset($redirectMap[$user['role']])) {
            header("Location: " . $redirectMap[$user['role']]);
            exit();
        }

        // Fallback for any unknown roles
        throw new Exception("Unknown user role: " . htmlspecialchars($user['role']));
    }
} catch (Exception $e) {
    $error = $e->getMessage();
} finally {
    // Close database connection if it exists
    if ($conn) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Construct.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #f59e0b;
            --primary-hover-color: #d97706;
            --dark-bg: #1f2937;
            --light-bg: #f9fafb;
            --white-bg: rgba(255, 255, 255, 0.9); /* Semi-transparent white */
            --text-dark: #111827;
            --text-medium: #4b5563;
            --border-color: rgba(209, 213, 219, 0.7); /* Semi-transparent border */
            --error-bg: rgba(254, 226, 226, 0.9); /* Semi-transparent error bg */
            --error-text: #b91c1c;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 1rem;
            background-image: url('corporate.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            background-color: var(--white-bg);
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        .logo span {
            color: var(--primary-color);
        }
        .login-container h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
            text-align: left;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: rgba(255, 255, 255, 0.7);
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.3);
            background-color: white;
        }
        .btn {
            width: 100%;
            padding: 0.85rem;
            border: none;
            background: var(--primary-color);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        .btn:hover {
            background: var(--primary-hover-color);
        }
        .message {
            font-weight: 500;
            margin-bottom: 1.5rem;
            padding: 0.75rem;
            border-radius: 8px;
            text-align: left;
            background-color: var(--error-bg);
            color: var(--error-text);
            border: 1px solid rgba(185, 28, 28, 0.3);
        }
        .register-link {
            margin-top: 1.5rem;
            color: var(--text-dark);
        }
        .register-link a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="logo">Construct<span>.</span></h2>
        <h3>Login</h3>

        <?php if (!empty($error)): ?>
            <p class="message"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Login</button>
            </div>
        </form>

        <p class="register-link">Don't have an account? <a href="email_otp_verification/clientregister.php">Register here</a>.</p>
    </div>
</body>
</html>