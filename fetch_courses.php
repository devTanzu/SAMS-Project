<?php
require_once 'config.php';

// Prevent any output before JSON response
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

// Optionally filter by department (if sent as GET param)
$department = isset($_GET['department']) ? $_GET['department'] : '';

try {
    if ($department) {
        $stmt = $conn->prepare("SELECT id, title, department FROM courses WHERE department = ?");
        $stmt->bind_param("s", $department);
    } else {
        $stmt = $conn->prepare("SELECT id, title, department FROM courses");
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    echo json_encode($courses);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while fetching courses: ' . $e->getMessage()]);
    exit;
}
