<?php
require_once __DIR__ . '/../../session_init.php';

// Require login
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
		header('Location: /auth/login.php');
		exit();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';

// basic role check (adjust capability name if different)
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();

if (!can_access($role, 'equipments')) {
		header('Location: /pages/dashboard/');
		exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Oil Status</title>
	<link rel="stylesheet" href="../../assets/css/base.css">
	<link rel="stylesheet" href="../../assets/css/admin-layout.css">
	<link rel="stylesheet" href="../../assets/css/dashboard.css">
	<style>
		.oil-status-panel { padding:18px; background:#fff; border:1px solid #e6eef6; border-radius:8px; box-shadow:0 6px 18px rgba(2,6,23,0.04); }
	</style>
</head>
<body class="admin-page">
	<div class="admin-container">
		<?php include __DIR__ . '/../../partials/portalheader.php'; ?>
		<div class="admin-layout">
			<?php include __DIR__ . '/../../partials/sidebar.php'; ?>
			<main class="content-area">
				<div class="main-content">
					<?php $previewParam = isset($_GET['preview_role']) ? '?preview_role=' . urlencode($_GET['preview_role']) : ''; ?>
					<div style="display:flex;align-items:center;gap:12px;margin:0 0 12px 0;">
						<button id="backBtn" type="button" class="btn btn-ghost" style="padding:6px 10px;border-radius:6px;border:1px solid #e5e7eb;background:#f8fafc;cursor:pointer;">← Back</button>
						<h2 style="margin:0;">Oil Status</h2>
					</div>
					<div class="oil-status-panel" id="oilStatusPanel">
						<p style="margin:0;color:#64748b">Loading oil status...</p>
					</div>
				</div>
			</main>
		</div>
	</div>

	<script>
		// Placeholder: load oil status via API when available
		(function(){
			var panel = document.getElementById('oilStatusPanel');
			// Example fetch (adjust endpoint if you have one)
			fetch('../../api/get_oil_status.php', { credentials:'same-origin' })
				.then(r=>r.json())
				.then(function(json){
					if (!json || !json.success) { panel.innerHTML = '<div style="color:#ef4444">Unable to load oil status</div>'; return; }
					var html = '<ul style="margin:0;padding-left:18px">';
					(json.items||[]).forEach(function(it){ html += '<li>' + (it.name||'Unnamed') + ': ' + (it.status||'n/a') + '</li>'; });
					html += '</ul>';
					panel.innerHTML = html;
				}).catch(function(){ panel.innerHTML = '<div style="color:#ef4444">Error loading oil status</div>'; });
			})();

			// Back button behavior: prefer history.back() when referrer is same-origin, else fallback to index.php
			(function(){
				var btn = document.getElementById('backBtn');
				if (!btn) return;
				btn.addEventListener('click', function(){
					try {
						var ref = document.referrer || '';
						if (ref && ref.indexOf(location.origin) === 0) {
							history.back();
							return;
						}
					} catch (e) {
						// ignore
					}
					window.location.href = 'index.php' + '<?php echo isset($previewParam) ? $previewParam : ""; ?>';
				});
			})();
	</script>
</body>
</html>

