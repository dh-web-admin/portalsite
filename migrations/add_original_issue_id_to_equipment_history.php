<?php
/**
 * Migration: Add original_issue_id column to equipment_history table
 *
 * Run this file once in your browser or via PHP CLI:
 *   php migrations/add_original_issue_id_to_equipment_history.php
 */

require_once __DIR__ . '/../config/config.php';

try {
    // Check if column already exists
    $exists = false;
    $result = $conn->query("SHOW COLUMNS FROM equipment_history LIKE 'original_issue_id'");
    if ($result && $result->num_rows > 0) {
        $exists = true;
    }
    
    if ($exists) {
        echo "<div style='color:blue'>Column 'original_issue_id' already exists in equipment_history table.</div>";
    } else {
        $sql = "ALTER TABLE equipment_history ADD COLUMN original_issue_id INT NULL AFTER is_edited_copy";
        if ($conn->query($sql) === TRUE) {
            echo "<div style='color:green'>Column 'original_issue_id' added to equipment_history table successfully.</div>";
        } else {
            echo "<div style='color:red'>Error: " . htmlspecialchars($conn->error) . "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

if (isset($conn)) {
    $conn->close();
}
?>

