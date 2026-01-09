<?php
define('IS_API', true);
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json');

require_admin_api();

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid user_id']);
    exit();
}

$userStmt = $conn->prepare('SELECT id, name, role FROM users WHERE id = ? LIMIT 1');
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

$pages = function_exists('portal_all_pages') ? portal_all_pages() : [];
$role = (string)($user['role'] ?? 'laborer');
$roleAllowed = function_exists('allowed_pages_for_role') ? allowed_pages_for_role($role) : [];

// Load overrides (if table exists)
$overrides = [];
try {
    $stmt = @$conn->prepare('SELECT page_key, can_access, can_edit FROM user_page_permissions WHERE user_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $key = (string)$r['page_key'];
                $overrides[$key] = [
                    'can_access' => (int)$r['can_access'] === 1,
                    'can_edit' => (int)$r['can_edit'] === 1,
                ];
            }
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    // If table doesn't exist yet, fall back to role defaults.
}

$out = [];
foreach ($pages as $pageKey) {
    $pageKey = (string)$pageKey;
    $defaultAccess = in_array($pageKey, $roleAllowed, true);
    // Default edit privileges are OFF for all pages.
    // Edit must be explicitly enabled via per-user override.
    $defaultEdit = false;

    if (isset($overrides[$pageKey])) {
        $canEdit = $overrides[$pageKey]['can_edit'];
        if ($pageKey === 'admin_panel') {
            $canEdit = false;
        }
        $out[] = [
            'page_key' => $pageKey,
            'can_access' => $overrides[$pageKey]['can_access'],
            'can_edit' => $canEdit,
        ];
    } else {
        $out[] = [
            'page_key' => $pageKey,
            'can_access' => $defaultAccess,
            'can_edit' => $defaultEdit,
        ];
    }
}

echo json_encode([
    'success' => true,
    'user' => [
        'id' => (int)$user['id'],
        'name' => (string)$user['name'],
        'role' => (string)$user['role'],
    ],
    'pages' => $out,
]);
