<?php
// Migration: add filter_hours column to filter_info and backfill

require_once __DIR__ . '/../config/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

header('Content-Type: text/plain; charset=utf-8');

echo "Running migration: add filter_hours to filter_info\n";

try {
    // 1) Add column if missing
    $check = $conn->query("SHOW COLUMNS FROM filter_info LIKE 'filter_hours'");
    if ($check->num_rows === 0) {
        $conn->query("ALTER TABLE filter_info ADD COLUMN filter_hours DECIMAL(10,1) NULL AFTER filter_life");
        echo "Added column filter_hours\n";
    } else {
        echo "Column filter_hours already exists\n";
    }
    $check->close();

    // 2) Backfill: store derived hours for existing rows
    $conn->query(
        "UPDATE filter_info fi " .
        "JOIN equipments e ON e.equipment_id = fi.equipment_id " .
        "SET fi.filter_hours = GREATEST(0, COALESCE(e.current_hours,0) - COALESCE(fi.hours,0))"
    );
    echo "Backfill complete\n";

    echo "Done.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
