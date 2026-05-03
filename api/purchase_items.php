<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT pi.*, m.name med_name, m.unit FROM purchase_items pi JOIN medicines m ON pi.medicine_id=m.id WHERE pi.purchase_id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($items);
?>
