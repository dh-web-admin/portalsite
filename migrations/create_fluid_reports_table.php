<?php
/**
 * Migration: create `fluid_reports` table
 * Run from CLI: php create_fluid_reports_table.php
 */
require_once __DIR__ . '/../config/config.php';

echo "Running migration: create fluid_reports table\n";

$sql = "CREATE TABLE IF NOT EXISTS `fluid_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `equipment_id` INT UNSIGNED NOT NULL,
  `oil_part_id` INT UNSIGNED NOT NULL,
  `part` VARCHAR(255) DEFAULT '',
  `fluid_type` VARCHAR(150) DEFAULT '',
  `change_date` DATETIME NOT NULL,
  `equipment_hours` DECIMAL(10,2) DEFAULT 0.00,
  `changed_by` VARCHAR(255) DEFAULT '',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_fluid_reports_equipment` (`equipment_id`),
  INDEX `idx_fluid_reports_part` (`oil_part_id`),
  INDEX `idx_fluid_reports_change_date` (`change_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Table `fluid_reports` created or already exists.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Close connection if available
if (isset($conn) && $conn) $conn->close();

echo "Done.\n";

?>
