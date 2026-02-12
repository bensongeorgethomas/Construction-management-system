<?php
require_once 'conn.php';
require_once 'includes/csrf.php';

// Check if connection was successful
if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? 'Unknown error'));
}

// Ensure a user is logged in AND their role is 'supplier'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$supplier_name = $_SESSION['name'];
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$order_id) {
    header("Location: supplier_orders.php");
    exit();
}

$success_message = '';
$error_message = '';

// --- ACTION HANDLING ---

// 1. Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    requireCSRF();
    $new_status = $_POST['new_status'];
    // Add validation for allowed statuses
    $allowed_statuses = ['Pending', 'Processing', 'Shipped', 'Fulfilled', 'Rejected'];
    
    if (in_array($new_status, $allowed_statuses)) {
        // Also capture tracking number and delivery date if provided
        $tracking_number = isset($_POST['tracking_number']) ? trim($_POST['tracking_number']) : null;
        $expected_delivery = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;

        // Check if columns exist before trying to update them (basic error prevention)
        // Ideally you'd run the ALTER TABLE query mentioned previously.
        // Assuming columns exist:
        $stmt_update = $conn->prepare("UPDATE supplier_orders SET status = ?, tracking_number = ?, expected_delivery_date = ? WHERE id = ? AND supplier_id = ?");
        $stmt_update->bind_param("sssii", $new_status, $tracking_number, $expected_delivery, $order_id, $user_id);
        
        if ($stmt_update->execute()) {
            $success_message = "Order status updated to '$new_status'!";
        } else {
            $error_message = "Failed to update status.";
        }
        $stmt_update->close();
    } else {
        $error_message = "Invalid status selected.";
    }
}

