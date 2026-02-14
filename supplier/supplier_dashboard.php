<?php
// --- DATABASE & SESSION SETUP ---
require_once '../conn.php';
require_once '../includes/csrf.php';

// Check if connection was successful (conn.php handles the connection creation)
if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? 'Unknown error'));
}

// Ensure a user is logged in AND their role is 'supplier'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header("Location: ../login.php");
    exit();
}

$supplier_id = $_SESSION['user_id'];
$supplier_name = $_SESSION['name'] ?? 'Guest Supplier';

$success_message = '';
$error_message = '';

// --- HANDLE ACCEPT REQUEST (The Connection) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_request'])) {
    requireCSRF();
    $request_id = intval($_POST['request_id']);
    
    // 1. Fetch the original request details (to get the Admin ID)
    $req_stmt = $conn->prepare("SELECT admin_id, subject FROM supplier_requests WHERE id = ? AND supplier_id = ?");
    $req_stmt->bind_param("ii", $request_id, $supplier_id);
    $req_stmt->execute();
    $req_data = $req_stmt->get_result()->fetch_assoc();
    $req_stmt->close();

    if ($req_data) {
        // 2. Create a new Order in 'supplier_orders' linked to this request
        // We set total_amount to 0.00 initially; you can update it in view_order.php
        $order_stmt = $conn->prepare("INSERT INTO supplier_orders (supplier_id, admin_id, request_id, order_date, status, total_amount) VALUES (?, ?, ?, NOW(), 'Pending', 0.00)");
        $order_stmt->bind_param("iii", $supplier_id, $req_data['admin_id'], $request_id);
        
        if ($order_stmt->execute()) {
            $new_order_id = $conn->insert_id;
            $success_message = "Request accepted! Order #$new_order_id has been created. Go to 'Orders' to manage it.";
        } else {
            $error_message = "Failed to create order: " . $order_stmt->error;
        }
        $order_stmt->close();
    } else {
        $error_message = "Invalid request.";
    }
}

