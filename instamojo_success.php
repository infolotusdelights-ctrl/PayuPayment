<?php
session_start();
require_once 'functions.php';

if (!isset($_GET['payment_id']) || !isset($_GET['payment_request_id']) || !isset($_GET['order'])) {
    die("Invalid response.");
}

$order_number = $_GET['order'];
$payment_id = $_GET['payment_id'];

// Update database
$stmt = $pdo->prepare("UPDATE orders SET status='paid', payment_id=? WHERE order_number=?");
$stmt->execute([$payment_id, $order_number]);

// Redirect to your success page
header("Location: https://lotusdelight.in/order_success.php?order=" . $order_number);
exit;
?>
