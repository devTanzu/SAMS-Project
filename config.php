<?php
$host = 'localhost';
$db   = 'attendance_system'; // Replace with your actual DB name
$user = 'root';
$pass = ''; // Leave it empty if you're using the default MySQL user with no password

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
