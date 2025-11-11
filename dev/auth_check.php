<?php
/**
 * Developer Authentication Check
 * Include this at the top of dev folder pages to restrict access to developers only
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    // Not logged in - redirect to login page
    header('Location: ../auth/login.php');
    exit();
}

// Include database configuration
require_once __DIR__ . '/../config/config.php';

// Check if user is a developer
$email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Verify user is a developer
if (!$user || $user['role'] !== 'developer') {
    // Not a developer - redirect to main dashboard
    header('Location: ../pages/dashboard/index.php');
    exit();
}

// User is authenticated as developer - allow access
?>
