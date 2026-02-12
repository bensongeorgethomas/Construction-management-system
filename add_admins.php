<?php
require_once 'conn.php';
require_once 'includes/csrf.php';

// Check admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'conn.php';

$error = '';
$success = '';
$name = $email = $phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "This email is already registered.";
        } else {
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'admin', 'approved')");
            $stmt->bind_param("ssss", $name, $email, $phone, $hashed_password);
            
            if ($stmt->execute()) {
                $success = "New admin added successfully!";
                $name = $email = $phone = ''; // Clear fields on success
            } else {
                $error = "Error adding admin: " . $conn->error;
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin - Construct.</title>
    <link href="admin_style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar_admin.php'; ?>

        <div class="main-content">
            <header class="header">
                <h1>Add New Admin</h1>
                <div class="user-info">
                    Welcome, <strong><?= htmlspecialchars($_SESSION['name']); ?></strong> | <a href="logout.php">Logout</a>
                </div>
            </header>

            <div class="content-wrapper">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="card" style="max-width: 600px; margin: 0 auto;">
                    <h3>Admin Details</h3>
                    <form method="POST" action="add_admins.php">
                        <?= csrfField() ?>
                        <div class="form-group">
                            <label for="name">Full Name <span style="color:var(--danger)">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address <span style="color:var(--danger)">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password <span style="color:var(--danger)">*</span></label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span style="color:var(--danger)">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div> 
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Add Admin</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 