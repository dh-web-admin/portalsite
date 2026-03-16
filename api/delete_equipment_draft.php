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

$input = json_decode(file_get_contents('php://input'), true);
$draftId = isset($input['draft_id']) ? (int)$input['draft_id'] : 0;
if ($draftId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid draft ID']);
    exit;
}

$email = $_SESSION['email'];

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

try {
    // Ensure draft belongs to current user before deletion.
    $stmt = $conn->prepare('SELECT id FROM draft_equipment WHERE id = ? AND created_by = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Database error');
    }
    $stmt->bind_param('is', $draftId, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $owned = $result && $result->fetch_assoc();
    $stmt->close();

    if (!$owned) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Draft not found or access denied']);
        exit;
    }

    $conn->begin_transaction();

    // Remove selections child rows.
    if ($stmt = $conn->prepare('DELETE FROM draft_equipment_selections WHERE draft_equipment_id = ?')) {
        $stmt->bind_param('i', $draftId);
        $stmt->execute();
        $stmt->close();
    }

    // Remove engineering draft items using whichever draft key exists in this environment.
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

    // Backward-compatible optional draft tables from earlier flow.
    if ($stmt = $conn->prepare('DELETE FROM draft_equipment_parts WHERE draft_equipment_id = ?')) {
        $stmt->bind_param('i', $draftId);
        $stmt->execute();
        $stmt->close();
    }
    if ($stmt = $conn->prepare('DELETE FROM draft_equipment_materials WHERE draft_equipment_id = ?')) {
        $stmt->bind_param('i', $draftId);
        $stmt->execute();
        $stmt->close();
    }
    if ($stmt = $conn->prepare('DELETE FROM draft_equipment_drawings WHERE draft_equipment_id = ?')) {
        $stmt->bind_param('i', $draftId);
        $stmt->execute();
        $stmt->close();
    }

    // Finally remove parent draft row.
    $stmt = $conn->prepare('DELETE FROM draft_equipment WHERE id = ? AND created_by = ?');
    if (!$stmt) {
        throw new Exception('Database error');
    }
    $stmt->bind_param('is', $draftId, $email);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to delete draft');
    }
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($conn) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete draft']);
}
