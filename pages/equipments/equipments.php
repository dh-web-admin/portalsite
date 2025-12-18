<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
	header('Location: /auth/login.php');
	exit();
}

// Include database configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';

// Get user role for sidebar
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();

// Enforce access control for this page
if (!can_access($role, 'equipments')) {
	header('Location: /pages/dashboard/');
	exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
	<meta name="theme-color" content="#667eea" />
	<title>Equipments</title>
	<link rel="stylesheet" href="../../assets/css/base.css" />
	<link rel="stylesheet" href="../../assets/css/admin-layout.css" />
	<link rel="stylesheet" href="../../assets/css/dashboard.css" />
	<link rel="stylesheet" href="./style.css" />
</head>
<body class="admin-page">
	<div class="admin-container">
		<?php include __DIR__ . '/../../partials/portalheader.php'; ?>
		<div class="admin-layout">
			<?php include __DIR__ . '/../../partials/sidebar.php'; ?>
			<main class="content-area">
				<div class="main-content">
					<section class="equipment-page" aria-label="Equipment management">
						<!-- Equipment top bar removed -->
					</section>
				</div>
			</main>
		</div>
	</div>

	<!-- Add Equipment Modal -->
	<div id="newEquipmentModal" class="equipment-modal" aria-hidden="true">
		<div class="equipment-modal__dialog" role="dialog" aria-modal="true" aria-label="Add equipment">
			<div class="equipment-modal__header">
				<h3 class="equipment-modal__title">Add Equipment</h3>
				<button id="closeNewEquipmentModal" class="equipment-icon-btn" type="button" aria-label="Close">×</button>
			</div>
			<form id="newEquipmentForm" class="equipment-form">
				<div class="equipment-form__grid">
					<div class="equipment-form__field">
						<label for="eq_equipment_number">Equipment #</label>
						<input id="eq_equipment_number" name="equipment_number" type="text" required />
					</div>
					<div class="equipment-form__field">
						<label for="eq_type">Type</label>
						<input id="eq_type" name="type" type="text" required />
					</div>
					<div class="equipment-form__field">
						<label for="eq_operating_condition">Operating Condition</label>
						<input id="eq_operating_condition" name="operating_condition" type="text" />
					</div>
					<div class="equipment-form__field">
						<label for="eq_location">Location</label>
						<input id="eq_location" name="location" type="text" />
					</div>
					<div class="equipment-form__field">
						<label for="eq_current_hours">Current Hours</label>
						<input id="eq_current_hours" name="current_hours" type="number" step="0.1" min="0" value="0" />
					</div>
					<div class="equipment-form__field">
						<label for="eq_oil_status">Oil Status</label>
						<input id="eq_oil_status" name="oil_status" type="text" />
					</div>
					<div class="equipment-form__field">
						<label for="eq_air_filters">Air Filters</label>
						<input id="eq_air_filters" name="air_filters" type="text" />
					</div>
					<div class="equipment-form__field">
						<label for="eq_warranty">Warranty</label>
						<input id="eq_warranty" name="warranty" type="date" />
					</div>
					<div class="equipment-form__field">
						<label for="eq_tires">Tires</label>
						<input id="eq_tires" name="tires" type="text" />
					</div>
				</div>
				<div class="equipment-form__actions">
					<button id="cancelNewEquipment" class="equipment-btn equipment-btn--secondary" type="button">Cancel</button>
					<button id="saveNewEquipment" class="equipment-btn" type="submit">Save</button>
				</div>
				<div id="newEquipmentError" class="equipment-form__error" role="alert" style="display:none;"></div>
			</form>
		</div>
	</div>

	<script>
		(function(){
			var newBtn = document.getElementById('newEquipmentBtn');
			var modal = document.getElementById('newEquipmentModal');
			var closeBtn = document.getElementById('closeNewEquipmentModal');
			var cancelBtn = document.getElementById('cancelNewEquipment');
			var form = document.getElementById('newEquipmentForm');
			var errBox = document.getElementById('newEquipmentError');
			var saveBtn = document.getElementById('saveNewEquipment');

			function openModal(){
				if (!modal) return;
				modal.classList.add('is-open');
				modal.setAttribute('aria-hidden','false');
				if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }
				var first = document.getElementById('eq_equipment_number');
				if (first) first.focus();
			}

			function closeModal(){
				if (!modal) return;
				modal.classList.remove('is-open');
				modal.setAttribute('aria-hidden','true');
				if (form) form.reset();
				if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }
			}

			if (newBtn) newBtn.addEventListener('click', function(){ openModal(); });
			if (closeBtn) closeBtn.addEventListener('click', function(){ closeModal(); });
			if (cancelBtn) cancelBtn.addEventListener('click', function(){ closeModal(); });
			if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
			document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) closeModal(); });

			if (form) form.addEventListener('submit', function(e){
				e.preventDefault();
				if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving...'; }
				if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }

				var fd = new FormData(form);
				fetch('../../api/add_equipment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function(r){ return r.json().then(function(j){ return { ok: r.ok, json: j }; }); })
					.then(function(res){
						if (!res.ok || !res.json || !res.json.success) {
							var msg = (res.json && res.json.message) ? res.json.message : 'Failed to save equipment';
							if (errBox) { errBox.textContent = msg; errBox.style.display = 'block'; }
							return;
						}
						// Reload to show new row
						window.location.reload();
					})
					.catch(function(){
						if (errBox) { errBox.textContent = 'Network error while saving'; errBox.style.display = 'block'; }
					})
					.finally(function(){
						if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
					});
			});

			// Sidebar toggles (match template behavior)
			var usersToggle = document.getElementById('usersToggle');
			var usersGroup = document.getElementById('usersGroup');
			if (usersToggle && usersGroup) {
				usersToggle.addEventListener('click', function(){
					usersGroup.classList.toggle('open');
				});
			}

			var devToggle = document.getElementById('devToggle');
			var devGroup = document.getElementById('devGroup');
			if (devToggle && devGroup) {
				devToggle.addEventListener('click', function(){
					devGroup.classList.toggle('open');
				});
			}

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
