<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'functions.php';
require_once 'db.php'; // ensure DB connection ($pdo)

// Capture parameters from Instamojo redirect
$order_number = $_GET['order'] ?? '';
$payment_id   = $_GET['payment_id'] ?? '';
$payment_req  = $_GET['payment_request_id'] ?? '';

// Instamojo API keys
$api_key    = "e556b2618691052e4837d0b81a8f3305";
$auth_token = "c8e6b59934dd816127dcdcf965d9e6c3";

// Verify payment with Instamojo
if ($payment_id && $payment_req) {
    $verify_url = "https://www.instamojo.com/api/1.1/payments/$payment_id/";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verify_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Api-Key:$api_key",
        "X-Auth-Token:$auth_token"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);

    if (!empty($json['success']) && $json['success'] === true) {
        $payment_status = $json['payment']['status'];

        if ($payment_status === 'Credit' && $order_number) {
            // Update order record
            $stmt = $pdo->prepare("UPDATE orders 
                SET status='Confirmed', payment_id=?, payment_verified=1 
                WHERE order_number=?");
            $stmt->execute([$payment_id, $order_number]);

            // Fetch order + user details
            $stmt = $pdo->prepare("SELECT o.*, u.email, u.name 
                                   FROM orders o 
                                   LEFT JOIN users u ON o.user_id = u.id 
                                   WHERE o.order_number=?");
            $stmt->execute([$order_number]);
            $order = $stmt->fetch();

            if ($order) {
                $customer_email = $order['email'];
                $customer_name  = $order['name'];
                $amount         = $order['amount'];
                $products       = json_decode($order['products_json'], true);

                $itemsList = "";
                foreach ($products as $p) {
                    $itemsList .= $p['name']." x ".$p['qty']." (₹".$p['price'].")\n";
                }

                // --- Send Email to Customer ---
               // --- Prepare common headers ---
//$headers = "MIME-Version: 1.0" . "\r\n";
//$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
//$headers .= "From: Lotus Delight <no-reply@lotusdelight.in>\r\n";
$headers  = "From: Lotus Delight <no-reply@lotusdelight.in>\r\n";
$headers .= "Reply-To: info.lotusdelights@gmail.com\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
// --- Customer Email ---
$subject_cust = "Your Lotus Delight Order #$order_number is Confirmed";
$message_cust = "
<div style='font-family:Arial,sans-serif; background:#f9f9f9; padding:20px;'>
  <div style='max-width:600px; margin:auto; background:#fff; border-radius:8px; padding:20px; box-shadow:0 0 8px rgba(0,0,0,0.1)'>
    <div style='text-align:center; margin-bottom:20px;'>
      <img src='https://lotusdelight.in/assets/logo.png' alt='Lotus Delight' style='height:70px;'>
    </div>
    <h2 style='color:#28a745; text-align:center;'>✅ Order Confirmed</h2>
    <p>Hi <strong>$customer_name</strong>,</p>
    <p>Thank you for shopping with <b>Lotus Delight</b>! Your order has been successfully placed.</p>
    <p><b>Order Number:</b> $order_number <br>
       <b>Payment ID:</b> $payment_id <br>
       <b>Total Amount:</b> ₹$amount</p>
    $itemsTable
    <p style='margin-top:20px;'>We will notify you once your order is shipped.</p>
    <p style='text-align:center;'>
      <a href='https://lotusdelight.in' style='background:#28a745; color:#fff; padding:10px 20px; border-radius:5px; text-decoration:none;'>Visit Our Store</a>
    </p>
    <p style='font-size:13px; color:#555; text-align:center;'>Lotus Delight • Healthy Crunch, Pure Delight</p>
  </div>
</div>";

@mail($customer_email, $subject_cust, $message_cust, $headers);

// --- Admin Email ---
$subject_admin = "New Order Received - #$order_number";
$message_admin = "
<div style='font-family:Arial,sans-serif; background:#f9f9f9; padding:20px;'>
  <div style='max-width:600px; margin:auto; background:#fff; border-radius:8px; padding:20px; box-shadow:0 0 8px rgba(0,0,0,0.1)'>
    <div style='text-align:center; margin-bottom:20px;'>
      <img src='https://lotusdelight.in/assets/logo.png' alt='Lotus Delight' style='height:70px;'>
    </div>
    <h2 style='color:#d9534f; text-align:center;'>📦 New Order Alert</h2>
    <p>A new order has been placed on Lotus Delight.</p>
    <p><b>Order Number:</b> $order_number <br>
       <b>Customer Name:</b> $customer_name <br>
       <b>Email:</b> $customer_email <br>
       <b>Total Amount:</b> ₹$amount <br>
       <b>Payment ID:</b> $payment_id</p>
    $itemsTable
    <p style='font-size:13px; color:#555; text-align:center;'>Lotus Delight Admin Panel</p>
  </div>
</div>";

@mail("info.lotusdelights@gmail.com", $subject_admin, $message_admin, $headers);
}
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Order Success</title>
  <style>
    body {
      margin:0;
      padding:0;
      display:flex;
      justify-content:center;
      align-items:center;
      height:100vh;
      font-family:Arial, sans-serif;
      background:#fff;
      text-align:center;
    }
    .container {
      max-width: 500px;
      background:#f9f9f9;
      padding:30px;
      border-radius:10px;
      box-shadow:0 0 15px rgba(0,0,0,0.1);
    }
    h2 {
      color:green;
      margin-bottom:15px;
    }
    p {
      font-size:16px;
      margin:8px 0;
    }
    .btn {
      background:#28a745; 
      color:#fff; 
      padding:12px 24px; 
      border-radius:8px; 
      text-decoration:none;
      display:inline-block;
      margin-top:15px;
    }
    .btn:hover { background:#218838; }
    canvas {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 9999;
    }
  </style>
</head>
<body>
  <canvas id="confetti"></canvas>
  <div class="container">
      <h2>✅ Your order has been placed successfully!</h2>
      <p>Thank you for ordering with Lotus Delight.</p>
      <p><strong>Order No:</strong> <?= htmlspecialchars($order_number) ?></p>
      <?php if($payment_id): ?>
        <p><strong>Payment ID:</strong> <?= htmlspecialchars($payment_id) ?></p>
      <?php endif; ?>
      <a href="index.php" class="btn">🏠 Go to Home</a>
  </div>

  <script>
  // Simple Confetti Animation for 2 seconds
  const canvas = document.getElementById('confetti');
  const ctx = canvas.getContext('2d');
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;

  const pieces = [];
  const numPieces = 100;

  for (let i = 0; i < numPieces; i++) {
    pieces.push({
      x: Math.random() * canvas.width,
      y: Math.random() * canvas.height - canvas.height,
      size: Math.random() * 6 + 4,
      speed: Math.random() * 3 + 2,
      color: `hsl(${Math.random() * 360}, 100%, 50%)`
    });
  }

  function update() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    for (let p of pieces) {
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.size, 0, 2 * Math.PI);
      ctx.fillStyle = p.color;
      ctx.fill();
      p.y += p.speed;
      if (p.y > canvas.height) {
        p.y = -10;
        p.x = Math.random() * canvas.width;
      }
    }
  }

  let duration = 10000; // 10 minutes
  let end = Date.now() + duration;
  function animate() {
    if (Date.now() < end) {
      update();
      requestAnimationFrame(animate);
    } else {
      ctx.clearRect(0,0,canvas.width,canvas.height); // stop confetti
    }
  }
  animate();
  </script>
</body>
</html>
