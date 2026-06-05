<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

function rel_path_from_file_url($fileUrl)
{
    $path = parse_url((string) $fileUrl, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = (string) $fileUrl;
    }

    $path = str_replace('\\', '/', $path);
    $pos = stripos($path, '/uploads/');

    if ($pos !== false) {
        return ltrim(substr($path, $pos + 9), '/');
    }

    if (stripos($path, 'uploads/') === 0) {
        return ltrim(substr($path, 8), '/');
    }

    $name = basename($path);
    return $name ? ('engineering_drawings/' . $name) : '';
}

try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'engineering_drawings'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'engineering_drawings table does not exist',
            'checked_total' => 0,
            'missing_count' => 0,
            'existing_count' => 0,
            'missing' => []
        ]);
        exit();
    }

    $partColumnCheck = $conn->query("SHOW COLUMNS FROM engineering_drawings LIKE 'part_id'");
    $hasPartId = $partColumnCheck && $partColumnCheck->num_rows > 0;

    $itemId = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
    $hasPartFilter = isset($_GET['part_id']);
    $partFilterRaw = $_GET['part_id'] ?? null;

    if ($hasPartFilter && $partFilterRaw !== 'null' && !is_numeric($partFilterRaw)) {
        echo json_encode(['success' => false, 'message' => 'Invalid part_id. Use a number or null']);
        exit();
    }

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 500;
    if ($limit < 1) {
        $limit = 1;
    }
    if ($limit > 2000) {
        $limit = 2000;
    }

    $includeExisting = isset($_GET['include_existing']) && $_GET['include_existing'] === '1';

    $conditions = [];
    $types = '';
    $params = [];

    if ($itemId > 0) {
        $conditions[] = 'item_id = ?';
        $types .= 'i';
        $params[] = $itemId;
    }

    if ($hasPartFilter) {
        if ($partFilterRaw === 'null') {
            if ($hasPartId) {
                $conditions[] = 'part_id IS NULL';
            }
        } else {
            if (!$hasPartId) {
                $conditions[] = '1 = 0';
            } else {
                $conditions[] = 'part_id = ?';
                $types .= 'i';
                $params[] = (int) $partFilterRaw;
            }
        }
    }

    $selectPart = $hasPartId ? 'part_id' : 'NULL AS part_id';
    $sql = "SELECT id, item_id, $selectPart, file_url, filename, version, uploaded_at FROM engineering_drawings";
    if (count($conditions) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY uploaded_at DESC, id DESC LIMIT ?';

    $types .= 'i';
    $params[] = $limit;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
    $uploadsMount = getenv('UPLOADS_MOUNT_PATH') ?: '/portalsite/uploads';
    $primaryBase = rtrim($uploadsMount, '/');
    $legacyBase = __DIR__ . '/../uploads';

    $missing = [];
    $existing = [];
    $checkedTotal = 0;

    while ($row = $result->fetch_assoc()) {
        $checkedTotal++;

        $rel = rel_path_from_file_url($row['file_url'] ?? '');
        $primaryPath = $primaryBase . '/' . $rel;
        $legacyPath = $legacyBase . '/' . $rel;

        $existsPrimary = $rel !== '' && is_file($primaryPath) && is_readable($primaryPath);
        $existsLegacy = $rel !== '' && is_file($legacyPath) && is_readable($legacyPath);
        $exists = $existsPrimary || $existsLegacy;

        $entry = [
            'id' => (int) $row['id'],
            'item_id' => (int) $row['item_id'],
            'part_id' => isset($row['part_id']) ? ($row['part_id'] === null ? null : (int) $row['part_id']) : null,
            'filename' => $row['filename'],
            'version' => $row['version'],
            'file_url' => $row['file_url'],
            'uploaded_at' => $row['uploaded_at'],
            'relative_path' => $rel,
            'exists' => $exists,
            'exists_primary' => $existsPrimary,
            'exists_legacy' => $existsLegacy,
            'primary_path' => $primaryPath,
            'legacy_path' => $legacyPath,
        ];

        if ($exists) {
            if ($includeExisting) {
                $existing[] = $entry;
            }
        } else {
            $missing[] = $entry;
        }
    }

    $stmt->close();

    $response = [
        'success' => true,
        'checked_total' => $checkedTotal,
        'missing_count' => count($missing),
        'existing_count' => $checkedTotal - count($missing),
        'is_production' => $isProduction,
        'uploads_mount_path' => $uploadsMount,
        'missing' => $missing,
    ];

    if ($includeExisting) {
        $response['existing'] = $existing;
    }

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Audit error: ' . $e->getMessage()]);
}
