<?php
session_start();

// Store the user type before destroying session
$userType = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Redirect based on user type
switch ($userType) {
    case 'admin':
        header('Location: admin_login.php');
        break;
    case 'student':
        header('Location: student_login.php');
        break;
    case 'teacher':
        header('Location: teacher_login.php');
        break;
    default:
        // If no user type is set, redirect to main login page
        header('Location: index.php');
        break;
}
exit;
?>
