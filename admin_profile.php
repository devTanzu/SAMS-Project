<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Database connection
$db = new mysqli('localhost', 'root', '', 'attendance_system');
if ($db->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $db->connect_error]));
}

// Use your actual admin id
$admin_id = 3; // or: $_SESSION['admin_id']

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $username = trim($_POST['name'] ?? ''); // 'name' field in form is actually username
        $email = trim($_POST['email'] ?? '');
        if (!$username || !$email) {
            echo json_encode(['error' => 'Username and email are required.']);
            exit;
        }
        // Check for duplicate email
        $stmt = $db->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ?");
        $stmt->bind_param('si', $email, $admin_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(['error' => 'Email already in use.']);
            exit;
        }
        $stmt->close();
        $stmt = $db->prepare("UPDATE admin_users SET username = ?, email = ? WHERE id = ?");
        $stmt->bind_param('ssi', $username, $email, $admin_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to update profile.']);
        }
        exit;
    }

    if ($action === 'change_password') {
        $current = $_POST['currentPassword'] ?? '';
        $new = $_POST['newPassword'] ?? '';
        $confirm = $_POST['confirmPassword'] ?? '';
        if (!$current || !$new || !$confirm) {
            echo json_encode(['error' => 'All password fields are required.']);
            exit;
        }
        if ($new !== $confirm) {
            echo json_encode(['error' => 'New passwords do not match.']);
            exit;
        }
        if (strlen($new) < 8) {
            echo json_encode(['error' => 'New password must be at least 8 characters.']);
            exit;
        }
        $stmt = $db->prepare("SELECT password FROM admin_users WHERE id = ?");
        $stmt->bind_param('i', $admin_id);
        $stmt->execute();
        $stmt->bind_result($hash);
        $stmt->fetch();
        $stmt->close();
        if (!password_verify($current, $hash)) {
            echo json_encode(['error' => 'Current password is incorrect.']);
            exit;
        }
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $newHash, $admin_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to change password.']);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("SELECT username, email FROM admin_users WHERE id = ?");
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $stmt->bind_result($username, $email);
    if ($stmt->fetch()) {
        echo json_encode(['name' => $username, 'email' => $email]);
    } else {
        echo json_encode(['error' => 'Admin not found.']);
    }
    $stmt->close();
    exit;
}

echo json_encode(['error' => 'Invalid request.']);
