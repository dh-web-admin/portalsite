<?php
define('IS_API', true);
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json');

require_admin_api();

$role = isset($_POST['role']) ? trim((string)$_POST['role']) : '';
$payload = isset($_POST['permissions']) ? (string)$_POST['permissions'] : '';

if ($role === '' || !in_array($role, portal_all_roles(), true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or unknown role']);
    exit();
}
if ($payload === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing permissions']);
    exit();
}

$data = json_decode($payload, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid permissions JSON']);
    exit();
}

if (!portal_ensure_permission_schema()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Permission tables unavailable']);
    exit();
}

$validSet = array_fill_keys(array_map('strval', portal_all_pages()), true);

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('INSERT INTO role_page_permissions (role, page_key, can_access, can_edit)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE can_access = VALUES(can_access), can_edit = VALUES(can_edit)');

    foreach ($data as $row) {
        if (!is_array($row)) continue;
        $pageKey = isset($row['page_key']) ? (string)$row['page_key'] : '';
        if ($pageKey === '' || !isset($validSet[$pageKey])) continue;

        $canAccess = !empty($row['can_access']) ? 1 : 0;
        $canEdit = !empty($row['can_edit']) ? 1 : 0;

        // The admin panel is a door, not a page to edit.
        if ($pageKey === 'admin_panel') {
            $canEdit = 0;
            // Safety rail: the admin role must keep the panel, or the next
            // save would leave nobody able to reach this screen again.
            if ($role === 'admin') {
                $canAccess = 1;
            }
        }

        // Edit without access is meaningless; never store that combination.
        if ($canAccess === 0) $canEdit = 0;

        $stmt->bind_param('ssii', $role, $pageKey, $canAccess, $canEdit);
        $stmt->execute();
    }

    $stmt->close();
    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('save_role_permissions: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save role permissions']);
}
