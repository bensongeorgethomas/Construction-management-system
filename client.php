<?php
require_once 'conn.php';

if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'conn.php';

$client_id = $_SESSION['client_id'];
$client_name = $_SESSION['client_name'];

$sql = "SELECT * FROM client WHERE client_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$client_data = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Client Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; margin: 0; padding: 0; }
        .header { background: #007bff; color: white; padding: 15px; text-align: center; }
        .container { padding: 20px; max-width: 600px; margin: auto; background: white; border-radius: 10px; margin-top: 30px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; }
        .logout-btn { background: #dc3545; color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer; }
        .logout-btn:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome, <?php echo htmlspecialchars($client_name); ?>!</h1>
    </div>
    <div class="container">
        <h2>Client Details</h2>
        <p><strong>Client ID:</strong> <?php echo htmlspecialchars($client_data['client_id']); ?></p>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($client_data['name']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($client_data['email']); ?></p>
        <p><strong>Mobile Number:</strong> <?php echo htmlspecialchars($client_data['mobilenumber']); ?></p>
        <p><strong>Gender:</strong> <?php echo htmlspecialchars($client_data['gender']); ?></p>
        <p><strong>Address:</strong> <?php echo htmlspecialchars($client_data['address']); ?></p>
        <form method="post">
            <?= csrfField() ?>
            <button type="submit" name="logout" class="logout-btn">Logout</button>
        </form>
    </div>

<?php
if (isset($_POST['logout'])) {
    require_once 'includes/csrf.php';
    requireCSRF();
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
</body>
</html>