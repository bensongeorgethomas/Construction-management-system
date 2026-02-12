<?php
require_once 'conn.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['client_id'])) {
    echo json_encode([]);
    exit();
}

$client_id = intval($_GET['client_id']);
$projects = $conn->query("SELECT id, name FROM projects WHERE client_id = $client_id AND deleted_at IS NULL ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

echo json_encode($projects);
?>