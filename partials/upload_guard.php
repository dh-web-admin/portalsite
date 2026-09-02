<?php
/**
 * Upload safety helpers (P3 — prevents uploaded files from executing as code).
 *
 * require_once __DIR__ . '/../partials/upload_guard.php';
 *
 *   upload_extension_is_dangerous($ext): bool
 *       True when $ext could be executed / interpreted by the web server
 *       (PHP, CGI, server-side includes, .htaccess) or used for stored XSS
 *       (svg/html). Use at sites that already computed an extension.
 *
 *   safe_upload_extension($file, $allowed = null): ?string
 *       Full check. $file is a ['name' => ..., 'tmp_name' => ...] array
 *       (works with a single $_FILES entry or one slice of a multi-file one).
 *         - rejects dangerous extensions unconditionally
 *         - if the name claims an image type, the bytes must really be an image
 *         - if the bytes are an image but the name is not, reject (polyglot)
 *         - if $allowed is an array, the extension must be in it
 *       Returns the ORIGINAL lowercase extension (no remapping, so .xlsx / .dwg
 *       / .step keep their extension) or null to reject.
 */

function upload_extension_is_dangerous(string $ext): bool
{
    static $blocked = [
        'php', 'php2', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phtml',
        'phar', 'phps', 'phpt', 'inc',
        'pl', 'pm', 'py', 'pyc', 'rb', 'cgi', 'sh', 'bash', 'ksh',
        'asp', 'aspx', 'asa', 'asax', 'jsp', 'jspx', 'jsw', 'jsv',
        'shtml', 'shtm', 'stm',
        'htaccess', 'htpasswd', 'user.ini',
        'svg', 'svgz', 'html', 'htm', 'xhtml', 'xht',
    ];
    $ext = strtolower(ltrim($ext, '.'));
    return $ext === '' || in_array($ext, $blocked, true);
}

function safe_upload_extension(array $file, ?array $allowed = null): ?string
{
    $tmp  = $file['tmp_name'] ?? '';
    $name = $file['name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (upload_extension_is_dangerous($ext)) {
        return null;
    }

    $imageExts  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $imageMimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/webp'];

    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = (string) finfo_file($fi, $tmp);
            finfo_close($fi);
        }
    }

    $claimsImage = in_array($ext, $imageExts, true);
    if ($claimsImage && @getimagesize($tmp) === false) {
        return null; // says .png, isn't an image
    }
    if (!$claimsImage && $mime !== '' && in_array($mime, $imageMimes, true)) {
        return null; // is an image, hiding behind another extension
    }

    if ($allowed !== null && !in_array($ext, $allowed, true)) {
        return null;
    }

    return $ext;
}
