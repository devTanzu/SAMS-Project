<?php
require 'db.php';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        // For demo: just show a reset link (in real app, send email)
        $message = "Reset link: <a href='reset_password.php?email=" . urlencode($email) . "'>Reset Password</a>";
    } else {
        $message = "No user found with that email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password - Attendance System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <style>
    body {
      background-color: #181f2a;
      color: #f4f6fb;
      font-family: 'Segoe UI', sans-serif;
    }
    .forgot-container {
      max-width: 400px;
      margin: 80px auto;
      padding: 32px 28px;
      background-color: #232b3e;
      border-radius: 12px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.25);
      border: 1px solid #313a4d;
     
    }
    h2 {
      text-align: center;
      margin-bottom: 20px;
    }
    .form-control {
      background-color: #232b3e;
      color: #f4f6fb;
      border: 1px solid #313a4d;
      border-radius: 12px;
      padding: 12px;
    }
    .form-control:focus {
      border-color: #8f5aff;
      box-shadow: 0 0 0 0.15rem rgba(143, 90, 255, 0.25);
    }
    .btn-reset {
      width: 100%;
      padding: 12px;
      background-color: #8f5aff;
      color: #fff;
      border: none;
      border-radius: 12px;
      font-weight: 600;
      margin-top: 10px;
    }
    .btn-reset:hover {
      background-color: #7a47e5;
    }
    .back-link {
      display: block;
      margin-top: 20px;
      text-align: center;
      color: #b0b8c1;
    }
    .back-link a {
      color: #8f5aff;
      text-decoration: none;
    }
    .back-link a:hover {
      color: #7a47e5;
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="forgot-container">
    <h2>Forgot Password</h2>

    <?php if ($message) echo "<p>$message</p>"; ?>

    <form method="post">
      <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" id="email" name="email" class="form-control" required />
      </div>
      <button type="submit" class="btn btn-reset">Send Reset Link</button>
    </form>
    <div class="back-link">
      <a href="login.php">← Back to Login</a>
    </div>
  </div>
</body>
</html>
