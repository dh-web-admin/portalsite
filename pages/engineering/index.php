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
						function renderItems(){
							itemList.innerHTML = '';
							var itemDetails = document.getElementById('itemDetails');
							itemList.innerHTML = '';
							itemDetails.innerHTML = '';
							selectedItem = null;
							items.forEach(function(item, idx){
								var div = document.createElement('div');
								div.textContent = item.name;
								div.style.padding = '8px 22px';
								div.style.background = '#b0b8c1';
								div.style.color = '#222';
								div.style.borderRadius = '16px';
								div.style.fontWeight = 'bold';
								div.style.fontSize = '1em';
								div.style.display = 'inline-block';
								div.style.width = '22ch';
								div.style.textAlign = 'left';
								div.style.paddingLeft = '16px';
								div.style.cursor = 'pointer';
								div.style.marginBottom = '18px';
								div.addEventListener('mouseenter', function() {
									div.style.background = '#a2a9b3';
								});
								div.addEventListener('mouseleave', function() {
									div.style.background = '#b0b8c1';
								});
								div.addEventListener('click', function() {
									selectedItem = item;
									showDetails(item);
								});
								itemList.appendChild(div);
								if (idx === 0) {
									selectedItem = item;
								}
							});
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
							var labels = ['In house Drawings', 'Bill of materials', 'Parts and Suppliers'];
							labels.forEach(function(label, idx) {
								var li = document.createElement('li');
								li.style.display = 'flex';
								li.style.alignItems = 'center';
								li.style.gap = '8px';
								li.style.padding = '6px 0';
								// Subitem name
								var span = document.createElement('span');
								span.textContent = label;
								span.style.display = 'inline-block';
								span.style.width = '220px'; // Fixed width for alignment
								span.style.textAlign = 'left';
								li.appendChild(span);
								// Chevron icon (SVG)
								var chevron = document.createElement('span');
								chevron.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.5 6L8 9.5L11.5 6" stroke="#888" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
								chevron.style.display = 'inline-flex';
								chevron.style.alignItems = 'center';
								chevron.style.justifyContent = 'center';
								li.appendChild(chevron);
								subList.appendChild(li);
								if (idx < labels.length - 1) {
									var hr = document.createElement('hr');
									hr.style.border = 'none';
									hr.style.borderTop = '1px solid #d1d5db';
									hr.style.margin = '2px 0';
									hr.style.width = '250px';
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