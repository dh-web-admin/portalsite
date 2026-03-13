<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit();
}

if (!isset($_GET['item_id']) || !is_numeric($_GET['item_id'])) {
    http_response_code(400);
    echo 'Invalid item ID';
    exit();
}

if (!isset($_GET['version']) || !preg_match('/^v\d+$/i', $_GET['version'])) {
    http_response_code(400);
    echo 'Invalid version';
    exit();
}

$itemId = intval($_GET['item_id']);
$version = strtolower(trim($_GET['version']));

function rrmdir($dirPath) {
    if (!is_dir($dirPath)) {
        return;
    }
    $items = scandir($dirPath);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dirPath . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dirPath);
}

function sendZipResponse($zipPath, $itemId, $version) {
    if (!is_file($zipPath)) {
        return false;
    }
    $downloadName = 'engineering_item_' . $itemId . '_' . strtoupper($version) . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($zipPath));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    readfile($zipPath);
    return true;
}

try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'engineering_drawings'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        http_response_code(404);
        echo 'No drawings table found';
        exit();
    }

    $partColumnCheck = $conn->query("SHOW COLUMNS FROM engineering_drawings LIKE 'part_id'");
    if (!$partColumnCheck || $partColumnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE engineering_drawings ADD COLUMN part_id INT(11) NULL DEFAULT NULL AFTER item_id");
        $conn->query("CREATE INDEX idx_part_id ON engineering_drawings (part_id)");
        $conn->query("CREATE INDEX idx_item_part_version ON engineering_drawings (item_id, part_id, version)");
    }

    $itemName = 'Item_' . $itemId;
    $itemStmt = $conn->prepare("SELECT name FROM engineering_items WHERE id = ? LIMIT 1");
    if ($itemStmt) {
        $itemStmt->bind_param('i', $itemId);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();
        if ($itemResult && ($itemRow = $itemResult->fetch_assoc()) && !empty($itemRow['name'])) {
            $itemName = (string) $itemRow['name'];
        }
        $itemStmt->close();
    }

    $safeItemName = preg_replace('/[^A-Za-z0-9]+/', '_', trim($itemName));
    $safeItemName = trim((string)$safeItemName, '_');
    if ($safeItemName === '') {
        $safeItemName = 'Item_' . $itemId;
    }
    $bundleName = $safeItemName . '_Drawings_' . strtoupper($version);
    $downloadName = $bundleName . '.zip';

    $stmt = $conn->prepare("SELECT filename, file_url FROM engineering_drawings WHERE item_id = ? AND part_id IS NULL AND LOWER(version) = ? ORDER BY id ASC");
    $stmt->bind_param('is', $itemId, $version);
    $stmt->execute();
    $result = $stmt->get_result();

    $files = [];
    while ($row = $result->fetch_assoc()) {
        $files[] = $row;
    }
    $stmt->close();

    if (empty($files)) {
        http_response_code(404);
        echo 'No files found for this version';
        exit();
    }

    $rootPath = realpath(__DIR__ . '/..');
    if ($rootPath === false) {
        http_response_code(500);
        echo 'Failed to resolve application root path';
        exit();
    }

    $resolvedFiles = [];
    $usedNames = [];
    foreach ($files as $file) {
        $urlPath = (string) $file['file_url'];
        $urlPath = preg_replace('#^/PortalSite/#', '/', $urlPath);
        $relativePath = ltrim($urlPath, '/');
        $absolutePath = realpath($rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

        if ($absolutePath === false || strpos($absolutePath, $rootPath) !== 0 || !is_file($absolutePath)) {
            continue;
        }

        $entryName = basename((string) $file['filename']);
        if ($entryName === '') {
            $entryName = basename($absolutePath);
        }

        if (isset($usedNames[$entryName])) {
            $usedNames[$entryName]++;
            $nameInfo = pathinfo($entryName);
            $base = isset($nameInfo['filename']) ? $nameInfo['filename'] : 'file';
            $ext = isset($nameInfo['extension']) ? '.' . $nameInfo['extension'] : '';
            $entryName = $base . '_' . $usedNames[$entryName] . $ext;
        } else {
            $usedNames[$entryName] = 1;
        }

        $resolvedFiles[] = [
            'source' => $absolutePath,
            'name' => $entryName,
        ];
    }

    if (empty($resolvedFiles)) {
        http_response_code(404);
        echo 'No downloadable files found for this version';
        exit();
    }

    $tmpBase = tempnam(sys_get_temp_dir(), 'eng_zip_');
    if ($tmpBase === false) {
        http_response_code(500);
        echo 'Failed to create temporary zip file';
        exit();
    }

    @unlink($tmpBase);
    $tmpZipPath = $tmpBase . '.zip';

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($tmpZipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpZipPath);
            http_response_code(500);
            echo 'Failed to create zip archive';
            exit();
        }

        $addedCount = 0;
        foreach ($resolvedFiles as $entry) {
            if ($zip->addFile($entry['source'], $bundleName . '/' . $entry['name'])) {
                $addedCount++;
            }
        }
        $zip->close();

        if ($addedCount === 0 || !is_file($tmpZipPath)) {
            @unlink($tmpZipPath);
            http_response_code(404);
            echo 'No downloadable files found for this version';
            exit();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($tmpZipPath));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        readfile($tmpZipPath);
        @unlink($tmpZipPath);
        exit();
    }

    $stagingDir = $tmpBase . '_files';
    if (!@mkdir($stagingDir, 0755, true) && !is_dir($stagingDir)) {
        http_response_code(500);
        echo 'Failed to prepare temporary files';
        exit();
    }

    $bundleDir = $stagingDir . DIRECTORY_SEPARATOR . $bundleName;
    if (!@mkdir($bundleDir, 0755, true) && !is_dir($bundleDir)) {
        rrmdir($stagingDir);
        http_response_code(500);
        echo 'Failed to prepare bundle folder';
        exit();
    }

    $copiedCount = 0;
    foreach ($resolvedFiles as $entry) {
        if (@copy($entry['source'], $bundleDir . DIRECTORY_SEPARATOR . $entry['name'])) {
            $copiedCount++;
        }
    }

    if ($copiedCount === 0) {
        rrmdir($stagingDir);
        http_response_code(404);
        echo 'No downloadable files found for this version';
        exit();
    }

    $zipCreated = false;
    if (function_exists('shell_exec')) {
        $osFamily = PHP_OS_FAMILY;
        if ($osFamily === 'Windows') {
            $psSource = str_replace("'", "''", $stagingDir . DIRECTORY_SEPARATOR . '*');
            $psDest = str_replace("'", "''", $tmpZipPath);
            $cmd = "powershell -NoProfile -NonInteractive -Command \"Compress-Archive -Path '$psSource' -DestinationPath '$psDest' -Force\"";
            @shell_exec($cmd . ' 2>NUL');
            $zipCreated = is_file($tmpZipPath) && filesize($tmpZipPath) > 0;
        } else {
            $sourceEscaped = escapeshellarg($stagingDir);
            $zipEscaped = escapeshellarg($tmpZipPath);
            @shell_exec('cd ' . $sourceEscaped . ' && zip -q -r ' . $zipEscaped . ' . 2>/dev/null');
            $zipCreated = is_file($tmpZipPath) && filesize($tmpZipPath) > 0;
        }
    }

    rrmdir($stagingDir);

    if (!$zipCreated) {
        http_response_code(500);
        echo 'ZIP download is unavailable. Enable PHP ZipArchive extension or shell zip support on the server.';
        exit();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($tmpZipPath));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    readfile($tmpZipPath);
    @unlink($tmpZipPath);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo 'Failed to generate zip: ' . $e->getMessage();
}
