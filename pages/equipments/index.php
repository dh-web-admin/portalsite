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

// Fetch equipment rows
$equipments = [];
$equipmentsError = null;

$sql = "SELECT equipment_id, equipment_number, type, operating_condition, location, current_hours, oil_status, air_filters, warranty, tires\n        FROM equipments\n        ORDER BY equipment_id DESC";

try {
	$res = $conn->query($sql);
	if ($res === false) {
		$equipmentsError = $conn->error;
	} else {
		while ($row = $res->fetch_assoc()) {
			$equipments[] = $row;
		}
		$res->free();
	}
} catch (Throwable $e) {
	$equipmentsError = $e->getMessage();
}

function eq_normalize_status($value) {
	$val = strtolower(trim((string)$value));
	if ($val === '') return 'neutral';
	if (strpos($val, 'good') !== false || strpos($val, 'ok') !== false || strpos($val, 'pass') !== false || $val === 'yes') return 'good';
	if (strpos($val, 'warn') !== false || strpos($val, 'soon') !== false || strpos($val, 'due') !== false || strpos($val, 'needs') !== false) return 'warn';
	if (strpos($val, 'bad') !== false || strpos($val, 'fail') !== false || strpos($val, 'no') !== false || strpos($val, 'down') !== false || strpos($val, 'out') !== false) return 'bad';
	return 'neutral';
}

