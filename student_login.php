<?php
require 'db.php';
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['student_logged_in']) && $_SESSION['student_logged_in']) {
    header('Location: student-dashboard.php');
    exit;
}

$error = '';
$success = '';
$showReset = isset($_GET['reset']); // Show reset form if ?reset=1
$showRegister = isset($_GET['register']); // Show register form if ?register=1

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register'])) {
        // Registration logic
        $fullName = trim($_POST['fullName']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $department = trim($_POST['department']);
        // Handle multiple courses
        $courses = isset($_POST['course']) ? $_POST['course'] : [];
        if (is_string($courses)) {
            $courses = explode(',', $courses);
        }
        $course = implode(',', $courses); // store IDs only
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirmPassword'];

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ?");
        $stmt->execute([$email]);
        $emailExists = $stmt->fetchColumn();

        if ($emailExists) {
            $error = "This Gmail address is already registered. Please use a different Gmail address.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match!";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long!";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO students (name, email, username, department, course, password) VALUES (?, ?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$fullName, $email, $username, $department, $course, $hashedPassword]);
                $success = "Registration successful! Please login.";
                $showRegister = false;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Username already exists. Please use a different username.";
                } else {
                    $error = "Error: " . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['reset'])) {
        // Password reset logic - REMOVED, handled by reset_password.php
        // (No code here)
    } else {
        // Login logic
        $userOrEmail = trim($_POST['user_or_email']);
        $password = $_POST['password'];

        if (empty($userOrEmail) || empty($password)) {
            $error = "Please fill in all fields!";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE username = ? OR email = ?");
            $stmt->execute([$userOrEmail, $userOrEmail]);
            $student = $stmt->fetch();

            if ($student && password_verify($password, $student['password'])) {
                $_SESSION['student_logged_in'] = true;
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_name'] = $student['name'];
                $_SESSION['student_email'] = $student['email'];
                $_SESSION['id'] = $student['id'];
                header('Location: student-dashboard.php');
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student <?php echo $showRegister ? 'Register' : ($showReset ? 'Reset Password' : 'Login'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #7a47e5;
            --primary-hover: #6a3cd5;
            --text-color: #333;
            --error-color: #dc3545;
            --success-color: #28a745;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #181f2a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            background: #232b3e;
            padding: 2.5rem 2.2rem;
            border-radius: 16px;
            box-shadow: 0 0 32px rgba(0,0,0,0.18);
            width: 100%;
            max-width: 480px;
            min-width: 350px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            color: #7a47e5;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .login-header p {
            color: #b0b8c1;
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

        .form-group input:focus {
            outline: none;
            border-color: #7a47e5;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            background: #7a47e5;
            color: #fff;
            border: none;
            border-radius: 7px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.3s;
            margin-bottom: 1.2rem;
        }

        .btn:hover {
            background: #5b2fd6;
        }

        .message {
            padding: 0.8rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
            color: #fff;
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
            color: #7a47e5;
            text-decoration: none;
            margin: 0 18px;
            font-size: 1.08rem;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .course-chip {
            display: inline-block;
            background: #7a47e5;
            color: #fff;
            padding: 6px 14px;
            border-radius: 16px;
            margin-right: 8px;
            margin-bottom: 6px;
            font-size: 15px;
            position: relative;
            cursor: default;
        }

        .login-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1.2rem;
        }
        .login-links a {
            color: #7a47e5;
            text-decoration: underline;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($showRegister): ?>
            <div class="login-header">
                <h1>Student Registration</h1>
                <p>Create your student account</p>
            </div>
        <?php elseif ($showReset): ?>
            <div class="login-header">
                <h1>Reset Password</h1>
                <p>Enter your email to reset your password</p>
            </div>
        <?php else: ?>
            <div class="login-header">
                <h1>Student Login</h1>
                <p>Welcome back! Please login to your account</p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error" style="margin-bottom: 1.5rem;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message success"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($showRegister): ?>
            <form method="post">
                <input type="hidden" name="register" value="1">
                <div class="form-group">
                    <label for="fullName">Full Name</label>
                    <input type="text" id="fullName" name="fullName" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="department">Department</label>
                    <select id="department" name="department" required>
                        <option value="">Select Department</option>
                        <option value="CSE">CSE</option>
                        <option value="EEE">EEE</option>
                        <option value="BBA">BBA</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="courseDropdown">Course</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <select id="courseDropdown" style="flex:1;"></select>
                        <button type="button" id="addCourseBtn">Add</button>
                    </div>
                    <div id="selectedCourses" style="margin: 10px 0; min-height: 32px;"></div>
                    <input type="hidden" id="course" name="course" />
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" required minlength="8">
                </div>
                <button type="submit" class="btn">Register</button>
            </form>
        <?php elseif ($showReset): ?>
            <form method="post">
                <input type="hidden" name="reset" value="1">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <button type="submit" class="btn">Reset Password</button>
            </form>
        <?php else: ?>
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

        <div class="login-links">
            <?php if ($showRegister): ?>
                <a href="student_login.php">Login</a>
                <a href="student_login.php?reset=1">Forgot Password?</a>
            <?php elseif ($showReset): ?>
                <a href="student_login.php">Login</a>
                <a href="student_login.php?register=1">Register</a>
            <?php else: ?>
                <a href="student_login.php?register=1">Register</a>
                <a href="reset_password.php">Forgot Password?</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let allCourses = [];
        let selectedCoursesArr = [];

        async function fetchCoursesForDepartment(department) {
            try {
                const response = await fetch(`fetch_courses.php`);
                const courses = await response.json();
                allCourses = courses;
                updateCourseDropdown();
            } catch (error) {
                allCourses = [];
                updateCourseDropdown();
            }
        }

        function updateCourseDropdown() {
            const dept = document.getElementById('department').value;
            const courseDropdown = document.getElementById('courseDropdown');
            courseDropdown.innerHTML = '';
            allCourses
                .filter(course => !dept || course.department === dept)
                .forEach(course => {
                    if (!selectedCoursesArr.includes(course.id.toString())) {
                        const option = document.createElement('option');
                        option.value = course.id;
                        option.textContent = course.title;
                        courseDropdown.appendChild(option);
                    }
                });
        }

        function renderSelectedCourses() {
            const container = document.getElementById('selectedCourses');
            container.innerHTML = '';
            selectedCoursesArr.forEach(courseId => {
                const course = allCourses.find(c => c.id.toString() === courseId);
                if (!course) return;
                const chip = document.createElement('span');
                chip.textContent = course.title;
                chip.className = 'course-chip';
                const removeBtn = document.createElement('span');
                removeBtn.textContent = ' ×';
                removeBtn.style.cursor = 'pointer';
                removeBtn.onclick = function() {
                    selectedCoursesArr = selectedCoursesArr.filter(c => c !== courseId);
                    renderSelectedCourses();
                    updateCourseDropdown();
                };
                chip.appendChild(removeBtn);
                container.appendChild(chip);
            });
            document.getElementById('course').value = selectedCoursesArr.join(',');
        }

        document.getElementById('department').addEventListener('change', function() {
            selectedCoursesArr = [];
            renderSelectedCourses();
            fetchCoursesForDepartment(this.value);
        });

        document.getElementById('addCourseBtn').onclick = function() {
            const courseDropdown = document.getElementById('courseDropdown');
            const selected = courseDropdown.value;
            if (selected && !selectedCoursesArr.includes(selected)) {
                selectedCoursesArr.push(selected);
                renderSelectedCourses();
                updateCourseDropdown();
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            fetchCoursesForDepartment(document.getElementById('department').value);
            renderSelectedCourses();
        });
    </script>
</body>
</html> 