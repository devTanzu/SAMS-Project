<?php
require 'db.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION['teacher_logged_in']) && $_SESSION['teacher_logged_in']) {
    header('Location: teacher-dashboard.php');
    exit;
}

$error = '';
$success = '';
$showRegister = isset($_GET['register']); // Show register form if ?register=1
$showReset = isset($_GET['reset']); // Show reset form if ?reset=1

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register'])) {
        // Registration logic
        $username = trim($_POST['username']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];

        if (empty($username) || empty($name) || empty($email) || empty($password) || empty($confirm)) {
            $error = "Please fill in all fields!";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match!";
        } else {
            // First check if email exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE email = ?");
            $stmt->execute([$email]);
            $emailExists = $stmt->fetchColumn();

            if ($emailExists) {
                $error = "This email address is already registered. Please use a different email.";
            } else {
                // Then check if username exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE username = ?");
                $stmt->execute([$username]);
                $usernameExists = $stmt->fetchColumn();

                if ($usernameExists) {
                    $error = "This username is already taken. Please choose a different username.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO teachers (name, email, username, password) VALUES (?, ?, ?, ?)");
                    if ($stmt->execute([$name, $email, $username, $hash])) {
                        $success = "Registration successful! You can now log in.";
                        $showRegister = false;
                    } else {
                        $error = "Registration failed!";
                    }
                }
            }
        }
    } else {
        // Login logic
        $userOrEmail = trim($_POST['user_or_email']);
        $password = $_POST['password'];

        if (empty($userOrEmail) || empty($password)) {
            $error = "Please fill in all fields!";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM teachers WHERE username = ? OR email = ?");
            $stmt->execute([$userOrEmail, $userOrEmail]);
            $teacher = $stmt->fetch();

            if ($teacher && password_verify($password, $teacher['password'])) {
                $_SESSION['teacher_logged_in'] = true;
                $_SESSION['teacher_id'] = $teacher['id'];
                $_SESSION['teacher_name'] = $teacher['name'];
                $_SESSION['teacher_email'] = $teacher['email'];
                header('Location: teacher-dashboard.php');
                exit;
            } else {
                $error = "Invalid username/email or password!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Login</title>
    <style>
        body { 
            background: #181f2a; 
            color: #f4f6fb; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .container { 
            max-width: 480px; 
            min-width: 350px;
            margin: 20px; 
            background: #232b3e; 
            padding: 48px 40px; 
            border-radius: 18px; 
            box-shadow: 0 4px 32px rgba(0,0,0,0.28); 
        }
        h2 { 
            color: #8f5aff; 
            margin-bottom: 32px; 
            text-align: center;
            font-size: 2rem;
        }
        .form-group { 
            margin-bottom: 1.7rem; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: #f4f6fb; 
            font-size: 1.08rem;
        }
        .form-group input { 
            width: 100%; 
            padding: 1rem; 
            background: #1e293b; 
            color: #f4f6fb; 
            border: 1px solid #444c5e; 
            border-radius: 7px; 
            font-size: 1.08rem;
        }
        .btn { 
            width: 100%; 
            padding: 1rem; 
            background: #8f5aff; 
            color: #fff; 
            border: none; 
            border-radius: 7px; 
            font-size: 1.1rem; 
            cursor: pointer; 
            margin-bottom: 1.2rem;
        }
        .btn:hover { 
            background: #7a47e5; 
        }
        .message { 
            padding: 0.8rem; 
            border-radius: 5px; 
            margin-bottom: 1rem; 
            text-align: center; 
        }
        .error { 
            background: #3b1a2b; 
            color: #ff6b81; 
        }
        .success { 
            background: #1a3b2b; 
            color: #28a745; 
        }
        .links {
            text-align: center;
            margin-top: 1.5rem;
        }
        .links a {
            color: #8f5aff;
            text-decoration: none;
            margin: 0 18px;
            font-size: 1.08rem;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($showRegister): ?>
            <h2>Teacher Registration</h2>
            <?php if ($error): ?>
                <div class="message error" style="margin-bottom: 1.5rem;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="register" value="1">
                <div class="form-group">
                    <label for="name" style="font-size:1.08rem;">Full Name</label>
                    <input type="text" id="name" name="name" required style="padding:1rem; font-size:1.08rem; min-width:100%;">
                </div>
                <div class="form-group">
                    <label for="email" style="font-size:1.08rem;">Email</label>
                    <input type="email" id="email" name="email" required style="padding:1rem; font-size:1.08rem; min-width:100%;">
                </div>
                <div class="form-group">
                    <label for="username" style="font-size:1.08rem;">Username</label>
                    <input type="text" id="username" name="username" required style="padding:1rem; font-size:1.08rem; min-width:100%;">
                </div>
                <div class="form-group">
                    <label for="password" style="font-size:1.08rem;">Password</label>
                    <input type="password" id="password" name="password" required style="padding:1rem; font-size:1.08rem; min-width:100%;">
                </div>
                <div class="form-group">
                    <label for="confirm_password" style="font-size:1.08rem;">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required style="padding:1rem; font-size:1.08rem; min-width:100%;">
                </div>
                <button type="submit" class="btn" style="padding:1rem; font-size:1.1rem;">Register</button>
            </form>
        <?php elseif ($showReset): ?>
            <h2>Reset Password</h2>
            <form method="post">
                <input type="hidden" name="reset" value="1">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <button type="submit" class="btn">Reset Password</button>
            </form>
        <?php else: ?>
            <h2>Teacher Login</h2>
            <form method="post">
                <div class="form-group">
                    <label for="user_or_email">Username or Email</label>
                    <input type="text" id="user_or_email" name="user_or_email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn">Login</button>
            </form>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message success"><?= $success ?></div>
        <?php endif; ?>

        <div class="links">
            <?php if ($showRegister): ?>
                <a href="teacher_login.php">Back to Login</a>
            <?php elseif ($showReset): ?>
                <a href="teacher_login.php">Back to Login</a>
            <?php else: ?>
                <a href="teacher_login.php?register=1">Register</a>
                <a href="reset_password.php">Forgot Password?</a>
            <?php endif; ?>
        </div>

       
    </div>
</body>
</html>