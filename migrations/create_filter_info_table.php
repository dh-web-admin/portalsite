<?php
require_once __DIR__ . '/../config/config.php';

$sql = "CREATE TABLE IF NOT EXISTS filter_info (
    filter_id INT UNSIGNED NOT NULL,
    filter_name VARCHAR(120) NOT NULL,
    filter_date DATE NULL,
    hours DECIMAL(10,1) NULL,
    part_number VARCHAR(120) NULL,
    make VARCHAR(120) NULL,
    PRIMARY KEY (filter_id),
    CONSTRAINT fk_filter_info_filter FOREIGN KEY (filter_id) REFERENCES filter_names(filter_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green;'>filter_info table created or already exists.</p>\n";
    } else {
        echo "<p style='color:red;'>Error creating filter_info: " . htmlspecialchars($conn->error) . "</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Exception: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
$conn->close();
?>