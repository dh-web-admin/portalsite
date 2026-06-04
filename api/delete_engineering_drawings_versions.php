<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['ids']) || !is_array($input['ids'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request payload']);
    exit();
}

$ids = [];
foreach ($input['ids'] as $id) {
    if (is_numeric($id)) {
        $id = intval($id);
        if ($id > 0) $ids[] = $id;
    }
}
$ids = array_values(array_unique($ids));

if (count($ids) === 0) {
    echo json_encode(['success' => false, 'message' => 'No valid drawing versions selected']);
    exit();
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    // Capture file URLs before deleting rows
    $stmt = $conn->prepare("SELECT id, file_url FROM engineering_drawings WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();

    $files = [];
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['file_url'])) {
            $files[] = $row['file_url'];
        }
    }
    $stmt->close();

    // Delete selected versions
    $stmt = $conn->prepare("DELETE FROM engineering_drawings WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $deletedCount = $stmt->affected_rows;
    $stmt->close();

    // Best-effort file cleanup
    foreach ($files as $fileUrl) {
        $name = basename($fileUrl);
        if (!$name) continue;
        $path = __DIR__ . '/../uploads/engineering_drawings/' . $name;
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    // Current version is implicitly the highest version remaining by existing query sort logic.
    echo json_encode([
        'success' => true,
        'message' => 'Deleted selected drawing version(s)',
        'deleted_count' => $deletedCount
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
