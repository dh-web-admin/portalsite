<?php
// Migration: Add original_issue_id column to equipment_history table
require_once __DIR__ . '/../config/config.php';

$sql = "ALTER TABLE equipment_history ADD COLUMN original_issue_id INT NULL AFTER is_edited_copy";

if ($conn->query($sql) === TRUE) {
    echo "Column original_issue_id added successfully to equipment_history table.\n";
} else {
    // Check if column already exists
    if (strpos($conn->error, 'Duplicate column name') !== false) {
        echo "Column original_issue_id already exists in equipment_history table.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
}

$conn->close();

