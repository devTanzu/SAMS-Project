<?php
session_start();
require 'db.php'; // Make sure you have your PDO connection here

$error = '';
$showRegister = false;

// Check if admin exists
$stmt = $pdo->query("SELECT * FROM admin_users LIMIT 1");
$admin = $stmt->fetch();
if (!$admin) {
    $showRegister = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register'])) {
        // Registration logic
        $user = trim($_POST['username']);
        $email = trim($_POST['email']);
        $pass = $_POST['password'];
        if (!$user || !$email || !$pass) {
            $error = 'All fields are required!';
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address!';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO admin_users (username, email, password) VALUES (?, ?, ?)");
            $insert->execute([$user, $email, $hash]);
            $_SESSION['admin_logged_in'] = true;
            header('Location: dashboard.php');
            exit;
        }
    } else if (isset($_POST['reset'])) {
        // Password reset logic - REMOVED, handled by reset_password.php
        // (No code here)
    } else {
        // Login logic
        $userOrEmail = trim($_POST['login_user']);
        $pass = $_POST['password'];
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$userOrEmail, $userOrEmail]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($pass, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid credentials!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin Login</title>
    <style>
        body { background: #0f172a; color: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .login-container {
            max-width: 400px; margin: 100px auto; background: #1e293b;
            padding: 32px 28px; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.25);
        }
        h2 { color: #7a47e5; text-align: center; }
        .form-label { font-weight: 500; color: #f1f5f9; }
        .form-control {
            width: 100%; padding: 12px; margin-bottom: 15px;
            border-radius: 8px; border: none; background: #334155; color: #fff;
        }
        .btn-login {
            width: 100%; padding: 12px; font-size: 16px; font-weight: 600;
            background: #7a47e5; color: #fff; border: none; border-radius: 8px;
            transition: background 0.2s, transform 0.2s;
        }
        .btn-login:hover { background: #5a32b3; transform: translateY(-2px); }
        .error { color: #ff6b6b; text-align: center; }
    </style>
</head>
<body>
    <?php if ($showRegister): ?>
    <div class="login-container" id="registerContainer">
        <h2>Admin Registration</h2>
        <?php if ($error) echo "<p class='error'>$error</p>"; ?>
        <form method="post">
            <input type="hidden" name="register" value="1">
            <label class="form-label" for="username">Username</label>
            <input type="text" class="form-control" id="username" name="username" value="tanjina" required>
            <label class="form-label" for="email">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="tanz.akter@gmail.com" required>
            <label class="form-label" for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" value="Tanjina@1" required>
            <button type="submit" class="btn-login">Register</button>
        </form>
    </div>
    <?php else: ?>
    <div class="login-container" id="loginContainer">
        <h2>Admin Login</h2>
        <?php if ($error && !isset($_POST['reset'])) echo "<p class='error'>$error</p>"; ?>
        <form method="post">
            <label class="form-label" for="login_user">Username or Email</label>
            <input type="text" class="form-control" id="login_user" name="login_user" required>
            <label class="form-label" for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
            <button type="submit" class="btn-login">Login</button>
        </form>
        <div style="text-align:center; margin-top:1rem;">
            <a href="reset_password.php" style="color:#7a47e5; text-decoration:underline; font-weight:500;">Forgot Password?</a>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
