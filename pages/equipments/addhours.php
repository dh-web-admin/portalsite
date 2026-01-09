<?php
require_once __DIR__ . '/../../session_init.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
	header('Location: /auth/login.php');
	exit();
}

$email = $_SESSION['email'];
$roleStmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$roleStmt->bind_param('s', $email);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$user = $roleRes ? $roleRes->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$roleStmt->close();

if (!can_access($role, 'equipments')) {
	header('Location: /pages/dashboard/');
	exit();
}

// Hide admin-only UI elements for non-admin users
if (!can_edit_page('equipments')) {
	echo <<<'HTML'
<style>.admin-only, .save-btn { display: none !important; }</style>
<script>
(function(){
	var patterns=[/\bedit\b/i,/\bupload\b/i,/\bdelete\b/i,/\badd\b/i,/\bremove\b/i,/\bsave\b/i];
	function hideIfMatch(el){
		var text=(el.innerText||el.value||'').trim();
		var title=(el.getAttribute && (el.getAttribute('title')||el.getAttribute('aria-label')))||'';
		if(!text && !title) return;
		var combined = (text + ' ' + title).trim();
		for(var i=0;i<patterns.length;i++){ if(patterns[i].test(combined)){ el.style.display='none'; return; } }
	}
	document.addEventListener('DOMContentLoaded', function(){
		var els=document.querySelectorAll('a,button,input[type=button],input[type=submit]');
		els.forEach(hideIfMatch);
	});
})();
</script>
HTML;
}

$equipments = [];
try {
	$r = $conn->query("SELECT equipment_id, COALESCE(dhss_equipment_number, '') AS number, COALESCE(current_hours,0) AS current_hours, COALESCE(type,'') AS type FROM equipments ORDER BY equipment_id ASC");
	if ($r) {
		while ($row = $r->fetch_assoc()) {
			$equipments[] = $row;
		}
		$r->free();
	}
} catch (Throwable $e) {
}

// Filters grouped by equipment
$filtersByEquip = [];
try {
	$sql = "SELECT filter_id, equipment_id, filter_name, filter_date, hours FROM filter_info ORDER BY equipment_id ASC, filter_name ASC";
	$res = $conn->query($sql);
	if ($res) {
		while ($row = $res->fetch_assoc()) {
			$eid = (int)($row['equipment_id'] ?? 0);
			if (!isset($filtersByEquip[$eid])) {
				$filtersByEquip[$eid] = [];
			}
			$filtersByEquip[$eid][] = [
				'filter_id'   => (int)($row['filter_id'] ?? 0),
				'equipment_id'=> $eid,
				'filter_name' => $row['filter_name'] ?? '',
				'filter_date' => $row['filter_date'] ?? null,
				'hours'       => $row['hours'],
			];
		}
		$res->free();
	}
} catch (Throwable $e) {
}

