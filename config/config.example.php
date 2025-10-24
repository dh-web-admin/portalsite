<?php
/**
 * Database Configuration Template
 * 
 * INSTRUCTIONS:
 * 1. Copy this file and rename it to 'config.php'
 * 2. Update the values below with your actual database credentials
 * 3. DO NOT commit config.php to GitHub (it's in .gitignore)
 */

// Database Configuration
$host = 'localhost';           // Database host (usually 'localhost')
$user = 'your_db_username';    // Your database username
$password = 'your_db_password'; // Your database password
$database = 'your_db_name';    // Your database name

// Create database connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Set charset to UTF-8
$conn->set_charset("utf8mb4");

?>
