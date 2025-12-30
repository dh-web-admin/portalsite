<?php
// Migration: import pages/maps/maps.csv into existing `suppliers` table
// Usage:
// CLI: php migrations/import_suppliers_from_maps_csv.php /full/path/maps.csv
// Web: /migrations/import_suppliers_from_maps_csv.php?csv=/full/path/maps/maps.csv

require_once __DIR__ . '/../config/config.php';

// --- Determine CSV path ---
$csvPath = __DIR__ . '/../pages/maps/maps.csv';

if (PHP_SAPI === 'cli' && isset($argv[1])) {
    $csvPath = $argv[1];
} elseif (PHP_SAPI !== 'cli' && !empty($_GET['csv'])) {
    $csvPath = $_GET['csv'];
}

// --- Verify file exists or try downloading ---
$handle = false;
if (file_exists($csvPath)) {
    $handle = fopen($csvPath, 'r');
} else {
    // Try downloading if it's a URL
    if (preg_match('#^https?://#', $csvPath)) {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: PortalSite-Importer/1.0\r\n",
                'timeout' => 15
            ]
        ];
        $context = stream_context_create($opts);
        $content = @file_get_contents($csvPath, false, $context);
        if ($content !== false && $content !== '') {
            $handle = fopen('php://temp', 'r+');
            fwrite($handle, $content);
            rewind($handle);
        }
    }
}

if ($handle === false) {
    echo "CSV file not found or could not be opened: $csvPath\n";
    exit(1);
}

// --- Check DB connection ---
if (!isset($conn) || !$conn) {
    echo "Database connection not available.\n";
    fclose($handle);
    exit(1);
}

// --- Read header ---
$header = fgetcsv($handle);
if ($header === false) {
    echo "CSV appears empty or malformed.\n";
    fclose($handle);
    exit(1);
}

// Remove BOM if present
if (strpos($header[0], "\xEF\xBB\xBF") === 0) {
    $header[0] = substr($header[0], 3);
}

// Normalize headers
$normalize = function($s) {
    $s = trim((string)$s);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
};
$normHeader = array_map($normalize, $header);

// --- Map CSV to target columns ---
$targets = [
    'name','material','sales_contact','contact_number','email','address','city','state',
    'latitude','longitude','pin_color','location_type','notes','service'
];

$indexMap = [];
foreach ($targets as $t) {
    $found = array_search($t, $normHeader, true);
    if ($found === false) {
        // try alternatives
        $alternatives = [];
        if ($t === 'sales_contact') $alternatives = ['sales_contact_name','sales_contact_person'];
        if ($t === 'contact_number') $alternatives = ['contact_number_phone','phone','contact'];
        if ($t === 'pin_color') $alternatives = ['pin_colour'];
        foreach ($alternatives as $a) {
            $pos = array_search($a, $normHeader, true);
            if ($pos !== false) { $found = $pos; break; }
        }
    }
    $indexMap[$t] = ($found === false) ? null : (int)$found;
}

if ($indexMap['name'] === null) {
    echo "CSV header missing required 'name' column. Found: " . implode(', ', $normHeader) . "\n";
    fclose($handle);
    exit(1);
}

// --- Check table exists ---
$table = 'suppliers';
$res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
if (!$res || $res->num_rows === 0) {
    echo "Target table '$table' does not exist.\n";
    fclose($handle);
    exit(1);
}

// --- Prepare insert ---
$colList = implode(', ', array_map(fn($c) => "`$c`", $targets));
$placeholders = implode(', ', array_fill(0, count($targets), '?'));
$insertSql = "INSERT IGNORE INTO `$table` ($colList) VALUES ($placeholders)";
$stmt = $conn->prepare($insertSql);
if (!$stmt) {
    echo "Failed to prepare insert: " . $conn->error . "\n";
    fclose($handle);
    exit(1);
}
$types = str_repeat('s', count($targets));

// --- Import CSV rows ---
$rowCount = 0;
$skipped = 0;
$conn->begin_transaction();
try {
    while (($data = fgetcsv($handle)) !== false) {
        $values = [];
        foreach ($targets as $t) {
            $idx = $indexMap[$t];
            $val = null;
            if ($idx !== null && isset($data[$idx])) {
                $val = trim($data[$idx]);
                if ($val === '' || strtoupper($val) === 'N/A' || strtoupper($val) === 'NA') $val = null;
            }
            $values[] = $val;
        }

        if (empty($values[0])) { $skipped++; continue; }

        $bind = [$types];
        foreach ($values as &$v) $bind[] = &$v;
        call_user_func_array([$stmt, 'bind_param'], $bind);

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

$stmt->close();
fclose($handle);
echo "Import completed successfully.\n";
return 0;
?>
