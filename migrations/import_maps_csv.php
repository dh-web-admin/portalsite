<?php
// Migration: import CSV data into DB
// Usage: php migrations/import_maps_csv.php

require_once __DIR__ . '/../config/config.php';

// Path to CSV (adjust if your CSV is located elsewhere)
$csvPath = __DIR__ . '/../pages/maps/maps.csv';

if (!file_exists($csvPath)) {
    echo "CSV file not found: $csvPath\n";
    exit(1);
}

if (!isset($conn) || !$conn) {
    echo "Database connection \$conn not available from config.php\n";
    exit(1);
}

echo "Importing CSV: $csvPath\n";

$handle = fopen($csvPath, 'r');
if ($handle === false) {
    echo "Failed to open CSV file.\n";
    exit(1);
}

// Read header
$header = fgetcsv($handle);
if ($header === false) {
    echo "CSV appears empty or malformed.\n";
    fclose($handle);
    exit(1);
}

// Trim BOM from first header cell if present
if (strpos($header[0], "\xEF\xBB\xBF") === 0) {
    $header[0] = substr($header[0], 3);
}

// Sanitize column names (lowercase, replace non-alnum with _)
$cols = [];
$seen = [];
foreach ($header as $c) {
    $name = trim((string)$c);
    $name = strtolower($name);
    // replace spaces and non-alnum with underscore
    $name = preg_replace('/[^a-z0-9]+/', '_', $name);
    $name = trim($name, '_');
    if ($name === '') $name = 'col';
    $orig = $name;
    $i = 1;
    while (in_array($name, $seen, true)) {
        $name = $orig . '_' . $i;
        $i++;
    }
    $seen[] = $name;
    $cols[] = $name;
}

$table = 'maps_import';

// Build CREATE TABLE statement
$colDefs = [];
foreach ($cols as $c) {
    // use TEXT to be safe for arbitrary CSV content
    $colDefs[] = "`$c` TEXT NULL";
}

$createSql = "CREATE TABLE IF NOT EXISTS `$table` (id INT UNSIGNED NOT NULL AUTO_INCREMENT, " . implode(', ', $colDefs) . ", PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($createSql)) {
    echo "Failed to create table $table: " . $conn->error . "\n";
    fclose($handle);
    exit(1);
}

// Truncate table so migration is idempotent
if (!$conn->query("TRUNCATE TABLE `$table`")) {
    echo "Failed to truncate $table: " . $conn->error . "\n";
    fclose($handle);
    exit(1);
}

// Prepare insert
$placeholders = implode(',', array_fill(0, count($cols), '?'));
$colList = implode(',', array_map(function($c){ return "`$c`"; }, $cols));
$insertSql = "INSERT INTO `$table` ($colList) VALUES ($placeholders)";
$stmt = $conn->prepare($insertSql);
if (!$stmt) {
    echo "Failed to prepare insert: " . $conn->error . "\n";
    fclose($handle);
    exit(1);
}

// Bind params dynamically using call_user_func_array
$types = str_repeat('s', count($cols));

$rowCount = 0;
$conn->begin_transaction();
try {
    while (($data = fgetcsv($handle)) !== false) {
        // pad data if row has fewer columns
        if (count($data) < count($cols)) {
            $data = array_merge($data, array_fill(0, count($cols) - count($data), null));
        }
        // convert empty strings to null for cleanliness
        foreach ($data as $k => $v) {
            if ($v === '') $data[$k] = null;
        }

        // bind parameters
        $bind_names = [];
        $bind_names[] = $types;
        for ($i = 0; $i < count($cols); $i++) {
            $bind_names[] = &$data[$i];
        }
        // bind_param requires references
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
        if (!$stmt->execute()) {
            throw new Exception('Insert failed at row ' . ($rowCount+1) . ': ' . $stmt->error);
        }
        $rowCount++;
    }
    $conn->commit();
    echo "Imported $rowCount rows into $table.\n";
} catch (Throwable $e) {
    $conn->rollback();
    echo "Error importing CSV: " . $e->getMessage() . "\n";
    fclose($handle);
    exit(1);
}

fclose($handle);
echo "Done.\n";

// Optionally print table name and columns
echo "Table: $table\nColumns: " . implode(', ', $cols) . "\n";

return 0;
