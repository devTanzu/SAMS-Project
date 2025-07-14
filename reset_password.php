<?php
require 'db.php';
$message = '';
$step = 1;
$username = '';
$email = '';
$user_type = '';
$show_password_form = false;
$table = '';
$login_page = '';
$table_map = [
    'teachers' => 'username',
    'students' => 'username',
    'admin_users' => 'username'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 1: Email submission
    if (isset($_POST['find_email'])) {
        $email = trim($_POST['email'] ?? '');
        if (!$email) {
            $message = 'Please enter your email.';
        } else {
            // Search in all user tables
            foreach ($table_map as $tbl => $uname_col) {
                $stmt = $pdo->prepare("SELECT * FROM $tbl WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user) {
                    $username = $user[$uname_col] ?? $user['name'] ?? '';
                    $show_password_form = true;
                    $step = 2;
                    $table = $tbl;
                    if ($tbl === 'teachers') {
                        $login_page = 'teacher_login.php';
                    } elseif ($tbl === 'students') {
                        $login_page = 'student_login.php';
                    } elseif ($tbl === 'admin_users') {
                        $login_page = 'admin_login.php';
                    }
                    break;
                }
            }
            if (!$show_password_form) {
                $message = 'No account found with that email.';
            }
        }
    }
    // Step 2: Password reset
    elseif (isset($_POST['reset_password'])) {
        $email = trim($_POST['email'] ?? '');
        $username = $_POST['username'] ?? '';
        $table = $_POST['table'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        if (!$new_password || !$confirm_password) {
            $message = 'Please fill in all fields.';
            $step = 2;
            $show_password_form = true;
        } elseif ($new_password !== $confirm_password) {
            $message = 'Passwords do not match.';
            $step = 2;
            $show_password_form = true;
        } elseif (strlen($new_password) < 8) {
            $message = 'Password must be at least 8 characters.';
            $step = 2;
            $show_password_form = true;
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE $table SET password = ? WHERE email = ?");
            $stmt->execute([$hash, $email]);
            $message = 'Password reset successfully!';
            $step = 1;
            // Set login page based on table after successful reset
            if ($table === 'teachers') {
                $login_page = 'teacher_login.php';
            } elseif ($table === 'students') {
                $login_page = 'student_login.php';
            } elseif ($table === 'admin_users') {
                $login_page = 'admin_login.php';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <style>
        body { background: #181f2a; color: #f4f6fb; font-family: Arial, sans-serif; }
        .container { max-width: 400px; margin: 80px auto; background: #232b3e; padding: 32px 28px; border-radius: 12px; }
        h2 { text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; }
        .form-group input { 
            width: 100%; 
            padding: 0.8rem; 
            border-radius: 7px; 
            border: 1px solid #444c5e; 
            background: #1e293b; 
            color: #f4f6fb;
            box-sizing: border-box;
            font-size: 1rem;
        }
        .btn { width: 100%; padding: 1rem; background: #8f5aff; color: #fff; border: none; border-radius: 7px; font-size: 1.1rem; cursor: pointer; }
        .btn:hover { background: #7a47e5; }
        .message { padding: 0.8rem; border-radius: 5px; margin-bottom: 1rem; text-align: center; background: #3b1a2b; color: #ff6b81; }
        .username-box { text-align: center; font-size: 1.2rem; margin-bottom: 1.5rem; color: #8f5aff; }
        .message.success { background: #1a3b2b; color: #4ef58c; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        <?php if ($message): ?>
            <div class="message<?= ($message === 'Password reset successfully!') ? ' success' : '' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1 && $message === 'Password reset successfully!'): ?>
            <form method="get" action="<?= htmlspecialchars($login_page ?: 'teacher_login.php') ?>">
                <button type="submit" class="btn">Back to Login</button>
            </form>
        <?php elseif ($step === 1): ?>
            <form method="post">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email) ?>">
                </div>
                <button type="submit" class="btn" name="find_email">Reset Password</button>
            </form>
        <?php elseif ($step === 2 && $show_password_form): ?>
            <div class="username-box">
                Username: <strong><?= htmlspecialchars($username) ?></strong>
            </div>
            <form method="post">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                <button type="submit" class="btn" name="reset_password">Submit</button>
            </form>
        <?php endif; ?>
    </div>
    <!-- <div class="links">
        <a href="teacher_login.php?register=1">Register</a>
        <a href="reset_password.php">Forgot Password?</a>
    </div> -->
</body>
</html>
