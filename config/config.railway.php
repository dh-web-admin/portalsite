<?php
/**
 * Database Configuration for Railway Deployment
 * 
 * This file checks if running on Railway (production) or locally (development)
 * and uses the appropriate database configuration
 */

// Check if we're on Railway (environment variables will be set)
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;

if ($isProduction) {
    // Production (Railway) - Use environment variables
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'dhdatabase';
    $port = getenv('DB_PORT') ?: 3306;
} else {
    // Local development - Use local XAMPP settings
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
    // In production, log error; in development, show it
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