// Fluids (oil parts) grouped by equipment
$fluidsByEquip = [];
try {
	$sql2 = "SELECT id, equipment_id, part, fluid_type, reset_at, current_hours FROM equipment_oil_parts ORDER BY equipment_id ASC, part ASC";
	$res2 = $conn->query($sql2);
	if ($res2) {
		while ($row = $res2->fetch_assoc()) {
			$eid = (int)($row['equipment_id'] ?? 0);
			if (!isset($fluidsByEquip[$eid])) {
				$fluidsByEquip[$eid] = [];
			}
			$fluidsByEquip[$eid][] = [
				'id'            => (int)($row['id'] ?? 0),
				'equipment_id'  => $eid,
				'part'          => $row['part'] ?? '',
				'fluid_type'    => $row['fluid_type'] ?? '',
				'reset_at'      => $row['reset_at'] ?? null,
				'current_hours' => $row['current_hours'],
			];
		}
		$res2->free();
	}
} catch (Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Adjust Hours</title>
	<link rel="stylesheet" href="../../assets/css/base.css">
	<link rel="stylesheet" href="../../assets/css/admin-layout.css">
	<link rel="stylesheet" href="../../assets/css/dashboard.css">
	<style>
		.hours-page-wrapper { max-width:1400px; margin:0 auto; }
		.hours-page-header { display:flex; align-items:center; justify-content:space-between; margin:16px 0 10px; }
		.hours-page-header h1 { margin:0; font-size:24px; font-weight:700; letter-spacing:2px; }
		.hours-page-header .subtitle { margin-top:4px; color:#6b7280; font-size:13px; }
		.save-btn { padding:8px 16px; border-radius:8px; border:none; background:#16a34a; color:#fff; font-weight:600; font-size:13px; cursor:pointer; box-shadow:0 6px 18px rgba(22,163,74,0.25); }
		.save-btn[disabled] { opacity:0.6; cursor:default; box-shadow:none; }

		.hours-panel { padding:14px; background:#fff; border:1px solid #e6eef6; border-radius:8px; box-shadow:0 6px 18px rgba(2,6,23,0.04); margin-top:10px; }

		.tables-row { display:flex; gap:16px; align-items:flex-start; margin-top:10px; }
		.table-card { flex:1 1 0; min-width:0; }
		.tables-row .table-card:first-child { border-right:1px solid #e5e7eb; padding-right:14px; margin-right:4px; }
		.tables-row .table-card:last-child { padding-left:10px; }
		.table-card h2 { font-size:16px; margin:0 0 6px; color:#0f172a; }
		.table-card table { width:100%; border-collapse:collapse; }
		.table-card th, .table-card td { padding:8px 10px; text-align:left; border-bottom:1px solid #e5e7eb; white-space:normal; word-break:break-word; }
		.table-card thead th { font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#6b7280; }
		.table-card tbody tr:nth-child(even) { background:#f9fafb; }

		.equipment-ribbon { margin-top:18px; padding:8px 10px; display:flex; flex-wrap:nowrap; gap:8px; overflow-x:auto; border-radius:999px; background:rgba(255,255,255,0.96); box-shadow:0 6px 18px rgba(2,6,23,0.06); }
		.equipment-chip { padding:8px 14px; border-radius:999px; border:1px solid rgba(226,232,240,0.9); background:#f8fafc; cursor:pointer; font-size:13px; box-shadow:0 6px 18px rgba(2,6,23,0.05); color:#0f172a; transition:all .15s ease; white-space:nowrap; }
		.equipment-chip:hover { transform:translateY(-2px); box-shadow:0 10px 26px rgba(2,6,23,0.08); }
		.equipment-chip.is-selected { background:#2563eb; color:#fff; border-color:#1e40af; transform:translateY(-4px); box-shadow:0 14px 34px rgba(37,99,235,0.22); }

		.editable-cell { cursor:pointer; }
		.editable-cell.editing { padding:4px 6px; }
		.editable-input { width:100%; box-sizing:border-box; padding:4px 6px; border-radius:4px; border:1px solid #cbd5e1; font-size:13px; }

		.hours-meta { margin-top:6px; font-size:13px; color:#6b7280; }
	</style>
</head>
<body class="admin-page">
	<div class="admin-container">
		<?php include __DIR__ . '/../../partials/portalheader.php'; ?>
		<div class="admin-layout">
			<?php include __DIR__ . '/../../partials/sidebar.php'; ?>
			<main class="content-area">
				<div class="main-content">
					<div class="hours-page-wrapper">
						<div style="margin-top:18px;margin-bottom:6px;">
							<a href="index.php" class="btn" style="padding:8px 14px;border-radius:8px;background:#2563eb;color:#fff;border:none;text-decoration:none;">← Back to Equipments</a>
						</div>
						<div class="hours-page-header">
							<div>
								<h1>Adjust Hours</h1>
								<div class="subtitle">Edit last changed dates and equipment hours at last change.</div>
							</div>
							<button id="saveChangesBtn" type="button" class="save-btn">Save Changes</button>
						</div>

						<div class="hours-panel">
							<div class="hours-meta" id="selectedEquipmentMeta">Select an equipment below to begin.</div>
							<div class="tables-row">
								<div class="table-card">
									<h2>Air Filters</h2>
									<table id="filtersTable">
										<thead>
											<tr>
												<th>Filter Name</th>
												<th>Last Changed Date</th>
												<th>Eqp hrs @ last change</th>
											</tr>
										</thead>
										<tbody id="filtersTbody">
											<tr><td colspan="3" style="color:#64748b;">Select an equipment to view filters.</td></tr>
										</tbody>
									</table>
								</div>
								<div class="table-card">
									<h2>Fluids</h2>
									<table id="fluidsTable">
										<thead>
											<tr>
												<th>Part</th>
												<th>Fluid Type</th>
												<th>Last Changed Date</th>
												<th>Eqp hrs @ last change</th>
											</tr>
										</thead>
										<tbody id="fluidsTbody">
											<tr><td colspan="4" style="color:#64748b;">Select an equipment to view fluids.</td></tr>
										</tbody>
									</table>
								</div>
							</div>
							<div class="equipment-ribbon" id="equipmentRibbon"></div>
						</div>
					</div>
				</div>
			</main>
		</div>
	</div>

	<script>
		var INITIAL_EQUIPMENTS = <?php echo json_encode($equipments ?: []); ?>;
		var INITIAL_FILTERS = <?php echo json_encode($filtersByEquip ?: new stdClass()); ?>;
		var INITIAL_FLUIDS = <?php echo json_encode($fluidsByEquip ?: new stdClass()); ?>;

		var CURRENT_EQUIPMENT_ID = null;
		var pendingFilterEdits = {};
		var pendingFluidEdits = {};

		function formatCell(v) {
			return v === null || v === undefined || v === '' ? '—' : String(v);
		}

		var API_BASE = (function(){
			try {
				var p = location.pathname || '/';
				var idx = p.indexOf('/pages/');
				if (idx !== -1) return location.origin + p.slice(0, idx) + '/';
				return location.origin + '/';
			} catch (e) { return location.origin + '/'; }
		})();

		function getEquipmentById(id) {
			return INITIAL_EQUIPMENTS.find(function(eq){ return Number(eq.equipment_id) === Number(id); }) || null;
		}

		function updateSelectedMeta() {
			var meta = document.getElementById('selectedEquipmentMeta');
			if (!meta) return;
			if (!CURRENT_EQUIPMENT_ID) {
				meta.textContent = 'Select an equipment below to begin.';
				return;
			}
			var eq = getEquipmentById(CURRENT_EQUIPMENT_ID);
			if (!eq) {
				meta.textContent = 'Equipment #' + CURRENT_EQUIPMENT_ID;
				return;
			}
			var label = (eq.number && eq.number !== '') ? eq.number : ('#' + eq.equipment_id);
			if (eq.type) label += ' | ' + eq.type;
			meta.textContent = label + ' — Current hours: ' + formatCell(eq.current_hours);
		}

		function renderFiltersFor(equipmentId) {
			var tbody = document.getElementById('filtersTbody');
			if (!tbody) return;
			tbody.innerHTML = '';
			var list = INITIAL_FILTERS && INITIAL_FILTERS[equipmentId] ? INITIAL_FILTERS[equipmentId] : [];
			if (!list.length) {
				tbody.innerHTML = '<tr><td colspan="3" style="color:#64748b;">No filters found for this equipment.</td></tr>';
				return;
			}
			list.forEach(function(item){
				var tr = document.createElement('tr');
				tr.innerHTML =
					'<td>' + formatCell(item.filter_name) + '</td>' +
					'<td class="editable-cell" data-kind="filter" data-field="filter_date" data-type="date" data-id="' + (item.filter_id || '') + '" data-equip="' + equipmentId + '">' + formatCell(item.filter_date) + '</td>' +
					'<td class="editable-cell" data-kind="filter" data-field="hours" data-type="number" data-id="' + (item.filter_id || '') + '" data-equip="' + equipmentId + '">' + formatCell(item.hours) + '</td>';
				tbody.appendChild(tr);
			});
		}

		function renderFluidsFor(equipmentId) {
			var tbody = document.getElementById('fluidsTbody');
			if (!tbody) return;
			tbody.innerHTML = '';
			var list = INITIAL_FLUIDS && INITIAL_FLUIDS[equipmentId] ? INITIAL_FLUIDS[equipmentId] : [];
			if (!list.length) {
				tbody.innerHTML = '<tr><td colspan="4" style="color:#64748b;">No fluids found for this equipment.</td></tr>';
				return;
			}
			list.forEach(function(item){
				var tr = document.createElement('tr');
				var resetDate = item.reset_at ? String(item.reset_at).split(' ')[0] : '';
				tr.innerHTML =
					'<td>' + formatCell(item.part) + '</td>' +
					'<td>' + formatCell(item.fluid_type) + '</td>' +
					'<td class="editable-cell" data-kind="fluid" data-field="reset_at" data-type="date" data-id="' + (item.id || '') + '" data-equip="' + equipmentId + '">' + formatCell(resetDate) + '</td>' +
					'<td class="editable-cell" data-kind="fluid" data-field="current_hours" data-type="number" data-id="' + (item.id || '') + '" data-equip="' + equipmentId + '">' + formatCell(item.current_hours) + '</td>';
				tbody.appendChild(tr);
			});
		}

		function renderForEquipment(equipmentId) {
			CURRENT_EQUIPMENT_ID = equipmentId;
			updateSelectedMeta();
			renderFiltersFor(equipmentId);
			renderFluidsFor(equipmentId);
		}

		function buildRibbon() {
			var ribbon = document.getElementById('equipmentRibbon');
			if (!ribbon) return;
			ribbon.innerHTML = '';
			if (!INITIAL_EQUIPMENTS || !INITIAL_EQUIPMENTS.length) {
				ribbon.innerHTML = '<span style="color:#94a3b8;">No equipment found.</span>';
				return;
			}
			INITIAL_EQUIPMENTS.forEach(function(eq){
				var chip = document.createElement('button');
				chip.type = 'button';
				chip.className = 'equipment-chip';
				chip.dataset.eid = eq.equipment_id;
				chip.textContent = eq.number && eq.number !== '' ? eq.number : ('#' + eq.equipment_id);
				chip.addEventListener('click', function(){
					var prev = document.querySelector('.equipment-chip.is-selected');
					if (prev) prev.classList.remove('is-selected');
					chip.classList.add('is-selected');
					renderForEquipment(eq.equipment_id);
				});
				ribbon.appendChild(chip);
			});
			// Pre-select the first equipment on initial load
			var first = ribbon.querySelector('.equipment-chip');
			if (first) {
				first.click();
			}
		}

		function beginEditCell(td) {
			if (!td || td.classList.contains('editing')) return;
			var type = td.getAttribute('data-type') || 'text';
			var current = td.textContent.trim();
			if (current === '—') current = '';
			td.classList.add('editing');
			var input = document.createElement('input');
			input.className = 'editable-input';
			if (type === 'date') {
				input.type = 'date';
			} else if (type === 'number') {
				input.type = 'number';
				input.step = '0.1';
			} else {
				input.type = 'text';
			}
			input.value = current;
			td.innerHTML = '';
			td.appendChild(input);
			input.focus();
			input.addEventListener('blur', function(){
				finishEditCell(td, input, false);
			});
			input.addEventListener('keydown', function(evt){
				if (evt.key === 'Enter') {
					evt.preventDefault();
					finishEditCell(td, input, false);
				} else if (evt.key === 'Escape') {
					evt.preventDefault();
					finishEditCell(td, input, true);
				}
			});
		}

		function finishEditCell(td, input, cancel) {
			var original = input.defaultValue || '';
			var newVal = input.value.trim();
			var type = td.getAttribute('data-type') || 'text';
			if (cancel) {
				td.classList.remove('editing');
				td.innerHTML = formatCell(original);
				return;
			}
			if (type === 'number') {
				if (newVal === '') {
					alert('Hours value is required.');
					input.focus();
					return;
				}
				var num = parseFloat(newVal);
				if (isNaN(num)) {
					alert('Enter a valid number for hours.');
					input.focus();
					return;
				}
				newVal = String(num);
			}
			if (type === 'date' && newVal === '') {
				alert('Date is required.');
				input.focus();
				return;
			}

			td.classList.remove('editing');
			td.innerHTML = formatCell(newVal);

			var kind = td.getAttribute('data-kind');
			var field = td.getAttribute('data-field');
			var id = td.getAttribute('data-id');
			var equipId = td.getAttribute('data-equip');
			if (!id || !field || !kind || !equipId) return;

			if (kind === 'filter') {
				var list = INITIAL_FILTERS[equipId] || [];
				var found = null;
				list.forEach(function(item){ if (!found && Number(item.filter_id) === Number(id)) found = item; });
				if (found) {
					if (field === 'filter_date') found.filter_date = newVal;
					if (field === 'hours') found.hours = newVal;
				}
				if (!pendingFilterEdits[id]) {
					pendingFilterEdits[id] = {
						filter_id: Number(id),
						filter_date: found ? (found.filter_date || '') : '',
						hours: found ? String(found.hours) : ''
					};
				}
				if (field === 'filter_date') pendingFilterEdits[id].filter_date = newVal;
				if (field === 'hours') pendingFilterEdits[id].hours = newVal;
			} else if (kind === 'fluid') {
				var listF = INITIAL_FLUIDS[equipId] || [];
				var foundF = null;
				listF.forEach(function(item){ if (!foundF && Number(item.id) === Number(id)) foundF = item; });
				if (foundF) {
					if (field === 'reset_at') foundF.reset_at = newVal;
					if (field === 'current_hours') foundF.current_hours = newVal;
				}
				if (!pendingFluidEdits[id]) {
					pendingFluidEdits[id] = {
						id: Number(id),
						reset_at: foundF && foundF.reset_at ? String(foundF.reset_at).split(' ')[0] : '',
						current_hours: foundF ? String(foundF.current_hours) : ''
					};
				}
				if (field === 'reset_at') pendingFluidEdits[id].reset_at = newVal;
				if (field === 'current_hours') pendingFluidEdits[id].current_hours = newVal;
			}
		}

		function attachEditableHandlers() {
			document.addEventListener('click', function(evt){
				var td = evt.target.closest('td.editable-cell');
				if (!td) return;
				beginEditCell(td);
			});
		}

		function saveChanges() {
			var btn = document.getElementById('saveChangesBtn');
			if (!btn) return;
			var filters = Object.keys(pendingFilterEdits).map(function(k){ return pendingFilterEdits[k]; });
			var fluids = Object.keys(pendingFluidEdits).map(function(k){ return pendingFluidEdits[k]; });
			if (!filters.length && !fluids.length) {
				alert('No changes to save.');
				return;
			}
			btn.disabled = true;
			btn.textContent = 'Saving...';
			var payload = { filters: filters, fluids: fluids };
			var fd = new FormData();
			fd.append('payload', JSON.stringify(payload));
			fetch(API_BASE + 'api/save_addhours_changes.php', { method:'POST', body:fd, credentials:'same-origin' })
				.then(function(resp){ return resp.text().then(function(text){ try { return JSON.parse(text); } catch (e){ throw { type:'parse', text:text }; } }); })
				.then(function(json){
					if (!json || !json.success) throw new Error((json && json.error) ? json.error : 'Save failed');
					pendingFilterEdits = {};
					pendingFluidEdits = {};
					alert('Changes saved.');
				})
				.catch(function(err){
					console.error('Save changes error', err);
					if (err && err.type === 'parse') {
						alert('Error saving changes: invalid server response.');
						console.error('Raw response:', err.text);
					} else {
						alert('Error saving changes: ' + (err && err.message ? err.message : 'Unknown error'));
					}
				})
				.finally(function(){
					btn.disabled = false;
					btn.textContent = 'Save Changes';
				});
		}

		document.addEventListener('DOMContentLoaded', function(){
			buildRibbon();
			updateSelectedMeta();
			attachEditableHandlers();
			var saveBtn = document.getElementById('saveChangesBtn');
			if (saveBtn) saveBtn.addEventListener('click', saveChanges);
		});
	</script>
	<script src="../../assets/js/mobile-menu.js"></script>
	<script src="../../assets/js/logout-confirm.js"></script>
</body>
</html>

