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

// Load permissions
require_once __DIR__ . '/../../partials/permissions.php';
$hasEditPermission = can_edit_page('engineering');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
	<meta name="theme-color" content="#667eea" />
	<title>Engineering</title>
	<link rel="stylesheet" href="../../assets/css/base.css" />
	<link rel="stylesheet" href="../../assets/css/admin-layout.css" />
	<link rel="stylesheet" href="../../assets/css/dashboard.css" />
	<style>
		/* Parts Grid Styles */
		.parts-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 260px)); gap:18px; margin-top:20px; justify-content:flex-start; }
		.part-card { background:#fff; border:1px solid #e6eef6; border-radius:12px; padding:16px; box-shadow:0 4px 12px rgba(2,6,23,0.06); transition:all 0.2s ease; position:relative; overflow:hidden; max-width:260px; text-align:left; cursor:pointer; }
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
		.supplier-suggest { position:absolute; z-index:2000; background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 12px 30px rgba(2,6,23,0.12); overflow:hidden; max-height:300px; overflow-y:auto; }
		.supplier-suggest .row { padding:8px 10px; cursor:pointer; font-size:13px; color:#0f172a; }
		.supplier-suggest .row:hover { background:#f8fafc; }
		.supplier-profile-link { display:inline-block; margin-top:6px; font-size:12px; font-weight:700; color:#0f5a8a; text-decoration:underline; }
	</style>
</head>
<body class="admin-page">
	<script>
	// Pass edit permission to JS as a global boolean - using the same can_edit_page() function
	window.hasEditEngineeringPermission = <?php echo $hasEditPermission ? 'true' : 'false'; ?>;
	console.log('=== PERMISSION DEBUG ===');
	console.log('Edit permission value:', window.hasEditEngineeringPermission);
	console.log('Edit permission type:', typeof window.hasEditEngineeringPermission);
	console.log('======================');
	</script>
	<div class="admin-container">
		<?php include __DIR__ . '/../../partials/portalheader.php'; ?>
		<div class="admin-layout">
			<?php include __DIR__ . '/../../partials/sidebar.php'; ?>
			<main class="content-area">
				<div class="main-content">
					<div style="display: flex; gap: 12px; margin-bottom: 24px; margin-top: 28px; justify-content: space-between; align-items: center;">
						<div style="display: flex; gap: 12px;">
							<?php if ($hasEditPermission) { ?>
							<button type="button" style="padding: 8px 18px; background: #5b7fa3; color: #fff; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Build new</button>
							<button id="addItemBtn" type="button" style="padding: 8px 18px; background: #5b7fa3; color: #fff; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Add Item</button>
							<?php if (isset($_SESSION['user_permissions']) && in_array('edit_engineering', $_SESSION['user_permissions'])): ?>
							<div style="position: absolute; top: 20px; right: 20px;">
								<a href="#" id="editButton"><img src="/assets/icons/edit.svg" alt="Edit" style="width: 24px; height: 24px;"></a>
							</div>
							<?php endif; ?>
							<?php } ?>
						</div>
						<a id="viewEquipmentsBtn" href="/pages/equipments/index.php" target="_blank" rel="noopener noreferrer" style="padding: 8px 18px; background: #4ca3af; color: #fff; border: none; border-radius: 4px; font-weight: bold; text-decoration: none; display: inline-block;">View equipments</a>
					</div>
					<hr style="border: none; border-top: 2px solid #b0b8c1; margin: 0 0 28px 0; width: 100%; box-shadow: 0 1px 4px #e0e4ea;" />
					<div style="display: flex; flex-direction: row; gap: 32px; margin-bottom: 24px; align-items: flex-start;">
						<div id="itemPanel" style="background: #f5f7fa; border: 1.5px solid #d1d5db; border-radius: 12px; padding: 24px 18px; min-width: 210px; max-width: 260px; width: 240px; height: calc(100vh - 220px); box-sizing: border-box; box-shadow: 2px 0 8px #e0e4ea; overflow-y: auto; overflow-x: hidden; display: flex; flex-direction: column; align-items: flex-start; gap: 10px; flex-shrink: 0; scrollbar-width: thin; scrollbar-color: #a2a9b3 #f5f7fa;">
							<div id="itemList" style="display: flex; flex-direction: column; align-items: flex-start; gap: 10px; width: 100%;"></div>
						</div>
						<div id="itemDetails" style="flex: 1; padding-top: 10px; min-width: 0;"></div>
					</div>
					<!-- Modal for Add Item -->
					<div id="addItemModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1000; align-items:center; justify-content:center;">
						<div style="background:#fff; border-radius:8px; box-shadow:0 2px 16px #b0b8c1; padding:32px 24px; min-width:320px; max-width:90vw;">
							<h3 style="margin-top:0; margin-bottom:18px; font-size:1.2em;">Add Item</h3>
							<input id="itemNameInput" type="text" placeholder="Enter item name" style="width:100%; padding:8px; margin-bottom:18px; border-radius:4px; border:1px solid #b0b8c1; font-size:1em;" />
							<div style="display:flex; gap:12px; justify-content:flex-end;">
								<button id="saveItemBtn" style="padding:8px 18px; background:#5b7fa3; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Save</button>
								<button id="cancelItemBtn" style="padding:8px 18px; background:#b0b8c1; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Cancel</button>
							</div>
						</div>
					</div>
					<!-- Modal for Upload Drawings -->
					<div id="uploadDrawingsModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1000; align-items:center; justify-content:center;">
						<div style="background:#fff; border-radius:8px; box-shadow:0 2px 16px #b0b8c1; padding:32px 24px; min-width:420px; max-width:90vw;">
							<h3 style="margin-top:0; margin-bottom:18px; font-size:1.2em;">Upload Drawings</h3>
							<div style="margin-bottom:18px;">
								<label style="display:block; margin-bottom:6px; font-weight:500;">Select Files (multiple allowed)</label>
								<input id="drawingFilesInput" type="file" multiple accept=".pdf,.dwg,.dxf,.png,.jpg,.jpeg" style="width:100%; padding:8px; border-radius:4px; border:1px solid #b0b8c1;" />
								<div id="selectedFilesPreview" style="margin-top:8px; font-size:0.9em; color:#666;"></div>
							</div>
							<div style="display:flex; gap:12px; justify-content:flex-end;">
								<button id="uploadDrawingsBtn" style="padding:8px 18px; background:#5b7fa3; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Upload</button>
								<button id="cancelUploadBtn" style="padding:8px 18px; background:#b0b8c1; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Cancel</button>
							</div>
						</div>
					</div>
					<!-- Modal for Upload BOM -->
					<div id="uploadBomModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1000; align-items:center; justify-content:center;">
						<div style="background:#fff; border-radius:8px; box-shadow:0 2px 16px #b0b8c1; padding:28px 24px; min-width:580px; max-width:92vw;">
							<h3 style="margin-top:0; margin-bottom:16px; font-size:1.15em;">Upload Bill of Materials</h3>
							<div style="display:flex; gap:10px; margin-bottom:8px;">
								<span style="flex:2; font-size:0.82em; font-weight:600; color:#64748b; padding-left:2px;">Document Name</span>
								<span style="flex:3; font-size:0.82em; font-weight:600; color:#64748b; padding-left:2px;">File</span>
								<span style="width:28px;"></span>
							</div>
							<div id="bomUploadRows" style="display:flex; flex-direction:column; gap:8px; margin-bottom:10px;"></div>
							<button type="button" id="addBomRowBtn" style="padding:5px 14px; background:transparent; color:#5b7fa3; border:1px dashed #5b7fa3; border-radius:4px; font-weight:600; cursor:pointer; font-size:0.88em; margin-bottom:18px;">+ Add another material</button>
							<div style="display:flex; gap:12px; justify-content:flex-end;">
								<button id="uploadBomBtn" style="padding:8px 18px; background:#5b7fa3; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Upload</button>
								<button id="cancelUploadBomBtn" style="padding:8px 18px; background:#b0b8c1; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Cancel</button>
							</div>
						</div>
					</div>
					<!-- Modal for Add/Edit Part -->
					<div id="addPartModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.45);z-index:1200;align-items:center;justify-content:center;padding:20px;">
						<div style="background:#fff;border-radius:12px;padding:24px;max-width:600px;width:100%;box-shadow:0 16px 48px rgba(2,6,23,0.3);max-height:90vh;overflow-y:auto;">
							<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
								<h3 style="margin:0;font-size:20px;color:#1e293b;font-weight:700;">Add Part</h3>
								<button type="button" id="closePartModalBtn" style="background:transparent;border:none;font-size:24px;color:#64748b;cursor:pointer;line-height:1;">&times;</button>
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
									<button type="button" id="cancelPartModalBtn" style="padding:10px 18px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;color:#374151;font-weight:600;cursor:pointer;">Cancel</button>
									<button type="submit" style="padding:10px 18px;background:#2563eb;border:none;border-radius:8px;color:#fff;font-weight:600;cursor:pointer;">Save Part</button>
								</div>
							</form>
						</div>
					</div>
					<style>
    #itemPanel::-webkit-scrollbar {
        width: 8px;
    }
    #itemPanel::-webkit-scrollbar-thumb {
        background: #a2a9b3;
        border-radius: 8px;
    }
    #itemPanel::-webkit-scrollbar-track {
        background: #f5f7fa;
    }
    #itemPanel {
        scrollbar-width: thin;
        scrollbar-color: #a2a9b3 #f5f7fa;
    }
