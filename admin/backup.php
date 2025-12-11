<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
// URL helper for base_url()
require_once __DIR__ . '/../partials/url.php';

// Only allow admin users
$role = $_SESSION['role'] ?? null;
if ($role !== 'admin') {
    http_response_code(403);
    echo '<h2>Access denied</h2><p>You must be an admin to access this page.</p>';
    exit();
}

$mysqli = $conn; // config.php provides $conn

// Fetch tables
$tables = [];
$res = $mysqli->query("SHOW TABLES");
if ($res) {
    while ($row = $res->fetch_array()) {
        $tables[] = $row[0];
    }
}

// Helper: table row count
function table_count($conn, $table) {
    $safe = $conn->real_escape_string($table);
    $r = $conn->query("SELECT COUNT(*) AS c FROM `" . $safe . "`");
    if ($r) return (int)$r->fetch_assoc()['c'];
    return 0;
}

// ---- Download handlers -------------------------------------------------------
// Per-table CSV
if (isset($_GET['download_table'])) {
    $table = $mysqli->real_escape_string($_GET['download_table']);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $table . '.csv"');
    $out = fopen('php://output', 'w');
    $r = $mysqli->query("SELECT * FROM `$table` LIMIT 10000");
    if ($r) {
        $first = true;
        while ($row = $r->fetch_assoc()) {
            if ($first) { fputcsv($out, array_keys($row)); $first = false; }
            fputcsv($out, array_values($row));
        }
    }
    fclose($out);
    exit;
}

