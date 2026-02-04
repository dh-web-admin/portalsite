<?php
// Minimal permissions helper for equipments pages
// Keep lightweight so it can be included anywhere.
if (session_status() === PHP_SESSION_NONE) session_start();

// Best-effort: make DB connection visible to permission helpers.
// Many pages include this helper before/after config.php, so we attempt to
// bind the common $conn into $GLOBALS['conn'] when available.
if (empty($GLOBALS['conn'])) {
    if (!empty($GLOBALS['conn'])) {
        // already set
    } else {
        // If config.php has been included in the global scope, $conn may exist.
        if (isset($conn) && $conn) {
            $GLOBALS['conn'] = $conn;
        }
    }
}

/**
 * Resolve the current user's role.
 * Uses $GLOBALS['role'] if present (pages often set it), otherwise queries DB if available.
 */
function get_current_role() {
    // Dev preview mode removed: always use actual role
    if (!empty($GLOBALS['role'])) return $GLOBALS['role'];
    if (!empty($_SESSION['role'])) return $_SESSION['role'];
    if (empty($_SESSION['email'])) return null;
    // Attempt to use existing DB connection if present
    if (empty($GLOBALS['conn'])) {
        global $conn;
        if (!empty($conn)) {
            $GLOBALS['conn'] = $conn;
        }
    }
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

function get_current_page_key(): ?string {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $path = $script !== '' ? $script : $uri;

    // Typical: /pages/<module>/index.php -> <module>
    if (strpos($path, '/pages/') !== false) {
        $parts = explode('/', trim($path, '/'));
        $pagesIndex = array_search('pages', $parts, true);
        if ($pagesIndex !== false && isset($parts[$pagesIndex + 1])) {
            return (string)$parts[$pagesIndex + 1];
        }
    }

    // Fallback: filename without .php
    $base = basename($path);
    $key = preg_replace('/\.php$/i', '', $base);
    return $key !== '' ? $key : null;
}

function get_user_page_override(string $email, string $pageKey): ?array {
    if ($email === '' || $pageKey === '') return null;
    if (empty($GLOBALS['conn'])) {
        global $conn;
        if (!empty($conn)) {
            $GLOBALS['conn'] = $conn;
        }
    }
    if (empty($GLOBALS['conn'])) return null;
    $conn = $GLOBALS['conn'];

    try {
        $stmt = @$conn->prepare('SELECT upp.can_access, upp.can_edit FROM user_page_permissions upp JOIN users u ON u.id = upp.user_id WHERE u.email = ? AND upp.page_key = ? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('ss', $email, $pageKey);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) return null;
        return [
            'can_access' => (int)($row['can_access'] ?? 0) === 1,
            'can_edit' => (int)($row['can_edit'] ?? 0) === 1,
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function can_edit_page(string $pageKey): bool {
    if ($pageKey === '') return false;

    // Must be logged in
    if (empty($_SESSION['email'])) return false;

    $role = get_current_role();
    if ($role === null) return false;

    // Admin role can always edit
    if ((string)$role === 'admin') return true;

    // If user has Admin Panel access, treat them as admin for all edit controls.
    // This is intentionally "full control".
    if (function_exists('can_access') && can_access((string)$role, 'admin_panel')) {
        return true;
    }

    // Otherwise, require an explicit per-user override AND access.
    $ovr = get_user_page_override((string)$_SESSION['email'], $pageKey);
    if ($ovr !== null) {
        return !empty($ovr['can_access']) && !empty($ovr['can_edit']);
    }

    // Default: no edits
    return false;
}

function require_edit_api(string $pageKey) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Content-Type: application/json; charset=utf-8');

    if (empty($_SESSION['email'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    if (!can_edit_page($pageKey)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
}

function require_admin_api() {
    $role = get_current_role();
    if ($role === null || !can_access((string)$role, 'admin_panel')) {
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
        'admin_panel',
        'equipments',
        'Bid_tracking',
        'scheduling',
        'engineering',
        'employee_information',
        'for_sale',
        'project_checklist',
        'forms',
        'company_policies',
        'sops',
        'maps',
    ];
}

function allowed_pages_for_role(string $role): array {
    $allWithAdminPanel = portal_all_pages();
    $all = array_values(array_diff($allWithAdminPanel, ['admin_panel']));
    switch ($role) {
        case 'developer':
            // Developers have full access in Employee Portal (dev tooling/God mode)
            return $allWithAdminPanel;
        case 'guest':
            // Guests see no tiles/pages by default
            return [];
        case 'data_entry':
            // Data-entry users can only access maps
            return ['maps'];
        case 'admin':
        case 'projectmanager':
        case 'estimator':
        case 'accounting':
            return $allWithAdminPanel;
        case 'superintendent':
            return array_values(array_diff($all, ['Bid_tracking']));
        case 'foreman':
            return array_values(array_diff($all, ['Bid_tracking','maps','engineering']));
        case 'mechanic':
            return array_values(array_diff($all, ['Bid_tracking','maps','engineering','forms','project_checklist']));
        case 'operator':
        case 'laborer':
            return ['employee_information','company_policies','sops'];
        default:
            // Unknown role: safest minimal access
            return ['employee_information'];
    }
}

function can_access(string $role, string $pageKey): bool {
    // Per-user override (if configured)
    if (!empty($_SESSION['email'])) {
        $ovr = get_user_page_override((string)$_SESSION['email'], $pageKey);
        if ($ovr !== null) return (bool)$ovr['can_access'];

        // Special-case: admin_panel should only be available by default to
        // the `admin` and `developer` roles. Explicit per-user overrides
        // (checked above) still apply and will be returned.
        if ($pageKey === 'admin_panel') {
            return in_array($role, ['admin', 'developer'], true);
        }

        // If Admin Panel is enabled for this user via the per-user admin_panel
        // flag, treat them as having the admin role's default access for other
        // pages (unless a specific override exists).
        $adminPanelOvr = get_user_page_override((string)$_SESSION['email'], 'admin_panel');
        if ($adminPanelOvr !== null && !empty($adminPanelOvr['can_access'])) {
            $role = 'admin';
        }
    }
    $allowed = allowed_pages_for_role($role);
    return in_array($pageKey, $allowed, true);
}

// Centralized UI-hide for non-admin viewers: hide common admin controls and icons
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('is_admin')) {
    // defensive no-op if helper isn't available for some reason
} else {
    // Only emit the UI-hide stylesheet/script for normal pages (not API endpoints)
    if (!defined('IS_API')) {
        $pageKey = get_current_page_key();
        $hideEdits = $pageKey ? !can_edit_page($pageKey) : !is_admin();
        if ($hideEdits) {
            echo "<style>.admin-only, .edit-filter-btn, .edit-dimension-btn, .edit-tire-btn, .upload-btn, #uploadImagesBtn, .editEquipmentBtn, .delete-equipment, .uploadFilterBtn, .add-equipment-btn, .equipment-edit-icon { display: none !important; }</style>";
            echo "<script>(function(){var patterns=[/\\bedit\\b/i,/\\bupload\\b/i,/\\bdelete\\b/i,/\\badd\\b/i,/\\bremove\\b/i];function hideIfMatch(el){var text=(el.innerText||el.value||'').trim();var title=(el.getAttribute&& (el.getAttribute('title')||el.getAttribute('aria-label')))||'';if(!text&& !title) return;var combined=(text+' '+title).trim();for(var i=0;i<patterns.length;i++){if(patterns[i].test(combined)){el.style.display='none';return;}}}document.addEventListener('DOMContentLoaded',function(){var els=document.querySelectorAll('a,button,input[type=button],input[type=submit]');els.forEach(hideIfMatch);});})();</script>";
        }
    }
}
