<?php
define('IS_API', true);
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json');

require_admin_api();

$role = isset($_GET['role']) ? trim((string)$_GET['role']) : '';
if ($role === '' || !in_array($role, portal_all_roles(), true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or unknown role']);
    exit();
}

$rolePerms = portal_role_permissions($role);

$out = [];
foreach (portal_all_pages() as $pageKey) {
    $pageKey = (string)$pageKey;
    $out[] = [
        'page_key' => $pageKey,
        'can_access' => !empty($rolePerms[$pageKey]['can_access']),
        'can_edit' => !empty($rolePerms[$pageKey]['can_edit']),
    ];
}

echo json_encode([
    'success' => true,
    'role' => $role,
    'pages' => $out,
]);
