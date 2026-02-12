<?php
require_once 'conn.php';
require_once 'includes/csrf.php';

$error_message = '';
$success_message = '';

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    // Sanitize and retrieve POST data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $company_name = trim($_POST['company_name'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Basic validation
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($company_name)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        try {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error_message = "An account with this email already exists.";
            } else {
                // Hash the password for security
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'supplier';
                
                // **FIXED: Status is now 'approved' by default**
                $status = 'approved'; 

                // Insert into the 'users' table with the correct columns
                $insert_stmt = $conn->prepare(
                    "INSERT INTO users (name, email, phone, password, address, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                // Using company_name for the 'name' field
                $insert_stmt->bind_param("sssssss", $company_name, $email, $phone, $hashed_password, $address, $role, $status);

                if ($insert_stmt->execute()) {
                    $success_message = "Registration successful! You can now log in.";
                } else {
                    throw new Exception("Registration failed. Please try again.");
                }
                $insert_stmt->close();
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "An error occurred: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Registration - Construct.</title>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .register-container { background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        h1 { text-align: center; margin-bottom: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        input { width: 100%; padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 1rem; }
        .btn { width: 100%; padding: 0.85rem; border: none; background: #f59e0b; color: white; border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight: 600; }
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: 8px; text-align: center; }
        .success { background-color: #dcfce7; color: #166534; }
        .error { background-color: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>
    <div class="register-container">
        <h1>Supplier Registration</h1>
        
        <?php if ($success_message): ?><p class="message success"><?= $success_message ?></p><?php endif; ?>
        <?php if ($error_message): ?><p class="message error"><?= $error_message ?></p><?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="company_name">Company Name</label>
                <input type="text" id="company_name" name="company_name" required>
            </div>
            <div class="form-group">
                <label for="name">Contact Person Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" required>
            </div>
            <div class="form-group">
                <label for="address">Company Address</label>
                <input type="text" id="address" name="address">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Register</button>
        </form>
    </div>
</body>
</html>