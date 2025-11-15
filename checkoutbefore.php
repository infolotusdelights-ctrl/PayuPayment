<?php
require_once 'functions.php';
if(!is_logged_in()){
  header("Location: login.php?redirect=checkout.php");
  exit;
}
$cart = $_SESSION['cart'] ?? [];
if(empty($cart)){
  header("Location: cart.php");
  exit;
}
$total = 0; 
foreach($cart as $c) $total += $c['price']*$c['qty'];
?>
<!doctype html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout - Lotus Delight</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0; padding: 0;
      background: #fafafa;
    }
    .container {
      max-width: 600px;
      margin: 20px auto;
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h2, h3 {
      margin-bottom: 15px;
      color: #333;
    }
    label {
      display: block;
      margin: 12px 0 5px;
      font-size: 14px;
      color: #444;
    }
    input[type="text"], 
    input[type="email"], 
    textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
    }
    textarea {
      min-height: 80px;
    }
    .error {
      color: red;
      font-size: 12px;
      display: none;
    }
    input.error-field {
      border: 1px solid red !important;
      background: #ffecec;
    }
    .payment-options {
      margin: 15px 0;
    }
    .payment-options label {
      display: flex;
      align-items: center;
      font-size: 14px;
      margin-bottom: 8px;
      cursor: pointer;
    }
    .payment-options input {
      margin-right: 8px;
    }
    .btn {
      display: inline-block;
      background: #28a745;
      color: #fff;
      padding: 12px 20px;
      border: none;
      border-radius: 6px;
      font-size: 16px;
      cursor: pointer;
      margin-top: 15px;
      text-align: center;
      width: 100%;
    }
    .btn:hover {
      background: #218838;
    }
    .top-buttons {
      display: flex;
      gap: 10px;
      margin-bottom: 15px;
      flex-wrap: wrap;
    }
    .top-buttons a {
      flex: 1;
      text-align: center;
      padding: 10px 0;
      border-radius: 6px;
      background: #2a7a4b;
      color: #fff;
      text-decoration: none;
    }
    .top-buttons a:hover {
      background: #24683f;
    }
    p {
      font-weight: bold;
      font-size: 16px;
      margin: 15px 0;
    }
    @media (max-width: 600px) {
      .container {
        margin: 10px;
        padding: 15px;
      }
      h2, h3 {
        font-size: 18px;
      }
      label {
        font-size: 13px;
      }
      input, textarea {
        font-size: 13px;
      }
      .btn {
        font-size: 15px;
        padding: 10px;
      }
    }
  </style>
</head>
<body>
<?php include 'index_header_snippet.php'; ?>

<div class="container">
  <div class="top-buttons">
    <a href="cart.php">← Back to Cart</a>
    <a href="index.php">🏠 Home</a>
  </div>

  <h2>Checkout</h2>
  <form id="checkoutForm" method="post" novalidate>
    <h3>Address</h3>
    <label>Name</label>
    <input type="text" name="name" id="name" required>
    <div class="error" id="nameError">Please enter your name</div>

    <label>Mobile</label>
    <input type="text" name="mobile" id="mobile" required maxlength="10">
    <div class="error" id="mobileError">Enter a valid 10-digit mobile number</div>

    <label>Email</label>
    <input type="email" name="email" id="email" required>
    <div class="error" id="emailError">Enter a valid email</div>

    <label>Address</label>
    <textarea name="address" id="address" required></textarea>
    <div class="error" id="addressError">Address cannot be empty</div>

    <label>Pincode</label>
    <input type="text" name="pincode" id="pincode" required maxlength="6">
    <div class="error" id="pincodeError">Enter a valid 6-digit pincode</div>

    <label>Landmark</label>
    <input type="text" name="landmark">

    <h3>Payment</h3>
    <div class="payment-options">
      <label><input type="radio" name="payment_mode" value="COD" checked> Cash on Delivery (COD)</label>
      <label><input type="radio" name="payment_mode" value="ONLINE"> Online Payment (via Instamojo)</label>
    </div>

    <p>Amount Payable: ₹<?php echo $total; ?></p>

    <input type="hidden" name="amount" value="<?php echo $total; ?>">

    <button type="submit" class="btn" id="placeOrderBtn">Place Order</button>
  </form>
</div>

<?php include 'footer_snippet.php'; ?>

<script>
  const form = document.getElementById('checkoutForm');
  const nameField = document.getElementById('name');
  const mobileField = document.getElementById('mobile');
  const emailField = document.getElementById('email');
  const addressField = document.getElementById('address');
  const pincodeField = document.getElementById('pincode');

  const showError = (field, errorId, condition) => {
    document.getElementById(errorId).style.display = condition ? "block" : "none";
    field.classList.toggle("error-field", condition);
  };

  nameField.addEventListener("input", () => {
    showError(nameField, "nameError", nameField.value.trim().length < 2);
  });

  mobileField.addEventListener("input", () => {
    const valid = /^[6-9]\d{9}$/.test(mobileField.value.trim());
    showError(mobileField, "mobileError", !valid);
  });

  emailField.addEventListener("input", () => {
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value.trim());
    showError(emailField, "emailError", !valid);
  });

  addressField.addEventListener("input", () => {
    showError(addressField, "addressError", addressField.value.trim().length < 5);
  });

  pincodeField.addEventListener("input", () => {
    const valid = /^\d{6}$/.test(pincodeField.value.trim());
    showError(pincodeField, "pincodeError", !valid);
  });

  // Change form target based on payment mode
  form.addEventListener('submit', function(e){
    const mode = document.querySelector("input[name=payment_mode]:checked").value;
    if (mode === "ONLINE") {
      form.action = "instamojo_pay.php";
    } else {
      form.action = "place_order.php";
    }

    if (document.querySelectorAll(".error[style*='block']").length > 0) {
      e.preventDefault();
      alert("Please fix the errors before submitting.");
    }
  });
</script>
</body>
</html>
