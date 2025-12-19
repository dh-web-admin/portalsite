<?php
// Migration: Add a new column to the existing Projects table
// Usage: Run this script once via CLI or browser (then delete it for safety)

require_once __DIR__ . '/../config/config.php';

// Change these values as needed:
$columnName = 'coluy,mn';
$columnType = 'VARCHAR(255) NULL'; // Adjust type as needed

try {
    $sql = "ALTER TABLE Projects ADD COLUMN `$columnName` $columnType";
    $pdo->exec($sql);
    echo "Column '$columnName' added to Projects table successfully.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column '$columnName' already exists in Projects table.";
    } else {
        echo "Error adding column: " . $e->getMessage();
    }
}
