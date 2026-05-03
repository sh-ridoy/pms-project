<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) { echo json_encode([]); exit; }

$q = "%$q%";
$stmt = $conn->prepare("SELECT id, name, generic_name, sale_price, stock_qty, unit FROM medicines WHERE (name LIKE ? OR generic_name LIKE ?) AND status='active' AND stock_qty > 0 ORDER BY name LIMIT 10");
$stmt->bind_param('ss', $q, $q);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($results);
?>
