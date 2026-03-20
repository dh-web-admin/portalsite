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
							<div id="buildNewMenu" style="position: relative; display: inline-flex; align-items: stretch;">
								<button type="button" id="buildNewBtn" style="padding: 8px 14px; background: #5b7fa3; color: #fff; border: none; border-radius: 4px 0 0 4px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center;">Build new</button>
								<button type="button" id="buildNewChevronBtn" aria-expanded="false" aria-haspopup="true" style="padding: 8px 10px; background: #4f7293; color: #fff; border: none; border-left: 1px solid rgba(255,255,255,0.22); border-radius: 0 4px 4px 0; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">
									<span style="display:inline-flex;align-items:center;justify-content:center;">
										<svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
											<path d="M4.5 6L8 9.5L11.5 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</span>
								</button>
								<div id="buildNewDropdown" style="display:none; position:absolute; top:calc(100% + 8px); left:0; min-width:180px; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 24px rgba(15,23,42,0.16); padding:8px; z-index:1200;">
									<button type="button" id="viewDraftsBtn" style="display:flex; align-items:center; padding:10px 12px; background:#f8fafc; color:#334155; border-radius:6px; font-weight:700; text-decoration:none; border:none; cursor:pointer; width:100%;">View Drafts</button>
								</div>
							</div>

							<!-- Modal for View Drafts -->
							<div id="viewDraftsModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:2400;align-items:center;justify-content:center;padding:24px;">
								<div style="background:#fff;border-radius:14px;box-shadow:0 24px 60px rgba(15,23,42,0.22);width:min(700px,96vw);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
									<div style="padding:22px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:16px;">
										<h2 style="margin:0;font-size:20px;color:#0f172a;">Draft Equipment</h2>
										<button type="button" id="closeViewDraftsModalBtn" style="background:transparent;border:none;font-size:28px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">&times;</button>
									</div>
									<div id="viewDraftsModalBody" style="padding:22px;overflow:auto;display:grid;gap:16px;background:#fff;">
										<div style="text-align:center;color:#64748b;padding:20px;">Loading drafts...</div>
									</div>
									<div style="display:flex;justify-content:flex-end;gap:10px;padding:18px 22px;border-top:1px solid #e2e8f0;background:#f8fafc;">
										<button type="button" id="closeViewDraftsBtn" style="padding:10px 16px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;color:#334155;font-weight:700;cursor:pointer;">Close</button>
									</div>
								</div>
							</div>
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
					<div id="uploadDrawingsModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:5000; align-items:center; justify-content:center;">
						<div style="position:relative; z-index:5001; background:#fff; border-radius:8px; box-shadow:0 2px 16px #b0b8c1; padding:32px 24px; min-width:420px; max-width:90vw;">
							<h3 id="uploadDrawingsModalTitle" style="margin-top:0; margin-bottom:6px; font-size:1.2em;">Upload Drawings</h3>
							<div id="uploadDrawingsModalTarget" style="margin-bottom:18px; font-size:0.92em; color:#64748b;">Select files to upload.</div>
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
										<div style="margin-top:10px;">
											<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Lnk</label>
											<input type="text" class="make-lnk" placeholder="https://..." style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
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
													<div>
														<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Supplier Part Number</label>
														<input type="text" class="make-supplier-part-number" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
													</div>
													<div>
														<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Lnk</label>
														<input type="text" class="make-supplier-lnk" placeholder="https://..." style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
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
					<!-- Modal for Add Member Assembly -->
					<div id="addMaterialModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1000; align-items:center; justify-content:center;">
						<div style="background:#fff; border-radius:8px; box-shadow:0 2px 16px #b0b8c1; padding:32px 24px; min-width:380px; max-width:90vw;">
							<div style="margin-bottom:20px;">
								<h3 style="margin:0; margin-bottom:4px; font-size:1.2em;">Add Member Assembly</h3>
								<div id="materialNumberHeader" style="font-size:0.9em; color:#64748b; font-weight:500;">Number: #...</div>
							</div>
							<div style="margin-bottom:18px;">
								<label style="display:block; margin-bottom:6px; font-weight:500;">Name *</label>
								<input id="materialNameInput" type="text" placeholder="Enter material name" style="width:100%; padding:8px; border-radius:4px; border:1px solid #b0b8c1; font-size:1em;" />
							</div>
							<div style="display:flex; gap:12px; justify-content:flex-end;">
								<button id="saveMaterialBtn" style="padding:8px 18px; background:#5b7fa3; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Save</button>
								<button id="cancelMaterialBtn" style="padding:8px 18px; background:#b0b8c1; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Cancel</button>
							</div>
						</div>
					</div>
					<!-- Modal for Add/Edit Material Part -->
					<div id="addMaterialPartModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1001; align-items:center; justify-content:center; overflow-y:auto;">
						<div style="background:#fff; border-radius:8px; box-shadow:0 2px 16px #b0b8c1; padding:32px 24px; min-width:600px; max-width:90vw; margin:20px auto;">
							<div style="margin-bottom:20px;">
								<h3 id="materialPartModalTitle" style="margin:0; margin-bottom:4px; font-size:1.2em;">Add Part</h3>
								<div style="margin-top:10px; max-width:220px;">
									<label for="materialPartIdInput" style="display:block; margin-bottom:6px; font-size:0.9em; color:#64748b; font-weight:600;">Part ID *</label>
									<input id="materialPartIdInput" type="text" placeholder="e.g. 2a" style="width:100%; padding:8px 10px; border-radius:6px; border:1px solid #b0b8c1; font-size:0.95em; font-weight:600; color:#334155;" />
								</div>
								<div style="margin-top:12px; padding:8px 12px; background:#f0f9ff; border-left:3px solid #3b82f6; border-radius:4px;">
									<span style="font-size:0.85em; color:#1e40af; font-style:italic;">All unit of measurements are in inches</span>
								</div>
							</div>
							<div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px;">
								<div>
									<label style="display:block; margin-bottom:6px; font-weight:500;">Name *</label>
									<input id="materialPartNameInput" type="text" placeholder="Enter part name" style="width:100%; padding:8px; border-radius:4px; border:1px solid #b0b8c1; font-size:1em;" />
								</div>
								<div>
									<label style="display:block; margin-bottom:6px; font-weight:500;">Make</label>
									<input id="materialPartMakeInput" type="text" placeholder="Enter make" style="width:100%; padding:8px; border-radius:4px; border:1px solid #b0b8c1; font-size:1em;" />
								</div>
								<div>
									<label style="display:block; margin-bottom:6px; font-weight:500;">Part Number</label>
									<input id="materialPartNumberInput" type="text" placeholder="Enter part number" style="width:100%; padding:8px; border-radius:4px; border:1px solid #b0b8c1; font-size:1em;" />
								</div>
								<div>
									<label style="display:block; margin-bottom:6px; font-weight:500;">Material Type</label>
									<input id="materialPartTypeInput" type="text" placeholder="Enter material type" style="width:100%; padding:8px; border-radius:4px; border:1px solid #b0b8c1; font-size:1em;" />
								</div>
								<div>
									<label style="display:block; margin-bottom:6px; font-weight:500;">Thickness</label>
									<input id="materialPartThicknessInput" type="text" placeholder="Enter thickness" style="width:100%; padding:8px; border-radius:4px; border:1px solid #b0b8c1; font-size:1em;" />
								</div>
								<div>
									<label style="display:block; margin-bottom:6px; font-weight:500;">Length</label>
									<input id="materialPartLengthInput" type="text" placeholder="Enter length" style="width:100%; padding:8px; border-radius:4px; border:1px solid #b0b8c1; font-size:1em;" />
								</div>
								<div>
									<label style="display:block; margin-bottom:6px; font-weight:500;">Width</label>
									<input id="materialPartWidthInput" type="text" placeholder="Enter width" style="width:100%; padding:8px; border-radius:4px; border:1px solid #b0b8c1; font-size:1em;" />
								</div>
								<div>
									<label style="display:block; margin-bottom:6px; font-weight:500;">Area</label>
									<input id="materialPartAreaInput" type="text" placeholder="Enter area" style="width:100%; padding:8px; border-radius:4px; border:1px solid #b0b8c1; font-size:1em;" />
								</div>
								<div>
									<label style="display:block; margin-bottom:6px; font-weight:500;">Quantity</label>
									<input id="materialPartQuantityInput" type="text" placeholder="Enter quantity" style="width:100%; padding:8px; border-radius:4px; border:1px solid #b0b8c1; font-size:1em;" />
								</div>
							</div>
							<div style="margin-top:20px;border-top:1px solid #e2e8f0;padding-top:16px;display:grid;gap:10px;">
								<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
									<div>
										<div style="font-size:13px;font-weight:700;color:#0f172a;">Part Drawings</div>
										<div style="font-size:12px;color:#64748b;">Upload drawings for this part after the part has been saved.</div>
									</div>
									<div style="display:flex;align-items:center;gap:8px;">
										<button type="button" id="materialPartDrawingsHistoryBtn" style="display:none;padding:8px 14px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;color:#c2410c;font-weight:700;cursor:pointer;">View previous versions</button>
										<button type="button" id="materialPartDrawingsUploadBtn" style="padding:8px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;color:#1d4ed8;font-weight:700;cursor:pointer;">Upload drawings</button>
									</div>
								</div>
								<div id="materialPartDrawingsList" style="display:grid;gap:8px;padding:12px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;min-height:58px;"></div>
							</div>
							<div style="display:flex; gap:12px; justify-content:flex-end; margin-top:24px;">
								<button id="deleteMaterialPartBtn" style="display:none; padding:8px 18px; background:#ef4444; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Delete</button>
								<button id="saveMaterialPartBtn" style="padding:8px 18px; background:#5b7fa3; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Save</button>
								<button id="cancelMaterialPartBtn" style="padding:8px 18px; background:#b0b8c1; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Cancel</button>
							</div>
						</div>
					</div>
					<!-- Modal for Build New Equipment -->
					<div id="buildNewModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:2400;align-items:center;justify-content:center;padding:24px;">
						<div style="background:#fff;border-radius:14px;box-shadow:0 24px 60px rgba(15,23,42,0.22);width:min(500px,96vw);display:flex;flex-direction:column;gap:0;overflow:hidden;">
							<div style="padding:22px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:16px;">
								<h2 style="margin:0;font-size:20px;color:#0f172a;">Build New Equipment</h2>
								<button type="button" id="closeBuildNewModalBtn" style="background:transparent;border:none;font-size:28px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">&times;</button>
							</div>
							<form id="buildNewModalForm" style="padding:22px;display:grid;gap:18px;">
								<div>
									<label style="display:block;font-weight:600;color:#0f172a;margin-bottom:8px;">Equipment #</label>
									<input type="text" id="modalEquipmentNumber" placeholder="Enter equipment number" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;box-sizing:border-box;" />
								</div>
								<div>
									<label style="display:block;font-weight:600;color:#0f172a;margin-bottom:8px;">Type</label>
									<input type="text" id="modalEquipmentType" placeholder="Enter type" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;box-sizing:border-box;" />
								</div>
							</form>
							<div style="display:flex;justify-content:flex-end;gap:10px;padding:18px 22px;border-top:1px solid #e2e8f0;background:#f8fafc;">
								<button type="button" id="cancelBuildNewModalBtn" style="padding:10px 16px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;color:#334155;font-weight:700;cursor:pointer;">Cancel</button>
								<button type="button" id="proceedBuildNewModalBtn" style="padding:10px 16px;background:#5b7fa3;border:none;border-radius:8px;color:#fff;font-weight:700;cursor:pointer;">Proceed</button>
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
						var buildNewMenu = document.getElementById('buildNewMenu');
						var buildNewBtn = document.getElementById('buildNewBtn');
						var buildNewChevronBtn = document.getElementById('buildNewChevronBtn');
						var buildNewDropdown = document.getElementById('buildNewDropdown');
						var buildNewModal = document.getElementById('buildNewModal');
						var closeBuildNewModalBtn = document.getElementById('closeBuildNewModalBtn');
						var cancelBuildNewModalBtn = document.getElementById('cancelBuildNewModalBtn');
						var proceedBuildNewModalBtn = document.getElementById('proceedBuildNewModalBtn');
						var modalEquipmentNumber = document.getElementById('modalEquipmentNumber');
						var modalEquipmentType = document.getElementById('modalEquipmentType');
						var addBtn = document.getElementById('addItemBtn');
						var modal = document.getElementById('addItemModal');
						var saveBtn = document.getElementById('saveItemBtn');
						var cancelBtn = document.getElementById('cancelItemBtn');
						var input = document.getElementById('itemNameInput');
						var itemList = document.getElementById('itemList');
						var items = [];

						function openBuildNewModal() {
							if (!buildNewModal) return;
							buildNewModal.style.display = 'flex';
							modalEquipmentNumber && modalEquipmentNumber.focus();
						}

						function closeBuildNewModal() {
							if (!buildNewModal) return;
							buildNewModal.style.display = 'none';
							if (modalEquipmentNumber) modalEquipmentNumber.value = '';
							if (modalEquipmentType) modalEquipmentType.value = '';
						}

						if (buildNewBtn) {
							buildNewBtn.addEventListener('click', openBuildNewModal);
						}
						if (closeBuildNewModalBtn) {
							closeBuildNewModalBtn.addEventListener('click', closeBuildNewModal);
						}
						if (cancelBuildNewModalBtn) {
							cancelBuildNewModalBtn.addEventListener('click', closeBuildNewModal);
						}
						if (proceedBuildNewModalBtn) {
							proceedBuildNewModalBtn.addEventListener('click', function() {
								var number = modalEquipmentNumber ? modalEquipmentNumber.value.trim() : '';
								var type = modalEquipmentType ? modalEquipmentType.value.trim() : '';
								
								fetch(apiBase + '/save_equipment_draft.php', {
									method: 'POST',
									headers: { 'Content-Type': 'application/json' },
									body: JSON.stringify({ number: number, type: type })
								})
								.then(function(res) { return res.json(); })
								.then(function(data) {
									if (data.success) {
										try {
											localStorage.setItem('buildNewEquipment', JSON.stringify({
												number: number,
												type: type,
												draftId: data.draft_id
											}));
										} catch (e) {
											console.warn('Could not store equipment data in localStorage');
										}
										closeBuildNewModal();
										window.open('build_new.php', '_blank');
									} else {
										alert(data.message || 'Failed to save draft equipment');
									}
								})
								.catch(function(err) {
									alert('Error saving draft equipment: ' + err.message);
								});
							});
						}
						if (buildNewModal) {
							buildNewModal.addEventListener('click', function(e) {
								if (e.target === buildNewModal) closeBuildNewModal();
							});
						}

						var viewDraftsBtn = document.getElementById('viewDraftsBtn');
						var viewDraftsModal = document.getElementById('viewDraftsModal');
						var closeViewDraftsModalBtn = document.getElementById('closeViewDraftsModalBtn');
						var closeViewDraftsBtn = document.getElementById('closeViewDraftsBtn');
						var viewDraftsModalBody = document.getElementById('viewDraftsModalBody');

						function openViewDraftsModal() {
							if (!viewDraftsModal) return;
							toggleBuildNewDropdown(false);
							viewDraftsModal.style.display = 'flex';
							loadDrafts();
						}

						function closeViewDraftsModal() {
							if (!viewDraftsModal) return;
							viewDraftsModal.style.display = 'none';
						}

						function loadDrafts() {
							if (!viewDraftsModalBody) return;
							viewDraftsModalBody.innerHTML = '<div style="text-align:center;color:#64748b;padding:20px;">Loading drafts...</div>';
							
							fetch(apiBase + '/get_equipment_drafts.php')
								.then(function(res) { return res.json(); })
								.then(function(data) {
									if (!data.success || !Array.isArray(data.drafts)) {
										viewDraftsModalBody.innerHTML = '<div style="padding:16px;color:#991b1b;">Failed to load drafts.</div>';
										return;
									}
									
									if (data.drafts.length === 0) {
										viewDraftsModalBody.innerHTML = '<div style="padding:20px;text-align:center;color:#64748b;">No draft equipment found.</div>';
										return;
									}
									
									viewDraftsModalBody.innerHTML = data.drafts.map(function(draft) {
										var createdDate = draft.created_at ? new Date(draft.created_at).toLocaleDateString() : '';
										return '' +
											'<div style="padding:16px;border:1px solid #e2e8f0;border-radius:8px;display:flex;justify-content:space-between;align-items:center;gap:16px;">' +
												'<div style="flex:1;">' +
													'<div style="font-size:16px;font-weight:700;color:#0f172a;">' + (draft.equipment_name || 'Unnamed') + '</div>' +
													'<div style="font-size:12px;color:#64748b;margin-top:4px;">' +
														(draft.equipment_number ? 'Equipment #: ' + draft.equipment_number + ' | ' : '') +
														(draft.equipment_type ? 'Type: ' + draft.equipment_type : '') +
													'</div>' +
													'<div style="font-size:11px;color:#94a3b8;margin-top:4px;">Created: ' + createdDate + '</div>' +
												'</div>' +
												'<button type="button" class="continue-building-btn" data-draft-id="' + draft.id + '" data-equipment-name="' + (draft.equipment_name || '').replace(/"/g, '&quot;') + '" data-equipment-number="' + (draft.equipment_number || '').replace(/"/g, '&quot;') + '" data-equipment-type="' + (draft.equipment_type || '').replace(/"/g, '&quot;') + '" style="padding:10px 18px;background:#5b7fa3;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;white-space:nowrap;">Continue Building</button>' +
											'</div>';
									}).join('');
									
									// Attach click handlers to continue building buttons
									Array.prototype.forEach.call(viewDraftsModalBody.querySelectorAll('.continue-building-btn'), function(btn) {
										btn.addEventListener('click', function() {
											var draftId = this.getAttribute('data-draft-id');
											var equipmentName = this.getAttribute('data-equipment-name');
											var equipmentNumber = this.getAttribute('data-equipment-number');
											var equipmentType = this.getAttribute('data-equipment-type');
											
											// Store equipment data in localStorage
											try {
												localStorage.setItem('buildNewEquipment', JSON.stringify({
													name: equipmentName,
													number: equipmentNumber,
													type: equipmentType,
													draftId: draftId
												}));
											} catch (e) {
												console.warn('Could not store equipment data in localStorage');
											}
											
											closeViewDraftsModal();
											window.open('build_new.php', '_blank');
										});
									});
								})
								.catch(function(err) {
									if (viewDraftsModalBody) {
										viewDraftsModalBody.innerHTML = '<div style="padding:16px;color:#991b1b;">Error loading drafts: ' + (err.message || 'Unknown error') + '</div>';
									}
								});
						}

						if (viewDraftsBtn) {
							viewDraftsBtn.addEventListener('click', openViewDraftsModal);
						}
						if (closeViewDraftsModalBtn) {
							closeViewDraftsModalBtn.addEventListener('click', closeViewDraftsModal);
						}
						if (closeViewDraftsBtn) {
							closeViewDraftsBtn.addEventListener('click', closeViewDraftsModal);
						}
						if (viewDraftsModal) {
							viewDraftsModal.addEventListener('click', function(e) {
								if (e.target === viewDraftsModal) closeViewDraftsModal();
							});
						}

						function toggleBuildNewDropdown(forceOpen) {
							if (!buildNewDropdown || !buildNewChevronBtn) return;
							var shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : buildNewDropdown.style.display === 'none';
							buildNewDropdown.style.display = shouldOpen ? 'block' : 'none';
							buildNewChevronBtn.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
						}

						if (buildNewChevronBtn) {
							buildNewChevronBtn.addEventListener('click', function(e) {
								e.preventDefault();
								e.stopPropagation();
								toggleBuildNewDropdown();
							});
						}

						document.addEventListener('click', function(e) {
							if (!buildNewMenu || !buildNewDropdown) return;
							if (!buildNewMenu.contains(e.target)) {
								toggleBuildNewDropdown(false);
							}
						});

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
						var selectedItemStorageKey = 'engineering:selectedItemId';

						function loadStoredSelectedItemId() {
							try {
								var rawValue = sessionStorage.getItem(selectedItemStorageKey);
								if (!rawValue) return null;
								return String(rawValue);
							} catch (e) {
								return null;
							}
						}

						function persistSelectedItemId(itemId) {
							try {
								if (itemId === null || typeof itemId === 'undefined') {
									sessionStorage.removeItem(selectedItemStorageKey);
									return;
								}
								sessionStorage.setItem(selectedItemStorageKey, String(itemId));
							} catch (e) {
								// Ignore storage errors (e.g., disabled storage).
							}
						}

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
							if (selectedItemId === null) {
								selectedItemId = loadStoredSelectedItemId();
							}
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
								var isSelected = selectedItemId !== null && String(item.id) === String(selectedItemId);
								setItemCardStyle(div, isSelected, false);
								if (isSelected) {
									hasSelectedInList = true;
									selectedItem = item;
								}
								div.addEventListener('mouseenter', function() {
									var hoverSelected = selectedItemId !== null && String(item.id) === String(selectedItemId);
									setItemCardStyle(div, hoverSelected, true);
								});
								div.addEventListener('mouseleave', function() {
									var leaveSelected = selectedItemId !== null && String(item.id) === String(selectedItemId);
									setItemCardStyle(div, leaveSelected, false);
								});
								div.addEventListener('click', function() {
									selectedItem = item;
									selectedItemId = item.id;
									persistSelectedItemId(selectedItemId);
									refreshSelectedItemStyles();
									showDetails(item);
								});
								itemList.appendChild(div);
							});
							if (!hasSelectedInList && selectedItemId !== null && sortedItems.length > 0) {
								selectedItemId = sortedItems[0].id;
								selectedItem = sortedItems[0];
								persistSelectedItemId(selectedItemId);
							}
							if (!hasSelectedInList && selectedItemId === null && sortedItems.length > 0) {
								selectedItemId = sortedItems[0].id;
								selectedItem = sortedItems[0];
								persistSelectedItemId(selectedItemId);
							}
							if (sortedItems.length === 0) {
								selectedItemId = null;
								persistSelectedItemId(null);
							}
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
							title.style.fontWeight = '700'; // Bold for prominence
							title.style.fontSize = '1.6em'; // Larger header size
							title.style.color = '#0f172a'; // Darker, more distinguished color
							title.style.letterSpacing = '0.5px'; // More letter spacing
							title.style.paddingBottom = '16px'; // Add padding below
							title.style.borderBottom = '2px solid #d1d5db'; // Add distinguishing bottom border
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
											openUploadDrawingsModal({
												type: 'item',
												itemId: item.id,
												targetLabel: item.name
											});
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

						function openUploadDrawingsModal(context) {
							var modal = document.getElementById('uploadDrawingsModal');
							var filesInput = document.getElementById('drawingFilesInput');
							var preview = document.getElementById('selectedFilesPreview');
							var title = document.getElementById('uploadDrawingsModalTitle');
							var target = document.getElementById('uploadDrawingsModalTarget');
							currentDrawingsUploadContext = context || {
								type: 'item',
								itemId: currentItemForDrawings ? currentItemForDrawings.id : 0,
								targetLabel: currentItemForDrawings ? currentItemForDrawings.name : ''
							};
							
							filesInput.value = '';
							preview.innerHTML = '';
							if (title) {
								title.textContent = currentDrawingsUploadContext.type === 'part' ? 'Upload Part Drawings' : 'Upload Drawings';
							}
							if (target) {
								target.textContent = currentDrawingsUploadContext.type === 'part'
									? 'Part: ' + (currentDrawingsUploadContext.targetLabel || '')
									: 'Item: ' + (currentDrawingsUploadContext.targetLabel || 'Engineering item');
							}
							modal.style.display = 'flex';
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
										var emptyStatePlaceholder = document.createElement('div');
										emptyStatePlaceholder.id = 'bomEmptyState';
										emptyStatePlaceholder.style.display = 'none'; // Hidden by default, will show only if no materials
										emptyStatePlaceholder.textContent = 'No BOMs available.';
										emptyStatePlaceholder.style.padding = '10px 16px 6px 16px';
										emptyStatePlaceholder.style.fontSize = '0.92em';
										emptyStatePlaceholder.style.color = '#6b7280';
										emptyStatePlaceholder.style.fontStyle = 'italic';
										dropdown.appendChild(emptyStatePlaceholder);
									}

									// Add materials section
									var materialsHeader = document.createElement('div');
									materialsHeader.style.padding = '10px 16px 6px 16px';
									materialsHeader.style.fontWeight = '700';
									materialsHeader.style.fontSize = '0.92em';
									materialsHeader.style.color = '#334155';
									materialsHeader.style.display = 'flex';
									materialsHeader.style.justifyContent = 'space-between';
									materialsHeader.style.alignItems = 'center';
									materialsHeader.style.borderTop = '1px solid #e5e7eb';
									materialsHeader.style.marginTop = '8px';
									materialsHeader.style.paddingTop = '12px';
									
									// Add Member Assembly button
									var addMemberBtn = document.createElement('button');
									addMemberBtn.textContent = '+ Add Member Assembly';
									addMemberBtn.style.padding = '4px 12px';
									addMemberBtn.style.background = '#5b7fa3';
									addMemberBtn.style.color = '#fff';
									addMemberBtn.style.border = 'none';
									addMemberBtn.style.borderRadius = '4px';
									addMemberBtn.style.fontWeight = '600';
									addMemberBtn.style.fontSize = '0.85em';
									addMemberBtn.style.cursor = 'pointer';
									addMemberBtn.style.marginRight = '12px';
									addMemberBtn.addEventListener('click', function(e) {
										e.stopPropagation();
										openAddMaterialModal(item);
									});
									materialsHeader.appendChild(addMemberBtn);
									
									var materialsTitle = document.createElement('span');
									materialsTitle.textContent = 'Materials';
									materialsHeader.appendChild(materialsTitle);
									
									dropdown.appendChild(materialsHeader);
									
									if (liElement.parentNode) {
										liElement.parentNode.insertBefore(dropdown, liElement.nextSibling);
									}
									
									// Fetch and display existing materials
									fetch(apiBase + '/get_engineering_materials.php?item_id=' + item.id)
										.then(function(res) { return res.json(); })
										.then(function(materialData) {
											if (materialData.success && materialData.materials && materialData.materials.length > 0) {
												var materialsContainer = document.createElement('div');
												materialsContainer.style.padding = '6px 12px 10px 12px';
												
												materialData.materials.forEach(function(material) {
																	// Create a wrapper to bundle material and its parts
																	var materialWrapper = document.createElement('div');
																	materialWrapper.classList.add('material-wrapper');
																	materialWrapper.style.border = '2px solid #d1d5db';
																	materialWrapper.style.borderRadius = '6px';
																	materialWrapper.style.marginBottom = '8px';
																	materialWrapper.style.overflow = 'hidden';
																	
																	var materialRow = document.createElement('div');
																	materialRow.setAttribute('data-material-id', material.id);
																	materialRow.style.padding = '10px 12px';
																	materialRow.style.display = 'flex';
																	materialRow.style.alignItems = 'center';
																	materialRow.style.fontSize = '0.93em';
																	materialRow.style.color = '#1f2937';
																	materialRow.style.background = '#f9fafb';
																	materialRow.style.cursor = 'pointer';
													
													var numberSpan = document.createElement('span');
													numberSpan.textContent = '#' + material.number;
													numberSpan.style.fontWeight = '700';
													numberSpan.style.color = '#5b7fa3';
													numberSpan.style.marginRight = '12px';
													numberSpan.style.minWidth = '50px';
													
													var nameSpan = document.createElement('span');
													nameSpan.textContent = material.name;
													nameSpan.style.flex = '1';
													
													// Add Parts button
													var addPartsBtn = document.createElement('button');
													addPartsBtn.textContent = '+ Add Parts';
													addPartsBtn.style.padding = '4px 12px';
													addPartsBtn.style.background = '#5b7fa3';
													addPartsBtn.style.color = '#fff';
													addPartsBtn.style.border = 'none';
													addPartsBtn.style.borderRadius = '4px';
													addPartsBtn.style.fontWeight = '600';
													addPartsBtn.style.fontSize = '0.85em';
													addPartsBtn.style.cursor = 'pointer';
													addPartsBtn.style.marginLeft = '12px';
													addPartsBtn.addEventListener('click', function(e) {
														e.stopPropagation();
														openAddMaterialPartModal(material);
													});
													
													var deleteBtn = document.createElement('button');
													deleteBtn.textContent = '✕';
													deleteBtn.style.background = 'transparent';
													deleteBtn.style.border = 'none';
													deleteBtn.style.color = '#ef4444';
													deleteBtn.style.cursor = 'pointer';
													deleteBtn.style.marginLeft = '16px';
													deleteBtn.style.marginRight = '8px';
													deleteBtn.style.fontSize = '15px';
													deleteBtn.addEventListener('click', function(e) {
														e.stopPropagation();
														if (confirm('Delete this material?')) {
															fetch(apiBase + '/delete_engineering_material.php', {
																method: 'POST',
																headers: { 'Content-Type': 'application/json' },
																body: JSON.stringify({ id: material.id })
															})
															.then(function(r) {
																return r.text().then(function(text) {
																	var j = text.indexOf('{"success"');
																	if (j < 0) j = text.lastIndexOf('{');
																	try { return JSON.parse(j >= 0 ? text.slice(j) : text); } catch(e) { return {success:false}; }
																});
															})
															.then(function(res) {
																if (res.success) {
																	materialWrapper.remove();
																} else {
																	alert('Failed to delete material');
																}
															})
															.catch(function(err) { console.error('Delete error:', err); });
														}
													});
													
													// Chevron icon
													var chevron = document.createElement('span');
													chevron.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.5 6L8 9.5L11.5 6" stroke="#888" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
													chevron.style.display = 'inline-flex';
													chevron.style.alignItems = 'center';
													chevron.style.justifyContent = 'center';
													chevron.style.cursor = 'pointer';
													chevron.style.padding = '4px';
													chevron.style.borderRadius = '4px';
													chevron.style.transition = 'background 0.2s, transform 0.2s';
													
													chevron.addEventListener('mouseenter', function() {
														chevron.style.background = '#f0f0f0';
													});
													chevron.addEventListener('mouseleave', function() {
														chevron.style.background = 'transparent';
													});
													
													// Click handler for entire row to toggle dropdown
													materialRow.addEventListener('click', function(e) {
														// Check if there's already a dropdown for this material
														var existingMaterialDropdown = materialWrapper.querySelector('.material-dropdown');
														var isDropdown = existingMaterialDropdown !== null;
														
														if (isDropdown) {
															existingMaterialDropdown.remove();
															chevron.style.transform = 'rotate(0deg)';
														} else {
															// Open the dropdown
															chevron.style.transform = 'rotate(180deg)';
															
															// Create dropdown
															var materialDropdown = document.createElement('div');
															materialDropdown.classList.add('material-dropdown');
															materialDropdown.style.padding = '12px 16px';
															materialDropdown.style.background = '#ffffff';
															materialDropdown.style.borderTop = '2px solid #e5e7eb';
															
															// Append dropdown to wrapper (not materials container)
															materialWrapper.appendChild(materialDropdown);
															var bomDropdown = document.getElementById('bomDropdown');
															if (bomDropdown) {
																bomDropdown.style.maxHeight = (bomDropdown.scrollHeight + 40) + 'px';
															}
															
															// Fetch and display parts for this material
															fetch(apiBase + '/get_material_parts.php?material_id=' + material.id)
																.then(function(res) { return res.json(); })
																.then(function(partsData) {
																	if (partsData.success && partsData.parts && partsData.parts.length > 0) {
																		// Create parts list container
																		var partsListContainer = document.createElement('div');
																		partsListContainer.style.marginTop = '8px';
																		partsListContainer.style.borderTop = '1px solid #e5e7eb';
																		partsListContainer.style.paddingTop = '8px';
																		
																		partsData.parts.forEach(function(part) {
																			var partRow = document.createElement('div');
																			partRow.style.padding = '10px 12px 10px 20px';
																			partRow.style.background = '#fafbfc';
																			partRow.style.borderRadius = '4px';
																			partRow.style.marginBottom = '4px';
																			partRow.style.marginLeft = '8px';
																			partRow.style.cursor = 'pointer';
																			partRow.style.fontSize = '0.88em';
																			partRow.style.display = 'flex';
																			partRow.style.alignItems = 'center';
																			partRow.style.gap = '16px';
																			partRow.style.border = '1px solid #e5e7eb';
																			partRow.style.borderLeft = '3px solid #5b7fa3';
																			
																			// Part number badge
																			var numberSpan = document.createElement('span');
																			numberSpan.textContent = '#' + part.number;
																			numberSpan.style.fontWeight = '700';
																			numberSpan.style.color = '#5b7fa3';
																			numberSpan.style.minWidth = '40px';
																			
																			// Name (prominent)
																			var nameSpan = document.createElement('span');
																			nameSpan.textContent = part.name;
																			nameSpan.style.color = '#1f2937';
																			nameSpan.style.fontWeight = '600';
																			nameSpan.style.minWidth = '120px';
																			
																			// Make
																			var makeSpan = document.createElement('span');
																			makeSpan.innerHTML = '<span style="color: #6b7280;">Make:</span> ' + (part.make || '-');
																			makeSpan.style.color = '#1f2937';
																			makeSpan.style.minWidth = '150px';
																			
																			// Material Type
																			var materialTypeSpan = document.createElement('span');
																			materialTypeSpan.innerHTML = '<span style="color: #6b7280;">Material Type:</span> ' + (part.material_type || '-');
																			materialTypeSpan.style.color = '#1f2937';
																			materialTypeSpan.style.minWidth = '180px';
																			
																			// Quantity
																			var quantitySpan = document.createElement('span');
																			quantitySpan.innerHTML = '<span style="color: #6b7280;">Quantity:</span> ' + (part.quantity || '-');
																			quantitySpan.style.color = '#1f2937';
																			quantitySpan.style.minWidth = '100px';

																			var versionSpan = document.createElement('span');
																			versionSpan.textContent = 'Version: V0';
																			versionSpan.style.color = '#1f2937';
																			versionSpan.style.minWidth = '95px';

																			var drawingIconButton = document.createElement('button');
																			drawingIconButton.type = 'button';
																			drawingIconButton.className = '';
																			drawingIconButton.style.display = 'inline-flex';
																			drawingIconButton.style.alignItems = 'center';
																			drawingIconButton.style.justifyContent = 'center';
																			drawingIconButton.style.lineHeight = '1';
																			drawingIconButton.style.marginLeft = '6px';
																			drawingIconButton.style.padding = '0';
																			drawingIconButton.style.border = 'none';
																			drawingIconButton.style.background = 'transparent';
																			drawingIconButton.style.boxShadow = 'none';
																			drawingIconButton.style.outline = 'none';
																			drawingIconButton.style.cursor = 'pointer';

																			var drawingIconImg = document.createElement('img');
																			drawingIconImg.src = getPartDrawingIconPath(false);
																			drawingIconImg.alt = 'no drawings';
																			drawingIconImg.style.width = '26.4px';
																			drawingIconImg.style.height = '26.4px';
																			drawingIconButton.appendChild(drawingIconImg);

																			wireBOMPartDrawingIcon(drawingIconButton, drawingIconImg, {
																				itemId: item.id,
																				partId: part.engineering_part_id,
																				targetLabel: '#' + part.number + ' ' + (part.name || '')
																			}, versionSpan);
																			
																			// Click for more detail
																			var detailText = document.createElement('span');
																			detailText.textContent = 'click for more detail';
																			detailText.style.color = '#9ca3af';
																			detailText.style.fontSize = '0.85em';
																			detailText.style.fontStyle = 'italic';
																			detailText.style.marginLeft = 'auto';
																			
																			partRow.appendChild(numberSpan);
																			partRow.appendChild(nameSpan);
																			partRow.appendChild(makeSpan);
																			partRow.appendChild(materialTypeSpan);
																			partRow.appendChild(quantitySpan);
																			partRow.appendChild(versionSpan);
																			partRow.appendChild(drawingIconButton);
																			partRow.appendChild(detailText);
																			
																			// Click to edit
																			partRow.addEventListener('click', function(e) {
																				e.stopPropagation();
																				openEditMaterialPartModal(part, material);
																			});
																			
																			partRow.addEventListener('mouseenter', function() {
																				partRow.style.background = '#f3f4f6';
																			});
																			partRow.addEventListener('mouseleave', function() {
																				partRow.style.background = '#fafbfc';
																			});
																			
																			partsListContainer.appendChild(partRow);
																		});
																		
																		materialDropdown.appendChild(partsListContainer);
																		
																		// Update parent BOM dropdown height after parts are loaded
																		var bomDropdown = document.getElementById('bomDropdown');
																		if (bomDropdown) {
																			setTimeout(function() {
																				bomDropdown.style.maxHeight = (bomDropdown.scrollHeight + 40) + 'px';
																			}, 50);
																		}
																	} else if (partsData.success) {
																		var emptyPartsState = document.createElement('div');
																		emptyPartsState.textContent = 'No parts found for this member assembly.';
																		emptyPartsState.style.padding = '8px 12px';
																		emptyPartsState.style.fontSize = '0.88em';
																		emptyPartsState.style.color = '#9ca3af';
																		emptyPartsState.style.fontStyle = 'italic';
																		materialDropdown.appendChild(emptyPartsState);
																	} else {
																		var errorPartsState = document.createElement('div');
																		errorPartsState.textContent = partsData.message || 'Failed to load parts for this member assembly.';
																		errorPartsState.style.padding = '8px 12px';
																		errorPartsState.style.fontSize = '0.88em';
																		errorPartsState.style.color = '#b91c1c';
																		materialDropdown.appendChild(errorPartsState);
																	}

																	var bomDropdown = document.getElementById('bomDropdown');
																	if (bomDropdown) {
																		setTimeout(function() {
																			bomDropdown.style.maxHeight = (bomDropdown.scrollHeight + 40) + 'px';
																		}, 50);
																	}
																});
														}
														
														// Also adjust height when closing
														var bomDropdown = document.getElementById('bomDropdown');
														if (bomDropdown && isDropdown) {
															setTimeout(function() {
																bomDropdown.style.maxHeight = (bomDropdown.scrollHeight + 40) + 'px';
															}, 50);
														}
													});
													
													materialRow.appendChild(numberSpan);
													materialRow.appendChild(nameSpan);
													materialRow.appendChild(addPartsBtn);
													materialRow.appendChild(deleteBtn);
													materialRow.appendChild(chevron);
													materialWrapper.appendChild(materialRow);
													materialsContainer.appendChild(materialWrapper);
												});
												
												dropdown.appendChild(materialsContainer);
												
												// Hide "No BOMs available" message since we have materials
												var bomEmpty = dropdown.querySelector('#bomEmptyState');
												if (bomEmpty) {
													bomEmpty.style.display = 'none';
												}
											} else {
												// Show "No BOMs available" only if there are no BOMs either
												if (!hasBoms) {
													var bomEmpty = dropdown.querySelector('#bomEmptyState');
													if (bomEmpty) {
														bomEmpty.style.display = 'block';
													}
												}
												
												var emptyMaterialsState = document.createElement('div');
												emptyMaterialsState.textContent = 'No materials added yet.';
												emptyMaterialsState.style.padding = '8px 16px 10px 16px';
												emptyMaterialsState.style.fontSize = '0.88em';
												emptyMaterialsState.style.color = '#9ca3af';
												emptyMaterialsState.style.fontStyle = 'italic';
												dropdown.appendChild(emptyMaterialsState);
											}
											
											// Trigger animation after materials are loaded
											setTimeout(function() {
												dropdown.style.maxHeight = (dropdown.scrollHeight + 40) + 'px';
												dropdown.style.opacity = '1';
												dropdown.style.transform = 'translateY(0)';
												dropdown.style.pointerEvents = 'auto';
											}, 10);
										});
								})
								.catch(function(err) {
									alert('Error loading BOMs: ' + err.message);
								})
								.finally(function() {
									bomDropdownBusy = false;
								});
						}

						// Material Modal functionality
						var currentItemForMaterial = null;
						function openAddMaterialModal(item) {
							currentItemForMaterial = item;
							var modal = document.getElementById('addMaterialModal');
							var nameInput = document.getElementById('materialNameInput');
							var numberHeader = document.getElementById('materialNumberHeader');
							var saveBtn = document.getElementById('saveMaterialBtn');
							var cancelBtn = document.getElementById('cancelMaterialBtn');
							
							// Clear inputs
							nameInput.value = '';
							numberHeader.textContent = 'Number: #...';
							
							// Fetch next number
							fetch(apiBase + '/get_engineering_materials.php?item_id=' + item.id)
								.then(function(res) { return res.json(); })
								.then(function(data) {
									if (data.success) {
										var maxNumber = 0;
										if (data.materials && data.materials.length > 0) {
											data.materials.forEach(function(mat) {
												if (mat.number > maxNumber) maxNumber = mat.number;
											});
										}
										numberHeader.textContent = 'Number: #' + (maxNumber + 1);
									}
								});
							
							// Show modal
							modal.style.display = 'flex';
							nameInput.focus();
							
							// Handle save
							saveBtn.onclick = function() {
								var name = nameInput.value.trim();
								if (!name) {
									alert('Please enter a material name');
									nameInput.focus();
									return;
								}
								
								fetch(apiBase + '/add_engineering_material.php', {
									method: 'POST',
									headers: { 'Content-Type': 'application/json' },
									body: JSON.stringify({ 
										name: name,
										item_id: currentItemForMaterial.id 
									})
								})
								.then(function(res) {
									return res.text().then(function(text) {
										var jsonStart = text.indexOf('{"success"');
										if (jsonStart < 0) jsonStart = text.lastIndexOf('{');
										var jsonText = jsonStart >= 0 ? text.slice(jsonStart) : text;
										try {
											return JSON.parse(jsonText);
										} catch (e) {
											console.error('Raw response:', text);
											throw new Error('Invalid JSON response from server');
										}
									});
								})
								.then(function(data) {
									if (data.success) {
										modal.style.display = 'none';
										// Find the BOM dropdown and add the new material directly
										var bomDropdown = document.getElementById('bomDropdown');
										if (bomDropdown) {
											// Find or create materials container
											var materialsContainer = bomDropdown.querySelector('div[style*="padding: 6px 12px 10px 12px"]');
											if (!materialsContainer) {
												// Create materials container if it doesn't exist
												materialsContainer = document.createElement('div');
												materialsContainer.style.padding = '6px 12px 10px 12px';
												// Remove any "no materials" message
												var emptyMsg = Array.from(bomDropdown.children).find(function(el) {
													return el.textContent && el.textContent.includes('No materials');
												});
												if (emptyMsg) emptyMsg.remove();
												bomDropdown.appendChild(materialsContainer);
											}
											
											// Create the new material wrapper and row
											var materialWrapper = document.createElement('div');
											materialWrapper.classList.add('material-wrapper');
											materialWrapper.style.border = '2px solid #d1d5db';
											materialWrapper.style.borderRadius = '6px';
											materialWrapper.style.marginBottom = '8px';
											materialWrapper.style.overflow = 'hidden';
											
											var materialRow = document.createElement('div');
											materialRow.setAttribute('data-material-id', data.id);
											materialRow.style.padding = '10px 12px';
											materialRow.style.display = 'flex';
											materialRow.style.alignItems = 'center';
											materialRow.style.fontSize = '0.93em';
											materialRow.style.color = '#1f2937';
											materialRow.style.background = '#f9fafb';
											materialRow.style.cursor = 'pointer';
											
											var numberSpan = document.createElement('span');
											numberSpan.textContent = '#' + data.number;
											numberSpan.style.fontWeight = '700';
											numberSpan.style.color = '#5b7fa3';
											numberSpan.style.marginRight = '12px';
											numberSpan.style.minWidth = '50px';
											
											var nameSpan = document.createElement('span');
											nameSpan.textContent = data.name;
											nameSpan.style.flex = '1';
											
											var addPartsBtn = document.createElement('button');
											addPartsBtn.textContent = '+ Add Parts';
											addPartsBtn.style.padding = '4px 12px';
											addPartsBtn.style.background = '#5b7fa3';
											addPartsBtn.style.color = '#fff';
											addPartsBtn.style.border = 'none';
											addPartsBtn.style.borderRadius = '4px';
											addPartsBtn.style.fontWeight = '600';
											addPartsBtn.style.fontSize = '0.85em';
											addPartsBtn.style.cursor = 'pointer';
											addPartsBtn.style.marginLeft = '12px';
											addPartsBtn.addEventListener('click', function(e) {
												e.stopPropagation();
												openAddMaterialPartModal({ id: data.id, number: data.number, name: data.name });
											});
											
											var deleteBtn = document.createElement('button');
											deleteBtn.textContent = '✕';
											deleteBtn.style.background = 'transparent';
											deleteBtn.style.border = 'none';
											deleteBtn.style.color = '#ef4444';
											deleteBtn.style.cursor = 'pointer';
											deleteBtn.style.marginLeft = '16px';
											deleteBtn.style.marginRight = '8px';
											deleteBtn.style.fontSize = '15px';
											deleteBtn.addEventListener('click', function(e) {
												e.stopPropagation();
												if (confirm('Delete this material?')) {
													fetch(apiBase + '/delete_engineering_material.php', {
														method: 'POST',
														headers: { 'Content-Type': 'application/json' },
														body: JSON.stringify({ id: data.id })
													})
													.then(function(res) {
														return res.text().then(function(text) {
															var j = text.indexOf('{"success"');
															if (j < 0) j = text.lastIndexOf('{');
															try { return JSON.parse(j >= 0 ? text.slice(j) : text); } catch(e) { return {success:false}; }
														});
													})
													.then(function(delData) {
														if (delData.success) {
															materialWrapper.remove();
														} else {
															alert(delData.message || 'Failed to delete');
														}
													})
													.catch(function(err) {
														console.error('Delete error:', err);
													});
												}
											});
											
											var chevron = document.createElement('span');
											chevron.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.5 6L8 9.5L11.5 6" stroke="#888" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
											chevron.style.display = 'inline-flex';
											chevron.style.alignItems = 'center';
											chevron.style.justifyContent = 'center';
											chevron.style.cursor = 'pointer';
											chevron.style.padding = '4px';
											chevron.style.borderRadius = '4px';
											chevron.style.transition = 'background 0.2s, transform 0.2s';
											chevron.addEventListener('mouseenter', function() { chevron.style.background = '#f0f0f0'; });
											chevron.addEventListener('mouseleave', function() { chevron.style.background = 'transparent'; });
											
											// Click handler to toggle parts dropdown (matches server-rendered rows)
											var newMaterial = { id: data.id, number: data.number, name: data.name };
											materialRow.addEventListener('click', function(e) {
												var existingMaterialDropdown = materialWrapper.querySelector('.material-dropdown');
												var isDropdown = existingMaterialDropdown !== null;
												if (isDropdown) {
													existingMaterialDropdown.remove();
													chevron.style.transform = 'rotate(0deg)';
												} else {
													chevron.style.transform = 'rotate(180deg)';
													var materialDropdown = document.createElement('div');
													materialDropdown.classList.add('material-dropdown');
													materialDropdown.style.padding = '12px 16px';
													materialDropdown.style.background = '#ffffff';
													materialDropdown.style.borderTop = '2px solid #e5e7eb';
													materialWrapper.appendChild(materialDropdown);
													var bd = document.getElementById('bomDropdown');
													if (bd) bd.style.maxHeight = (bd.scrollHeight + 40) + 'px';
												}
												var bd2 = document.getElementById('bomDropdown');
												if (bd2 && isDropdown) {
													setTimeout(function() { bd2.style.maxHeight = (bd2.scrollHeight + 40) + 'px'; }, 50);
												}
											});
											
											materialRow.appendChild(numberSpan);
											materialRow.appendChild(nameSpan);
											materialRow.appendChild(addPartsBtn);
											materialRow.appendChild(deleteBtn);
											materialRow.appendChild(chevron);
											materialWrapper.appendChild(materialRow);
											materialsContainer.appendChild(materialWrapper);
											
											// Animate the dropdown height to accommodate new material
											setTimeout(function() {
												bomDropdown.style.maxHeight = (bomDropdown.scrollHeight + 40) + 'px';
											}, 10);
										}
									} else {
										alert(data.message || 'Failed to add material');
									}
								})
								.catch(function(err) {
									console.error('Add material error:', err);
									alert('Error adding material: ' + err.message);
								});
							};
							
							// Handle cancel
							cancelBtn.onclick = function() {
								modal.style.display = 'none';
							};
							
							// Handle clicking outside modal
							modal.onclick = function(e) {
								if (e.target === modal) {
									modal.style.display = 'none';
								}
							};
						}

						// Material Part Modal functionality
						var currentMaterialForPart = null;
						var currentPartEditId = null;
						function openAddMaterialPartModal(material) {
							currentMaterialForPart = material;
							currentPartEditId = null;
							var modal = document.getElementById('addMaterialPartModal');
							var title = document.getElementById('materialPartModalTitle');
							var partIdInput = document.getElementById('materialPartIdInput');
							var deleteBtn = document.getElementById('deleteMaterialPartBtn');
							
							// Set title
							title.textContent = 'Add Part';
							deleteBtn.style.display = 'none';
							
							// Clear all inputs
							document.getElementById('materialPartNameInput').value = '';
							document.getElementById('materialPartMakeInput').value = '';
							document.getElementById('materialPartNumberInput').value = '';
							document.getElementById('materialPartTypeInput').value = '';
							document.getElementById('materialPartThicknessInput').value = '';
							document.getElementById('materialPartLengthInput').value = '';
							document.getElementById('materialPartWidthInput').value = '';
							document.getElementById('materialPartAreaInput').value = '';
							document.getElementById('materialPartQuantityInput').value = '';
							if (partIdInput) partIdInput.value = '';
							
							// Fetch next number (material number + letter suffix)
							fetch(apiBase + '/get_material_parts.php?material_id=' + material.id)
								.then(function(res) { return res.json(); })
								.then(function(data) {
									if (data.success) {
										var nextSuffix = 'a';
										if (data.parts && data.parts.length > 0) {
											var maxSuffixCode = 96;
											data.parts.forEach(function(existingPart) {
												var existingNumber = existingPart.number || '';
												var match = existingNumber.match(/(\d+)([a-z])$/i);
												if (match && String(match[1]) === String(material.number)) {
													var code = match[2].toLowerCase().charCodeAt(0);
													if (code > maxSuffixCode) maxSuffixCode = code;
												}
											});
											nextSuffix = String.fromCharCode(maxSuffixCode + 1);
										}
										if (partIdInput) partIdInput.value = material.number + nextSuffix;
									}
								});
							
							// Show modal
							modal.style.display = 'flex';
							updateMaterialPartDrawingsSection(null, false);
							document.getElementById('materialPartNameInput').focus();
						}

						function openEditMaterialPartModal(part, material) {
							currentMaterialForPart = material;
							currentPartEditId = part.id;
							var modal = document.getElementById('addMaterialPartModal');
							var title = document.getElementById('materialPartModalTitle');
							var partIdInput = document.getElementById('materialPartIdInput');
							var deleteBtn = document.getElementById('deleteMaterialPartBtn');
							
							// Set title
							title.textContent = 'Edit Part';
							deleteBtn.style.display = 'inline-block';
							
							// Fill inputs with current values
							if (partIdInput) partIdInput.value = part.number || '';
							document.getElementById('materialPartNameInput').value = part.name || '';
							document.getElementById('materialPartMakeInput').value = part.make || '';
							document.getElementById('materialPartNumberInput').value = part.part_number || '';
							document.getElementById('materialPartTypeInput').value = part.material_type || '';
							document.getElementById('materialPartThicknessInput').value = part.thickness || '';
							document.getElementById('materialPartLengthInput').value = part.length || '';
							document.getElementById('materialPartWidthInput').value = part.width || '';
							document.getElementById('materialPartAreaInput').value = part.area || '';
							document.getElementById('materialPartQuantityInput').value = part.quantity || '';
							
							// Show modal
							modal.style.display = 'flex';
							updateMaterialPartDrawingsSection({
								itemId: currentItemForBom ? currentItemForBom.id : 0,
								partId: part.engineering_part_id || 0,
								targetLabel: part.name || ''
							}, true);
							document.getElementById('materialPartNameInput').focus();
						}

						// Save material part button handler
						document.getElementById('saveMaterialPartBtn').addEventListener('click', function() {
							var name = document.getElementById('materialPartNameInput').value.trim();
							var number = document.getElementById('materialPartIdInput').value.trim();
							if (!name) {
								alert('Please enter a part name');
								document.getElementById('materialPartNameInput').focus();
								return;
							}
							if (!number) {
								alert('Please enter a part ID');
								document.getElementById('materialPartIdInput').focus();
								return;
							}
							
							var partData = {
								number: number,
								name: name,
								make: document.getElementById('materialPartMakeInput').value.trim(),
								part_number: document.getElementById('materialPartNumberInput').value.trim(),
								material_type: document.getElementById('materialPartTypeInput').value.trim(),
								thickness: document.getElementById('materialPartThicknessInput').value.trim(),
								length: document.getElementById('materialPartLengthInput').value.trim(),
								width: document.getElementById('materialPartWidthInput').value.trim(),
								area: document.getElementById('materialPartAreaInput').value.trim(),
								quantity: document.getElementById('materialPartQuantityInput').value.trim()
							};
							
							if (currentPartEditId) {
								// Update existing part
								partData.id = currentPartEditId;
								fetch(apiBase + '/update_material_part.php', {
									method: 'POST',
									headers: { 'Content-Type': 'application/json' },
									body: JSON.stringify(partData)
								})
								.then(function(res) {
									return res.text().then(function(text) {
										console.log('Raw update response:', text);
										try {
											return JSON.parse(text);
										} catch (e) {
											console.error('JSON parse error:', e);
											console.error('Response text was:', text);
											throw new Error('Invalid JSON response from server');
										}
									});
								})
								.then(function(data) {
									if (data.success) {
										document.getElementById('addMaterialPartModal').style.display = 'none';
										// Refresh the material dropdown to show updated parts
										refreshMaterialDropdown(currentMaterialForPart);
									} else {
										alert(data.message || 'Failed to update part');
									}
								})
								.catch(function(err) {
									console.error('Error:', err);
									alert('Error: ' + err.message);
								});
							} else {
								// Add new part
								partData.material_id = currentMaterialForPart.id;
								fetch(apiBase + '/add_material_part.php', {
									method: 'POST',
									headers: { 'Content-Type': 'application/json' },
									body: JSON.stringify(partData)
								})
								.then(function(res) {
									return res.text().then(function(text) {
										console.log('Raw response:', text);
										try {
											return JSON.parse(text);
										} catch (e) {
											console.error('JSON parse error:', e);
											console.error('Response text was:', text);
											throw new Error('Invalid JSON response from server');
										}
									});
								})
								.then(function(data) {
									if (data.success) {
										document.getElementById('addMaterialPartModal').style.display = 'none';
										// Refresh the material dropdown to show new parts
										refreshMaterialDropdown(currentMaterialForPart);
									} else {
										alert(data.message || 'Failed to add part');
									}
								})
								.catch(function(err) {
									console.error('Error:', err);
									alert('Error: ' + err.message);
								});
							}
						});

						// Delete material part button handler
						document.getElementById('deleteMaterialPartBtn').addEventListener('click', function() {
							if (!currentPartEditId) return;
							if (!confirm('Are you sure you want to delete this part?')) return;
							
							fetch(apiBase + '/delete_material_part.php', {
								method: 'POST',
								headers: { 'Content-Type': 'application/json' },
								body: JSON.stringify({ id: currentPartEditId })
							})
							.then(function(res) {
								return res.text().then(function(text) {
									var j = text.indexOf('{"success"'); if (j < 0) j = text.lastIndexOf('{');
									try { return JSON.parse(j >= 0 ? text.slice(j) : text); } catch(e) { console.error('Raw:', text); throw new Error('Invalid JSON response from server'); }
								});
							})
							.then(function(data) {
								if (data.success) {
									document.getElementById('addMaterialPartModal').style.display = 'none';
									refreshMaterialDropdown(currentMaterialForPart);
								} else {
									alert(data.message || 'Failed to delete part');
								}
							});
						});

						// Cancel material part button handler
						document.getElementById('cancelMaterialPartBtn').addEventListener('click', function() {
							document.getElementById('addMaterialPartModal').style.display = 'none';
							updateMaterialPartDrawingsSection(null, false);
						});

						// Click outside modal to close
						document.getElementById('addMaterialPartModal').addEventListener('click', function(e) {
							if (e.target === this) {
								this.style.display = 'none';
								updateMaterialPartDrawingsSection(null, false);
							}
						});

						// Helper function to refresh material dropdown with parts
						function refreshMaterialDropdown(material) {
							// Find the material row in the current BOM dropdown
							var bomDropdown = document.getElementById('bomDropdown');
							if (!bomDropdown) return;
							
							// Find the Bill of materials li element
							var allLis = document.querySelectorAll('#itemDetails li');
							var bomLi = null;
							allLis.forEach(function(li) {
								var span = li.querySelector('span');
								if (span && span.textContent === 'Bill of materials') {
									bomLi = li;
								}
							});
							
							if (bomLi) {
								// Close and reopen the BOM dropdown
								bomDropdown.remove();
								setTimeout(function() {
									handleBillOfMaterialsClick(currentItemForBom, bomLi);
								}, 100);
							}
						}

						// Parts and Suppliers functionality
						var currentItemForParts = null;
						var partsDropdownBusy = false;
						var partModalState = { editMode: false, originalPartName: '' };
						var currentDrawingsUploadContext = null;
						var materialPartDrawingContext = null;

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
											var partKey = part.part_id ? String(part.part_id) : ('name:' + (part.part_name || ''));
											if (!partsList[partKey]) {
												partsList[partKey] = {
													part_id: part.part_id || 0,
													part_name: part.part_name,
													nsn_number: part.nsn_number || '',
													quantity: part.quantity || 1,
													notes: part.notes || '',
													makes: []
												};
											}
											if (part.make) {
												var makeEntry = {
													make: part.make,
													partNumber: part.model || '',
													otherNumbers: part.other_numbers || '',
													makeLnk: part.make_lnk || '',
													supplier: part.supplier || '',
													supplierName: part.supplier_name || '',
													supplierNumber: part.supplier_number || '',
													supplierEmail: part.supplier_email || '',
													supplierAddress: part.supplier_address || '',
													supplierPartNumber: part.supplier_part_number || '',
													supplierPrice: part.supplier_price || '',
													supplierLnk: part.supplier_lnk || ''
												};
												var makeExists = partsList[partKey].makes.some(function(m) {
													return (m.make || '') === (makeEntry.make || '')
														&& (m.partNumber || '') === (makeEntry.partNumber || '')
														&& (m.supplierPartNumber || '') === (makeEntry.supplierPartNumber || '');
												});
												if (!makeExists) {
													partsList[partKey].makes.push(makeEntry);
												}
											}
										});

										// Display parts in horizontal rows (matching BOM section style)
										var partsContainer = document.createElement('div');
										partsContainer.style.marginTop = '8px';
										partsContainer.style.borderTop = '1px solid #e5e7eb';
										partsContainer.style.paddingTop = '8px';
										
										Object.keys(partsList).forEach(function(partName) {
											var partData = partsList[partName];
											
											// Create part row
											var partRow = document.createElement('div');
											partRow.style.display = 'flex';
											partRow.style.alignItems = 'center';
											partRow.style.padding = '10px 12px';
											partRow.style.gap = '16px';
											partRow.style.cursor = 'pointer';
											partRow.style.borderRadius = '4px';
											partRow.style.background = '#fafbfc';
											partRow.style.marginBottom = '6px';
											partRow.style.transition = 'background 0.15s';
											
											partRow.setAttribute('data-part-name', partData.part_name);
											partRow.setAttribute('data-part-nsn', partData.nsn_number);
											partRow.setAttribute('data-part-makes', JSON.stringify(partData.makes));
											
											// Name
											var nameSpan = document.createElement('span');
											nameSpan.textContent = partData.part_name;
											nameSpan.style.color = '#1f2937';
											nameSpan.style.fontWeight = '500';
											nameSpan.style.minWidth = '150px';
											
											// Make(s)
											var makeSpan = document.createElement('span');
											var makesText = partData.makes.length > 0 
												? partData.makes.map(function(m) { return m.make; }).filter(Boolean).join(', ') || '-'
												: '-';
											makeSpan.innerHTML = '<span style="color: #6b7280;">Make:</span> ' + makesText;
											makeSpan.style.color = '#1f2937';
											makeSpan.style.minWidth = '200px';
											
											// NSN Number
											var nsnSpan = document.createElement('span');
											nsnSpan.innerHTML = '<span style="color: #6b7280;">NSN:</span> ' + (partData.nsn_number || '-');
											nsnSpan.style.color = '#1f2937';
											nsnSpan.style.minWidth = '150px';
											
											// Quantity
											var quantitySpan = document.createElement('span');
											quantitySpan.innerHTML = '<span style="color: #6b7280;">Quantity:</span> ' + (partData.quantity || '-');
											quantitySpan.style.color = '#1f2937';
											quantitySpan.style.minWidth = '100px';
											
											// Click for more detail
											var detailText = document.createElement('span');
											detailText.textContent = 'click for more detail';
											detailText.style.color = '#9ca3af';
											detailText.style.fontSize = '0.85em';
											detailText.style.fontStyle = 'italic';
											detailText.style.marginLeft = 'auto';
											
											partRow.appendChild(nameSpan);
											partRow.appendChild(makeSpan);
											partRow.appendChild(nsnSpan);
											partRow.appendChild(quantitySpan);
											partRow.appendChild(detailText);
											
											// Click handler to edit
											partRow.addEventListener('click', function() {
												dropdown.remove();
												openEditPartModal(partData);
											});
											
											// Hover effects
											partRow.addEventListener('mouseenter', function() {
												partRow.style.background = '#f3f4f6';
											});
											partRow.addEventListener('mouseleave', function() {
												partRow.style.background = '#fafbfc';
											});
											
											partsContainer.appendChild(partRow);
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
							var ml = makeItem.querySelector('.make-lnk');
							var sup = makeItem.querySelector('.make-supplier');
							var sname = makeItem.querySelector('.make-supplier-name');
							var snum = makeItem.querySelector('.make-supplier-number');
							var semail = makeItem.querySelector('.make-supplier-email');
							var saddr = makeItem.querySelector('.make-supplier-address');
							var spnum = makeItem.querySelector('.make-supplier-part-number');
							var sprice = makeItem.querySelector('.make-supplier-price');
							var sl = makeItem.querySelector('.make-supplier-lnk');
							if (mi) mi.value = makeData.make || '';
							if (pn) pn.value = makeData.partNumber || '';
							if (on) on.value = makeData.otherNumbers || '';
							if (ml) ml.value = makeData.makeLnk || '';
							if (sup) sup.value = makeData.supplier || '';
							if (sname) sname.value = makeData.supplierName || '';
							if (snum) snum.value = makeData.supplierNumber || '';
							if (semail) semail.value = makeData.supplierEmail || '';
							if (saddr) saddr.value = makeData.supplierAddress || '';
							if (spnum) spnum.value = makeData.supplierPartNumber || '';
							if (sprice) sprice.value = makeData.supplierPrice || '';
							if (sl) sl.value = makeData.supplierLnk || '';
						}

						function setDrawingListMessage(container, message, tone) {
							if (!container) return;
							container.innerHTML = '';
							var state = document.createElement('div');
							state.textContent = message;
							state.style.fontSize = '0.9em';
							state.style.color = tone === 'error' ? '#b91c1c' : '#64748b';
							state.style.fontStyle = tone === 'error' ? 'normal' : 'italic';
							container.appendChild(state);
						}

						function createDrawingRow(drawing) {
							var row = document.createElement('button');
							row.type = 'button';
							row.style.display = 'flex';
							row.style.justifyContent = 'space-between';
							row.style.alignItems = 'center';
							row.style.gap = '12px';
							row.style.padding = '10px 12px';
							row.style.border = '1px solid #dbe4ee';
							row.style.borderRadius = '6px';
							row.style.background = '#fff';
							row.style.cursor = 'pointer';
							row.style.textAlign = 'left';
							row.addEventListener('click', function() {
								window.open(drawing.file_url, '_blank');
							});

							var name = document.createElement('span');
							name.textContent = drawing.filename;
							name.style.fontWeight = '600';
							name.style.color = '#1f2937';

							var meta = document.createElement('span');
							meta.textContent = (drawing.version || 'v1').toUpperCase();
							meta.style.fontSize = '0.82em';
							meta.style.color = '#64748b';

							row.appendChild(name);
							row.appendChild(meta);
							return row;
						}

						function getPartDrawingIconPath(hasDrawings) {
							return (window.location.hostname === 'localhost'
								? '/PortalSite/pages/engineering/image/'
								: '/pages/engineering/image/') + (hasDrawings ? 'drawings.svg' : 'nodrawings.svg');
						}

						function wireBOMPartDrawingIcon(iconBtn, iconImg, context, versionEl) {
							if (!iconBtn || !iconImg) return;
							var latestUrl = '';
							var hasDrawings = false;
							var latestVersion = '';

							function renderState() {
								iconImg.src = getPartDrawingIconPath(hasDrawings);
								iconImg.alt = hasDrawings ? 'drawings' : 'no drawings';
								iconBtn.title = hasDrawings ? 'Open latest drawing' : 'No drawings. Click to upload';
								if (versionEl) {
									versionEl.textContent = 'Version: ' + (hasDrawings ? (latestVersion || 'v1').toUpperCase() : 'V0');
								}
							}

							function refreshState() {
								if (!context || !context.itemId || !context.partId) {
									hasDrawings = false;
									latestUrl = '';
									latestVersion = '';
									renderState();
									return;
								}
								fetch(apiBase + '/get_engineering_part_drawings.php?item_id=' + encodeURIComponent(context.itemId) + '&part_id=' + encodeURIComponent(context.partId))
									.then(function(res) { return res.json(); })
									.then(function(data) {
										hasDrawings = !!(data.success && data.drawings && data.drawings.length > 0);
										latestUrl = hasDrawings ? (data.drawings[0].file_url || '') : '';
										latestVersion = hasDrawings ? (data.drawings[0].version || 'v1') : '';
										renderState();
									})
									.catch(function() {
										hasDrawings = false;
										latestUrl = '';
										latestVersion = '';
										renderState();
									});
							}

							iconBtn.addEventListener('click', function(e) {
								e.stopPropagation();
								if (hasDrawings && latestUrl) {
									window.open(latestUrl, '_blank');
									return;
								}
								if (!context || !context.itemId || !context.partId) {
									alert('Save this part first before uploading drawings.');
									return;
								}
								openUploadDrawingsModal({
									type: 'part',
									itemId: context.itemId,
									partId: context.partId,
									targetLabel: context.targetLabel || '',
									onSuccess: function() {
										refreshState();
									}
								});
							});

							refreshState();
						}

						function renderPartDrawingsList(container, drawings, uploadBtn, historyBtn) {
							if (!container) return;
							container.innerHTML = '';
							if (historyBtn) {
								historyBtn.style.display = 'none';
								historyBtn.textContent = 'View previous versions';
								historyBtn.onclick = null;
							}
							if (uploadBtn) {
								uploadBtn.textContent = 'Upload drawings';
							}
							if (!drawings || !drawings.length) {
								setDrawingListMessage(container, 'No drawings uploaded yet.');
								return;
							}

							if (uploadBtn) {
								uploadBtn.textContent = 'Update drawings';
							}

							var byVersion = {};
							drawings.forEach(function(drawing) {
								var versionKey = (drawing.version || 'v1').toLowerCase();
								if (!byVersion[versionKey]) byVersion[versionKey] = [];
								byVersion[versionKey].push(drawing);
							});

							var versions = Object.keys(byVersion).sort(function(a, b) {
								return parseInt(b.replace('v', ''), 10) - parseInt(a.replace('v', ''), 10);
							});

							var currentVersion = versions[0];
							var currentWrap = document.createElement('div');
							currentWrap.style.display = 'grid';
							currentWrap.style.gap = '8px';

							var currentLabel = document.createElement('div');
							currentLabel.textContent = 'Current Version: ' + currentVersion.toUpperCase();
							currentLabel.style.fontSize = '0.82em';
							currentLabel.style.fontWeight = '700';
							currentLabel.style.color = '#64748b';
							currentWrap.appendChild(currentLabel);

							byVersion[currentVersion].forEach(function(drawing) {
								currentWrap.appendChild(createDrawingRow(drawing));
							});
							container.appendChild(currentWrap);

							if (versions.length > 1 && historyBtn) {
								var previousWrap = document.createElement('div');
								previousWrap.style.display = 'none';
								previousWrap.style.marginTop = '10px';
								previousWrap.style.paddingTop = '10px';
								previousWrap.style.borderTop = '1px solid #e2e8f0';
								previousWrap.style.display = 'none';

								versions.slice(1).forEach(function(versionKey) {
									var versionSection = document.createElement('div');
									versionSection.style.display = 'grid';
									versionSection.style.gap = '8px';
									versionSection.style.marginTop = '10px';

									var versionLabel = document.createElement('div');
									versionLabel.textContent = 'Version: ' + versionKey.toUpperCase();
									versionLabel.style.fontSize = '0.82em';
									versionLabel.style.fontWeight = '700';
									versionLabel.style.color = '#64748b';
									versionSection.appendChild(versionLabel);

									byVersion[versionKey].forEach(function(drawing) {
										versionSection.appendChild(createDrawingRow(drawing));
									});

									previousWrap.appendChild(versionSection);
								});

								container.appendChild(previousWrap);
								historyBtn.style.display = 'inline-flex';
								historyBtn.onclick = function() {
									var isHidden = previousWrap.style.display === 'none';
									previousWrap.style.display = isHidden ? 'block' : 'none';
									historyBtn.textContent = isHidden ? 'Hide previous versions' : 'View previous versions';
								};
							}
						}

						function loadPartDrawings(context, container, uploadBtn) {
							if (!container) return;
							var historyBtn = document.getElementById('materialPartDrawingsHistoryBtn');
							if (!context || !context.itemId || !context.partId) {
								setDrawingListMessage(container, 'Save the part first to upload drawings.');
								if (uploadBtn) uploadBtn.disabled = true;
								if (historyBtn) historyBtn.style.display = 'none';
								return;
							}

							if (uploadBtn) uploadBtn.disabled = false;
							setDrawingListMessage(container, 'Loading drawings...');

							fetch(apiBase + '/get_engineering_part_drawings.php?item_id=' + encodeURIComponent(context.itemId) + '&part_id=' + encodeURIComponent(context.partId))
								.then(function(res) { return res.json(); })
								.then(function(data) {
									if (!data.success) {
										setDrawingListMessage(container, data.message || 'Failed to load drawings.', 'error');
										if (historyBtn) historyBtn.style.display = 'none';
										return;
									}
									renderPartDrawingsList(container, data.drawings || [], uploadBtn, historyBtn);
								})
								.catch(function(err) {
									setDrawingListMessage(container, 'Failed to load drawings: ' + err.message, 'error');
									if (historyBtn) historyBtn.style.display = 'none';
								});
						}

						function updateMaterialPartDrawingsSection(context, allowUpload) {
							materialPartDrawingContext = context || null;
							var list = document.getElementById('materialPartDrawingsList');
							var btn = document.getElementById('materialPartDrawingsUploadBtn');
							var historyBtn = document.getElementById('materialPartDrawingsHistoryBtn');
							if (!allowUpload || !context || !context.itemId || !context.partId) {
								setDrawingListMessage(list, 'Save the part first to upload drawings.');
								if (btn) {
									btn.disabled = true;
									btn.style.opacity = '0.55';
									btn.style.cursor = 'not-allowed';
									btn.title = 'Save the part first before uploading drawings.';
									btn.textContent = 'Upload drawings';
								}
								if (historyBtn) historyBtn.style.display = 'none';
								return;
							}
							if (btn) {
								btn.disabled = false;
								btn.style.opacity = '1';
								btn.style.cursor = 'pointer';
								btn.title = '';
							}
							loadPartDrawings(context, list, btn);
						}

						function bindPartDrawingUploadButton(buttonId, contextGetter, refreshFn) {
							var button = document.getElementById(buttonId);
							if (!button || button.getAttribute('data-bound') === '1') return;
							button.setAttribute('data-bound', '1');
							button.addEventListener('click', function() {
								var context = typeof contextGetter === 'function' ? contextGetter() : null;
								if (!context || !context.itemId || !context.partId) {
									alert('Save the part first before uploading drawings.');
									return;
								}
								openUploadDrawingsModal({
									type: 'part',
									itemId: context.itemId,
									partId: context.partId,
									targetLabel: context.targetLabel || '',
									onSuccess: refreshFn
								});
							});
						}

						bindPartDrawingUploadButton('materialPartDrawingsUploadBtn', function() {
							return materialPartDrawingContext;
						}, function() {
							updateMaterialPartDrawingsSection(materialPartDrawingContext, true);
						});

						var drawingFilesInput = document.getElementById('drawingFilesInput');
						var selectedFilesPreview = document.getElementById('selectedFilesPreview');
						if (drawingFilesInput && drawingFilesInput.getAttribute('data-bound') !== '1') {
							drawingFilesInput.setAttribute('data-bound', '1');
							drawingFilesInput.addEventListener('change', function() {
								var files = drawingFilesInput.files;
								if (!selectedFilesPreview) return;
								if (files.length > 0) {
									selectedFilesPreview.innerHTML = files.length + ' file(s) selected: ' + Array.from(files).map(function(f) { return f.name; }).join(', ');
								} else {
									selectedFilesPreview.innerHTML = '';
								}
							});
						}

						// Upload drawings button handler
						document.getElementById('uploadDrawingsBtn').addEventListener('click', function() {
							var filesInput = document.getElementById('drawingFilesInput');
							var files = filesInput.files;
							var uploadContext = currentDrawingsUploadContext || {};
							
							if (files.length === 0) {
								alert('Please select at least one file');
								return;
							}
							
							if (!uploadContext.itemId) {
								alert('No drawing target selected');
								return;
							}
							
							var formData = new FormData();
							formData.append('item_id', uploadContext.itemId);
							if (uploadContext.type === 'part') {
								formData.append('part_id', uploadContext.partId || '');
							}
							for (var i = 0; i < files.length; i++) {
								formData.append('drawings[]', files[i]);
							}
							
							document.getElementById('uploadDrawingsBtn').textContent = 'Uploading...';
							document.getElementById('uploadDrawingsBtn').disabled = true;
							
							fetch(apiBase + (uploadContext.type === 'part' ? '/upload_engineering_part_drawings.php' : '/upload_engineering_drawings.php'), {
								method: 'POST',
								body: formData
							})
							.then(function(res) { return res.json(); })
							.then(function(data) {
								if (data.success) {
									document.getElementById('uploadDrawingsModal').style.display = 'none';
									if (typeof uploadContext.onSuccess === 'function') {
										uploadContext.onSuccess(data);
									}
									alert(uploadContext.type === 'part' ? 'Part drawings uploaded successfully!' : 'Drawings uploaded successfully!');
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
								'<div style="margin-top:10px;">' +
								'<label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Lnk</label>' +
								'<input type="text" class="make-lnk" placeholder="https://..." style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />' +
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
								'<div><label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Supplier Part Number</label>' +
								'<input type="text" class="make-supplier-part-number" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" /></div>' +
								'<div><label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Lnk</label>' +
								'<input type="text" class="make-supplier-lnk" placeholder="https://..." style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" /></div>' +
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
											makeLnk: (item.querySelector('.make-lnk') || {}).value?.trim() || '',
											supplier: (item.querySelector('.make-supplier') || {}).value?.trim() || '',
											supplierName: (item.querySelector('.make-supplier-name') || {}).value?.trim() || '',
											supplierNumber: (item.querySelector('.make-supplier-number') || {}).value?.trim() || '',
											supplierEmail: (item.querySelector('.make-supplier-email') || {}).value?.trim() || '',
											supplierAddress: (item.querySelector('.make-supplier-address') || {}).value?.trim() || '',
											supplierPartNumber: (item.querySelector('.make-supplier-part-number') || {}).value?.trim() || '',
											supplierPrice: priceVal,
											supplierLnk: (item.querySelector('.make-supplier-lnk') || {}).value?.trim() || ''
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







