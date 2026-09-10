<?php
// Simple URL helper to build links that work in both local (XAMPP) and Railway
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        // On Railway, app runs at domain root. Locally, it's typically /PortalSite
        $isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
        $base = $isProduction ? '' : '/PortalSite';
        // Ensure leading slash for provided path
        if ($path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }
        return $base . $path;
    }
}

if (!function_exists('take_intended_url')) {
    /**
     * One-shot read of the page a signed-out visitor originally asked for
     * (captured in session_init.php). Returns null when there isn't one, so
     * callers fall back to their normal post-login destination.
     *
     * Re-validated here rather than trusted: only a same-site absolute path
     * is ever returned, never "//host" or an absolute URL.
     */
    function take_intended_url(): ?string {
        $url = $_SESSION['intended_url'] ?? null;
        unset($_SESSION['intended_url']);

        if (!is_string($url) || $url === '' || $url[0] !== '/' || strncmp($url, '//', 2) === 0) {
            return null;
        }
        // Defence in depth: header() already refuses newlines, but never let a
        // control character reach a Location header regardless.
        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return null;
        }
        return $url;
    }
}
?>
