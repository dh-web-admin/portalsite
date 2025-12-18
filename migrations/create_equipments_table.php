<?php
/**
 * Migration: Create equipments table
 *
 * Run this file once in your browser or via PHP CLI:
 *   php migrations/create_equipments_table.php
 */

require_once __DIR__ . '/../config/config.php';

$sql = "CREATE TABLE IF NOT EXISTS equipments (
    equipment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    equipment_number VARCHAR(50) NOT NULL,
    dhcst_equipment_number VARCHAR(50) NULL,
    dhss_equipment_number VARCHAR(50) NULL,
    type VARCHAR(100) NOT NULL,
    make VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    engine VARCHAR(120) NULL,
    engine_serial_number VARCHAR(120) NULL,
    transmission VARCHAR(120) NULL,
    trans_serial_number VARCHAR(120) NULL,
    vehicle_year VARCHAR(10) NULL,
    vin VARCHAR(50) NULL,
    operating_condition VARCHAR(60) NULL,
    location VARCHAR(150) NULL,
    current_hours DECIMAL(10,1) NOT NULL DEFAULT 0.0,
    oil_status VARCHAR(60) NULL,
    air_filters VARCHAR(60) NULL,
    warranty DATE NULL,
    tires VARCHAR(60) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (equipment_id),
    UNIQUE KEY uniq_equipment_number (equipment_number),
    KEY idx_type (type),
    KEY idx_location (location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    if ($conn->query($sql) === TRUE) {
        echo "<h2 style='color: green;'>✓ equipments table created successfully!</h2>";
        echo "<p>You can now view the equipment list on <code>/pages/equipments/</code>.</p>";
    } else {
        echo "<h2 style='color: red;'>✗ Error creating table:</h2>";
        echo "<p>" . htmlspecialchars($conn->error) . "</p>";
    }
} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ Exception:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

$conn->close();
?>
