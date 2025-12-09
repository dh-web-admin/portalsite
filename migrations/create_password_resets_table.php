<?php
/**
 * Migration: Create password_resets table for password reset system
 * 
 * This file creates the table needed for the password reset functionality.
 * Run this file once in your browser or via PHP CLI.
 */

require_once __DIR__ . '/../config/config.php';

$sql = "CREATE TABLE IF NOT EXISTS password_resets (
    email VARCHAR(255) PRIMARY KEY,
    code VARCHAR(10) NOT NULL,
    expires_at INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at)
)";

try {
    if ($conn->query($sql) === TRUE) {
        echo "<h2 style='color: green;'>✓ password_resets table created successfully!</h2>";
        echo "<p>The password reset system is ready to use.</p>";
    } else {
        echo "<h2 style='color: red;'>✗ Error creating table:</h2>";
        echo "<p>" . htmlspecialchars($conn->error) . "</p>";
    }
} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ Exception:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

$conn->close();
?>
