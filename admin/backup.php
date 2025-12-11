<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

// Only allow admin users
$role = $_SESSION['role'] ?? null;
if ($role !== 'admin') {
		http_response_code(403);
		echo '<h2>Access denied</h2><p>You must be an admin to access this page.</p>';
		exit();
}

// Helper: get all table names
function get_tables($conn) {
		$tables = [];
		$res = $conn->query("SHOW TABLES");
		if ($res) {
				while ($row = $res->fetch_array(MYSQLI_NUM)) {
						$tables[] = $row[0];
				}
				$res->free();
		}
		return $tables;
}

// Helper: get row count for table
function table_count($conn, $table) {
		$safe = $conn->real_escape_string($table);
		$res = $conn->query("SELECT COUNT(*) AS c FROM `" . $safe . "`");
		if ($res) {
				$r = $res->fetch_assoc();
				$res->free();
				return intval($r['c'] ?? 0);
		}
		return 0;
}

// Download handler: stream entire table as CSV or Excel (HTML .xls)
if (isset($_GET['download'])) {
	$table = $_GET['download'];
	$format = strtolower($_GET['format'] ?? 'csv'); // csv or excel
	$tables = get_tables($conn);
	if (!in_array($table, $tables, true)) {
		http_response_code(400);
		echo 'Invalid table specified.';
		exit();
	}

	$safeTable = $conn->real_escape_string($table);
	$query = "SELECT * FROM `" . $safeTable . "`";
	$result = $conn->query($query);
	if (!$result) {
		http_response_code(500);
		echo 'Query failed: ' . htmlspecialchars($conn->error);
		exit();
	}

	if ($format === 'excel' || $format === 'xls') {
		// Excel-compatible HTML table (works well for most MS Excel versions)
		$filename = $table . '_' . date('Ymd_His') . '.xls';
		header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		// BOM for UTF-8
		echo "\xEF\xBB\xBF";

		echo "<table border=1>\n";
		$first = $result->fetch_assoc();
		if ($first) {
			echo "<tr>";
			foreach (array_keys($first) as $h) {
				echo '<th>' . htmlspecialchars($h) . '</th>';
			}
			echo "</tr>\n";
			// first row
			echo "<tr>";
			foreach ($first as $c) echo '<td>' . htmlspecialchars((string)$c) . '</td>';
			echo "</tr>\n";
		}
		while ($row = $result->fetch_assoc()) {
			echo "<tr>";
			foreach ($row as $c) echo '<td>' . htmlspecialchars((string)$c) . '</td>';
			echo "</tr>\n";
		}
		echo "</table>";
		$result->free();
		exit();
	} else {
		// Default CSV
		$filename = $table . '_' . date('Ymd_His') . '.csv';
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		$out = fopen('php://output', 'w');
		if ($out === false) {
			http_response_code(500);
			echo 'Failed to open output stream.';
			exit();
		}

		// Write header and rows
		rewind($result);
		// mysqli_result doesn't support rewind; we already used first row earlier for csv -> restructure
		// Instead, execute query again for CSV to ensure full rows
		$result->free();
		$result2 = $conn->query($query);
		if ($result2) {
			$first = $result2->fetch_assoc();
			if ($first) {
				fputcsv($out, array_keys($first));
				fputcsv($out, array_values($first));
			}
			while ($row = $result2->fetch_assoc()) {
				fputcsv($out, array_values($row));
			}
			$result2->free();
		}
		fclose($out);
		exit();
	}
}

