<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

$equipmentNumber = trim((string)($payload['equipment_number'] ?? ''));
$equipmentType = trim((string)($payload['equipment_type'] ?? ''));
$draftId = isset($payload['draft_id']) ? (int)$payload['draft_id'] : 0;
$selectionRows = isset($payload['selection_rows']) && is_array($payload['selection_rows']) ? $payload['selection_rows'] : [];

if ($equipmentNumber === '' || $equipmentType === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Equipment number and type are required before deploy']);
    exit;
}

function table_columns(mysqli $conn, $tableName) {
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tableName) . "`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['Field'])) {
                $columns[$row['Field']] = true;
            }
        }
        $result->free();
    }
    return $columns;
}

function normalize_selection_rows($rows) {
    $out = [];
    $seen = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $partId = isset($row['part_id']) ? (int)$row['part_id'] : 0;
        if ($partId <= 0) continue;
        $version = isset($row['version']) ? strtolower(trim((string)$row['version'])) : '';
        $key = $partId . '|' . $version;
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = [
            'part_id' => $partId,
            'version' => $version !== '' ? $version : null,
        ];
    }
    return $out;
}

function build_column_select($availableColumns, $alias, $wantedColumns) {
    $selectParts = [];
    foreach ($wantedColumns as $col) {
        if (isset($availableColumns[$col])) {
            $selectParts[] = $alias . '.' . $col;
        } else {
            $selectParts[] = "'' AS " . $col;
        }
    }
    return implode(', ', $selectParts);
}

