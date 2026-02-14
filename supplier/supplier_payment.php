<?php
require_once '../conn.php';

// Check if Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// --- CONFIGURATION: ENTER YOUR DETAILS HERE ---
// 1. Open your GPay/PhonePe/Paytm.
// 2. Copy your UPI ID (e.g., mobile-number@okaxis or yourname@oksbi).
// 3. Paste it below.
$my_upi_id = "bensong2468@okicici"; // <--- PASTE YOUR UPI ID HERE
$my_name   = "Construct Admin";   // <--- YOUR NAME OR BUSINESS NAME

// Get Payment Details from URL
// Get Payment Details
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
$supplier = filter_input(INPUT_GET, 'supplier', FILTER_SANITIZE_STRING) ?? 'Supplier';

// Fetch verifiable amount from database
require_once '../conn.php';

$amount = 0;
if ($order_id) {
    $stmt = $conn->prepare("SELECT total_amount FROM supplier_orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $amount = $row['total_amount'];
    }
    $stmt->close();
} else {
    die("Invalid Order ID.");
}

// Basic validation
if ($amount <= 0 || empty($my_upi_id)) {
    die("Invalid Request or Missing UPI Configuration.");
}

// Generate the UPI Link
// Format: upi://pay?pa=UPI_ID&pn=NAME&am=AMOUNT&tn=NOTE&cu=INR
$transaction_note = "Payment for Order #$order_id";
$upi_link = "upi://pay?pa=" . $my_upi_id . "&pn=" . urlencode($my_name) . "&am=" . $amount . "&tn=" . urlencode($transaction_note) . "&cu=INR";

// Generate QR Code URL (Using a public QR API for simplicity)
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($upi_link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Supplier</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .payment-card { background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); text-align: center; max-width: 400px; width: 100%; }
        h1 { font-size: 1.5rem; color: #111827; margin-bottom: 0.5rem; }
        .amount { font-size: 2.5rem; font-weight: 800; color: #111827; margin: 1rem 0; }
        .upi-logo { height: 30px; margin-bottom: 1rem; }
        .qr-container { background: #fff; padding: 10px; border: 1px solid #e5e7eb; border-radius: 12px; display: inline-block; margin-bottom: 1.5rem; }
        .qr-container img { display: block; width: 100%; max-width: 250px; }
        .details { text-align: left; background: #f9fafb; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; color: #4b5563; }
        .details div { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .details div:last-child { margin-bottom: 0; font-weight: 600; color: #111827; }
        .btn-back { display: inline-block; text-decoration: none; color: #6b7280; font-weight: 500; }
        .btn-back:hover { color: #111827; }
        .note { font-size: 0.8rem; color: #ef4444; margin-top: 10px; }
    </style>
</head>
<body>

    <div class="payment-card">
        <h1>Scan to Pay</h1>
        <p style="color: #6b7280;">Use GPay, PhonePe, or Paytm</p>
        
        <div class="amount">₹<?= number_format($amount, 2) ?></div>

        <div class="qr-container">
            <img src="<?= $qr_url ?>" alt="Payment QR Code">
        </div>

        <div class="details">
            <div><span>To:</span> <span><?= htmlspecialchars($supplier) ?></span></div>
            <div><span>Order ID:</span> <span>#<?= $order_id ?></span></div>
            <div><span>UPI ID:</span> <span><?= htmlspecialchars($my_upi_id) ?></span></div>
        </div>

        <a href="admin_orders.php" class="btn-back">&larr; Go Back</a>
        
        <p class="note">Note: This generates a payment link. Please verify the transaction in your GPay app.</p>
    </div>

</body>
</html>
