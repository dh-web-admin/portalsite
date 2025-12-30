<?php
// Migration: import pages/maps/maps.csv into existing `suppliers` table
// Usage: php migrations/import_suppliers_from_maps_csv.php

require_once __DIR__ . '/../config/config.php';

// Default relative path
$csvPath = __DIR__ . '/../pages/maps/maps.csv';

// If a csv path is provided via query (web) or argv (cli), prefer that
if (PHP_SAPI !== 'cli' && !empty($_GET['csv'])) {
    $csvPath = $_GET['csv'];
}
if (PHP_SAPI === 'cli' && isset($argv) && count($argv) > 1) {
    // allow: php script.php path/to/maps.csv
    $csvPath = $argv[1];
}

// Try a set of sensible fallbacks so the script works from web or CLI
$candidates = [];
$candidates[] = $csvPath;
$candidates[] = __DIR__ . '/../../pages/maps/maps.csv';
$candidates[] = __DIR__ . '/../public/pages/maps/maps.csv';
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/pages/maps/maps.csv';
}
$candidates[] = getcwd() . '/pages/maps/maps.csv';

$found = null;
foreach ($candidates as $c) {
    if ($c === null) continue;
    $real = realpath($c);
    if ($real && file_exists($real)) { $found = $real; break; }
    // allow non-realpath relative paths from web context
    if (file_exists($c)) { $found = $c; break; }
}

if ($found !== null) {
    $csvPath = $found;
} else {
    echo "CSV file not found. Tried:\n";
    foreach ($candidates as $c) { if ($c) echo " - $c\n"; }
    echo "Provide a path via CLI: php migrations/import_suppliers_from_maps_csv.php /full/path/maps.csv\n";
    echo "Or via web: /migrations/import_suppliers_from_maps_csv.php?csv=/full/path/maps.csv\n";
    exit(1);
}

if (!isset($conn) || !$conn) {
    echo "Database connection \$conn not available from config.php\n";
    exit(1);
}

echo "Importing CSV into `suppliers` from: $csvPath\n";

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

// Normalizer: lowercase, replace non-alnum with underscore
$normalize = function($s) {
    $s = trim((string)$s);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    $s = trim($s, '_');
    return $s;
};

$normHeader = array_map($normalize, $header);

// Desired target columns (in suppliers table)
$targets = [
    'name','material','sales_contact','contact_number','email','address','city','state','latitude','longitude','pin_color','location_type','notes','service'
];

// Map target -> csv index (if present)
$indexMap = [];
foreach ($targets as $t) {
    $found = array_search($t, $normHeader, true);
    if ($found === false) {
        // try alternative names present in some CSVs
        $alternatives = [];
        if ($t === 'sales_contact') $alternatives = ['sales_contact','sales_contact_name','sales_contact_person','sales_contact'];
        if ($t === 'contact_number') $alternatives = ['contact_number','contact_number_phone','phone','contact'];
        if ($t === 'pin_color') $alternatives = ['pin_color','pin_colour'];
        foreach ($alternatives as $a) {
            $pos = array_search($a, $normHeader, true);
            if ($pos !== false) { $found = $pos; break; }
        }
    }
    $indexMap[$t] = ($found === false) ? null : (int)$found;
}

// Verify at least the `name` column exists
if ($indexMap['name'] === null) {
    echo "CSV header does not contain a 'name' column (normalized). Found: " . implode(', ', $normHeader) . "\n";
    fclose($handle);
    exit(1);
}

$table = 'suppliers';

// Check table exists
$res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
if (!$res || $res->num_rows === 0) {
    echo "Target table '$table' does not exist in the database.\n";
    fclose($handle);
    exit(1);
}

// Build insert SQL (we will not insert `id` so auto-increment will be used)
$colList = implode(', ', array_map(function($c){ return "`$c`"; }, $targets));
$placeholders = implode(', ', array_fill(0, count($targets), '?'));
$insertSql = "INSERT IGNORE INTO `$table` ($colList) VALUES ($placeholders)";
$stmt = $conn->prepare($insertSql);
if (!$stmt) {
    echo "Failed to prepare insert: " . $conn->error . "\n";
    fclose($handle);
    exit(1);
}

$types = str_repeat('s', count($targets));

$rowCount = 0;
$skipped = 0;
$conn->begin_transaction();
try {
    while (($data = fgetcsv($handle)) !== false) {
        // build values array in target order
        $values = [];
        foreach ($targets as $t) {
            $idx = $indexMap[$t];
            $val = null;
            if ($idx !== null && array_key_exists($idx, $data)) {
                $val = trim($data[$idx]);
                // normalize common placeholders for missing data
                if ($val === '' || strtoupper($val) === 'N/A' || strtoupper($val) === 'NA') {
                    $val = null;
                }
            }
            $values[] = $val;
        }

        // skip rows with no name
        if (empty($values[0])) { $skipped++; continue; }

        // bind params (bind_param requires references)
        $bind_names = [];
        $bind_names[] = $types;
        for ($i = 0; $i < count($values); $i++) {
            $bind_names[] = &$values[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
        if (!$stmt->execute()) {
            throw new Exception('Insert failed at row ' . ($rowCount+1) . ': ' . $stmt->error);
        }
        $rowCount++;
    }
    $conn->commit();
    echo "Inserted $rowCount rows into $table (skipped $skipped rows with empty name).\n";
} catch (Throwable $e) {
    $conn->rollback();
    echo "Error importing CSV: " . $e->getMessage() . "\n";
    fclose($handle);
    exit(1);
}

fclose($handle);
echo "Done.\n";

return 0;

?>
