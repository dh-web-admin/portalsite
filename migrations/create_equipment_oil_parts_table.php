<?php
/**
 * Migration: create `equipment_oil_parts` table
 * Run from CLI: php create_equipment_oil_parts_table.php
 */
require_once __DIR__ . '/../config/config.php';

echo "Running migration: create_equipment_oil_parts table\n";

$sql = "CREATE TABLE IF NOT EXISTS `equipment_oil_parts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `equipment_id` INT UNSIGNED NOT NULL,
  `part` VARCHAR(255) DEFAULT '',
  `approx_capacity` VARCHAR(100) DEFAULT '',
  `fluid_type` VARCHAR(100) DEFAULT '',
  `weight` VARCHAR(50) DEFAULT '',
  `mfg` VARCHAR(150) DEFAULT '',
  `supplier` VARCHAR(255) DEFAULT '',
  `unit_cost` DECIMAL(12,2) DEFAULT 0.00,
  `unit` VARCHAR(50) DEFAULT '',
  `total` DECIMAL(12,2) DEFAULT 0.00,
  `notes` TEXT,
  `current_hours` DECIMAL(10,2) DEFAULT 0.00,
  `reset_at` DATETIME DEFAULT NULL,
  `oil_life` DECIMAL(10,2) DEFAULT 0.00,
  `condition` VARCHAR(50) DEFAULT '',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Table `equipment_oil_parts` created or already exists.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Close connection if available
if (isset($conn) && $conn) $conn->close();

echo "Done.\n";

?>
