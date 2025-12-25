<?php
require_once __DIR__ . '/../config/config.php';

$columns = [
    'dhcst_equipment_number VARCHAR(50) NULL',
    'dhss_equipment_number VARCHAR(50) NULL',
    'type VARCHAR(100) NULL',
    'vehicle_year VARCHAR(10) NULL',
    'make VARCHAR(100) NULL',
    'model VARCHAR(100) NULL'
];

foreach ($columns as $col) {
    $colName = explode(' ', $col)[0];
    $exists = $conn->query("SHOW COLUMNS FROM equipments LIKE '" . $colName . "'");
    if ($exists && $exists->num_rows === 0) {
        $sql = "ALTER TABLE equipments ADD COLUMN $col";
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