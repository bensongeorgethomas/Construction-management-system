<?php
require_once '../conn.php';
require_once '../includes/csrf.php';

// Check if connection was successful (conn.php handles the connection creation)
if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? 'Unknown error'));
}

// Ensure a user is logged in AND their role is 'supplier'
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'supplier') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$supplier_name = $_SESSION['name'];
$success_message = '';
$error_message = '';
$supplier_profile = null;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    requireCSRF();
    $contact_person = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $tax_id = trim($_POST['tax_id']);

    // Update the 'suppliers' table using the 'user_id' link
    $stmt = $conn->prepare("UPDATE suppliers SET contact_person = ?, phone = ?, address = ?, tax_id = ? WHERE user_id = ?");
    $stmt->bind_param("ssssi", $contact_person, $phone, $address, $tax_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Profile updated successfully!";
    } else {
        $error_message = "Failed to update profile: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch supplier profile data from 'suppliers' table
$stmt_fetch = $conn->prepare("SELECT * FROM suppliers WHERE user_id = ?");
$stmt_fetch->bind_param("i", $user_id);
if ($stmt_fetch->execute()) {
    $supplier_profile = $stmt_fetch->get_result()->fetch_assoc();
} else {
    $error_message = "Could not load supplier profile.";
}
$stmt_fetch->close();

// If no profile exists, create one
if (!$supplier_profile) {
    // Fetch email from users table to create a basic profile
    $user_email = $_SESSION['email'];
    $stmt_create = $conn->prepare("INSERT INTO suppliers (user_id, name, email) VALUES (?, ?, ?)");
    $stmt_create->bind_param("iss", $user_id, $supplier_name, $user_email);
    $stmt_create->execute();
    $stmt_create->close();
    
    // Re-fetch the newly created profile
    $stmt_fetch = $conn->prepare("SELECT * FROM suppliers WHERE user_id = ?");
    $stmt_fetch->bind_param("i", $user_id);
    $stmt_fetch->execute();
    $supplier_profile = $stmt_fetch->get_result()->fetch_assoc();
    $stmt_fetch->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Profile - Construct.</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #f59e0b; 
            --primary-hover: #d97706; 
            --dark-bg: #111827;
            --sidebar-bg: #1f2937;
            --light-bg: #f3f4f6; 
            --card-bg: #ffffff; 
            --text-dark: #1f2937;
            --text-light: #9ca3af; 
            --text-white: #f9fafb;
            --border-color: #e5e7eb; 
            --success-color: #10b981;
            --danger-color: #ef4444; 
            --sidebar-width: 260px;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-bg); color: var(--text-dark); display: flex; height: 100vh; overflow: hidden; }
        
        /* --- Sidebar --- */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            color: var(--text-white);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
            z-index: 1000;
            flex-shrink: 0;
            height: 100%;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .logo { font-size: 1.5rem; font-weight: 800; color: var(--text-white); text-decoration: none; }
        .logo span { color: var(--primary-color); }
        
        .nav-links { flex: 1; padding: 1rem 0; overflow-y: auto; }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border-left: 3px solid transparent;
            font-weight: 500;
        }
        
        .nav-item:hover, .nav-item.active {
            background-color: rgba(255,255,255,0.05);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }
        
        .nav-item i { width: 24px; margin-right: 10px; }
        
        .user-profile {
            padding: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px; height: 40px; background: var(--primary-color); border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-weight: bold; color: white;
        }
        
        .user-info { flex: 1; overflow: hidden; }
        .user-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { font-size: 0.8rem; color: var(--text-light); }
        
        .btn-logout { color: var(--text-light); transition: color 0.2s; }
        .btn-logout:hover { color: var(--danger-color); }

        /* --- Main Content --- */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            position: relative;
        }
        
        .top-bar {
            background: var(--card-bg);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            display: none; /* Hidden on desktop, shown on mobile */
        }
        
        .menu-toggle { font-size: 1.5rem; color: var(--text-dark); cursor: pointer; background: none; border: none; }
        
        .content-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }
        
        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 1.875rem; font-weight: 700; color: var(--text-dark); }
        .page-subtitle { color: #6b7280; margin-top: 0.25rem; }

        /* --- Form Styles --- */
        .form-container { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid var(--border-color); max-width: 900px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 1rem; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { font-weight: 500; margin-bottom: 0.5rem; color: var(--text-dark); }
        .form-group input, .form-group textarea { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); font-size: 1rem; font-family: 'Inter', sans-serif; transition: border-color 0.2s; }
        .form-group input:focus, .form-group textarea:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1); }
        .form-group input[disabled] { background-color: #f9fafb; color: #6b7280; cursor: not-allowed; }
        
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .alert-success { background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        
        .btn { display: inline-block; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; transition: background-color 0.2s; }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover); }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { position: fixed; transform: translateX(-100%); width: 280px; }
            .sidebar.active { transform: translateX(0); }
            .top-bar { display: flex; }
            .content-container { padding: 1.5rem; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="logo">Construct<span>.</span></a>
            <button class="menu-toggle" style="color:white; display:none;" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
        </div>
        
        <nav class="nav-links">
            <a href="supplier_dashboard.php" class="nav-item">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            <a href="supplier_dashboard.php" class="nav-item">
                <i class="fas fa-boxes"></i> My Products
            </a>
            <a href="supplier_dashboard.php" class="nav-item">
                <i class="fas fa-plus-circle"></i> Add Product
            </a>
            <a href="supplier_dashboard.php" class="nav-item">
                <i class="fas fa-clipboard-list"></i> Requests 
            </a>
            <a href="supplier_orders.php" class="nav-item">
                <i class="fas fa-shopping-cart"></i> Orders 
            </a>
            <a href="supplier_profile.php" class="nav-item active">
                <i class="fas fa-user-circle"></i> Profile
            </a>
        </nav>
        
        <div class="user-profile">
            <div class="user-avatar"><?= strtoupper(substr($supplier_name, 0, 1)) ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($supplier_name) ?></div>
                <div class="user-role">Supplier</div>
            </div>
            <a href="../logout.php" class="btn-logout" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Mobile Header -->
        <header class="top-bar">
            <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <a href="#" class="logo" style="color:var(--text-dark)">Construct<span>.</span></a>
            <div class="user-avatar" style="width:32px; height:32px; font-size:0.8rem;"><?= strtoupper(substr($supplier_name, 0, 1)) ?></div>
        </header>

        <div class="content-container">
            <div class="page-header">
                <h1 class="page-title">My Company Profile</h1>
                <p class="page-subtitle">Manage your company details and contact information.</p>
            </div>

            <div class="form-container">
                <?php if (!empty($success_message)): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success_message ?></div><?php endif; ?>
                <?php if (!empty($error_message)): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error_message ?></div><?php endif; ?>

                <?php if ($supplier_profile): ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Company Name</label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($supplier_profile['name']) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="email">Login Email</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($supplier_profile['email']) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="contact_person">Contact Person</label>
                            <input type="text" id="contact_person" name="contact_person" value="<?= htmlspecialchars($supplier_profile['contact_person']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($supplier_profile['phone']) ?>" required>
                        </div>
                        <div class="form-group full-width">
                            <label for="address">Company Address</label>
                            <textarea id="address" name="address" rows="3"><?= htmlspecialchars($supplier_profile['address']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="tax_id">Tax ID / GST Number</label>
                            <input type="text" id="tax_id" name="tax_id" value="<?= htmlspecialchars($supplier_profile['tax_id']) ?>">
                        </div>
                    </div>
                    <div style="text-align: right; margin-top: 1.5rem;">
                        <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
                <?php else: ?>
                    <p>Error loading profile. Please try logging out and back in.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>
