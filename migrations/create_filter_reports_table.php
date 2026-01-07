<?php
/**
 * Migration: create `filter_reports` table
 * Run from CLI: php create_filter_reports_table.php
 */
require_once __DIR__ . '/../config/config.php';

echo "Running migration: create filter_reports table\n";

$sql = "CREATE TABLE IF NOT EXISTS `filter_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `equipment_id` INT UNSIGNED NOT NULL,
  `filter_id` INT UNSIGNED NOT NULL,
  `filter_name` VARCHAR(255) NOT NULL,
  `make` VARCHAR(255) DEFAULT '',
  `part_number` VARCHAR(255) DEFAULT '',
  `change_date` DATETIME NOT NULL,
  `equipment_hours` DECIMAL(10,2) DEFAULT 0.00,
  `changed_by` VARCHAR(255) DEFAULT '',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_filter_reports_equipment` (`equipment_id`),
  INDEX `idx_filter_reports_filter` (`filter_id`),
  INDEX `idx_filter_reports_change_date` (`change_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Table `filter_reports` created or already exists.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Close connection if available
if (isset($conn) && $conn) $conn->close();

echo "Done.\n";

?>
