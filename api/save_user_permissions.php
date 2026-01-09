<?php
define('IS_API', true);
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json');

require_admin_api();

$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$payload = isset($_POST['permissions']) ? (string)$_POST['permissions'] : '';

if ($userId <= 0 || $payload === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing user_id or permissions']);
    exit();
}

$data = json_decode($payload, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid permissions JSON']);
    exit();
}

$validPages = function_exists('portal_all_pages') ? portal_all_pages() : [];
$validSet = array_fill_keys(array_map('strval', $validPages), true);

// Validate user
$userStmt = $conn->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userRes = $userStmt->get_result();
$user = $userRes ? $userRes->fetch_assoc() : null;
$userStmt->close();
if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

// Ensure table exists
$testStmt = @$conn->prepare('SELECT 1 FROM user_page_permissions LIMIT 1');
if (!$testStmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Permissions table not found',
        'message' => 'Run migrations/create_user_page_permissions_table.php'
    ]);
    exit();
}
$testStmt->close();

$conn->begin_transaction();
try {
    // Replace all rows for this user with the provided set
    $del = $conn->prepare('DELETE FROM user_page_permissions WHERE user_id = ?');
    $del->bind_param('i', $userId);
    $del->execute();
    $del->close();

    $ins = $conn->prepare('INSERT INTO user_page_permissions (user_id, page_key, can_access, can_edit) VALUES (?, ?, ?, ?)');

    $targetRole = (string)($user['role'] ?? 'laborer');
    $roleAllowed = function_exists('allowed_pages_for_role') ? allowed_pages_for_role($targetRole) : [];

    foreach ($data as $row) {
        if (!is_array($row)) continue;
        $pageKey = isset($row['page_key']) ? (string)$row['page_key'] : '';
        if ($pageKey === '' || !isset($validSet[$pageKey])) continue;

        $canAccess = !empty($row['can_access']) ? 1 : 0;
        $canEdit = !empty($row['can_edit']) ? 1 : 0;
        if ($pageKey === 'admin_panel') {
            $canEdit = 0;
        }

        // Store only overrides: if the value matches role-based default, omit the row.
        $defaultAccess = in_array($pageKey, $roleAllowed, true) ? 1 : 0;
        $defaultEdit = ($targetRole === 'admin') ? 1 : 0;
        if ($pageKey === 'admin_panel') {
            $defaultEdit = 0;
        }

        if ($canAccess === $defaultAccess && $canEdit === $defaultEdit) {
            continue;
        }

        $ins->bind_param('isii', $userId, $pageKey, $canAccess, $canEdit);
        $ins->execute();
    }

    $ins->close();
    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save permissions']);
}
