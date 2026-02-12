<?php
require_once 'conn.php';

// Check if logged in as supplier
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    die("access denied: please log in as a supplier first.");
}

$supplier_id = $_SESSION['user_id'];

// List of 15 sample construction products
$products = [
    ['UltraTech Portland Cement (50kg)', 'Cement', 380.00, 500],
    ['Kamdhenu TMT Steel Bars (12mm)', 'Steel', 5500.00, 200],
    ['Red Clay Bricks (Class A, 1000 pcs)', 'Bricks', 8000.00, 50],
    ['River Sand (Truck Load)', 'Sand', 12000.00, 20],
    ['20mm Construction Aggregate (Ton)', 'Aggregates', 1500.00, 100],
    ['Asian Paints Apex Ultima (20L)', 'Paint', 4500.00, 30],
    ['Dr. Fixit Waterproofing Compound (5L)', 'Chemicals', 1200.00, 80],
    ['Kajaria Vitrified Floor Tiles (Box)', 'Flooring', 850.00, 150],
    ['Teak Wood Door Frame (Standard)', 'Wood', 8500.00, 25],
    ['Finolex PVC Pipes (1-inch, 6m)', 'Plumbing', 350.00, 300],
    ['Havells Electrical Wire (2.5mm, 90m)', 'Electrical', 1800.00, 100],
    ['Birla White Wall Putty (40kg)', 'Wall Care', 750.00, 200],
    ['Greenply Plywood (18mm, 8x4)', 'Wood', 3200.00, 60],
    ['Jaguar Bath Fittings Set (Chrome)', 'Plumbing', 15000.00, 10],
    ['Philips LED Downlights (12W, Pack of 6)', 'Electrical', 2400.00, 50]
];

// Placeholder image URL service
$placeholder_service = "https://placehold.co/600x400/e2e8f0/64748b?text=";

echo "<h2>Seeding Products for Supplier ID: $supplier_id</h2>";
echo "<ul>";

$stmt = $conn->prepare("INSERT INTO products (supplier_id, name, category, price, stock_quantity, image_path) VALUES (?, ?, ?, ?, ?, ?)");

foreach ($products as $prod) {
    $name = $prod[0];
    $cat = $prod[1];
    $price = $prod[2];
    $stock = $prod[3];
    // Create a safe URL-encoded name for the placeholder image text
    $img_text = urlencode($name);
    $image_path = $placeholder_service . $img_text;

    $stmt->bind_param("issdis", $supplier_id, $name, $cat, $price, $stock, $image_path);
    
    if ($stmt->execute()) {
        echo "<li style='color:green'>Added: $name</li>";
    } else {
        echo "<li style='color:red'>Failed to add: $name - " . $stmt->error . "</li>";
    }
}

$stmt->close();
$conn->close();

echo "</ul>";
echo "<p><strong>Done!</strong> <a href='supplier_dashboard.php'>Go back to Dashboard</a></p>";
?>
