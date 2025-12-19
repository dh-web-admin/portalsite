<?php
// Migration: Add updated_at column to Projects table for SSE logic
// Usage: Run this script once via CLI or browser (then delete it for safety)

require_once __DIR__ . '/../config/config.php';

try {
    // Check if column already exists
    $exists = false;
    $stmt = $pdo->query("SHOW COLUMNS FROM Projects LIKE 'updated_at'");
    if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
        $exists = true;
    }
    if ($exists) {
        echo "<div style='color:blue'>Column 'updated_at' already exists in Projects table.</div>";
    } else {
        $sql = "ALTER TABLE Projects ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        $pdo->exec($sql);
        echo "<div style='color:green'>Column 'updated_at' added to Projects table successfully.</div>";
    }
} catch (PDOException $e) {
    echo "<div style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
