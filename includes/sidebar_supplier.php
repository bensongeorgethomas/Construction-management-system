<?php
// Determine current page for active state (simpler fallback if not dashboard)
$current_page = basename($_SERVER['PHP_SELF']);
$is_dashboard = isset($is_dashboard_page) && $is_dashboard_page === true;

// Helper to determine href and class
function getLink($target, $is_dashboard_page) {
    if ($is_dashboard_page) {
        return "javascript:void(0)";
    }
    // If not on dashboard, we might want to link back to dashboard with a query param to open active tab,
    // but for now let's just link to dashboard.php
    return "supplier_dashboard.php"; // Simplified: External pages link back to dashboard root
}

function getOnclick($tabName, $is_dashboard_page) {
    if ($is_dashboard_page) {
        return "onclick=\"showTab('$tabName', this)\"";
    }
    return "";
}

$dashboard_active = $is_dashboard ? 'active' : '';
$product_active = ''; // On dashboard usually handled by JS click
$orders_active = ($current_page == 'supplier_orders.php' || $current_page == 'view_order.php') ? 'active' : '';
$profile_active = ($current_page == 'supplier_profile.php') ? 'active' : '';

?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="supplier_dashboard.php" class="logo">Construct<span>.</span></a>
        <button class="menu-toggle" style="color:white; display:none;" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
    </div>
    
    <nav class="nav-links">
        <!-- Dashboard -->
        <a href="<?= $is_dashboard ? '#' : 'supplier_dashboard.php' ?>" 
           class="nav-item <?= $dashboard_active ?>" 
           <?= getOnclick('dashboard', $is_dashboard) ?>>
            <i class="fas fa-th-large"></i> Dashboard
        </a>

        <!-- My Products -->
        <a href="<?= $is_dashboard ? '#' : 'supplier_dashboard.php' ?>" 
           class="nav-item <?= $product_active ?>" 
           <?= getOnclick('products', $is_dashboard) ?>>
            <i class="fas fa-boxes"></i> My Products
        </a>

        <!-- Add Product -->
        <a href="<?= $is_dashboard ? '#' : 'supplier_dashboard.php' ?>" 
           class="nav-item" 
           <?= getOnclick('upload', $is_dashboard) ?>>
            <i class="fas fa-plus-circle"></i> Add Product
        </a>

        <!-- Requests -->
        <a href="<?= $is_dashboard ? '#' : 'supplier_dashboard.php' ?>" 
           class="nav-item" 
           <?= getOnclick('requests', $is_dashboard) ?>>
            <i class="fas fa-clipboard-list"></i> Requests 
            <?php if(isset($stats['pending_requests']) && $stats['pending_requests'] > 0): ?>
                <span class="badge"><?= $stats['pending_requests'] ?></span>
            <?php endif; ?>
        </a>

        <!-- Orders (Always a separate page) -->
        <a href="supplier_orders.php" class="nav-item <?= $orders_active ?>">
            <i class="fas fa-shopping-cart"></i> Orders 
            <?php if(isset($stats['pending_orders']) && $stats['pending_orders'] > 0): ?>
                <span class="badge"><?= $stats['pending_orders'] ?></span>
            <?php endif; ?>
        </a>

        <!-- Profile -->
        <a href="supplier_profile.php" class="nav-item <?= $profile_active ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
    </nav>
    
    <div class="user-profile">
        <div class="user-avatar"><?= strtoupper(substr($supplier_name ?? 'U', 0, 1)) ?></div>
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($supplier_name ?? 'User') ?></div>
            <div class="user-role">Supplier</div>
        </div>
        <a href="logout.php" class="btn-logout" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</aside>
