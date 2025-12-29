<?php
require_once __DIR__ . '/../../session_init.php';
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
	header('Location: /auth/login.php');
	exit();
}
require_once __DIR__ . '/../../config/config.php';
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
		   height: 20px;
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
		padding: 18px 0;
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
					</style>
					</style>
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
									// Example: fetch from equipment and dimensions (replace with real join as needed)
									$sql = "SELECT e.equipment_id AS main_equipment_id, e.dhcst_equipment_number, e.dhss_equipment_number, e.make, d.* FROM equipments e LEFT JOIN dimensions d ON e.equipment_id = d.equipment_id ORDER BY e.equipment_id DESC";
									$result = $conn->query($sql);
									if ($result && $result->num_rows > 0) {
										while ($row = $result->fetch_assoc()) {
											// Ensure equipment_id is available for selection
											$equipment_id = isset($row['main_equipment_id']) ? (int)$row['main_equipment_id'] : 0;
											echo '<tr data-equipment-id="' . $equipment_id . '" data-debug-eid="' . htmlspecialchars($row['main_equipment_id']) . '" data-photo="' . htmlspecialchars($row['photos'] ?? '') . '">';
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
							<div class="dimension-image-list" id="dimensionImageList" style="min-height: 520px; max-height: 800px;">
								<span class="no-image">No image selected</span>
							</div>
							<input type="file" id="dimensionImageInput" accept="image/*" multiple style="display:none;" />
							<div style="display:flex;gap:10px;align-items:center;justify-content:center;margin-top:8px;">
								<button id="addImageBtn" disabled style="opacity:0.5;cursor:not-allowed;">Add Image</button>
								<button id="uploadImagesBtn" style="display:none;">Upload Selected</button>
							</div>
						</div>
					</div>
						<script>
						// Make table rows selectable and show images for selected row
						document.addEventListener('DOMContentLoaded', function() {
							var tableBody = document.getElementById('dimensionTableBody');
							var imageList = document.getElementById('dimensionImageList');

							var addImageBtn = document.getElementById('addImageBtn');
							var uploadImagesBtn = document.getElementById('uploadImagesBtn');
							var imageInput = document.getElementById('dimensionImageInput');
							var selectedRow = null;
							var selectedEquipmentId = null;
							var selectedFiles = [];


							function fetchAndShowImages(equipmentId) {
								imageList.innerHTML = '<span class="no-image">Loading...</span>';
								var countMsg = document.getElementById('dimensionImageCountMsg');
								countMsg.textContent = '';
								fetch('/PortalSite/api/get_equipment_uploads.php?equipment_id=' + encodeURIComponent(equipmentId))
									.then(res => res.json())
									.then(data => {
										imageList.innerHTML = '';
										if (data.success && data.uploads && data.uploads.dimension && data.uploads.dimension.length > 0) {
											countMsg.textContent = data.uploads.dimension.length + ' image' + (data.uploads.dimension.length > 1 ? 's' : '') + ' added';
											addImageBtn.textContent = 'Add More';
											addImageBtn.classList.add('add-more');
											data.uploads.dimension.forEach(function(upload) {
												var img = document.createElement('img');
												img.src = upload.file_url;
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
											// Show friendly message if no images found
											var msg = document.createElement('span');
											msg.className = 'no-image';
											msg.textContent = 'No image uploaded for this equipment.';
											imageList.innerHTML = '';
											imageList.appendChild(msg);
										}
										// Always clear preview and hide upload button after refresh
										clearSelectedPreviews();
									})
									.catch((err) => {
										countMsg.textContent = '';
										imageList.innerHTML = '<span class="no-image">No image available (fetch error)</span>';
									});
							}

							function showSelectedPreviews(files) {
								clearSelectedPreviews();
								if (!files || files.length === 0) return;
								var previewDiv = document.createElement('div');
								previewDiv.id = 'dimensionImagePreviewList';
								previewDiv.style.display = 'flex';
								previewDiv.style.flexWrap = 'wrap';
								previewDiv.style.gap = '8px';
								Array.from(files).forEach(function(file) {
									var reader = new FileReader();
									var img = document.createElement('img');
									img.style.maxWidth = '90px';
									img.style.maxHeight = '90px';
									img.style.border = '1px solid #ccc';
									img.style.borderRadius = '6px';
									img.style.objectFit = 'cover';
									reader.onload = function(e) {
										img.src = e.target.result;
									};
									reader.readAsDataURL(file);
									previewDiv.appendChild(img);
								});
								imageList.insertBefore(previewDiv, imageList.firstChild);
							}

							function clearSelectedPreviews() {
								var previewDiv = document.getElementById('dimensionImagePreviewList');
								if (previewDiv) previewDiv.remove();
								selectedFiles = [];
								uploadImagesBtn.style.display = 'none';
							}

							// Always show image panel, even if no row is selected
							imageList.innerHTML = '<span class="no-image">No image selected</span>';

							tableBody.addEventListener('click', function(e) {
								var tr = e.target.closest('tr');
								if (!tr) return;
								// Remove selection from previous row
								if (selectedRow) selectedRow.classList.remove('selected');
								// Select new row
								tr.classList.add('selected');
								selectedRow = tr;
								// Get equipment_id from data attribute
								var equipmentId = tr.getAttribute('data-equipment-id');
								selectedEquipmentId = equipmentId;
								// Enable Add Image button if valid equipmentId
								if (equipmentId && parseInt(equipmentId) > 0) {
									addImageBtn.disabled = false;
									addImageBtn.style.opacity = 1;
									addImageBtn.style.cursor = '';
									fetchAndShowImages(equipmentId);
								} else {
									addImageBtn.disabled = true;
									addImageBtn.style.opacity = 0.5;
									addImageBtn.style.cursor = 'not-allowed';
									imageList.innerHTML = '<span class="no-image">No image available</span>';
								}
							});


							addImageBtn.addEventListener('click', function() {
								if (!selectedEquipmentId || parseInt(selectedEquipmentId) <= 0) {
									return;
								}
								imageInput.value = '';
								imageInput.click();
							});


							imageInput.addEventListener('change', function() {
								if (!selectedEquipmentId || !imageInput.files.length) return;
								selectedFiles = Array.from(imageInput.files);
								showSelectedPreviews(selectedFiles);
								uploadImagesBtn.style.display = 'inline-block';
							});

							uploadImagesBtn.addEventListener('click', function() {
								if (!selectedEquipmentId || !selectedFiles.length) return;
								uploadImagesBtn.disabled = true;
								addImageBtn.disabled = true;
								addImageBtn.style.opacity = 0.5;
								var uploads = selectedFiles.map(function(file) {
									var formData = new FormData();
									formData.append('equipment_id', selectedEquipmentId);
									formData.append('file', file);
									return fetch('/PortalSite/api/add_equipment_upload.php', {
										method: 'POST',
										body: formData
									}).then(res => res.json());
								});
								Promise.all(uploads).then(function(results) {
									var msg = '';
									var successCount = 0;
									var errorCount = 0;
									results.forEach(function(r) {
										if (r && r.success) successCount++;
										else errorCount++;
									});
									if (successCount > 0) {
										msg += successCount + ' image' + (successCount > 1 ? 's' : '') + ' uploaded successfully. ';
									}
									if (errorCount > 0) {
										msg += errorCount + ' image' + (errorCount > 1 ? 's' : '') + ' failed to upload.';
									}
									var countMsg = document.getElementById('dimensionImageCountMsg');
									countMsg.textContent = msg;
									fetchAndShowImages(selectedEquipmentId);
									uploadImagesBtn.disabled = false;
									addImageBtn.disabled = false;
									addImageBtn.style.opacity = 1;
								});
							});
						});
						</script>
					<style>
					.dimension-table tbody tr.selected td {
						background: #c7d2fe !important;
					}
					</style>
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
								<div style="margin-bottom:12px;"><label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">L.O.A.</label><input type="text" name="loa" id="edit_loa" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;"></div>
								<div style="margin-bottom:18px;"><label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Photos</label><input type="text" name="photos" id="edit_photos" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;"></div>
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
