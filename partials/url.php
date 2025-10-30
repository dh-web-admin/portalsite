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
?>
