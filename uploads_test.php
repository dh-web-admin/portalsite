<?php
// uploads_test.php
// Place this file in your PortalSite root and open in browser

$dir = __DIR__ . '/PortalSite/uploads/equipment/';

if (!is_dir($dir)) {
    echo "<b>Directory does not exist:</b> $dir";
    exit;
}


// Recursively list all files in the uploads volume
function listFilesRecursive($baseDir, $baseWeb) {
    $results = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $relPath = ltrim(str_replace($baseDir, '', $file->getPathname()), '/\\');
        $webPath = $baseWeb . str_replace(DIRECTORY_SEPARATOR, '/', $relPath);
        $results[] = [
            'name' => $relPath,
            'exists' => $file->isFile(),
            'readable' => $file->isReadable(),
            'size' => $file->getSize(),
            'webPath' => $webPath
        ];
    }
    return $results;
}

$results = listFilesRecursive($dir, '/PortalSite/uploads/equipment/');
?>
<!DOCTYPE html>
<html><head><title>Uploads Directory Test</title></head><body>
<h2>Uploads Directory Test</h2>
<p>Directory: <b><?= htmlspecialchars($dir) ?></b></p>
<table border="1" cellpadding="6" style="border-collapse:collapse;">
<tr><th>File</th><th>Exists</th><th>Readable</th><th>Size (bytes)</th><th>Web URL</th><th>Open</th></tr>
<?php foreach ($results as $row): ?>
<tr>
    <td><?= htmlspecialchars($row['name']) ?></td>
    <td style="color:<?= $row['exists'] ? 'green' : 'red' ?>;"><?= $row['exists'] ? 'Yes' : 'No' ?></td>
    <td style="color:<?= $row['readable'] ? 'green' : 'red' ?>;"><?= $row['readable'] ? 'Yes' : 'No' ?></td>
    <td><?= $row['size'] ?></td>
    <td><a href="<?= htmlspecialchars($row['webPath']) ?>" target="_blank"><?= htmlspecialchars($row['webPath']) ?></a></td>
    <td><a href="<?= htmlspecialchars($row['webPath']) ?>" target="_blank">Open</a></td>
</tr>
<?php endforeach; ?>
</table>
</body></html>
