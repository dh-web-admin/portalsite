<?php
/**
 * Migration: Create sso_tokens table for short-lived SSO tokens
 *
 * Run this file once in your browser or via PHP CLI to create the table.
 */

require_once __DIR__ . '/../config/config.php';

$sql = "CREATE TABLE IF NOT EXISTS sso_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(128) NOT NULL,
    expires_at INT NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at),
    INDEX idx_user (user_id)
)";

try {
    if ($conn->query($sql) === TRUE) {
        echo "<h2 style='color: green;'>✓ sso_tokens table created successfully!</h2>";
        echo "<p>The SSO token table is ready.</p>";
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
