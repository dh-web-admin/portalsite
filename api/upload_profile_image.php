<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if (empty($_FILES['profile_image']) || !is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['profile_image'];
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($file['type'], $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File too large']);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/profile_images';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$target = $uploadDir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    exit;
}

// Build public URL relative to application base (handles subdirectory like /PortalSite)
$scriptPath = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$appBase = rtrim(str_replace('\\', '/', dirname(dirname($scriptPath))), '/'); // e.g. /PortalSite
$publicUrl = $appBase . '/uploads/profile_images/' . $filename;
if ($publicUrl === '') $publicUrl = '/uploads/profile_images/' . $filename;
@chmod($target, 0644);

// Ensure column exists (best-effort)
$alterSql = "ALTER TABLE users ADD COLUMN profile_image VARCHAR(1024) NULL";
$conn->query($alterSql);

$email = $_SESSION['email'];
$stmt = $conn->prepare('UPDATE users SET profile_image = ? WHERE email = ?');
$stmt->bind_param('ss', $publicUrl, $email);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    echo json_encode(['success' => true, 'url' => $publicUrl]);
} else {
    echo json_encode(['success' => false, 'error' => 'DB update failed']);
}