// 2. Handle Invoice Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_invoice'])) {
    requireCSRF();
    $invoice_number = trim($_POST['invoice_number']);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $invoice_date = $_POST['invoice_date'];
    $due_date = $_POST['due_date'];
    $invoice_path = null;

    if (isset($_FILES['invoice_file']) && $_FILES['invoice_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/invoices/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $file_name = "inv_" . $order_id . "_" . uniqid() . "_" . basename($_FILES['invoice_file']['name']);
        $destination = $upload_dir . $file_name;

        // Ensure it's a PDF
        $file_type = mime_content_type($_FILES['invoice_file']['tmp_name']);
        if ($file_type == 'application/pdf') {
            if (move_uploaded_file($_FILES['invoice_file']['tmp_name'], $destination)) {
                $invoice_path = $destination;
            } else {
                $error_message = "Could not move uploaded file.";
            }
        } else {
            $error_message = "Invalid file type. Only PDF files are allowed.";
        }
    } else {
        $error_message = "No file uploaded or an error occurred.";
    }

    if ($invoice_path && $amount && $invoice_date) {
        $stmt_inv = $conn->prepare("INSERT INTO supplier_invoices (order_id, supplier_id, invoice_number, invoice_path, amount, invoice_date, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_inv->bind_param("iisssss", $order_id, $user_id, $invoice_number, $invoice_path, $amount, $invoice_date, $due_date);
        
        if ($stmt_inv->execute()) {
            $success_message = "Invoice uploaded successfully!";
        } else {
            $error_message = "Failed to save invoice details: " . $stmt_inv->error;
        }
        $stmt_inv->close();
    } else if (!$error_message) {
        $error_message = "Please fill in all required invoice fields.";
    }
}


// --- DATA FETCHING FOR DISPLAY ---
$order = null;
$order_items = [];
$invoices = [];

// 1. Fetch Order Details (Including Request Message)
$stmt_ord = $conn->prepare("
    SELECT 
        o.*, 
        a.name AS admin_name,
        r.subject AS request_subject,
        r.message AS request_message
    FROM supplier_orders o 
    JOIN users a ON o.admin_id = a.id 
    LEFT JOIN supplier_requests r ON o.request_id = r.id
    WHERE o.id = ? AND o.supplier_id = ?
");
$stmt_ord->bind_param("ii", $order_id, $user_id);

if ($stmt_ord->execute()) {
    $order = $stmt_ord->get_result()->fetch_assoc();
}
$stmt_ord->close();

if (!$order) {
    // If order doesn't exist or doesn't belong to this supplier, kick them out
    header("Location: supplier_orders.php");
    exit();
}

// 2. Fetch Order Items (If you implement itemized orders later)
$stmt_items = $conn->prepare("SELECT * FROM supplier_order_items WHERE order_id = ?");
$stmt_items->bind_param("i", $order_id);
if ($stmt_items->execute()) {
    $result_items = $stmt_items->get_result();
    while ($row = $result_items->fetch_assoc()) {
        $order_items[] = $row;
    }
}
$stmt_items->close();

// 3. Fetch Invoices
$stmt_inv = $conn->prepare("SELECT * FROM supplier_invoices WHERE order_id = ? ORDER BY invoice_date DESC");
$stmt_inv->bind_param("i", $order_id);
if ($stmt_inv->execute()) {
    $result_inv = $stmt_inv->get_result();
    while ($row = $result_inv->fetch_assoc()) {
        $invoices[] = $row;
    }
}
$stmt_inv->close();
$conn->close();

// Determine initial total (if items exist use them, else use the order total, else 0)
$calculated_total = 0;
if (!empty($order_items)) {
    foreach ($order_items as $item) {
        $calculated_total += $item['quantity'] * $item['price_per_unit'];
    }
} else {
    // Fallback if no items found but order has a total
    $calculated_total = $order['total_amount'] ?? 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Order #<?= htmlspecialchars($order_id) ?> - Construct.</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="supplier_style.css">
    <style>
        /* Page Specific Styles */
        .order-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: flex-start; }
        .main-column { display: flex; flex-direction: column; gap: 1.5rem; }
        .sidebar-column { display: flex; flex-direction: column; gap: 1.5rem; position: sticky; top: 2rem; }
        
        .order-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .meta-item { display: flex; flex-direction: column; }
        .meta-item label { font-weight: 600; color: var(--text-dark); margin-bottom: 0.25rem; }
        .meta-item span { color: var(--text-medium); }
        
        .order-total { text-align: right; font-size: 1.1rem; font-weight: 700; padding: 1rem; }
        .form-group.inline { flex-direction: row; align-items: center; gap: 1rem; }
        .form-group.inline button { flex-shrink: 0; }
        
        .invoice-list li { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color); }
        .invoice-list li:last-child { border-bottom: none; }
        
        /* Responsive adjustments for this page */
        @media (max-width: 1024px) {
            .order-layout { grid-template-columns: 1fr; }
            .sidebar-column { position: static; }
        }
    </style>
</head>
<body>

    <?php 
    $is_dashboard_page = false; 
    include 'includes/sidebar_supplier.php'; 
    ?>

    <main class="main-content">
        <header class="top-bar">
            <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <a href="supplier_dashboard.php" class="logo" style="color:var(--text-dark)">Construct<span>.</span></a>
            <div class="user-avatar" style="width:32px; height:32px; font-size:0.8rem;"><?= strtoupper(substr($supplier_name, 0, 1)) ?></div>
        </header>

        <div class="content-container">
            
            <div class="page-header">
                <h1>Order #<?= htmlspecialchars($order['id']) ?></h1>
                <a href="supplier_orders.php" class="btn btn-secondary">&larr; Back to All Orders</a>
            </div>

            <?php if (!empty($success_message)): ?><div class="alert alert-success"><?= $success_message ?></div><?php endif; ?>
            <?php if (!empty($error_message)): ?><div class="alert alert-error"><?= $error_message ?></div><?php endif; ?>

            <div class="order-layout">
                <div class="main-column">
                    
                    <!-- Stepper & Order Details -->
                    <div class="card" style="overflow: visible;">
                        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                            <h2><i class="fas fa-info-circle" style="color:var(--primary-color); margin-right:8px;"></i> Order Status & Details</h2>
                            <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
                                <i class="fas fa-circle" style="font-size:0.5rem;"></i> <?= htmlspecialchars($order['status']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <!-- Visual Stepper -->
                            <?php 
                            $stages = ['Pending', 'Processing', 'Shipped', 'Fulfilled'];
                            $current_status = $order['status'];
                            $current_index = array_search($current_status, $stages);
                            // If status is not in the standard flow (e.g. Cancelled/Rejected), handle appropriately
                            // For simplicity, if Rejected, we might show a red alert instead of stepper.
                            ?>

                            <?php if($current_status == 'Rejected' || $current_status == 'Cancelled'): ?>
                                <div class="alert alert-error" style="margin-bottom:2rem;">
                                    <strong>Order is <?= $current_status ?>.</strong> No further action required.
                                </div>
                            <?php else: ?>
                                <div class="stepper">
                                    <?php foreach($stages as $index => $stage): 
                                        $class = '';
                                        if ($current_index !== false) {
                                            if ($index < $current_index) $class = 'completed';
                                            elseif ($index == $current_index) $class = 'active';
                                        }
                                    ?>
                                    <div class="step <?= $class ?>">
                                        <div class="step-circle">
                                            <?php if($class == 'completed'): ?>
                                                <i class="fas fa-check"></i>
                                            <?php else: ?>
                                                <?= $index + 1 ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="step-label"><?= $stage ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <hr style="border:0; border-top:1px solid var(--border-color); margin: 2rem 0;">

                            <h4 style="margin-bottom:1rem; color:var(--text-medium); font-size:0.9rem; text-transform:uppercase; letter-spacing:0.05em;">Original Request Message</h4>
                            <?php if(!empty($order['request_message'])): ?>
                                <div style="background:#f8fafc; padding:1.5rem; border-radius:8px; border:1px solid var(--border-color); color:var(--text-dark); line-height:1.6;">
                                    <i class="fas fa-quote-left" style="color:var(--text-light); margin-right:8px;"></i>
                                    <?= htmlspecialchars($order['request_message']) ?>
                                </div>
                            <?php else: ?>
                                <p style="color: var(--text-medium); font-style:italic;">No specific message provided for this order.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-box-open" style="color:var(--primary-color); margin-right:8px;"></i> Order Items</h2>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Product Name</th>
                                            <th>Quantity</th>
                                            <th>Price per Unit</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($order_items)): ?>
                                            <tr>
                                                <td colspan="4" style="text-align:center; padding:3rem; color:var(--text-medium);">
                                                    <i class="fas fa-cubes" style="font-size:2rem; margin-bottom:1rem; display:block; opacity:0.3;"></i>
                                                    No itemized products for this request. Refer to the order message above.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($order_items as $item): 
                                                $subtotal = $item['quantity'] * $item['price_per_unit'];
                                            ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight:600; color:var(--text-dark);"><?= htmlspecialchars($item['product_name']) ?></div>
                                                </td>
                                                <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-weight:600;"><?= $item['quantity'] ?></span></td>
                                                <td>₹<?= number_format($item['price_per_unit'], 2) ?></td>
                                                <td style="font-weight:600; color:var(--success-color);">₹<?= number_format($subtotal, 2) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="order-total" style="background:#f8fafc; border-top:1px solid var(--border-color);">
                                <span style="color:var(--text-medium); margin-right:1rem;">Total Order Value:</span>
                                <strong style="font-size:1.4rem; color:var(--text-dark);">₹<?= number_format($calculated_total, 2) ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-file-invoice-dollar" style="color:var(--primary-color); margin-right:8px;"></i> Upload New Invoice</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                    <div class="form-group">
                                        <label for="invoice_number" style="font-size:0.9rem; color:var(--text-medium);">Invoice Number</label>
                                        <div style="position:relative;">
                                            <i class="fas fa-hashtag" style="position:absolute; left:12px; top:12px; color:var(--text-light);"></i>
                                            <input type="text" id="invoice_number" name="invoice_number" placeholder="e.g. INV-001" style="padding-left:36px; transition:all 0.2s;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="amount" style="font-size:0.9rem; color:var(--text-medium);">Invoice Amount (₹)</label>
                                        <div style="position:relative;">
                                            <i class="fas fa-rupee-sign" style="position:absolute; left:12px; top:12px; color:var(--text-light);"></i>
                                            <input type="number" id="amount" name="amount" step="0.01" value="<?= number_format($calculated_total, 2, '.', '') ?>" required style="padding-left:36px; font-weight:600;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="invoice_date" style="font-size:0.9rem; color:var(--text-medium);">Invoice Date</label>
                                        <input type="date" id="invoice_date" name="invoice_date" value="<?= date('Y-m-d') ?>" required style="color:var(--text-dark);">
                                    </div>
                                    <div class="form-group">
                                        <label for="due_date" style="font-size:0.9rem; color:var(--text-medium);">Due Date</label>
                                        <input type="date" id="due_date" name="due_date" style="color:var(--text-dark);">
                                    </div>
                                </div>
                                <div class="form-group" style="margin-top: 1.5rem;">
                                    <label for="invoice_file" style="font-size:0.9rem; color:var(--text-medium);">Invoice File (PDF Only)</label>
                                    <div style="border:2px dashed var(--border-color); padding:1.5rem; border-radius:8px; text-align:center; background:#f9fafb; transition:all 0.2s;" onmouseover="this.style.borderColor='var(--primary-color)'; this.style.background='#fffbeb';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.background='#f9fafb';">
                                        <i class="fas fa-cloud-upload-alt" style="font-size:2rem; color:var(--text-light); margin-bottom:0.5rem;"></i>
                                        <input type="file" id="invoice_file" name="invoice_file" accept="application/pdf" required style="display:block; margin:0 auto; width:auto;">
                                    </div>
                                </div>
                                <div style="text-align: right; margin-top: 1.5rem;">
                                    <button type="submit" name="upload_invoice" class="btn btn-primary">
                                        <i class="fas fa-upload" style="margin-right:8px;"></i> Upload Invoice
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="sidebar-column">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-clipboard-list" style="color:var(--primary-color); margin-right:8px;"></i> Summary</h2>
                        </div>
                        <div class="card-body">
                            <div class="order-meta">
                                <div class="meta-item" style="grid-column: 1 / -1;">
                                    <label>Current Status</label>
                                    <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>" style="font-size: 1rem; justify-content:center;">
                                        <?= htmlspecialchars($order['status']) ?>
                                    </span>
                                </div>
                                <div class="meta-item">
                                    <label><i class="far fa-calendar-alt" style="margin-right:5px; color:var(--text-light);"></i> Date</label>
                                    <span style="font-weight:500;"><?= date('M d, Y', strtotime($order['order_date'])) ?></span>
                                </div>
                                <div class="meta-item">
                                    <label><i class="far fa-user" style="margin-right:5px; color:var(--text-light);"></i> By</label>
                                    <span style="font-weight:500;"><?= htmlspecialchars($order['admin_name']) ?></span>
                                </div>
                                <div class="meta-item" style="grid-column: 1 / -1;">
                                    <label><i class="fas fa-truck" style="margin-right:5px; color:var(--text-light);"></i> Tracking #</label>
                                    <span style="font-family:monospace; background:#f3f4f6; padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($order['tracking_number'] ?? 'Not Added') ?></span>
                                </div>
                                <div class="meta-item" style="grid-column: 1 / -1;">
                                    <label><i class="far fa-clock" style="margin-right:5px; color:var(--text-light);"></i> Est. Delivery</label>
                                    <span style="font-weight:500;"><?= !empty($order['expected_delivery_date']) ? date('M d, Y', strtotime($order['expected_delivery_date'])) : 'Not Added' ?></span>
                                </div>
                            </div>
                            
                            <hr style="border: none; border-top: 1px solid var(--border-color); margin: 1.5rem 0;">

                            <form method="POST">
    <?= csrfField() ?>
                                <div class="form-group">
                                    <label for="new_status" style="margin-bottom:0.5rem; display:block;">Update Status</label>
                                    <select id="new_status" name="new_status" style="margin-bottom:1rem; width:100%; padding:0.6rem; border-radius:6px; border:1px solid var(--border-color);">
                                        <option value="Pending" <?= $order['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Processing" <?= $order['status'] == 'Processing' ? 'selected' : '' ?>>Processing</option>
                                        <option value="Shipped" <?= $order['status'] == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                        <option value="Fulfilled" <?= $order['status'] == 'Fulfilled' ? 'selected' : '' ?>>Fulfilled</option>
                                        <option value="Rejected" <?= $order['status'] == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                    </select>
                                    
                                    <label for="tracking_number" style="margin-bottom:0.5rem; display:block;">Tracking Number</label>
                                    <input type="text" name="tracking_number" id="tracking_number" value="<?= htmlspecialchars($order['tracking_number'] ?? '') ?>" style="margin-bottom:1rem; width:100%; padding:0.6rem; border-radius:6px; border:1px solid var(--border-color);">
                                    
                                    <label for="delivery_date" style="margin-bottom:0.5rem; display:block;">Expected Delivery</label>
                                    <input type="date" name="delivery_date" id="delivery_date" value="<?= htmlspecialchars($order['expected_delivery_date'] ?? '') ?>" style="margin-bottom:1.5rem; width:100%; padding:0.6rem; border-radius:6px; border:1px solid var(--border-color);">

                                    <button type="submit" name="update_status" class="btn btn-primary" style="width:100%">Update Order</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-file-invoice" style="color:var(--primary-color); margin-right:8px;"></i> Invoices</h2>
                        </div>
                        <div class="card-body">
                            <?php if (empty($invoices)): ?>
                                <div style="text-align:center; padding:1.5rem; color:var(--text-medium);">
                                    <i class="far fa-folder-open" style="font-size:2rem; margin-bottom:0.5rem; display:block; opacity:0.3;"></i>
                                    No invoices uploaded yet.
                                </div>
                            <?php else: ?>
                                <ul class="invoice-list" style="list-style: none;">
                                    <?php foreach ($invoices as $invoice): ?>
                                    <li style="padding: 1rem 0; display:flex; justify-content:space-between; align-items:flex-start;">
                                        <div>
                                            <a href="<?= htmlspecialchars($invoice['invoice_path']) ?>" target="_blank" style="font-weight: 600; text-decoration: none; color:var(--info-color); display:flex; align-items:center;">
                                                <i class="fas fa-file-pdf" style="margin-right:6px;"></i>
                                                <?= htmlspecialchars($invoice['invoice_number'] ?: 'Invoice #' . $invoice['id']) ?>
                                            </a>
                                            <div style="font-size:0.8rem; color:var(--text-medium); margin-top:4px;">
                                                <?= date('M d, Y', strtotime($invoice['invoice_date'])) ?>
                                                <span style="margin:0 4px;">•</span>
                                                <strong>₹<?= number_format($invoice['amount'], 2) ?></strong>
                                            </div>
                                        </div>
                                        <span class="status-<?= $invoice['payment_status'] ?>" style="font-size:0.7rem; padding:0.2rem 0.5rem; border-radius:4px;"><?= $invoice['payment_status'] ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
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