<?php
require_once __DIR__ . '/../../session_init.php';
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
	header('Location: /auth/login.php');
	exit();
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';
// Hide admin-only UI elements for non-admin users
if (!is_admin()) {
		echo <<<'HTML'
<style>.admin-only, .edit-filter-btn, .edit-dimension-btn, .edit-tire-btn, .upload-btn, .editEquipmentBtn, .delete-equipment, .uploadFilterBtn, .add-equipment-btn { display: none !important; }</style>
<script>
(function(){
	var patterns=[/\bedit\b/i,/\bupload\b/i,/\bdelete\b/i,/\badd\b/i,/\bremove\b/i];
	function hideIfMatch(el){
		// Skip the uploadImagesBtn - it's controlled by JavaScript logic
		if (el.id === 'uploadImagesBtn' || el.id === 'uploadBtnContainer') return;
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
$dimensionUploads = [];
if (isset($uploads['dimension'])) {
	foreach ($uploads['dimension'] as $row) {
		$fileUrl = $row['file_url'] ?? '';
		$fileUrl = str_replace('\\', '/', $fileUrl);
		$fileUrl = ltrim($fileUrl, '/');
		$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
		if ($isProduction) {
			if (strpos($fileUrl, 'PortalSite/uploads/equipment/') === 0 || strpos($fileUrl, 'uploads/equipment/') === 0) {
				$fileUrl = '/' . $fileUrl;
			} else {
				$fileUrl = '/uploads/equipment/' . $fileUrl;
			}
		} else {
			if (strpos($fileUrl, 'PortalSite/uploads/equipment/') === 0) {
				$fileUrl = '/' . $fileUrl;
			} elseif (strpos($fileUrl, 'uploads/equipment/') === 0) {
				$fileUrl = '/PortalSite/' . $fileUrl;
			} else {
				$fileUrl = '/PortalSite/uploads/equipment/' . $fileUrl;
			}
		}
		$fileUrl = preg_replace('#/+#', '/', $fileUrl);
		$ext = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));
		$isImage = in_array($ext, ['jpg','jpeg','png','gif','bmp','webp','svg']);
		$dimensionUploads[] = [
			'url' => $fileUrl,
			'name' => basename($fileUrl),
			'isImage' => $isImage,
			'uploaded_at' => $row['uploaded_at'] ?? '',
			'id' => $row['id'] ?? ''
		];
	}
}
?>
<style>
	.dimension-table-area {
		overflow-x: auto;
		margin-left: 0;
		margin-right: 0;
		max-width: 60vw;
	}
	.dimension-table {
		border-collapse: separate;
		border-spacing: 0;
		width: 100%;
		min-width: 0;
		background: #fff;
		border-radius: 16px;
		box-shadow: 0 4px 24px rgba(30,41,59,0.08);
		overflow: hidden;
		table-layout: fixed;
	}
	.dimension-table thead th {
		width: 8.5%;
		max-width: 8.5%;
		min-width: 8.5%;
		text-align: center;
		vertical-align: middle;
		position: relative;
		font-size: 13px;
		font-weight: bold;
		padding: 2px 4px 2px 4px;
		border-bottom: 2px solid #d1d5db;
		z-index: 2;
		letter-spacing: 0.01em;
		white-space: pre-line;
		line-height: 1.05;
		word-break: break-word;
		color: #22223b;
		background: #f8fafc;
		border-right: 1px solid #e5e7eb;
	}
	.dimension-table thead th:last-child {
		border-right: none;
	}
	.dimension-table tbody td {
		width: 8.5%;
		max-width: 8.5%;
		min-width: 8.5%;
		text-align: center;
		vertical-align: middle;
		font-size: 13px;
		font-weight: 400;
		padding: 1px 4px 1px 4px;
		border-bottom: 1px solid #f1f1f1;
		background: #fff;
		line-height: 1.05;
		height: 20px;
		white-space: nowrap;
	}
	.dimension-table tbody tr:hover td,
	.dimension-table tbody tr:active td {
		background: #e0e7ff;
		transition: background 0.2s;
		height: 28px;
	}
	.dimension-table tbody td {
		font-weight: 400;
		border-bottom: 1px solid #f1f1f1;
		background: #fff;
	}
	.dimension-table tbody tr:nth-child(even) td {
		background: #f8fafc;
	}
	.dimension-table tbody tr:hover td {
		background: #e0e7ff;
		transition: background 0.2s;
	}
	.dimension-table tbody tr:last-child td {
		border-bottom: none;
	}
	.dimension-table tbody tr.selected td {
		background: #c7d2fe !important;
	}
	.edit-dimension-btn {
		visibility: hidden;
		background: #667eea;
		color: #fff;
		border: none;
		border-radius: 8px;
		padding: 8px 18px;
		font-size: 15px;
		font-weight: 700;
		cursor: pointer;
		box-shadow: 0 2px 8px #0002;
		transition: background 0.15s, transform 0.1s;
		margin: 0 auto;
		letter-spacing: 0.01em;
		outline: none;
	}
	.edit-dimension-btn:hover {
		background: #3b4cca;
		color: #fff;
		transform: translateY(-1px) scale(1.04);
		box-shadow: 0 4px 16px #0002;
	}
	.dimension-table tbody tr:hover .edit-dimension-btn {
		visibility: visible;
	}
	.edit-dimension-btn svg {
		margin-right: 7px;
		vertical-align: middle;
	}
	.dimension-image-list {
		width: 100%;
		min-height: 320px;
		max-height: 480px;
		overflow-y: auto;
		display: flex;
		flex-direction: column;
		gap: 18px;
		align-items: center;
		justify-content: flex-start;
		background: #fff;
		border-radius: 10px;
		box-shadow: 0 1px 4px #0001;
		margin-bottom: 18px;
		padding: 18px;
		position: relative;
	}
	.dimension-image-list img {
		width: 100%;
		height: auto;
		border-radius: 12px;
		box-shadow: 0 2px 8px #0002;
		margin: 0 auto 18px auto;
		display: block;
		object-fit: cover;
		aspect-ratio: 16/9;
	}
	.dimension-image-list .no-image {
		color: #aaa;
		font-size: 1.1rem;
		text-align: center;
		width: 100%;
	}
	#addImageBtn {
		background: #6c7ae0;
		color: #fff;
		border: none;
		border-radius: 8px;
		padding: 10px 32px;
		font-size: 16px;
		font-weight: 600;
		cursor: pointer;
		box-shadow: 0 2px 6px rgba(156, 163, 175, 0.15);
		margin-top: 12px;
		margin-bottom: 0;
		width: 90%;
		display: block;
		transition: all 0.2s ease;
	}
	#addImageBtn:hover:not(:disabled) {
		background: #5a68d0;
		transform: translateY(-2px);
		box-shadow: 0 4px 12px rgba(108, 122, 224, 0.3);
	}
	#addImageBtn:disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}
	#dimensionImagePanel {
		flex: 1 1 0;
		min-width: 320px;
		max-width: 520px;
		background: #f8fafc;
		border-radius: 14px;
		box-shadow: 0 2px 8px #0001;
		padding: 32px 18px;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: flex-start;
		min-height: 520px;
	}
	
	/* Professional Upload Button Styling */
	#uploadBtnContainer {
		display: none;
		width: 100%;
		padding: 12px 0;
		margin-bottom: 16px;
		background: #667eea; /* Changed from gradient to solid color */
		border-radius: 12px;
		box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
		text-align: center;
	}
	
	#uploadBtnContainer.visible {
		display: block;
	}
	
	#uploadImagesBtn {
		display: inline-block !important;
		background: #fff;
		color: #667eea;
		border: none;
		border-radius: 8px;
		padding: 12px 32px;
		font-size: 16px;
		font-weight: 700;
		cursor: pointer;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		transition: all 0.2s ease;
		letter-spacing: 0.5px;
		text-transform: uppercase;
		font-size: 14px;
	}
	
	#uploadImagesBtn:hover:not(:disabled) {
		background: #f0f0f0;
		transform: translateY(-2px);
		box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
	}
	
	#uploadImagesBtn:active:not(:disabled) {
		transform: translateY(0);
	}
	
	#uploadImagesBtn:disabled {
		opacity: 0.6;
		cursor: not-allowed;
		background: #e5e7eb;
		color: #9ca3af;
	}
	
	#dimensionImagePreviewList {
		width: 100%;
		display: flex;
		flex-wrap: wrap;
		gap: 12px;
		padding: 16px;
		background: #f9fafb;
		border-radius: 8px;
		margin-bottom: 12px;
		border: 2px dashed #d1d5db;
	}
	
	#dimensionImagePreviewList img {
		width: 100px;
		height: 100px;
		object-fit: cover;
		border-radius: 8px;
		border: 2px solid #667eea;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
	}
