<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$db = new mysqli('localhost', 'root', '', 'attendance_system');
if ($db->connect_error) {
    die("Database connection failed: " . $db->connect_error);
}

$studentsCount = $db->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'];
$teachersCount = $db->query("SELECT COUNT(*) as total FROM teachers")->fetch_assoc()['total'];
$coursesCount = $db->query("SELECT COUNT(*) as total FROM courses")->fetch_assoc()['total'];

// Create attendance_records table if it doesn't exist
$createTable = "CREATE TABLE IF NOT EXISTS attendance_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (student_id, course_id, date)
)";

if (!$db->query($createTable)) {
    die("Error creating table: " . $db->error);
}

// Handle AJAX actions
if (isset($_POST['action']) || isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'];

    if ($action === 'fetch_report') {
        $reportType = $_GET['reportType'] ?? 'monthly';
        $startDate = $_GET['startDate'] ?? null;
        $endDate = $_GET['endDate'] ?? null;
        $month = $_GET['month'] ?? null;
        $semester = $_GET['semester'] ?? null;
        $year = $_GET['year'] ?? null;
        $studentId = $_GET['studentId'] ?? null;
        $courseId = $_GET['courseId'] ?? null;

        // Build date range based on report type
        if ($reportType === 'monthly' && $month && $year) {
            $startDate = "$year-$month-01";
            $endDate = date('Y-m-t', strtotime($startDate));
        } elseif ($reportType === 'semester' && $semester && $year) {
            switch ($semester) {
                case 'spring':
                    $startDate = "$year-01-01";
                    $endDate = "$year-04-30";
                    break;
                case 'summer':
                    $startDate = "$year-05-01";
                    $endDate = "$year-08-31";
                    break;
                case 'fall':
                    $startDate = "$year-09-01";
                    $endDate = "$year-12-31";
                    break;
            }
        } elseif ($reportType === 'all') {
            // No date filters for 'all' report type
            $startDate = null;
            $endDate = null;
        }

        // Build the query
        $query = "
            SELECT 
                s.id as student_id,
                s.name as student_name,
                COUNT(ar.id) as total_classes,
                SUM(CASE WHEN ar.status = 'present' OR ar.status = 'late' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count
            FROM students s
            LEFT JOIN attendance_records ar ON s.id = ar.student_id
            WHERE 1=1
        ";

        $params = [];
        $types = "";

        if ($startDate && $endDate) {
            $query .= " AND ar.date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= "ss";
        }

        if ($reportType === 'course_attendance') {
            $query = "
                SELECT 
                    c.id as id,
                    c.code as course_code,
                    c.title as course_title,
                    COUNT(ar.id) as total_classes,
                    SUM(CASE WHEN ar.status = 'present' OR ar.status = 'late' THEN 1 ELSE 0 END) as total_present,
                    SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as total_absent
                FROM courses c
                LEFT JOIN attendance_records ar ON c.id = ar.course_id
                WHERE 1=1
            ";
            if ($courseId) {
                $query .= " AND c.id = ?";
                $params[] = $courseId;
                $types .= "i";
            }
            $query .= " GROUP BY c.id, c.code, c.title ORDER BY c.title";
        } else { // Student reports
            if ($studentId) {
                $query .= " AND s.id = ?";
                $params[] = $studentId;
                $types .= "i";
            }
            $query .= " GROUP BY s.id, s.name ORDER BY s.name";
        }

        $stmt = $db->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $attendanceData = [];
        while ($row = $result->fetch_assoc()) {
            if ($reportType === 'course_attendance') {
                $total = $row['total_classes'] ?: 0;
                $present = $row['total_present'] ?: 0;
                $absent = $row['total_absent'] ?: 0;
                $percentage = $total > 0 ? round(($present / $total) * 100) : 0;
                $attendanceData[] = [
                    'id' => $row['id'],
                    'course_code' => $row['course_code'],
                    'course_title' => $row['course_title'],
                    'total_classes' => $total,
                    'total_present' => $present,
                    'total_absent' => $absent,
                    'percentage' => $percentage
                ];
            } else { // Existing student report logic
                $total = $row['total_classes'] ?: 0;
                $present = $row['present_count'] ?: 0;
                $absent = $row['absent_count'] ?: 0;
                $percentage = $total > 0 ? round(($present / $total) * 100) : 0;
    
                $attendanceData[] = [
                    'student_id' => $row['student_id'],
                    'name' => $row['student_name'],
                    'total_classes' => $total,
                    'present' => $present,
                    'absent' => $absent,
                    'percentage' => $percentage
                ];
            }
        }

        echo json_encode($attendanceData);
        exit;
    }

    if ($action === 'search_student') {
        $searchTerm = $_GET['searchTerm'] ?? '';
        
        $query = "
            SELECT id, name, department
            FROM students
            WHERE id LIKE ? OR name LIKE ?
            LIMIT 10
        ";
        
        $searchPattern = "%$searchTerm%";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ss", $searchPattern, $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();

        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'department' => $row['department']
            ];
        }

        echo json_encode($students);
        exit;
    }

    if ($action === 'search_course') {
        $searchTerm = $_GET['searchTerm'] ?? '';
        
        $query = "
            SELECT id, code, title
            FROM courses
            WHERE code LIKE ? OR title LIKE ?
            LIMIT 10
        ";
        
        $searchPattern = "%$searchTerm%";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ss", $searchPattern, $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();

        $courses = [];
        while ($row = $result->fetch_assoc()) {
            $courses[] = [
                'id' => $row['id'],
                'code' => $row['code'],
                'title' => $row['title']
            ];
        }

        echo json_encode($courses);
        exit;
    }

    if ($action === 'export_excel') {
        // TODO: Implement Excel export
        echo json_encode(['error' => 'Excel export not implemented yet']);
        exit;
    }

    if ($action === 'export_pdf') {
        // TODO: Implement PDF export
        echo json_encode(['error' => 'PDF export not implemented yet']);
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
    exit;
}

// After inserting teacher and getting $teacher_id
foreach ($assigned_course_ids as $course_id) {
    // For example, schedule every course at 9am for the next 5 days
    for ($i = 0; $i < 5; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        $stmt = $db->prepare("INSERT INTO class_schedules (teacher_id, course_id, class_date, start_time, end_time) VALUES (?, ?, ?, '09:00:00', '10:00:00')");
        $stmt->bind_param('iis', $teacher_id, $course_id, $date);
        $stmt->execute();
    }
}
?> 