<?php
session_start();
if (!isset($_SESSION['teacher_logged_in']) || !$_SESSION['teacher_logged_in']) {
    header('Location: index.php');
    exit;
}
// Set user type for logout handling IMMEDIATELY after session_start
$_SESSION['user_type'] = 'teacher';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: teacher_login.php');
    exit;
}

$db = new mysqli('localhost', 'root', '', 'attendance_system');
if ($db->connect_error) {
    die("Database connection failed: " . $db->connect_error);
}

$teacher_id = $_SESSION['teacher_id']; // Make sure this is set at login

// Get today's date
$today = date('l'); // Gets the current weekday, e.g., "Tuesday"

// Fetch today's classes for this teacher
$todays_classes = [];
$sql = "SELECT c.title, c.code, cs.start_time, cs.end_time, cs.course_id
        FROM class_schedules cs
        JOIN courses c ON cs.course_id = c.id
        WHERE cs.teacher_id = ? AND cs.class_date = ?
        ORDER BY cs.start_time";
$stmt = $db->prepare($sql);
$stmt->bind_param('is', $teacher_id, $today);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $todays_classes[] = $row;
    }
}
$stmt->close();

// Fetch courses for this teacher
$courses = [];
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM students s WHERE FIND_IN_SET(c.id, s.course)) as enrolled_students
        FROM teacher_courses tc
        JOIN courses c ON tc.course_id = c.id
        WHERE tc.teacher_id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param('i', $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

// Fetch total students
$studentsCount = $db->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['profileName'], $_POST['profileEmail'])) {
        // Update session (simulate DB update)
        $_SESSION['teacher_name'] = $_POST['profileName'];
        $_SESSION['teacher_email'] = $_POST['profileEmail'];
        $_SESSION['flash_success'] = 'Profile updated successfully!';
        header('Location: teacher_dashboard.php');
        exit;
    }
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        // Here you would check the current password, validate, and update in DB
        // For demo, just set a flash message
        $_SESSION['flash_success'] = 'Password changed successfully!';
        header('Location: teacher-dashboard.php');
        exit;
    }
    if (isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
        error_log("Received attendance submission request");
        $response = ['success' => false, 'message' => ''];

        try {
            // Revert to processing form-urlencoded data from $_POST
            $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
            $attendance = isset($_POST['attendance']) ? json_decode($_POST['attendance'], true) : null;
            $date = date('Y-m-d');

            error_log("Course ID received (PHP): " . $course_id);
            error_log("Raw POST attendance string (PHP): " . (isset($_POST['attendance']) ? $_POST['attendance'] : '[NOT SET]'));
            error_log("Decoded attendance data (PHP): " . print_r($attendance, true));

            if (!$course_id || !$attendance) {
                throw new Exception('Invalid course ID or attendance data');
            }

            // Start transaction
            $db->begin_transaction();

            // Delete existing attendance records for this course and date
            $stmt = $db->prepare("DELETE FROM attendance_records WHERE course_id = ? AND date = ?");
            if (!$stmt) {
                throw new Exception('Error preparing delete statement: ' . $db->error);
            }
            $stmt->bind_param('is', $course_id, $date);
            if (!$stmt->execute()) {
                throw new Exception('Error deleting existing records: ' . $stmt->error);
            }
            $stmt->close();

            // Insert new attendance records
            $stmt = $db->prepare("INSERT INTO attendance_records (student_id, course_id, date, status) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception('Error preparing insert statement: ' . $db->error);
            }

            foreach ($attendance as $student_id => $status) {
                $stmt->bind_param('iiss', $student_id, $course_id, $date, $status);
                if (!$stmt->execute()) {
                    throw new Exception('Error saving attendance for student ID: ' . $student_id . ' - ' . $stmt->error);
                }
            }

            $stmt->close();

            // Commit transaction
            $db->commit();

            $response['success'] = true;
            $response['message'] = 'Attendance saved successfully';
            error_log("Attendance saved successfully");
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollback();
            $response['message'] = $e->getMessage();
            error_log("Attendance save error: " . $e->getMessage());
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Handle AJAX request to fetch students for a course
if (isset($_GET['fetch_students_for_course']) && isset($_GET['course_id'])) {
    $course_id = $_GET['course_id'];
    $students = [];
    $stmt = $db->prepare("SELECT s.id, s.name FROM students s WHERE FIND_IN_SET(?, s.course)");

    if ($stmt) {
        $stmt->bind_param('s', $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
    } else {
        // Handle prepared statement error
        $students = ['error' => 'Database error preparing statement: ' . $db->error];
        error_log('Database error preparing student fetch statement: ' . $db->error);
    }

    header('Content-Type: application/json');
    echo json_encode($students);
    exit; // Ensure the script stops here after sending JSON
}

// 1. Add Attendance Records to sidebar
// Find the sidebar <ul> and add:
// <li><a href="#" data-section="attendance-records"><i class="fas fa-clipboard-list"></i> Attendance Records</a></li>

// 2. Add Attendance Records section to main content
// <section class="section" id="attendance-records">
//   <div class="main-header"><h1>Attendance Records</h1></div>
//   <div id="attendanceRecordsTable"></div>
// </section>

// 3. PHP: Fetch attendance records for this teacher
// Add after fetching courses:
$attendance_records = [];
$sql = "SELECT ar.id, ar.date, ar.status, s.name AS student_name, c.title AS course_title, c.code AS course_code, c.id AS course_id
        FROM attendance_records ar
        JOIN students s ON ar.student_id = s.id
        JOIN courses c ON ar.course_id = c.id
        JOIN teacher_courses tc ON tc.course_id = c.id
        WHERE tc.teacher_id = ?
        ORDER BY ar.date DESC, c.title, s.name";
$stmt = $db->prepare($sql);
$stmt->bind_param('i', $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $attendance_records[] = $row;
}
$stmt->close();

// 1. PHP: Add AJAX endpoint for attendance records
if (isset($_GET['fetch_attendance_records'])) {
    error_log("--- PHP: Received fetch_attendance_records request ---");
    error_log("PHP: Teacher ID for attendance fetch: " . $teacher_id);
    $attendance_records = [];
    $sql = "SELECT ar.id, ar.date, c.title AS course_title, c.code AS course_code, s.name AS student_name, ar.status, ar.created_at
            FROM attendance_records ar
            JOIN students s ON ar.student_id = s.id
            JOIN courses c ON ar.course_id = c.id
            JOIN teacher_courses tc ON tc.course_id = c.id
            WHERE tc.teacher_id = ?";
    $params = [$teacher_id]; // Add teacher_id to parameters
    $types = 'i'; // Add type for teacher_id

    if (!empty($_GET['date'])) {
        $sql .= " AND ar.date = ?"; // Change to AND, as WHERE is now present
        $params[] = $_GET['date'];
        $types .= 's';
    }

    if (!empty($_GET['search_term'])) {
        $searchTerm = '%' . $_GET['search_term'] . '%';
        $sql .= " AND (c.title LIKE ? OR c.code LIKE ? OR s.name LIKE ?)"; // Change to AND
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
    }

    $sql .= " ORDER BY ar.date DESC, c.title, s.name";
    $stmt = $db->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance_records[] = $row;
    }
    $stmt->close();
    echo json_encode($attendance_records);
    exit;
}

// Add new PHP endpoint to handle updating a single attendance record
if (isset($_POST['action']) && $_POST['action'] === 'update_attendance_record') {
    $response = ['success' => false, 'message' => ''];

    error_log("--- PHP: Received update_attendance_record request ---");
    error_log("PHP: Raw POST data: " . print_r($_POST, true));

    $record_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';

    error_log("PHP: Type of record_id: " . gettype($record_id) . ", Type of status: " . gettype($status));
    error_log("PHP: Value of record_id: " . $record_id . ", Value of status: " . $status);

    if ($record_id > 0 && !empty($status)) {
        // Validate status against allowed values
        $allowed_statuses = ['Present', 'Absent', 'Late']; // Match these to your database/JS values
        if (in_array($status, $allowed_statuses)) {
            $stmt = $db->prepare("UPDATE attendance_records SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $record_id);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Record updated successfully';
            } else {
                $response['message'] = 'Database error: ' . $stmt->error;
                error_log("PHP: Database error updating record: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $response['message'] = 'Invalid status provided.';
            error_log("PHP: Invalid status provided for record update: " . $status);
        }
    } else {
        $response['message'] = 'Invalid request parameters.';
        error_log("PHP: Invalid parameters for record update: id=" . $record_id . ", status=" . $status);
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #181f2a;
            --sidebar-bg: #232b3e;
            --container-bg: #232b3e;
            --accent: #8f5aff;
            --accent-hover: #7a47e5;
            --text-main: #f4f6fb;
            --text-muted: #b0b8c1;
            --input-bg: #232b3e;
            --input-border: #313a4d;
            --border-radius: 14px;
            --card-bg: #20283a;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        body {
            margin: 0;
            background: var(--primary-bg);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            padding: 32px 0 24px 0;
            border-top-right-radius: var(--border-radius);
            border-bottom-right-radius: var(--border-radius);
        }

        .profile {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 40px;
        }

        .profile-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--input-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--accent);
            margin-bottom: 12px;
        }

        .profile-name {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .profile-role {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .profile-email {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-top: 4px;
        }

        .sidebar-nav {
            flex: 1;
        }

        .sidebar-nav ul {
            list-style: none;
            padding: 0; /* Remove horizontal padding from ul */
            margin: 0;
        }

        .sidebar-nav li {
            margin-bottom: 8px;
            /* Removed background and border-radius from li, a will handle */
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: var(--text-main);
            padding: 12px 32px; /* Content padding for all links */
            border-radius: var(--border-radius); /* All links have rounded corners */
            font-size: 1rem;
            transition: background 0.2s, color 0.2s;
            width: 100%; /* Ensure a fills its li parent */
            box-sizing: border-box; /* Include padding in a's width */
            background: var(--card-bg); /* Default box background for all links */
        }

        .sidebar-nav a.active,
        .sidebar-nav a:hover {
            background: var(--accent); /* Active/hover background */
            color: #fff;
            /* border-radius inherited from .sidebar-nav a, no need to redefine */
            /* Padding is already applied by .sidebar-nav a */
        }

        .sidebar-logout {
            margin-top: 8px; /* Match li margin-bottom for consistent spacing */
            /* Removed text-align: center; as a is display:flex and width:100% */
        }

        .sidebar-logout a {
            color: var(--danger);
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: color 0.2s;
        }

        .main {
            flex: 1;
            padding: 40px 48px;
        }

        .section {
            display: none;
        }

        .section.active {
            display: block;
        }

        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .main-header h1 {
            font-size: 2rem;
            font-weight: 700;
        }

        .stats {
            display: flex;
            gap: 24px;
            margin-bottom: 36px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 24px 32px;
            min-width: 180px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 1.7rem;
            font-weight: 700;
        }

        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }

        .take-attendance-btn {
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--border-radius);
            padding: 12px 28px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 24px;
            transition: background 0.2s, transform 0.2s;
        }

        .take-attendance-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }

        .upcoming {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 12px 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
            border: 1.5px solid #232b3e;
        }

        #takeAttendancePanel {
            min-height: unset;
            margin-bottom: 0;
            border: 2px solid var(--accent);
            background: #232b3e;
            box-shadow: 0 2px 12px rgba(143, 90, 255, 0.08);
            padding: 0 0 24px 0;
            transition: box-shadow 0.2s;
        }

        #takeAttendancePanel h2 {
            margin: 0;
            padding: 18px 24px 0 24px;
            font-size: 1.3rem;
            color: var(--accent);
            font-weight: 700;
        }

        #attendanceStepper {
            margin: 0 24px 0 24px;
        }

        #attendanceLiveTable {
            margin: 0 24px 0 24px;
        }

        #attendanceLiveTable h3 {
            margin-top: 18px;
            margin-bottom: 10px;
        }

        #attendanceSearchEditBar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        #attendanceSearchBox {
            flex: 0 0 220px;
        }

        .class-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .class-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid var(--input-border);
        }

        .class-item:last-child {
            border-bottom: none;
        }

        .class-info {
            display: flex;
            flex-direction: column;
        }

        .class-title {
            font-weight: 600;
            font-size: 1.05rem;
        }

        .class-time {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .class-status {
            padding: 4px 14px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .status-upcoming {
            background: var(--accent);
            color: #fff;
        }

        .status-completed {
            background: var(--success);
            color: #fff;
        }

        .status-cancelled {
            background: var(--danger);
            color: #fff;
        }

        .status-present,
        .status-absent,
        .status-late {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
        }

        @media (max-width: 900px) {
            .dashboard {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                flex-direction: row;
                border-radius: 0;
                padding: 16px 0;
            }

            .main {
                padding: 24px 8px;
            }

            .stats {
                flex-direction: column;
                gap: 16px;
            }

            #takeAttendancePanel,
            .upcoming {
                padding: 8px 4px;
                margin-bottom: 12px;
            }

            #attendanceLiveTable,
            #attendanceStepper {
                margin: 0 4px;
            }
        }

        @media (max-width: 600px) {
            .main-header h1 {
                font-size: 1.2rem;
            }

            .stat-card {
                padding: 16px;
                min-width: 120px;
            }

            .upcoming {
                padding: 12px;
            }
        }

        .profile-settings-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 32px 24px;
            max-width: 1000px;
            margin: 0 auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-settings-avatar {
            font-size: 4rem;
            color: var(--accent);
            margin-bottom: 18px;
        }

        .profile-settings-form {
            width: 100%;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            color: var(--text-main);
            font-weight: 500;
            margin-bottom: 6px;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1.5px solid var(--input-border);
            background: var(--input-bg);
            color: var(--text-main);
            font-size: 1rem;
        }

        .form-group input:focus {
            border-color: var(--accent);
            outline: none;
        }

        .toggle-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 22px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #444c5e;
            border-radius: 22px;
            transition: .4s;
        }

        .toggle-switch input:checked+.slider {
            background-color: var(--accent);
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            border-radius: 50%;
            transition: .4s;
        }

        .toggle-switch input:checked+.slider:before {
            transform: translateX(18px);
        }

        .save-profile-btn {
            width: 100%;
            background: var(--accent);
            color: #fff;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s, transform 0.2s;
        }

        .save-profile-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }

        /* Attendance List */
        #attendanceList {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }

        .student-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid var(--input-border);
            gap: 10px;
        }

        .student-row:last-child {
            border-bottom: none;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--input-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--accent);
        }

        .student-name {
            font-size: 1rem;
            font-weight: 500;
        }

        .attendance-btns {
            display: flex;
            gap: 8px;
        }

        .attendance-btn {
            padding: 7px 16px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .attendance-btn.present {
            background: var(--success);
            color: #fff;
        }

        .attendance-btn.absent {
            background: var(--danger);
            color: #fff;
        }

        .attendance-btn.late {
            background: var(--warning);
            color: #fff;
        }

        .attendance-btn.selected {
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--accent);
            transform: scale(1.05);
        }

        .student-status {
            min-width: 70px;
            text-align: center;
            font-weight: 600;
            border-radius: 8px;
            padding: 4px 10px;
        }

        .status-present {
            background: var(--success);
            color: #fff;
        }

        .status-absent {
            background: var(--danger);
            color: #fff;
        }

        .status-late {
            background: var(--warning);
            color: #fff;
        }

        /* Attendance Stepper */
        #attendanceStepper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 180px;
        }

        .student-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
        }

        .student-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: var(--input-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            color: var(--accent);
        }

        .student-name {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .attendance-btns {
            display: flex;
            gap: 16px;
        }

        .attendance-btn {
            padding: 10px 22px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .attendance-btn.present {
            background: var(--success);
            color: #fff;
        }

        .attendance-btn.absent {
            background: var(--danger);
            color: #fff;
        }

        .attendance-btn.late {
            background: var(--warning);
            color: #fff;
        }

        .attendance-btn:active {
            transform: scale(0.97);
        }

        /* Attendance Summary Table */
        #attendanceSummary table,
        #attendanceSummary th,
        #attendanceSummary td {
            border: 1px solid var(--input-border);
        }

        #attendanceSummary th,
        #attendanceSummary td {
            padding: 8px 4px;
            text-align: left;
        }

        #attendanceSummary td.status-present {
            background: var(--success);
            color: #fff;
        }

        #attendanceSummary td.status-absent {
            background: var(--danger);
            color: #fff;
        }

        #attendanceSummary td.status-late {
            background: var(--warning);
            color: #fff;
        }

        #attendanceSummary th.id-col,
        #attendanceSummary td.id-col {
            width: 80px;
        }

        #attendanceSummary th.course-col,
        #attendanceSummary td.course-col {
            width: 120px;
        }

        .edit-btn {
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 5px 14px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .edit-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }

        .edit-status-btns {
            display: flex;
            gap: 8px;
        }

        .edit-status-btn {
            padding: 5px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .edit-status-btn.present {
            background: var(--success);
            color: #fff;
        }

        .edit-status-btn.absent {
            background: var(--danger);
            color: #fff;
        }

        .edit-status-btn.late {
            background: var(--warning);
            color: #fff;
        }

        .edit-status-btn:active {
            transform: scale(0.97);
        }

        .edit-status-select {
            padding: 6px 10px;
            border-radius: 6px;
            border: 1.5px solid var(--input-border);
            font-size: 1rem;
            font-weight: 600;
        }

        .edit-status-select:focus {
            border-color: var(--accent);
            outline: none;
        }

        .course-semester {
            color: var(--accent);
            font-size: 0.95rem;
            font-weight: 600;
            margin-left: 8px;
        }

        .settings-boxes {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .settings-form {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            min-width: 260px;
            flex: 1 1 260px;
            box-sizing: border-box;
            max-width: 400px;
        }

        @media (max-width: 900px) {
            .settings-boxes {
                flex-direction: column;
                gap: 1.5rem;
            }
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #334155;
        }

        th {
            background-color: #334155;
            font-weight: 600;
        }

        .sidebar-nav a[data-section="logout"] {
            background-color: var(--danger);
            color: #fff;
            font-weight: 600;
            /* border-radius inherited from .sidebar-nav a, no need to redefine */
            transition: background 0.2s, color 0.2s;
            /* Inherit other properties from .sidebar-nav a, no need to redefine flex, padding etc. */
        }

        .sidebar-nav a[data-section="logout"]:hover,
        .sidebar-nav a[data-section="logout"]:focus {
            background-color: #b91c1c;
            color: #fff;
        }

        @media (max-width: 600px) {
            .sidebar-nav a[data-section="logout"] {
                width: 100%;
                text-align: center;
                font-size: 1.1rem;
                padding: 1rem 0.5rem;
            }
        }

        /* Profile & Settings Modern Card Styles */
        .profile-section-card,
        .settings-section-card {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 2.5rem 2rem 2rem 2rem;
            min-width: 320px;
            max-width: 420px;
            margin: 0 auto;
            box-shadow: 0 4px 24px rgba(143, 90, 255, 0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-section-card h2,
        .settings-section-card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #fff;
        }

        .profile-section-card .form-group,
        .settings-section-card .form-group {
            margin-bottom: 1.5rem;
            width: 100%;
        }

        .profile-section-card label,
        .settings-section-card label {
            display: block;
            margin-bottom: 0.5rem;
            color: #f4f6fb;
            font-weight: 500;
        }

        .profile-section-card input,
        .settings-section-card input {
            width: 100%;
            padding: 0.8rem;
            background: #1e293b;
            color: #f4f6fb;
            border: 1px solid #444c5e;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .profile-section-card input:focus,
        .settings-section-card input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .save-profile-btn,
        .save-btn {
            width: 100%;
            padding: 1rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1.25rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 1rem;
            transition: background 0.2s, transform 0.2s;
        }

        .save-profile-btn:hover,
        .save-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px) scale(1.03);
        }

        @media (max-width: 600px) {

            .profile-section-card,
            .settings-section-card {
                min-width: 0;
                width: 100%;
                padding: 1.5rem 0.5rem 1.5rem 0.5rem;
            }

            .save-profile-btn,
            .save-btn {
                font-size: 1.1rem;
                padding: 1rem 0.5rem;
            }
        }

        #saveAttendanceBtn {
            background: var(--accent);
            color: #fff;
            padding: 12px 32px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 8px rgba(143, 90, 255, 0.08);
            margin-bottom: 8px;
        }

        #saveAttendanceBtn:hover,
        #saveAttendanceBtn:active {
            background: var(--accent-hover);
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 4px 16px rgba(143, 90, 255, 0.12);
        }

        @media (max-width: 600px) {
            #saveAttendanceBtn {
                width: 100%;
                font-size: 1.2rem;
                padding: 16px 0;
                border-radius: 12px;
                margin-bottom: 12px;
            }
        }

        /* Specific styling for the logout button */
        .nav-link#logout-btn {
            background-color: var(--danger); /* Red background for logout */
            color: #fff;
            font-weight: 600;
            border-radius: var(--border-radius); /* Rounded corners for logout button */
            transition: background 0.2s, color 0.2s;
            display: flex; /* Ensure it behaves like other flex items */
            align-items: center; /* Center content vertically */
            gap: 14px; /* Space between icon and text */
            text-decoration: none; /* Remove underline */
            padding: 12px 32px; /* Consistent padding */
            font-size: 1rem; /* Consistent font size */
            width: 100%; /* Fill parent width */
            box-sizing: border-box; /* Include padding in width calculation */
        }

        .nav-link#logout-btn:hover,
        .nav-link#logout-btn:focus {
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

        .main.hide-when-attendance {
            filter: blur(4px);
            pointer-events: none;
            user-select: none;
            opacity: 0.3;
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            min-width: 70px; /* Ensure consistent width for all statuses */
            box-sizing: border-box; /* Include padding in the width calculation */
        }

        .status-badge.status-present {
            background: var(--success);
            color: #fff;
        }

        .status-badge.status-absent {
            background: var(--danger);
            color: #fff;
        }

        .status-badge.status-late {
            background: var(--warning);
            color: #fff;
        }

        .status-badge.status-not-marked {
            background: #4b5563;
            color: #fff;
        }

        /* Attendance Records Styling */
        #attendanceRecordsTable {
            margin-bottom: 30px;
        }

        #attendanceRecordsTable table {
            width: 100%;
            border-radius: 8px;
            overflow: hidden;
            border-collapse: separate;
            border-spacing: 0;
        }

        #attendanceRecordsTable th {
            text-align: left;
            padding: 12px 15px;
            background: var(--card-bg);
            color: var(--text-main);
            font-weight: 600;
        }

        #attendanceRecordsTable td {
            padding: 12px 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        #attendanceRecordsTable tr:nth-child(even) {
            background: var(--card-bg);
        }

        #attendanceRecordsTable tr:nth-child(odd) {
            background: var(--container-bg);
        }

        /* Hide attendance panel when taking attendance */
        .hide-when-attendance .section {
            opacity: 0.3;
            pointer-events: none;
        }

        .attendance-btn {
            background-color: var(--accent);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .attendance-btn:hover {
            background-color: var(--accent-hover);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background-color: var(--container-bg);
            margin: 10% auto;
            padding: 20px;
            border-radius: var(--border-radius);
            width: 80%;
            max-width: 800px;
        }

        .close-modal {
            float: right;
            cursor: pointer;
            font-size: 24px;
            color: var(--text-muted);
        }

        .student-list {
            margin-top: 20px;
        }

        .student-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid var(--input-border);
        }

        .attendance-status {
            margin-left: auto;
        }

        .attendance-status select {
            background-color: var(--input-bg);
            color: var(--text-main);
            border: 1px solid var(--input-border);
            padding: 5px;
            border-radius: 4px;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        .course-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .course-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.2rem;
            color: var(--text-main);
        }

        .course-code {
            color: var(--text-muted);
            margin: 0 0 10px 0;
            font-size: 0.95rem;
        }

        .enrolled-students {
            color: var(--text-muted);
            margin: 0 0 15px 0;
            font-size: 0.95rem;
        }

        .course-card .attendance-btn {
            margin-top: auto;
            align-self: flex-end;
            background-color: var(--accent);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .course-card .attendance-btn:hover {
            background-color: var(--accent-hover);
        }
    </style>
</head>

<body>
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div style="background: #10b981; color: #fff; padding: 16px; text-align: center; font-weight: bold; border-radius: 8px; margin: 20px auto; max-width: 600px;">
            <?php echo $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
        </div>
    <?php endif; ?>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="profile">
                <div class="profile-img"><i class="fas fa-user-tie"></i></div>
                <div class="profile-name"><?php echo isset($_SESSION['teacher_name']) ? htmlspecialchars($_SESSION['teacher_name']) : 'Teacher'; ?></div>
                <div class="profile-role">Teacher</div>
                <div class="profile-email" style="color: #b0b8c1; font-size: 0.95rem; margin-top: 4px;">
                    <?php echo isset($_SESSION['teacher_email']) ? htmlspecialchars($_SESSION['teacher_email']) : ''; ?>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" class="active" data-section="dashboard"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="#" data-section="courses"><i class="fas fa-book"></i> Courses</a></li>
                    <li><a href="#" data-section="attendance-records"><i class="fas fa-clipboard-list"></i> Attendance Records</a></li>
                    <li><a href="#" data-section="profile"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="#" data-section="settings"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="logout.php" class="nav-link" id="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a></li>
                </ul>
            </nav>
        </aside>
        <main class="main">
            <!-- Dashboard Section -->
            <section class="section active" id="dashboard">
                <div class="main-header">
                    <h1>Welcome, <?php echo isset($_SESSION['teacher_name']) ? htmlspecialchars($_SESSION['teacher_name']) : 'Teacher'; ?></h1>
                    <span style="color: var(--text-muted); font-size: 1rem;">
                        Today: <?php echo date('l, F j, Y'); // Example: Tuesday, June 11, 2024 
                                ?>
                    </span>
                </div>
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="stat-label">Today's Classes</div>
                        <div class="stat-value"><?php echo count($todays_classes); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-label">Total Students</div>
                        <div class="stat-value"><?php echo $studentsCount; ?></div>
                    </div>
                    <!-- New: Overall Attendance Stat Card -->
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-label">Overall Attendance</div>
                        <div class="stat-value">85%</div> <!-- Placeholder: Make dynamic if needed -->
                    </div>
                </div>
                <div class="upcoming">
                    <h2>Today's Classes</h2>
                    <ul class="class-list">
                        <?php if (count($todays_classes) > 0): ?>
                            <?php foreach ($todays_classes as $class): ?>
                                <li class="class-item">
                                    <div class="class-info">
                                        <span class="class-title"><?= htmlspecialchars($class['title']) ?></span>
                                        <span class="class-time">
                                            <?= date('h:i A', strtotime($class['start_time'])) ?> - <?= date('h:i A', strtotime($class['end_time'])) ?>
                                        </span>
                                    </div>
                                    <button class="take-attendance-btn" data-course-title="<?= htmlspecialchars($class['title']) ?>" data-course-id="<?= $class['course_id'] ?>">Take Attendance</button>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No classes scheduled for today.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- New: Recent Attendance Section -->
                <div class="upcoming" style="margin-top: 24px;">
                    <h2>Recent Attendance</h2>
                    <div style="overflow-x: auto;">
                        <table style="width:100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="padding: 10px 16px; text-align: left; background-color: #334155; font-weight: 600;">Date</th>
                                    <th style="padding: 10px 16px; text-align: left; background-color: #334155; font-weight: 600;">Course</th>
                                    <th style="padding: 10px 16px; text-align: left; background-color: #334155; font-weight: 600;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($attendance_records)): ?>
                                    <?php foreach ($attendance_records as $record): ?>
                                        <tr style="color:#fff; background:var(--card-bg);">
                                            <td style="padding:8px 16px;"><?= htmlspecialchars($record['date']) ?></td>
                                            <td style="padding:8px 16px;"><?= htmlspecialchars($record['course_title']) ?> (<?= htmlspecialchars($record['course_code']) ?>)</td>
                                            <td style="padding:8px 16px;">
                                                <span class="status-badge status-<?= htmlspecialchars(strtolower($record['status'])) ?>">
                                                    <?= htmlspecialchars($record['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr style="color:#fff; background:var(--card-bg);"><td colspan="3" style="text-align:center; padding: 10px;">No recent attendance records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <!-- Courses Section -->
            <section class="section" id="courses">
                <div class="main-header">
                    <h1>My Courses</h1>
                </div>
                <div class="courses-grid">
                    <?php if (!empty($courses)): ?>
                        <?php foreach ($courses as $course): ?>
                            <div class="course-card">
                                <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                                <p class="course-code"><?php echo htmlspecialchars($course['code']); ?></p>
                                <p class="enrolled-students">Enrolled Students: <?php echo $course['enrolled_students']; ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No courses found for this teacher.</p>
                    <?php endif; ?>
                </div>
            </section>
            <!-- Profile Section -->
            <section class="section" id="profile">
                <div class="main-header">
                    <h1>Edit Profile</h1>
                </div>
                <div class="profile-section-card">
                    <h2>Edit Profile</h2>
                    <form class="profile-settings-form" method="POST" action="">
                        <div class="form-group">
                            <label for="profileName">Full Name</label>
                            <input type="text" id="profileName" name="profileName" value="<?php echo isset($_SESSION['teacher_name']) ? htmlspecialchars($_SESSION['teacher_name']) : ''; ?>" required />
                        </div>
                        <div class="form-group">
                            <label for="profileEmail">Email</label>
                            <input type="email" id="profileEmail" name="profileEmail" value="<?php echo isset($_SESSION['teacher_email']) ? htmlspecialchars($_SESSION['teacher_email']) : ''; ?>" required />
                        </div>
                        <button type="submit" class="save-profile-btn">Save Profile</button>
                    </form>
                </div>
            </section>
            <!-- Settings Section -->
            <section class="section" id="settings">
                <div class="main-header">
                    <h1>Change Password</h1>
                </div>
                <div class="settings-section-card">
                    <h2>Change Password</h2>
                    <form id="changePasswordForm" method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group">
                            <label for="currentPassword">Current Password</label>
                            <input type="password" id="currentPassword" name="currentPassword" placeholder="Enter current password" required>
                        </div>
                        <div class="form-group">
                            <label for="newPassword">New Password</label>
                            <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm new password" required>
                        </div>
                        <button type="submit" class="save-btn">Change Password</button>
                    </form>
                </div>
            </section>
            <!-- Attendance Records Section -->
            <section class="section" id="attendance-records">
                <div class="main-header">
                    <h1>Attendance Records</h1>
                </div>
                <div class="section-description" style="margin-bottom:20px;color:var(--text-muted);">
                    View and filter attendance records for all your classes. Use the search and date filters to find specific records.
                </div>
                <div style="display:flex;gap:12px;align-items:center;margin-bottom:18px;flex-wrap:wrap;">
                    <div style="position:relative;flex:1;min-width:250px;">
                        <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                        <input type="text" id="attendanceCourseSearch" placeholder="Search by course name or student..."
                            style="padding:12px 12px 12px 38px;border-radius:8px;border:none;font-size:1rem;background:var(--container-bg);color:var(--text-main);width:100%;">
                    </div>
                    <div style="position:relative;display:flex;align-items:center;background:var(--container-bg);border-radius:8px;padding:0 12px;">
                        <i class="fas fa-calendar" style="color:var(--text-muted);margin-right:8px;"></i>
                        <input type="date" id="attendanceDateFilter"
                            style="padding:12px;border-radius:8px;border:none;font-size:1rem;background:transparent;color:var(--text-main);">
                    </div>
                </div>

                <div id="attendanceRecordsTable"></div>
            </section>
        </main>
    </div>
    <div id="attendanceModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeAttendanceModal()">&times;</span>
            <h2 id="modalTitle">Take Attendance</h2>
            <div id="studentList" class="student-list"></div>
            <button class="attendance-btn" onclick="saveAttendance()" style="margin-top: 20px;">Save Attendance</button>
        </div>
    </div>

    <!-- Add this attendance panel div before the closing body tag -->
    <div id="attendancePanel" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; overflow-y: auto;">
        <div style="background: var(--container-bg); margin: 20px auto; padding: 20px; border-radius: 12px; max-width: 800px; position: relative;">
            <button onclick="closeAttendancePanel()" style="position: absolute; right: 20px; top: 20px; background: none; border: none; color: var(--text-muted); font-size: 24px; cursor: pointer;">&times;</button>
            <div id="attendancePanelContent"></div>
        </div>
    </div>

    <script>
        // Section switching logic
        const navLinks = document.querySelectorAll('.sidebar-nav a');
        const sections = document.querySelectorAll('.section');

        // Function to switch sections
        function switchSection(sectionId) {
            console.log("Switching to section:", sectionId);
            // Remove active class from all sections and links
            sections.forEach(sec => sec.classList.remove('active'));
            navLinks.forEach(link => link.classList.remove('active'));

            // Add active class to selected section and link
            const targetSection = document.getElementById(sectionId);
            const targetLink = document.querySelector(`[data-section="${sectionId}"]`);

            if (targetSection) {
                targetSection.classList.add('active');
                console.log("Section activated:", sectionId);
            } else {
                console.error("Section not found:", sectionId);
            }

            if (targetLink) {
                targetLink.classList.add('active');
                console.log("Link activated for section:", sectionId);
            }

            // Remove blur from main content
            document.querySelector('.main').classList.remove('hide-when-attendance');

            // Hide attendance panel if open
            const panel = document.getElementById('attendancePanel'); // Corrected ID
            if (panel) panel.style.display = 'none';

            // Special handling for specific sections
            if (sectionId === 'dashboard') {
                // No specific action needed on switch, handlers attached on load
            } else if (sectionId === 'attendance-records') {
                updateAttendanceRecordsTable();
            } else if (sectionId === 'courses') {
                // Optional: Add logic here if needed for the courses section after switching
                console.log("Courses section activated.");
            }
        }

        // Add click event listeners to all nav links
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Only prevent default for links that are NOT logout
                if (this.getAttribute('href') === 'logout.php') {
                    return;
                }
                e.preventDefault();

                const sectionId = this.getAttribute('data-section');
                switchSection(sectionId);
            });
        });

        // Add event listeners for attendance search and date filter
        document.getElementById('attendanceCourseSearch')?.addEventListener('input', updateAttendanceRecordsTable);
        document.getElementById('attendanceDateFilter')?.addEventListener('change', updateAttendanceRecordsTable);

        // Initialize the dashboard section on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial active section
            switchSection('dashboard');

            // Attach attendance handlers
            attachAttendanceHandlers();

            // Initialize profile form
            const profileForm = document.querySelector('.profile-settings-form');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    // Add your profile update logic here
                    alert('Profile updated successfully!');
                });
            }

            // Initialize password change form
            const passwordForm = document.getElementById('changePasswordForm');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    // Add your password change logic here
                    alert('Password changed successfully!');
                });
            }
        });

        function attachAttendanceHandlers() {
            console.log("Attaching attendance handlers");
            // Select only the buttons in Today's Classes
            document.querySelectorAll('.take-attendance-btn').forEach(btn => {
                console.log("Found attendance button to attach handler:", btn);

                btn.onclick = function(e) {
                    e.preventDefault();
                    console.log("Attendance button clicked");

                    // Get course details from data attributes
                    const courseId = this.getAttribute('data-course-id');
                    const courseTitle = this.getAttribute('data-course-title');

                    // Get class details if available (for Today's Classes section)
                    const classItem = this.closest('.class-item');
                    const classTitle = classItem ? classItem.querySelector('.class-title').textContent : courseTitle;
                    const classTime = classItem ? classItem.querySelector('.class-time').textContent : '';

                    console.log("Course Title:", courseTitle);
                    console.log("Course ID:", courseId);
                    console.log("Class Title:", classTitle);
                    console.log("Class Time:", classTime);

                    if (!courseId) {
                        alert("Error: Course ID is missing. Cannot take attendance.");
                        return;
                    }

                    // Start the attendance process using the stepper panel
                    startCourseAttendance(courseTitle, classTitle, classTime, courseId);
                };
            });
        }

        function startCourseAttendance(courseTitle, classTitle, classTime, courseId) {
            console.log("Starting attendance for course:", courseTitle, "ID:", courseId);

            // Show loading state
            const panel = document.getElementById('attendancePanel');
            const panelContent = document.getElementById('attendancePanelContent');
            if (panel && panelContent) {
                panel.style.display = 'block';
                // Removed loading message for students
            }

            fetch('teacher-dashboard.php?fetch_students_for_course=1&course_id=' + encodeURIComponent(courseId))
                .then(res => {
                    console.log("Server responded with status:", res.status);
                    if (!res.ok) {
                        throw new Error(`HTTP error! status: ${res.status}`);
                    }
                    return res.text(); // First get the raw text
                })
                .then(text => {
                    console.log("Raw response text (PHP):", text);
                    try {
                        const students = JSON.parse(text); // Then try to parse as JSON
                        console.log("Students fetched:", students);
                        if (!students || students.length === 0) {
                            alert("No students found for this course. Please make sure students are assigned to this course.");
                            closeAttendancePanel();
                            return;
                        }
                        renderAttendancePanel(courseTitle, students, courseId);
                    } catch (e) {
                        console.error("Error parsing JSON from server response:", e, "Raw text:", text);
                        alert("Error processing student data from server. Please check console for details.");
                        closeAttendancePanel();
                    }
                })
                .catch(error => {
                    console.error("Fetch operation failed:", error);
                    alert("Error loading students. Please try again or contact support.");
                    closeAttendancePanel();
                });
        }

        function renderAttendancePanel(courseTitle, students, courseId) {
            const panel = document.getElementById('attendancePanel');
            const panelContent = document.getElementById('attendancePanelContent');

            if (!panel || !panelContent) {
                console.error('Attendance panel elements not found');
                return;
            }

            // Show the panel
            panel.style.display = 'block';

            // Initialize attendance data and store in dataset for multi-function access
            let attendanceResults = students.map(s => ({
                id: s.id,
                name: s.name,
                status: ''
            }));
            panelContent.dataset.attendanceResults = JSON.stringify(attendanceResults);
            panelContent.dataset.courseTitle = courseTitle;
            panelContent.dataset.courseId = courseId;

            // Start with the first student in the stepper view
            renderStudentStep(0);
        }

        function renderStudentStep(index) {
            const panelContent = document.getElementById('attendancePanelContent');
            let attendanceResults = JSON.parse(panelContent.dataset.attendanceResults);
            const courseTitle = panelContent.dataset.courseTitle;
            const courseId = panelContent.dataset.courseId;

            if (index >= attendanceResults.length) {
                renderAttendanceSummary(attendanceResults, courseTitle, courseId);
                return;
            }

            const student = attendanceResults[index];
            panelContent.innerHTML = `
                <div style='display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;'>
                    <h2 style='margin-bottom:20px;color:var(--text-main);'>${courseTitle} - Take Attendance</h2>
                    <div style='background:linear-gradient(135deg,#7c3aed,#8f5aff);width:110px;height:110px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:3.2rem;color:#fff;box-shadow:0 4px 24px rgba(124,58,237,0.18);margin-bottom:18px;'>${student.name[0]}</div>
                    <div style='font-size:1.1rem;color:#b0b8c1;margin-bottom:2px;'>ID: <span style="color:#fff;">${student.id}</span></div>
                    <div style='font-size:1.5rem;font-weight:700;margin-bottom:18px;color:#fff;text-align:center;'>${student.name}</div>
                    <div style='display:flex;flex-direction:column;gap:10px;width:100%;max-width:300px;margin-bottom:24px;'>
                        <button class='att-btn present-btn' style='background:#10b981;border:none;padding:15px;border-radius:8px;font-size:1.1rem;font-weight:600;box-shadow:0 2px 8px rgba(16,185,129,0.12);transition:background 0.2s;width:100%;'>Present</button>
                        <button class='att-btn absent-btn' style='background:#ef4444;border:none;padding:15px;border-radius:8px;font-size:1.1rem;font-weight:600;box-shadow:0 2px 8px rgba(239,68,68,0.12);transition:background 0.2s;width:100%;'>Absent</button>
                    </div>
                </div>
            `;

            // Add click handlers for attendance buttons
            document.querySelector('.present-btn').onclick = () => {
                attendanceResults[index].status = 'Present';
                panelContent.dataset.attendanceResults = JSON.stringify(attendanceResults);
                renderStudentStep(index + 1);
            };
            document.querySelector('.absent-btn').onclick = () => {
                attendanceResults[index].status = 'Absent';
                panelContent.dataset.attendanceResults = JSON.stringify(attendanceResults);
                renderStudentStep(index + 1);
            };
        }

        function renderAttendanceSummary(attendanceResults, courseTitle, courseId) {
            const panelContent = document.getElementById('attendancePanelContent');
            panelContent.innerHTML = `
                <div style='display:flex;flex-direction:column;align-items:center;padding:20px;'>
                    <h2 style='margin-bottom:20px;color:var(--text-main);'>Attendance Summary - ${courseTitle}</h2>
                    <div style='background:#20283a;padding:18px;border-radius:14px;box-shadow:0 2px 12px rgba(143,90,255,0.08);width:100%;max-width:800px;overflow-x:auto;'>
                        <table style='width:100%;border-radius:10px;overflow:hidden;background:#232b3e;min-width:300px;'>
                            <thead style='background:#334155;color:#fff;'>
                                <tr>
                                    <th style='padding:10px 16px;'>ID</th>
                                    <th style='padding:10px 16px;'>Name</th>
                                    <th style='padding:10px 16px;'>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${attendanceResults.map((r, idx) => `
                                    <tr style='color:#fff;'>
                                        <td style='padding:8px 16px;'>${r.id}</td>
                                        <td style='padding:8px 16px;'>${r.name}</td>
                                        <td style='padding:8px 16px;'>
                                            <select class="status-select" data-index="${idx}"
                                                style="padding:6px 10px;border-radius:6px;border:1.5px solid var(--input-border);font-size:1rem;font-weight:600;width:120px;color:white;
                                                ${r.status === 'Present' ? 'background:#10b981;' :
                                                  r.status === 'Absent' ? 'background:#ef4444;' :
                                                  r.status === 'Late' ? 'background:#f59e0b;' :
                                                  'background:var(--input-bg);color:var(--text-muted);'}">
                                                <option value="" ${!r.status ? 'selected' : ''} style="background:var(--input-bg);color:var(--text-muted);">Not Marked</option>
                                                <option value="Present" ${r.status === 'Present' ? 'selected' : ''} style="background:#10b981;color:white;">Present</option>
                                                <option value="Absent" ${r.status === 'Absent' ? 'selected' : ''} style="background:#ef4444;color:white;">Absent</option>
                                                <option value="Late" ${r.status === 'Late' ? 'selected' : ''} style="background:#f59e0b;color:white;">Late</option>
                                            </select>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    <button id="saveAttendanceBtn" style='background:var(--accent);color:#fff;border:none;padding:15px 30px;border-radius:8px;font-size:1.1rem;font-weight:600;margin-top:20px;cursor:pointer;'>
                        Submit Attendance
                    </button>
                </div>
            `;

            // Add change handlers for status dropdowns in the summary view
            document.querySelectorAll('.status-select').forEach(select => {
                select.onchange = function() {
                    const idx = parseInt(this.getAttribute('data-index'));
                    attendanceResults[idx].status = this.value;
                    // Update the select background color based on the new status
                    this.style.background = this.value === 'Present' ? '#10b981' :
                        this.value === 'Absent' ? '#ef4444' :
                        this.value === 'Late' ? '#f59e0b' :
                        'var(--input-bg)';
                    this.style.color = this.value ? 'white' : 'var(--text-muted)';
                };
            });

            // Attach click handler for the submit button
            document.getElementById('saveAttendanceBtn').onclick = () => {
                submitAttendance(attendanceResults, courseId);
            };
        }

        function closeAttendancePanel() {
            const panel = document.getElementById('attendancePanel');
            if (panel) {
                panel.style.display = 'none';
            }
            // Remove blur from main content
            document.querySelector('.main').classList.remove('hide-when-attendance');
        }

        function submitAttendance(attendanceResults, courseId) {
            console.log('--- submitAttendance function called ---');
            console.log('Initial attendanceResults:', attendanceResults);
            console.log('Initial courseId:', courseId);

            // Convert attendance results to the format expected by the server
            const attendanceData = {};
            attendanceResults.forEach(result => {
                if (result.status) {
                    attendanceData[result.id] = result.status;
                }
            });

            console.log('Formatted attendance data to be sent:', attendanceData);

            // Show loading state
            const submitBtn = document.getElementById('saveAttendanceBtn');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                submitBtn.disabled = true;
                console.log('Submit button disabled and loading state set.');
            }

            // Create form data
            const formData = new FormData();
            formData.append('action', 'save_attendance');
            formData.append('course_id', courseId);
            formData.append('attendance', JSON.stringify(attendanceData));
            console.log('FormData created with action, course_id, and attendance.');

            // Send the attendance data to the server
            fetch('teacher-dashboard.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Fetch response received:', response);
                    // Check if the response is OK (status 200-299)
                    if (!response.ok) {
                        console.error('Server responded with non-OK status:', response.status);
                        return response.text().then(text => {
                            console.error('Raw server response text for error:', text);
                            throw new Error('Server error: ' + response.status + ' - ' + text);
                        });
                    }
                    // Attempt to parse as JSON
                    return response.json().catch(error => {
                        console.error('Error parsing JSON response (might not be JSON):', error);
                        return response.text().then(text => {
                            console.error('Raw server response text:', text);
                            throw new Error('Invalid JSON response from server');
                        });
                    });
                })
                .then(data => {
                    console.log('Parsed server data:', data);
                    if (data.success) {
                        // Show success message
                        alert('Attendance saved successfully!');
                        console.log('Attendance saved successfully. Closing panel.');
                        // Close the attendance panel
                        closeAttendancePanel();
                        // Refresh the attendance records table if it's visible
                        if (document.getElementById('attendance-records').classList.contains('active')) {
                            updateAttendanceRecordsTable(true);
                            console.log('Attendance records table refreshed.');
                        }
                    } else {
                        alert('Error saving attendance: ' + (data.message || 'Unknown error'));
                        console.error('Server reported error:', data.message);
                        // Reset submit button
                        if (submitBtn) {
                            submitBtn.innerHTML = 'Submit Attendance';
                            submitBtn.disabled = false;
                            console.log('Submit button reset.');
                        }
                    }
                })
                .catch(error => {
                    console.error('Fetch operation failed:', error);
                    alert('An error occurred during attendance submission: ' + error.message + '. Please check the console for more details.');
                    // Reset submit button
                    if (submitBtn) {
                        submitBtn.innerHTML = 'Submit Attendance';
                        submitBtn.disabled = false;
                        console.log('Submit button reset after fetch error.');
                    }
                });
        }

        // On page load
        renderCourseAttendanceList();

        // Back button functionality
        const backToDashboard = document.getElementById('backToDashboard');
        backToDashboard.addEventListener('click', function() {
            // Hide attendance panel and show course list
            takeAttendancePanel.style.display = 'none';
            document.getElementById('attendanceCourseList').style.display = 'block';
            backToDashboard.style.display = 'none';
        });

        // Courses Data
        const courseList = document.getElementById('courseList');
        const semesterFilter = document.getElementById('semesterFilter');

        // Populate semester filter
        const semesters = Array.from(new Set(courses.map(c => c.semester)));
        semesters.forEach(sem => {
            const opt = document.createElement('option');
            opt.value = sem;
            opt.textContent = sem;
            semesterFilter.appendChild(opt);
        });

        function renderCourses(filter) {
            courseList.innerHTML = '';
            courses.filter(c => filter === 'all' || c.semester === filter).forEach(course => {
                const li = document.createElement('li');
                li.className = 'class-item';
                li.innerHTML = `
                    <div class="class-info">
                        <span class="class-title">${course.name}</span>
                        <span class="class-time">${course.room}</span>
                        <span class="course-semester">${course.semester}</span>
                    </div>
                `;
                
                courseList.appendChild(li);
            });
        }
        semesterFilter.onchange = function() {
            renderCourses(this.value);
        };
        renderCourses('all');

        // 2. JS: Add function to fetch and update attendance records table
        function updateAttendanceRecordsTable(showConfirmation = false) {
            const searchTerm = document.getElementById('attendanceCourseSearch')?.value?.toLowerCase().trim() || '';
            const date = document.getElementById('attendanceDateFilter')?.value || '';
            const params = new URLSearchParams({
                fetch_attendance_records: 1
            });
            if (date) params.append('date', date);

            // Clear content and show loading indicator (if desired, or just clear)
            const container = document.getElementById('attendanceRecordsTable');
            if (container) {
                container.innerHTML = ''; // Clear content to remove previous state and loading message
            }

            fetch('teacher-dashboard.php?' + params.toString())
                .then(res => res.json())
                .then(records => {
                    console.log('Fetched records for attendance table:', records);
                    let filtered = records;
                    if (searchTerm) {
                        filtered = records.filter(rec =>
                            (rec.course_title && rec.course_title.toLowerCase().includes(searchTerm)) ||
                            (rec.course_code && rec.course_code.toLowerCase().includes(searchTerm)) ||
                            (rec.student_name && rec.student_name.toLowerCase().includes(searchTerm))
                        );
                    }

                    let html = '';

                    // Add confirmation message if needed
                    if (showConfirmation) {
                        html += `
                            <div style="background:#10b981;color:white;padding:15px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
                                <i class="fas fa-check-circle" style="font-size:24px;"></i>
                                <div>
                                    <h3 style="margin:0;font-size:18px;">Attendance Submitted Successfully!</h3>
                                    <p style="margin:5px 0 0;">The attendance records have been saved and are displayed below.</p>
                                </div>
                            </div>
                        `;
                    }

                    html += `
                        <div style="background:var(--container-bg);padding:20px;border-radius:12px;margin-bottom:20px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                                <h2 style="margin:0;font-size:18px;">Attendance Summary</h2>
                            </div>
                            <div style="display:flex;gap:15px;flex-wrap:wrap;margin-bottom:10px;">
                                <div style="background:var(--card-bg);padding:15px;border-radius:8px;flex:1;min-width:150px;">
                                    <div style="font-size:14px;color:var(--text-muted);">Total Records</div>
                                    <div style="font-size:24px;font-weight:bold;">${records.length}</div>
                                </div>
                                <div style="background:var(--card-bg);padding:15px;border-radius:8px;flex:1;min-width:150px;">
                                    <div style="font-size:14px;color:var(--text-muted);">Present</div>
                                    <div style="font-size:24px;font-weight:bold;color:var(--success);">${records.filter(r => r.status?.toLowerCase() === 'present').length}</div>
                                </div>
                                <div style="background:var(--card-bg);padding:15px;border-radius:8px;flex:1;min-width:150px;">
                                    <div style="font-size:14px;color:var(--text-muted);">Absent</div>
                                    <div style="font-size:24px;font-weight:bold;color:var(--danger);">${records.filter(r => r.status?.toLowerCase() === 'absent').length}</div>
                                </div>
                                <div style="background:var(--card-bg);padding:15px;border-radius:8px;flex:1;min-width:150px;">
                                    <div style="font-size:14px;color:var(--text-muted);">Late</div>
                                    <div style="font-size:24px;font-weight:bold;color:var(--warning);">${records.filter(r => r.status?.toLowerCase() === 'late').length}</div>
                                </div>
                            </div>
                        </div>
                    `;

                    // Create the table for the records
                    const table = document.createElement('div');
                    table.style.overflowX = 'auto';

                    if (filtered.length === 0) {
                        table.innerHTML = `
                            <div style="text-align:center;padding:20px;">No records found.</div>
                        `;
                    } else {
                        table.innerHTML = `
                            <table id="attendanceRecordsLiveTable" style='width:100%;border-radius:10px;overflow:hidden;background:#232b3e;'>
                                <thead style='background:#334155;color:#fff;'><tr><th style='padding:10px 16px;'>Date</th><th style='padding:10px 16px;'>Course</th><th style='padding:10px 16px;'>Student</th><th style='padding:10px 16px;'>Status</th><th style='padding:10px 16px;'>Actions</th></tr></thead>
                                <tbody>
                                    ${filtered.map(r => `<tr data-record-id='${r.id}' data-student-id='${r.student_id}' data-course-id='${r.course_id}' data-date='${r.date}'>
                                        <td style='padding:8px 16px;'>${r.date}</td>
                                        <td style='padding:8px 16px;'>${r.course_title} (${r.course_code})</td>
                                        <td style='padding:8px 16px;'>${r.student_name}</td>
                                        <td class='status-cell' data-status='${r.status?.toLowerCase()}' style='padding:8px 16px;'>
                                            <span class="status-badge status-${r.status?.toLowerCase()}">${r.status}</span>
                                        </td>
                                        <td class='actions-cell' style='padding:8px 16px;'>
                                            <button class='edit-record-btn' data-id='${r.id}' style='background:var(--accent);color:#fff;border:none;border-radius:6px;padding:5px 14px;font-size:0.95rem;font-weight:500;cursor:pointer;'>Edit</button>
                                        </td>
                                    </tr>`).join('')}
                                </tbody>
                            </table>
                        `;
                    }

                    container.innerHTML += table.outerHTML; // Append the created table div
                    attachEditButtonHandlers(); // Attach handlers after rendering
                })
                .catch(error => {
                    console.error('Error fetching attendance records:', error);
                    container.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: var(--danger);">
                            <i class="fas fa-exclamation-circle" style="font-size: 24px;"></i>
                            <p>Error loading attendance records: ${error.message}</p>
                        </div>
                    `;
                });
        }

        // New function to attach handlers to edit buttons
        function attachEditButtonHandlers() {
            document.querySelectorAll('.edit-record-btn').forEach(button => {
                button.onclick = function() {
                    const recordId = this.getAttribute('data-id');
                    console.log(`Edit button clicked for record ID: ${recordId}`);
                    const row = this.closest('tr');
                    toggleEditMode(row, recordId);
                };
            });
        }

        // Function to toggle edit mode for a row
        function toggleEditMode(row, recordId) {
            console.log(`Entering edit mode for record ID: ${recordId}`);
            const statusCell = row.querySelector('.status-cell');
            const actionsCell = row.querySelector('.actions-cell');
            const currentStatus = statusCell.getAttribute('data-status');
            console.log(`Current status from data-status: ${currentStatus}`);

            // Replace status span with a select dropdown
            statusCell.innerHTML = `
                <select class="edit-status-select" style="padding:6px 10px;border-radius:6px;border:1.5px solid var(--input-border);font-size:1rem;font-weight:600;">
                    <option value="Present">Present</option>
                    <option value="Absent">Absent</option>
                    <option value="Late">Late</option>
                </select>
            `;
            // Set the current status as selected
            const selectedStatus = currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1);
            statusCell.querySelector('select').value = selectedStatus;
            console.log(`Setting select value to: ${selectedStatus}`);

            // Replace Edit button with Save and Cancel
            actionsCell.innerHTML = `
                <button class="save-record-btn" data-id="${recordId}" style="background:var(--success);color:#fff;border:none;border-radius:6px;padding:5px 14px;font-size:0.95rem;font-weight:500;cursor:pointer;margin-right:5px;">Save</button>
                <button class="cancel-edit-btn" style="background:var(--danger);color:#fff;border:none;border-radius:6px;padding:5px 14px;font-size:0.95rem;font-weight:500;cursor:pointer;">Cancel</button>
            `;

            // Add event listeners for Save and Cancel buttons
            actionsCell.querySelector('.save-record-btn').onclick = function(event) {
                event.stopPropagation(); // Prevent event from bubbling up
                const newStatus = statusCell.querySelector('select').value;
                saveEditedAttendance(recordId, newStatus);
            };
            actionsCell.querySelector('.cancel-edit-btn').onclick = function(event) {
                event.stopPropagation(); // Prevent event from bubbling up
                // Revert changes and exit edit mode
                const originalStatus = row.getAttribute('data-status-original') || currentStatus; // Use original or current if no original saved
                statusCell.innerHTML = `<span class="status-badge status-${originalStatus}">${originalStatus.charAt(0).toUpperCase() + originalStatus.slice(1)}</span>`;
                actionsCell.innerHTML = `<button class='edit-record-btn' data-id='${recordId}' style='background:var(--accent);color:#fff;border:none;border-radius:6px;padding:5px 14px;font-size:0.95rem;font-weight:500;cursor:pointer;'>Edit</button>`;
                attachEditButtonHandlers(); // Re-attach handlers for the Edit button
            };
            // Store original status to revert on cancel
            row.setAttribute('data-status-original', currentStatus);
        }

        // New function to save edited attendance via AJAX
        function saveEditedAttendance(recordId, newStatus) {
            console.log(`--- saveEditedAttendance function called ---`);
            console.log(`Record ID being sent (JS): ${recordId}`);
            console.log(`New status being sent (JS): ${newStatus}`);

            const requestBody = `action=update_attendance_record&id=${encodeURIComponent(recordId)}&status=${encodeURIComponent(newStatus)}`;
            console.log(`Request body (JS): ${requestBody}`);

            fetch('teacher-dashboard.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: requestBody
                })
                .then(response => {
                    console.log('Fetch response for single record update:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('Parsed data for single record update:', data);
                    if (data.success) {
                        alert('Attendance record updated successfully!');
                        // Refresh the table or update the row directly
                        updateAttendanceRecordsTable(); // Refresh the whole table
                    } else {
                        alert('Error updating attendance record: ' + (data.message || 'Unknown error'));
                        updateAttendanceRecordsTable(); // Refresh to revert incorrect state
                    }
                })
                .catch(error => {
                    console.error('Error saving edited attendance:', error);
                    alert('Error saving attendance. Please try again.');
                    updateAttendanceRecordsTable(); // Refresh to revert incorrect state
                });
        }

        let currentCourseId = null;

        function openAttendanceModal(courseId, courseTitle) {
            currentCourseId = courseId;
            document.getElementById('modalTitle').textContent = `Take Attendance - ${courseTitle}`;
            document.getElementById('attendanceModal').style.display = 'block';

            // Fetch students for this course
            fetch(`teacher-dashboard.php?fetch_students_for_course=1&course_id=${courseId}`)
                .then(response => response.json())
                .then(students => {
                    const studentList = document.getElementById('studentList');
                    studentList.innerHTML = '';

                    students.forEach(student => {
                        const studentItem = document.createElement('div');
                        studentItem.className = 'student-item';
                        studentItem.innerHTML = `
                            <span>${student.name}</span>
                            <div class="attendance-status">
                                <select name="attendance[${student.id}]">
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                </select>
                            </div>
                        `;
                        studentList.appendChild(studentItem);
                    });
                });
        }

        function saveAttendance() {
            const attendanceData = {};
            const selects = document.querySelectorAll('select[name^="attendance"]');
            selects.forEach(select => {
                const studentId = select.name.match(/\[(\d+)\]/)[1];
                attendanceData[studentId] = select.value;
            });

            fetch('teacher-dashboard.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=save_attendance&course_id=${currentCourseId}&attendance=${JSON.stringify(attendanceData)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Attendance saved successfully!');
                        closeAttendanceModal();
                        // Refresh the attendance records table if it's visible
                        if (document.getElementById('attendance-records').classList.contains('active')) {
                            updateAttendanceRecordsTable(true);
                        }
                    } else {
                        alert('Error saving attendance. Please try again.');
                    }
                });
        }

        function closeAttendanceModal() {
            document.getElementById('attendanceModal').style.display = 'none';
            currentCourseId = null;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('attendanceModal');
            if (event.target == modal) {
                closeAttendanceModal();
            }
        }

        // ... existing code ...
        function renderStudentStep(index) {
            const panelContent = document.getElementById('attendancePanelContent');
            const attendanceResults = JSON.parse(panelContent.dataset.attendanceResults);
            const courseTitle = panelContent.dataset.courseTitle;
            const courseId = panelContent.dataset.courseId;

            if (index >= attendanceResults.length) {
                renderAttendanceSummary(attendanceResults, courseTitle, courseId);
                return;
            }

            const student = attendanceResults[index];
            panelContent.innerHTML = `
                <div style='display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;'>
                    <h2 style='margin-bottom:20px;color:var(--text-main);'>${courseTitle} - Take Attendance</h2>
                    <div style='background:linear-gradient(135deg,#7c3aed,#8f5aff);width:110px;height:110px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:3.2rem;color:#fff;box-shadow:0 4px 24px rgba(124,58,237,0.18);margin-bottom:18px;'>${student.name[0]}</div>
                    <div style='font-size:1.1rem;color:#b0b8c1;margin-bottom:2px;'>ID: <span style="color:#fff;">${student.id}</span></div>
                    <div style='font-size:1.5rem;font-weight:700;margin-bottom:18px;color:#fff;text-align:center;'>${student.name}</div>
                    <div style='display:flex;flex-direction:column;gap:10px;width:100%;max-width:300px;margin-bottom:24px;'>
                        <button class='att-btn present-btn' style='background:#10b981;border:none;padding:15px;border-radius:8px;font-size:1.1rem;font-weight:600;box-shadow:0 2px 8px rgba(16,185,129,0.12);transition:background 0.2s;width:100%;'>Present</button>
                        <button class='att-btn absent-btn' style='background:#ef4444;border:none;padding:15px;border-radius:8px;font-size:1.1rem;font-weight:600;box-shadow:0 2px 8px rgba(239,68,68,0.12);transition:background 0.2s;width:100%;'>Absent</button>
                    </div>
                </div>
            `;

            // Add click handlers for attendance buttons
            document.querySelector('.present-btn').onclick = () => {
                attendanceResults[index].status = 'Present';
                panelContent.dataset.attendanceResults = JSON.stringify(attendanceResults);
                renderStudentStep(index + 1);
            };
            document.querySelector('.absent-btn').onclick = () => {
                attendanceResults[index].status = 'Absent';
                panelContent.dataset.attendanceResults = JSON.stringify(attendanceResults);
                renderStudentStep(index + 1);
            };
        }

        function renderAttendanceSummary(attendanceResults, courseTitle, courseId) {
            const panelContent = document.getElementById('attendancePanelContent');
            panelContent.innerHTML = `
                <div style='display:flex;flex-direction:column;align-items:center;padding:20px;'>
                    <h2 style='margin-bottom:20px;color:var(--text-main);'>Attendance Summary - ${courseTitle}</h2>
                    <div style='background:#20283a;padding:18px;border-radius:14px;box-shadow:0 2px 12px rgba(143,90,255,0.08);width:100%;max-width:800px;overflow-x:auto;'>
                        <table style='width:100%;border-radius:10px;overflow:hidden;background:#232b3e;min-width:300px;'>
                            <thead style='background:#334155;color:#fff;'>
                                <tr>
                                    <th style='padding:10px 16px;'>ID</th>
                                    <th style='padding:10px 16px;'>Name</th>
                                    <th style='padding:10px 16px;'>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${attendanceResults.map((r, idx) => `
                                    <tr style='color:#fff;'>
                                        <td style='padding:8px 16px;'>${r.id}</td>
                                        <td style='padding:8px 16px;'>${r.name}</td>
                                        <td style='padding:8px 16px;'>
                                            <select class="status-select" data-index="${idx}"
                                                style="padding:6px 10px;border-radius:6px;border:1.5px solid var(--input-border);font-size:1rem;font-weight:600;width:120px;color:white;
                                                ${r.status === 'Present' ? 'background:#10b981;' :
                                                  r.status === 'Absent' ? 'background:#ef4444;' :
                                                  r.status === 'Late' ? 'background:#f59e0b;' :
                                                  'background:var(--input-bg);color:var(--text-muted);'}">
                                                <option value="" ${!r.status ? 'selected' : ''} style="background:var(--input-bg);color:var(--text-muted);">Not Marked</option>
                                                <option value="Present" ${r.status === 'Present' ? 'selected' : ''} style="background:#10b981;color:white;">Present</option>
                                                <option value="Absent" ${r.status === 'Absent' ? 'selected' : ''} style="background:#ef4444;color:white;">Absent</option>
                                                <option value="Late" ${r.status === 'Late' ? 'selected' : ''} style="background:#f59e0b;color:white;">Late</option>
                                            </select>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    <button id="saveAttendanceBtn" style='background:var(--accent);color:#fff;border:none;padding:15px 30px;border-radius:8px;font-size:1.1rem;font-weight:600;margin-top:20px;cursor:pointer;'>
                        Submit Attendance
                    </button>
                </div>
            `;

            // Add change handlers for status dropdowns in the summary view
            document.querySelectorAll('.status-select').forEach(select => {
                select.onchange = function() {
                    const idx = parseInt(this.getAttribute('data-index'));
                    attendanceResults[idx].status = this.value;
                    // Update the select background color based on the new status
                    this.style.background = this.value === 'Present' ? '#10b981' :
                        this.value === 'Absent' ? '#ef4444' :
                        this.value === 'Late' ? '#f59e0b' :
                        'var(--input-bg)';
                    this.style.color = this.value ? 'white' : 'var(--text-muted)';
                };
            });

            // Attach click handler for the submit button
            document.getElementById('saveAttendanceBtn').onclick = () => {
                submitAttendance(attendanceResults, courseId);
            };
        }

        function closeAttendancePanel() {
            const panel = document.getElementById('attendancePanel');
            if (panel) {
                panel.style.display = 'none';
            }
            // Remove blur from main content
            document.querySelector('.main').classList.remove('hide-when-attendance');
        }
    </script>
</body>

</html>