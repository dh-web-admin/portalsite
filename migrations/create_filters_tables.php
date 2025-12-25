<?php
require_once __DIR__ . '/../config/config.php';

// Create filter_names table
$sql1 = "CREATE TABLE IF NOT EXISTS filter_names (
    filter_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    filter_name VARCHAR(120) NOT NULL,
    PRIMARY KEY (filter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Create equipment_filters table (linking filters to equipment)
$sql2 = "CREATE TABLE IF NOT EXISTS equipment_filters (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    equipment_id INT UNSIGNED NOT NULL,
    filter_id INT UNSIGNED NOT NULL,
    filter_date DATE NULL,
    hours DECIMAL(10,1) NULL,
    part_number VARCHAR(120) NULL,
    make VARCHAR(120) NULL,
    PRIMARY KEY (id),
    KEY idx_equipment_id (equipment_id),
    KEY idx_filter_id (filter_id),
    CONSTRAINT fk_equipment_filters_equipment FOREIGN KEY (equipment_id) REFERENCES equipments(equipment_id) ON DELETE CASCADE,
    CONSTRAINT fk_equipment_filters_filter FOREIGN KEY (filter_id) REFERENCES filter_names(filter_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    if ($conn->query($sql1) === TRUE) {
        echo "<p style='color:green;'>filter_names table created or already exists.</p>\n";
    } else {
        echo "<p style='color:red;'>Error creating filter_names: " . htmlspecialchars($conn->error) . "</p>\n";
    }
    if ($conn->query($sql2) === TRUE) {
        echo "<p style='color:green;'>equipment_filters table created or already exists.</p>\n";
    } else {
        echo "<p style='color:red;'>Error creating equipment_filters: " . htmlspecialchars($conn->error) . "</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Exception: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
$conn->close();
?>