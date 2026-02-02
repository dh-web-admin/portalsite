<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

function ensure_filter_life_column($conn) {
    static $ensured = false;
    if ($ensured) {
        return;
    }
    try {
        $check = $conn->query("SHOW COLUMNS FROM filter_info LIKE 'filter_life'");
        $hasColumn = $check && $check->num_rows > 0;
        if ($check) {
            $check->close();
        }
        if (!$hasColumn) {
            $conn->query("ALTER TABLE filter_info ADD COLUMN filter_life DECIMAL(10,1) NULL AFTER hours");
        }
        $ensured = true;
    } catch (Throwable $e) {
        error_log('[fetch_equipment_filters] Unable to ensure filter_life column: ' . $e->getMessage());
    }
}

$equipment_id = isset($_GET['equipment_id']) ? (int)$_GET['equipment_id'] : 0;
if (!$equipment_id) {
    echo json_encode([]);
    exit;
}

ensure_filter_life_column($conn);

// Determine which make/part-number columns exist so we build a safe query
$cols = [];
try {
    $colRes = $conn->query("SHOW COLUMNS FROM filter_info");
    if ($colRes) {
        while ($c = $colRes->fetch_assoc()) {
            $cols[$c['Field']] = true;
        }
        $colRes->free();
    }
} catch (Throwable $e) {
    // If this fails for some reason, fall back to legacy column names
}

// Prefer the new schema (make_1/part_number_1) when available, otherwise use legacy make/part_number
if (!empty($cols['make_1']) || !empty($cols['part_number_1'])) {
    $sql = 'SELECT filter_id, equipment_id, filter_name, filter_date, hours, filter_life,
                   part_number_1 AS part_number,
                   make_1 AS make
            FROM filter_info WHERE equipment_id = ? ORDER BY filter_id ASC';
} else {
    $sql = 'SELECT filter_id, equipment_id, filter_name, filter_date, hours, filter_life,
                   part_number, make
            FROM filter_info WHERE equipment_id = ? ORDER BY filter_id ASC';
}

// Fetch all filters for this equipment from filter_info
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $equipment_id);
$stmt->execute();
$res = $stmt->get_result();
$filters = [];
while ($row = $res->fetch_assoc()) {
    $filters[] = $row;
}
$stmt->close();

echo json_encode($filters);
?>
