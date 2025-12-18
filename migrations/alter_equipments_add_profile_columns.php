<?php
/**
 * Migration: Add profile/spec columns to equipments table
 *
 * Run via browser or PHP CLI:
 *   php migrations/alter_equipments_add_profile_columns.php
 */

require_once __DIR__ . '/../config/config.php';

function column_exists(mysqli $conn, string $table, string $column): bool {
	$sql = "SELECT COUNT(*) AS c
		FROM INFORMATION_SCHEMA.COLUMNS
		WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = ?
			AND COLUMN_NAME = ?";
	$stmt = $conn->prepare($sql);
	if (!$stmt) return false;
	$stmt->bind_param('ss', $table, $column);
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res ? $res->fetch_assoc() : null;
	$stmt->close();
	return (int)($row['c'] ?? 0) > 0;
}

function table_exists(mysqli $conn, string $table): bool {
	$sql = "SELECT COUNT(*) AS c
		FROM INFORMATION_SCHEMA.TABLES
		WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = ?";
	$stmt = $conn->prepare($sql);
	if (!$stmt) return false;
	$stmt->bind_param('s', $table);
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res ? $res->fetch_assoc() : null;
	$stmt->close();
	return (int)($row['c'] ?? 0) > 0;
}

$table = 'equipments';

if (!table_exists($conn, $table)) {
	echo "<h2 style='color:red;'>✗ Table '$table' does not exist.</h2>";
	echo "<p>Run: <code>php migrations/create_equipments_table.php</code></p>";
	$conn->close();
	exit;
}

$columns = [
	// Spreadsheet fields
	'dhcst_equipment_number' => "VARCHAR(50) NULL",
	'dhss_equipment_number' => "VARCHAR(50) NULL",
	'make' => "VARCHAR(100) NULL",
	'model' => "VARCHAR(100) NULL",
	'engine' => "VARCHAR(120) NULL",
	'engine_serial_number' => "VARCHAR(120) NULL",
	'transmission' => "VARCHAR(120) NULL",
	'trans_serial_number' => "VARCHAR(120) NULL",
	'vehicle_year' => "VARCHAR(10) NULL",
	'vin' => "VARCHAR(50) NULL",
];

$added = [];
$skipped = [];
$failed = [];

foreach ($columns as $name => $def) {
	if (column_exists($conn, $table, $name)) {
		$skipped[] = $name;
		continue;
	}
	$sql = "ALTER TABLE $table ADD COLUMN $name $def";
	$ok = $conn->query($sql);
	if ($ok) {
		$added[] = $name;
	} else {
		$failed[] = $name . ' (' . $conn->error . ')';
	}
}

if (count($failed) === 0) {
	echo "<h2 style='color:green;'>✓ Equipments table updated successfully.</h2>";
} else {
	echo "<h2 style='color:orange;'>⚠ Equipments table updated with some errors.</h2>";
}

if (count($added) > 0) {
	echo "<p><strong>Added columns:</strong> " . htmlspecialchars(implode(', ', $added)) . "</p>";
} else {
	echo "<p><strong>Added columns:</strong> none</p>";
}

if (count($skipped) > 0) {
	echo "<p><strong>Already existed:</strong> " . htmlspecialchars(implode(', ', $skipped)) . "</p>";
}

if (count($failed) > 0) {
	echo "<p><strong>Failed:</strong></p><ul>";
	foreach ($failed as $f) {
		echo '<li>' . htmlspecialchars($f) . '</li>';
	}
	echo "</ul>";
}

$conn->close();
?>
