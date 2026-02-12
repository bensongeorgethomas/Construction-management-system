<?php
require_once 'conn.php';
require_once 'includes/csrf.php';

// Check if Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Query for orders
$sql = "SELECT 
            o.*, 
            s.name as supplier_name, 
            s.phone as supplier_phone,
            i.invoice_path, 
            i.invoice_date, 
            i.amount as invoice_amount
        FROM supplier_orders o 
        JOIN users s ON o.supplier_id = s.id 
        LEFT JOIN supplier_invoices i ON o.id = i.order_id
        ORDER BY o.created_at DESC";

$result = $conn->query($sql);

// Calculate stats
$total_orders = 0;
$pending_orders = 0;
$total_spent = 0;

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $total_orders++;
        if (strtolower($row['status']) == 'pending') {
            $pending_orders++;
        }
        // Assuming total_amount is in the order table, if not use invoice_amount if available
        $amount = isset($row['total_amount']) ? $row['total_amount'] : (isset($row['invoice_amount']) ? $row['invoice_amount'] : 0);
        $total_spent += $amount;
    }
    // Reset result pointer for display loop
    $result->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Orders - Construct.</title>
    <link href="admin_style.css" rel="stylesheet">
    <style>
        .order-card { 
            background: var(--bg-card); 
            padding: 1.5rem; 
            border-radius: var(--radius); 
            box-shadow: var(--shadow-sm); 
            margin-bottom: 1.5rem; 
            border-left: 5px solid var(--border); 
            border: 1px solid var(--border);
            border-left-width: 5px;
        }
        /* Status Colors for Borders */
        .order-card[data-status="Pending"] { border-left-color: var(--warning); }
        .order-card[data-status="Processing"] { border-left-color: var(--info); }
        .order-card[data-status="Shipped"] { border-left-color: var(--primary); }
        .order-card[data-status="Fulfilled"] { border-left-color: var(--success); }
        .order-card[data-status="Rejected"], .order-card[data-status="Cancelled"] { border-left-color: var(--danger); }

        .card-header-flex { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
        .card-header-flex h3 { font-size: 1.1rem; color: var(--text-main); margin: 0; font-weight: 600; }

        .details { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; font-size: 0.9rem; color: var(--text-muted); }
        .label { display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-light); font-weight: 600; margin-bottom: 4px; }
        .date-highlight { color: var(--text-main); font-weight: 500; }
        .amount-highlight { color: var(--text-main); font-weight: 700; font-size: 1rem; }
        
        .btn-sm { padding: 0.25rem 0.75rem; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar_admin.php'; ?>

        <div class="main-content">
            <header class="header">
                <h1>Supplier Orders</h1>
                <div class="user-info">
                    Welcome, <strong><?= htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></strong> | <a href="logout.php">Logout</a>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="dashboard-grid">
                    <div class="card">
                        <h3>Total Orders</h3>
                        <p class="value"><?= $total_orders ?></p>
                    </div>
                    <div class="card">
                        <h3>Pending Orders</h3>
                        <p class="value"><?= $pending_orders ?></p>
                    </div>
                    <div class="card">
                        <h3>Total Spent</h3>
                        <p class="value">$<?= number_format($total_spent, 2) ?></p>
                    </div>
                </div>

                <div class="section-header">
                    <h2>Order History</h2>
                </div>

                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <div class="order-card" data-status="<?= htmlspecialchars($row['status']) ?>">
                            <div class="card-header-flex">
                                <h3>Order #<?= $row['id'] ?> - <?= htmlspecialchars($row['supplier_name']) ?></h3>
                                <span class="status-badge" style="background-color: var(--text-light); color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem;"><?= htmlspecialchars($row['status']) ?></span>
                            </div>
                            <div class="details">
                                <div>
                                    <span class="label">Ordered On</span>
                                    <span class="date-highlight"><?= date('M d, Y', strtotime($row['created_at'] ?? $row['order_date'] ?? 'now')) ?></span>
                                </div>
                                    <span class="label">Details</span>
                                    Status: <?= htmlspecialchars($row['status']) ?>
                                </div>
                                <div>
                                    <span class="label">Invoice</span>
                                    <?php if (!empty($row['invoice_path'])): ?>
                                        <a href="<?= htmlspecialchars($row['invoice_path']) ?>" target="_blank" style="color: var(--primary); text-decoration: underline;">View PDF</a>
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">Not uploaded</span>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align:right;">
                                    <span class="label">Total Amount</span>
                                    <span class="amount-highlight">$<?= number_format($row['total_amount'] ?? $row['invoice_amount'] ?? 0, 2) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="card">
                        <p style="text-align: center; color: var(--text-muted); padding: 2rem;">No supplier orders found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>