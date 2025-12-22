<?php
// Migration: Create equipment_uploads table
require_once __DIR__ . '/../config/config.php';

$sql = "CREATE TABLE IF NOT EXISTS equipment_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    field VARCHAR(32) NOT NULL, -- air_filters, warranty, tires
    file_url VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipments(equipment_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "Table equipment_uploads created successfully.";
} else {
    echo "Error creating table: " . $conn->error;
}
