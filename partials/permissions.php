<?php
// Minimal permissions helper for equipments pages
// Keep lightweight so it can be included anywhere.
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Resolve the current user's role.
 * Uses $GLOBALS['role'] if present (pages often set it), otherwise queries DB if available.
 */
function get_current_role() {
    // Allow developer preview via GET param (pages use ?preview_role=...)
    if (!empty($_GET['preview_role'])) return $_GET['preview_role'];
    if (!empty($GLOBALS['preview_role'])) return $GLOBALS['preview_role'];
    if (!empty($GLOBALS['role'])) return $GLOBALS['role'];
    if (!empty($_SESSION['role'])) return $_SESSION['role'];
    if (empty($_SESSION['email'])) return null;
    // Attempt to use existing DB connection if present
    if (empty($GLOBALS['conn'])) return null;
    $email = $_SESSION['email'];
    $stmt = $GLOBALS['conn']->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $user ? $user['role'] : null;
}

function is_admin() {
    $r = get_current_role();
    return $r === 'admin';
}

function require_admin_api() {
    if (!is_admin()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'forbidden']);
        exit();
    }
}

function role_has($cap) {
    // Placeholder for future capability checks. For now, only admin has edit capabilities.
    if ($cap === 'edit') return is_admin();
    return false;
}

?>
<?php
// Role-based page access rules
// Usage: can_access($role, $pageKey) where $pageKey is the filename without .php

function portal_all_pages(): array {
    return [
    'equipments',
        'Bid_tracking',
        'scheduling',
        'engineering',
        'employee_information',
        'for_sale',
        'project_checklist',
        'pictures',
        'forms',
        'manuals',
        'videos',
        'maps',
    ];
}

function allowed_pages_for_role(string $role): array {
    $all = portal_all_pages();
    switch ($role) {
        case 'developer':
            // Developers have full access in Employee Portal (dev tooling/God mode)
            return $all;
        case 'data_entry':
            // Data-entry users can only access maps and coordinate_entry
            return ['maps','coordinate_entry'];
        case 'admin':
        case 'projectmanager':
        case 'estimator':
        case 'accounting':
            return $all;
        case 'superintendent':
            return array_values(array_diff($all, ['Bid_tracking']));
        case 'foreman':
            return array_values(array_diff($all, ['Bid_tracking','maps','engineering']));
        case 'mechanic':
            return array_values(array_diff($all, ['Bid_tracking','maps','engineering','forms','project_checklist']));
        case 'operator':
        case 'laborer':
            return ['employee_information','manuals','videos'];
        default:
            // Unknown role: safest minimal access
            return ['employee_information'];
    }
}

function can_access(string $role, string $pageKey): bool {
    $allowed = allowed_pages_for_role($role);
    return in_array($pageKey, $allowed, true);
}

// Centralized UI-hide for non-admin viewers: hide common admin controls and icons
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('is_admin')) {
    // defensive no-op if helper isn't available for some reason
} else {
    // Only emit the UI-hide stylesheet/script for normal pages (not API endpoints)
    if (!defined('IS_API') && !is_admin()) {
        echo "<style>.admin-only, .edit-filter-btn, .edit-dimension-btn, .edit-tire-btn, .upload-btn, #uploadImagesBtn, .editEquipmentBtn, .delete-equipment, .uploadFilterBtn, .add-equipment-btn, .equipment-edit-icon { display: none !important; }</style>";
        echo "<script>(function(){var patterns=[/\\bedit\\b/i,/\\bupload\\b/i,/\\bdelete\\b/i,/\\badd\\b/i,/\\bremove\\b/i];function hideIfMatch(el){var text=(el.innerText||el.value||'').trim();var title=(el.getAttribute&& (el.getAttribute('title')||el.getAttribute('aria-label')))||'';if(!text&& !title) return;var combined=(text+' '+title).trim();for(var i=0;i<patterns.length;i++){if(patterns[i].test(combined)){el.style.display='none';return;}}}document.addEventListener('DOMContentLoaded',function(){var els=document.querySelectorAll('a,button,span,input[type=button],input[type=submit]');els.forEach(hideIfMatch);});})();</script>";
    }
}
