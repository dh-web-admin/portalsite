<?php
// Minimal permissions helper for  pages

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

    // A per-user override, when present, decides on its own.
    $ovr = get_user_page_override((string)$_SESSION['email'], $pageKey);
    if ($ovr !== null) {
        return !empty($ovr['can_access']) && !empty($ovr['can_edit']);
    }

    // Otherwise fall back to the role's default for this page. These are all
    // seeded off, so behaviour only changes once an admin grants edit to a role.
    $rolePerms = portal_role_permissions((string)$role);
    if (isset($rolePerms[$pageKey])) {
        return !empty($rolePerms[$pageKey]['can_access']) && !empty($rolePerms[$pageKey]['can_edit']);
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

/**
 * The page list and per-role defaults used to be hardcoded here. They now live
 * in two tables so admins can change them without a code deploy, and so pages
 * that didn't exist when this file was written still get governed:
 *
 *   portal_pages           - registry of every known page key (+ display label).
 *                            partials/access_gate.php auto-registers new keys
 *                            the first time anyone requests them.
 *   role_page_permissions  - per-role access/edit defaults.
 *
 * Per-user rows in user_page_permissions still override both, exactly as before.
 * The legacy arrays below survive only as the one-time seed for a database that
 * has never had these tables, and as a fallback when there is no connection.
 */

function portal_legacy_pages(): array {
    return [
        'admin_panel',
        'equipments',
        'client_profile',
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

function portal_legacy_allowed_pages_for_role(string $role): array {
    $allWithAdminPanel = portal_legacy_pages();
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
            return ['employee_information','company_policies','sops','client_profile'];
        default:
            // Unknown role: safest minimal access
            return ['employee_information'];
    }
}

/** Every role the portal assigns, used when seeding role defaults. */
function portal_all_roles(): array {
    return [
        'admin', 'developer', 'projectmanager', 'estimator', 'accounting',
        'superintendent', 'foreman', 'mechanic', 'operator', 'laborer',
        'data_entry', 'guest',
    ];
}

/** "client_profile" -> "Client Profile" (mirrors prettyLabel() in the admin UI). */
function portal_page_label(string $pageKey): string {
    return ucwords(trim(str_replace(['_', '-'], ' ', $pageKey)));
}

function portal_perm_conn() {
    if (!empty($GLOBALS['conn'])) return $GLOBALS['conn'];
    global $conn;
    if (!empty($conn)) {
        $GLOBALS['conn'] = $conn;
        return $conn;
    }
    return null;
}

/**
 * Create the two tables on demand and, only when role_page_permissions has
 * never been populated, seed it from the legacy hardcoded defaults so that
 * nobody's access changes the moment this ships.
 */
function portal_ensure_permission_schema(): bool {
    static $done = null;
    if ($done !== null) return $done;

    $conn = portal_perm_conn();
    if (!$conn) { $done = false; return false; }

    try {
        $conn->query("CREATE TABLE IF NOT EXISTS `portal_pages` (
            `page_key` VARCHAR(100) NOT NULL,
            `label` VARCHAR(150) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`page_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $conn->query("CREATE TABLE IF NOT EXISTS `role_page_permissions` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `role` VARCHAR(50) NOT NULL,
            `page_key` VARCHAR(100) NOT NULL,
            `can_access` TINYINT(1) NOT NULL DEFAULT 0,
            `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_role_page` (`role`, `page_key`),
            KEY `idx_role` (`role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // Seed once: an empty role table means this database predates the move.
        $res = $conn->query('SELECT COUNT(*) AS c FROM role_page_permissions');
        $row = $res ? $res->fetch_assoc() : null;
        if ($row && (int)$row['c'] === 0) {
            $pageIns = $conn->prepare('INSERT IGNORE INTO portal_pages (page_key, label) VALUES (?, ?)');
            foreach (portal_legacy_pages() as $pageKey) {
                $label = portal_page_label($pageKey);
                $pageIns->bind_param('ss', $pageKey, $label);
                $pageIns->execute();
            }
            $pageIns->close();

            $roleIns = $conn->prepare('INSERT IGNORE INTO role_page_permissions (role, page_key, can_access, can_edit) VALUES (?, ?, ?, ?)');
            foreach (portal_all_roles() as $role) {
                $allowed = portal_legacy_allowed_pages_for_role($role);
                foreach (portal_legacy_pages() as $pageKey) {
                    $canAccess = in_array($pageKey, $allowed, true) ? 1 : 0;
                    // admin_panel was narrowed to admin/developer by a hardcoded
                    // branch in can_access(), regardless of what the role array
                    // said. Seed the EFFECTIVE permission, or roles like
                    // projectmanager would silently gain the admin panel here.
                    if ($pageKey === 'admin_panel') {
                        $canAccess = in_array($role, ['admin', 'developer'], true) ? 1 : 0;
                    }
                    // Matches the old can_edit_page(): only admin/developer
                    // (the admin_panel holders) had edit rights by default.
                    $canEdit = ($canAccess === 1 && in_array($role, ['admin', 'developer'], true) && $pageKey !== 'admin_panel') ? 1 : 0;
                    $roleIns->bind_param('ssii', $role, $pageKey, $canAccess, $canEdit);
                    $roleIns->execute();
                }
            }
            $roleIns->close();
        }

        $done = true;
    } catch (Throwable $e) {
        error_log('portal_ensure_permission_schema: ' . $e->getMessage());
        $done = false;
    }
    return $done;
}

/**
 * Record a page key the first time it is seen. New pages land here denied for
 * every role, so they show up in the admin permissions screen ready to be
 * granted rather than being silently reachable.
 */
function portal_register_page(string $pageKey): void {
    if ($pageKey === '') return;
    if (!portal_ensure_permission_schema()) return;
    $conn = portal_perm_conn();
    if (!$conn) return;

    try {
        $stmt = $conn->prepare('INSERT IGNORE INTO portal_pages (page_key, label) VALUES (?, ?)');
        if (!$stmt) return;
        $label = portal_page_label($pageKey);
        $stmt->bind_param('ss', $pageKey, $label);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('portal_register_page: ' . $e->getMessage());
    }
}

/** Every known page key, newest registrations included. */
function portal_all_pages(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    if (portal_ensure_permission_schema()) {
        $conn = portal_perm_conn();
        try {
            $res = $conn->query('SELECT page_key FROM portal_pages ORDER BY (page_key = "admin_panel") DESC, label ASC');
            if ($res) {
                $out = [];
                while ($r = $res->fetch_assoc()) $out[] = (string)$r['page_key'];
                if ($out) { $cache = $out; return $cache; }
            }
        } catch (Throwable $e) {
            error_log('portal_all_pages: ' . $e->getMessage());
        }
    }

    $cache = portal_legacy_pages();
    return $cache;
}

/** Role defaults straight from the table: [page_key => ['can_access','can_edit']]. */
function portal_role_permissions(string $role): array {
    static $cache = [];
    if (isset($cache[$role])) return $cache[$role];

    $out = [];
    if (portal_ensure_permission_schema()) {
        $conn = portal_perm_conn();
        try {
            $stmt = $conn->prepare('SELECT page_key, can_access, can_edit FROM role_page_permissions WHERE role = ?');
            if ($stmt) {
                $stmt->bind_param('s', $role);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($res && ($r = $res->fetch_assoc())) {
                    $out[(string)$r['page_key']] = [
                        'can_access' => (int)$r['can_access'] === 1,
                        'can_edit' => (int)$r['can_edit'] === 1,
                    ];
                }
                $stmt->close();
                $cache[$role] = $out;
                return $out;
            }
        } catch (Throwable $e) {
            error_log('portal_role_permissions: ' . $e->getMessage());
        }
    }

    foreach (portal_legacy_allowed_pages_for_role($role) as $pageKey) {
        $out[$pageKey] = ['can_access' => true, 'can_edit' => false];
    }
    $cache[$role] = $out;
    return $out;
}

function allowed_pages_for_role(string $role): array {
    $out = [];
    foreach (portal_role_permissions($role) as $pageKey => $perm) {
        if (!empty($perm['can_access'])) $out[] = $pageKey;
    }
    return $out;
}

function can_access(string $role, string $pageKey): bool {
    // Per-user override (if configured)
    if (!empty($_SESSION['email'])) {
        $ovr = get_user_page_override((string)$_SESSION['email'], $pageKey);
        if ($ovr !== null) return (bool)$ovr['can_access'];

        // admin_panel used to be hardcoded to admin/developer here. It is now
        // just another row in role_page_permissions (seeded to match exactly
        // that), so admins can grant it without a code change.

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
    // Never emit HTML/JS for API endpoints because it corrupts JSON responses.
    $requestPath = (string)($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['REQUEST_URI'] ?? ''));
    $isApiRequest = defined('IS_API') || preg_match('#(^|/)api(/|$)#i', $requestPath);

    if (!$isApiRequest) {
        $pageKey = get_current_page_key();
        $hideEdits = $pageKey ? !can_edit_page($pageKey) : !is_admin();
        if ($hideEdits) {
            echo "<style>.admin-only, .edit-filter-btn, .edit-dimension-btn, .edit-tire-btn, .upload-btn, #uploadImagesBtn, .editEquipmentBtn, .delete-equipment, .uploadFilterBtn, .add-equipment-btn, .equipment-edit-icon { display: none !important; }</style>";
            echo "<script>(function(){var patterns=[/\\bedit\\b/i,/\\bupload\\b/i,/\\bdelete\\b/i,/\\badd\\b/i,/\\bremove\\b/i];function hideIfMatch(el){var text=(el.innerText||el.value||'').trim();var title=(el.getAttribute&& (el.getAttribute('title')||el.getAttribute('aria-label')))||'';if(!text&& !title) return;var combined=(text+' '+title).trim();for(var i=0;i<patterns.length;i++){if(patterns[i].test(combined)){el.style.display='none';return;}}}document.addEventListener('DOMContentLoaded',function(){var els=document.querySelectorAll('a,button,input[type=button],input[type=submit]');els.forEach(hideIfMatch);});})();</script>";
        }
    }
}
