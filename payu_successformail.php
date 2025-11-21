<?php
// payu_success.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'db.php';        // $pdo
require_once 'functions.php'; // current_user_id() etc

// PAYU credentials
$merchant_key  = "GFPg4Y";
$merchant_salt = "BRkRjAIEwffCDgnwkCKzZDExLOrTPqBw";

// read POST
$post = $_POST;
$status = $post['status'] ?? '';
$txnid  = $post['txnid'] ?? '';      // this should match order_number we created
$posted_hash = $post['hash'] ?? '';
$mihpayid = $post['mihpayid'] ?? ''; // PayU payment id
$amount = $post['amount'] ?? '';
$productinfo = $post['productinfo'] ?? '';
$firstname = $post['firstname'] ?? '';
$email = $post['email'] ?? '';

// Reconstruct hash for verification
// If additionalCharges are present, sequence is:
// additionalCharges|salt|status|||||||||||email|firstname|productinfo|amount|txnid|key
// Otherwise:
// salt|status|||||||||||email|firstname|productinfo|amount|txnid|key

$additionalCharges = isset($post['additionalCharges']) ? $post['additionalCharges'] : '';

if($additionalCharges) {
    $retHashSeq = $additionalCharges . '|' . $merchant_salt . '|' . $status . '|||||||||||' 
                 . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $merchant_key;
} else {
    $retHashSeq = $merchant_salt . '|' . $status . '|||||||||||' 
                 . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $merchant_key;
}

$calculated_hash = strtolower(hash("sha512", $retHashSeq));

// Debug logger helper
function payu_log($data){
    $file = __DIR__ . '/payu_debug.log';
    @file_put_contents($file, date('Y-m-d H:i:s') . " - " . print_r($data, true) . "\n\n", FILE_APPEND);
}

// If hash mismatch or status not success => failure
if($calculated_hash !== $posted_hash || strtolower($status) !== 'success') {
    // Log full POST for troubleshooting
    payu_log([
        'reason' => 'hash_mismatch_or_failed_status',
        'posted_hash' => $posted_hash,
        'calculated_hash' => $calculated_hash,
        'status' => $status,
        'post' => $post
    ]);

    // optional: update order with failed status if the txn exists
    if($txnid){
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Pending' WHERE order_number = ?");
        $stmt->execute([$txnid]);
    }

    // redirect to failure page
    header("Location: /order_failed.php");
    exit;
}

// Successful and verified: update DB
try {
    // Update order: set transaction_id and status
    $stmt = $pdo->prepare("UPDATE orders SET status = 'Confirmed', transaction_id = ?, created_at = created_at WHERE order_number = ?");
    $stmt->execute([$mihpayid, $txnid]);

    // Fetch order details to email / display
    $stmt = $pdo->prepare("SELECT o.*, u.name AS uname, u.email AS uemail FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.order_number = ?");
    $stmt->execute([$txnid]);
    $order = $stmt->fetch();

    // send emails (simple)
    if($order){
        $customer_email = $order['address_email'] ?: $order['uemail'];
        $customer_name = $order['address_name'] ?: $order['uname'];
        $amount = $order['amount'];
        $products = json_decode($order['products_json'], true);

        $itemsHtml = '';
        foreach($products as $p){
            $itemsHtml .= "<tr><td style='padding:8px;border:1px solid #ddd;'>{$p['name']}</td><td style='padding:8px;border:1px solid #ddd;'>{$p['qty']}</td><td style='padding:8px;border:1px solid #ddd;'>₹{$p['price']}</td></tr>";
        }

        $headers  = "From: Lotus Delight <no-reply@lotusdelight.in>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $subject_cust = "Your Lotus Delight Order #{$txnid} is Confirmed";
        $message_cust = "
        <div style='font-family:Arial,sans-serif; padding:20px;'>
          <div style='max-width:600px;margin:auto;background:#fff;padding:20px;border-radius:8px;'>
            <div style='text-align:center;'><img src='https://{$_SERVER['HTTP_HOST']}/assets/logo.png' style='height:70px;'></div>
            <h2 style='color:#28a745;'>Order Confirmed</h2>
            <p>Hi <strong>".htmlspecialchars($customer_name)."</strong>,</p>
            <p>Your payment was successful. Here are the details:</p>
            <p><b>Order No:</b> {$txnid}<br><b>Payment ID:</b> {$mihpayid}<br><b>Amount:</b> ₹{$amount}</p>
            <table style='width:100%;border-collapse:collapse;margin-top:10px;'> 
              <tr style='background:#28a745;color:#fff;'>
                <th style='padding:8px;border:1px solid #ddd;'>Product</th><th style='padding:8px;border:1px solid #ddd;'>Qty</th><th style='padding:8px;border:1px solid #ddd;'>Price</th>
              </tr>
              {$itemsHtml}
            </table>
            <p style='margin-top:15px;'>Thanks for shopping with Lotus Delight.</p>
          </div>
        </div>";
        @mail($customer_email, $subject_cust, $message_cust, $headers);

        // admin
        @mail("info.lotusdelights@gmail.com", "New Order - {$txnid}", $message_cust, $headers);
    }

} catch(Exception $e){
    payu_log(['error_updating_db' => $e->getMessage(), 'post' => $post]);
    // if DB update failed, redirect to failure (or show error)
    header("Location: /order_failed.php");
    exit;
}

// Show success page
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Order Success</title>
  <style>
    body{font-family:Arial; background:#fff; margin:0; padding:40px; text-align:center;}
    .box{max-width:600px;margin:auto;background:#f9f9f9;padding:30px;border-radius:8px;}
    .btn{display:inline-block;padding:12px 20px;background:#28a745;color:#fff;border-radius:8px;text-decoration:none;}
  </style>
</head>
<body>
  <div class="box">
    <h2>✅ Payment Successful</h2>
    <p>Your order <strong><?php echo htmlspecialchars($txnid); ?></strong> has been placed successfully.</p>
    <p><strong>Payment ID:</strong> <?php echo htmlspecialchars($mihpayid); ?></p>
    <p><a href="/index.php" class="btn">Return to Home</a> &nbsp; <a href="/myorders.php" class="btn">View My Orders</a></p>
  </div>
</body>
</html>
