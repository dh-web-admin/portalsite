<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';
require_once __DIR__ . '/../partials/url.php';

// Admin-only access
$email = $_SESSION['email'] ?? null;
if (!$email) { header('Location: ../auth/login.php'); exit(); }
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user['role'] ?? 'laborer';
$stmt->close();
if ($role !== 'admin') { header('Location: ../pages/dashboard/'); exit(); }

$pages = portal_all_pages();
$results = [];
foreach ($pages as $p) {
    $file = __DIR__ . '/../pages/' . $p . '.php';
    $status = [
        'page' => $p,
        'exists' => file_exists($file),
        'corrupted' => false,
        'notes' => [],
    ];
    if ($status['exists']) {
        $content = file_get_contents($file);
        if (strpos($content, '<?php<?php') !== false) {
            $status['corrupted'] = true;
            $status['notes'][] = 'Duplicate PHP open tags detected';
        }
        if (substr_count($content, "require_once __DIR__ . '/../session_init.php';") > 1) {
            $status['corrupted'] = true;
            $status['notes'][] = 'Repeated session_init include';
        }
        if (substr_count($content, '<!DOCTYPE html>') > 1) {
            $status['corrupted'] = true;
            $status['notes'][] = 'Repeated HTML DOCTYPE';
        }
    } else {
        $status['notes'][] = 'File missing';
    }
    $results[] = $status;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Pages Health Check</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:20px;background:#f7f7fb;color:#222}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px}
.ok{background:#def7ec;color:#03543f}
.err{background:#fde8e8;color:#9b1c1c}
.note{color:#555;font-size:12px}
code{background:#f3f4f6;padding:2px 4px;border-radius:4px}
</style>
</head>
<body>
<h1>Pages Health Check</h1>
<p>Admin-only. Scans for common file corruption patterns in <code>pages/</code>.</p>
<p>
    Quick links:
    <a href="<?php echo htmlspecialchars(base_url('/debug/health.php')); ?>">App Health</a> ·
    <a href="<?php echo htmlspecialchars(base_url('/debug/debug_session.php')); ?>">Session Debug</a> ·
    <a href="<?php echo htmlspecialchars(base_url('/debug/debug_page_load.php')); ?>">Page Load Debug</a>
    · <a href="<?php echo htmlspecialchars(base_url('/pages/dashboard/')); ?>">Back to Dashboard</a>
    · <a href="<?php echo htmlspecialchars(base_url('/auth/logout.php')); ?>">Logout</a>
 </p>
<table class="table">
<thead>
<tr><th>Page</th><th>Exists</th><th>Status</th><th>Notes</th></tr>
</thead>
<tbody>
<?php foreach ($results as $r): ?>
<tr>
  <td><?php echo htmlspecialchars($r['page']); ?></td>
  <td><?php echo $r['exists'] ? '<span class="badge ok">Yes</span>' : '<span class="badge err">No</span>'; ?></td>
  <td><?php echo !$r['exists'] ? '<span class="badge err">Missing</span>' : ($r['corrupted'] ? '<span class="badge err">Corrupted</span>' : '<span class="badge ok">OK</span>'); ?></td>
  <td class="note"><?php echo htmlspecialchars(implode('; ', $r['notes'])); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
