<?php
/**
 * Migration: Add client profile columns
 *
 * Run this file once in your browser or via PHP CLI:
 *   php migrations/add_client_profile_columns.php
 */

require_once __DIR__ . '/../config/config.php';

$columns = [
    'client_type VARCHAR(120) NULL',
    'union_status VARCHAR(40) NULL',
    'contact_name VARCHAR(160) NULL',
    'contact_phone VARCHAR(40) NULL',
    'website VARCHAR(255) NULL'
];

foreach ($columns as $col) {
    $colName = explode(' ', $col)[0];
    $exists = $conn->query("SHOW COLUMNS FROM clients LIKE '" . $conn->real_escape_string($colName) . "'");
    if ($exists && $exists->num_rows === 0) {
        $sql = "ALTER TABLE clients ADD COLUMN $col";
        if ($conn->query($sql) === TRUE) {
            echo "<p style='color:green;'>Added column: $col</p>\n";
        } else {
            echo "<p style='color:red;'>Error adding $col: " . htmlspecialchars($conn->error) . "</p>\n";
        }
    } else {
        echo "<p style='color:gray;'>Column $colName already exists.</p>\n";
    }
}

$conn->close();
?>