// --- EXISTING PRODUCT ACTIONS (Add, Update, Delete) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    require_once '../includes/csrf.php';
    requireCSRF();
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    $image_path = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/products/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_name = uniqid() . '-' . basename($_FILES['image']['name']);
        $destination = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            $image_path = $destination;
        }
    }

    if ($name && $price !== false && $stock !== false) {
        $stmt = $conn->prepare("INSERT INTO products (supplier_id, name, category, price, stock_quantity, image_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdis", $supplier_id, $name, $category, $price, $stock, $image_path);
        if ($stmt->execute()) {
            $success_message = "Product added successfully!";
        } else {
            $error_message = "Failed to add product: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = "Invalid input for product details.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    require_once '../includes/csrf.php';
    requireCSRF();
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $new_stock = filter_input(INPUT_POST, 'new_stock', FILTER_VALIDATE_INT);
    if ($product_id && isset($new_stock)) {
        $stmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE id = ? AND supplier_id = ?");
        $stmt->bind_param("iii", $new_stock, $product_id, $supplier_id);
        if ($stmt->execute()) $success_message = "Stock updated!";
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    require_once '../includes/csrf.php';
    requireCSRF();
    $product_id = intval($_POST['delete_product']);
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND supplier_id = ?");
    $stmt->bind_param("ii", $product_id, $supplier_id);
    $stmt->execute();
    $stmt->close();
    header("Location: supplier_dashboard.php");
    exit();
}

// --- DATA FETCHING ---

// Stats
$stats = ['total_products' => 0, 'pending_orders' => 0, 'total_revenue' => 0.00, 'pending_requests' => 0];
$prod_result = $conn->query("SELECT COUNT(*) as count FROM products WHERE supplier_id = $supplier_id");
if ($prod_result) $stats['total_products'] = $prod_result->fetch_assoc()['count'];

$order_result = $conn->query("SELECT COUNT(*) as count FROM supplier_orders WHERE supplier_id = $supplier_id AND status = 'Pending'");
if ($order_result) $stats['pending_orders'] = $order_result->fetch_assoc()['count'];

$rev_result = $conn->query("SELECT SUM(amount) as total FROM supplier_invoices WHERE supplier_id = $supplier_id AND payment_status = 'Paid'");
if ($rev_result) $stats['total_revenue'] = $rev_result->fetch_assoc()['total'] ?? 0.00;

// Products
$products = [];
$stmt_prod = $conn->prepare("SELECT * FROM products WHERE supplier_id = ? ORDER BY created_at DESC");
$stmt_prod->bind_param("i", $supplier_id);
if ($stmt_prod->execute()) $products = $stmt_prod->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_prod->close();

// Requests (FILTERED: Hide requests that are already Orders)
$requests = [];
$requests_query = "
    SELECT sr.id, sr.subject, sr.message, sr.urgency, sr.created_at, u.name as admin_name
    FROM supplier_requests sr
    LEFT JOIN users u ON sr.admin_id = u.id
    LEFT JOIN supplier_orders so ON sr.id = so.request_id
    WHERE sr.supplier_id = ? AND so.id IS NULL
    ORDER BY sr.created_at DESC";

$stmt_req = $conn->prepare($requests_query);
$stmt_req->bind_param("i", $supplier_id);
if ($stmt_req->execute()) $requests = $stmt_req->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_req->close();

// Update pending request count based on filtered list
$stats['pending_requests'] = count($requests);

// --- GRAPH DATA FETCHING (Must be before $conn->close()) ---

// Fetch Monthly Revenue (Last 6 Months)
$months = [];
$revenues = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    $months[] = date('M Y', strtotime("-$i months"));
    
    $query = "SELECT SUM(amount) as total FROM supplier_invoices WHERE supplier_id = ? AND payment_status = 'Paid' AND invoice_date BETWEEN ? AND ?";
    if ($rev_stmt = $conn->prepare($query)) {
        $rev_stmt->bind_param("iss", $supplier_id, $month_start, $month_end);
        $rev_stmt->execute();
        $res = $rev_stmt->get_result()->fetch_assoc();
        $revenues[] = $res['total'] ?? 0;
        $rev_stmt->close();
    } else {
        $revenues[] = 0; // Fallback if query fails
    }
}

// Fetch Order Status Counts
$status_counts = ['Pending' => 0, 'Completed' => 0, 'Cancelled' => 0];
if ($status_stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM supplier_orders WHERE supplier_id = ? GROUP BY status")) {
    $status_stmt->bind_param("i", $supplier_id);
    $status_stmt->execute();
    $res_status = $status_stmt->get_result();
    while($row = $res_status->fetch_assoc()){
        // Normalize stats keys if needed
        $key = $row['status'] ?: 'Unknown';
        $status_counts[$key] = $row['count'];
    }
    $status_stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Dashboard - Construct.</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="supplier_style.css">
    <style>
        /* Page-specific overrides or chart styles if any */
    </style>
</head>
<body>

    <!-- Sidebar -->
    <?php 
    $is_dashboard_page = true; // Use internal tab logic
    include '../includes/sidebar_supplier.php'; 
    ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Mobile Header -->
        <header class="top-bar">
            <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <a href="#" class="logo" style="color:var(--text-dark)">Construct<span>.</span></a>
            <div class="user-avatar" style="width:32px; height:32px; font-size:0.8rem;"><?= strtoupper(substr($supplier_name, 0, 1)) ?></div>
        </header>

        <div class="content-container">
            <!-- Messages -->
            <?php if (!empty($success_message)): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success_message ?></div><?php endif; ?>
            <?php if (!empty($error_message)): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error_message ?></div><?php endif; ?>

            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content active">
                <div class="page-header">
                    <h1 class="page-title">Dashboard Overview</h1>
                    <p class="page-subtitle">Welcome back, here's what's happening today.</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Products</h3>
                        <p class="value"><?= $stats['total_products'] ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Orders</h3>
                        <p class="value" style="color:var(--primary-color);"><?= $stats['pending_orders'] ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Revenue</h3>
                        <p class="value" style="color:var(--success-color);">₹<?= number_format($stats['total_revenue'], 2) ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>New Requests</h3>
                        <p class="value"><?= $stats['pending_requests'] ?></p>
                    </div>
                </div>

                <!-- Graphs Section -->
                <div class="charts-grid">
                    <div class="stat-card">
                        <h3 style="margin-bottom: 1rem;">Monthly Revenue (Last 6 Months)</h3>
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                    <div class="stat-card">
                        <h3 style="margin-bottom: 1rem;">Order Status</h3>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Chart Data Preparation (Moved to top) -->


                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>
                    // Revenue Chart
                    const ctxRev = document.getElementById('revenueChart').getContext('2d');
                    new Chart(ctxRev, {
                        type: 'line',
                        data: {
                            labels: <?= json_encode($months) ?>,
                            datasets: [{
                                label: 'Revenue (₹)',
                                data: <?= json_encode($revenues) ?>,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                tension: 0.3,
                                fill: true,
                                pointBackgroundColor: '#fff',
                                pointBorderColor: '#10b981',
                                pointHoverBackgroundColor: '#10b981',
                                pointHoverBorderColor: '#fff',
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: { 
                                    display: false 
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 12,
                                    titleColor: '#fff',
                                    bodyColor: '#fff',
                                    displayColors: false
                                }
                            },
                            scales: {
                                y: { 
                                    beginAtZero: true, 
                                    grid: { 
                                        borderDash: [2, 4],
                                        color: 'rgba(0, 0, 0, 0.05)' 
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return '₹' + value.toLocaleString();
                                        }
                                    }
                                },
                                x: { 
                                    grid: { 
                                        display: false 
                                    } 
                                }
                            }
                        }
                    });

                    // Status Chart - Fixed for stability
                    const statusData = <?= json_encode($status_counts) ?>;
                    const ctxStatus = document.getElementById('statusChart').getContext('2d');
                    new Chart(ctxStatus, {
                        type: 'doughnut',
                        data: {
                            labels: Object.keys(statusData),
                            datasets: [{
                                data: Object.values(statusData),
                                backgroundColor: ['#f59e0b', '#10b981', '#ef4444', '#3b82f6'],
                                borderWidth: 2,
                                borderColor: '#fff',
                                hoverOffset: 10
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { 
                                    position: 'bottom', 
                                    labels: { 
                                        usePointStyle: true, 
                                        padding: 15,
                                        font: {
                                            size: 12
                                        }
                                    } 
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 12,
                                    displayColors: true
                                }
                            },
                            cutout: '70%'
                        }
                    });
                </script>
            </div>

            <!-- Products Tab -->
            <div id="products" class="tab-content">
                <div class="page-header display-flex" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h1 class="page-title">My Products</h1>
                        <p class="page-subtitle">Manage your inventory and stock levels.</p>
                    </div>
                    <button class="btn btn-primary" onclick="showTab('upload', document.querySelectorAll('.nav-item')[2])"><i class="fas fa-plus"></i> Add New</button>
                </div>

                <div class="product-grid">
                    <?php if (empty($products)): ?>
                        <div style="grid-column: 1/-1; text-align:center; padding:3rem; color:var(--text-light);">
                            <i class="fas fa-box-open" style="font-size:3rem; margin-bottom:1rem;"></i>
                            <p>No products added yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <img src="<?= htmlspecialchars($product['image_path'] ?? 'https://placehold.co/600x400/e2e8f0/64748b?text=No+Image') ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                            <div class="product-info">
                                <h4><?= htmlspecialchars($product['name']) ?></h4>
                                <div style="display:flex; justify-content:space-between; color:var(--text-light); font-size:0.9rem; margin-bottom:0.5rem;">
                                    <span><?= htmlspecialchars($product['category']) ?></span>
                                    <span>Stock: <strong><?= $product['stock_quantity'] ?></strong></span>
                                </div>
                                <div style="font-size:1.1rem; font-weight:700; color:var(--text-dark);">₹<?= number_format($product['price'], 2) ?></div>
                                
                                <form method="POST" class="product-actions">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <input type="number" name="new_stock" value="<?= $product['stock_quantity'] ?>" style="width:70px; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
                                    <button type="submit" name="update_stock" class="btn btn-primary btn-sm">Update</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="delete_product" value="<?= $product['id'] ?>">
                                    <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#991b1b; border:none; padding: 0.5rem; cursor: pointer;"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upload Tab -->
            <div id="upload" class="tab-content">
                <div class="page-header">
                    <h1 class="page-title">Add New Product</h1>
                    <p class="page-subtitle">Add a new item to your supplier catalog.</p>
                </div>

                <div class="form-container">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <div class="form-group">
                            <label>Product Name</label>
                            <input type="text" name="name" required placeholder="e.g. Cement Bags (50kg)">
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <input type="text" name="category" required placeholder="e.g. Raw Material">
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                            <div class="form-group">
                                <label>Price (₹)</label>
                                <input type="number" name="price" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label>Initial Stock</label>
                                <input type="number" name="stock" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Product Image</label>
                            <input type="file" name="image" style="padding:0.5rem; background:#f9fafb;">
                        </div>
                        <button type="submit" name="add_product" class="btn btn-primary"><i class="fas fa-save"></i> Save Product</button>
                    </form>
                </div>
            </div>

            <!-- Requests Tab -->
            <div id="requests" class="tab-content">
                <div class="page-header">
                    <h1 class="page-title">Supply Requests</h1>
                    <p class="page-subtitle">Requests from administrators needing material.</p>
                </div>

                <?php if (empty($requests)): ?>
                    <p style="text-align:center; padding:2rem; color:var(--text-light);">No pending requests at the moment.</p>
                <?php else: ?>
                    <?php foreach($requests as $req): ?>
                        <div class="request-card">
                            <div class="request-header">
                                <h3><?= htmlspecialchars($req['subject']) ?></h3>
                                <span class="urgency-badge"><?= htmlspecialchars($req['urgency']) ?> Priority</span>
                            </div>
                            <div style="color:var(--text-light); font-size:0.9rem; margin-bottom:1rem;">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($req['admin_name']) ?> &nbsp;&nbsp; 
                                <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($req['created_at'])) ?>
                            </div>
                            <div style="background:#f9fafb; padding:1rem; border-radius:8px; border:1px solid var(--border-color); margin-bottom:1rem; line-height:1.6;">
                                <?= nl2br(htmlspecialchars($req['message'])) ?>
                            </div>
                            
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                <button type="submit" name="accept_request" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Accept & Create Order
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <script>
        function showTab(tabId, navItem) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            // Remove active class from sidebar items
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            // Highlight nav item
            if(navItem) navItem.classList.add('active');
            
            // If mobile, close sidebar after selection
            if(window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Initialize first tab (done by default via HTML classes, but fail-safe here)
        document.addEventListener('DOMContentLoaded', () => {
           // Ensure at least dashboard is active if nothing else is
           if(!document.querySelector('.tab-content.active')) {
               showTab('dashboard', document.querySelector('.nav-item'));
           }
        });
    </script>
</body>
</html>