</style>
<?php
require_once __DIR__ . '/../../session_init.php';
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
	header('Location: /auth/login.php');
	exit();
}
require_once __DIR__ . '/../../config/config.php';
$email = $_SESSION['email'];
$roleStmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$roleStmt->bind_param('s', $email);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$user = $roleRes ? $roleRes->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$roleStmt->close();
$previewParam = '';
if (isset($_GET['preview_role'])) {
	$previewParam = '?preview_role=' . urlencode($_GET['preview_role']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
	<meta name="theme-color" content="#667eea" />
	<title>Dimension Cheat Sheet</title>
	<link rel="stylesheet" href="../../assets/css/base.css" />
	<link rel="stylesheet" href="../../assets/css/admin-layout.css" />
	<link rel="stylesheet" href="../../assets/css/dashboard.css" />
</head>
<body class="admin-page">
	<div class="admin-container">
		<?php include __DIR__ . '/../../partials/portalheader.php'; ?>
		<div class="admin-layout">
			<?php include __DIR__ . '/../../partials/sidebar.php'; ?>
			<main class="content-area">
				<div class="main-content" style="margin-top: 32px;">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; width: 100%;">
						<div style="flex: 1; display: flex; align-items: center;">
							<a href="index.php" class="equipment-btn equipment-btn--secondary" style="padding: 10px 28px; border-radius: 8px; font-weight: 600; font-size: 15px; background: #f3f4f6; color: #6b7280; border: none; text-decoration: none; display: inline-block; margin: 0; transition: background 0.2s;">&larr; Back to Equipments</a>
						</div>
						<div style="flex: 2; text-align: center;">
							<h1 class="admin-page-title" style="font-size: 2.5rem; font-weight: 700; color: #374151; margin: 0;">Dimension Cheat Sheet</h1>
						</div>
						<div style="flex: 1; display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
							<button id="downloadTableBtn" class="download-print-btn" style="margin-right: 18px;">
								<span class="icon" aria-hidden="true" style="display:inline-flex;align-items:center;">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
								</span>
								<span>Download CSV</span>
							</button>
							<button id="printTableBtn" class="download-print-btn">
								<span class="icon" aria-hidden="true" style="display:inline-flex;align-items:center;">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="9" width="12" height="7" rx="2"/><path d="M6 17v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2"/><polyline points="6 9 6 4 18 4 18 9"/><line x1="9" y1="13" x2="15" y2="13"/></svg>
								</span>
								<span>Print Table</span>
							</button>
						</div>
					</div>
					<div style="display: flex; flex-direction: row; gap: 40px; align-items: flex-start; min-height: 480px; width: 100%;">
						<div class="dimension-table-area" style="flex: 2 1 0; min-width: 700px; max-width: 70vw;">
							<table class="dimension-table">
								<thead>
									<tr>
										<th>DHCS<br>#</th>
										<th>DHSS<br>#</th>
										<th>Make</th>
										<th>Total<br>Height</th>
										<th>Ground<br>Clearance</th>
										<th>Total<br>Width</th>
										<th>Axle<br>Width</th>
										<th>Weight</th>
										<th>Length<br>to Back<br>of Rear<br>Tire</th>
										<th>Length<br>to Back<br>of Auger</th>
										<th>L.O.A.</th>
									</tr>
								</thead>
								<tbody id="dimensionTableBody">
									<?php
									$sql = "SELECT e.equipment_id AS main_equipment_id, e.dhcst_equipment_number, e.dhss_equipment_number, e.make, d.* FROM equipments e LEFT JOIN dimensions d ON e.equipment_id = d.equipment_id ORDER BY e.equipment_id ASC";
									$result = $conn->query($sql);
									if ($result && $result->num_rows > 0) {
										$tempId = 1;
										while ($row = $result->fetch_assoc()) {
											$equipment_id = isset($row['main_equipment_id']) && $row['main_equipment_id'] ? htmlspecialchars($row['main_equipment_id']) : '';
											$rowDisabled = false;
											if (!$equipment_id || $equipment_id === '0') {
												// Fallback: assign a unique temp id, but mark row as disabled
												$equipment_id = 'temp_' . $tempId++;
												$rowDisabled = true;
											}
											echo '<tr data-equipment-id="' . $equipment_id . '" data-debug-eid="' . $equipment_id . '" data-photo="' . htmlspecialchars($row['photos'] ?? '') . '"' . ($rowDisabled ? ' class="disabled-row" style="opacity:0.5;pointer-events:none;"' : '') . '>';
											echo '<td>' . htmlspecialchars($row['dhcst_equipment_number'] ?? '') . '</td>';
											echo '<td>' . htmlspecialchars($row['dhss_equipment_number'] ?? '') . '</td>';
											echo '<td>' . htmlspecialchars($row['make'] ?? '') . '</td>';
											echo '<td>' . htmlspecialchars($row['total_height'] ?? '') . '</td>';
											echo '<td>' . htmlspecialchars($row['ground_clearance'] ?? '') . '</td>';
											echo '<td>' . htmlspecialchars($row['total_width'] ?? '') . '</td>';
											echo '<td>' . htmlspecialchars($row['axle_width'] ?? '') . '</td>';
											echo '<td>' . htmlspecialchars($row['weight'] ?? '') . '</td>';
											echo '<td>' . htmlspecialchars($row['length_rear_tire'] ?? '') . '</td>';
											echo '<td>' . htmlspecialchars($row['length_auger'] ?? '') . '</td>';
											echo '<td>' . htmlspecialchars($row['loa'] ?? '') . '</td>';
											echo '</tr>';
										}
									} else {
										echo '<tr><td colspan="12" style="color:#888;font-style:italic;">No dimension data found.</td></tr>';
									}
									?>
								</tbody>
							</table>
						</div>
						<div id="dimensionImagePanel" class="no-print" style="flex: 0 0 600px; max-width: 600px; min-width: 400px; margin-left: auto;">
							<div style="width:100%;text-align:center;margin-bottom:8px;">
								<span id="dimensionImageCountMsg" style="color:#374151;font-weight:600;font-size:1.1rem;"></span>
							</div>
							
							<!-- Upload Button Container -->
							<div id="uploadBtnContainer">
								<button id="uploadImagesBtn" disabled>
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;margin-right:8px;">
										<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
										<polyline points="17 8 12 3 7 8"></polyline>
										<line x1="12" y1="3" x2="12" y2="15"></line>
									</svg>
									Upload Selected
								</button>
							</div>
							
							<div class="dimension-image-list" id="dimensionImageList" style="min-height: 520px; max-height: 800px;">
								<span class="no-image">Select an equipment row to view images</span>
							</div>
							<input type="file" id="dimensionImageInput" accept="image/*" multiple style="display:none;" />
							<div style="display:flex;gap:10px;align-items:center;justify-content:center;margin-top:8px;">
								<button id="addImageBtn" disabled style="opacity:0.5;cursor:not-allowed;">Add Image</button>
							</div>
						</div>
					</div>
					
					<script>
					document.addEventListener('DOMContentLoaded', function() {
						var tableBody = document.getElementById('dimensionTableBody');
						var imageList = document.getElementById('dimensionImageList');
						var addImageBtn = document.getElementById('addImageBtn');
						var uploadImagesBtn = document.getElementById('uploadImagesBtn');
						var uploadBtnContainer = document.getElementById('uploadBtnContainer');
						var imageInput = document.getElementById('dimensionImageInput');
						var countMsg = document.getElementById('dimensionImageCountMsg');
						var selectedRow = null;
						var selectedEquipmentId = null;
						var selectedFiles = [];

						function isValidEquipmentId(eid) {
							// Accept only non-empty, non-zero, non-temp ids
							return eid && eid !== '0' && !/^temp_/.test(eid);
						}

						function normalizeImageUrl(url) {
							url = url.replace(/\\/g, '/').replace(/\/+/g, '/').replace(/^\/+/, '/');
							if (window.location.pathname.indexOf('/PortalSite/') === 0) {
								if (url.indexOf('/PortalSite/uploads/equipment/') === 0) return url;
								if (url.indexOf('/uploads/equipment/') === 0) return '/PortalSite' + url;
							} else {
								if (url.indexOf('/uploads/equipment/') === 0) return url;
								if (url.indexOf('/PortalSite/uploads/equipment/') === 0) return url.replace('/PortalSite', '');
							}
							if (url.indexOf('uploads/equipment/') !== -1) {
								return url.indexOf('/') === 0 ? url : '/' + url;
							}
							return url;
						}

						function fetchAndShowImages(equipmentId) {
							imageList.innerHTML = '<span class="no-image">Loading...</span>';
							countMsg.textContent = '';
							clearSelectedPreviews();
							selectedFiles = [];
							uploadImagesBtn.disabled = true;
							uploadBtnContainer.classList.remove('visible');
							imageInput.value = '';
							// Use relative path that works in both local and production
							var apiUrl = window.location.pathname.includes('/PortalSite/') 
								? '/PortalSite/api/get_equipment_uploads.php' 
								: '/api/get_equipment_uploads.php';
							fetch(apiUrl + '?equipment_id=' + encodeURIComponent(equipmentId))
								.then(function(res){
									return res.text().then(function(text){
										try {
											return JSON.parse(text);
										} catch (err) {
											console.error('get_equipment_uploads: invalid JSON response', text);
											return { success: false, error: 'Invalid JSON response', raw: text };
										}
									});
								})
								.then(data => {
									imageList.innerHTML = '';
									if (data && data.success && data.uploads && data.uploads.dimension && data.uploads.dimension.length > 0) {
										countMsg.textContent = data.uploads.dimension.length + ' image' + (data.uploads.dimension.length > 1 ? 's' : '') + ' added';
										addImageBtn.textContent = 'Add More';
										addImageBtn.classList.add('add-more');
										data.uploads.dimension.forEach(function(upload) {
											var img = document.createElement('img');
											img.src = normalizeImageUrl(upload.file_url);
											img.alt = 'Equipment Photo';
											img.onerror = function() {
												var errSpan = document.createElement('span');
												errSpan.className = 'no-image';
												errSpan.textContent = 'Error loading image';
												img.replaceWith(errSpan);
											};
											imageList.appendChild(img);
										});
									} else {
										countMsg.textContent = '';
										addImageBtn.textContent = 'Add Image';
										addImageBtn.classList.remove('add-more');
										var msg = document.createElement('span');
										msg.className = 'no-image';
										msg.textContent = 'No image uploaded for this equipment.';
										imageList.appendChild(msg);
									}
								})
								.catch((err) => {
									console.error('Fetch error:', err);
									countMsg.textContent = '';
									imageList.innerHTML = '<span class="no-image">Error loading images</span>';
								});
						}

						function showSelectedPreviews(files) {
							clearSelectedPreviews();
							if (!files || files.length === 0) {
								uploadBtnContainer.classList.remove('visible');
								uploadImagesBtn.disabled = true;
								return;
							}
							var previewDiv = document.createElement('div');
							previewDiv.id = 'dimensionImagePreviewList';
							Array.from(files).forEach(function(file) {
								var reader = new FileReader();
								var img = document.createElement('img');
								reader.onload = function(e) {
									img.src = e.target.result;
								};
								reader.onerror = function(e) {
									img.alt = 'Error loading preview';
								};
								reader.readAsDataURL(file);
								previewDiv.appendChild(img);
							});
							imageList.insertBefore(previewDiv, imageList.firstChild);
							uploadBtnContainer.classList.add('visible');
							uploadImagesBtn.disabled = false;
						}

						function clearSelectedPreviews() {
							var previewDiv = document.getElementById('dimensionImagePreviewList');
							if (previewDiv) previewDiv.remove();
							uploadBtnContainer.classList.remove('visible');
							uploadImagesBtn.disabled = true;
							imageInput.value = '';
						}

						tableBody.addEventListener('click', function(e) {
							var tr = e.target.closest('tr');
							if (!tr || tr.classList.contains('disabled-row')) return;
							if (selectedRow) selectedRow.classList.remove('selected');
							tr.classList.add('selected');
							selectedRow = tr;
							var equipmentId = tr.getAttribute('data-equipment-id');
							if (isValidEquipmentId(equipmentId)) {
								selectedEquipmentId = equipmentId;
								addImageBtn.disabled = false;
								addImageBtn.style.opacity = 1;
								addImageBtn.style.cursor = 'pointer';
								addImageBtn.style.display = '';
								addImageBtn.classList.remove('hidden');
								fetchAndShowImages(equipmentId);
							} else {
								selectedEquipmentId = null;
								addImageBtn.disabled = true;
								addImageBtn.style.opacity = 0.5;
								addImageBtn.style.cursor = 'not-allowed';
								addImageBtn.style.display = '';
								addImageBtn.classList.remove('hidden');
								imageList.innerHTML = '<span class="no-image">No valid equipment ID</span>';
								clearSelectedPreviews();
							}
						});

						addImageBtn.addEventListener('click', function() {
							if (!isValidEquipmentId(selectedEquipmentId)) {
								alert('Please select a valid equipment row first.');
								return;
							}
							imageInput.value = '';
							imageInput.click();
						});

						imageInput.addEventListener('change', function(e) {
							if (!isValidEquipmentId(selectedEquipmentId)) {
								alert('Please select a valid equipment row first.');
								imageInput.value = '';
								return;
							}
							if (!imageInput.files || imageInput.files.length === 0) {
								clearSelectedPreviews();
								selectedFiles = [];
								return;
							}
							selectedFiles = Array.from(imageInput.files);
							showSelectedPreviews(selectedFiles);
						});

						uploadImagesBtn.addEventListener('click', function(e) {
							e.preventDefault();
							if (!isValidEquipmentId(selectedEquipmentId)) {
								alert('Please select a valid equipment row from the table first.');
								return;
							}
							if (!selectedFiles || selectedFiles.length === 0) {
								alert('Please select images to upload first by clicking "Add Image".');
								return;
							}
							uploadImagesBtn.disabled = true;
							addImageBtn.disabled = true;
							addImageBtn.style.opacity = 0.5;
							countMsg.textContent = 'Uploading ' + selectedFiles.length + ' image(s)...';
							countMsg.style.color = '#667eea';
							var uploads = selectedFiles.map(function(file) {
								var formData = new FormData();
								formData.append('equipment_id', selectedEquipmentId);
								formData.append('file', file);
								formData.append('field', 'dimension');
								// Use relative path that works in both local and production
								var apiUrl = window.location.pathname.includes('/PortalSite/') 
									? '/PortalSite/api/add_equipment_upload.php' 
									: '/api/add_equipment_upload.php';
								return fetch(apiUrl, {
									method: 'POST',
									body: formData
								}).then(function(res){
									return res.text().then(function(text){
										try {
											return JSON.parse(text);
										} catch (err) {
											console.error('add_equipment_upload: invalid JSON response', text);
											return { success: false, error: 'Invalid JSON response', raw: text };
										}
									});
								}).catch(function(err){
									console.error('Network error during upload', err);
									return { success: false, error: 'Network error', raw: err && err.message ? err.message : String(err) };
								});
							});
							Promise.all(uploads).then(function(results) {
								var success = results.filter(r => r && r.success).length;
								var fail = results.length - success;
								countMsg.style.display = 'block';
								
								// Log failed results for debugging
								if (fail > 0) {
									console.log('Upload failures:', results.filter(r => !r || !r.success));
								}
								
								if (success > 0 && fail === 0) {
									countMsg.textContent = success + ' image(s) uploaded successfully!';
									countMsg.style.color = '#22c55e';
								} else if (success > 0 && fail > 0) {
									countMsg.textContent = success + ' uploaded, ' + fail + ' failed.';
									countMsg.style.color = '#eab308';
								} else {
									// Show specific error if available
									var errorMsg = 'Upload failed.';
									if (results.length > 0 && results[0] && results[0].error) {
										errorMsg = 'Upload failed: ' + results[0].error;
									} else if (results.length > 0 && results[0] && results[0].raw) {
										console.error('Raw error response:', results[0].raw);
										errorMsg = 'Upload failed. Check console for details.';
									}
									countMsg.textContent = errorMsg;
									countMsg.style.color = '#dc2626';
								}
								imageInput.value = '';
								setTimeout(function(){
									fetchAndShowImages(selectedEquipmentId);
									clearSelectedPreviews();
									addImageBtn.disabled = false;
									addImageBtn.style.opacity = 1;
									addImageBtn.style.cursor = 'pointer';
								}, 1200);
							}).catch(function(err){
								imageInput.value = '';
								countMsg.textContent = 'Upload failed: ' + (err && err.message ? err.message : 'Network error');
								countMsg.style.color = '#dc2626';
							});
						});
					});
					</script>
					
					<!-- Edit Dimension Modal -->
					<div id="editDimensionModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:10000; align-items:center; justify-content:center;">
						<div style="background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.2); padding:32px 28px; min-width:400px; max-width:96vw;">
							<h3 style="margin-bottom:18px; font-size:1.3rem; font-weight:700; color:#374151;">Edit Dimension Info</h3>
							<form id="editDimensionForm">
								<input type="hidden" name="dimension_id" id="edit_dimension_id">
								<div style="margin-bottom:12px;"><label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">DHCS #</label><input type="text" name="dhcs_number" id="edit_dhcs_number" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;"></div>
								<div style="margin-bottom:12px;"><label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">DHSS #</label><input type="text" name="dhss_number" id="edit_dhss_number" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;"></div>
								<div style="margin-bottom:12px;"><label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Make</label><input type="text" name="make" id="edit_make" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;"></div>
								<div style="margin-bottom:12px;"><label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Total Height</label><input type="text" name="total_height" id="edit_total_height" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;"></div>
								<div style="margin-bottom:12px;"><label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Ground Clearance</label><input type="text" name="ground_clearance" id="edit_ground_clearance" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;"></div>
								<div style="margin-bottom:12px;"><label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Total Width</label><input type="text" name="total_width" id="edit_total_width" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;"></div>
								<div style="margin-bottom:12px;"><label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Axle Width</label><input type="text" name="axle_width" id="edit_axle_width" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;"></div>
								<div style="margin-bottom:12px;"><label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Weight</label><input type="text" name="weight" id="edit_weight" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;"></div>
								<div style="margin-bottom:12px;"><label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Length to Back of Rear Tire</label><input type="text" name="length_rear_tire" id="edit_length_rear_tire" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;"></div>
								<div style="margin-bottom:12px;"><label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Length to Back of Auger</label><input type="text" name="length_auger" id="edit_length_auger" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;"></div>
								<div style="margin-bottom:18px;"><label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">L.O.A.</label><input type="text" name="loa" id="edit_loa" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;"></div>
								<div style="display:flex;gap:16px;justify-content:flex-end;">
									<button type="button" id="cancelEditDimensionBtn" style="background:#e5e7eb;color:#374151;border:none;border-radius:6px;padding:10px 24px;font-size:15px;font-weight:600;cursor:pointer;transition:background 0.15s;">Cancel</button>
									<button type="submit" style="background:#43b77a;color:#fff;border:none;border-radius:6px;padding:10px 24px;font-size:15px;font-weight:600;cursor:pointer;transition:background 0.15s;">Save</button>
								</div>
							</form>
						</div>
					</div>
				</div>
			</main>
		</div>
	</div>
	<script src="../../assets/js/mobile-menu.js"></script>
	<script src="../../assets/js/logout-confirm.js"></script>
	<script>
	// Print only the table (excluding photos)
	document.getElementById('printTableBtn').addEventListener('click', function() {
		var table = document.querySelector('.dimension-table').outerHTML;
		var win = window.open('', '', 'width=900,height=700');
		win.document.write('<html><head><title>Print Table</title>');
		win.document.write('<link rel="stylesheet" href="../../assets/css/base.css" />');
		win.document.write('<style>body{background:#fff!important;} .dimension-table{margin-top:24px;} th,td{font-size:13px;padding:4px 8px;} </style>');
		win.document.write('</head><body>');
		win.document.write(table);
		win.document.write('</body></html>');
		win.document.close();
		win.focus();
		setTimeout(function(){ win.print(); win.close(); }, 400);
	});

	// Download table as CSV (excluding photos, fix header names)
	document.getElementById('downloadTableBtn').addEventListener('click', function() {
		var table = document.querySelector('.dimension-table');
		var rows = Array.from(table.querySelectorAll('tr'));
		var csv = rows.map(function(row) {
			var cells = Array.from(row.querySelectorAll('th,td'));
			return cells.map(function(cell) {
				// Replace <br> with space for CSV header
				var text = cell.innerHTML.replace(/<br\s*\/?>(\s*)?/gi, ' ');
				return '"' + text.replace(/"/g, '""').trim() + '"';
			}).join(',');
		}).join('\n');
		var blob = new Blob([csv], { type: 'text/csv' });
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = 'dimension_cheat_sheet.csv';
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	});
	</script>
	<style>
	@media print {
		.no-print, .no-print * {
			display: none !important;
		}
		.main-content {
			box-shadow: none !important;
			background: #fff !important;
		}
	}
	.download-print-btn {
		padding: 10px 22px 10px 16px;
		border-radius: 12px;
		font-weight: 600;
		font-size: 15px;
		cursor: pointer;
		border: none;
		background: #667eea;
		color: #fff;
		margin-right: 18px;
		transition: background 0.18s, color 0.18s, box-shadow 0.18s, transform 0.1s;
		box-shadow: 0 2px 8px #0001;
		outline: none;
		text-align: center;
		min-width: 140px;
		display: inline-flex;
		align-items: center;
		gap: 10px;
	}
	.download-print-btn:last-child {
		margin-right: 0;
	}
	.download-print-btn .icon {
		font-size: 20px;
		display: inline-block;
		vertical-align: middle;
		transition: color 0.18s;
	}
	.download-print-btn:active, .download-print-btn.active {
		background: #667eea !important;
		color: #fff !important;
		box-shadow: 0 2px 8px #0001;
	}
	.download-print-btn:active .icon, .download-print-btn.active .icon {
		color: #fff !important;
		stroke: #fff !important;
	}
	.download-print-btn:hover, .download-print-btn:focus {
		background: #f3f4f6 !important;
		color: #3b4cca !important;
		box-shadow: 0 4px 16px #0002;
		transform: translateY(-2px) scale(1.04);
		text-decoration: none;
	}
	.download-print-btn:hover .icon, .download-print-btn:focus .icon {
		color: #3b4cca !important;
		stroke: #3b4cca !important;
	}
	</style>
</body>
</html>