function eq_format_warranty($dateValue) {
	if ($dateValue === null || $dateValue === '') {
		return ['label' => '—', 'state' => 'neutral'];
	}
	$ts = strtotime((string)$dateValue);
	if ($ts === false) {
		return ['label' => (string)$dateValue, 'state' => 'neutral'];
	}
	$today = strtotime(date('Y-m-d'));
	if ($ts >= $today) {
		return ['label' => date('Y-m-d', $ts), 'state' => 'good'];
	}
	return ['label' => date('Y-m-d', $ts), 'state' => 'bad'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Equipments</title>
	<link rel="stylesheet" href="../../assets/css/base.css" />
	<link rel="stylesheet" href="../../assets/css/admin-layout.css" />
	<link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="admin-page">
	<div class="admin-container">
		<?php include __DIR__ . '/../../partials/portalheader.php'; ?>
		<div class="admin-layout">
			<?php include __DIR__ . '/../../partials/sidebar.php'; ?>
			<main class="content-area">
				<div class="main-content">
					<section class="equipment-page" aria-label="Equipment management">
						<div class="equipment-topbar" role="region" aria-label="Equipment actions">
							<button id="newEquipmentBtn" class="equipment-btn equipment-btn--green" type="button">Add Equipment</button>
							<div class="equipment-ribbon" aria-label="Cheat sheets">
								<span class="equipment-ribbon__item">All Eng Cheat Sheet</span>
								<span class="equipment-ribbon__item">Filter Cheat Sheet</span>
								<span class="equipment-ribbon__item">Tire Cheat Sheet</span>
								<span class="equipment-ribbon__item">Dimension Cheat Sheet</span>
							</div>
							<div class="equipment-ribbon" aria-label="Reports">
								<span class="equipment-ribbon__item equipment-ribbon__item--danger">Engine Reports</span>
								<span class="equipment-ribbon__item">Oil Change Reports</span>
							</div>
						</div>

						<?php if ($equipmentsError): ?>
							<div class="equipment-alert equipment-alert--error" role="alert">
								<strong>Database error:</strong>
								<span><?php echo htmlspecialchars($equipmentsError); ?></span>
								<div class="equipment-alert__hint">Run the migration: <code>php migrations/create_equipments_table.php</code></div>
							</div>
						<?php endif; ?>

						<div class="table-area">
							<div class="table-wrap">
								<div class="table-container" role="region" aria-label="Equipment table">
									<table class="project-table equipment-table" role="table" aria-label="Equipment list">
									<thead>
										<tr>
											<th scope="col">
												<span class="equip-th">
													<span class="equip-th__label">Equipment #</span>
													<button class="equip-sort-btn" type="button" aria-label="Sort equipment number" data-sort="equipment_number">▾</button>
												</span>
											</th>
											<th scope="col">Type</th>
											<th scope="col">
												<span class="equip-th">
													<span class="equip-th__label">Operating Condition</span>
													<button class="equip-sort-btn" type="button" aria-label="Sort operating condition" data-sort="operating_condition">▾</button>
												</span>
											</th>
											<th scope="col">Location</th>
											<th scope="col">
												<span class="equip-th">
													<span class="equip-th__label">Current Hours</span>
													<button class="equip-sort-btn" type="button" aria-label="Sort current hours" data-sort="current_hours">▾</button>
												</span>
											</th>
											<th scope="col">
												<span class="equip-th">
													<span class="equip-th__label">Oil Status</span>
													<button class="equip-sort-btn" type="button" aria-label="Sort oil status" data-sort="oil_status">▾</button>
												</span>
											</th>
											<th scope="col">Air Filters</th>
											<th scope="col">Warranty</th>
											<th scope="col">Tires</th>
										</tr>
									</thead>
									<tbody>
										<?php if (count($equipments) === 0): ?>
											<tr>
												<td class="equipment-empty" colspan="9">No equipment yet. Once rows exist in the database, they’ll show up here.</td>
											</tr>
										<?php else: ?>
											<?php $eqIndex = 0; ?>
											<?php foreach ($equipments as $eq): ?>
												<?php
													$opState = eq_normalize_status($eq['operating_condition'] ?? '');
													$oilState = eq_normalize_status($eq['oil_status'] ?? '');
													$airState = eq_normalize_status($eq['air_filters'] ?? '');
													$tiresState = eq_normalize_status($eq['tires'] ?? '');
													$warranty = eq_format_warranty($eq['warranty'] ?? null);
													$eqNumSort = strtolower(trim((string)($eq['equipment_number'] ?? '')));
													$hoursSort = is_numeric($eq['current_hours'] ?? null) ? (float)$eq['current_hours'] : 0.0;
												?>
												<tr
													data-equipment-id="<?php echo (int)$eq['equipment_id']; ?>"
													data-original-index="<?php echo (int)$eqIndex; ?>"
													data-sort-equipment-number="<?php echo htmlspecialchars($eqNumSort); ?>"
													data-sort-operating-condition="<?php echo htmlspecialchars($opState); ?>"
													data-sort-oil-status="<?php echo htmlspecialchars($oilState); ?>"
													data-sort-current-hours="<?php echo htmlspecialchars((string)$hoursSort); ?>"
												>
													<td><a class="equipment-number" href="equipment.php?id=<?php echo (int)($eq['equipment_id'] ?? 0); ?>"><?php echo htmlspecialchars((string)($eq['equipment_number'] ?? '')); ?></a></td>
													<td><?php echo htmlspecialchars((string)($eq['type'] ?? '')); ?></td>
													<td>
														<?php $val = trim((string)($eq['operating_condition'] ?? '')); ?>
														<?php if ($val === ''): ?>
															<span class="equipment-pill equipment-pill--neutral">—</span>
														<?php else: ?>
															<span class="equipment-pill equipment-pill--<?php echo htmlspecialchars($opState); ?>"><?php echo htmlspecialchars($val); ?></span>
														<?php endif; ?>
													</td>
													<td><?php echo htmlspecialchars((string)($eq['location'] ?? '')); ?></td>
													<td><span class="equipment-hours"><?php echo htmlspecialchars((string)($eq['current_hours'] ?? '0')); ?></span></td>
													<td>
														<?php $val = trim((string)($eq['oil_status'] ?? '')); ?>
														<?php if ($val === ''): ?>
															<span class="equipment-pill equipment-pill--neutral">—</span>
														<?php else: ?>
															<span class="equipment-pill equipment-pill--<?php echo htmlspecialchars($oilState); ?>"><?php echo htmlspecialchars($val); ?></span>
														<?php endif; ?>
													</td>
													<td>
														<?php $val = trim((string)($eq['air_filters'] ?? '')); ?>
														<?php if ($val === ''): ?>
															<span class="equipment-pill equipment-pill--neutral">—</span>
														<?php else: ?>
															<span class="equipment-pill equipment-pill--<?php echo htmlspecialchars($airState); ?>"><?php echo htmlspecialchars($val); ?></span>
														<?php endif; ?>
													</td>
													<td>
														<span class="equipment-pill equipment-pill--<?php echo htmlspecialchars($warranty['state']); ?>"><?php echo htmlspecialchars($warranty['label']); ?></span>
													</td>
													<td>
														<?php $val = trim((string)($eq['tires'] ?? '')); ?>
														<?php if ($val === ''): ?>
															<span class="equipment-pill equipment-pill--neutral">—</span>
														<?php else: ?>
															<span class="equipment-pill equipment-pill--<?php echo htmlspecialchars($tiresState); ?>"><?php echo htmlspecialchars($val); ?></span>
														<?php endif; ?>
													</td>
												</tr>
												<?php $eqIndex++; ?>
											<?php endforeach; ?>
										<?php endif; ?>
									</tbody>
								</table>
								<div id="equipSortMenu" class="equip-sort-menu" aria-hidden="true"></div>
								</div>
							</div>
						</div>
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
			// Equipment table sorting dropdowns
			(function(){
				var table = document.querySelector('.equipment-table');
				var sortMenu = document.getElementById('equipSortMenu');
				if (!table || !sortMenu) return;
				var tbody = table.querySelector('tbody');
				if (!tbody) return;
				var buttons = Array.prototype.slice.call(document.querySelectorAll('.equip-sort-btn'));
				var menuOpen = false;
				var currentSortKey = null;

				function closeMenu(){
					sortMenu.style.display = 'none';
					sortMenu.setAttribute('aria-hidden','true');
					sortMenu.innerHTML = '';
					menuOpen = false;
					currentSortKey = null;
				}

				function getRows(){
					return Array.prototype.slice.call(tbody.querySelectorAll('tr'))
						.filter(function(tr){ return tr.getAttribute('data-equipment-id'); });
				}

				function stableFallback(a, b){
					var ai = parseInt(a.getAttribute('data-original-index') || '0', 10);
					var bi = parseInt(b.getAttribute('data-original-index') || '0', 10);
					return ai - bi;
				}

				function replaceRows(rows){
					var emptyRow = tbody.querySelector('tr:not([data-equipment-id])');
					rows.forEach(function(tr){ tbody.appendChild(tr); });
					if (emptyRow) tbody.appendChild(emptyRow);
				}

				function applyTextSort(direction){
					var rows = getRows();
					rows.sort(function(a, b){
						var av = (a.getAttribute('data-sort-equipment-number') || '').toLowerCase();
						var bv = (b.getAttribute('data-sort-equipment-number') || '').toLowerCase();
						if (av < bv) return direction === 'asc' ? -1 : 1;
						if (av > bv) return direction === 'asc' ? 1 : -1;
						return stableFallback(a, b);
					});
					replaceRows(rows);
				}

				function applyHoursSort(direction){
					var rows = getRows();
					rows.sort(function(a, b){
						var av = parseFloat(a.getAttribute('data-sort-current-hours') || '0');
						var bv = parseFloat(b.getAttribute('data-sort-current-hours') || '0');
						if (av < bv) return direction === 'asc' ? -1 : 1;
						if (av > bv) return direction === 'asc' ? 1 : -1;
						return stableFallback(a, b);
					});
					replaceRows(rows);
				}

				function applyStatusSort(key, preferred){
					var order;
					if (preferred === 'good') order = ['good', 'warn', 'bad', 'neutral'];
					else if (preferred === 'warn') order = ['warn', 'good', 'bad', 'neutral'];
					else order = ['bad', 'warn', 'good', 'neutral'];

					var rows = getRows();
					rows.sort(function(a, b){
						var attr = key === 'operating_condition' ? 'data-sort-operating-condition' : 'data-sort-oil-status';
						var av = a.getAttribute(attr) || 'neutral';
						var bv = b.getAttribute(attr) || 'neutral';
						var ai = order.indexOf(av); if (ai === -1) ai = order.length;
						var bi = order.indexOf(bv); if (bi === -1) bi = order.length;
						if (ai < bi) return -1;
						if (ai > bi) return 1;
						return stableFallback(a, b);
					});
					replaceRows(rows);
				}

				function openMenuFor(btn, key){
					currentSortKey = key;
					sortMenu.innerHTML = '';
					sortMenu.setAttribute('aria-hidden','false');
					sortMenu.style.display = 'block';
					menuOpen = true;

					var rect = btn.getBoundingClientRect();
					var menuWidth = 190;
					var left = rect.left + window.pageXOffset;
					var top = rect.bottom + window.pageYOffset + 6;
					var maxLeft = (window.pageXOffset + window.innerWidth) - menuWidth - 10;
					if (left > maxLeft) left = Math.max(10, maxLeft);
					sortMenu.style.left = left + 'px';
					sortMenu.style.top = top + 'px';

					function addOption(label, action){
						var opt = document.createElement('button');
						opt.type = 'button';
						opt.className = 'equip-sort-option';
						opt.textContent = label;
						opt.addEventListener('click', function(e){
							e.preventDefault();
							action();
							closeMenu();
						});
						sortMenu.appendChild(opt);
					}

					if (key === 'operating_condition' || key === 'oil_status') {
						addOption('Green', function(){ applyStatusSort(key, 'good'); });
						addOption('Yellow', function(){ applyStatusSort(key, 'warn'); });
						addOption('Red', function(){ applyStatusSort(key, 'bad'); });
					} else if (key === 'current_hours') {
						addOption('Highest', function(){ applyHoursSort('desc'); });
						addOption('Lowest', function(){ applyHoursSort('asc'); });
					} else if (key === 'equipment_number') {
						addOption('A → Z', function(){ applyTextSort('asc'); });
						addOption('Z → A', function(){ applyTextSort('desc'); });
					}
				}

				buttons.forEach(function(btn){
					btn.addEventListener('click', function(e){
						e.preventDefault();
						e.stopPropagation();
						var key = btn.getAttribute('data-sort');
						if (menuOpen && currentSortKey === key) {
							closeMenu();
							return;
						}
						openMenuFor(btn, key);
					});
				});

				document.addEventListener('click', function(){ if (menuOpen) closeMenu(); });
				document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && menuOpen) closeMenu(); });
				window.addEventListener('resize', function(){ if (menuOpen) closeMenu(); });
				window.addEventListener('scroll', function(){ if (menuOpen) closeMenu(); }, true);
			})();

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

			var usersToggle = document.getElementById('usersToggle');
			var usersGroup = document.getElementById('usersGroup');
			if (usersToggle && usersGroup) {
				usersToggle.addEventListener('click', function(){
					usersGroup.classList.toggle('open');
				});
			}
		})();
	</script>
</body>
</html>

