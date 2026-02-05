<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
		header('Location: /auth/login.php');
		exit();
}

// Include database configuration
require_once __DIR__ . '/../../config/config.php';

// Get user role for sidebar
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
	<meta name="theme-color" content="#667eea" />
	<title>Client Profile</title>
	<link rel="stylesheet" href="../../assets/css/base.css" />
	<link rel="stylesheet" href="../../assets/css/admin-layout.css" />
	<link rel="stylesheet" href="../../assets/css/dashboard.css" />
	<style>
		.admin-container { text-align: left; }
		.welcome-section { justify-content: flex-start; }
		.welcome-logo { margin-left: 0; }
		.header-actions { justify-content: flex-start; }
		#addClientModal { display:none; position:fixed; inset:0; background:rgba(2,6,23,0.6); z-index:2000; width:100vw; height:100vh; left:0; top:0; }
		#addClientModal .modal-shell { position:fixed; inset:0; background:#f8fafc; display:flex; flex-direction:column; width:100vw; height:100vh; }
		#addClientModal .modal-header { display:flex; align-items:center; justify-content:space-between; padding:16px 24px; border-bottom:1px solid #e2e8f0; background:#fff; flex-shrink:0; }
		#addClientModal .modal-body { flex:1; overflow:auto; padding:24px; background:#f8fafc; width:100%; }
		#addClientModal .modal-actions { display:flex; align-items:center; gap:10px; }
		#addClientModal .modal-close { background:transparent; border:none; font-size:28px; line-height:1; cursor:pointer; color:#64748b; }
	</style>
</head>
<body class="admin-page">
	<div class="admin-container">
		<?php include __DIR__ . '/../../partials/portalheader.php'; ?>
		<div class="admin-layout">
			<?php include __DIR__ . '/../../partials/sidebar.php'; ?>
			<main class="content-area">
				<div class="main-content">
					<div style="margin-top:12px;margin-bottom:6px;display:flex;gap:10px;align-items:center;justify-content:center;width:100%;">
						<input type="text" id="clientSearch" placeholder="Search clients..." style="width:500px;max-width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;box-shadow:0 2px 6px rgba(2,6,23,0.04);" />
						<button type="button" id="openAddClientModal" style="padding:10px 16px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;transition:background 0.2s ease;white-space:nowrap;">+ Add</button>
					</div>
					<div style="margin-top:16px;border-radius:12px;overflow:hidden;box-shadow:0 6px 18px rgba(2,6,23,0.04);">
						<table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
							<thead>
								<tr style="background:#f8fafc;text-align:left;">
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Client Name</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Client Number</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Client Email</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Client Address</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">City</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">State</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">—</td>
									<td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">—</td>
									<td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">—</td>
									<td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">—</td>
									<td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">—</td>
									<td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">—</td>
								</tr>
							</tbody>
						</table>
					</div>
					<!-- Client Profile content will go here -->
				</div>
			</main>
		</div>
	</div>

	<div id="addClientModal" aria-hidden="true">
		<div class="modal-shell">
			<div class="modal-header">
				<h2 style="margin:0;font-size:18px;color:#0f172a;">Add Client</h2>
				<div class="modal-actions">
					<button type="button" id="cancelAddClientModal" style="padding:8px 14px;background:#fff;color:#374151;border:1px solid #e5e7eb;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
					<button type="button" style="padding:8px 14px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Save</button>
				</div>
			</div>
			<div class="modal-body">
				<div style="max-width:1200px;margin:0 auto;">
					<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
						<div>
							<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Name</label>
							<input type="text" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
						</div>
						<div>
							<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Number</label>
							<input type="text" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
						</div>
						<div>
							<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Email</label>
							<input type="email" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
						</div>
						<div>
							<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Address</label>
							<input type="text" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
						</div>
						<div>
							<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">City</label>
							<input type="text" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
						</div>
						<div>
							<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">State</label>
							<input type="text" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
						</div>
					</div>
					<div style="margin-top:16px;">
						<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Notes</label>
						<textarea style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;resize:vertical;min-height:100px;" placeholder="Add any additional notes..."></textarea>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script>
		(function(){
			var addModal = document.getElementById('addClientModal');
			var openAddBtn = document.getElementById('openAddClientModal');
			var closeAddBtn = document.getElementById('closeAddClientModal');
			var cancelAddBtn = document.getElementById('cancelAddClientModal');
			function openAddModal(){ if (addModal) { addModal.style.display = 'block'; addModal.setAttribute('aria-hidden', 'false'); } }
			function closeAddModal(){ if (addModal) { addModal.style.display = 'none'; addModal.setAttribute('aria-hidden', 'true'); } }
			if (openAddBtn) openAddBtn.addEventListener('click', openAddModal);
			if (closeAddBtn) closeAddBtn.addEventListener('click', closeAddModal);
			if (cancelAddBtn) cancelAddBtn.addEventListener('click', closeAddModal);
			if (addModal) {
				addModal.addEventListener('click', function(e){ if (e.target === addModal) closeAddModal(); });
			}

			var usersToggle = document.getElementById('usersToggle');
			var usersGroup = document.getElementById('usersGroup');
			if (usersToggle && usersGroup) {
				usersToggle.addEventListener('click', function(){
					usersGroup.classList.toggle('open');
				});
			}

			// Toggle dev options sub-nav
			var devToggle = document.getElementById('devToggle');
			var devGroup = document.getElementById('devGroup');
			if (devToggle && devGroup) {
				devToggle.addEventListener('click', function(){
					devGroup.classList.toggle('open');
				});
			}
			// Toggle maintenance sub-nav
			var maintenanceToggle = document.getElementById('maintenanceToggle');
			var maintenanceGroup = document.getElementById('maintenanceGroup');
			if (maintenanceToggle && maintenanceGroup) {
				maintenanceToggle.addEventListener('click', function(){
					maintenanceGroup.classList.toggle('open');
				});
			}
		})();
	</script>
	<script src="../../assets/js/mobile-menu.js"></script>
	<script src="../../assets/js/logout-confirm.js"></script>
</body>
</html>
