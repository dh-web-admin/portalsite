<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$email = $_SESSION['email'];
// get user id
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}
$user_id = $user['id'];

// Ensure table exists (best-effort) - only attempt if migration file exists
$migrationsPath = __DIR__ . '/../migrations/001_create_user_details.sql';
$createSql = '';
if (file_exists($migrationsPath) && is_readable($migrationsPath)) {
    $createSql = file_get_contents($migrationsPath);
    if ($createSql !== false && trim($createSql) !== '') {
        if ($conn->multi_query($createSql)) {
            // flush multi_query results
            do {
                // no-op: advance to next result
            } while ($conn->more_results() && $conn->next_result());
        }
    }
}

// handle file upload
$profileUrl = null;
// Handle remove request (AJAX from client)
if (isset($_POST['remove_picture']) && $_POST['remove_picture']) {
    // fetch existing value
    $stmt = $conn->prepare('SELECT profile_picture FROM user_details WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    // attempt to unlink the stored file if it appears local
    if ($row && !empty($row['profile_picture'])) {
        $pp = $row['profile_picture'];
        $uPrefix = '/uploads/profile_images/';
        if (strpos($pp, $uPrefix) !== false) {
            $rel = substr($pp, strpos($pp, $uPrefix));
            $filePath = __DIR__ . '/..' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (file_exists($filePath)) @unlink($filePath);
        }
    }

    // clear DB values
    $upd = $conn->prepare('UPDATE user_details SET profile_picture = NULL WHERE user_id = ?');
    if ($upd) { $upd->bind_param('i',$user_id); $upd->execute(); $upd->close(); }

    // also clear legacy users.profile_image if the column exists
    $colRes = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    if ($colRes && $colRes->num_rows > 0) {
        $upd2 = $conn->prepare('UPDATE users SET profile_image = NULL WHERE id = ?');
        if ($upd2) { $upd2->bind_param('i',$user_id); $upd2->execute(); $upd2->close(); }
    }

    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['profile_image'])) unset($_SESSION['profile_image']);
    echo json_encode(['success' => true]);
    exit;
}
if (!empty($_FILES['profile_picture']) && is_uploaded_file($_FILES['profile_picture']['tmp_name'])) {
    $file = $_FILES['profile_picture'];
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type']);
        exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File too large']);
        exit;
    }
    $uploadDir = __DIR__ . '/../uploads/profile_images';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
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
    $profileUrl = $appBase . '/uploads/profile_images/' . $filename;
    // ensure leading slash
    if ($profileUrl === '') $profileUrl = '/uploads/profile_images/' . $filename;
    // set permissions on saved file
    @chmod($target, 0644);
}

// collect other fields
$street = isset($_POST['street_address']) ? trim($_POST['street_address']) : null;
$city = isset($_POST['city']) ? trim($_POST['city']) : null;
$state = isset($_POST['state']) ? trim($_POST['state']) : null;
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
// optionally update first/last name on users table for current user
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : null;
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : null;

// upsert into user_details
$stmt = $conn->prepare('INSERT INTO user_details (user_id, profile_picture, street_address, city, state, phone) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE profile_picture = VALUES(profile_picture), street_address = VALUES(street_address), city = VALUES(city), state = VALUES(state), phone = VALUES(phone), updated_at = CURRENT_TIMESTAMP');
$stmt->bind_param('isssss', $user_id, $profileUrl, $street, $city, $state, $phone);
$ok = $stmt->execute();
$stmt->close();

// Update users table first_name/last_name if provided (self-service), only when columns exist
$col1 = $conn->query("SHOW COLUMNS FROM users LIKE 'first_name'");
$col2 = $conn->query("SHOW COLUMNS FROM users LIKE 'last_name'");
$hasFirst = ($col1 && $col1->num_rows > 0);
$hasLast = ($col2 && $col2->num_rows > 0);
if (($first_name !== null && $first_name !== '') || ($last_name !== null && $last_name !== '')) {
    $sets = [];
    $types = '';
    $params = [];
    if ($hasFirst && $first_name !== null && $first_name !== '') { $sets[] = 'first_name = ?'; $types .= 's'; $params[] = $first_name; }
    if ($hasLast && $last_name !== null && $last_name !== '') { $sets[] = 'last_name = ?'; $types .= 's'; $params[] = $last_name; }
    if (!empty($sets)) {
        $types .= 'i';
        $params[] = $user_id;
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $upd = $conn->prepare($sql);
        if ($upd) {
            $upd->bind_param($types, ...$params);
            $upd->execute();
            $upd->close();
            // update session display name if present
            if (isset($_SESSION['name'])) {
                $displayFirst = $hasFirst ? ($first_name ?: '') : '';
                $displayLast = $hasLast ? ($last_name ?: '') : '';
                // fall back to existing name parts when one side missing
                if (!$displayFirst) {
                    $existingParts = array_values(array_filter(explode(' ', trim($_SESSION['name']))));
                    $displayFirst = $existingParts[0] ?? '';
                }
                if (!$displayLast) {
                    $existingParts = array_values(array_filter(explode(' ', trim($_SESSION['name']))));
                    $displayLast = isset($existingParts[1]) ? implode(' ', array_slice($existingParts,1)) : '';
                }
                $_SESSION['name'] = trim(($displayFirst . ' ' . $displayLast));
            }
        }
    }
}

if ($ok) {
    // If we saved a new profile image, ensure session reflects it so header can show immediately
    if (!empty($profileUrl) && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['profile_image'] = $profileUrl;
    }

    echo json_encode(['success' => true, 'url' => $profileUrl]);
} else {
    echo json_encode(['success' => false, 'error' => 'DB error']);
}
