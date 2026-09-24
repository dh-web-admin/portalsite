<?php
/**
 * Central page access gate.
 *
 * Required at the bottom of session_init.php, which every entry point in the
 * portal already loads, so a page cannot opt out of this by forgetting to add
 * a check — and a page that skips session_init.php has no session at all, so
 * it could not authenticate anyone anyway.
 *
 * Scope (deliberately narrow for this pass):
 *   /pages/<key>/...  -> gated on page key <key>
 *   /admin/...        -> gated on 'admin_panel'
 * Everything else (api/, auth/, dev/, scripts/, assets/, uploads/) is left to
 * its own existing checks. API endpoints are a separate follow-up because they
 * need JSON refusals and a filename->page-key map that does not exist yet.
 *
 * Unknown page keys are registered on first sight and denied to every role,
 * so a brand new page shows up in the admin permissions screen locked, rather
 * than being quietly reachable by anyone with a login.
 */

if (defined('PORTAL_ACCESS_GATE_RAN')) return;
define('PORTAL_ACCESS_GATE_RAN', true);

// CLI (migrations, cron scripts) has no request to gate.
if (PHP_SAPI === 'cli' || empty($_SERVER['REQUEST_URI'])) return;

$gatePath = parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
// Normalise away the local /PortalSite mount so one set of rules covers both
// XAMPP and the Railway root deployment.
$gatePath = preg_replace('#^/PortalSite#i', '', $gatePath);
if ($gatePath === '' || $gatePath[0] !== '/') $gatePath = '/' . $gatePath;

/*
 * A lock applied while someone is signed in has to take effect on their next
 * request, otherwise "locked" means "still browsing for up to ten hours".
 * Checked for any authenticated request — pages and API alike — but throttled,
 * so it costs one indexed lookup a minute per session rather than one per hit.
 */
if (!empty($_SESSION['email'])) {
    $gateNow = time();
    if ($gateNow - (int)($_SESSION['lock_check_at'] ?? 0) >= 60) {
        $_SESSION['lock_check_at'] = $gateNow;

        require_once __DIR__ . '/../config/config.php';
        if (isset($conn)) $GLOBALS['conn'] = $conn;
        require_once __DIR__ . '/account_lock.php';

        $gateLock = account_lock_status($conn, (string)$_SESSION['email']);
        if ($gateLock && $gateLock['locked']) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            @session_unset();
            @session_destroy();

            // Never redirect an API caller into an HTML login page; with the
            // session gone its own auth check returns the usual JSON 401.
            if (!preg_match('#^/api/#i', $gatePath)) {
                require_once __DIR__ . '/url.php';
                header('Location: ' . base_url('/auth/login.php'));
                exit();
            }
            return;
        }
    }
}

$gateKey = null;
if (preg_match('#^/pages/([^/]+)#i', $gatePath, $m)) {
    $gateKey = $m[1];
} elseif (preg_match('#^/admin(/|$)#i', $gatePath)) {
    $gateKey = 'admin_panel';
}

// Not a gated area.
if ($gateKey === null) return;

/*
 * Baseline pages every signed-in user needs regardless of role: the dashboard
 * they land on after login and their own account settings. These are not
 * gated on purpose — if they were rows in the table, one wrong click in the
 * permissions screen would lock everyone out of the portal.
 */
$gateBaseline = ['dashboard', 'account_settings'];

require_once __DIR__ . '/url.php';

if (empty($_SESSION['email'])) {
    // session_init.php has already stashed intended_url for the trip back.
    header('Location: ' . base_url('/auth/login.php'));
    exit();
}

if (in_array($gateKey, $gateBaseline, true)) return;

require_once __DIR__ . '/../config/config.php';
if (isset($conn)) $GLOBALS['conn'] = $conn;
require_once __DIR__ . '/permissions.php';

// First sighting of a page key records it, denied for every role.
portal_register_page($gateKey);

$gateRole = function_exists('get_current_role') ? get_current_role() : null;
if ($gateRole === null || !can_access((string)$gateRole, $gateKey)) {
    $_SESSION['access_denied_page'] = $gateKey;
    header('Location: ' . base_url('/pages/dashboard/'));
    exit();
}

unset($gatePath, $gateKey, $gateBaseline, $gateRole, $m);
