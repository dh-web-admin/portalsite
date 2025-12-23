<?php
// Migration: Create equipment_history table for equipment maintenance records
require_once __DIR__ . '/../config/config.php';

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS equipment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    date_reported DATE NOT NULL,
    reported_issues TEXT,
    reported_by VARCHAR(128),
    equipment_location VARCHAR(255),
    operating_condition VARCHAR(64),
    mechanic_diagnosis TEXT,
    date_repaired DATE,
    repair_mechanic VARCHAR(128),
    parts_fixed TEXT,
    pictures TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipments(equipment_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

if ($conn->query($sql) === TRUE) {
    echo "equipment_history table created successfully.\n";
} else {
    echo "Error creating equipment_history table: " . $conn->error . "\n";
}
