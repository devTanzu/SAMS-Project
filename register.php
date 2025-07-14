<?php
$success = '';
$error = '';
$show_register_form = true;
$show_login_form = false;
require 'db.php'; // include your DB connection
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['fullName']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO teachers (name, email, username, password) VALUES (?, ?, ?, ?)");
    try {
        $stmt->execute([$fullName, $email, $username, $password]);
        $success = "Registration successful! Please log in below.";
        $show_register_form = false;
        $show_login_form = true;
    } catch (PDOException $e) {
        // Handle duplicate username/email gracefully
        if ($e->getCode() == 23000) {
            $error = "Username or email already exists. Please use a different one.";
        } else {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-bg: #181f2a;
            --secondary-bg: #232b3e;
            --container-bg: #232b3e;
            --accent: #8f5aff;
            --accent-hover: #7a47e5;
            --text-main: #f4f6fb;
            --text-muted: #b0b8c1;
            --input-bg: #232b3e;
            --input-border: #313a4d;
            --input-focus: #8f5aff;
            --border-radius: 12px;
        }
        body {
            background-color: var(--primary-bg);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .register-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 32px 28px;
            background-color: var(--container-bg);
            border-radius: var(--border-radius);
            box-shadow: 0 4px 24px rgba(0,0,0,0.25);
            border: 1px solid var(--input-border);
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-header h2 {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-main);
        }
        .register-header p {
            color: var(--text-muted);
        }
        .form-label {
            font-weight: 500;
            color: var(--text-main);
        }
        .form-control, .form-select {
            background-color: var(--input-bg);
            color: var(--text-main);
            border: 1px solid var(--input-border);
            border-radius: var(--border-radius);
            padding: 12px;
            margin-bottom: 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--input-focus);
            box-shadow: 0 0 0 0.15rem rgba(143, 90, 255, 0.25);
            background-color: var(--input-bg);
            color: var(--text-main);
        }
        .btn-register {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            background-color: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--border-radius);
            transition: background 0.2s, transform 0.2s;
        }
        .btn-register:hover {
            background-color: var(--accent-hover);
            transform: translateY(-2px);
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: var(--text-muted);
        }
        .login-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }
        .login-link a:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="register-header">
                <h2>Create Account</h2>
            </div>
            <?php if ($message) echo "<p>$message</p>"; ?>
            <?php if ($success): ?>
                <div class="alert alert-success" style="text-align:center;"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" style="text-align:center;"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($show_register_form): ?>
            <form id="registerForm" action="register.php" method="POST">
                <div class="mb-3">
                    <label for="fullName" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="fullName" name="fullName" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="confirmPassword" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                </div>
                <button type="submit" class="btn btn-primary btn-register">Register</button>
            </form>
            <?php endif; ?>
            <?php if ($show_login_form): ?>
            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label for="login_username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="login_username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="login_password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="login_password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-login">Login</button>
            </form>
            <?php endif; ?>
            <div class="login-link">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return;
            }
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters long!');
                return;
            }
            
            // If all validations pass, submit the form
            this.submit();
        });
    </script>
</body>
</html>
