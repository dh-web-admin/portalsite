<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: ../../auth/login.php');
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
if (!can_access($role, 'maps')) {
  header('Location: ../dashboard/');
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Maps</title>
  <link rel="stylesheet" href="../../assets/css/base.css" />
  <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../../assets/css/project-checklist.css" />
  <link rel="stylesheet" href="style.css" />
  <style>
    /* Supplier details polished styles */
    .supplier-details-container {
      width: 560px;
      min-height: 56px;
      padding: 22px 14px 14px 14px;
      border-radius: 8px;
      background: #ffffff;
      color: #334155;
      box-shadow: 0 6px 18px rgba(2,6,23,0.08);
      border: 1px solid #e2e8f0;
      font-size: 13px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      position: relative;
      line-height: 1.2;
    }
    .supplier-details-left { flex: 1; }
    .supplier-grid { display:grid; grid-template-columns: 110px 1fr 110px 1fr; column-gap:10px; row-gap:6px; }
    .supplier-name { grid-column: 1 / 3; font-weight:700; color:#059669; font-size:14px; }
    .supplier-contact { grid-column: 3 / 5; text-align:right; font-size:12px; color:#475569; }
    .supplier-label { font-weight:600; color:#64748b; text-align:right; }
    .supplier-notes { margin-top:8px; font-size:12px; color:#64748b; border-top:1px solid #f1f5f9; padding-top:8px; font-style:italic; }
    /* Actions bar under ribbon */
    .supplier-details-stack { display:flex; flex-direction:column; align-items:flex-start; gap:8px; width:560px; }
    /* Actions bar should match the details box width and align left */
    .supplier-actions-bar { display:flex; justify-content:flex-start; gap:8px; padding:8px 0; width:100%; }
    /* Keep old class available but make it inert (we now render actions in the bar) */
    .supplier-buttons { display:flex; }
    .supplier-buttons > * { display:inline-flex !important; align-items:center !important; justify-content:center !important; white-space:nowrap !important; }
    .btn-small { padding:6px 10px; border-radius:6px; font-size:12px; color:#fff; border:none; cursor:pointer; box-shadow:0 2px 6px rgba(2,6,23,0.08); display:inline-flex !important; }
    .btn-edit { background:#667eea; }
    .btn-directions { background:#06b6d4; text-decoration:none; display:inline-block; text-align:center; }
    .btn-delete { background:#ef4444; }
    .supplier-details-placeholder { color:#94a3b8; font-size:13px; padding:6px 0; }
    .supplier-details-container a { color: #059669; }
  </style>
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin="" />
  <!-- MarkerCluster CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
</head>
<body class="admin-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
      <main class="content-area">
        <div class="main-content" style="display: flex; flex-direction: column; height: 100%;">
          <!-- Map Selection Ribbon -->
          <div class="map-ribbon" id="mapRibbon">
            <!-- Buttons will be dynamically loaded -->
          </div>
          <!-- Add Supplier Button, Details Box, Filters, and Supplier Dropdown -->
          <div style="margin-bottom: 12px; margin-top: 12px; display: flex; gap: 14px; align-items: flex-start; flex-shrink: 0;">
            <button id="addSupplierBtn" class="btn btn-primary">+ Add Supplier</button>
            <div class="supplier-details-stack">
              <div id="supplierDetailsBox" class="supplier-details-container">
                <div class="supplier-details-placeholder">Supplier Details</div>
              </div>
              <div id="supplierActionsBar" class="supplier-actions-bar" style="display:flex; justify-content:flex-start; gap:8px; padding:8px 0; width:100%;">
              </div>
            </div>
            
            <!-- Supplier Count -->
            <div style="position:relative;">
              <div id="supplierCount" style="display:flex; align-items:center; padding:6px 12px; border:1px solid #e2e8f0; border-radius:6px; background:#667eea; color:#fff; box-shadow:0 1px 2px rgba(0,0,0,0.05); font-size:11px; font-weight:600; white-space:nowrap; cursor:pointer;">
                <span id="supplierCountNumber">0</span>
                <span style="margin-left:4px;">Suppliers</span>
              </div>
              <div id="supplierCountList" style="display:none; position:absolute; right:0; top:calc(100% + 6px); width:360px; max-height:360px; overflow:auto; background:#fff; border:1px solid #e2e8f0; border-radius:6px; box-shadow:0 8px 24px rgba(2,6,23,0.12); z-index:1200; padding:8px; font-size:13px;">
                <!-- populated dynamically -->
                <div style="color:#64748b; font-size:12px;">Loading...</div>
              </div>
            </div>
            
            <!-- Filters Bar -->
            <div id="filtersBar" style="display:flex; align-items:center; gap:8px; padding:6px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
              <span style="font-size:11px; color:#64748b; font-weight:600;">Filter:</span>
              <input id="filterName" type="text" placeholder="Supplier Name" style="width:120px; padding:5px 8px; border:1px solid #cbd5e1; border-radius:4px; font-size:11px; color:#334155;" />
              <input id="filterMaterial" type="text" placeholder="Material" style="width:120px; padding:5px 8px; border:1px solid #cbd5e1; border-radius:4px; font-size:11px; color:#334155;" />
              <input id="filterCity" type="text" placeholder="City" style="width:110px; padding:5px 8px; border:1px solid #cbd5e1; border-radius:4px; font-size:11px; color:#334155;" />
              <input id="filterState" type="text" placeholder="State" style="width:80px; padding:5px 8px; border:1px solid #cbd5e1; border-radius:4px; font-size:11px; color:#334155;" />
              <button id="clearFiltersBtn" class="btn" title="Clear filters" style="padding:5px 8px; font-size:11px;">Clear</button>
            </div>
            <!-- Search removed: supplier search bar and dropdown intentionally omitted -->
          </div>
          
          <!-- Map Container -->
          <div id="map" style="width: 100%; flex: 1; min-height: 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; border-bottom: 2px solid #cbd5e1;"></div>
        </div>
      </main>
    </div>
  </div>
  
  <!-- Add Supplier Modal -->
  <div id="addSupplierModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.6);align-items:center;justify-content:center;z-index:4000;padding:20px;overflow-y:auto;">
    <div style="background:#fff;border-radius:12px;padding:24px;max-width:600px;width:100%;box-shadow:0 8px 30px rgba(2,6,23,0.2);max-height:90vh;overflow-y:auto;">
      <h3 style="margin:0 0 20px 0;font-size:20px;color:#1e293b;">Add New Supplier</h3>
      <form id="addSupplierForm" style="display:grid;gap:16px;">
        <div style="position:relative;">
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Name *</label>
          <input type="text" name="name" required autocomplete="off" class="autocomplete-input" data-field="name" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
          <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Material</label>
            <input type="text" name="material" autocomplete="off" class="autocomplete-input" data-field="material" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Location Type</label>
            <input type="text" name="location_type" autocomplete="off" class="autocomplete-input" data-field="location_type" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
        </div>
        <div style="position:relative;">
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Sales Contact</label>
          <input type="text" name="sales_contact" autocomplete="off" class="autocomplete-input" data-field="sales_contact" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
          <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Contact Number</label>
            <input type="tel" name="contact_number" autocomplete="off" class="autocomplete-input" data-field="contact_number" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Email</label>
            <input type="email" name="email" autocomplete="off" class="autocomplete-input" data-field="email" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
        </div>
        <div style="position:relative;">
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Address</label>
          <input type="text" name="address" autocomplete="off" class="autocomplete-input" data-field="address" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
          <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
        </div>
        <div>
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Coordinates * <span style="font-weight:400;font-size:12px;color:#94a3b8">(format: lat, lng)</span></label>
          <input type="text" id="addSupplierCoordinates" placeholder="e.g. 41.0998, -80.6495" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
          <div id="addSupplierCoordError" class="coord-error" aria-live="polite" style="display:none;margin-top:6px;color:#ef4444;font-size:12px;"></div>
          <!-- Hidden fields populated from combined coordinates before submit -->
          <input type="hidden" name="latitude" id="addSupplierLatitude" />
          <input type="hidden" name="longitude" id="addSupplierLongitude" />
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">City</label>
            <input type="text" name="city" autocomplete="off" class="autocomplete-input" data-field="city" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">State</label>
            <input type="text" name="state" autocomplete="off" class="autocomplete-input" data-field="state" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
        </div>
        <div>
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Notes</label>
          <textarea name="notes" rows="3" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;resize:vertical;"></textarea>
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px;">
          <button type="button" id="cancelSupplierBtn" class="btn" style="padding:10px 20px;">Cancel</button>
          <button type="submit" class="btn btn-primary" style="padding:10px 20px;">Add Supplier</button>
        </div>
      </form>
    </div>
  </div>
  
  <!-- Edit Supplier Modal -->
  <div id="editSupplierModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.6);align-items:center;justify-content:center;z-index:4000;padding:20px;overflow-y:auto;">
    <div style="background:#fff;border-radius:12px;padding:24px;max-width:600px;width:100%;box-shadow:0 8px 30px rgba(2,6,23,0.2);max-height:90vh;overflow-y:auto;">
      <h3 style="margin:0 0 20px 0;font-size:20px;color:#1e293b;">Edit Supplier</h3>
      <form id="editSupplierForm" style="display:grid;gap:16px;">
        <input type="hidden" name="id" id="editSupplierId" />
        <div style="position:relative;">
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Name *</label>
          <input type="text" name="name" id="editSupplierName" required autocomplete="off" class="autocomplete-input" data-field="name" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
          <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Material</label>
            <input type="text" name="material" id="editSupplierMaterial" autocomplete="off" class="autocomplete-input" data-field="material" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Location Type</label>
            <input type="text" name="location_type" id="editSupplierLocationType" autocomplete="off" class="autocomplete-input" data-field="location_type" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
        </div>
        <div style="position:relative;">
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Sales Contact</label>
          <input type="text" name="sales_contact" id="editSupplierSalesContact" autocomplete="off" class="autocomplete-input" data-field="sales_contact" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
          <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Contact Number</label>
            <input type="tel" name="contact_number" id="editSupplierContactNumber" autocomplete="off" class="autocomplete-input" data-field="contact_number" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Email</label>
            <input type="email" name="email" id="editSupplierEmail" autocomplete="off" class="autocomplete-input" data-field="email" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
        </div>
        <div style="position:relative;">
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Address</label>
          <input type="text" name="address" id="editSupplierAddress" autocomplete="off" class="autocomplete-input" data-field="address" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
          <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">City</label>
            <input type="text" name="city" id="editSupplierCity" autocomplete="off" class="autocomplete-input" data-field="city" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">State</label>
            <input type="text" name="state" id="editSupplierState" autocomplete="off" class="autocomplete-input" data-field="state" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
        </div>
        <input type="hidden" name="service" id="editSupplierService" />
        <div>
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Notes</label>
          <textarea name="notes" id="editSupplierNotes" rows="3" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;resize:vertical;"></textarea>
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px;">
          <button type="button" id="cancelEditBtn" class="btn" style="padding:10px 20px;">Cancel</button>
          <button type="submit" class="btn btn-primary" style="padding:10px 20px;">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
  
  <!-- Leaflet JavaScript -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
          crossorigin=""></script>
  <!-- MarkerCluster JS -->
  <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
  <script>
    (function(){
      // Initialize Leaflet map - unrestricted worldwide view
      var map = L.map('map', {
        minZoom: 2,
        maxZoom: 19
      }).setView([39.8283, -98.5795], 5); // Center of USA, zoom 5
      
      // Add OpenStreetMap tile layer
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
      }).addTo(map);

      // Initialize marker clustering with chunked loading for performance
      try {
        markerCluster = L.markerClusterGroup({ chunkedLoading: true });
        map.addLayer(markerCluster);
      } catch (e) {
        // If markercluster isn't available, fall back to direct map markers
        console.warn('MarkerCluster initialization failed, falling back to regular markers', e);
        markerCluster = null;
      }
      
      // Reset supplier details box when clicking on empty space
      map.on('click', function(e) {
        resetSupplierDetails();
      });
      
      // Store current markers
      var currentMarkers = [];
      // MarkerCluster group (initialized after tile layer)
      var markerCluster = null;
      // Cache of suppliers returned from API (includes those without coords)
      var suppliersCache = [];
      // Cache for generated marker icons by color to avoid recreating identical SVGs
      var iconCache = {};
      var currentService = null;
      
      function showSupplierDetails(supplier) {
        var detailsBox = document.getElementById('supplierDetailsBox');
        if (!detailsBox) return;

        // Build a cleaner, class-based HTML for the details box
        var html = '';
        html += '<div class="supplier-details-left">';
        html += '<div class="supplier-grid">';
        html += '<div class="supplier-name">' + (supplier.name || 'Unknown') + '</div>';
        html += '<div class="supplier-contact">' + (supplier.sales_contact || '') + '</div>';
        html += '<div class="supplier-label">Material:</div><div>' + (supplier.material || '') + '</div>';
        html += '<div class="supplier-label">Type:</div><div>' + (supplier.location_type || '') + '</div>';
        html += '<div class="supplier-label">Address:</div><div style="grid-column:2 / 5;">' + (supplier.address || '') + '</div>';
        html += '<div class="supplier-label">City:</div><div>' + (supplier.city || '') + '</div>';
        html += '<div class="supplier-label">State:</div><div>' + (supplier.state || '') + '</div>';
        html += '<div class="supplier-label">Phone:</div><div>' + (supplier.contact_number || '') + '</div>';
        html += '<div class="supplier-label">Email:</div><div><a href="mailto:' + (supplier.email || '') + '">' + (supplier.email || '') + '</a></div>';
          html += '</div>'; // supplier-grid
          if (supplier.notes) {
            html += '<div class="supplier-notes">' + supplier.notes + '</div>';
          }
          html += '</div>'; // supplier-details-left

          detailsBox.innerHTML = html;

          // Render action buttons into the separate actions bar under the ribbon
          try {
            var actionsBar = document.getElementById('supplierActionsBar');
            if (actionsBar) {
              var actionsHtml = '';
              actionsHtml += '<button id="supplierActionEdit" class="btn-small btn-edit">Edit</button>';

              // Build directions URL (prefer coords)
              var directionsUrl = '';
              try {
                if (supplier.latitude && supplier.longitude) {
                  directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(String(supplier.latitude) + ',' + String(supplier.longitude)) + '&travelmode=driving';
                } else {
                  var addrParts = [];
                  if (supplier.address) addrParts.push(supplier.address);
                  if (supplier.city) addrParts.push(supplier.city);
                  if (supplier.state) addrParts.push(supplier.state);
                  if (addrParts.length) directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(addrParts.join(', ')) + '&travelmode=driving';
                }
              } catch (e) { directionsUrl = ''; }

              if (directionsUrl) {
                actionsHtml += '<a id="supplierActionDirections" href="' + directionsUrl + '" target="_blank" rel="noopener" class="btn-small btn-directions">Directions</a>';
              }

              actionsHtml += '<button id="supplierActionDelete" class="btn-small btn-delete">Delete</button>';
              actionsBar.innerHTML = actionsHtml;

              // Attach handlers
              var editBtn = document.getElementById('supplierActionEdit');
              if (editBtn) editBtn.addEventListener('click', function(e){ e.preventDefault(); editSupplier(supplier.id); });
              var delBtn = document.getElementById('supplierActionDelete');
              if (delBtn) delBtn.addEventListener('click', function(e){ e.preventDefault(); deleteSupplier(supplier.id, supplier.name || ''); });
            }
          } catch (ex) {
            console.error('Failed to render actions bar:', ex);
          }

          updateSupplierDropdown(supplier.id);
      }

      // Reset the supplier details UI and clear the actions bar
      function resetSupplierDetails() {
        try {
          var detailsBox = document.getElementById('supplierDetailsBox');
          if (detailsBox) {
            detailsBox.innerHTML = '<div class="supplier-details-placeholder">Supplier Details</div>';
          }

          var actionsBar = document.getElementById('supplierActionsBar');
          if (actionsBar) {
            actionsBar.innerHTML = '';
          }

          // Clear any edit state
          try { currentEditSupplier = null; } catch (e) { /* ignore if not defined yet */ }
        } catch (e) {
          console.error('resetSupplierDetails error', e);
        }
      }

      // Supplier search removed: keep a safe no-op update function so other code can call it
      function updateSupplierDropdown(currentSupplierId) {
        // no-op: search bar and dropdown were removed intentionally
      }
      
      // Function to generate a consistent color based on supplier name
      function getColorForSupplier(supplierName) {
        if (!supplierName) return '#3b82f6'; // Default blue
        
        // Hash the supplier name to get a consistent number
        var hash = 0;
        for (var i = 0; i < supplierName.length; i++) {
          hash = supplierName.charCodeAt(i) + ((hash << 5) - hash);
        }
        
        // Generate HSL color with good saturation and lightness for visibility
        var hue = Math.abs(hash) % 360;
        var saturation = 65 + (Math.abs(hash) % 20); // 65-85%
        var lightness = 45 + (Math.abs(hash) % 15); // 45-60%
        
        return 'hsl(' + hue + ', ' + saturation + '%, ' + lightness + '%)';
      }
      
      // Function to create a custom colored marker icon
      function createColoredIcon(color) {
        // Reuse icons for the same color to reduce DOM/SVG creation overhead
        if (iconCache[color]) return iconCache[color];
        // Create SVG marker with the specified color
        var svgIcon = '<svg width="25" height="41" viewBox="0 0 25 41" xmlns="http://www.w3.org/2000/svg">' +
          '<path d="M12.5 0C5.6 0 0 5.6 0 12.5c0 8.5 12.5 28.5 12.5 28.5S25 21 25 12.5C25 5.6 19.4 0 12.5 0z" ' +
          'fill="' + color + '" stroke="#fff" stroke-width="1.5"/>' +
          '<circle cx="12.5" cy="12.5" r="5" fill="#fff" opacity="0.9"/>' +
          '</svg>';

        var icon = L.divIcon({
          className: 'custom-marker-icon',
          html: svgIcon,
          iconSize: [25, 41],
          iconAnchor: [12.5, 41],
          popupAnchor: [0, -41]
        });
        iconCache[color] = icon;
        return icon;
      }
      
      // Geocoding disabled: placeholder function
      // Suppliers without latitude/longitude will not be geocoded automatically.
      function geocodeAndPlotMarker(supplier, popupContent) {
        console.warn('Geocoding disabled by configuration. Supplier', supplier && supplier.id, 'will not be geocoded.');
        return;
      }
      
      // Function to plot marker on map
      function plotMarker(supplier, popupContent, lat, lng) {
        // Get color for this supplier and create custom icon
        var markerColor = getColorForSupplier(supplier.name);
        var customIcon = createColoredIcon(markerColor);
        
        // Note: action buttons are intentionally only shown in the supplier details box,
        // not in the marker popup. Keep popupContent focused on supplier info.

        var marker = L.marker([lat, lng], { icon: customIcon });
        // Add marker to cluster group when available, otherwise add directly to map
        if (markerCluster) {
          markerCluster.addLayer(marker);
        } else {
          marker.addTo(map);
        }
        marker.bindPopup(popupContent);
        
        // Store supplier data on marker
        marker.supplierData = supplier;
        
        // Open popup on hover
        marker.on('mouseover', function(e) {
          this.openPopup();
        });
        marker.on('mouseout', function(e) {
          this.closePopup();
        });
        
        // Show details in box on click
        marker.on('click', function(e) {
          L.DomEvent.stopPropagation(e); // Prevent map click event
          showSupplierDetails(this.supplierData);
        });
        
        currentMarkers.push(marker);
      }
      
      // Function to clear all markers from map / cluster
      function clearMarkers() {
        if (markerCluster) {
          markerCluster.clearLayers();
        } else {
          currentMarkers.forEach(function(marker) {
            map.removeLayer(marker);
          });
        }
        currentMarkers = [];
      }
      
      // Function to load suppliers for a given service
      function loadSuppliers(service) {
        clearMarkers();
        currentService = service;
        
        fetch('../../api/get_suppliers.php?service=' + encodeURIComponent(service), {
          method: 'GET',
          credentials: 'same-origin'
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
          if (data.success && data.suppliers) {
            // Keep a cache of suppliers (so UI lists can show suppliers before geocoding completes)
            suppliersCache = data.suppliers;
            if (data.suppliers.length === 0) {
              // No suppliers for this service
              updateSupplierCount();
              return map.setView([39.8283, -98.5795], 5);
            }
            var suppliersNeedingGeocode = [];
            var suppliersWithCoords = [];
            
            // Separate suppliers with and without coordinates
            data.suppliers.forEach(function(supplier) {
              if (supplier.latitude && supplier.longitude) {
                suppliersWithCoords.push(supplier);
              } else {
                suppliersNeedingGeocode.push(supplier);
              }
            });
            
            // Plot suppliers with cached coordinates using batching to avoid UI freeze
            var batchSize = 50; // number of markers to add per tick
            var idx = 0;
            function addNextBatch() {
              var end = Math.min(idx + batchSize, suppliersWithCoords.length);
              for (var i = idx; i < end; i++) {
                var supplier = suppliersWithCoords[i];
                var popupContent = '<div style="font-size:13px;line-height:1.4;">' +
                  '<strong style="font-size:15px;display:block;margin-bottom:6px;">' + (supplier.name || 'Unknown') + '</strong>';

                if (supplier.material) popupContent += '<div><strong>Material:</strong> ' + supplier.material + '</div>';
                if (supplier.location_type) popupContent += '<div><strong>Type:</strong> ' + supplier.location_type + '</div>';
                if (supplier.address) popupContent += '<div><strong>Street Address:</strong> ' + supplier.address + '</div>';
                if (supplier.city) popupContent += '<div><strong>City:</strong> ' + supplier.city + '</div>';
                if (supplier.state) popupContent += '<div><strong>State:</strong> ' + supplier.state + '</div>';
                if (supplier.sales_contact) popupContent += '<div><strong>Contact:</strong> ' + supplier.sales_contact + '</div>';
                if (supplier.contact_number) popupContent += '<div><strong>Phone:</strong> ' + supplier.contact_number + '</div>';
                if (supplier.email) popupContent += '<div><strong>Email:</strong> <a href="mailto:' + supplier.email + '">' + supplier.email + '</a></div>';
                if (supplier.notes) popupContent += '<div style="margin-top:6px;padding-top:6px;border-top:1px solid #e2e8f0;"><em>' + supplier.notes + '</em></div>';

                popupContent += '</div>';
                plotMarker(supplier, popupContent, parseFloat(supplier.latitude), parseFloat(supplier.longitude));
              }
              idx = end;
              if (idx < suppliersWithCoords.length) {
                // Schedule next batch on next tick to keep UI responsive
                setTimeout(addNextBatch, 10);
              } else {
                // All markers added; continue with filters and UI updates
                applyFilters(true);
                updateSupplierDropdown();
              }
            }
            addNextBatch();
            
            // Geocoding is disabled: skip suppliers without cached coordinates.
            if (suppliersNeedingGeocode.length > 0) {
              // Geocoding disabled; skipping suppliers without cached coordinates.
            }
            
            // applyFilters and updateSupplierDropdown are called after batch plotting completes
          } else {
            // No suppliers found - set default US center view
            map.setView([39.8283, -98.5795], 5);
          }
        })
        .catch(function(error) {
          console.error('Error loading suppliers:', error);
          map.setView([39.8283, -98.5795], 5);
        });
      }
      
      // Function to load and render ribbon buttons
      function loadRibbon() {
        var ribbon = document.getElementById('mapRibbon');
        if (!ribbon) return;
        
        fetch('../../api/get_services.php', {
          method: 'GET',
          credentials: 'same-origin'
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
          if (data.success && data.services) {
            ribbon.innerHTML = '';
            
            // Use services from database, or empty if none exist
            var services = data.services || [];
            
            if (services.length === 0) {
              // Show a message when no services exist
              ribbon.innerHTML = '<div style="padding:10px; color:#64748b; font-size:13px; font-style:italic;">No services available. Add one using the sidebar.</div>';
              return;
            }
            
            services.forEach(function(serviceName, index) {
              var btn = document.createElement('button');
              btn.className = 'map-ribbon-btn';
              btn.setAttribute('data-map', serviceName);
              btn.textContent = serviceName.charAt(0).toUpperCase() + serviceName.slice(1).replace(/-/g, ' ');
              
              // First button is active by default
              if (index === 0) {
                btn.classList.add('active');
              }
              
              // Wire click handler
              btn.addEventListener('click', function(){
                var selectedService = this.getAttribute('data-map');
                
                // Update active state
                ribbon.querySelectorAll('.map-ribbon-btn').forEach(function(b){ b.classList.remove('active'); });
                this.classList.add('active');
                
                // Load suppliers for selected service
                loadSuppliers(selectedService);
              });
              
              ribbon.appendChild(btn);
            });
            
            // Load first service by default
            if (services.length > 0) {
              loadSuppliers(services[0]);
            }
          }
        })
        .catch(function(error) {
          console.error('Error loading services:', error);
          // Show error message instead of fallback
          ribbon.innerHTML = '<div style="padding:10px; color:#ef4444; font-size:13px;">Error loading services. Please refresh the page.</div>';
        });
      }
      
      // Initialize ribbon
      loadRibbon();
      
      // Sidebar toggle handler
      var usersToggle = document.getElementById('usersToggle');
      var usersGroup = document.getElementById('usersGroup');
      if (usersToggle && usersGroup) {
        usersToggle.addEventListener('click', function(){
          usersGroup.classList.toggle('open');
        });
      }
      
      // Services toggle handler
      var servicesToggle = document.getElementById('servicesToggle');
      var servicesGroup = document.getElementById('servicesGroup');
      if (servicesToggle && servicesGroup) {
        servicesToggle.addEventListener('click', function(){
          servicesGroup.classList.toggle('open');
        });
      }
      
      // (Removed old add service handler - replaced by combined add/remove handlers below)
      
      // Services add/remove handlers
      var addServiceBtn = document.getElementById('addServiceBtn');
      var newServiceInput = document.getElementById('newServiceName');
      var removeServiceSelect = document.getElementById('removeServiceSelect');
      var removeServiceBtn = document.getElementById('removeServiceBtn');

      function populateRemoveServiceSelect() {
        if (!removeServiceSelect) return;
        removeServiceSelect.innerHTML = '<option value="">Loading...</option>';
        fetch('../../api/get_services.php', { credentials: 'same-origin' })
          .then(r=>r.json())
          .then(function(data){
            if (data.success) {
              if (!data.services || data.services.length === 0) {
                removeServiceSelect.innerHTML = '<option value="">No services</option>';
                return;
              }
              removeServiceSelect.innerHTML = '<option value="">Select service</option>' +
                data.services.map(function(s){ return '<option value="'+s+'">'+s+'</option>'; }).join('');
            } else {
              removeServiceSelect.innerHTML = '<option value="">Error</option>';
            }
          })
          .catch(function(){ removeServiceSelect.innerHTML = '<option value="">Error</option>'; });
      }

      if (addServiceBtn && newServiceInput) {
        addServiceBtn.addEventListener('click', function(){
          var serviceName = newServiceInput.value.trim();
          if (!serviceName) { alert('Please enter a service name'); return; }
          addServiceBtn.disabled = true; addServiceBtn.textContent = 'Adding...';
          var formData = new FormData(); formData.append('service_name', serviceName);
          fetch('../../api/add_service.php', { method:'POST', body:formData, credentials:'same-origin'})
            .then(r=>r.json())
            .then(function(data){
              if (data.success) { newServiceInput.value=''; loadRibbon(); populateRemoveServiceSelect(); alert('Service added'); }
              else alert(data.message || 'Failed to add service');
            })
            .catch(function(e){ console.error(e); alert('Failed to add service'); })
            .finally(function(){ addServiceBtn.disabled=false; addServiceBtn.textContent='Add'; });
        });
      }

      if (removeServiceBtn && removeServiceSelect) {
        removeServiceBtn.addEventListener('click', function(){
          var selected = removeServiceSelect.value;
          if (!selected) { alert('Select a service to remove'); return; }
          if (!confirm('Remove service "'+selected+'"? This does not delete suppliers.')) return;
          removeServiceBtn.disabled = true; removeServiceBtn.textContent='Removing...';
          var formData = new FormData(); formData.append('service_name', selected);
          fetch('../../api/delete_service.php', { method:'POST', body:formData, credentials:'same-origin'})
            .then(r=>r.json())
            .then(function(data){
              if (data.success) { alert('Service removed'); loadRibbon(); populateRemoveServiceSelect(); }
              else alert(data.message || 'Failed to remove service');
            })
            .catch(function(e){ console.error(e); alert('Failed to remove service'); })
            .finally(function(){ removeServiceBtn.disabled=false; removeServiceBtn.textContent='Remove'; });
        });
      }

      // Populate remove-service select on load (sidebar present only on maps page)
      populateRemoveServiceSelect();

      /* =========================
         Add Supplier Modal Logic
         ========================= */
      var addSupplierModal = document.getElementById('addSupplierModal');
      var addSupplierForm = document.getElementById('addSupplierForm');
      var cancelSupplierBtn = document.getElementById('cancelSupplierBtn');
      var addSupplierOpenBtn = document.getElementById('addSupplierBtn');

      // Open modal
      if (addSupplierOpenBtn && addSupplierModal) {
        addSupplierOpenBtn.addEventListener('click', function() {
          addSupplierModal.style.display = 'flex';
        });
      }

      // Close modal on cancel
      if (cancelSupplierBtn && addSupplierModal) {
        cancelSupplierBtn.addEventListener('click', function() {
          addSupplierModal.style.display = 'none';
        });
      }

      // Close modal when clicking backdrop
      if (addSupplierModal) {
        addSupplierModal.addEventListener('click', function(e) {
          if (e.target === addSupplierModal) {
            addSupplierModal.style.display = 'none';
          }
        });
      }

      // Parse combined coordinates for Add Supplier before submit (capture phase)
      if (addSupplierForm) {
        addSupplierForm.addEventListener('submit', function(e) {
          // run early (capture-phase listener added later in file will ensure this runs before bubble listeners)
          var coordsInput = document.getElementById('addSupplierCoordinates');
          var latHidden = document.getElementById('addSupplierLatitude');
          var lngHidden = document.getElementById('addSupplierLongitude');
          var err = document.getElementById('addSupplierCoordError');
          if (!coordsInput || !latHidden || !lngHidden) return;

          var val = coordsInput.value.trim();
          // Clear prior error
          if (err) { err.textContent = ''; err.style.display = 'none'; }

          if (!val) {
            // API requires lat/lng — prevent submit and show error
            if (err) { err.textContent = 'Please provide coordinates as "lat, lng"'; err.style.display = 'block'; }
            e.preventDefault();
            return;
          }

          // Use parseCoords (defined later in this file) to interpret the combined value
          var parsed = null;
          try { parsed = parseCoords(val); } catch (ex) { parsed = null; }
          // Fallback: try to extract two numeric values from the string (robust against NBSP, extra chars)
          if (!parsed || isNaN(Number(parsed.lat)) || isNaN(Number(parsed.lng))) {
            var m = (val || '').replace(/\u00A0/g, ' ').match(/-?\d+(?:\.\d+)?/g);
            if (m && m.length >= 2) {
              parsed = { lat: parseFloat(m[0]), lng: parseFloat(m[1]) };
            }
          }
          if (!parsed || isNaN(Number(parsed.lat)) || isNaN(Number(parsed.lng))) {
            if (err) { err.textContent = 'Invalid coordinates — enter as "lat, lng"'; err.style.display = 'block'; }
            e.preventDefault();
            return;
          }

          // Populate hidden fields so existing submit logic sends numeric lat/lng
          latHidden.value = String(parsed.lat);
          lngHidden.value = String(parsed.lng);
        }, true); // capture = true to run before other submit listeners
      }

      // Submit handler
      if (addSupplierForm) {
        addSupplierForm.addEventListener('submit', function(e) {
          e.preventDefault();

          if (!currentService) {
            alert('Please select a service from the ribbon first.');
            return;
          }

          var formData = new FormData(addSupplierForm);
          formData.append('service', currentService);

          // Fallback: if hidden latitude/longitude are empty, try parsing from combined coordinates input
          var latHiddenEl = addSupplierForm.querySelector('[name="latitude"]');
          var lngHiddenEl = addSupplierForm.querySelector('[name="longitude"]');
          var coordsInputEl = document.getElementById('addSupplierCoordinates');
          var coordErrEl = document.getElementById('addSupplierCoordError');
          if (coordErrEl) { coordErrEl.textContent = ''; coordErrEl.style.display = 'none'; }
          try {
            var latValNow = latHiddenEl ? (latHiddenEl.value || '').trim() : '';
            var lngValNow = lngHiddenEl ? (lngHiddenEl.value || '').trim() : '';
            if ((!latValNow || !lngValNow) && coordsInputEl) {
              var parsedFallback = null;
              try { parsedFallback = parseCoords((coordsInputEl.value || '').trim()); } catch (ex) { parsedFallback = null; }
              if ((!parsedFallback || isNaN(Number(parsedFallback.lat)) || isNaN(Number(parsedFallback.lng))) && coordsInputEl) {
                var raw = (coordsInputEl.value || '').replace(/\u00A0/g, ' ');
                var m = raw.match(/-?\d+(?:\.\d+)?/g);
                if (m && m.length >= 2) {
                  parsedFallback = { lat: parseFloat(m[0]), lng: parseFloat(m[1]) };
                }
              }
              if (parsedFallback && !isNaN(Number(parsedFallback.lat)) && !isNaN(Number(parsedFallback.lng))) {
                if (latHiddenEl) latHiddenEl.value = String(parsedFallback.lat);
                if (lngHiddenEl) lngHiddenEl.value = String(parsedFallback.lng);
                // Update formData with new values as well
                formData.set('latitude', String(parsedFallback.lat));
                formData.set('longitude', String(parsedFallback.lng));
              } else {
                console.debug('Add Supplier: failed to parse coords fallback, input="' + (coordsInputEl ? coordsInputEl.value : '') + '", parsed=', parsedFallback);
              }
            }
          } catch (e) {
            console.error('Coordinate fallback parse error', e);
          }

          var submitBtn = addSupplierForm.querySelector('button[type="submit"]');
          if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Adding...';
          }

          // Determine latitude and longitude: prefer hidden fields, fallback to parsing the combined coords input
          var latHiddenEl = addSupplierForm.querySelector('[name="latitude"]');
          var lngHiddenEl = addSupplierForm.querySelector('[name="longitude"]');
          var coordsInputEl = document.getElementById('addSupplierCoordinates');
          var coordErrEl = document.getElementById('addSupplierCoordError');
          if (coordErrEl) { coordErrEl.textContent = ''; coordErrEl.style.display = 'none'; }

          var latVal = latHiddenEl ? (latHiddenEl.value || '').trim() : '';
          var lngVal = lngHiddenEl ? (lngHiddenEl.value || '').trim() : '';

          if ((!latVal || !lngVal) && coordsInputEl) {
            var parsedCoords = null;
            try { parsedCoords = parseCoords((coordsInputEl.value || '').trim()); } catch (ex) { parsedCoords = null; }
            if (parsedCoords && !isNaN(Number(parsedCoords.lat)) && !isNaN(Number(parsedCoords.lng))) {
              latVal = String(parsedCoords.lat);
              lngVal = String(parsedCoords.lng);
              if (latHiddenEl) latHiddenEl.value = latVal;
              if (lngHiddenEl) lngHiddenEl.value = lngVal;
              formData.set('latitude', latVal);
              formData.set('longitude', lngVal);
            }
          }

          if (!latVal || !lngVal || isNaN(Number(latVal)) || isNaN(Number(lngVal))) {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Add Supplier'; }
            if (coordErrEl) {
              coordErrEl.textContent = 'Please provide valid numeric Latitude and Longitude values.';
              coordErrEl.style.display = 'block';
            } else {
              alert('Please provide valid numeric Latitude and Longitude values.');
            }
            return;
          }

          fetch('../../api/add_supplier.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
          })
          .then(function(response) { return response.json(); })
          .then(function(data) {
            if (data.success) {
              alert('Supplier added successfully!');
              addSupplierModal.style.display = 'none';
              addSupplierForm.reset();
              // Reload suppliers for current service
              // Reload suppliers from server to refresh authoritative list
              loadSuppliers(currentService);

              // Also optimistically add and plot the new supplier client-side so it appears immediately
              try {
                var newId = data.supplier_id;
                if (newId) {
                  // Build supplier object from form inputs
                  var form = addSupplierForm;
                  var supplierObj = {
                    id: newId,
                    name: form.querySelector('[name="name"]').value || '',
                    material: form.querySelector('[name="material"]').value || '',
                    sales_contact: form.querySelector('[name="sales_contact"]').value || '',
                    contact_number: form.querySelector('[name="contact_number"]').value || '',
                    email: form.querySelector('[name="email"]').value || '',
                    address: form.querySelector('[name="address"]').value || '',
                    city: form.querySelector('[name="city"]').value || '',
                    state: form.querySelector('[name="state"]').value || '',
                    location_type: form.querySelector('[name="location_type"]').value || '',
                    notes: form.querySelector('[name="notes"]').value || '',
                    latitude: form.querySelector('[name="latitude"]').value || '',
                    longitude: form.querySelector('[name="longitude"]').value || '',
                    service: currentService
                  };

                  // Insert into suppliersCache so UI shows it immediately
                  suppliersCache = suppliersCache || [];
                  suppliersCache.unshift(supplierObj);
                  updateSupplierCount();
                  populateSupplierCountList();

                  // If supplier has coordinates (unlikely), plot immediately; otherwise geocode and plot now
                  var popupContent = '<div style="font-size:13px;line-height:1.4;">' +
                    '<strong style="font-size:15px;display:block;margin-bottom:6px;">' + (supplierObj.name || 'Unknown') + '</strong>' +
                    (supplierObj.material?('<div><strong>Material:</strong> ' + supplierObj.material + '</div>'):'') +
                    (supplierObj.location_type?('<div><strong>Type:</strong> ' + supplierObj.location_type + '</div>'):'') +
                    (supplierObj.address?('<div><strong>Street Address:</strong> ' + supplierObj.address + '</div>'):'') +
                    (supplierObj.city?('<div><strong>City:</strong> ' + supplierObj.city + '</div>'):'') +
                    (supplierObj.state?('<div><strong>State:</strong> ' + supplierObj.state + '</div>'):'') +
                    (supplierObj.sales_contact?('<div><strong>Contact:</strong> ' + supplierObj.sales_contact + '</div>'):'') +
                    (supplierObj.contact_number?('<div><strong>Phone:</strong> ' + supplierObj.contact_number + '</div>'):'') +
                    (supplierObj.email?('<div><strong>Email:</strong> <a href="mailto:' + supplierObj.email + '">' + supplierObj.email + '</a></div>'):'') +
                    (supplierObj.notes?('<div style="margin-top:6px;padding-top:6px;border-top:1px solid #e2e8f0;"><em>' + supplierObj.notes + '</em></div>'):'') +
                    '</div>';

                  if (supplierObj.latitude && supplierObj.longitude) {
                    plotMarker(supplierObj, popupContent, parseFloat(supplierObj.latitude), parseFloat(supplierObj.longitude));
                  } else if (supplierObj.address || supplierObj.city || supplierObj.state) {
                    // Geocode immediately and plot (bypass rate limiter used for bulk geocoding)
                    geocodeAndPlotMarker(supplierObj, popupContent);
                  }
                }
              } catch (e) {
                console.error('Optimistic add/plot failed', e);
              }
            } else {
              alert(data.message || 'Failed to add supplier');
            }
          })
          .catch(function(error) {
            console.error('Error adding supplier:', error);
            alert('Failed to add supplier');
          })
          .finally(function() {
            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.textContent = 'Add Supplier';
            }
          });
        });
      }
      
      // Edit supplier modal elements
      var editSupplierModal = document.getElementById('editSupplierModal');
      var editSupplierForm = document.getElementById('editSupplierForm');
      var cancelEditBtn = document.getElementById('cancelEditBtn');
      var currentEditSupplier = null;
      
      // Function to open edit modal - make it global
      window.editSupplier = function(supplierId) {
        // Find supplier data from current markers
        var supplier = null;
        currentMarkers.forEach(function(marker) {
          if (marker.supplierData && marker.supplierData.id == supplierId) {
            supplier = marker.supplierData;
          }
        });
        
        if (!supplier) {
          alert('Supplier not found');
          return;
        }
        
        currentEditSupplier = supplier;
        
        // Populate form fields
        document.getElementById('editSupplierId').value = supplier.id || '';
        document.getElementById('editSupplierName').value = supplier.name || '';
        document.getElementById('editSupplierMaterial').value = supplier.material || '';
        document.getElementById('editSupplierLocationType').value = supplier.location_type || '';
        document.getElementById('editSupplierSalesContact').value = supplier.sales_contact || '';
        document.getElementById('editSupplierContactNumber').value = supplier.contact_number || '';
        document.getElementById('editSupplierEmail').value = supplier.email || '';
        document.getElementById('editSupplierAddress').value = supplier.address || '';
        document.getElementById('editSupplierCity').value = supplier.city || '';
        document.getElementById('editSupplierState').value = supplier.state || '';
        document.getElementById('editSupplierService').value = supplier.service || '';
        document.getElementById('editSupplierNotes').value = supplier.notes || '';
        
        // Show modal
        editSupplierModal.style.display = 'flex';
      };
      
      // Function to delete supplier - make it global
      window.deleteSupplier = function(supplierId, supplierName) {
        if (!confirm('Are you sure you want to delete "' + supplierName + '"?\n\nThis action cannot be undone.')) {
          return;
        }
        
        fetch('../../api/delete_supplier.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: 'id=' + encodeURIComponent(supplierId),
          credentials: 'same-origin'
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
          if (data.success) {
            alert('Supplier deleted successfully!');
            // Reset details box
            resetSupplierDetails();
            // Reload suppliers for current service
            if (currentService) {
              loadSuppliers(currentService);
            }
          } else {
            alert(data.message || 'Failed to delete supplier');
          }
        })
        .catch(function(error) {
          console.error('Error deleting supplier:', error);
          alert('Failed to delete supplier');
        });
      };
      
      // Cancel edit button
      if (cancelEditBtn && editSupplierModal) {
        cancelEditBtn.addEventListener('click', function() {
          editSupplierModal.style.display = 'none';
          currentEditSupplier = null;
        });
      }
      
      // Click outside to close edit modal
      if (editSupplierModal) {
        editSupplierModal.addEventListener('click', function(e) {
          if (e.target === editSupplierModal) {
            editSupplierModal.style.display = 'none';
            currentEditSupplier = null;
          }
        });
      }
      
      // Handle edit form submission
      if (editSupplierForm) {
        editSupplierForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          var formData = new FormData(editSupplierForm);
          
          var submitBtn = editSupplierForm.querySelector('button[type="submit"]');
          if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
          }
          
          fetch('../../api/update_supplier.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
          })
          .then(function(response) { return response.json(); })
          .then(function(data) {
            if (data.success) {
              alert('Supplier updated successfully!');
              editSupplierModal.style.display = 'none';
              currentEditSupplier = null;
              // Reload suppliers for current service
              if (currentService) {
                loadSuppliers(currentService);
              }
              // Reset details box
              resetSupplierDetails();
            } else {
              alert(data.message || 'Failed to update supplier');
            }
          })
          .catch(function(error) {
            console.error('Error updating supplier:', error);
            alert('Failed to update supplier');
          })
          .finally(function() {
            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.textContent = 'Save Changes';
            }
          });
        });
      }
      
      // Autocomplete functionality for form fields
      var autocompleteCache = {}; // Cache field values to avoid repeated API calls
      
      // Initialize autocomplete for all inputs with class 'autocomplete-input'
      function initializeAutocomplete() {
        var inputs = document.querySelectorAll('.autocomplete-input');
        
        inputs.forEach(function(input) {
          var field = input.getAttribute('data-field');
          var dropdown = input.nextElementSibling;
          
          if (!dropdown || !dropdown.classList.contains('autocomplete-dropdown')) {
            return;
          }
          
          // Focus event - load and show suggestions
          input.addEventListener('focus', function() {
            loadFieldValues(field, input, dropdown);
          });
          
          // Input event - filter suggestions
          input.addEventListener('input', function() {
            filterAutocomplete(input, dropdown, field);
          });
          
          // Blur event - hide dropdown after a delay (to allow click)
          input.addEventListener('blur', function() {
            setTimeout(function() {
              dropdown.style.display = 'none';
            }, 200);
          });
        });
      }
      
      // Load field values from API or cache
      function loadFieldValues(field, input, dropdown) {
        if (autocompleteCache[field]) {
          // Use cached values
          populateAutocomplete(input, dropdown, autocompleteCache[field]);
        } else {
          // Fetch from API
          fetch('../../api/get_supplier_field_values.php?field=' + encodeURIComponent(field), {
            method: 'GET',
            credentials: 'same-origin'
          })
          .then(function(response) { return response.json(); })
          .then(function(data) {
            if (data.success && data.values) {
              autocompleteCache[field] = data.values;
              populateAutocomplete(input, dropdown, data.values);
            }
          })
          .catch(function(error) {
            console.error('Error loading field values:', error);
          });
        }
      }
      
      // Populate autocomplete dropdown
      function populateAutocomplete(input, dropdown, values) {
        var currentValue = input.value.toLowerCase();
        var filteredValues = values.filter(function(val) {
          return val.toLowerCase().includes(currentValue);
        });
        
        dropdown.innerHTML = '';
        
        if (filteredValues.length === 0) {
          dropdown.style.display = 'none';
          return;
        }
        
        filteredValues.forEach(function(value) {
          var item = document.createElement('div');
          item.textContent = value;
          item.style.cssText = 'padding: 8px 12px; cursor: pointer; font-size: 13px; color: #334155; border-bottom: 1px solid #f1f5f9;';
          
          item.addEventListener('mouseover', function() {
            this.style.background = '#f8fafc';
          });
          
          item.addEventListener('mouseout', function() {
            this.style.background = '#fff';
          });
          
          item.addEventListener('click', function() {
            input.value = value;
            dropdown.style.display = 'none';
            input.focus();
          });
          
          dropdown.appendChild(item);
        });
        
        dropdown.style.display = 'block';
        input.style.borderRadius = '6px 6px 0 0';
      }
      
      // Filter autocomplete based on input
      function filterAutocomplete(input, dropdown, field) {
        if (autocompleteCache[field]) {
          populateAutocomplete(input, dropdown, autocompleteCache[field]);
        }
      }
      
      // Initialize autocomplete when modals open
      var addSupplierBtn = document.getElementById('addSupplierBtn');
      if (addSupplierBtn) {
        addSupplierBtn.addEventListener('click', function() {
          setTimeout(initializeAutocomplete, 100);
        });
      }
      
      // Re-initialize when edit modal opens
      var originalEditSupplier = window.editSupplier;
      window.editSupplier = function(supplierId) {
        originalEditSupplier(supplierId);
        setTimeout(initializeAutocomplete, 100);
      };
      
      // Initial setup
      initializeAutocomplete();

      /* =========================
         Filtering Logic
         ========================= */
      function getActiveFilters() {
        return {
          name: (document.getElementById('filterName')?.value || '').trim().toLowerCase(),
          material: (document.getElementById('filterMaterial')?.value || '').trim().toLowerCase(),
          city: (document.getElementById('filterCity')?.value || '').trim().toLowerCase(),
          state: (document.getElementById('filterState')?.value || '').trim().toLowerCase()
        };
      }

      function markerMatchesFilters(supplier, filters) {
        if (!supplier) return false;
        // Name
        if (filters.name && (!supplier.name || supplier.name.toLowerCase().indexOf(filters.name) === -1)) return false;
        // Material
        if (filters.material && (!supplier.material || supplier.material.toLowerCase().indexOf(filters.material) === -1)) return false;
        // City
        if (filters.city && (!supplier.city || supplier.city.toLowerCase().indexOf(filters.city) === -1)) return false;
        // State
        if (filters.state && (!supplier.state || supplier.state.toLowerCase().indexOf(filters.state) === -1)) return false;
        return true;
      }

      function updateSupplierCount() {
        var filters = getActiveFilters();
        var hasFilters = filters.name || filters.material || filters.city || filters.state;
        var visibleSuppliers = 0;

        // Use suppliersCache (includes suppliers without coords) for counts
        (suppliersCache || []).forEach(function(supplier) {
          if (supplier && markerMatchesFilters(supplier, filters)) visibleSuppliers++;
        });

        var total = (suppliersCache || []).length;
        var countElement = document.getElementById('supplierCountNumber');
        if (countElement) {
          if (hasFilters && visibleSuppliers < total) {
            countElement.textContent = visibleSuppliers + ' of ' + total;
          } else {
            countElement.textContent = total;
          }
        }
      }

      // Toggle and populate supplier count dropdown
      function toggleSupplierCountList() {
        var list = document.getElementById('supplierCountList');
        if (!list) return;
        if (list.style.display === 'block') {
          list.style.display = 'none';
        } else {
          populateSupplierCountList();
          list.style.display = 'block';
        }
      }

      function populateSupplierCountList() {
        var list = document.getElementById('supplierCountList');
        if (!list) return;
        list.innerHTML = '';

        var filters = getActiveFilters();
        var visibleSuppliers = (suppliersCache || []).filter(function(supplier) {
          return supplier && markerMatchesFilters(supplier, filters);
        });

        if (visibleSuppliers.length === 0) {
          list.innerHTML = '<div style="padding:10px; color:#64748b;">No suppliers to show</div>';
          return;
        }

        visibleSuppliers.forEach(function(supplier) {
          var entry = document.createElement('div');
          entry.style.cssText = 'padding:8px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; gap:12px;';

          var left = document.createElement('div');
          left.style.cssText = 'flex:1; font-size:13px; color:#334155; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;';
          // Build concise inline details: Name (bold) — Material • City, State — Address (trimmed)
          var name = (supplier.name || 'Unknown');
          var parts = [];
          if (supplier.material) parts.push(supplier.material);
          if (supplier.city) parts.push(supplier.city);
          if (supplier.state) parts.push(supplier.state);
          var meta = parts.join(' • ');
          var address = supplier.address ? (' — ' + supplier.address) : '';
          left.innerHTML = '<span style="font-weight:600; color:#059669; margin-right:8px;">' + name + '</span>' +
                           '<span style="color:#64748b;">' + (meta || '') + '</span>' +
                           '<span style="color:#475569; margin-left:8px; font-size:12px;">' + (address || '') + '</span>';

          var right = document.createElement('div');
          right.style.cssText = 'flex:0 0 auto;';
          var btn = document.createElement('button');
          btn.textContent = 'Find in map';
          btn.className = 'btn';
          btn.style.cssText = 'padding:6px 8px; font-size:12px; white-space:nowrap;';
          btn.addEventListener('click', function(e){
            e.stopPropagation();
            findSupplierOnMap(supplier.id);
            var listEl = document.getElementById('supplierCountList'); if (listEl) listEl.style.display='none';
          });

          right.appendChild(btn);

          entry.appendChild(left);
          entry.appendChild(right);
          list.appendChild(entry);
        });
      }

      // Pan to supplier marker and open popup. If marker not yet plotted, attempt to plot or geocode.
      function findSupplierOnMap(supplierId) {
        var id = parseInt(supplierId, 10);
        var found = false;
        currentMarkers.forEach(function(marker) {
          if (marker.supplierData && marker.supplierData.id == id) {
            found = true;
            map.setView(marker.getLatLng(), Math.max(map.getZoom(), 10));
            marker.openPopup();
            showSupplierDetails(marker.supplierData);
          }
        });

        if (found) return;

        // Not plotted yet - try to find supplier in cache
        var supplier = (suppliersCache || []).find(function(s) { return s && parseInt(s.id,10) === id; });
        if (!supplier) return;

        var popupContent = '<div style="font-size:13px;line-height:1.4;">' +
          '<strong style="font-size:15px;display:block;margin-bottom:6px;">' + (supplier.name || 'Unknown') + '</strong>' +
          (supplier.material?('<div><strong>Material:</strong> ' + supplier.material + '</div>'):'') +
          (supplier.location_type?('<div><strong>Type:</strong> ' + supplier.location_type + '</div>'):'') +
          (supplier.address?('<div><strong>Street Address:</strong> ' + supplier.address + '</div>'):'') +
          (supplier.city?('<div><strong>City:</strong> ' + supplier.city + '</div>'):'') +
          (supplier.state?('<div><strong>State:</strong> ' + supplier.state + '</div>'):'') +
          (supplier.sales_contact?('<div><strong>Contact:</strong> ' + supplier.sales_contact + '</div>'):'') +
          (supplier.contact_number?('<div><strong>Phone:</strong> ' + supplier.contact_number + '</div>'):'') +
          (supplier.email?('<div><strong>Email:</strong> <a href="mailto:' + supplier.email + '">' + supplier.email + '</a></div>'):'') +
          (supplier.notes?('<div style="margin-top:6px;padding-top:6px;border-top:1px solid #e2e8f0;"><em>' + supplier.notes + '</em></div>'):'') +
          '</div>';

        if (supplier.latitude && supplier.longitude) {
          plotMarker(supplier, popupContent, parseFloat(supplier.latitude), parseFloat(supplier.longitude));
          // open the marker shortly after plotting
          setTimeout(function(){
            currentMarkers.forEach(function(marker) {
              if (marker.supplierData && marker.supplierData.id == id) {
                map.setView(marker.getLatLng(), Math.max(map.getZoom(), 10));
                marker.openPopup();
                showSupplierDetails(marker.supplierData);
              }
            });
          }, 250);
        } else {
          // No coordinates available — geocoding disabled.
          alert('No coordinates available for this supplier. Please edit the supplier and add latitude/longitude to enable map plotting.');
          showSupplierDetails(supplier);
        }
      }

      // Close supplier count list when clicking outside
      document.addEventListener('click', function(e) {
        var list = document.getElementById('supplierCountList');
        var count = document.getElementById('supplierCount');
        if (!list || !count) return;
        if (list.style.display === 'block') {
          if (!list.contains(e.target) && !count.contains(e.target)) {
            list.style.display = 'none';
          }
        }
      });

      // Attach click handler in JS (avoid inline onclick and reference errors)
      var supplierCountEl = document.getElementById('supplierCount');
      if (supplierCountEl) {
        supplierCountEl.addEventListener('click', function(e){
          e.stopPropagation();
          toggleSupplierCountList();
        });
      }

      // Expose some helpers for debugging in console
      window.findSupplierOnMap = findSupplierOnMap;
      window.getCurrentMarkers = function(){ return currentMarkers; };
      window.getSuppliersCache = function(){ return suppliersCache; };

      function applyFilters(refit) {
        var filters = getActiveFilters();
        var visibleMarkers = [];
        currentMarkers.forEach(function(marker) {
          if (marker.supplierData && markerMatchesFilters(marker.supplierData, filters)) {
            if (markerCluster) {
              if (!markerCluster.hasLayer(marker)) markerCluster.addLayer(marker);
            } else {
              if (!map.hasLayer(marker)) map.addLayer(marker);
            }
            visibleMarkers.push(marker);
          } else {
            if (markerCluster) {
              if (markerCluster.hasLayer(marker)) markerCluster.removeLayer(marker);
            } else {
              if (map.hasLayer(marker)) map.removeLayer(marker);
            }
          }
        });
        // Update supplier count display
        updateSupplierCount();
        if (refit && visibleMarkers.length > 0) {
          fitMapToVisibleMarkers(visibleMarkers);
        }
      }

      function fitMapToVisibleMarkers(markers) {
        if (!markers || markers.length === 0) return;
        var bounds = L.latLngBounds(markers.map(function(m){ return m.getLatLng(); }));
        var maxZoom = markers.length === 1 ? 10 : 12;
        map.fitBounds(bounds, { padding: [50,50], maxZoom: maxZoom });
      }

      function debounce(fn, delay) {
        var t; return function() {
          var args = arguments; var ctx = this;
          clearTimeout(t);
          t = setTimeout(function(){ fn.apply(ctx, args); }, delay);
        };
      }

      var filterInputs = ['filterName','filterMaterial','filterCity','filterState'];
      filterInputs.forEach(function(id){
        var el = document.getElementById(id);
        if (el) {
          el.addEventListener('input', debounce(function(){ applyFilters(true); }, 300));
        }
      });

      var clearBtn = document.getElementById('clearFiltersBtn');
      if (clearBtn) {
        clearBtn.addEventListener('click', function(){
          filterInputs.forEach(function(id){ var el = document.getElementById(id); if (el) el.value=''; });
          applyFilters(true);
        });
      }

      // Apply filters when suppliers load initially
      applyFilters(false);

      // Background geocoding removed: geocoding is disabled on this installation.
    })();
  </script>
</body>
</html>
