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
    <link rel="stylesheet" href="../../assets/css/base.css">
    <link rel="stylesheet" href="../../assets/css/admin-layout.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
	<style>
/* equipments Page Styles */
/* This file contains page-specific styles for equipments */
/* Base styles, admin layout, and dashboard styles are inherited from parent CSS files */
/* Add any equipments specific overrides or additional styles here */
.equipment-page { width: 100%; text-align: left; padding-top: 18px; }
.equipment-topbar { position: sticky; top: 0; z-index: 50; display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-start; gap: 10px; padding: 10px 0 6px 0; background: #fff; }
.equipment-ribbon { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 0; padding: 6px 8px; border-radius: 999px; border: 1px solid rgba(15,23,42,0.12); background: linear-gradient(180deg,#fff 0%,#f8fafc 100%); box-shadow: 0 10px 24px rgba(2,6,23,0.06),inset 0 1px 0 rgba(255,255,255,0.85); }
.equipment-ribbon__item { display: inline-flex; align-items: center; padding: 6px 10px; color: #0f172a; font-weight: 600; font-size: 12px; line-height: 1; letter-spacing: 0.1px; white-space: nowrap; cursor: default; }
.equipment-ribbon__item:hover { background: rgba(15,23,42,0.03); }
.equipment-ribbon__label { display: inline-flex; align-items: center; padding: 6px 10px; color: #0f172a; font-weight: 700; font-size: 11px; line-height: 1; white-space: nowrap; cursor: default; opacity: 0.7; text-transform: uppercase; letter-spacing: 0.08em; }
.equipment-ribbon__label + .equipment-ribbon__item { border-left: 1px solid rgba(15,23,42,0.14); }
.equipment-ribbon__item + .equipment-ribbon__item { border-left: 1px solid rgba(15,23,42,0.14); }
.equipment-ribbon__item--danger { color: #b91c1c; }
.equipment-ribbon__item--danger::before { content: ""; width: 6px; height: 6px; border-radius: 999px; background: rgba(185,28,28,0.95); box-shadow: 0 0 0 3px rgba(185,28,28,0.12); margin-right: 8px; }
.equipment-btn { appearance: none; border: 1px solid rgba(15,23,42,0.12); background: rgba(255,255,255,0.95); color: #0f172a; font-weight: 800; font-size: 13px; padding: 9px 12px; border-radius: 10px; cursor: pointer; }
.equipment-btn:hover { background: rgba(15,23,42,0.03); }
.equipment-btn--green { border-color: rgba(22,163,74,0.35); background: rgba(22,163,74,0.92); color: #fff; }
.equipment-btn--green:hover { background: rgba(22,163,74,1); }
.equipment-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.equipment-btn--secondary { background: rgba(15,23,42,0.03); }
.equipment-icon-btn { width: 32px; height: 32px; border-radius: 10px; border: 1px solid rgba(15,23,42,0.1); background: rgba(255,255,255,0.9); cursor: pointer; font-size: 20px; line-height: 1; font-weight: 900; color: #0f172a; }
.equipment-icon-btn:hover { background: rgba(15,23,42,0.03); }
.equipment-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin: 8px 0 16px 0; }
.equipment-title { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: 0.2px; color: #111827; }
.equipment-subtitle { margin: 6px 0 0 0; font-size: 13px; font-weight: 600; color: #475569; }
.equipment-count { display: inline-flex; align-items: center; justify-content: center; padding: 6px 10px; border-radius: 999px; background: rgba(49,115,212,0.12); color: #1f4b8f; font-weight: 800; font-size: 12px; }
.equipment-alert { width: 100%; border-radius: 12px; padding: 12px 14px; margin: 0 0 14px 0; border: 1px solid rgba(185,28,28,0.25); background: rgba(255,229,229,0.65); color: #7f1d1d; }
.equipment-alert__hint { margin-top: 8px; font-size: 12px; color: #7f1d1d; opacity: 0.95; }
.equipment-alert code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 12px; }
.table-area { width: 100%; margin: 0; padding: 0; }
.table-wrap { width: 100%; padding: 8px 0; }
.table-container { width: 100%; overflow: auto; max-width: 100%; -webkit-overflow-scrolling: touch; background: #fff; border-radius: 10px; border: 1px solid rgba(15,23,42,0.06); }
.project-table.equipment-table { width: 100%; border-collapse: separate; border-spacing: 0; background: #fff; table-layout: fixed; }
.project-table.equipment-table thead th { position: sticky; top: 0; z-index: 2; text-align: left; padding: 10px 12px; font-weight: 700; color: #0f172a; font-size: 13px; background: linear-gradient(180deg,#f8fafc 0%,#f1f5f9 100%); border-bottom: 1px solid rgba(15,23,42,0.06); }
.equip-th { display: inline-flex; align-items: center; gap: 8px; max-width: 100%; }
.equip-th__label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.equip-sort-btn { appearance: none; border: 1px solid rgba(15,23,42,0.1); background: rgba(255,255,255,0.65); color: #0f172a; width: 22px; height: 22px; border-radius: 7px; cursor: pointer; line-height: 1; font-weight: 900; padding: 0; flex: 0 0 auto; }
.equip-sort-btn:hover { background: rgba(15,23,42,0.05); }
.equip-sort-menu { display: none; position: absolute; min-width: 190px; background: #fff; border: 1px solid rgba(15,23,42,0.1); box-shadow: 0 12px 30px rgba(2,6,23,0.14); border-radius: 10px; z-index: 2500; overflow: hidden; }
.equip-sort-option { width: 100%; display: block; text-align: left; padding: 10px 12px; border: none; background: transparent; cursor: pointer; font-weight: 800; font-size: 13px; color: #0f172a; }
.equip-sort-option:hover { background: rgba(15,23,42,0.03); }
.project-table.equipment-table tbody td { padding: 10px 12px; border-bottom: 1px solid rgba(15,23,42,0.04); vertical-align: middle; font-size: 14px; color: #0f172a; background: transparent; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.project-table.equipment-table thead th { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.equipment-table tbody tr { transition: transform 120ms ease, background-color 120ms ease; }
.project-table.equipment-table tbody tr:nth-child(even) td { background: #fbfdff; }
.project-table.equipment-table tbody tr:hover td { background: rgba(15,23,42,0.03); }
.equipment-empty { padding: 24px 14px; text-align: center; color: #475569; font-weight: 700; }
.project-table.equipment-table th, .project-table.equipment-table td { width: 11.1111%; }
.project-table.equipment-table th:nth-child(4), .project-table.equipment-table td:nth-child(4), .project-table.equipment-table th:nth-child(7), .project-table.equipment-table td:nth-child(7), .project-table.equipment-table th:nth-child(8), .project-table.equipment-table td:nth-child(8), .project-table.equipment-table th:nth-child(9), .project-table.equipment-table td:nth-child(9), .project-table.equipment-table th:nth-child(10), .project-table.equipment-table td:nth-child(10) { text-align: center; }
.project-table.equipment-table th:nth-child(6), .project-table.equipment-table td:nth-child(6) { text-align: right; }
.equipment-id, .equipment-number, .equipment-pill { padding: 0; }
.equipment-modal { display: none; position: fixed; inset: 0; background: rgba(2,6,23,0.6); align-items: center; justify-content: center; z-index: 2000; padding: 18px; }
.equipment-modal.is-open { display: flex; }
.equipment-modal__dialog { width: 100%; max-width: 760px; background: #fff; border-radius: 12px; border: 1px solid rgba(15,23,42,0.08); box-shadow: 0 12px 40px rgba(2,6,23,0.22); overflow: hidden; }
.equipment-modal__header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 14px; background: linear-gradient(180deg,#f8fafc 0%,#f1f5f9 100%); border-bottom: 1px solid rgba(15,23,42,0.06); }
.equipment-modal__title { margin: 0; font-size: 16px; font-weight: 900; color: #0f172a; }
.equipment-form { padding: 14px; }
.equipment-form__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.equipment-form__field label { display: block; font-size: 12px; font-weight: 800; color: #334155; margin-bottom: 6px; }
.equipment-form__field input { width: 100%; padding: 9px 10px; border: 1px solid rgba(15,23,42,0.1); border-radius: 10px; font-size: 14px; font-weight: 650; color: #0f172a; background: #fff; }
.equipment-form__field input:focus { outline: none; border-color: rgba(99,102,241,0.28); box-shadow: 0 6px 18px rgba(99,102,241,0.08); }
.equipment-form__actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px; }
.equipment-form__error { margin-top: 10px; padding: 10px 12px; border-radius: 10px; background: rgba(255,229,229,0.65); border: 1px solid rgba(185,28,28,0.25); color: #7f1d1d; font-weight: 800; font-size: 12px; }
@media (max-width: 900px) { .equipment-form__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 600px) { .equipment-form__grid { grid-template-columns: 1fr; } }
.equipment-id { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; background: rgba(15,23,42,0.06); color: #0f172a; font-weight: 900; font-size: 12px; }
.equipment-number { display: inline; padding: 0; border-radius: 0; background: transparent; border: 0; color: inherit; font-weight: inherit; font-size: inherit; letter-spacing: normal; }
.equipment-hours { font-variant-numeric: tabular-nums; font-feature-settings: "tnum"; }
.equipment-pill { display: inline; padding: 0; border-radius: 0; font-size: inherit; font-weight: inherit; line-height: inherit; border: 0; background: transparent; white-space: nowrap; }
.equipment-pill--neutral { color: #334155; }
.equipment-pill--good { color: #166534; }
.equipment-pill--warn { color: #92400e; }
.equipment-pill--bad { color: #7f1d1d; }
@media (max-width: 768px) { .equipment-header { align-items: flex-start; flex-direction: column; } }
	</style>
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