// Per-table Excel-compatible (HTML table)
if (isset($_GET['download_table_xls'])) {
    $table = $mysqli->real_escape_string($_GET['download_table_xls']);
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $table . '.xls"');
    echo "\xEF\xBB\xBF"; // BOM
    echo "<table border=1><tr>";
    $r = $mysqli->query("SELECT * FROM `$table` LIMIT 10000");
    $first = true;
    while ($row = $r->fetch_assoc()) {
        if ($first) { foreach (array_keys($row) as $h) echo "<th>".htmlspecialchars($h)."</th>"; echo "</tr>"; $first = false; }
        echo "<tr>";
        foreach ($row as $v) echo "<td>".htmlspecialchars($v)."</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

// Bulk ZIP of all tables (CSV)
if (isset($_GET['download_all_zip'])) {
    $tmpdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'portalsite_backup_' . time() . '_' . uniqid();
    if (!mkdir($tmpdir) && !is_dir($tmpdir)) {
        http_response_code(500);
        echo 'Failed to create temp directory.';
        exit;
    }
    $filenames = [];
    foreach ($tables as $table) {
        $fname = $tmpdir . DIRECTORY_SEPARATOR . $table . '.csv';
        $filenames[] = $fname;
        $out = fopen($fname, 'w');
        $r = $mysqli->query("SELECT * FROM `$table`");
        if ($r) {
            $first = true;
            while ($row = $r->fetch_assoc()) {
                if ($first) { fputcsv($out, array_keys($row)); $first = false; }
                fputcsv($out, array_values($row));
            }
        }
        fclose($out);
    }

    // Prefer ZipArchive when available
    $timestamp = date('Ymd_His');
    $zipname = $tmpdir . DIRECTORY_SEPARATOR . 'portalsite_backup_' . $timestamp . '.zip';
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipname, ZipArchive::CREATE) === TRUE) {
            foreach ($filenames as $f) $zip->addFile($f, basename($f));
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($zipname) . '"');
            readfile($zipname);
            // cleanup
            foreach ($filenames as $f) @unlink($f);
            @unlink($zipname);
            @rmdir($tmpdir);
            exit;
        } else {
            http_response_code(500);
            echo 'Failed to create zip archive.';
            // cleanup files
            foreach ($filenames as $f) @unlink($f);
            @rmdir($tmpdir);
            exit;
        }
    }

    // Fallback: use PharData to create a tar.gz if available
    if (class_exists('PharData')) {
        try {
            $tarPath = $tmpdir . DIRECTORY_SEPARATOR . 'portalsite_backup_' . $timestamp . '.tar';
            $phar = new PharData($tarPath);
            foreach ($filenames as $f) {
                $phar->addFile($f, basename($f));
            }
            // compress to gzip (.tar.gz)
            $phar->compress(Phar::GZ);
            $gzPath = $tarPath . '.gz';
            header('Content-Type: application/gzip');
            header('Content-Disposition: attachment; filename="' . basename($gzPath) . '"');
            readfile($gzPath);
            // cleanup
            foreach ($filenames as $f) @unlink($f);
            @unlink($tarPath);
            @unlink($gzPath);
            @rmdir($tmpdir);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo 'Failed to create archive: ' . htmlspecialchars($e->getMessage());
            foreach ($filenames as $f) @unlink($f);
            @rmdir($tmpdir);
            exit;
        }
    }

    // Last resort: combine all CSVs into one downloadable CSV with table markers
    $combined = $tmpdir . DIRECTORY_SEPARATOR . 'portalsite_combined_' . $timestamp . '.csv';
    $outc = fopen($combined, 'w');
    foreach ($filenames as $f) {
        $tableName = pathinfo($f, PATHINFO_FILENAME);
        fwrite($outc, "## Table: " . $tableName . "\n");
        $fh = fopen($f, 'r');
        if ($fh) {
            while (($line = fgets($fh)) !== false) fwrite($outc, $line);
            fclose($fh);
        }
        fwrite($outc, "\n");
    }
    fclose($outc);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . basename($combined) . '"');
    readfile($combined);
    // cleanup
    foreach ($filenames as $f) @unlink($f);
    @unlink($combined);
    @rmdir($tmpdir);
    exit;
}
// -----------------------------------------------------------------------------
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Database Backup (Admin)</title>
    <style>
        body{font-family:Arial, Helvetica, sans-serif; margin:18px; background:#f3f4f6}
        .tables-list{display:flex;flex-direction:column;gap:12px;max-width:1100px}
        .table-card{border:1px solid #e5e7eb;padding:10px;border-radius:6px;background:#fff}
        .table-card-header{display:flex;align-items:center;gap:12px}
        .table-title{font-weight:700;flex:1}
        .table-meta{color:#6b7280}
        .table-actions{display:flex;gap:8px}
        .preview{display:none;margin-top:8px}
        .preview.open{display:block}
        .preview-table{border-collapse:collapse;width:100%}
        .preview-table th,.preview-table td{border:1px solid #e5e7eb;padding:6px;text-align:left}
        .btn{background:#2d6cdf;color:#fff;padding:6px 10px;text-decoration:none;border-radius:4px;border:0;cursor:pointer}
        .muted{color:#6b7280}
    </style>
</head>
<body>
    <div style="display:flex;align-items:center;gap:12px;">
        <h1 style="margin:0">Database Backup (Admin)</h1>
        <div style="margin-left:auto">
            <a class="btn" href="<?php echo htmlspecialchars(base_url('/pages/dashboard/')); ?>">Home</a>
        </div>
    </div>
    <p class="muted">Below are the existing tables in the database. Use Preview to inspect first rows, or download per table.</p>

    <p><a class="btn" href="?download_all_zip=1">Download All Tables (ZIP)</a></p>

    <div class="tables-list">
    <?php foreach ($tables as $t):
        $count = table_count($mysqli, $t);
    ?>
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-title"><?php echo htmlspecialchars($t); ?></div>
                <div class="table-meta"><?php echo number_format($count); ?> rows</div>
                <div class="table-actions">
                    <a class="btn" href="?download_table=<?php echo urlencode($t); ?>">CSV</a>
                    <a class="btn" href="?download_table_xls=<?php echo urlencode($t); ?>">Excel</a>
                    <button class="btn toggle-preview" data-table="<?php echo htmlspecialchars($t); ?>">Preview</button>
                </div>
            </div>
            <div class="preview" id="preview-<?php echo htmlspecialchars($t); ?>">
                <?php
                $safeT = $mysqli->real_escape_string($t);
                $res2 = $mysqli->query("SELECT * FROM `" . $safeT . "` LIMIT 10");
                if ($res2 && $res2->num_rows) {
                    echo '<table class="preview-table">';
                    $first = true;
                    while ($row = $res2->fetch_assoc()) {
                        if ($first) {
                            echo '<tr>'; foreach (array_keys($row) as $h) echo '<th>'.htmlspecialchars($h).'</th>'; echo '</tr>';
                            $first = false;
                        }
                        echo '<tr>'; foreach ($row as $c) echo '<td>'.htmlspecialchars(substr((string)$c,0,200)).'</td>'; echo '</tr>';
                    }
                    echo '</table>';
                    $res2->free();
                } else {
                    echo '<em>(no rows)</em>';
                }
                ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        var toggles = document.querySelectorAll('.toggle-preview');
        toggles.forEach(function(btn){
            btn.addEventListener('click', function(){
                var id = 'preview-' + btn.getAttribute('data-table');
                var el = document.getElementById(id);
                if (!el) return;
                el.classList.toggle('open');
            });
        });
    });
    </script>
</body>
</html>
