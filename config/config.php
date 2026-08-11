<?php
/**
 * Database Configuration
 * Works on both Railway (production) and local development.
 *
 * Improvements:
 * - Single canonical block (removed accidental duplicate)
 * - Supports explicit DB_* env vars and a DATABASE_URL fallback
 * - Keeps existing local XAMPP defaults
 */

// Detect production by Railway environment variable presence
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;

// Default (development) settings
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'dhdatabase';
$port = 3306;

if ($isProduction) {
    // Prefer explicit DB_* environment variables when provided
    $envHost = getenv('DB_HOST');
    $envUser = getenv('DB_USER');
    $envPass = getenv('DB_PASSWORD');
    $envName = getenv('DB_NAME');
    $envPort = getenv('DB_PORT');

    if ($envHost !== false && $envHost !== '') $host = $envHost;
    if ($envUser !== false && $envUser !== '') $user = $envUser;
    if ($envPass !== false) $password = $envPass;
    if ($envName !== false && $envName !== '') $database = $envName;
    if ($envPort !== false && $envPort !== '') $port = (int)$envPort;

    // If a DATABASE_URL (or similar) is present, parse it and fill any missing parts.
    // Common platform-provided names: DATABASE_URL, CLEARDB_DATABASE_URL
    $dbUrl = getenv('DATABASE_URL') ?: getenv('CLEARDB_DATABASE_URL') ?: '';
    if ($dbUrl) {
        $parts = parse_url($dbUrl);
        if ($parts !== false) {
            if (!empty($parts['host'])) $host = $parts['host'];
            if (!empty($parts['port'])) $port = (int)$parts['port'];
            if (!empty($parts['user'])) $user = $parts['user'];
            if (isset($parts['pass'])) $password = $parts['pass'];
            if (!empty($parts['path'])) $database = ltrim($parts['path'], '/');
        }
    }
}

// Create database connection
// Log resolved DB parameters in production (avoid logging password)
if ($isProduction) {
    try {
        error_log(sprintf('DB resolve: host=%s user=%s db=%s port=%s', $host, $user, $database, $port));
        // Warn when using common unsafe fallback user
        if ($user === 'root') error_log('DB resolve warning: resolved DB user is "root" in production — check environment variables');
    } catch (Throwable $e) { /* best-effort logging */ }
}

$conn = new mysqli($host, $user, $password, $database, $port);

// Check connection
if ($conn->connect_error) {
    if ($isProduction) {
        error_log('Database connection failed: ' . $conn->connect_error);
        // Generic message for production
        die('Database connection failed. Please contact support.');
    } else {
        die('Connection failed: ' . $conn->connect_error);
    }
}

// Set charset to UTF-8
$conn->set_charset('utf8mb4');

?>
