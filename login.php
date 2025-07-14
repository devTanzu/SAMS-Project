<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Attendance System</title>
    <style>
        body { background: #181f2a; color: #f4f6fb; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .login-choice-container {
            max-width: 400px;
            margin: 100px auto;
            background: #232b3e;
            padding: 32px 28px;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.25);
            text-align: center;
        }
        .login-choice-container h2 { color: #8f5aff; margin-bottom: 24px; }
        .login-btn {
            display: block;
            width: 100%;
            margin: 16px 0;
            padding: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            background: #8f5aff;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .login-btn:hover { background: #7a47e5; }
    </style>
</head>
<body>
    <div class="login-choice-container">
        <h2>Login or Register</h2>
        <button class="login-btn" onclick="window.location.href='teacher_login.php'">Teacher Login / Register</button>
        <button class="login-btn" onclick="window.location.href='student_login.php'">Student Login / Register</button>
    </div>
</body>
</html>