</style>
					<script>
					// Modal logic for Add Item
					(function(){
						var apiBase = window.location.hostname === 'localhost' ? '/PortalSite/api' : '/api';
						var addBtn = document.getElementById('addItemBtn');
						var modal = document.getElementById('addItemModal');
						var saveBtn = document.getElementById('saveItemBtn');
						var cancelBtn = document.getElementById('cancelItemBtn');
						var input = document.getElementById('itemNameInput');
						var itemList = document.getElementById('itemList');
						var items = [];

						// Fetch items from backend on load
						function fetchItems() {
							fetch(apiBase + '/get_engineering_items.php')
								.then(function(res) { return res.json(); })
								.then(function(data) {
									if (data.success && Array.isArray(data.items)) {
										items = data.items; // keep id and name
										renderItems();
									}
								});
						}
						fetchItems();

						if (addBtn) {
							addBtn.addEventListener('click', function(){
								input.value = '';
								modal.style.display = 'flex';
								input.focus();
							});
						}
						
						cancelBtn && cancelBtn.addEventListener('click', function(){
							modal.style.display = 'none';
						});
						
						saveBtn && saveBtn.addEventListener('click', function(){
							var name = input.value.trim();
							if(name){
								saveItemToBackend(name);
							} else {
								input.focus();
							}
						});

						function saveItemToBackend(name) {
							fetch(apiBase + '/add_engineering_item.php', {
								method: 'POST',
								headers: { 'Content-Type': 'application/json' },
								body: JSON.stringify({ name: name })
							})
							.then(function(res) { return res.json(); })
							.then(function(data) {
								if (data.success) {
									modal.style.display = 'none';
									fetchItems();
								} else {
									alert(data.message || 'Failed to save item');
								}
							})
							.catch(function() {
								alert('Failed to save item');
							});
						}

						// Edit modal for renaming/deleting item
						var editModal = document.createElement('div');
						editModal.id = 'editItemModal';
						editModal.style.display = 'none';
						editModal.style.position = 'fixed';
						editModal.style.top = '0';
						editModal.style.left = '0';
						editModal.style.width = '100vw';
						editModal.style.height = '100vh';
						editModal.style.background = 'rgba(0,0,0,0.18)';
						editModal.style.zIndex = '1001';
						editModal.style.alignItems = 'center';
						editModal.style.justifyContent = 'center';
						editModal.innerHTML = '<div style="background:#fff; border-radius:8px; box-shadow:0 2px 16px #b0b8c1; padding:32px 24px; min-width:320px; max-width:90vw; display:flex; flex-direction:column; gap:18px;">'
							+ '<h3 style="margin:0 0 12px 0; font-size:1.1em;">Edit Item</h3>'
							+ '<input id="editItemNameInput" type="text" style="width:100%; padding:8px; border-radius:4px; border:1px solid #b0b8c1; font-size:1em; margin-bottom:8px;" />'
							+ '<div style="display:flex; gap:12px; justify-content:flex-end;">'
							+ '<button id="saveEditItemBtn" style="padding:8px 18px; background:#5b7fa3; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Save</button>'
							+ '<button id="deleteEditItemBtn" style="padding:8px 18px; background:#e57373; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Delete</button>'
							+ '<button id="cancelEditItemBtn" style="padding:8px 18px; background:#b0b8c1; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Cancel</button>'
							+ '</div>'
							+ '</div>';
						document.body.appendChild(editModal);

						var selectedItem = null;
						var selectedItemId = null;

						function setItemCardStyle(card, isSelected, isHover) {
							if (isSelected) {
								card.style.background = isHover ? '#6e88a3' : '#5b7fa3';
								card.style.color = '#fff';
								card.style.boxShadow = '0 2px 8px rgba(91,127,163,0.28)';
							} else {
								card.style.background = isHover ? '#a2a9b3' : '#b0b8c1';
								card.style.color = '#222';
								card.style.boxShadow = 'none';
							}
						}

						function refreshSelectedItemStyles() {
							Array.prototype.forEach.call(itemList.children, function(card) {
								var isSelected = selectedItemId !== null && String(selectedItemId) === card.getAttribute('data-item-id');
								setItemCardStyle(card, isSelected, false);
							});
						}

						function renderItems(){
							itemList.innerHTML = '';
							var itemDetails = document.getElementById('itemDetails');
							itemList.innerHTML = '';
							itemDetails.innerHTML = '';
							selectedItem = null;
							var hasSelectedInList = false;
							// Sort items alphabetically by name
							var sortedItems = items.slice().sort(function(a, b) {
								return a.name.localeCompare(b.name);
							});
							sortedItems.forEach(function(item, idx){
								var div = document.createElement('div');
								div.setAttribute('data-item-id', String(item.id));
								div.textContent = item.name;
								div.style.padding = '8px 22px';
								div.style.borderRadius = '16px';
								div.style.fontWeight = 'bold';
								div.style.fontSize = '1em';
								div.style.display = 'inline-block';
								div.style.width = '22ch';
								div.style.textAlign = 'left';
								div.style.paddingLeft = '16px';
								div.style.cursor = 'pointer';
								div.style.marginBottom = '18px';
								var isSelected = selectedItemId !== null && item.id === selectedItemId;
								setItemCardStyle(div, isSelected, false);
								if (isSelected) {
									hasSelectedInList = true;
									selectedItem = item;
								}
								div.addEventListener('mouseenter', function() {
									var hoverSelected = selectedItemId !== null && item.id === selectedItemId;
									setItemCardStyle(div, hoverSelected, true);
								});
								div.addEventListener('mouseleave', function() {
									var leaveSelected = selectedItemId !== null && item.id === selectedItemId;
									setItemCardStyle(div, leaveSelected, false);
								});
								div.addEventListener('click', function() {
									selectedItem = item;
									selectedItemId = item.id;
									refreshSelectedItemStyles();
									showDetails(item);
								});
								itemList.appendChild(div);
								if (!hasSelectedInList && idx === 0) {
									selectedItem = item;
									selectedItemId = item.id;
								}
							});
							refreshSelectedItemStyles();
							if (selectedItem) showDetails(selectedItem);
						}

						function showDetails(item) {
							console.log('=== showDetails called ===');
							console.log('Item:', item);
							console.log('Permission check:', window.hasEditEngineeringPermission);
							
							var itemDetails = document.getElementById('itemDetails');
							itemDetails.innerHTML = '';
							var wrapper = document.createElement('div');
							wrapper.style.textAlign = 'left';
							wrapper.style.marginLeft = '0';
							wrapper.style.width = '100%';
							wrapper.style.maxWidth = '100%';
							
							var titleRow = document.createElement('div');
							titleRow.style.display = 'flex';
							titleRow.style.alignItems = 'center';
							titleRow.style.justifyContent = 'space-between';
							titleRow.style.marginBottom = '24px'; // Increased gap from 12px to 24px
							titleRow.style.gap = '12px';
							
							var title = document.createElement('div');
							title.textContent = item.name;
							title.style.fontWeight = '500'; // Changed from bold to medium weight
							title.style.fontSize = '1.05em'; // Slightly reduced from 1.1em
							title.style.color = '#5a5a5a'; // Softer color instead of default black
							title.style.letterSpacing = '0.3px'; // Subtle letter spacing for distinction
							titleRow.appendChild(title);
							
							// Edit button - Try multiple image paths as fallback
							if (window.hasEditEngineeringPermission === true) {
								console.log('Adding edit button...');
								
								// Create a clickable edit button with SVG or text fallback
								var editBtn = document.createElement('button');
								editBtn.style.background = 'transparent';
								editBtn.style.border = 'none';
								editBtn.style.cursor = 'pointer';
								editBtn.style.padding = '4px';
								editBtn.style.display = 'flex';
								editBtn.style.alignItems = 'center';
								editBtn.style.justifyContent = 'center';
								editBtn.style.minWidth = '24px';
								editBtn.style.minHeight = '24px';
								editBtn.title = 'Edit';
								
								// Try to load the image
								var pencil = document.createElement('img');
								var imagePaths = [
									'/PortalSite/pages/engineering/images/pencil.svg',
									'images/pencil.svg',
									'./images/pencil.svg',
									'../../assets/icons/edit.svg',
									'/assets/icons/edit.svg'
								];
								
								pencil.style.width = '20px';
								pencil.style.height = '20px';
								pencil.style.display = 'block';
								
								// Try first path
								pencil.src = imagePaths[0];
								
								// If image fails to load, try other paths or use text fallback
								var pathIndex = 0;
								pencil.onerror = function() {
									pathIndex++;
									if (pathIndex < imagePaths.length) {
										console.log('Trying image path:', imagePaths[pathIndex]);
										pencil.src = imagePaths[pathIndex];
									} else {
										// All paths failed, use text fallback
										console.log('All image paths failed, using text fallback');
										editBtn.removeChild(pencil);
										editBtn.textContent = '✏️';
										editBtn.style.fontSize = '18px';
									}
								};
								
								pencil.onload = function() {
									console.log('Image loaded successfully from:', pencil.src);
								};
								
								editBtn.appendChild(pencil);
								
								editBtn.addEventListener('click', function(e) {
									e.preventDefault();
									e.stopPropagation();
									console.log('Edit button clicked!');
									openEditModal(item);
								});
								
								titleRow.appendChild(editBtn);
								console.log('Edit button added to titleRow');
							} else {
								console.log('No edit permission - button not added');
							}
							
							wrapper.appendChild(titleRow);

							var subList = document.createElement('ul');
							subList.style.listStyle = 'none';
							subList.style.padding = '0 0 0 12px';
							subList.style.margin = '0';
							subList.style.fontSize = '1em';
							subList.style.color = '#444';
							subList.style.width = '100%';
							subList.style.boxSizing = 'border-box';
							var labels = ['In house Drawings', 'Bill of materials', 'Parts and Suppliers'];
							labels.forEach(function(label, idx) {
								var li = document.createElement('li');
								li.style.display = 'flex';
								li.style.alignItems = 'center';
								li.style.gap = '8px';
								li.style.padding = '6px 0';
								li.style.position = 'relative';
								li.style.width = '100%';
								// Subitem name
								var span = document.createElement('span');
								span.textContent = label;
								span.style.display = 'inline-block';
								span.style.flex = '0 0 auto';
								span.style.minWidth = 'auto';
								span.style.textAlign = 'left';
								li.appendChild(span);
								// Chevron icon (SVG)
								var chevron = document.createElement('span');
								chevron.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.5 6L8 9.5L11.5 6" stroke="#888" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
								chevron.style.display = 'inline-flex';
								chevron.style.alignItems = 'center';
								chevron.style.justifyContent = 'center';
								chevron.style.cursor = 'pointer';
								chevron.style.padding = '4px';
								chevron.style.borderRadius = '4px';
								chevron.style.transition = 'background 0.2s';
								
								// Add hover effect
								chevron.addEventListener('mouseenter', function() {
									chevron.style.background = '#f0f0f0';
								});
								chevron.addEventListener('mouseleave', function() {
									chevron.style.background = 'transparent';
								});
								
								// Single click handler on the entire li row to prevent double-firing
								// (span + chevron as siblings could both receive a near-boundary click)
								if (label === 'In house Drawings') {
									span.style.cursor = 'pointer';
									li.style.cursor = 'pointer';
									li.addEventListener('click', function(e) {
										e.stopPropagation();
										handleDrawingsClick(item, li);
									});
								}
								
								// Add click handler for Bill of Materials
								if (label === 'Bill of materials') {
									span.style.cursor = 'pointer';
									li.style.cursor = 'pointer';
									li.addEventListener('click', function(e) {
										e.stopPropagation();
										handleBillOfMaterialsClick(item, li);
									});
								}
								
								// Add click handler for Parts and Suppliers
								if (label === 'Parts and Suppliers') {
									span.style.cursor = 'pointer';
									li.style.cursor = 'pointer';
									li.addEventListener('click', function(e) {
										e.stopPropagation();
										handlePartsClick(item, li);
									});
								}
								
								li.appendChild(chevron);
								subList.appendChild(li);
								if (idx < labels.length - 1) {
									var hr = document.createElement('hr');
									hr.style.border = 'none';
									hr.style.borderTop = '1px solid #d1d5db';
									hr.style.margin = '2px 0';
									hr.style.width = '100%';
									hr.style.marginLeft = '0';
									subList.appendChild(hr);
								}
							});
							wrapper.appendChild(subList);
							itemDetails.appendChild(wrapper);
							
							console.log('Details rendered, titleRow children:', titleRow.children.length);
						}

						function openEditModal(item) {
							document.getElementById('editItemNameInput').value = item.name;
							editModal.style.display = 'flex';
							// Save
							document.getElementById('saveEditItemBtn').onclick = function() {
								var newName = document.getElementById('editItemNameInput').value.trim();
								if (!newName) { document.getElementById('editItemNameInput').focus(); return; }
								fetch(apiBase + '/update_engineering_item.php', {
									method: 'POST',
									headers: { 'Content-Type': 'application/json' },
									body: JSON.stringify({ id: item.id, name: newName })
								})
								.then(function(res) { return res.json(); })
								.then(function(data) {
									if (data.success) {
										editModal.style.display = 'none';
										fetchItems();
									} else {
										alert(data.message || 'Failed to update item');
									}
								});
							};
							// Delete
							document.getElementById('deleteEditItemBtn').onclick = function() {
								if (!confirm('Are you sure you want to delete this item?')) return;
								fetch(apiBase + '/delete_engineering_item.php', {
									method: 'POST',
									headers: { 'Content-Type': 'application/json' },
									body: JSON.stringify({ id: item.id })
								})
								.then(function(res) { return res.json(); })
								.then(function(data) {
									if (data.success) {
										editModal.style.display = 'none';
										fetchItems();
									} else {
										alert(data.message || 'Failed to delete item');
									}
								});
							};
							// Cancel
							document.getElementById('cancelEditItemBtn').onclick = function() {
								editModal.style.display = 'none';
							};
						}

						// Handle drawings dropdown click
						var currentItemForDrawings = null;
						var drawingsDropdownBusy = false;
						function handleDrawingsClick(item, liElement) {
							if (drawingsDropdownBusy) return;
							drawingsDropdownBusy = true;
							
							currentItemForDrawings = item;
							
							// Toggle: if already open for this trigger, close it
							var existingDropdown = document.getElementById('drawingsDropdown');
							if (existingDropdown) {
								existingDropdown.remove();
								drawingsDropdownBusy = false;
								return;
							}
							
							// Fetch existing drawings
							fetch(apiBase + '/get_engineering_drawings.php?item_id=' + item.id)
								.then(function(res) { return res.json(); })
								.then(function(data) {
									var dropdown = document.createElement('div');
									dropdown.id = 'drawingsDropdown';
									dropdown.style.position = 'relative';
									dropdown.style.display = 'block';
									dropdown.style.background = '#f7f9fc';
									dropdown.style.border = '1px solid #d1d5db';
									dropdown.style.borderTop = 'none';
									dropdown.style.borderRadius = '0 0 6px 6px';
									dropdown.style.boxShadow = 'none';
									dropdown.style.width = '100%';
									dropdown.style.maxWidth = '100%';
									dropdown.style.zIndex = '1';
									dropdown.style.margin = '0 0 10px 0';
									dropdown.style.padding = '10px 0';
									dropdown.style.boxSizing = 'border-box';
									dropdown.style.overflow = 'hidden';
									dropdown.style.maxHeight = '0';
									dropdown.style.opacity = '0';
									dropdown.style.transform = 'translateY(-4px)';
									dropdown.style.transition = 'max-height 0.24s ease, opacity 0.2s ease, transform 0.2s ease';
									dropdown.style.pointerEvents = 'none';
									
									var hasDrawings = data.success && data.drawings && data.drawings.length > 0;
									if (hasDrawings) {
										// Group drawings by version so each upload batch is shown as one boxed section
										var drawingsByVersion = {};
										data.drawings.forEach(function(drawing) {
											if (!drawingsByVersion[drawing.version]) {
												drawingsByVersion[drawing.version] = [];
											}
											drawingsByVersion[drawing.version].push(drawing);
										});

										var sortedVersions = Object.keys(drawingsByVersion)
											.sort(function(a, b) {
												return parseInt(b.replace('v', ''), 10) - parseInt(a.replace('v', ''), 10);
											});

										function createVersionBox(versionKey, headerText, withPreviousToggle, previousContainer, isCurrentVersion) {
											var versionBox = document.createElement('div');
											versionBox.style.margin = '8px 12px';
											versionBox.style.width = '50%';
											versionBox.style.maxWidth = '920px';
											versionBox.style.border = '1px solid #d7dce4';
											versionBox.style.borderRadius = '6px';
											versionBox.style.background = '#ffffff';

											var versionHeader = document.createElement('div');
											versionHeader.style.padding = '8px 12px';
											versionHeader.style.fontWeight = '700';
											versionHeader.style.fontSize = '0.92em';
											versionHeader.style.color = '#334155';
											versionHeader.style.borderBottom = '1px solid #e6ebf2';

											versionHeader.style.display = 'flex';
											versionHeader.style.alignItems = 'center';
											versionHeader.style.gap = '12px';

											var headerTitle = document.createElement('span');
											headerTitle.textContent = headerText;
											versionHeader.appendChild(headerTitle);

											var downloadAllBtn = document.createElement('span');
											downloadAllBtn.textContent = 'Download ' + versionKey.toUpperCase();
											downloadAllBtn.style.fontWeight = '600';
											downloadAllBtn.style.fontSize = '0.88em';
											downloadAllBtn.style.color = '#5b7fa3';
											downloadAllBtn.style.cursor = 'pointer';
											downloadAllBtn.style.marginLeft = withPreviousToggle ? '0' : 'auto';
											downloadAllBtn.addEventListener('click', function(e) {
												e.stopPropagation();
												var zipUrl = apiBase + '/download_engineering_drawings_zip.php?item_id=' + encodeURIComponent(item.id) + '&version=' + encodeURIComponent(versionKey);
												window.location.href = zipUrl;
											});
											versionHeader.appendChild(downloadAllBtn);

											if (withPreviousToggle) {
												var previousToggle = document.createElement('span');
												previousToggle.textContent = 'Click to view previous versions';
												previousToggle.style.fontWeight = '600';
												previousToggle.style.fontSize = '0.88em';
												previousToggle.style.color = '#5b7fa3';
												previousToggle.style.cursor = 'pointer';
												previousToggle.style.marginLeft = 'auto';
												previousToggle.addEventListener('click', function() {
													if (!previousContainer) return;
													var isHidden = previousContainer.style.display === 'none';
													previousContainer.style.display = isHidden ? 'block' : 'none';
													previousToggle.textContent = isHidden ? 'Hide previous versions' : 'Click to view previous versions';
													dropdown.style.maxHeight = (dropdown.scrollHeight + 40) + 'px';
												});
												versionHeader.appendChild(previousToggle);
											}

											versionBox.appendChild(versionHeader);

											drawingsByVersion[versionKey].forEach(function(drawing, index) {
												var drawingItem = document.createElement('div');
												drawingItem.style.padding = '8px 12px';
												drawingItem.style.cursor = 'pointer';
												drawingItem.style.fontSize = '0.93em';
												drawingItem.style.color = '#1f2937';
												drawingItem.textContent = drawing.filename;

												if (index < drawingsByVersion[versionKey].length - 1) {
													drawingItem.style.borderBottom = '1px solid #f1f5f9';
												}

												drawingItem.addEventListener('mouseenter', function() {
													drawingItem.style.background = '#f8fafc';
												});
												drawingItem.addEventListener('mouseleave', function() {
													drawingItem.style.background = 'transparent';
												});
												drawingItem.addEventListener('click', function() {
													window.open(drawing.file_url, '_blank');
													dropdown.remove();
												});

												versionBox.appendChild(drawingItem);
											});

											return versionBox;
										}

										var currentVersionKey = sortedVersions[0];
										var currentDisplayVersion = currentVersionKey.toUpperCase();
										var previousContainer = document.createElement('div');
										previousContainer.style.display = 'none';

										var currentBox = createVersionBox(
											currentVersionKey,
											'Current Version: ' + currentDisplayVersion,
											sortedVersions.length > 1,
											previousContainer,
											true
										);
										dropdown.appendChild(currentBox);

										sortedVersions.slice(1).forEach(function(versionKey) {
											var displayVersion = versionKey.toUpperCase();
											previousContainer.appendChild(
												createVersionBox(versionKey, 'Version: ' + displayVersion, false, null, false)
											);
										});

										dropdown.appendChild(previousContainer);
										
										// Add separator
										var separator = document.createElement('div');
										separator.style.borderTop = '1px solid #e5e7eb';
										separator.style.margin = '4px 0';
										dropdown.appendChild(separator);
									}

									if (!hasDrawings) {
										var emptyState = document.createElement('div');
										emptyState.textContent = 'No drawings yet. Click add.';
										emptyState.style.padding = '10px 16px 6px 16px';
										emptyState.style.fontSize = '0.92em';
										emptyState.style.color = '#6b7280';
										emptyState.style.fontStyle = 'italic';
										dropdown.appendChild(emptyState);
									}
									
									// Add "Add/Update drawings" option - update requires permission
									var shouldShowOption = !hasDrawings || (hasDrawings && window.hasEditEngineeringPermission === true);
									
									if (shouldShowOption) {
										var addOption = document.createElement('div');
										addOption.textContent = hasDrawings ? '+ Update drawings' : '+ Add drawings';
										addOption.style.padding = '10px 16px 12px 16px';
										addOption.style.cursor = 'pointer';
										addOption.style.fontWeight = '600';
										addOption.style.color = '#5b7fa3';
										addOption.style.fontSize = '0.95em';
										addOption.style.borderTop = hasDrawings ? 'none' : '1px solid #e5e7eb';
										addOption.style.borderBottom = hasDrawings ? '1px solid #e5e7eb' : 'none';
										addOption.addEventListener('mouseenter', function() {
											addOption.style.background = '#f3f4f6';
										});
										addOption.addEventListener('mouseleave', function() {
											addOption.style.background = 'transparent';
										});
										addOption.addEventListener('click', function() {
											dropdown.remove();
											openUploadDrawingsModal();
										});
										dropdown.appendChild(addOption);
										if (hasDrawings && dropdown.firstChild) {
											dropdown.insertBefore(addOption, dropdown.firstChild);
										}
									}
									
									if (liElement.parentNode) {
										liElement.parentNode.insertBefore(dropdown, liElement.nextSibling);
									}
									
									// Trigger animation
									setTimeout(function() {
										dropdown.style.maxHeight = (dropdown.scrollHeight + 40) + 'px';
										dropdown.style.opacity = '1';
										dropdown.style.transform = 'translateY(0)';
										dropdown.style.pointerEvents = 'auto';
									}, 10);
								})
								.catch(function(err) {
									console.error('Error loading drawings:', err);
								})
								.finally(function() {
									drawingsDropdownBusy = false;
								});
						}

						function openUploadDrawingsModal() {
							var modal = document.getElementById('uploadDrawingsModal');
							var filesInput = document.getElementById('drawingFilesInput');
							var preview = document.getElementById('selectedFilesPreview');
							
							filesInput.value = '';
							preview.innerHTML = '';
							modal.style.display = 'flex';
							
							// Show selected files
							filesInput.addEventListener('change', function() {
								var files = filesInput.files;
								if (files.length > 0) {
									preview.innerHTML = files.length + ' file(s) selected: ' + Array.from(files).map(function(f) { return f.name; }).join(', ');
								} else {
									preview.innerHTML = '';
								}
							});
						}

						function createBomRow() {
							var row = document.createElement('div');
							row.className = 'bom-upload-row';
							row.style.display = 'flex';
							row.style.alignItems = 'center';
							row.style.gap = '10px';
						
							var nameInput = document.createElement('input');
							nameInput.type = 'text';
							nameInput.placeholder = 'e.g., Initial BOM, Rev A';
							nameInput.className = 'bom-row-name';
							nameInput.style.flex = '2';
							nameInput.style.padding = '7px 9px';
							nameInput.style.border = '1px solid #b0b8c1';
							nameInput.style.borderRadius = '4px';
							nameInput.style.fontSize = '0.92em';
						
							var fileInput = document.createElement('input');
							fileInput.type = 'file';
							fileInput.accept = '.pdf,.xlsx,.xls,.csv,.doc,.docx';
							fileInput.className = 'bom-row-file';
							fileInput.style.flex = '3';
							fileInput.style.padding = '5px';
							fileInput.style.border = '1px solid #b0b8c1';
							fileInput.style.borderRadius = '4px';
							fileInput.style.fontSize = '0.88em';
						
							var removeBtn = document.createElement('button');
							removeBtn.type = 'button';
							removeBtn.textContent = '×';
							removeBtn.style.background = 'transparent';
							removeBtn.style.border = 'none';
							removeBtn.style.color = '#ef4444';
							removeBtn.style.cursor = 'pointer';
							removeBtn.style.fontSize = '20px';
							removeBtn.style.lineHeight = '1';
							removeBtn.style.padding = '0 4px';
							removeBtn.style.width = '28px';
							removeBtn.style.flexShrink = '0';
							removeBtn.addEventListener('click', function() {
								var rows = document.querySelectorAll('#bomUploadRows .bom-upload-row');
								if (rows.length > 1) { row.remove(); }
							});
						
							row.appendChild(nameInput);
							row.appendChild(fileInput);
							row.appendChild(removeBtn);
							return row;
						}
						
						function openUploadBomModal() {
							var modal = document.getElementById('uploadBomModal');
							var rowsContainer = document.getElementById('bomUploadRows');
							rowsContainer.innerHTML = '';
							rowsContainer.appendChild(createBomRow());
							modal.style.display = 'flex';
						}

						// Bill of Materials functionality
						var currentItemForBom = null;
						var bomDropdownBusy = false;
						function handleBillOfMaterialsClick(item, liElement) {
							if (bomDropdownBusy) return;
							bomDropdownBusy = true;
							
							currentItemForBom = item;
							
							// Toggle: if already open, close it
							var existingDropdown = document.getElementById('bomDropdown');
							if (existingDropdown) {
								existingDropdown.remove();
								bomDropdownBusy = false;
								return;
							}
							
							// Fetch existing BOMs for this engineering item
							fetch(apiBase + '/get_engineering_bom.php?item_id=' + item.id)
								.then(function(res) { return res.json(); })
								.then(function(data) {
									var dropdown = document.createElement('div');
									dropdown.id = 'bomDropdown';
									dropdown.style.position = 'relative';
									dropdown.style.display = 'block';
									dropdown.style.background = '#f7f9fc';
									dropdown.style.border = '1px solid #d1d5db';
									dropdown.style.borderTop = 'none';
									dropdown.style.borderRadius = '0 0 6px 6px';
									dropdown.style.boxShadow = 'none';
									dropdown.style.width = '100%';
									dropdown.style.maxWidth = '100%';
									dropdown.style.zIndex = '1';
									dropdown.style.margin = '0 0 10px 0';
									dropdown.style.padding = '10px 0';
									dropdown.style.boxSizing = 'border-box';
									dropdown.style.overflow = 'hidden';
									dropdown.style.maxHeight = '0';
									dropdown.style.opacity = '0';
									dropdown.style.transform = 'translateY(-4px)';
									dropdown.style.transition = 'max-height 0.24s ease, opacity 0.2s ease, transform 0.2s ease';
									dropdown.style.pointerEvents = 'none';
									
									var hasBoms = data.success && data.boms && data.boms.length > 0;

									if (hasBoms) {
										// Group BOMs by version
										var bomsByVersion = {};
										data.boms.forEach(function(bom) {
											var ver = bom.version || 'v1';
											if (!bomsByVersion[ver]) bomsByVersion[ver] = [];
											bomsByVersion[ver].push(bom);
										});

										var sortedVersions = Object.keys(bomsByVersion)
											.sort(function(a, b) {
												return parseInt(b.replace('v', ''), 10) - parseInt(a.replace('v', ''), 10);
											});

										function createBomVersionBox(versionKey, headerText, withPreviousToggle, previousContainer) {
											var versionBox = document.createElement('div');
											versionBox.style.margin = '8px 12px';
											versionBox.style.width = '50%';
											versionBox.style.maxWidth = '920px';
											versionBox.style.border = '1px solid #d7dce4';
											versionBox.style.borderRadius = '6px';
											versionBox.style.background = '#ffffff';

											var versionHeader = document.createElement('div');
											versionHeader.style.padding = '8px 12px';
											versionHeader.style.fontWeight = '700';
											versionHeader.style.fontSize = '0.92em';
											versionHeader.style.color = '#334155';
											versionHeader.style.borderBottom = '1px solid #e6ebf2';
											versionHeader.style.display = 'flex';
											versionHeader.style.alignItems = 'center';
											versionHeader.style.gap = '12px';

											var headerTitle = document.createElement('span');
											headerTitle.textContent = headerText;
											versionHeader.appendChild(headerTitle);

											var downloadAllBtn = document.createElement('span');
											downloadAllBtn.textContent = 'Download ' + versionKey.toUpperCase();
											downloadAllBtn.style.fontWeight = '600';
											downloadAllBtn.style.fontSize = '0.88em';
											downloadAllBtn.style.color = '#5b7fa3';
											downloadAllBtn.style.cursor = 'pointer';
											downloadAllBtn.style.marginLeft = withPreviousToggle ? '0' : 'auto';
											downloadAllBtn.addEventListener('click', function(e) {
												e.stopPropagation();
												var bom = bomsByVersion[versionKey][0];
												window.open(bom.file_url, '_blank');
											});
											versionHeader.appendChild(downloadAllBtn);

											if (withPreviousToggle) {
												var previousToggle = document.createElement('span');
												previousToggle.textContent = 'Click to view previous versions';
												previousToggle.style.fontWeight = '600';
												previousToggle.style.fontSize = '0.88em';
												previousToggle.style.color = '#5b7fa3';
												previousToggle.style.cursor = 'pointer';
												previousToggle.style.marginLeft = 'auto';
												previousToggle.addEventListener('click', function() {
													if (!previousContainer) return;
													var isHidden = previousContainer.style.display === 'none';
													previousContainer.style.display = isHidden ? 'block' : 'none';
													previousToggle.textContent = isHidden ? 'Hide previous versions' : 'Click to view previous versions';
													dropdown.style.maxHeight = (dropdown.scrollHeight + 40) + 'px';
												});
												versionHeader.appendChild(previousToggle);
											}

											versionBox.appendChild(versionHeader);

											bomsByVersion[versionKey].forEach(function(bom, index) {
												var bomRow = document.createElement('div');
												bomRow.style.padding = '8px 12px';
												bomRow.style.display = 'flex';
												bomRow.style.alignItems = 'center';
												bomRow.style.fontSize = '0.93em';
												bomRow.style.color = '#1f2937';
												if (index < bomsByVersion[versionKey].length - 1) {
													bomRow.style.borderBottom = '1px solid #f1f5f9';
												}

												var nameSpan = document.createElement('span');
												nameSpan.textContent = bom.document_name;
												nameSpan.style.flex = '1';

												var deleteBtn = document.createElement('button');
												deleteBtn.textContent = '✕';
												deleteBtn.style.background = 'transparent';
												deleteBtn.style.border = 'none';
												deleteBtn.style.color = '#ef4444';
												deleteBtn.style.cursor = 'pointer';
												deleteBtn.style.marginLeft = '16px';
												deleteBtn.style.marginRight = '4px';
												deleteBtn.style.fontSize = '15px';
												deleteBtn.addEventListener('click', function(e) {
													e.stopPropagation();
													if (confirm('Delete this BOM?')) {
														fetch(apiBase + '/delete_engineering_bom.php', {
															method: 'POST',
															headers: { 'Content-Type': 'application/json' },
															body: JSON.stringify({ id: bom.id })
														})
														.then(function(r) { return r.json(); })
														.then(function(res) {
															if (res.success) {
																handleBillOfMaterialsClick(currentItemForBom, liElement);
															} else {
																alert('Failed to delete BOM');
															}
														});
													}
												});

												bomRow.appendChild(nameSpan);
												bomRow.appendChild(deleteBtn);
												versionBox.appendChild(bomRow);
											});

											return versionBox;
										}

										var currentVersionKey = sortedVersions[0];
										var currentDisplayVersion = currentVersionKey.toUpperCase();
										var previousContainer = document.createElement('div');
										previousContainer.style.display = 'none';

										var currentBox = createBomVersionBox(
											currentVersionKey,
											'Current Version: ' + currentDisplayVersion,
											sortedVersions.length > 1,
											previousContainer
										);
										dropdown.appendChild(currentBox);

										sortedVersions.slice(1).forEach(function(versionKey) {
											previousContainer.appendChild(
												createBomVersionBox(versionKey, 'Version: ' + versionKey.toUpperCase(), false, null)
											);
										});

										dropdown.appendChild(previousContainer);

										var separator = document.createElement('div');
										separator.style.borderTop = '1px solid #e5e7eb';
										separator.style.margin = '4px 0';
										dropdown.appendChild(separator);
									}

									if (!hasBoms) {
										var emptyState = document.createElement('div');
										emptyState.textContent = 'No BOMs available. Click add.';
										emptyState.style.padding = '10px 16px 6px 16px';
										emptyState.style.fontSize = '0.92em';
										emptyState.style.color = '#6b7280';
										emptyState.style.fontStyle = 'italic';
										dropdown.appendChild(emptyState);
									}

									// "+ Update BOM" (permission required) or "+ Add BOM"
									var shouldShowBomOption = !hasBoms || (hasBoms && window.hasEditEngineeringPermission === true);
									if (shouldShowBomOption) {
										var addOption = document.createElement('div');
										addOption.textContent = hasBoms ? '+ Update BOM' : '+ Add BOM';
										addOption.style.padding = '10px 16px 12px 16px';
										addOption.style.cursor = 'pointer';
										addOption.style.fontWeight = '600';
										addOption.style.color = '#5b7fa3';
										addOption.style.fontSize = '0.95em';
										addOption.style.borderTop = hasBoms ? 'none' : '1px solid #e5e7eb';
										addOption.style.borderBottom = hasBoms ? '1px solid #e5e7eb' : 'none';
										addOption.addEventListener('mouseenter', function() {
											addOption.style.background = '#f3f4f6';
										});
										addOption.addEventListener('mouseleave', function() {
											addOption.style.background = 'transparent';
										});
										addOption.addEventListener('click', function() {
											dropdown.remove();
											openUploadBomModal();
										});
										if (hasBoms && dropdown.firstChild) {
											dropdown.insertBefore(addOption, dropdown.firstChild);
										} else {
											dropdown.appendChild(addOption);
										}
									}
									
									if (liElement.parentNode) {
										liElement.parentNode.insertBefore(dropdown, liElement.nextSibling);
									}
									
									// Trigger animation
									setTimeout(function() {
										dropdown.style.maxHeight = (dropdown.scrollHeight + 40) + 'px';
										dropdown.style.opacity = '1';
										dropdown.style.transform = 'translateY(0)';
										dropdown.style.pointerEvents = 'auto';
									}, 10);
								})
								.catch(function(err) {
									alert('Error loading BOMs: ' + err.message);
								})
								.finally(function() {
									bomDropdownBusy = false;
								});
						}

						// Parts and Suppliers functionality
						var currentItemForParts = null;
						var partsDropdownBusy = false;
						var partModalState = { editMode: false, originalPartName: '' };

						function handlePartsClick(item, liElement) {
							if (partsDropdownBusy) return;
							partsDropdownBusy = true;
							
							currentItemForParts = item;
							
							// Toggle: if already open, close it
							var existingDropdown = document.getElementById('partsDropdown');
							if (existingDropdown) {
								existingDropdown.remove();
								partsDropdownBusy = false;
								return;
							}
							
							// Fetch existing parts for this engineering item
							fetch(apiBase + '/get_engineering_item_parts.php?item_id=' + item.id)
								.then(function(res) { return res.json(); })
								.then(function(data) {
									var dropdown = document.createElement('div');
									dropdown.id = 'partsDropdown';
									dropdown.style.position = 'relative';
									dropdown.style.display = 'block';
									dropdown.style.background = '#f7f9fc';
									dropdown.style.border = '1px solid #d1d5db';
									dropdown.style.borderTop = 'none';
									dropdown.style.borderRadius = '0 0 6px 6px';
									dropdown.style.boxShadow = 'none';
									dropdown.style.width = '100%';
									dropdown.style.maxWidth = '100%';
									dropdown.style.zIndex = '1';
									dropdown.style.margin = '0 0 10px 0';
									dropdown.style.padding = '10px 0';
									dropdown.style.boxSizing = 'border-box';
									dropdown.style.overflow = 'hidden';
									dropdown.style.maxHeight = '0';
									dropdown.style.opacity = '0';
									dropdown.style.transform = 'translateY(-4px)';
									dropdown.style.transition = 'max-height 0.24s ease, opacity 0.2s ease, transform 0.2s ease';
									dropdown.style.pointerEvents = 'none';
									
									var hasParts = data.success && data.parts && data.parts.length > 0;
									
									// Add "Add part" button first
									var addOption = document.createElement('div');
									addOption.textContent = hasParts ? '+ Add / Update parts' : '+ Add parts';
									addOption.style.padding = '10px 16px 12px 16px';
									addOption.style.cursor = 'pointer';
									addOption.style.fontWeight = '600';
									addOption.style.color = '#5b7fa3';
									addOption.style.fontSize = '0.95em';
									addOption.style.borderBottom = hasParts ? '1px solid #e5e7eb' : 'none';
									addOption.addEventListener('mouseenter', function() {
										addOption.style.background = '#f3f4f6';
									});
									addOption.addEventListener('mouseleave', function() {
										addOption.style.background = 'transparent';
									});
									addOption.addEventListener('click', function() {
										dropdown.remove();
										openAddPartModal();
									});
									dropdown.appendChild(addOption);
									
									if (hasParts) {
										// Group parts by part name with makes
										var partsList = {};
										data.parts.forEach(function(part) {
											if (!partsList[part.part_name]) {
												partsList[part.part_name] = {
													part_name: part.part_name,
													nsn_number: part.nsn_number || '',
													quantity: part.quantity || 1,
													notes: part.notes || '',
													makes: []
												};
											}
											if (part.make) {
												partsList[part.part_name].makes.push({
													make: part.make,
													partNumber: part.model || '',
													otherNumbers: part.other_numbers || '',
													supplier: part.supplier || '',
													supplierName: part.supplier_name || '',
													supplierNumber: part.supplier_number || '',
													supplierEmail: part.supplier_email || '',
													supplierAddress: part.supplier_address || '',
													supplierPrice: part.supplier_price || ''
												});
											}
										});

										// Display parts as cards
										var partsContainer = document.createElement('div');
										partsContainer.className = 'parts-grid';
										partsContainer.style.padding = '10px 12px';
										partsContainer.style.gap = '12px';
										partsContainer.style.gridTemplateColumns = 'repeat(auto-fill, minmax(200px, 1fr))';
										
										Object.keys(partsList).forEach(function(partName) {
											var partData = partsList[partName];
											var partCard = document.createElement('div');
											partCard.className = 'part-card';
											partCard.style.cursor = 'pointer';
											partCard.setAttribute('data-part-name', partData.part_name);
											partCard.setAttribute('data-part-nsn', partData.nsn_number);
											partCard.setAttribute('data-part-makes', JSON.stringify(partData.makes));
											
											// Part header
											var partHeader = document.createElement('div');
											partHeader.className = 'part-header';
											var partNameEl = document.createElement('div');
											partNameEl.className = 'part-name';
											partNameEl.textContent = partData.part_name;
											partHeader.appendChild(partNameEl);
											if (partData.quantity > 1) {
												var qtyBadge = document.createElement('span');
												qtyBadge.className = 'part-quantity';
												qtyBadge.textContent = 'Qty: ' + partData.quantity;
												partHeader.appendChild(qtyBadge);
											}
											partCard.appendChild(partHeader);
											
											// Makes table
											if (partData.makes.length > 0) {
												var makesSection = document.createElement('div');
												makesSection.className = 'makes-section';
												var table = document.createElement('table');
												table.className = 'makes-table';
												var thead = document.createElement('thead');
												thead.innerHTML = '<tr class="makes-table-header"><th class="makes-table-cell make-col" style="text-align:left;">Make</th><th class="makes-table-cell part-col">Part Number</th></tr>';
												table.appendChild(thead);
												var tbody = document.createElement('tbody');
												partData.makes.forEach(function(make) {
													var tr = document.createElement('tr');
													tr.className = 'makes-table-row';
													tr.innerHTML = '<td class="makes-table-cell make-col">' + make.make + '</td><td class="makes-table-cell part-col">' + (make.partNumber || '') + '</td>';
													tbody.appendChild(tr);
												});
												table.appendChild(tbody);
												makesSection.appendChild(table);
												partCard.appendChild(makesSection);
											}
											
											// Notes
											if (partData.notes) {
												var notesDiv = document.createElement('div');
												notesDiv.className = 'part-notes';
												notesDiv.innerHTML = '💡 ' + partData.notes;
												partCard.appendChild(notesDiv);
											}
											
											// Click handler to edit
											partCard.addEventListener('click', function() {
												dropdown.remove();
												openEditPartModal(partData);
											});
											
											partsContainer.appendChild(partCard);
										});
										
										dropdown.appendChild(partsContainer);
									} else {
										var emptyState = document.createElement('div');
										emptyState.textContent = 'No parts yet. Click add.';
										emptyState.style.padding = '10px 16px 6px 16px';
										emptyState.style.fontSize = '0.92em';
										emptyState.style.color = '#6b7280';
										emptyState.style.fontStyle = 'italic';
										dropdown.appendChild(emptyState); 
									}
									
									if (liElement.parentNode) {
										liElement.parentNode.insertBefore(dropdown, liElement.nextSibling);
									}
									
									// Trigger animation
									setTimeout(function() {
										dropdown.style.maxHeight = (dropdown.scrollHeight + 100) + 'px';
										dropdown.style.opacity = '1';
										dropdown.style.transform = 'translateY(0)';
										dropdown.style.pointerEvents = 'auto';
									}, 10);
								})
								.catch(function(err) {
									console.error('Error fetching parts:', err);
								})
								.finally(function() {
									partsDropdownBusy = false;
								});
						}

						function openAddPartModal() {
							partModalState.editMode = false;
							partModalState.originalPartName = '';
							var modal = document.getElementById('addPartModal');
							var form = document.getElementById('addPartForm');
							if (form) form.reset();
							
							// Reset makesList to single item
							var makesList = document.getElementById('makesList');
							if (makesList) {
								var items = makesList.querySelectorAll('.make-item');
								for (var i = 1; i < items.length; i++) {
									items[i].remove();
								}
							}
							
							var title = modal.querySelector('h3');
							if (title) title.textContent = 'Add Part';
							var deleteBtn = document.getElementById('deletePartBtn');
							if (deleteBtn) deleteBtn.style.display = 'none';
							
							modal.style.display = 'flex';
							
							// Initialize toggles and autocomplete
							setTimeout(function() {
								initSupplierDetailsToggle();
								var allMakeItems = makesList ? makesList.querySelectorAll('.make-item') : [];
								allMakeItems.forEach(function(it) { wireSupplierAutocomplete(it); });
							}, 100);
						}

						function openEditPartModal(partData) {
							partModalState.editMode = true;
							partModalState.originalPartName = partData.part_name;
							var modal = document.getElementById('addPartModal');
							modal.style.display = 'flex';
							
							var title = modal.querySelector('h3');
							if (title) title.textContent = 'Edit Part';
							var deleteBtn = document.getElementById('deletePartBtn');
							if (deleteBtn) deleteBtn.style.display = 'inline-block';
							
							// Fill form
							var partInput = document.getElementById('partNumber');
							if (partInput) partInput.value = partData.part_name;
							var nsnInput = document.getElementById('partNsn');
							if (nsnInput) nsnInput.value = partData.nsn_number;
							
							// Reset makesList
							var makesList = document.getElementById('makesList');
							if (makesList) {
								var items = makesList.querySelectorAll('.make-item');
								for (var i = 1; i < items.length; i++) {
									items[i].remove();
								}
							}
							
							// Fill makes
							if (partData.makes && partData.makes.length > 0) {
								var firstItem = makesList ? makesList.querySelector('.make-item') : null;
								if (firstItem) {
									fillMakeItem(firstItem, partData.makes[0]);
								}
								
								for (var j = 1; j < partData.makes.length; j++) {
									addAnotherMake();
									var allItems = makesList ? makesList.querySelectorAll('.make-item') : [];
									var lastItem = allItems[allItems.length - 1];
									if (lastItem) {
										fillMakeItem(lastItem, partData.makes[j]);
									}
								}
							}
							
							// Initialize toggles and autocomplete
							setTimeout(function() {
								initSupplierDetailsToggle();
								var allMakeItems = makesList ? makesList.querySelectorAll('.make-item') : [];
								allMakeItems.forEach(function(it) { wireSupplierAutocomplete(it); });
							}, 100);
						}

						function fillMakeItem(makeItem, makeData) {
							if (!makeItem) return;
							var mi = makeItem.querySelector('.make-input');
							var pn = makeItem.querySelector('.make-part-number');
							var on = makeItem.querySelector('.make-other-numbers');
							var sup = makeItem.querySelector('.make-supplier');
							var sname = makeItem.querySelector('.make-supplier-name');
							var snum = makeItem.querySelector('.make-supplier-number');
							var semail = makeItem.querySelector('.make-supplier-email');
							var saddr = makeItem.querySelector('.make-supplier-address');
							var sprice = makeItem.querySelector('.make-supplier-price');
							if (mi) mi.value = makeData.make || '';
							if (pn) pn.value = makeData.partNumber || '';
							if (on) on.value = makeData.otherNumbers || '';
							if (sup) sup.value = makeData.supplier || '';
							if (sname) sname.value = makeData.supplierName || '';
							if (snum) snum.value = makeData.supplierNumber || '';
							if (semail) semail.value = makeData.supplierEmail || '';
							if (saddr) saddr.value = makeData.supplierAddress || '';
							if (sprice) sprice.value = makeData.supplierPrice || '';
						}

						// Upload drawings button handler
						document.getElementById('uploadDrawingsBtn').addEventListener('click', function() {
							var filesInput = document.getElementById('drawingFilesInput');
							var files = filesInput.files;
							
							if (files.length === 0) {
								alert('Please select at least one file');
								return;
							}
							
							if (!currentItemForDrawings) {
								alert('No item selected');
								return;
							}
							
							var formData = new FormData();
							formData.append('item_id', currentItemForDrawings.id);
							for (var i = 0; i < files.length; i++) {
								formData.append('drawings[]', files[i]);
							}
							
							// Show uploading state
							document.getElementById('uploadDrawingsBtn').textContent = 'Uploading...';
							document.getElementById('uploadDrawingsBtn').disabled = true;
							
							fetch(apiBase + '/upload_engineering_drawings.php', {
								method: 'POST',
								body: formData
							})
							.then(function(res) { return res.json(); })
							.then(function(data) {
								if (data.success) {
									document.getElementById('uploadDrawingsModal').style.display = 'none';
									alert('Drawings uploaded successfully!');
								} else {
									alert(data.message || 'Failed to upload drawings');
								}
							})
							.catch(function(err) {
								alert('Upload failed: ' + err.message);
							})
							.finally(function() {
								document.getElementById('uploadDrawingsBtn').textContent = 'Upload';
								document.getElementById('uploadDrawingsBtn').disabled = false;
							});
						});

						// Cancel upload button handler
						document.getElementById('cancelUploadBtn').addEventListener('click', function() {
							document.getElementById('uploadDrawingsModal').style.display = 'none';
						});

						// BOM Upload Event Listeners
						document.getElementById('addBomRowBtn').addEventListener('click', function() {
							document.getElementById('bomUploadRows').appendChild(createBomRow());
						});
						
						document.getElementById('uploadBomBtn').addEventListener('click', function() {
							if (!currentItemForBom) { alert('No item selected'); return; }
							
							var rows = document.querySelectorAll('#bomUploadRows .bom-upload-row');
							var items = [];
							var valid = true;
							rows.forEach(function(row) {
								var name = row.querySelector('.bom-row-name').value.trim();
								var file = row.querySelector('.bom-row-file').files[0];
								if (!name && !file) return; // skip fully empty rows
								if (!name) { alert('Please enter a document name for each row'); valid = false; }
								else if (!file) { alert('Please select a file for: ' + name); valid = false; }
								else { items.push({ name: name, file: file }); }
							});
							if (!valid) return;
							if (items.length === 0) { alert('Please fill in at least one row'); return; }
							
							var btn = document.getElementById('uploadBomBtn');
							btn.textContent = 'Uploading...';
							btn.disabled = true;
							
							var chain = Promise.resolve();
							var errors = [];
							items.forEach(function(item) {
								chain = chain.then(function() {
									var formData = new FormData();
									formData.append('item_id', currentItemForBom.id);
									formData.append('document_name', item.name);
									formData.append('bom_file', item.file);
									return fetch(apiBase + '/upload_engineering_bom.php', { method: 'POST', body: formData })
										.then(function(res) { return res.json(); })
										.then(function(d) { if (!d.success) errors.push(item.name + ': ' + (d.message || 'failed')); });
								});
							});
							
							chain.then(function() {
								btn.textContent = 'Upload';
								btn.disabled = false;
								if (errors.length > 0) {
									alert('Some files failed:\n' + errors.join('\n'));
								} else {
									document.getElementById('uploadBomModal').style.display = 'none';
									alert(items.length + ' BOM(s) uploaded successfully!');
								}
							}).catch(function(err) {
								btn.textContent = 'Upload';
								btn.disabled = false;
								alert('Upload failed: ' + err.message);
							});
						});

						// Cancel BOM upload button handler
						document.getElementById('cancelUploadBomBtn').addEventListener('click', function() {
							document.getElementById('uploadBomModal').style.display = 'none';
						});

						// Parts Modal Control Logic
						var partModal = document.getElementById('addPartModal');
						var closePartModalBtn = document.getElementById('closePartModalBtn');
						var cancelPartModalBtn = document.getElementById('cancelPartModalBtn');
						var addPartForm = document.getElementById('addPartForm');
						var makesList = document.getElementById('makesList');
						var addAnotherMakeBtn = document.getElementById('addAnotherMakeBtn');
						var deletePartBtn = document.getElementById('deletePartBtn');
						var makeCounter = 1;

						function closePartModal() {
							if (partModal) partModal.style.display = 'none';
							if (addPartForm) addPartForm.reset();
							var items = makesList ? makesList.querySelectorAll('.make-item') : [];
							for (var i = 1; i < items.length; i++) {
								items[i].remove();
							}
							makeCounter = 1;
							partModalState.editMode = false;
							partModalState.originalPartName = '';
							if (deletePartBtn) deletePartBtn.style.display = 'none';
						}

						if (closePartModalBtn) closePartModalBtn.addEventListener('click', closePartModal);
						if (cancelPartModalBtn) cancelPartModalBtn.addEventListener('click', closePartModal);
						if (partModal) {
							partModal.addEventListener('click', function(e) {
								if (e.target === partModal) closePartModal();
							});
						}

						function addAnotherMake() {
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
								'</div></div></div>';
							makesList.appendChild(makeItem);
							initSupplierDetailsToggle();
							wireSupplierAutocomplete(makeItem);
							bindMakeRemoveHandler(makeItem);
						}

						if (addAnotherMakeBtn) {
							addAnotherMakeBtn.addEventListener('click', addAnotherMake);
						}

						function bindMakeRemoveHandler(makeItem) {
							if (!makeItem) return;
							var removeBtn = makeItem.querySelector('.remove-make-btn');
							if (!removeBtn || removeBtn.getAttribute('data-bound') === '1') return;
							removeBtn.setAttribute('data-bound', '1');
							removeBtn.addEventListener('click', function() {
								if (partModalState.editMode) {
									var partNameField = document.getElementById('partNumber');
									var partName = (partModalState.originalPartName || (partNameField ? partNameField.value.trim() : ''));
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

						function deleteMakeSpecification(partName, makeVal, modelVal) {
							if (!partName || !makeVal || !modelVal) return;
							var data = new FormData();
							data.append('part_name', partName);
							data.append('make', makeVal);
							data.append('model', modelVal);
							fetch(apiBase + '/delete_part_specification.php', {
								method: 'POST',
								body: data
							}).catch(function(err) { console.error('Delete make specification error', err); });
						}

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

						// Initialize first make item
						var firstMakeItem = makesList ? makesList.querySelector('.make-item') : null;
						if (firstMakeItem) {
							bindMakeRemoveHandler(firstMakeItem);
							wireSupplierAutocomplete(firstMakeItem);
						}

						// Delete part button
						if (deletePartBtn) {
							deletePartBtn.addEventListener('click', function() {
								if (!partModalState.editMode || !currentItemForParts) return;
								var targetName = partModalState.originalPartName || (document.getElementById('partNumber') ? document.getElementById('partNumber').value.trim() : '');
								if (!targetName) {
									alert('Missing part name to delete.');
									return;
								}
								if (!confirm('Delete this part?')) return;
								deletePartBtn.disabled = true;
								deletePartBtn.textContent = 'Deleting...';
								var fd = new FormData();
								fd.append('item_id', currentItemForParts.id);
								fd.append('part_name', targetName);
								fetch(apiBase + '/delete_engineering_item_part.php', {
									method: 'POST',
									body: fd
								})
								.then(function(r) { return r.json(); })
								.then(function(data) {
									if (data && data.success) {
										closePartModal();
										alert('Part deleted successfully!');
									} else {
										alert('Failed to delete part: ' + (data && data.message ? data.message : 'Unknown error'));
									}
								})
								.catch(function(err) {
									console.error(err);
									alert('Network error while deleting part.');
								})
								.finally(function() {
									deletePartBtn.disabled = false;
									deletePartBtn.textContent = 'Delete';
								});
							});
						}

						// Form submit handler
						if (addPartForm) {
							addPartForm.addEventListener('submit', function(e) {
								e.preventDefault();
								
								if (!currentItemForParts) {
									alert('No item selected');
									return;
								}
								
								var partNumber = document.getElementById('partNumber').value.trim();
								var partNsn = (document.getElementById('partNsn') || {}).value?.trim() || '';
								var makes = [];
								
								var makeItems = makesList ? makesList.querySelectorAll('.make-item') : [];
								makeItems.forEach(function(item) {
									var makeInput = item.querySelector('.make-input');
									var partInput = item.querySelector('.make-part-number');
									var otherInput = item.querySelector('.make-other-numbers');
									if (makeInput && partInput && makeInput.value.trim() && partInput.value.trim()) {
										var priceVal = (item.querySelector('.make-supplier-price') || {}).value?.trim() || '';
										priceVal = priceVal.replace(/,/g, '');
										if (priceVal === '') priceVal = null;
										var otherVal = otherInput ? otherInput.value.trim() : '';
										if (otherVal) {
											otherVal = otherVal.split(',').map(function(v) {
												return v.trim();
											}).filter(function(v) {
												return v.length > 0;
											}).join(', ');
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
								
								var submitBtn = addPartForm.querySelector('button[type="submit"]');
								if (submitBtn) {
									submitBtn.disabled = true;
									submitBtn.textContent = 'Saving...';
								}
								
								var formData = new FormData();
								formData.append('item_id', currentItemForParts.id);
								formData.append('part_number', partNumber);
								formData.append('nsn_number', partNsn);
								formData.append('quantity', 1);
								formData.append('notes', '');
								formData.append('makes', JSON.stringify(makes));
								formData.append('edit_mode', partModalState.editMode ? '1' : '0');
								formData.append('original_part_name', partModalState.originalPartName || '');
								
								fetch(apiBase + '/add_engineering_item_part.php', {
									method: 'POST',
									body: formData
								})
								.then(function(response) { return response.json(); })
								.then(function(data) {
									if (data.success) {
										savePartsSuppliers(makes, partNumber, partNsn);
										alert(partModalState.editMode ? 'Part updated successfully!' : 'Part added successfully!');
										closePartModal();
									} else {
										alert('Error: ' + (data.error || 'Failed to save part'));
									}
								})
								.catch(function(error) {
									console.error('Error:', error);
									alert('Failed to save part. Please try again.');
								})
								.finally(function() {
									if (submitBtn) {
										submitBtn.disabled = false;
										submitBtn.textContent = 'Save Part';
									}
								});
							});
						}

						function savePartsSuppliers(suppliers, partName, nsnNumber) {
							if (!suppliers || !suppliers.length) return;
							var payload = suppliers.map(function(s) {
								return {
									supplier: s.supplier || '',
									supplier_name: s.supplierName || '',
									supplier_number: s.supplierNumber || '',
									supplier_email: s.supplierEmail || '',
									supplier_address: s.supplierAddress || '',
									part_name: partName || '',
									nsn_number: nsnNumber || ''
								};
							}).filter(function(s) {
								return (s.supplier_name || s.supplier || s.supplier_email);
							});
							if (!payload.length) return;
							var fd = new FormData();
							fd.append('suppliers', JSON.stringify(payload));
							fetch(apiBase + '/save_parts_suppliers.php', {
								method: 'POST',
								body: fd
							}).catch(function(err) { console.warn('save_parts_suppliers failed', err); });
						}

						// Supplier autocomplete functionality
						var supplierDirectoryCache = null;
						var supplierDirectoryPromise = null;

						function normText(v) {
							return (v || '').toString().trim().toLowerCase();
						}

						function getBasePath() {
							try {
								var path = window.location.pathname || '';
								var idx = path.indexOf('/pages/');
								return (idx >= 0) ? path.slice(0, idx) : '';
							} catch (e) {
								return '';
							}
						}

						function fetchSupplierDirectory() {
							if (supplierDirectoryCache) return Promise.resolve(supplierDirectoryCache);
							if (supplierDirectoryPromise) return supplierDirectoryPromise;
							supplierDirectoryPromise = fetch(apiBase + '/get_parts_suppliers.php')
								.then(function(r) {
									return r.text().then(function(t) {
										try {
											return JSON.parse(t);
										} catch (e) {
											return { success: false, message: t };
										}
									});
								})
								.then(function(json) {
									var list = (json && json.clients) ? json.clients : [];
									supplierDirectoryCache = list;
									return list;
								})
								.catch(function() { return []; })
								.finally(function() { supplierDirectoryPromise = null; });
							return supplierDirectoryPromise;
						}

						function findClientByNameOrCompany(clients, name, company) {
							var n = normText(name);
							var comp = normText(company);
							if (n) {
								for (var i = 0; i < (clients || []).length; i++) {
									var c = clients[i];
									if (normText(c.client_name) === n) return c;
								}
							}
							if (comp) {
								for (var j = 0; j < (clients || []).length; j++) {
									var c2 = clients[j];
									if (normText(c2.current_employer) === comp) return c2;
								}
							}
							return null;
						}

						function removeSuggest(input) {
							try {
								if (input && input._supplierSuggestEl) {
									input._supplierSuggestEl.remove();
									input._supplierSuggestEl = null;
								}
							} catch (e) {}
						}

						function showSuggest(input, items, renderLabel, onPick) {
							if (!input) return;
							removeSuggest(input);
							if (!items || !items.length) return;
							var box = document.createElement('div');
							box.className = 'supplier-suggest';
							items.slice(0, 8).forEach(function(item) {
								var row = document.createElement('div');
								row.className = 'row';
								row.textContent = renderLabel(item);
								row.addEventListener('mousedown', function(e) {
									e.preventDefault();
									onPick(item);
									removeSuggest(input);
								});
								box.appendChild(row);
							});
							var rect = input.getBoundingClientRect();
							box.style.left = (rect.left + window.scrollX) + 'px';
							box.style.top = (rect.bottom + window.scrollY + 4) + 'px';
							box.style.width = rect.width + 'px';
							document.body.appendChild(box);
							input._supplierSuggestEl = box;
						}

						function ensureSupplierLink(container) {
							if (!container) return null;
							var existing = container.querySelector('.supplier-profile-link');
							if (existing) return existing;
							var link = document.createElement('a');
							link.className = 'supplier-profile-link';
							link.href = '#';
							link.target = '_blank';
							link.rel = 'noopener';
							link.textContent = 'Open client profile';
							link.style.display = 'none';
							container.appendChild(link);
							return link;
						}

						function setSupplierProfileLink(container, client) {
							var link = ensureSupplierLink(container);
							if (!link) return;
							if (client && client.client_id) {
								link.href = getBasePath() + '/pages/client_profile/index.php?client_id=' + encodeURIComponent(client.client_id);
								link.style.display = 'inline-block';
							} else {
								link.href = '#';
								link.style.display = 'none';
							}
						}

						function applySupplierClient(makeItem, client) {
							if (!makeItem || !client) return;
							var companyInput = makeItem.querySelector('.make-supplier');
							var nameInput = makeItem.querySelector('.make-supplier-name');
							var numberInput = makeItem.querySelector('.make-supplier-number');
							var emailInput = makeItem.querySelector('.make-supplier-email');
							var addressInput = makeItem.querySelector('.make-supplier-address');
							if (companyInput) companyInput.value = client.current_employer || '';
							if (nameInput) nameInput.value = client.client_name || '';
							if (numberInput) numberInput.value = client.contact_phone || '';
							if (emailInput) emailInput.value = client.client_email || '';
							if (addressInput) addressInput.value = client.client_address || '';
							var supplierContainer = companyInput ? companyInput.parentElement : null;
							if (supplierContainer) setSupplierProfileLink(supplierContainer, client);
							if (companyInput) companyInput.dataset.clientId = client.client_id || '';
							if (nameInput) nameInput.dataset.clientId = client.client_id || '';
						}

						function applySupplierCompany(makeItem, companyName) {
							if (!makeItem) return;
							var companyInput = makeItem.querySelector('.make-supplier');
							var nameInput = makeItem.querySelector('.make-supplier-name');
							var numberInput = makeItem.querySelector('.make-supplier-number');
							var emailInput = makeItem.querySelector('.make-supplier-email');
							var addressInput = makeItem.querySelector('.make-supplier-address');
							if (companyInput) companyInput.value = companyName || '';
							if (nameInput) nameInput.value = '';
							if (numberInput) numberInput.value = '';
							if (emailInput) emailInput.value = '';
							if (addressInput) addressInput.value = '';
							var supplierContainer = companyInput ? companyInput.parentElement : null;
							if (supplierContainer) setSupplierProfileLink(supplierContainer, null);
							if (companyInput) companyInput.dataset.clientId = '';
							if (nameInput) nameInput.dataset.clientId = '';
						}

						function wireSupplierAutocomplete(makeItem) {
							if (!makeItem) return;
							var companyInput = makeItem.querySelector('.make-supplier');
							var nameInput = makeItem.querySelector('.make-supplier-name');
							var supplierContainer = companyInput ? companyInput.parentElement : null;
							if (supplierContainer) ensureSupplierLink(supplierContainer);

							function bindSuggest(input, mode) {
								if (!input || input.dataset.supplierSuggestWired === '1') return;
								input.dataset.supplierSuggestWired = '1';
								input.addEventListener('input', function() {
									var q = input.value || '';
									fetchSupplierDirectory().then(function(clients) {
										if (mode === 'company') {
											var seen = {};
											var companies = [];
											(clients || []).forEach(function(c) {
												var comp = (c.current_employer || '').toString().trim();
												if (!comp) return;
												var key = comp.toLowerCase();
												if (seen[key]) return;
												seen[key] = true;
												if (normText(comp).indexOf(normText(q)) === -1) return;
												companies.push({ company: comp });
											});
											showSuggest(input, companies, function(c) {
												return c.company || '';
											}, function(c) {
												applySupplierCompany(makeItem, c.company || '');
											});
											return;
										}

										var companySelected = companyInput ? companyInput.value : '';
										var list = (clients || []).filter(function(c) {
											if (companySelected) {
												return normText(c.current_employer) === normText(companySelected);
											}
											return true;
										}).filter(function(c) {
											var v = c.client_name;
											return normText(v).indexOf(normText(q)) !== -1;
										});
										if (!list.length && !companySelected) {
											list = (clients || []).filter(function(c) {
												var v2 = c.client_name;
												return normText(v2).indexOf(normText(q)) !== -1;
											});
										}
										showSuggest(input, list, function(c) {
											var comp2 = c.current_employer || '';
											var nm2 = c.client_name || '';
											return nm2 + (comp2 ? ' — ' + comp2 : '');
										}, function(c) {
											applySupplierClient(makeItem, c);
										});
									});
								});
								input.addEventListener('blur', function() {
									setTimeout(function() { removeSuggest(input); }, 120);
								});
							}

							bindSuggest(companyInput, 'company');
							bindSuggest(nameInput, 'name');

							if (companyInput && !companyInput.dataset.supplierLinkWired) {
								companyInput.dataset.supplierLinkWired = '1';
								companyInput.addEventListener('dblclick', function() {
									fetchSupplierDirectory().then(function(clients) {
										var c = findClientByNameOrCompany(clients, nameInput ? nameInput.value : '', companyInput.value);
										applySupplierClient(makeItem, c);
									});
								});
							}

							if (nameInput && !nameInput.dataset.supplierLinkWired) {
								nameInput.dataset.supplierLinkWired = '1';
								nameInput.addEventListener('dblclick', function() {
									fetchSupplierDirectory().then(function(clients) {
										var c = findClientByNameOrCompany(clients, nameInput.value, companyInput ? companyInput.value : '');
										applySupplierClient(makeItem, c);
									});
								});
							}
						}
					})();
					// Dynamically set the link for View equipments button
					(function() {
						var btn = document.getElementById('viewEquipmentsBtn');
						if (btn) {
							if (window.location.hostname === 'localhost') {
								btn.href = '/PortalSite/pages/equipments/';
							} else {
								btn.href = '/pages/equipments/index.php';
							}
						}
					})();
					</script>
					</div>
				</div>
			</main>
		</div>
	</div>
	<script>
		(function(){
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







