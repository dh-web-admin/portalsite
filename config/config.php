<?php
/**
 * Database Configuration
 * Works on both Railway (production) and local development
 */

// Check if running on Railway (production)
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;

if ($isProduction) {
    // Railway production - use environment variables
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'railway';
    $port = getenv('DB_PORT') ?: 3306;
} else {
    // Local development - XAMPP settings
    $host = 'localhost';
    $user = 'root';
    $password = '';
    $database = 'dhdatabase';
    $port = 3306;
}

// Create database connection
$conn = new mysqli($host, $user, $password, $database, $port);

// Check connection
if ($conn->connect_error) {
    if ($isProduction) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Database connection failed. Please contact support.");
    } else {
        die("Connection failed: " . $conn->connect_error);
    }
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

?>
