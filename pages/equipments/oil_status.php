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

// Hide admin-only UI elements for non-admin users
if (!is_admin()) {
	echo <<<'HTML'
<style>.admin-only { display: none !important; }</style>
HTML;
}

// Fetch equipments (id, display number, current_hours)
$equipments = [];
$equipErr = null;
try {
	$r = $conn->query("SELECT equipment_id, COALESCE(dhss_equipment_number, '') AS number, COALESCE(current_hours, 0) AS current_hours, COALESCE(type, '') AS type FROM equipments ORDER BY equipment_id ASC");
	if ($r) {
		while ($row = $r->fetch_assoc()) { $equipments[] = $row; }
		$r->free();
	} else {
		$equipErr = $conn->error;
	}
} catch (Throwable $e) {
	$equipErr = $e->getMessage();
}

// Fetch parts grouped by equipment_id
$partsByEquip = [];
try {
	$qr = $conn->query("SELECT * FROM equipment_oil_parts ORDER BY id ASC");
	if ($qr) {
		while ($p = $qr->fetch_assoc()) {
			$eid = (int)($p['equipment_id'] ?? 0);
			if (!isset($partsByEquip[$eid])) $partsByEquip[$eid] = [];
			$partsByEquip[$eid][] = $p;
		}
		$qr->free();
	}
} catch (Throwable $e) {
	// ignore
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
		.oil-status-panel { padding:12px; background:#fff; border:1px solid #e6eef6; border-radius:8px; box-shadow:0 6px 18px rgba(2,6,23,0.04); margin-top:22px; box-sizing:border-box; }
		.panel-wrapper { max-width:1200px; margin-left:auto; margin-right:auto; position:relative; }
		/* allow a wider table for long content */
		.panel-wrapper.wide { max-width:1600px; }
		.oil-page-heading { text-align:center; margin:8px 0 8px; }
		/* Back button (match equipment.php styling) */
		.equipment-back-btn-wrapper--top-left { margin-top: 18px; margin-bottom: 18px; }
		.equipment-back-btn {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding: 10px 18px;
			background: #2563eb;
			color: #fff;
			text-decoration: none;
			border-radius: 8px;
			font-weight: 600;
			font-size: 14px;
			transition: background 0.2s ease, transform 0.1s ease;
			border: none;
			cursor: pointer;
		}
		.equipment-back-btn:hover { background: #1d4ed8; cursor: pointer; }
		.equipment-back-btn:active { transform: scale(0.98); }
		.oil-page-heading h1 { margin:0; font-size:26px; letter-spacing:3px; font-weight:800; color:#0f172a; }
		.oil-page-heading .subtitle { margin-top:6px; color:#6b7280; font-size:14px; }
		#partsContainer { margin-top:10px; padding:0 6px; }
		#partsTable { table-layout:fixed; width:100%; }
		#partsTable thead th { width: calc(100% / 11); padding:10px 12px; }
		#partsTable tbody td { padding:10px 12px; }
		#partsTable td, #partsTable th { text-align:left; }
		#partsTable td, #partsTable th { white-space:normal; word-wrap:break-word; word-break:break-word; }
		#partsContainer { overflow:auto; }
		#partsTable td, #partsTable th { text-align:left; }
		#partsTable { width:100%; border-collapse:collapse; }
		/* Current hours bubble (larger, solid background) */
		.hours-bubble { display:inline-block; background:#ffffff; padding:10px 16px; border-radius:999px; border:1px solid #e6eef6; font-weight:700; color:#0f172a; font-size:15px; box-shadow:0 8px 22px rgba(2,6,23,0.05); }
		.selected-info { text-align:center; margin-bottom:10px; }
		.selected-info.outside-info { position:absolute; right:40px; top:-40px; width:auto; background:transparent; display:flex; align-items:center; justify-content:flex-end; z-index:1100; }
		.selected-info.outside-info .hours-bubble { display:block; margin:0; padding:8px 14px; font-size:14px; background:#fff; border-radius:999px; border:1px solid #e6eef6; box-shadow:0 8px 24px rgba(2,6,23,0.06); }
		@media (max-width:1200px) {
			.selected-info.outside-info { display:none; }
		}
		/* Bottom equipment selector styling */
		#equipmentRibbon { transition:all .18s ease; }
		.equipment-chip { padding:10px 14px; border-radius:999px; border:1px solid rgba(226,232,240,0.9); background:#f8fafc; cursor:pointer; font-size:14px; box-shadow:0 6px 18px rgba(2,6,23,0.05); color:#0f172a; transition:all .15s ease; }
		.equipment-chip:hover { transform:translateY(-2px); box-shadow:0 10px 26px rgba(2,6,23,0.08); }
		.equipment-chip.is-selected { background:#2563eb; color:#fff; border-color:#1e40af; transform:translateY(-6px); box-shadow:0 14px 34px rgba(37,99,235,0.22); }
		.equipment-chip.is-selected:hover { transform:translateY(-6px); }

		/* Parts action button styles (no gradients) */
		#showAddPartBtn { background:#111827; color:#fff; border:none; padding:8px 12px; border-radius:8px; box-shadow:0 6px 18px rgba(2,6,23,0.06); cursor:pointer; transition:transform .12s ease, box-shadow .12s ease; }
		#showAddPartBtn:hover { transform:translateY(-3px); box-shadow:0 10px 26px rgba(2,6,23,0.08); }
		/* smaller action button for table rows */
		.parts-action-btn { background:#f3f4f6; color:#0f172a; border:1px solid #e6eef6; padding:6px 10px; border-radius:6px; font-size:13px; cursor:pointer; box-shadow:none; transition:background .12s ease, transform .12s ease; }
		.parts-action-btn:hover { background:#e6eef6; transform:translateY(-2px); }
		/* primary save buttons inside modals */
		#addModal .btn, #editModal .btn, #submitAddPart, #submitEditPartBtn { background:#0f172a; color:#fff; border:none; box-shadow:0 8px 24px rgba(2,6,23,0.06); }
		#addModal .btn:hover, #editModal .btn:hover { transform:translateY(-3px); }
		#addModal .btn.btn-ghost, #editModal .btn.btn-ghost { background:#fff !important; color:#0f172a !important; border:1px solid #e6eef6 !important; }

		.parts-delete-btn { background:#ef4444; color:#fff; border:none; padding:8px 12px; border-radius:8px; cursor:pointer; }
		.parts-delete-btn:hover { transform:translateY(-2px); }

			/* Restore original table row styling: collapsed tables and consistent cell padding */
			.oil-status-panel table, #partsTable, #fluidsTable { border-collapse: collapse !important; border-spacing: 0; }
			/* Ensure lower table cells align left */
			#fluidsTable th, #fluidsTable td { text-align: left; }

			/* Alerts above fluids table */
			#fluidsAlerts .fluid-alert { padding:10px 14px; border-radius:8px; margin-bottom:8px; font-weight:600; }
			#fluidsAlerts .fluid-alert.warn { background:linear-gradient(90deg,#fff7ed,#fffaf0); color:#92400e; border:1px solid #fcd34d; }
			#fluidsAlerts .fluid-alert.urgent { background:linear-gradient(90deg,#fff1f2,#fff5f6); color:#7f1d1d; border:1px solid #f87171; }
			.oil-status-panel tbody tr { background: transparent; }
			.oil-status-panel tbody td { background: transparent; border-radius: 0; padding:10px 12px; box-shadow: none; }
			.oil-status-panel tbody td:first-child { padding-left:12px; }
			.oil-status-panel tbody td:last-child { padding-right:12px; }

			/* Horizontal separator between panels: thicker, subtle gradient and shadow */
			.panel-separator {
				height:8px;
				background: linear-gradient(90deg, rgba(230,236,243,1) 0%, rgba(233,237,242,0.7) 50%, rgba(230,236,243,1) 100%);
				border-radius:6px;
				margin:20px 0;
				box-shadow: inset 0 1px 0 rgba(255,255,255,0.6), 0 6px 18px rgba(2,6,23,0.03);
			}
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
					<div class="panel-wrapper">
						<div class="equipment-back-btn-wrapper equipment-back-btn-wrapper--top-left" style="text-align:left;">
							<a id="backBtn" href="index.php<?php echo $previewParam; ?>" class="equipment-back-btn">
								<span>←</span>
								<span>Back to Equipments</span>
							</a>
						</div>
					</div>
					<div class="oil-page-heading" aria-hidden="true">
						<h1 id="equipmentHeading"></h1>
						<div class="subtitle" id="equipmentSubtitle">Fluid Reference Sheet</div>
					</div>
							<div class="panel-wrapper">
								<div class="oil-status-panel" id="oilStatusPanel">
									<div id="partsContainer">
										<div style="display:flex;justify-content:flex-end;align-items:center;margin-bottom:8px;">
											<div>
												<button id="showAddPartBtn" type="button" class="btn" style="padding:8px 12px;border-radius:8px;">+ Add Part</button>
											</div>
										</div>
										<table id="partsTable">
								<thead>
									<tr style="text-align:left;">
										<th>Part</th>
										<th>Approx Capacity</th>
										<th>Fluid Type</th>
										<th>Weight</th>
										<th>Mfg</th>
										<th>Supplier</th>
										<th>Unit Cost</th>
										<th>Unit</th>
										<th>Total</th>
										<th>Notes</th>
										<th style="width:90px;text-align:left;"></th>
									</tr>
								</thead>
								<tbody id="partsTbody">
									<tr><td colspan="11" style="color:#64748b">Select an equipment below to view parts.</td></tr>
								</tbody>
								</table>
					

							</div>
						</div>
								<div id="selectedInfo" class="selected-info outside-info" aria-live="polite"></div>

								<div class="panel-separator" aria-hidden="true"></div>

								<!-- Fluids panel (separate box) -->
								<div class="panel-wrapper">
									<!-- Alerts for low/urgent fluid conditions -->
									<div id="fluidsAlerts" style="margin-bottom:10px;"></div>
									<div class="oil-status-panel" id="fluidsPanel">
										<table id="fluidsTable" style="width:100%;">
										<thead>
										<tr>
											<th>Fluid Type</th>
											<th>Current Hours</th>
											<th>Fluid Life</th>
											<th>Condition</th>
											<th style="width:110px;text-align:left;"></th>
										</tr>
										</thead>
										<tbody id="fluidsTbody">
											<tr><td colspan="5" style="color:#64748b">Select an equipment below to view fluid status.</td></tr>
										</tbody>
										</table>
									</div>
								</div>

								<!-- Add Part Modal -->
								<div id="addModal" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(2,6,23,0.45);z-index:1200;">
									<div style="background:#fff;padding:18px;border-radius:10px;min-width:520px;max-width:95%;box-shadow:0 16px 48px rgba(2,6,23,0.3);">
										<h3 style="margin:0 0 8px 0;">Add Part</h3>
										<div style="display:flex;flex-direction:column;gap:8px;">
											<input list="existingPartsList" id="partInput" name="part" placeholder="Part name" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;">
											<datalist id="existingPartsList"></datalist>
											<div style="display:flex;gap:8px;flex-wrap:wrap;">
												<input id="approxCapacityInput" name="approx_capacity" placeholder="Approx Capacity" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:140px;">
												<input id="fluidTypeInput" name="fluid_type" placeholder="Fluid Type" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:140px;">
												<input id="weightInput" name="weight" placeholder="Weight" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:120px;">
											</div>
											<div style="display:flex;gap:8px;flex-wrap:wrap;">
												<input id="mfgInput" name="mfg" placeholder="Mfg" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:140px;">
												<input id="supplierInput" name="supplier" placeholder="Supplier" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:140px;">
												<input id="unitCostInput" name="unit_cost" placeholder="Unit Cost" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:120px;">
											</div>
											<div style="display:flex;gap:8px;flex-wrap:wrap;">
												<input id="unitInput" name="unit" placeholder="Unit" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:120px;">
												<input id="totalInput" name="total" placeholder="Total" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:120px;">
												<input id="notesInput" name="notes" placeholder="Notes" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:220px;">
												<input id="oilLifeInput" name="oil_life" placeholder="Fluid life (hours)" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:160px;">
											</div>
											<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:6px;">
												<button id="submitAddPart" type="button" class="btn" style="padding:8px 12px;border-radius:8px;background:#2563eb;color:#fff;border:none;">Save</button>
												<button id="cancelAddPart" type="button" class="btn btn-ghost" style="padding:8px 12px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;">Cancel</button>
											</div>
										</div>
									</div>
								</div>

								<!-- Edit Part Modal -->
								<div id="editModal" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(2,6,23,0.45);z-index:1200;">
									<div style="background:#fff;padding:18px;border-radius:10px;min-width:520px;max-width:95%;box-shadow:0 16px 48px rgba(2,6,23,0.3);">
										<h3 style="margin:0 0 8px 0;">Edit Part</h3>
										<input type="hidden" id="editPartId">
										<div style="display:flex;flex-direction:column;gap:8px;">
											<input id="editPartName" placeholder="Part name" list="existingPartsList" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;">
											<div style="display:flex;gap:8px;flex-wrap:wrap;">
												<input id="editApproxCapacity" placeholder="Approx Capacity" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:140px;">
												<input id="editFluidType" placeholder="Fluid Type" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:140px;">
												<input id="editWeight" placeholder="Weight" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:120px;">
											</div>
											<div style="display:flex;gap:8px;flex-wrap:wrap;">
												<input id="editMfg" placeholder="Mfg" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:140px;">
												<input id="editSupplier" placeholder="Supplier" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:140px;">
												<input id="editUnitCost" placeholder="Unit Cost" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:120px;">
											</div>
											<div style="display:flex;gap:8px;flex-wrap:wrap;">
												<input id="editUnit" placeholder="Unit" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:120px;">
												<input id="editTotal" placeholder="Total" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:120px;">
													<input id="editNotes" placeholder="Notes" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:220px;">
													<input id="editOilLife" placeholder="Fluid life (hours)" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:160px;">
											</div>
											<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:6px;">
												<button id="submitEditPartBtn" type="button" class="btn" style="padding:8px 12px;border-radius:8px;background:#2563eb;color:#fff;border:none;">Save</button>
												<button id="deleteEditPartBtn" type="button" class="parts-delete-btn" style="padding:8px 12px;border-radius:8px;border:none;background:#ef4444;color:#fff;">Delete</button>
												<button id="cancelEditPartBtn" type="button" class="btn btn-ghost" style="padding:8px 12px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;">Cancel</button>
											</div>
										</div>
									</div>
								</div>
					</div>

					<!-- Bottom centered equipment selector ribbon -->
					<div id="equipmentRibbon" style="position:fixed;left:50%;transform:translateX(-50%);bottom:18px;z-index:999;background:rgba(255,255,255,0.96);padding:8px 12px;border-radius:999px;box-shadow:0 6px 20px rgba(2,6,23,0.08);display:flex;gap:8px;align-items:center;max-width:95%;overflow:auto;">
						<!-- chips inserted here -->
					</div>
				</div>
			</main>
		</div>
	</div>

	<script>
	// Initial data emitted from server
	var INITIAL_EQUIPMENTS = <?php echo json_encode($equipments ?: []); ?>;
	var INITIAL_PARTS = <?php echo json_encode($partsByEquip ?: new stdClass()); ?>;
	var IS_ADMIN = <?php echo is_admin() ? 'true' : 'false'; ?>;
	// build a list of existing part names for reuse across equipments
	var EXISTING_PART_NAMES = (function(){
		var out = [];
		try {
			var keys = Object.keys(INITIAL_PARTS || {});
			keys.forEach(function(k){
				var arr = INITIAL_PARTS[k] || [];
				arr.forEach(function(p){ if (p && p.part) out.push(p.part); });
			});
		} catch(e){}
		// unique
		return out.filter(function(v,i,a){ return v && a.indexOf(v) === i; });
	})();
	var CURRENT_EQUIPMENT_ID = null;

	function formatCell(v){ return v === null || v === undefined || v === '' ? '—' : String(v); }

	// Compute API base (handles installations under a subpath)
	var API_BASE = (function(){
		try {
			var p = location.pathname || '/';
			var idx = p.indexOf('/pages/');
			if (idx !== -1) return location.origin + p.slice(0, idx) + '/';
			// fallback to site root
			return location.origin + '/';
		} catch (e) { return location.origin + '/'; }
	})();

	// Render parts rows for equipment id
	function renderPartsFor(equipmentId){
		var tbody = document.getElementById('partsTbody');
		tbody.innerHTML = '';
		var parts = INITIAL_PARTS && INITIAL_PARTS[equipmentId] ? INITIAL_PARTS[equipmentId] : [];
		var equip = INITIAL_EQUIPMENTS.find(function(x){ return Number(x.equipment_id) === Number(equipmentId); }) || null;
		CURRENT_EQUIPMENT_ID = equipmentId;
		var currentHours = equip ? formatCell(equip.current_hours) : '—';
		
		// DECLARE alerts arrays at the start
		var warnAlerts = [];
		var urgentAlerts = [];
		
		if (!parts.length){
			// leave table body empty when there are no parts for this equipment
			tbody.innerHTML = '';
			// update selected info (hours only) even when no parts
			try {
				var info = document.getElementById('selectedInfo');
				info.innerHTML = '<div class="hours-bubble">Current equipment hours: ' + currentHours + '</div>';
			} catch (e) {}
			// ensure heading still updates even if there are no parts
			try { updateHeading(equip, equipmentId); } catch (e) {}
			try { renderFluidsFor(equipmentId); } catch(e) {}
			return;
		}
		
		parts.forEach(function(p){
			var tr = document.createElement('tr');
			var resetAt = p.reset_at ? p.reset_at : '';
			var editBtn = IS_ADMIN ? '<button type="button" class="parts-action-btn" data-partid="' + (p.id || '') + '" onclick="openEditModal(' + (p.id || 0) + ', ' + equipmentId + ')">Edit</button>' : '';
			tr.innerHTML = '<td>' + formatCell(p.part) + '</td>' +
						   '<td>' + formatCell(p.approx_capacity) + '</td>' +
						   '<td>' + formatCell(p.fluid_type) + '</td>' +
						   '<td>' + formatCell(p.weight) + '</td>' +
						   '<td>' + formatCell(p.mfg) + '</td>' +
						   '<td>' + formatCell(p.supplier) + '</td>' +
						   '<td>' + formatCell(p.unit_cost) + '</td>' +
						   '<td>' + formatCell(p.unit) + '</td>' +
						   '<td>' + formatCell(p.total) + '</td>' +
						   '<td>' + formatCell(p.notes) + '</td>' +
						   '<td style="text-align:left;">' + editBtn + '</td>';
			tbody.appendChild(tr);
		});

		// Update selected info bubble (outside panel) and heading
		try {
			var info = document.getElementById('selectedInfo');
			var hours = currentHours;
			info.innerHTML = '<div class="hours-bubble">Current equipment hours: ' + hours + '</div>';
		} catch (e) {}
		try {
			updateHeading(equip, equipmentId);
		} catch (e) {}
		// render bottom fluids table for selected equipment
		try { renderFluidsFor(equipmentId); } catch (e) {}
	}

	// Build equipment ribbon
	function buildRibbon(){
		var ribbon = document.getElementById('equipmentRibbon');
		ribbon.innerHTML = '';
		if (!INITIAL_EQUIPMENTS || !INITIAL_EQUIPMENTS.length) {
			var note = document.createElement('div'); note.style.color='#64748b'; note.textContent='No equipments found'; ribbon.appendChild(note); return;
		}
		INITIAL_EQUIPMENTS.forEach(function(eq, idx){
			var chip = document.createElement('button');
			chip.className = 'equipment-chip';
			chip.setAttribute('type','button');
			chip.setAttribute('aria-pressed','false');
			chip.style.whiteSpace = 'nowrap';
			chip.dataset.eid = eq.equipment_id;
			chip.textContent = (eq.number && eq.number !== '') ? eq.number : ('#' + eq.equipment_id);
			chip.addEventListener('click', function(){
				var prev = document.querySelector('.equipment-chip.is-selected');
				if (prev) { prev.classList.remove('is-selected'); prev.setAttribute('aria-pressed','false'); }
				chip.classList.add('is-selected');
				chip.setAttribute('aria-pressed','true');
				renderPartsFor(eq.equipment_id);
			});
			ribbon.appendChild(chip);
		});
		// Select an equipment: prefer id from URL, otherwise default to first
		var first = ribbon.querySelector('.equipment-chip');
		try {
			var params = (new URLSearchParams(location.search));
			var requested = params.get('id') || params.get('equipment_id') || null;
			var selectedChip = null;
			if (requested) {
				// try to match dataset.eid (string compare)
				selectedChip = ribbon.querySelector('.equipment-chip[data-eid="' + requested + '"]');
			}
			if (selectedChip) {
				selectedChip.click();
				// ensure heading updates for the selected equipment
				var eq = INITIAL_EQUIPMENTS.find(function(x){ return String(x.equipment_id) === String(requested); });
				if (eq) setTimeout(function(){ updateHeading(eq, eq.equipment_id); }, 50);
			} else if (first) {
				first.click();
				// also set heading for the first equipment
				if (INITIAL_EQUIPMENTS && INITIAL_EQUIPMENTS.length) setTimeout(function(){ updateHeading(INITIAL_EQUIPMENTS[0], INITIAL_EQUIPMENTS[0].equipment_id); }, 50);
			}
		} catch (e) {}
	}

	// Update page heading based on equipment object (or id)
	function updateHeading(equip, equipmentId) {
		var heading = document.getElementById('equipmentHeading');
		var subtitle = document.getElementById('equipmentSubtitle');
		if (!heading) return;
		var label = '';
		if (equip) {
			label = (equip.number && equip.number !== '') ? equip.number : ('#' + equip.equipment_id);
			if (equip.type) label += ' | ' + equip.type;
		} else if (equipmentId) {
			label = '#' + equipmentId;
		}
		if (label && label !== '') {
			heading.textContent = label;
		} else {
			heading.textContent = '';
		}
		if (subtitle) subtitle.textContent = 'Fluid Reference Sheet';
	}

	// Populate datalist for existing parts
	function renderExistingPartsDatalist(){
		var dl = document.getElementById('existingPartsList'); if (!dl) return;
		dl.innerHTML = '';
		(EXISTING_PART_NAMES || []).forEach(function(name){
			var opt = document.createElement('option'); opt.value = name; dl.appendChild(opt);
		});
	}

	// Render bottom fluids table (read-only) showing remaining life and condition
	function renderFluidsFor(equipmentId){
		var tbody = document.getElementById('fluidsTbody'); if (!tbody) return;
		tbody.innerHTML = '';
		var parts = INITIAL_PARTS && INITIAL_PARTS[equipmentId] ? INITIAL_PARTS[equipmentId] : [];
		var equip = INITIAL_EQUIPMENTS.find(function(x){ return Number(x.equipment_id) === Number(equipmentId); }) || { current_hours:0 };
		// clear alerts element first
		var alertsEl = document.getElementById('fluidsAlerts'); if (alertsEl) alertsEl.innerHTML = '';
		if (!parts.length){ tbody.innerHTML = '<tr><td colspan="5" style="color:#64748b">No fluids found for this equipment.</td></tr>'; return; }
		var warnAlerts = [];
		var urgentAlerts = [];
		parts.forEach(function(p){
			var tr = document.createElement('tr');
			var partCurrent = parseFloat(p.current_hours) || 0;
			var equipCurrent = parseFloat(equip.current_hours) || 0;
			// Hours since last reset: equipment.current_hours - part.current_hours
			var diff = (equipCurrent - partCurrent);
			if (isNaN(diff) || diff < 0) diff = 0;
			var oilLife = parseFloat(p.oil_life) || 0;
			var conditionPct = 100;
			if (oilLife > 0) {
				var usedPercent = (diff / oilLife) * 100;
				conditionPct = Math.max(0, Math.min(100, Math.round(100 - usedPercent)));
			}
			var resetBtn = IS_ADMIN ? '<button type="button" class="parts-action-btn" onclick="resetPartHours(' + (p.id || 0) + ')">Reset</button>' : '';
			tr.innerHTML = '<td>' + formatCell(p.fluid_type) + '</td>' +
			               '<td>' + formatCell(diff.toFixed(2)) + '</td>' +
			               '<td>' + formatCell(oilLife) + '</td>' +
					'<td>' + conditionPct + '%' + '</td>' +
					'<td style="text-align:left;">' + resetBtn + '</td>';
			tbody.appendChild(tr);
			// collect alerts
			if (conditionPct <= 0) urgentAlerts.push(p.fluid_type || 'Fluid');
			else if (conditionPct < 20) warnAlerts.push(p.fluid_type || 'Fluid');
		});
		// render alerts: urgent first
		if (alertsEl) {
			if (urgentAlerts.length) {
				urgentAlerts.forEach(function(name){
					var d = document.createElement('div'); d.className = 'fluid-alert urgent'; d.textContent = 'Change ' + name + ' now.'; alertsEl.appendChild(d);
				});
			} else if (warnAlerts.length) {
				warnAlerts.forEach(function(name){
					var d = document.createElement('div'); d.className = 'fluid-alert warn'; d.textContent = 'Change ' + name + ' soon.'; alertsEl.appendChild(d);
				});
			}
		}
	}

	function resetPartHours(partId){
		if (!confirm('Reset this part\'s hours to current equipment hours?')) return;
		var fd = new FormData(); fd.append('id', partId);
		fetch(API_BASE + 'api/reset_equipment_oil_part_hours.php', { method: 'POST', body: fd, credentials: 'same-origin' })
		.then(function(r){ return r.text().then(function(text){ try { return JSON.parse(text); } catch(e){ throw { type:'parse', text:text, status:r.status }; } }); })
		.then(function(json){
			if (!json || !json.success) throw new Error((json && json.message) ? json.message : 'Reset failed');
			var updated = json.row;
			// update part in INITIAL_PARTS
			for (var k in INITIAL_PARTS){ if (!INITIAL_PARTS.hasOwnProperty(k)) continue; INITIAL_PARTS[k] = INITIAL_PARTS[k].map(function(it){ return Number(it.id) === Number(updated.id) ? updated : it; }); }
			renderPartsFor(CURRENT_EQUIPMENT_ID);
		})
		.catch(function(err){ console.error('Reset error', err); if (err && err.type === 'parse') { alert('Error resetting part: invalid JSON response from server.'); console.error('Raw response:', err.text); } else alert('Error resetting part: ' + (err && err.message ? err.message : 'unknown')); });
	}

	function openAddForm(){
		renderExistingPartsDatalist();
		var modal = document.getElementById('addModal');
		if (modal) modal.style.display = 'flex';
		var partEl = document.getElementById('partInput'); if (partEl) partEl.focus();
	}

	function closeAddForm(){
		var modal = document.getElementById('addModal'); if (modal) modal.style.display = 'none';
		// clear inputs
		['partInput','approxCapacityInput','fluidTypeInput','weightInput','mfgInput','supplierInput','unitCostInput','unitInput','totalInput','notesInput','oilLifeInput'].forEach(function(id){ var el = document.getElementById(id); if (el) el.value = ''; });
	}

	function submitAddPart(){
		var btn = document.getElementById('submitAddPart'); btn.disabled = true; var orig = btn.textContent; btn.textContent = 'Saving...';
		var data = new FormData();
		// allow creating parts without selecting an equipment; use 0 as fallback
		data.append('equipment_id', CURRENT_EQUIPMENT_ID || 0);
		var partVal = document.getElementById('partInput').value.trim();
		data.append('part', partVal);
		data.append('approx_capacity', document.getElementById('approxCapacityInput').value);
		data.append('fluid_type', document.getElementById('fluidTypeInput').value);
		data.append('weight', document.getElementById('weightInput').value);
		data.append('mfg', document.getElementById('mfgInput').value);
		data.append('supplier', document.getElementById('supplierInput').value);
		data.append('unit_cost', document.getElementById('unitCostInput').value);
		data.append('unit', document.getElementById('unitInput').value);
		data.append('total', document.getElementById('totalInput').value);
		data.append('notes', document.getElementById('notesInput').value);
		data.append('oil_life', document.getElementById('oilLifeInput').value || 0);

		fetch(API_BASE + 'api/add_equipment_oil_part.php', { method: 'POST', body: data, credentials: 'same-origin' })
		.then(function(r){
			return r.text().then(function(text){
				try { return JSON.parse(text); }
				catch (err) { throw { type: 'parse', text: text, status: r.status }; }
			});
		})
		.then(function(json){
			if (!json || !json.success){ throw new Error((json && json.message) ? json.message : 'Save failed'); }
			// Add to INITIAL_PARTS for current equipment
			INITIAL_PARTS[CURRENT_EQUIPMENT_ID] = INITIAL_PARTS[CURRENT_EQUIPMENT_ID] || [];
			INITIAL_PARTS[CURRENT_EQUIPMENT_ID].push(json.row);
			// add to existing names if missing
			if (EXISTING_PART_NAMES.indexOf(json.row.part) === -1){ EXISTING_PART_NAMES.push(json.row.part); }
			closeAddForm();
			renderPartsFor(CURRENT_EQUIPMENT_ID);
			btn.disabled = false; btn.textContent = orig;
			// ensure datalist updated
			renderExistingPartsDatalist();
		})
		.catch(function(err){
			console.error('Add part error', err);
			if (err && err.type === 'parse') {
				// server returned non-JSON (likely PHP warning/html) — show raw text
				alert('Error adding part: invalid JSON response from server. See console for raw response.');
				console.error('Raw response:', err.text);
			} else {
				alert('Error adding part: ' + (err && err.message ? err.message : 'unknown'));
			}
			btn.disabled = false; btn.textContent = orig;
		});
	}

	// --- Edit modal support ---
	function openEditModal(partId, equipmentId){
		// find part object in INITIAL_PARTS
		var parts = INITIAL_PARTS && INITIAL_PARTS[equipmentId] ? INITIAL_PARTS[equipmentId] : [];
		var p = parts.find(function(x){ return Number(x.id) === Number(partId); }) || null;
		if (!p) { alert('Part not found'); return; }
		// populate fields
		document.getElementById('editPartId').value = p.id;
		document.getElementById('editPartName').value = p.part || '';
		document.getElementById('editApproxCapacity').value = p.approx_capacity || '';
		document.getElementById('editFluidType').value = p.fluid_type || '';
		document.getElementById('editWeight').value = p.weight || '';
		document.getElementById('editMfg').value = p.mfg || '';
		document.getElementById('editSupplier').value = p.supplier || '';
		document.getElementById('editUnitCost').value = p.unit_cost || '';
		document.getElementById('editUnit').value = p.unit || '';
		document.getElementById('editTotal').value = p.total || '';
		document.getElementById('editNotes').value = p.notes || '';
		document.getElementById('editOilLife').value = (p.oil_life !== undefined && p.oil_life !== null) ? p.oil_life : '';
		// show modal
		document.getElementById('editModal').style.display = 'flex';
	}

	function closeEditModal(){ document.getElementById('editModal').style.display = 'none'; }

	function submitEditPart(){
		var id = document.getElementById('editPartId').value;
		if (!id) return alert('Invalid part id');
		var data = new FormData();
		data.append('id', id);
		data.append('part', document.getElementById('editPartName').value);
		data.append('approx_capacity', document.getElementById('editApproxCapacity').value);
		data.append('fluid_type', document.getElementById('editFluidType').value);
		data.append('weight', document.getElementById('editWeight').value);
		data.append('mfg', document.getElementById('editMfg').value);
		data.append('supplier', document.getElementById('editSupplier').value);
		data.append('unit_cost', document.getElementById('editUnitCost').value);
		data.append('unit', document.getElementById('editUnit').value);
		data.append('total', document.getElementById('editTotal').value);
		data.append('notes', document.getElementById('editNotes').value);
		data.append('oil_life', document.getElementById('editOilLife').value || 0);
		fetch(API_BASE + 'api/update_equipment_oil_part.php', { method: 'POST', body: data, credentials: 'same-origin' })
		.then(function(r){
			return r.text().then(function(text){
				try { return JSON.parse(text); }
				catch (err) { throw { type: 'parse', text: text, status: r.status }; }
			});
		})
		.then(function(json){
			if (!json || !json.success) throw new Error((json && json.message) ? json.message : 'Update failed');
			// update in INITIAL_PARTS
			var updated = json.row;
			for (var k in INITIAL_PARTS){ if (!INITIAL_PARTS.hasOwnProperty(k)) continue; INITIAL_PARTS[k] = INITIAL_PARTS[k].map(function(it){ return Number(it.id) === Number(updated.id) ? updated : it; }); }
			closeEditModal();
			// re-render current equipment
			renderPartsFor(CURRENT_EQUIPMENT_ID);
		})
		.catch(function(err){
			console.error('Update error', err);
			if (err && err.type === 'parse'){
				alert('Error updating part: invalid JSON response from server. See console for raw response.');
				console.error('Raw response:', err.text);
			} else {
				alert('Error updating part: ' + (err && err.message ? err.message : 'unknown'));
			}
		});
	}

	function submitDeletePart(){
		var id = document.getElementById('editPartId').value;
		if (!id) return alert('Invalid part id');
		if (!confirm('Delete this part? This action cannot be undone.')) return;
		var fd = new FormData(); fd.append('id', id);
		fetch(API_BASE + 'api/delete_equipment_oil_part.php', { method: 'POST', body: fd, credentials: 'same-origin' })
		.then(function(r){ return r.text().then(function(text){ try { return JSON.parse(text); } catch(e){ throw { type:'parse', text:text, status:r.status }; } }); })
		.then(function(json){
			if (!json || !json.success) throw new Error((json && json.message) ? json.message : 'Delete failed');
			// remove from INITIAL_PARTS
			for (var k in INITIAL_PARTS){ if (!INITIAL_PARTS.hasOwnProperty(k)) continue; INITIAL_PARTS[k] = INITIAL_PARTS[k].filter(function(it){ return Number(it.id) !== Number(id); }); }
			closeEditModal();
			renderPartsFor(CURRENT_EQUIPMENT_ID);
		})
		.catch(function(err){
			console.error('Delete error', err);
			if (err && err.type === 'parse') { alert('Error deleting part: invalid JSON response from server. See console.'); console.error('Raw response:', err.text); }
			else alert('Error deleting part: ' + (err && err.message ? err.message : 'unknown'));
		});
	}

	// Back button behavior
	(function(){
		var btn = document.getElementById('backBtn');
		if (!btn) return;
		btn.addEventListener('click', function(){
			try {
				var ref = document.referrer || '';
				if (ref && ref.indexOf(location.origin) === 0) { history.back(); return; }
			} catch (e) {}
			window.location.href = 'index.php' + '<?php echo isset($previewParam) ? $previewParam : ""; ?>';
		});
	})();

	// Kick off
	document.addEventListener('DOMContentLoaded', function(){
		buildRibbon();
		// wire up add part UI (modal)
		var showBtn = document.getElementById('showAddPartBtn'); if (showBtn) showBtn.addEventListener('click', function(){ renderExistingPartsDatalist(); document.getElementById('addModal').style.display='flex'; document.getElementById('partInput').focus(); });
		var cancelBtn = document.getElementById('cancelAddPart'); if (cancelBtn) cancelBtn.addEventListener('click', function(){ document.getElementById('addModal').style.display='none'; });
		var submitBtn = document.getElementById('submitAddPart'); if (submitBtn) submitBtn.addEventListener('click', submitAddPart);
		var cancelEditBtn = document.getElementById('cancelEditPartBtn'); if (cancelEditBtn) cancelEditBtn.addEventListener('click', closeEditModal);
		var submitEditBtn = document.getElementById('submitEditPartBtn'); if (submitEditBtn) submitEditBtn.addEventListener('click', submitEditPart);
		var deleteEditBtn = document.getElementById('deleteEditPartBtn'); if (deleteEditBtn) deleteEditBtn.addEventListener('click', submitDeletePart);
		// ensure datalist initially populated
		renderExistingPartsDatalist();
	});
</script>
</body>
</html>

