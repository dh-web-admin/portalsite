<?php
require_once __DIR__ . '/../../session_init.php';

// Require login
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
	header('Location: /auth/login.php');
	exit();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';

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

// Selected equipment (optional)
$equipmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selectedEquipment = null;

// Fetch list for selector ribbon and dropdown
$equipments = [];
try {
	$r = $conn->query("SELECT equipment_id, COALESCE(dhss_equipment_number,'') AS number, COALESCE(type,'') AS type, COALESCE(current_hours,0) AS current_hours FROM equipments ORDER BY equipment_id ASC");
	if ($r) {
		while ($row = $r->fetch_assoc()) { $equipments[] = $row; if (!$selectedEquipment && $equipmentId > 0 && (int)$row['equipment_id'] === $equipmentId) $selectedEquipment = $row; }
		$r->free();
	}
} catch (Throwable $e) { /* ignore */ }

// If no equipment selected and equipments exist, select first one
if ($equipmentId === 0 && !empty($equipments)) {
	$equipmentId = (int)$equipments[0]['equipment_id'];
	$selectedEquipment = $equipments[0];
}

// Fetch parts for selected equipment (grouped by part name with makes)
$partsList = [];
if ($equipmentId > 0) {
	try {
		$stmt = $conn->prepare("
			SELECT ep.part_name, ep.nsn_number, ps.make, ps.model, ps.other_numbers, ps.supplier, ps.supplier_name, ps.supplier_number, ps.supplier_email, ps.supplier_address, ps.supplier_price, ep.quantity, ep.notes
			FROM equipment_parts ep
			LEFT JOIN part_specifications ps ON ep.part_name = ps.part_name
			WHERE ep.equipment_id = ?
			ORDER BY ep.part_name, ps.make
		");
		$stmt->bind_param('i', $equipmentId);
		$stmt->execute();
		$result = $stmt->get_result();
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				$partName = $row['part_name'];
				if (!isset($partsList[$partName])) {
					$partsList[$partName] = [
						'part_name' => $partName,
						'nsn_number' => isset($row['nsn_number']) ? $row['nsn_number'] : '',
						'quantity' => $row['quantity'],
						'notes' => $row['notes'],
						'makes' => []
					];
				}
				if ($row['make']) {
					$partsList[$partName]['makes'][] = [
						'make' => $row['make'],
						'model' => $row['model'],
						'other_numbers' => $row['other_numbers'],
						'supplier' => $row['supplier'],
						'supplier_name' => $row['supplier_name'],
						'supplier_number' => $row['supplier_number'],
						'supplier_email' => $row['supplier_email'],
						'supplier_address' => $row['supplier_address'],
						'supplier_price' => $row['supplier_price']
					];
				}
			}
		}
		$stmt->close();
	} catch (Throwable $e) { /* ignore */ }
}