try {
    $conn->begin_transaction();

    // Insert equipment using only columns that exist in this environment.
    $equipmentColumns = table_columns($conn, 'equipments');
    $insertFields = [];
    $insertValues = [];
    $bindTypes = '';
    $bindValues = [];

    $fieldValueMap = [
        'equipment_number' => $equipmentNumber,
        'dhss_equipment_number' => $equipmentNumber,
        'type' => $equipmentType,
        'operating_condition' => '',
        'location' => '',
        'current_hours' => 0,
        'oil_status' => '',
        'air_filters' => '',
    ];

    foreach ($fieldValueMap as $field => $value) {
        if (!isset($equipmentColumns[$field])) {
            continue;
        }
        $insertFields[] = $field;
        $insertValues[] = '?';
        if ($field === 'current_hours') {
            $bindTypes .= 'd';
            $bindValues[] = (float)$value;
        } else {
            $bindTypes .= 's';
            $bindValues[] = (string)$value;
        }
    }

    if (empty($insertFields)) {
        throw new Exception('Unable to map equipments table columns for deploy');
    }

    $insertSql = 'INSERT INTO equipments (' . implode(', ', $insertFields) . ') VALUES (' . implode(', ', $insertValues) . ')';
    $stmtEquipment = $conn->prepare($insertSql);
    if (!$stmtEquipment) {
        throw new Exception('Failed to prepare equipment insert: ' . $conn->error);
    }
    $stmtEquipment->bind_param($bindTypes, ...$bindValues);
    if (!$stmtEquipment->execute()) {
        throw new Exception('Failed to insert equipment: ' . $stmtEquipment->error);
    }
    $equipmentId = (int)$stmtEquipment->insert_id;
    $stmtEquipment->close();

    $normalizedSelections = normalize_selection_rows($selectionRows);

    $equipmentPartColumns = table_columns($conn, 'equipment_parts');
    $partSpecColumns = table_columns($conn, 'part_specifications');
    $engineeringSpecColumns = table_columns($conn, 'engineering_part_specifications');

    $sourceSpecFields = [
        'make', 'model', 'other_numbers', 'make_lnk',
        'supplier', 'supplier_name', 'supplier_number', 'supplier_email',
        'supplier_address', 'supplier_part_number', 'supplier_price', 'supplier_lnk'
    ];

    $specSelectSql = 'SELECT ' . build_column_select($engineeringSpecColumns, 'eps', $sourceSpecFields) .
        ' FROM engineering_part_specifications eps WHERE eps.part_name = ?';
    $stmtLoadSpecs = $conn->prepare($specSelectSql);
    if (!$stmtLoadSpecs) {
        throw new Exception('Failed to prepare engineering spec lookup: ' . $conn->error);
    }

    $stmtLoadPart = $conn->prepare('SELECT id, part_name, nsn_number, quantity, notes FROM engineering_item_parts WHERE id = ? LIMIT 1');
    if (!$stmtLoadPart) {
        throw new Exception('Failed to prepare part lookup: ' . $conn->error);
    }

    $partsInserted = 0;
    $specsInserted = 0;
    $partsSkipped = 0;

    foreach ($normalizedSelections as $selection) {
        $partId = (int)$selection['part_id'];
        $version = $selection['version'];

        $stmtLoadPart->bind_param('i', $partId);
        $stmtLoadPart->execute();
        $partResult = $stmtLoadPart->get_result();
        $part = $partResult ? $partResult->fetch_assoc() : null;
        if ($partResult) {
            $partResult->free();
        }
        if (!$part || empty($part['part_name'])) {
            $partsSkipped++;
            continue;
        }

        $partName = (string)$part['part_name'];
        $nsnNumber = isset($part['nsn_number']) ? (string)$part['nsn_number'] : '';
        $quantity = isset($part['quantity']) ? (int)$part['quantity'] : 1;
        if ($quantity <= 0) {
            $quantity = 1;
        }
        $notes = isset($part['notes']) ? (string)$part['notes'] : '';

        // Build equipment part insert dynamically; include version if the column exists.
        $epFields = [];
        $epValues = [];
        $epTypes = '';
        $epBind = [];

        $epMap = [
            'equipment_id' => $equipmentId,
            'part_name' => $partName,
            'nsn_number' => $nsnNumber,
            'quantity' => $quantity,
            'notes' => $notes,
        ];
        if (isset($equipmentPartColumns['version'])) {
            $epMap['version'] = $version ?: 'v1';
        }

        foreach ($epMap as $field => $value) {
            if (!isset($equipmentPartColumns[$field])) continue;
            $epFields[] = $field;
            $epValues[] = '?';
            if (in_array($field, ['equipment_id', 'quantity'], true)) {
                $epTypes .= 'i';
                $epBind[] = (int)$value;
            } else {
                $epTypes .= 's';
                $epBind[] = (string)$value;
            }
        }

        if (!empty($epFields)) {
            $stmtInsertEquipmentPart = $conn->prepare(
                'INSERT INTO equipment_parts (' . implode(', ', $epFields) . ') VALUES (' . implode(', ', $epValues) . ')'
            );
            if (!$stmtInsertEquipmentPart) {
                throw new Exception('Failed to prepare equipment part insert: ' . $conn->error);
            }
            $stmtInsertEquipmentPart->bind_param($epTypes, ...$epBind);
            if (!$stmtInsertEquipmentPart->execute()) {
                $stmtInsertEquipmentPart->close();
                throw new Exception('Failed to insert equipment part: ' . $stmtInsertEquipmentPart->error);
            }
            $stmtInsertEquipmentPart->close();
            $partsInserted++;
        }

        // Copy engineering specs into production part specs.
        $stmtLoadSpecs->bind_param('s', $partName);
        $stmtLoadSpecs->execute();
        $specResult = $stmtLoadSpecs->get_result();
        if ($specResult) {
            while ($spec = $specResult->fetch_assoc()) {
                $psFields = [];
                $psValues = [];
                $psTypes = '';
                $psBind = [];

                $specMap = [
                    'part_name' => $partName,
                    'make' => (string)($spec['make'] ?? ''),
                    'model' => (string)($spec['model'] ?? ''),
                    'other_numbers' => (string)($spec['other_numbers'] ?? ''),
                    'make_lnk' => (string)($spec['make_lnk'] ?? ''),
                    'supplier' => (string)($spec['supplier'] ?? ''),
                    'supplier_name' => (string)($spec['supplier_name'] ?? ''),
                    'supplier_number' => (string)($spec['supplier_number'] ?? ''),
                    'supplier_email' => (string)($spec['supplier_email'] ?? ''),
                    'supplier_address' => (string)($spec['supplier_address'] ?? ''),
                    'supplier_part_number' => (string)($spec['supplier_part_number'] ?? ''),
                    'supplier_price' => $spec['supplier_price'] === '' ? null : $spec['supplier_price'],
                    'supplier_lnk' => (string)($spec['supplier_lnk'] ?? ''),
                ];

                foreach ($specMap as $field => $value) {
                    if (!isset($partSpecColumns[$field])) continue;
                    $psFields[] = $field;
                    $psValues[] = '?';
                    if ($field === 'supplier_price') {
                        $psTypes .= 's';
                        $psBind[] = $value === null ? null : (string)$value;
                    } else {
                        $psTypes .= 's';
                        $psBind[] = (string)$value;
                    }
                }

                if (empty($psFields)) {
                    continue;
                }

                $updateParts = [];
                foreach ($psFields as $field) {
                    if ($field === 'part_name') continue;
                    $updateParts[] = $field . ' = VALUES(' . $field . ')';
                }
                $sql = 'INSERT INTO part_specifications (' . implode(', ', $psFields) . ') VALUES (' . implode(', ', $psValues) . ')';
                if (!empty($updateParts)) {
                    $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts);
                }

                $stmtInsertSpec = $conn->prepare($sql);
                if (!$stmtInsertSpec) {
                    throw new Exception('Failed to prepare part specification insert: ' . $conn->error);
                }
                $stmtInsertSpec->bind_param($psTypes, ...$psBind);
                if (!$stmtInsertSpec->execute()) {
                    $stmtInsertSpec->close();
                    throw new Exception('Failed to insert part specification: ' . $stmtInsertSpec->error);
                }
                $stmtInsertSpec->close();
                $specsInserted++;
            }
            $specResult->free();
        }
    }

    $stmtLoadPart->close();
    $stmtLoadSpecs->close();

    // Best effort: remove draft rows after successful deploy.
    if ($draftId > 0) {
        $draftItemsColumns = table_columns($conn, 'engineering_draft_items');
        if (isset($draftItemsColumns['draft_id'])) {
            if ($stmt = $conn->prepare('DELETE FROM engineering_draft_items WHERE draft_id = ?')) {
                $stmt->bind_param('i', $draftId);
                $stmt->execute();
                $stmt->close();
            }
        } elseif (isset($draftItemsColumns['draft_equipment_id'])) {
            if ($stmt = $conn->prepare('DELETE FROM engineering_draft_items WHERE draft_equipment_id = ?')) {
                $stmt->bind_param('i', $draftId);
                $stmt->execute();
                $stmt->close();
            }
        }
        if ($stmt = $conn->prepare('DELETE FROM draft_equipment_selections WHERE draft_equipment_id = ?')) {
            $stmt->bind_param('i', $draftId);
            $stmt->execute();
            $stmt->close();
        }
        if ($stmt = $conn->prepare('DELETE FROM draft_equipment WHERE id = ? AND created_by = ?')) {
            $email = $_SESSION['email'];
            $stmt->bind_param('is', $draftId, $email);
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'equipment_id' => $equipmentId,
        'parts_inserted' => $partsInserted,
        'specs_inserted' => $specsInserted,
        'parts_skipped' => $partsSkipped,
    ]);
} catch (Throwable $e) {
    if ($conn && $conn->errno !== 0) {
        // no-op
    }
    if ($conn) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Deploy failed: ' . $e->getMessage(),
    ]);
}