$tables = get_tables($conn);
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>DB Backup - Admin</title>
	<style>
		body{font-family: Arial, Helvetica, sans-serif; margin:20px}
		table{border-collapse:collapse;width:100%;margin-bottom:20px}
		th,td{border:1px solid #ccc;padding:8px;text-align:left}
		.preview{max-height:200px;overflow:auto;background:#f9f9f9}
		.download{white-space:nowrap}
		.btn{background:#2d6cdf;color:#fff;padding:6px 10px;text-decoration:none;border-radius:4px}
	</style>
</head>
<body>
	<h1>Database Backup (Admin)</h1>
	<p>Below are the existing tables in the database. Click "Download" to export the entire table as CSV.</p>

	<table>
		<thead>
			<tr><th>Table</th><th>Rows</th><th>Preview (first 10 rows)</th><th>Download</th></tr>
		</thead>
		<tbody>
<?php foreach ($tables as $t): ?>
			<tr>
				<td><?php echo htmlspecialchars($t); ?></td>
				<td><?php echo table_count($conn, $t); ?></td>
				<td class="preview">
					<?php
						// Show a small preview of first 10 rows
						$safeT = $conn->real_escape_string($t);
						$res = $conn->query("SELECT * FROM `" . $safeT . "` LIMIT 10");
						if ($res && $res->num_rows) {
								echo '<table>'; 
								// header
								$row = $res->fetch_assoc();
								echo '<tr>'; foreach(array_keys($row) as $h) echo '<th>'.htmlspecialchars($h).'</th>'; echo '</tr>';
								// first row
								echo '<tr>'; foreach($row as $c) echo '<td>'.htmlspecialchars(substr((string)$c,0,200)).'</td>'; echo '</tr>';
								// remaining rows
								while($r = $res->fetch_assoc()){
										echo '<tr>'; foreach($r as $c) echo '<td>'.htmlspecialchars(substr((string)$c,0,200)).'</td>'; echo '</tr>';
								}
								echo '</table>';
								$res->free();
						} else {
								echo '<em>(no rows)</em>';
						}
					?>
				</td>
				<td class="download">
				  <a class="btn" href="?download=<?php echo urlencode($t); ?>&format=csv">CSV</a>
				  &nbsp;
				  <a class="btn" href="?download=<?php echo urlencode($t); ?>&format=excel">Excel</a>
				</td>
			</tr>
<?php endforeach; ?>
		</tbody>
	</table>
		  <p>
			<strong>Bulk export:</strong>
			<a class="btn" href="?export_all=zip&format=csv">Download all tables (ZIP, CSV)</a>
			&nbsp;
			<a class="btn" href="?export_all=zip&format=excel">Download all tables (ZIP, Excel)</a>
		  </p>
</body>
</html>
 
		<?php
		// Bulk export handler: generate per-table files, zip them, stream, cleanup
		if (isset($_GET['export_all']) && $_GET['export_all'] === 'zip') {
			$format = strtolower($_GET['format'] ?? 'csv');
			$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sso_backup_' . uniqid();
			if (!mkdir($tmpDir) && !is_dir($tmpDir)) {
				http_response_code(500);
				echo 'Failed to create temp directory.';
				exit();
			}

			$createdFiles = [];
			foreach ($tables as $table) {
				$safe = $conn->real_escape_string($table);
				$query = "SELECT * FROM `" . $safe . "`";
				$result = $conn->query($query);
				if (!$result) continue;

				$basename = $table . ($format === 'excel' ? '.xls' : '.csv');
				$filePath = $tmpDir . DIRECTORY_SEPARATOR . $basename;

				if ($format === 'excel') {
					$fh = fopen($filePath, 'w');
					if ($fh) {
						// write UTF-8 BOM
						fwrite($fh, "\xEF\xBB\xBF");
						fwrite($fh, "<table border=1>\n");
						$first = $result->fetch_assoc();
						if ($first) {
							fwrite($fh, "<tr>");
							foreach (array_keys($first) as $h) fwrite($fh, '<th>' . htmlspecialchars($h) . '</th>');
							fwrite($fh, "</tr>\n");
							fwrite($fh, "<tr>"); foreach ($first as $c) fwrite($fh, '<td>' . htmlspecialchars((string)$c) . '</td>'); fwrite($fh, "</tr>\n");
						}
						while ($row = $result->fetch_assoc()) {
							fwrite($fh, "<tr>"); foreach ($row as $c) fwrite($fh, '<td>' . htmlspecialchars((string)$c) . '</td>'); fwrite($fh, "</tr>\n");
						}
						fwrite($fh, "</table>");
						fclose($fh);
						$createdFiles[] = $filePath;
					}
				} else {
					$fh = fopen($filePath, 'w');
					if ($fh) {
						$first = $result->fetch_assoc();
						if ($first) {
							fputcsv($fh, array_keys($first));
							fputcsv($fh, array_values($first));
						}
						while ($row = $result->fetch_assoc()) {
							fputcsv($fh, array_values($row));
						}
						fclose($fh);
						$createdFiles[] = $filePath;
					}
				}
				$result->free();
			}

			// Create ZIP
			$zipPath = $tmpDir . DIRECTORY_SEPARATOR . 'db_export_' . date('Ymd_His') . '.zip';
			$zip = new ZipArchive();
			if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
				foreach ($createdFiles as $f) {
					$zip->addFile($f, basename($f));
				}
				$zip->close();

				// Stream ZIP
				header('Content-Type: application/zip');
				header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
				header('Content-Length: ' . filesize($zipPath));
				readfile($zipPath);
			} else {
				http_response_code(500);
				echo 'Failed to create zip file.';
			}

			// Cleanup
			foreach ($createdFiles as $f) { if (file_exists($f)) @unlink($f); }
			if (file_exists($zipPath)) @unlink($zipPath);
			@rmdir($tmpDir);
			exit();
		}