function equipment_label($row) {
	if (!$row) return '';
	$label = !empty($row['number']) ? $row['number'] : ('#' . $row['equipment_id']);
	if (!empty($row['type'])) $label .= ' | ' . $row['type'];
	return $label;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Parts</title>
	<link rel="stylesheet" href="../../assets/css/base.css">
	<link rel="stylesheet" href="../../assets/css/admin-layout.css">
	<link rel="stylesheet" href="../../assets/css/dashboard.css">
	<style>
		.panel-wrapper { max-width:1200px; margin:0 auto; position:relative; }
		.page-heading { text-align:center; margin:6px 0 12px; }
		
		/* Parts Grid */
		.parts-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 260px)); gap:18px; margin-top:20px; justify-content:flex-start; }
		.part-card { background:#fff; border:1px solid #e6eef6; border-radius:12px; padding:16px; box-shadow:0 4px 12px rgba(2,6,23,0.06); transition:all 0.2s ease; position:relative; overflow:hidden; max-width:260px; text-align:left; }
		.part-card:hover { transform:translateY(-4px); box-shadow:0 8px 24px rgba(2,6,23,0.12); }
		.part-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
		.part-name { font-size:16px; font-weight:700; color:#0f172a; line-height:1.3; }
		.part-quantity { background:#eff6ff; color:#1e40af; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
		.makes-section { margin-top:6px; }
		.makes-table { width:100%; border-collapse:collapse; margin-top:4px; }
		.makes-table-header { font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#9ca3af; border-bottom:1px solid #e5e7eb; }
		.makes-table-row { font-size:14px; color:#0f172a; }
		.makes-table-cell { padding:3px 0; text-align:left; }
		.makes-table-cell.make-col { font-weight:700; }
		.makes-table-cell.part-col { color:#64748b; }
		.part-notes { margin-top:12px; padding:10px; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; font-size:13px; color:#78350f; font-style:italic; }
		.no-parts { text-align:center; padding:60px 20px; color:#94a3b8; font-size:15px; }
		.no-parts-icon { font-size:48px; margin-bottom:12px; opacity:0.4; }
		@media (max-width:768px) { .parts-grid { grid-template-columns:1fr; } }
		.page-heading h1 { margin:0; font-size:26px; letter-spacing:3px; font-weight:800; color:#0f172a; }
		.page-heading .subtitle { margin-top:6px; color:#6b7280; font-size:14px; }
		.equipment-back-btn-wrapper--top-left { margin-top:18px; margin-bottom:18px; }
		.equipment-back-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 18px; background:#2563eb; color:#fff; text-decoration:none; border-radius:8px; font-weight:600; font-size:14px; border:none; cursor:pointer; transition:background .2s ease, transform .1s ease; }
		.equipment-back-btn:hover { background:#1d4ed8; }
		.equipment-back-btn:active { transform:scale(0.98); }
		/* Ribbon */
		#equipmentRibbon { position:fixed; left:50%; transform:translateX(-50%); bottom:18px; z-index:999; background:rgba(255,255,255,0.96); padding:8px 12px; border-radius:999px; box-shadow:0 6px 20px rgba(2,6,23,0.08); display:flex; gap:8px; align-items:center; max-width:95%; overflow:auto; }
		.equipment-chip { padding:10px 14px; border-radius:999px; border:1px solid rgba(226,232,240,0.9); background:#f8fafc; cursor:pointer; font-size:14px; box-shadow:0 6px 18px rgba(2,6,23,0.05); color:#0f172a; transition:all .15s ease; white-space:nowrap; }
		.equipment-chip:hover { transform:translateY(-2px); box-shadow:0 10px 26px rgba(2,6,23,0.08); }
		.equipment-chip.is-selected { background:#2563eb; color:#fff; border-color:#1e40af; transform:translateY(-6px); box-shadow:0 14px 34px rgba(37,99,235,0.22); }
		/* Inline selector */
		.selector-row { display:flex; justify-content:center; align-items:center; gap:10px; margin:12px 0; }
		.selector-row select { padding:10px; border:1px solid #e6eef6; border-radius:8px; font-size:14px; min-width:280px; }
		.info-badge { display:inline-block; background:#fff; padding:8px 12px; border-radius:999px; border:1px solid #e6eef6; font-weight:700; color:#0f172a; font-size:14px; box-shadow:0 8px 22px rgba(2,6,23,0.05); }
		.card { background:#fff; border:1px solid #e6eef6; border-radius:10px; box-shadow:0 6px 18px rgba(2,6,23,0.04); padding:14px; margin-top:12px; }
	</style>
	<script>
		// Initial data
		var EQUIPMENTS = <?php echo json_encode($equipments ?: []); ?>;
		var SELECTED_ID = <?php echo (int)$equipmentId; ?>;
		function buildRibbon(targetPage){
			var ribbon = document.getElementById('equipmentRibbon'); if(!ribbon) return; ribbon.innerHTML='';
			if(!EQUIPMENTS || !EQUIPMENTS.length){ var note=document.createElement('div'); note.style.color='#64748b'; note.textContent='No equipments found'; ribbon.appendChild(note); return; }
			EQUIPMENTS.forEach(function(eq){
				var chip=document.createElement('button'); chip.type='button'; chip.className='equipment-chip'; chip.dataset.eid=eq.equipment_id;
				chip.textContent = (eq.number && eq.number !== '') ? eq.number : ('#'+eq.equipment_id);
				chip.addEventListener('click', function(){ window.location.href = targetPage + '?id=' + eq.equipment_id; });
				if (Number(eq.equipment_id) === Number(SELECTED_ID)) chip.classList.add('is-selected');
				ribbon.appendChild(chip);
			});
		}
		function initSelector(){
			var sel=document.getElementById('equipmentSelect'); if(!sel) return; sel.innerHTML='';
			var opt=document.createElement('option'); opt.value=''; opt.textContent='Select equipment…'; sel.appendChild(opt);
			(EQUIPMENTS||[]).forEach(function(eq){ var o=document.createElement('option'); o.value=eq.equipment_id; o.textContent=(eq.number && eq.number!=='')? eq.number : ('#'+eq.equipment_id); if(Number(eq.equipment_id)===Number(SELECTED_ID)) o.selected=true; sel.appendChild(o); });
			sel.addEventListener('change', function(){ if(this.value){ window.location.href = 'parts.php?id=' + encodeURIComponent(this.value); } });
		}
		document.addEventListener('DOMContentLoaded', function(){ buildRibbon('parts.php'); initSelector(); });
	</script>
</head>
<body class="admin-page">
<div class="admin-container">
	<?php include __DIR__ . '/../../partials/portalheader.php'; ?>
	<div class="admin-layout">
		<?php include __DIR__ . '/../../partials/sidebar.php'; ?>
		<main class="content-area">
			<div class="main-content">
				<div class="panel-wrapper">
					<div class="equipment-back-btn-wrapper equipment-back-btn-wrapper--top-left" style="text-align:left;">
						<a id="backBtn" href="index.php" class="equipment-back-btn"><span>Back ← </span></a>
					</div>
				</div>
				
				<?php if ($selectedEquipment): ?>
				<div class="page-heading">
					<h1>Part List for <?php echo htmlspecialchars(equipment_label($selectedEquipment)); ?></h1>
				</div>
				
				<div class="panel-wrapper" style="margin-top:12px;">
					<div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
						<button type="button" id="addPartBtn" class="btn" style="padding:10px 18px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;transition:background 0.2s ease;">+ Add Part</button>
					</div>				
				<?php if (empty($partsList)): ?>
				<div class="no-parts">
					No parts added yet. Click "Add Part" to get started.
				</div>
				<?php else: ?>
				<div class="parts-grid">
					<?php foreach ($partsList as $part): ?>
					<?php
						$cardMakes = [];
						if (!empty($part['makes'])) {
							foreach ($part['makes'] as $mk) {
								$cardMakes[] = [
									'make' => $mk['make'],
									'partNumber' => $mk['model'],
									'otherNumbers' => $mk['other_numbers'],
									'supplier' => isset($mk['supplier']) ? $mk['supplier'] : '',
									'supplierName' => isset($mk['supplier_name']) ? $mk['supplier_name'] : '',
									'supplierNumber' => isset($mk['supplier_number']) ? $mk['supplier_number'] : '',
									'supplierEmail' => isset($mk['supplier_email']) ? $mk['supplier_email'] : '',
									'supplierAddress' => isset($mk['supplier_address']) ? $mk['supplier_address'] : '',
									'supplierPrice' => isset($mk['supplier_price']) ? $mk['supplier_price'] : '',
								];
							}
						}
					?>
					<div class="part-card" data-part-name="<?php echo htmlspecialchars($part['part_name']); ?>" data-part-nsn="<?php echo htmlspecialchars($part['nsn_number']); ?>" data-part-makes='<?php echo json_encode($cardMakes); ?>'>
						<div class="part-header">
							<div class="part-name"><?php echo htmlspecialchars($part['part_name']); ?></div>
							<?php if (!empty($part['quantity']) && $part['quantity'] > 1): ?>
							<span class="part-quantity">Qty: <?php echo (int)$part['quantity']; ?></span>
							<?php endif; ?>
						</div>
					
						<?php if (!empty($part['makes'])): ?>
						<div class="makes-section">
							<table class="makes-table">
								<thead>
									<tr class="makes-table-header">
										<th class="makes-table-cell make-col" style="text-align:left;">Make</th>
										<th class="makes-table-cell part-col">Part Number</th>
									</tr>
								</thead>
								<tbody>
								<?php foreach ($part['makes'] as $make): ?>
									<tr class="makes-table-row">
										<td class="makes-table-cell make-col"><?php echo htmlspecialchars($make['make']); ?></td>
										<td class="makes-table-cell part-col"><?php echo !empty($make['model']) ? htmlspecialchars($make['model']) : ''; ?></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<?php endif; ?>
					
						<?php if (!empty($part['notes'])): ?>
						<div class="part-notes">
							💡 <?php echo htmlspecialchars($part['notes']); ?>
						</div>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>				</div>
			<?php else: ?>
			<div style="text-align:center;padding:80px 20px;color:#64748b;">
				<div style="font-size:48px;margin-bottom:16px;opacity:0.5;">🔧</div>
				<div style="font-size:18px;font-weight:600;margin-bottom:8px;color:#1e293b;">Select an Equipment</div>
				<div style="font-size:14px;">Choose an equipment from the list below to view and manage its parts</div>
			</div>
			<?php endif; ?>
		</div>
	</main>
</div>
	</div>
</div>

<!-- Add Part Modal -->
<div id="addPartModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.45);z-index:1200;align-items:center;justify-content:center;padding:20px;">
	<div style="background:#fff;border-radius:12px;padding:24px;max-width:600px;width:100%;box-shadow:0 16px 48px rgba(2,6,23,0.3);max-height:90vh;overflow-y:auto;">
		<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
			<h3 style="margin:0;font-size:20px;color:#1e293b;font-weight:700;">Add Part</h3>
			<button type="button" id="closeModalBtn" style="background:transparent;border:none;font-size:24px;color:#64748b;cursor:pointer;line-height:1;">&times;</button>
		</div>
		<form id="addPartForm" style="display:flex;flex-direction:column;gap:16px;">
			<input type="hidden" id="partEditMode" value="0" />
			<input type="hidden" id="originalPartName" value="" />
			<div style="display:flex;gap:12px;flex-wrap:wrap;">
				<div style="flex:1;min-width:220px;">
					<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Part Name *</label>
					<input type="text" id="partNumber" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
				</div>
				<div style="flex:1;min-width:220px;">
					<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">NSN Number</label>
					<input type="text" id="partNsn" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
				</div>
			</div>
			
			<div id="makesList" style="display:flex;flex-direction:column;gap:12px;">
				<div class="make-item" style="padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
					<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
						<span style="font-size:13px;font-weight:700;color:#0f172a;">Make #1</span>
						<button type="button" class="remove-make-btn" aria-label="Remove make" style="background:transparent;color:#ef4444;border:none;padding:0;font-size:16px;cursor:pointer;line-height:1;">&times;</button>
					</div>
					<div style="display:flex;gap:10px;">
						<div style="flex:1;">
							<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Make *</label>
							<input type="text" class="make-input" required style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
						</div>
						<div style="flex:1;">
							<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Part Number for this Make *</label>
							<input type="text" class="make-part-number" required style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
						</div>
					</div>
					<div style="margin-top:10px;">
						<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Other Numbers</label>
						<input type="text" class="make-other-numbers" placeholder="12345, 45657, 76876876" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
					</div>
					<div style="margin-top:10px;padding-top:10px;border-top:1px solid #cbd5e1;">
						<div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;margin-bottom:8px;" class="supplier-details-toggle">
							<div style="font-size:12px;font-weight:600;color:#0f172a;">Supplier Details:</div>
							<span style="font-size:14px;color:#475569;transition:transform 0.2s ease;transform:rotate(-90deg);" class="toggle-icon">▾</span>
						</div>
						<div style="display:none;" class="supplier-details-content">
							<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Supplier</label>
									<input type="text" class="make-supplier" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
								</div>
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Price</label>
									<div style="display:flex;align-items:center;gap:8px;border:1px solid #cbd5e1;border-radius:6px;padding:4px 8px;background:#fff;">
										<span style="color:#374151;font-weight:700;margin-right:4px;flex:0 0 auto;">$</span>
										<input type="text" class="make-supplier-price" placeholder="0.00" style="border:0;padding:6px 0;margin:0;background:transparent;flex:1 1 auto;font-size:14px;" />
									</div>
								</div>
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Name</label>
									<input type="text" class="make-supplier-name" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
								</div>
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Number</label>
									<input type="text" class="make-supplier-number" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
								</div>
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Email</label>
									<input type="text" class="make-supplier-email" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
								</div>
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Address</label>
									<input type="text" class="make-supplier-address" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			
			<div style="text-align:center;">
				<button type="button" id="addAnotherMakeBtn" style="padding:8px 16px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;color:#374151;font-weight:600;cursor:pointer;font-size:13px;transition:background 0.2s ease;">+ Add Another Make</button>
			</div>
			
			<div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;align-items:center;">
				<button type="button" id="deletePartBtn" style="display:none;padding:10px 16px;background:#ef4444;border:none;border-radius:8px;color:#fff;font-weight:600;cursor:pointer;">Delete</button>
				<button type="button" id="cancelModalBtn" style="padding:10px 18px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;color:#374151;font-weight:600;cursor:pointer;">Cancel</button>
				<button type="submit" style="padding:10px 18px;background:#2563eb;border:none;border-radius:8px;color:#fff;font-weight:600;cursor:pointer;">Save Part</button>
			</div>
		</form>
	</div>
</div>
<script src="../../assets/js/mobile-menu.js"></script>
<script>
// Add Part Modal Logic
document.addEventListener('DOMContentLoaded', function(){
	console.log('Modal script loaded');
	var modal = document.getElementById('addPartModal');
	var openBtn = document.getElementById('addPartBtn');
	var closeBtn = document.getElementById('closeModalBtn');
	var cancelBtn = document.getElementById('cancelModalBtn');
	var form = document.getElementById('addPartForm');
	var makesList = document.getElementById('makesList');
	var addMakeBtn = document.getElementById('addAnotherMakeBtn');
	var makeCounter = 1;
	var partCards = document.querySelectorAll('.part-card');
	var deleteBtn = document.getElementById('deletePartBtn');
	var editModeInput = document.getElementById('partEditMode');
	var originalPartNameInput = document.getElementById('originalPartName');
	var isEditMode = false;
	var originalPartName = '';
	
	console.log('Modal:', modal);
	console.log('Open button:', openBtn);
	
	if (openBtn && modal) {
		console.log('Adding click listener to button');
		openBtn.addEventListener('click', function(){
			console.log('Button clicked!');
			// Open in ADD mode
			isEditMode = false;
			originalPartName = '';
			if (editModeInput) editModeInput.value = '0';
			if (originalPartNameInput) originalPartNameInput.value = '';
			var titleEl = modal.querySelector('h3');
			if (titleEl) titleEl.textContent = 'Add Part';
			if (deleteBtn) deleteBtn.style.display = 'none';
			if (form) form.reset();
			modal.style.display = 'flex';
		});
	} else {
		console.log('Missing elements - openBtn:', !!openBtn, 'modal:', !!modal);
	}
	
	function closeModal(){
		if (modal) modal.style.display = 'none';
		if (form) form.reset();
		// Reset to single make item
		var items = makesList.querySelectorAll('.make-item');
		for (var i = 1; i < items.length; i++) {
			items[i].remove();
		}
		makeCounter = 1;
		isEditMode = false;
		originalPartName = '';
		if (editModeInput) editModeInput.value = '0';
		if (originalPartNameInput) originalPartNameInput.value = '';
		if (deleteBtn) deleteBtn.style.display = 'none';
	}
	
	if (closeBtn) closeBtn.addEventListener('click', closeModal);
	if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
	
	function deleteMakeSpecification(partName, makeVal, modelVal) {
		if (!partName || !makeVal || !modelVal) return;
		var data = new FormData();
		data.append('part_name', partName);
		data.append('make', makeVal);
		data.append('model', modelVal);
		fetch('../../api/delete_part_specification.php', {
			method: 'POST',
			body: data,
			credentials: 'same-origin'
		})
		.then(function(r){
			return r.text().then(function(text){
				try { return JSON.parse(text); }
				catch (err) { throw { type: 'parse', text: text, status: r.status }; }
			});
		})
		.then(function(json){
			if (!json || !json.success) throw new Error((json && json.message) ? json.message : 'Delete failed');
		})
		.catch(function(err){
			console.error('Delete make specification error', err);
		});
	}

	function bindMakeRemoveHandler(makeItem) {
		if (!makeItem) return;
		var removeBtn = makeItem.querySelector('.remove-make-btn');
		if (!removeBtn || removeBtn.getAttribute('data-bound') === '1') return;
		removeBtn.setAttribute('data-bound', '1');
		removeBtn.addEventListener('click', function(){
			if (isEditMode) {
				var partNameField = document.getElementById('partNumber');
				var partName = (originalPartName || (partNameField ? partNameField.value.trim() : ''));
				var makeVal = '';
				var modelVal = '';
				var makeInput = makeItem.querySelector('.make-input');
				var modelInput = makeItem.querySelector('.make-part-number');
				if (makeInput) makeVal = makeInput.value.trim();
				if (modelInput) modelVal = modelInput.value.trim();
				deleteMakeSpecification(partName, makeVal, modelVal);
			}
			makeItem.remove();
		});
	}

	if (makesList) {
		makesList.addEventListener('click', function(e){
			var btn = e.target.closest('.remove-make-btn');
			if (!btn) return;
			var makeItem = btn.closest('.make-item');
			if (!makeItem) return;
			if (isEditMode) {
				var partNameField = document.getElementById('partNumber');
				var partName = (originalPartName || (partNameField ? partNameField.value.trim() : ''));
				var makeVal = '';
				var modelVal = '';
				var makeInput = makeItem.querySelector('.make-input');
				var modelInput = makeItem.querySelector('.make-part-number');
				if (makeInput) makeVal = makeInput.value.trim();
				if (modelInput) modelVal = modelInput.value.trim();
				deleteMakeSpecification(partName, makeVal, modelVal);
			}
			makeItem.remove();
		});
	}
	
	// Add collapse/expand functionality to supplier details
	function initSupplierDetailsToggle() {
		var toggles = document.querySelectorAll('.supplier-details-toggle');
		toggles.forEach(function(toggle) {
			if (toggle.getAttribute('data-bound') === '1') return;
			toggle.setAttribute('data-bound', '1');
			toggle.addEventListener('click', function() {
				var content = this.nextElementSibling;
				var icon = this.querySelector('.toggle-icon');
				if (content) {
					var isVisible = content.style.display !== 'none';
					content.style.display = isVisible ? 'none' : 'block';
					if (icon) {
						icon.style.transform = isVisible ? 'rotate(-90deg)' : 'rotate(0deg)';
					}
				}
			});
		});
	}
	
	// Initialize toggles on modal open
	var origOpenBtn = openBtn;
	if (openBtn) {
		openBtn.addEventListener('click', function() {
			setTimeout(function() { initSupplierDetailsToggle(); }, 100);
		});
	}
	
	if (modal) {
		modal.addEventListener('click', function(e){
			if (e.target === modal) closeModal();
		});
	}
	
	if (addMakeBtn) {
		addMakeBtn.addEventListener('click', function(){
			makeCounter++;
			var makeItem = document.createElement('div');
			makeItem.className = 'make-item';
			makeItem.style.cssText = 'padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;position:relative;';
			makeItem.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">' +
				'<span style="font-size:13px;font-weight:700;color:#0f172a;">Make #' + makeCounter + '</span>' +
				'<button type="button" class="remove-make-btn" aria-label="Remove make" style="background:transparent;color:#ef4444;border:none;padding:0;font-size:16px;cursor:pointer;line-height:1;">&times;</button>' +
			'</div>' +
			'<div style="display:flex;gap:10px;">' +
				'<div style="flex:1;"><label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Make *</label>' +
				'<input type="text" class="make-input" required style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" /></div>' +
				'<div style="flex:1;"><label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Part Number for this Make *</label>' +
				'<input type="text" class="make-part-number" required style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" /></div>' +
			'</div>' +
			'<div style="margin-top:10px;">' +
				'<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Other Numbers</label>' +
				'<input type="text" class="make-other-numbers" placeholder="12345, 45657, 76876876" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />' +
			'</div>' +
			'<div style="margin-top:10px;padding-top:10px;border-top:1px solid #cbd5e1;">' +
				'<div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;margin-bottom:8px;" class="supplier-details-toggle">' +
				'<div style="font-size:12px;font-weight:600;color:#0f172a;">Supplier Details:</div>' +
				'<span style="font-size:14px;color:#475569;transition:transform 0.2s ease;transform:rotate(-90deg);" class="toggle-icon">▾</span>' +
				'</div>' +
				'<div style="display:none;" class="supplier-details-content">' +
				'<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">' +
					'<div><label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Supplier</label>' +
					'<input type="text" class="make-supplier" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" /></div>' +
					'<div><label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Price</label>' +
					'<div style="display:flex;align-items:center;gap:8px;border:1px solid #cbd5e1;border-radius:6px;padding:4px 8px;background:#fff;">' +
					'<span style="color:#374151;font-weight:700;margin-right:4px;flex:0 0 auto;">$</span>' +
					'<input type="text" class="make-supplier-price" placeholder="0.00" style="border:0;padding:6px 0;margin:0;background:transparent;flex:1 1 auto;font-size:14px;" /></div></div>' +
					'<div><label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Name</label>' +
					'<input type="text" class="make-supplier-name" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" /></div>' +
					'<div><label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Number</label>' +
					'<input type="text" class="make-supplier-number" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" /></div>' +
					'<div><label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Email</label>' +
					'<input type="text" class="make-supplier-email" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" /></div>' +
					'<div><label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Address</label>' +
					'<input type="text" class="make-supplier-address" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" /></div>' +
				'</div>' +
				'</div>' +
			'</div>';
			
			makesList.appendChild(makeItem);
			
			// Initialize toggle for the newly added make item
			initSupplierDetailsToggle();
			
			// Add remove handler
			bindMakeRemoveHandler(makeItem);
		});
	}

	// Bind remove handler for the initial make item
	bindMakeRemoveHandler(makesList ? makesList.querySelector('.make-item') : null);

	// Open modal in "edit" mode when clicking an existing part card
	if (partCards && partCards.length && modal) {
		partCards.forEach(function(card){
			card.addEventListener('click', function(){
				var partName = card.getAttribute('data-part-name') || '';
				var partNsn = card.getAttribute('data-part-nsn') || '';
				var makesJson = card.getAttribute('data-part-makes') || '[]';
				var parsedMakes = [];
				try { parsedMakes = JSON.parse(makesJson); } catch(e) { parsedMakes = []; }

				// Mark edit mode
				isEditMode = true;
				originalPartName = partName;
				if (editModeInput) editModeInput.value = '1';
				if (originalPartNameInput) originalPartNameInput.value = partName;

				// Open modal
				modal.style.display = 'flex';
				
				// Initialize toggle functionality
				setTimeout(function() { initSupplierDetailsToggle(); }, 100);

				// Set title to Edit Part
				var titleEl = modal.querySelector('h3');
				if (titleEl) titleEl.textContent = 'Edit Part';
				if (deleteBtn) deleteBtn.style.display = 'inline-flex';

				// Reset existing fields
				if (form) form.reset();
				var items = makesList.querySelectorAll('.make-item');
				for (var i = 1; i < items.length; i++) { items[i].remove(); }
				makeCounter = 1;

				// Fill in part name
				var partInput = document.getElementById('partNumber');
				if (partInput) partInput.value = partName;
				var nsnInput = document.getElementById('partNsn');
				if (nsnInput) nsnInput.value = partNsn;

				// Fill in makes
				if (parsedMakes && parsedMakes.length) {
					// First make goes into the initial block
					var first = parsedMakes[0];
					var firstItem = makesList.querySelector('.make-item');
					if (firstItem) {
						var mi = firstItem.querySelector('.make-input');
						var pn = firstItem.querySelector('.make-part-number');
						var on = firstItem.querySelector('.make-other-numbers');
						var sup = firstItem.querySelector('.make-supplier');
						var sname = firstItem.querySelector('.make-supplier-name');
						var snum = firstItem.querySelector('.make-supplier-number');
						var semail = firstItem.querySelector('.make-supplier-email');
						var saddr = firstItem.querySelector('.make-supplier-address');
						var sprice = firstItem.querySelector('.make-supplier-price');
						if (mi) mi.value = first.make || '';
						if (pn) pn.value = first.partNumber || '';
						if (on) on.value = first.otherNumbers || '';
						if (sup) sup.value = first.supplier || '';
						if (sname) sname.value = first.supplierName || '';
						if (snum) snum.value = first.supplierNumber || '';
						if (semail) semail.value = first.supplierEmail || '';
						if (saddr) saddr.value = first.supplierAddress || '';
						if (sprice) sprice.value = first.supplierPrice || '';
					}

					// Remaining makes: add extra blocks
					for (var j = 1; j < parsedMakes.length; j++) {
						addMakeBtn.click();
						var allItems = makesList.querySelectorAll('.make-item');
						var last = allItems[allItems.length - 1];
						var m2 = parsedMakes[j];
						if (last) {
							var mi2 = last.querySelector('.make-input');
							var pn2 = last.querySelector('.make-part-number');
							var on2 = last.querySelector('.make-other-numbers');
							var sup2 = last.querySelector('.make-supplier');
							var sname2 = last.querySelector('.make-supplier-name');
							var snum2 = last.querySelector('.make-supplier-number');
							var semail2 = last.querySelector('.make-supplier-email');
							var saddr2 = last.querySelector('.make-supplier-address');
							var sprice2 = last.querySelector('.make-supplier-price');
							if (mi2) mi2.value = m2.make || '';
							if (pn2) pn2.value = m2.partNumber || '';
							if (on2) on2.value = m2.otherNumbers || '';
							if (sup2) sup2.value = m2.supplier || '';
							if (sname2) sname2.value = m2.supplierName || '';
							if (snum2) snum2.value = m2.supplierNumber || '';
							if (semail2) semail2.value = m2.supplierEmail || '';
							if (saddr2) saddr2.value = m2.supplierAddress || '';
							if (sprice2) sprice2.value = m2.supplierPrice || '';
						}
					}
				}
				
				// Re-initialize toggles after all makes have been added and populated
				initSupplierDetailsToggle();
			});
		});
	}

	// Delete button handler (only in edit mode)
	if (deleteBtn) {
		deleteBtn.addEventListener('click', function(){
			if (!isEditMode) return;
			var targetName = originalPartName || (document.getElementById('partNumber') ? document.getElementById('partNumber').value.trim() : '');
			if (!targetName) {
				alert('Missing part name to delete.');
				return;
			}
			if (!confirm('Delete this part for this equipment?')) return;
			deleteBtn.disabled = true;
			deleteBtn.textContent = 'Deleting...';
			var fd = new FormData();
			fd.append('equipment_id', <?php echo (int)($equipmentId ?: 0); ?>);
			fd.append('part_name', targetName);
			fetch('../../api/delete_equipment_part.php', {
				method: 'POST',
				body: fd,
				credentials: 'same-origin'
			})
			.then(function(r){ return r.json(); })
			.then(function(data){
				if (data && data.success) {
					closeModal();
					window.location.reload();
				} else {
					alert('Failed to delete part: ' + (data && data.message ? data.message : 'Unknown error'));
				}
			})
			.catch(function(err){
				console.error(err);
				alert('Network error while deleting part.');
			})
			.finally(function(){
				deleteBtn.disabled = false;
				deleteBtn.textContent = 'Delete';
			});
		});
	}
	
	if (form) {
		form.addEventListener('submit', function(e){
			e.preventDefault();
			
			var partNumber = document.getElementById('partNumber').value.trim();
			var partNsn = (document.getElementById('partNsn') || {}).value?.trim() || '';
			var makes = [];
			
			var makeItems = makesList.querySelectorAll('.make-item');
			makeItems.forEach(function(item){
				var makeInput = item.querySelector('.make-input');
				var partInput = item.querySelector('.make-part-number');
				var otherInput = item.querySelector('.make-other-numbers');
				if (makeInput && partInput && makeInput.value.trim() && partInput.value.trim()) {
					var priceVal = (item.querySelector('.make-supplier-price') || {}).value?.trim() || '';
					// Sanitize price: remove commas but keep decimal
					priceVal = priceVal.replace(/,/g, '');
					if (priceVal === '') {
						priceVal = null;
					}
					var otherVal = otherInput ? otherInput.value.trim() : '';
					if (otherVal) {
						otherVal = otherVal
							.split(',')
							.map(function(v){ return v.trim(); })
							.filter(function(v){ return v.length > 0; })
							.join(', ');
					}
					makes.push({
						make: makeInput.value.trim(),
						partNumber: partInput.value.trim(),
						otherNumbers: otherVal,
						supplier: (item.querySelector('.make-supplier') || {}).value?.trim() || '',
						supplierName: (item.querySelector('.make-supplier-name') || {}).value?.trim() || '',
						supplierNumber: (item.querySelector('.make-supplier-number') || {}).value?.trim() || '',
						supplierEmail: (item.querySelector('.make-supplier-email') || {}).value?.trim() || '',
						supplierAddress: (item.querySelector('.make-supplier-address') || {}).value?.trim() || '',
						supplierPrice: priceVal
					});
				}
			});
			
			if (!partNumber || makes.length === 0) {
				alert('Please fill in all required fields');
				return;
			}
			
			var submitBtn = form.querySelector('button[type="submit"]');
			if (submitBtn) {
				submitBtn.disabled = true;
				submitBtn.textContent = 'Saving...';
			}
			
			var formData = new FormData();
			formData.append('equipment_id', <?php echo (int)($equipmentId ?: 0); ?>);
			formData.append('part_number', partNumber);
			formData.append('nsn_number', partNsn);
			formData.append('quantity', 1);
			formData.append('notes', '');
			formData.append('makes', JSON.stringify(makes));
			// Pass edit metadata so the API can avoid duplicates when updating
			formData.append('edit_mode', isEditMode ? '1' : '0');
			formData.append('original_part_name', originalPartName || '');
			
			fetch('../../api/add_equipment_part.php', {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
			.then(function(response){ return response.json(); })
			.then(function(data){
				if (data.success) {
					alert(isEditMode ? 'Part updated successfully!' : 'Part added successfully!');
					closeModal();
					window.location.reload();
				} else {
					alert('Error: ' + (data.error || 'Failed to add part'));
				}
			})
			.catch(function(error){
				console.error('Error:', error);
				alert('Failed to add part. Please try again.');
			})
			.finally(function(){
				if (submitBtn) {
					submitBtn.disabled = false;
					submitBtn.textContent = 'Save Part';
				}
			});
		});
	}
});

// Back button behavior like Tires page
(function(){
	var backBtn = document.getElementById('backBtn');
	if (!backBtn) return;
	backBtn.addEventListener('click', function(e){
		try {
			var ref = document.referrer || '';
			var isPrevSame = ref && ref.indexOf(location.origin) === 0 && /\/pages\/equipments\//.test(ref);
			if (isPrevSame) return; // let anchor navigate to index
			if (ref && ref.indexOf(location.origin) === 0) { e.preventDefault(); history.back(); }
		} catch(_){}
	});
})();

// Bottom equipment selector ribbon (same UX as Tires page)
(function(){
	var allEquip = <?php echo json_encode($equipments ?: []); ?>;
	var currentId = <?php echo (int)$equipmentId; ?>;
	var container = document.createElement('div');
	container.id = 'equipmentRibbon';
	container.style.position='fixed';
	container.style.left='50%';
	container.style.transform='translateX(-50%)';
	container.style.bottom='18px';
	container.style.zIndex='999';
	container.style.background='rgba(255,255,255,0.96)';
	container.style.padding='8px 12px';
	container.style.borderRadius='999px';
	container.style.boxShadow='0 6px 20px rgba(2,6,23,0.08)';
	container.style.display='flex';
	container.style.gap='8px';
	container.style.alignItems='center';
	container.style.maxWidth='95%';
	container.style.overflow='auto';
	document.body.appendChild(container);

	var style = document.createElement('style');
	style.textContent = '.equipment-chip{padding:10px 14px;border-radius:999px;border:1px solid rgba(226,232,240,0.9);background:#f8fafc;cursor:pointer;font-size:14px;box-shadow:0 6px 18px rgba(2,6,23,0.05);color:#0f172a;transition:all .15s ease;} .equipment-chip:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(2,6,23,0.08);} .equipment-chip.is-selected{background:#2563eb;color:#fff;border-color:#1e40af;transform:translateY(-6px);box-shadow:0 14px 34px rgba(37,99,235,0.22);}';
	document.head.appendChild(style);

	function buildRibbon(){
		var ribbon = document.getElementById('equipmentRibbon');
		if (!ribbon) return;
		ribbon.innerHTML='';
		if (!allEquip || !allEquip.length){ var note=document.createElement('div'); note.style.color='#64748b'; note.textContent='No equipments found'; ribbon.appendChild(note); return; }
		allEquip.forEach(function(eq){
			var chip=document.createElement('button');
			chip.type='button'; chip.className='equipment-chip'; chip.dataset.eid=eq.equipment_id; chip.style.whiteSpace='nowrap';
			chip.textContent=(eq.number && eq.number!=='') ? eq.number : ('#'+eq.equipment_id);
			chip.addEventListener('click', function(){ window.location.href = 'parts.php?id=' + eq.equipment_id; });
			if (Number(eq.equipment_id) === Number(currentId)) chip.classList.add('is-selected');
			ribbon.appendChild(chip);
		});
	}
	document.addEventListener('DOMContentLoaded', buildRibbon);
})();
</script>
</body>
</html>