<?php
ob_start(); // Start output buffering at the very beginning
session_start();
if (!isset($_SESSION['student_logged_in']) || !$_SESSION['student_logged_in']) {
    header('Location: student_login.php');
    exit;
}

// Handle password change request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    require_once 'config.php';
    
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];
    $studentId = $_SESSION['id'];
    
    $response = ['success' => false, 'message' => ''];
    
    // Validate new password
    if (strlen($newPassword) < 6) {
        $response['message'] = 'New password must be at least 6 characters long';
    } elseif ($newPassword !== $confirmPassword) {
        $response['message'] = 'New passwords do not match';
    } else {
        try {
            // First verify current password
            $stmt = $conn->prepare("SELECT password FROM students WHERE id = ?");
            $stmt->bind_param("s", $studentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response['message'] = 'Student not found';
            } else {
                $student = $result->fetch_assoc();
                
                if (!password_verify($currentPassword, $student['password'])) {
                    $response['message'] = 'Current password is incorrect';
                } else {
                    // Hash new password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    // Update password
                    $updateStmt = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
                    $updateStmt->bind_param("ss", $hashedPassword, $studentId);
                    
                    if ($updateStmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Password updated successfully';
                    } else {
                        $response['message'] = 'Failed to update password';
                    }
                }
            }
        } catch (Exception $e) {
            $response['message'] = 'An error occurred: ' . $e->getMessage();
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle profile update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    // Prevent any output before JSON response
    ob_clean();
    header('Content-Type: application/json');
    require_once 'config.php';
    
    $name = $_POST['name'];
    $email = $_POST['email'];
    $studentId = $_SESSION['id'];
    
    $response = ['success' => false, 'message' => ''];
    
    // Validate input
    if (empty($name) || empty($email)) {
        $response['message'] = 'Name and email are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format';
    } else {
        try {
            // Check if email is already taken by another student
            $stmt = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
            $stmt->bind_param("ss", $email, $studentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $response['message'] = 'Email is already taken by another student';
            } else {
                // Update profile
                $updateStmt = $conn->prepare("UPDATE students SET name = ?, email = ? WHERE id = ?");
                $updateStmt->bind_param("sss", $name, $email, $studentId);
                
                if ($updateStmt->execute()) {
                    // Update session variables
                    $_SESSION['student_name'] = $name;
                    $_SESSION['student_email'] = $email;
                    
                    $response['success'] = true;
                    $response['message'] = 'Profile updated successfully';
                } else {
                    $response['message'] = 'Failed to update profile';
                }
            }
        } catch (Exception $e) {
            $response['message'] = 'An error occurred: ' . $e->getMessage();
        }
    }
    echo json_encode($response);
    exit;
}

// Handle AJAX request to fetch student's attendance records
if (isset($_GET['action']) && $_GET['action'] === 'fetch_student_attendance') {
    // Prevent any output before JSON response
    ob_clean();

    // Set JSON header
    header('Content-Type: application/json');
    
    try {
        require_once 'config.php';
        $studentId = $_SESSION['id'];
        $attendanceRecords = [];

        // First verify the student exists
        $stmt = $conn->prepare("SELECT id FROM students WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Error preparing student verification query: " . $conn->error);
        }
        
        $stmt->bind_param("i", $studentId);
        if (!$stmt->execute()) {
            throw new Exception("Error executing student verification query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(['message' => 'Student not found']);
            exit;
        }
        $stmt->close();

        // Now fetch attendance records
        $sql = "SELECT ar.date, c.title AS course_title, c.code AS course_code, ar.status
                FROM attendance_records ar
                JOIN courses c ON ar.course_id = c.id
                WHERE ar.student_id = ?
                ORDER BY ar.date DESC, c.title";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparing attendance query: " . $conn->error);
        }

        $stmt->bind_param("i", $studentId);
        if (!$stmt->execute()) {
            throw new Exception("Error executing attendance query: " . $stmt->error);
        }

        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $attendanceRecords[] = $row;
        }
        $stmt->close();

        if (empty($attendanceRecords)) {
            echo json_encode(['message' => 'No attendance records found for this student']);
            exit;
        }

        echo json_encode($attendanceRecords);
        exit;

    } catch (Exception $e) {
        error_log("Error in fetch_student_attendance: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'An error occurred while fetching attendance records: ' . $e->getMessage()]);
        exit;
    }
}

