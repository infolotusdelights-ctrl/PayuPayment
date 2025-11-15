<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'functions.php';
require_once 'db.php';  // make sure $pdo is available

// Instamojo credentials
$api_key    = "e556b2618691052e4837d0b81a8f3305";
$auth_token = "c8e6b59934dd816127dcdcf965d9e6c3";
$endpoint   = "https://www.instamojo.com/api/1.1/";

// Customer data from checkout form
$name    = trim($_POST['name']);
$email   = trim($_POST['email']);
$phone   = trim($_POST['mobile']);
$address = trim($_POST['address']);
$pincode = trim($_POST['pincode']);
$landmark= trim($_POST['landmark']);
$amount  = floatval($_POST['amount']);

// --- Generate Order Number ---
$order_number = generate_order_number();

// Get user_id if logged in
$user_id = current_user_id();

// Get cart
$cart = $_SESSION['cart'] ?? [];
if(empty($cart)){
    die("Cart is empty!");
}
$products_json = json_encode(array_values($cart));

// Insert into orders table as Pending
$stmt = $pdo->prepare("INSERT INTO orders 
    (user_id, order_number, products_json, amount, payment_mode, status, 
     address_name, address_mobile, address_email, address_line, address_pincode, address_landmark, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt->execute([
    $user_id,
    $order_number,
    $products_json,
    $amount,
    "ONLINE",
    "Pending Payment",
    $name,
    $phone,
    $email,
    $address,
    $pincode,
    $landmark
]);

// --- Create Instamojo payment request ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $endpoint . "payment-requests/");
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER,
    array("X-Api-Key:$api_key", "X-Auth-Token:$auth_token"));

$payload = array(
    'purpose' => 'Lotus Delight Order Payment',
    'amount' => $amount,
    'phone' => $phone,
    'buyer_name' => $name,
    'redirect_url' => 'https://lotusdelight.in/order_success.php?order=' . urlencode($order_number),
    'send_email' => true,
    'send_sms' => true,
    'email' => $email,
    'allow_repeated_payments' => false
);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
$response = curl_exec($ch);
curl_close($ch);
$response = json_decode($response, true);

if ($response['success']) {
    // Clear cart since order is already created
    unset($_SESSION['cart']);
    // Redirect to Instamojo payment page
    header('Location: ' . $response['payment_request']['longurl']);
    exit;
} else {
    echo "<h2>Payment initiation failed. Try again later.</h2>";
    echo "<pre>";
    print_r($response);
    echo "</pre>";
}
?>
