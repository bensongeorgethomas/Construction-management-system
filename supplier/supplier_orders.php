<?php
require_once '../conn.php';

// Check if connection was successful (conn.php handles the connection creation)
if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? 'Unknown error'));
}

// FIX: Changed to check 'user_id' for consistency with your other files
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$supplier_name = $_SESSION['name'];

// --- DATA FETCHING ---
$orders = [];
$sql = "
    SELECT 
        o.id, 
        o.order_date, 
        o.status, 
        o.total_amount,
        a.name AS admin_name
    FROM 
        supplier_orders o
    JOIN 
        users a ON o.admin_id = a.id
    WHERE 
        o.supplier_id = ?
    ORDER BY 
        o.order_date DESC, o.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
$stmt->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Construct.</title>
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

        /* --- Table Styles --- */
        .table-container { background-color: var(--card-bg); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow-x: auto; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th, td { padding: 1rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { background-color: #f9fafb; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; }
        td { color: var(--text-dark); font-size: 0.95rem; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background-color: #f9fafb; }
        
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-weight: 600; font-size: 0.8rem; text-transform: capitalize; display: inline-block; }
        .status-Pending { background-color: #fef3c7; color: #92400e; }
        .status-Processing { background-color: #dbeafe; color: #1e40af; }
        .status-Shipped { background-color: #e0e7ff; color: #3730a3; }
        .status-Fulfilled { background-color: #d1fae5; color: #065f46; }
        .status-Rejected { background-color: #fee2e2; color: #991b1b; }
        
        .btn { display: inline-block; padding: 0.5rem 1rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 0.9rem; transition: background 0.2s; }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover); }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { position: fixed; transform: translateX(-100%); width: 280px; }
            .sidebar.active { transform: translateX(0); }
            .top-bar { display: flex; }
            .content-container { padding: 1.5rem; }
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
            <a href="supplier_orders.php" class="nav-item active">
                <i class="fas fa-shopping-cart"></i> Orders 
            </a>
            <a href="supplier_profile.php" class="nav-item">
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
                <h1 class="page-title">My Orders</h1>
                <p class="page-subtitle">Track and manage orders from administrators.</p>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Admin</th>
                            <th>Order Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 4rem; color: #6b7280;">
                                    <i class="fas fa-shopping-basket" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                    You have not received any orders yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong>#<?= htmlspecialchars($order['id']) ?></strong></td>
                                    <td>
                                        <div style="display:flex; align-items:center;">
                                            <div style="width:30px; height:30px; background:#e0e7ff; color:#3730a3; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.8rem; font-weight:bold; margin-right:10px;">
                                                <?= strtoupper(substr($order['admin_name'], 0, 1)) ?>
                                            </div>
                                            <?= htmlspecialchars($order['admin_name']) ?>
                                        </div>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                    <td style="font-weight:600;">₹<?= number_format($order['total_amount'], 2) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
                                            <?= htmlspecialchars($order['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_order.php?id=<?= $order['id'] ?>" class="btn btn-primary"><i class="fas fa-eye"></i> View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