// Handle AJAX request to fetch student's enrolled courses with teacher details
if (isset($_GET['action']) && $_GET['action'] === 'fetch_enrolled_courses') {
    // Prevent any output before JSON response
    ob_clean();
    
    // Set JSON header
    header('Content-Type: application/json');
    
    try {
        require_once 'config.php';
        $studentId = $_SESSION['id'];
        $enrolledCourses = [];

        // First, let's check if the student exists and get their course string
        $stmt = $conn->prepare("SELECT course FROM students WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Error preparing student query: " . $conn->error);
        }
        
        $stmt->bind_param("i", $studentId);
        if (!$stmt->execute()) {
            throw new Exception("Error executing student query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $studentData = $result->fetch_assoc();
        
        if (!$studentData) {
            echo json_encode(['message' => 'Student not found']);
            exit;
        }
        
        $studentCoursesString = $studentData['course'] ?? '';
        $stmt->close();

        if (empty($studentCoursesString)) {
            echo json_encode(['message' => 'No courses assigned to this student']);
            exit;
        }

        $courseIds = explode(',', $studentCoursesString);
        $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
        $types = str_repeat('i', count($courseIds));

        $sql = "SELECT c.id, c.title, c.code, tc.teacher_id, t.name AS teacher_name
                FROM courses c
                JOIN teacher_courses tc ON c.id = tc.course_id
                JOIN teachers t ON tc.teacher_id = t.id
                WHERE c.id IN ($placeholders)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparing courses query: " . $conn->error);
        }
        
        $stmt->bind_param($types, ...$courseIds);
        if (!$stmt->execute()) {
            throw new Exception("Error executing courses query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $enrolledCourses[] = $row;
        }
        $stmt->close();

        if (empty($enrolledCourses)) {
            echo json_encode(['message' => 'No active courses found for the assigned course IDs']);
            exit;
        }

        echo json_encode($enrolledCourses);
        exit;

    } catch (Exception $e) {
        error_log("Error in fetch_enrolled_courses: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'An error occurred while fetching courses: ' . $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #7c3aed;
            --secondary-color: #4f46e5;
            --background-dark: #0f172a;
            --card-bg: #1e293b;
            --text-light: #f1f5f9;
            --text-muted: #94a3b8;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--background-dark);
            color: var(--text-light);
        }

        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-right: 1px solid #334155;
        }

        .profile-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            background-color: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--text-muted);
        }

        .profile-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .profile-id {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            color: var(--text-light);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            background-color: #334155;
            color: var(--primary-color);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Main Content Styles */
        .main-content {
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background-color: #334155;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            width: 300px;
        }

        .search-bar input {
            background: none;
            border: none;
            color: var(--text-light);
            width: 100%;
            padding: 0.5rem;
        }

        .search-bar input:focus {
            outline: none;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .attendance-table {
            background-color: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            margin-top: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #334155;
        }

        th {
            background-color: #334155;
            font-weight: 600;
        }

        .status-present {
            color: var(--success-color);
        }

        .status-absent {
            color: var(--danger-color);
        }

        .status-late {
            color: var(--warning-color);
        }

        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }

            .search-bar {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Content Sections */
        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--text-light);
        }

        /* Attendance Section */
        .attendance-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            background-color: #334155;
            color: var(--text-light);
            cursor: pointer;
        }

        .filter-btn.active {
            background-color: var(--primary-color);
        }

        /* Courses Section */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .course-card {
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .course-card h3 {
            margin-bottom: 0.5rem;
        }

        .course-info {
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        /* Performance Section */
        .performance-chart {
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            height: 300px;
        }

        /* Settings Section */
        .settings-boxes {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .settings-form {
            background-color: var(--card-bg);
            padding: 2.5rem 2rem 2rem 2rem;
            border-radius: 24px;
            min-width: 320px;
            max-width: 420px;
            margin: 0 auto;
            box-shadow: 0 4px 24px rgba(143,90,255,0.08);
        }
        .settings-form h3 {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #fff;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #fff;
            font-weight: 500;
        }
        .form-group input[type="password"] {
            width: 100%;
            padding: 1rem 1.5rem;
            background-color: #232b3e;
            border: 2px solid #313a4d;
            border-radius: 12px;
            color: #fff;
            font-size: 1.1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input[type="password"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px var(--primary-color);
        }
        .save-btn {
            background-color: var(--primary-color);
            color: #fff;
            padding: 1rem 0;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1.2rem;
            font-weight: 700;
            width: 100%;
            margin-top: 1.5rem;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
        }
        .save-btn:hover, .save-btn:focus {
            background-color: var(--secondary-color);
            color: #fff;
            box-shadow: 0 2px 12px rgba(76,70,229,0.12);
        }
        @media (max-width: 900px) {
            .settings-boxes {
                flex-direction: column;
                gap: 1.5rem;
            }
        }
        @media (max-width: 600px) {
            .save-btn {
                font-size: 1.1rem;
                padding: 1rem 0.5rem;
                width: 100%;
            }
        }
        .nav-link#logout-btn {
            background-color: var(--danger-color);
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            transition: background 0.2s, color 0.2s;
        }
        .nav-link#logout-btn:hover, .nav-link#logout-btn:focus {
            background-color: #b91c1c;
            color: #fff;
        }
        @media (max-width: 600px) {
            .nav-link#logout-btn {
                width: 100%;
                text-align: center;
                font-size: 1.1rem;
                padding: 1rem 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="profile-section">
                <div class="profile-image">
                    <i class="fas fa-user"></i>
                </div>
                <h2 class="profile-name"><?= htmlspecialchars($_SESSION['student_name']) ?></h2>
                <p class="profile-id">ID: <?= isset($_SESSION['id']) ? htmlspecialchars($_SESSION['id']) : 'N/A' ?></p>
            </div>

            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="#" class="nav-link active" data-section="dashboard">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link" data-section="courses">
                        <i class="fas fa-book"></i>
                        Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" data-section="profile">
                        <i class="fas fa-user-edit"></i>
                        Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" data-section="settings">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" id="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Dashboard Section -->
            <div class="content-section active" id="dashboard-section">
                <div class="header">
                <h2 class="profile-name"><?= htmlspecialchars($_SESSION['student_name']) ?></h2>
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by course code...">
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Overall Attendance</h3>
                        <div class="value" id="overallAttendance">85%</div>
                    </div>
                    <div class="stat-card" id="courseAttendanceCard" style="display: none;">
                        <h3>Course Attendance</h3>
                        <div class="value" id="courseAttendance">0%</div>
                    </div>
                </div>

                <div class="attendance-table">
                    <h2 style="padding: 1rem;">Recent Attendance</h2>
                    <table id="attendanceTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Course</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            <tr>
                                <td>2025-03-20</td>
                                <td>AI</td>
                                <td class="status-present">Present</td>
                            </tr>
                            <tr>
                                <td>2025-03-19</td>
                                <td>DC</td>
                                <td class="status-late">Late</td>
                            </tr>
                            <tr>
                                <td>2025-03-18</td>
                                <td>SEDP</td>
                                <td class="status-present">Present</td>
                            </tr>
                            <tr>
                                <td>2025-03-17</td>
                                <td>CN</td>
                                <td class="status-absent">Absent</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Courses Section -->
            <div class="content-section" id="courses-section">
                <h1 class="section-title">My Courses</h1>
                <div class="courses-grid" id="enrolledCoursesGrid">
                    <!-- Enrolled courses will be dynamically loaded here -->
                </div>
            </div>

            <!-- Profile Section -->
            <div class="content-section" id="profile-section">
                <h1 class="section-title">Edit Profile</h1>
                <div class="settings-boxes">
                    <div class="settings-form">
                        <h3 style="margin-bottom:1rem;">Edit Profile</h3>
                        <form id="profileForm" method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="<?= htmlspecialchars($_SESSION['student_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_SESSION['student_email'] ?? '') ?>" required>
                            </div>
                            <button type="submit" class="save-btn" style="margin-top:1rem;">Save Profile</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Settings Section -->
            <div class="content-section" id="settings-section">
                <h1 class="section-title">Change Password</h1>
                <div class="settings-boxes">
                    <div class="settings-form">
                        <h3 style="margin-bottom:1rem;">Change Password</h3>
                        <form id="changePasswordForm" method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group">
                                <label for="currentPassword">Current Password</label>
                                <input type="password" id="currentPassword" name="currentPassword" placeholder="Enter current password">
                            </div>
                            <div class="form-group">
                                <label for="newPassword">New Password</label>
                                <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password">
                            </div>
                            <div class="form-group">
                                <label for="confirmPassword">Confirm Password</label>
                                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm new password">
                            </div>
                            <button type="submit" class="save-btn" style="margin-top:1rem;">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navigation handling
            const navLinks = document.querySelectorAll('.nav-link');
            const contentSections = document.querySelectorAll('.content-section');

            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Remove active class from all links and sections
                    navLinks.forEach(l => l.classList.remove('active'));
                    contentSections.forEach(section => section.classList.remove('active'));
                    // Add active class to clicked link
                    this.classList.add('active');
                    // Show corresponding section
                    const sectionId = this.getAttribute('data-section') + '-section';
                    const section = document.getElementById(sectionId);
                    if (section) section.classList.add('active');
                });
            });
            // By default, show dashboard section
            document.querySelector('.nav-link[data-section="dashboard"]').classList.add('active');
            document.getElementById('dashboard-section').classList.add('active');

            // Settings form handling
            const saveBtn = document.querySelector('.save-btn');
            saveBtn.addEventListener('click', function() {
                // Add save functionality here
                alert('Settings saved successfully!');
            });

            // Password change handling
            const changePasswordForm = document.getElementById('changePasswordForm');
            
            changePasswordForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const currentPassword = document.getElementById('currentPassword').value;
                const newPassword = document.getElementById('newPassword').value;
                const confirmPassword = document.getElementById('confirmPassword').value;

                // Basic validation
                if (!currentPassword || !newPassword || !confirmPassword) {
                    alert('Please fill in all password fields');
                    return;
                }

                if (newPassword !== confirmPassword) {
                    alert('New passwords do not match');
                    return;
                }

                if (newPassword.length < 6) {
                    alert('New password must be at least 6 characters long');
                    return;
                }

                try {
                    const formData = new FormData(this);
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        alert(data.message);
                        // Clear form
                        this.reset();
                    } else {
                        alert(data.message);
                    }
                } catch (error) {
                    alert('An error occurred while changing password');
                    console.error('Error:', error);
                }
            });

            // Profile form handling
            const profileForm = document.getElementById('profileForm');
            
            profileForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const name = document.getElementById('name').value.trim();
                const email = document.getElementById('email').value.trim();

                // Basic validation
                if (!name || !email) {
                    alert('Please fill in all fields');
                    return;
                }

                try {
                    const formData = new FormData(this);
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        alert(data.message);
                        // Update the profile name in the sidebar
                        document.querySelector('.profile-name').textContent = name;
                        // Update the profile name in the dashboard header
                        document.querySelector('.header .profile-name').textContent = name;
                    } else {
                        alert(data.message);
                    }
                } catch (error) {
                    alert('An error occurred while updating profile');
                    console.error('Error:', error);
                }
            });

            // Search functionality
            const searchInput = document.getElementById('searchInput');
            const attendanceTableBody = document.getElementById('attendanceTableBody');
            const courseAttendanceCard = document.getElementById('courseAttendanceCard');
            const courseAttendanceValue = document.getElementById('courseAttendance');
            const overallAttendanceValue = document.getElementById('overallAttendance');

            let fetchedAttendanceData = []; // Variable to store fetched data

            // Function to fetch and render attendance data
            async function fetchAndRenderAttendance() {
                try {
                    const response = await fetch('student-dashboard.php?action=fetch_student_attendance');
                    const data = await response.json();
                    console.log('Fetched attendance data response:', data);

                    if (response.ok) {
                        if (data.message) {
                            // Handle informational messages
                            attendanceTableBody.innerHTML = `
                                <tr>
                                    <td colspan="3" style="text-align: center; padding: 2rem;">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">${data.message}</p>
                                        <p style="color: var(--text-muted); font-size: 0.9rem;">Your attendance records will appear here once your teachers mark attendance.</p>
                                    </td>
                                </tr>
                            `;
                            overallAttendanceValue.textContent = 'N/A';
                            return;
                        }

                        fetchedAttendanceData = data;
                        updateAttendanceTable(fetchedAttendanceData);
                        calculateAndDisplayOverallAttendance(fetchedAttendanceData);
                    } else {
                        // Handle error response
                        throw new Error(data.error || 'Failed to fetch attendance records');
                    }
                } catch (error) {
                    console.error('Error fetching attendance data:', error);
                    attendanceTableBody.innerHTML = `
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 2rem;">
                                <p style="color: var(--danger-color); margin-bottom: 1rem;">Error loading attendance records.</p>
                                <p style="color: var(--text-muted); font-size: 0.9rem;">${error.message}</p>
                                <p style="color: var(--text-muted); font-size: 0.9rem;">Please try refreshing the page or contact support if the problem persists.</p>
                            </td>
                        </tr>
                    `;
                    overallAttendanceValue.textContent = 'N/A';
                }
            }

            // Function to calculate overall attendance
            function calculateAndDisplayOverallAttendance(data) {
                if (data.length === 0) {
                    overallAttendanceValue.textContent = '0%';
                    return;
                }
                const presentCount = data.filter(record => 
                    record.status === 'Present'
                ).length;
                console.log('Overall Attendance: presentCount=', presentCount, 'totalRecords=', data.length);
                const percentage = Math.round((presentCount / data.length) * 100);
                overallAttendanceValue.textContent = `${percentage}%`;
            }

            function calculateCourseAttendance(courseCode) {
                const courseRecords = fetchedAttendanceData.filter(record => record.course_code.toUpperCase() === courseCode.toUpperCase()); // Use fetched data and case-insensitive comparison
                if (courseRecords.length === 0) return 'N/A'; // Return N/A if no records for the course

                const presentCount = courseRecords.filter(record => 
                    record.status === 'Present' || record.status === 'Late'
                ).length;
                console.log('Course Attendance for ', courseCode, ': presentCount=', presentCount, 'totalRecords=', courseRecords.length);

                return Math.round((presentCount / courseRecords.length) * 100);
            }

            function updateAttendanceTable(dataToDisplay) { // Renamed parameter to avoid confusion
                attendanceTableBody.innerHTML = '';
                if (dataToDisplay.length === 0) {
                    attendanceTableBody.innerHTML = `
                        <tr>
                            <td colspan="3" style="text-align: center;">No records found.</td>
                        </tr>
                    `;
                    return;
                }
                dataToDisplay.forEach(record => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${record.date}</td>
                        <td>${record.course_title} (${record.course_code})</td>
                        <td class="status-${record.status.toLowerCase()}">${record.status}</td>
                    `;
                    attendanceTableBody.appendChild(row);
                });
            }

            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.trim().toUpperCase();
                
                if (searchTerm === '') {
                    // Show all records and overall attendance
                    updateAttendanceTable(fetchedAttendanceData); // Use fetched data
                    courseAttendanceCard.style.display = 'none';
                    return;
                }

                // Filter records by course code or title
                const filteredData = fetchedAttendanceData.filter(record => 
                    record.course_code.toUpperCase().includes(searchTerm) || record.course_title.toUpperCase().includes(searchTerm)
                );

                if (filteredData.length > 0) {
                    // Show filtered records and course-specific attendance
                    updateAttendanceTable(filteredData);
                    // Calculate attendance for the specific searched course, not just any course in filteredData
                    // Get unique course codes from filteredData to accurately calculate for each
                    const uniqueCourseCodes = [...new Set(filteredData.map(record => record.course_code.toUpperCase()))];
                    
                    // If there's only one unique course, display its attendance
                    if (uniqueCourseCodes.length === 1) {
                        const attendancePercentage = calculateCourseAttendance(uniqueCourseCodes[0]);
                        courseAttendanceValue.textContent = `${attendancePercentage}%`;
                        courseAttendanceCard.style.display = 'block';
                    } else {
                        // If multiple courses match, display a general message or hide the specific course attendance
                        courseAttendanceCard.style.display = 'none'; // Or show 'N/A' or aggregate if sensible
                    }
                } else {
                    // No matching records found
                    attendanceTableBody.innerHTML = `
                        <tr>
                            <td colspan="3" style="text-align: center;">No attendance records found for ${searchTerm}</td>
                        </tr>
                    `;
                    courseAttendanceCard.style.display = 'none';
                }
            });

            // Initial fetch and render when the dashboard section is active
            // This logic is a bit tricky with section switching. Let's make it fetch when DOM is ready.
            fetchAndRenderAttendance();

            // Function to fetch and render enrolled courses
            async function fetchAndRenderEnrolledCourses() {
                const enrolledCoursesGrid = document.getElementById('enrolledCoursesGrid');
                enrolledCoursesGrid.innerHTML = '<div style="text-align: center; padding: 2rem;"><p style="color: var(--text-muted);">Loading courses...</p></div>';
                
                try {
                    const response = await fetch('student-dashboard.php?action=fetch_enrolled_courses');
                    let data;
                    try {
                        data = await response.json();
                    } catch (e) {
                        console.error('Error parsing JSON response:', e);
                        throw new Error('Invalid response from server');
                    }
                    
                    console.log('Fetched enrolled courses response:', data);

                    enrolledCoursesGrid.innerHTML = ''; // Clear loading message

                    if (response.ok) {
                        if (data.message) {
                            // Handle informational messages
                            enrolledCoursesGrid.innerHTML = `
                                <div style="text-align: center; padding: 2rem;">
                                    <p style="color: var(--text-muted); margin-bottom: 1rem;">${data.message}</p>
                                    <p style="color: var(--text-muted); font-size: 0.9rem;">Please contact your administrator if you believe this is an error.</p>
                                </div>
                            `;
                            return;
                        }

                        if (!Array.isArray(data) || data.length === 0) {
                            enrolledCoursesGrid.innerHTML = `
                                <div style="text-align: center; padding: 2rem;">
                                    <p style="color: var(--text-muted);">No courses found.</p>
                                </div>
                            `;
                            return;
                        }

                        data.forEach(course => {
                            const courseCard = document.createElement('div');
                            courseCard.classList.add('course-card');
                            courseCard.innerHTML = `
                                <h3>${course.code} - ${course.title}</h3>
                                <p class="course-info">Instructor: ${course.teacher_name || 'Not Assigned'}</p>
                                <p class="course-info">Course ID: ${course.id}</p>
                            `;
                            enrolledCoursesGrid.appendChild(courseCard);
                        });
                    } else {
                        // Handle error response
                        throw new Error(data.error || 'Failed to fetch courses');
                    }
                } catch (error) {
                    console.error('Error fetching enrolled courses:', error);
                    enrolledCoursesGrid.innerHTML = `
                        <div style="text-align: center; padding: 2rem;">
                            <p style="color: var(--danger-color); margin-bottom: 1rem;">Error loading courses.</p>
                            <p style="color: var(--text-muted); font-size: 0.9rem;">${error.message}</p>
                            <p style="color: var(--text-muted); font-size: 0.9rem;">Please try refreshing the page or contact support if the problem persists.</p>
                        </div>
                    `;
                }
            }

            // Call fetchAndRenderEnrolledCourses when the courses section is activated
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (this.getAttribute('data-section') === 'courses') {
                        fetchAndRenderEnrolledCourses();
                    }
                });
            });

            // Logout handling
            const logoutBtn = document.getElementById('logout-btn');
            logoutBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'logout.php';
            });
        });
    </script>
</body>
</html> 