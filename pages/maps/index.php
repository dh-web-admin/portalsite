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
if (!can_access($role, 'maps')) {
  header('Location: /pages/dashboard/');
  exit();
}

$canEditMaps = can_edit_page('maps');
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

    /* Legend active state */
    .legend-item { cursor: pointer; padding: 4px 6px; border-radius: 6px; }
    .legend-item.active { background: #e2e8f0; }
    .legend-item.active .legend-supplier-name { font-weight: 700; }

    /* Slim action buttons (Maps page) */
    .btn-slim {
      padding: 8px 12px !important;
      font-size: 12px !important;
      font-weight: 700;
      line-height: 1 !important;
      border-radius: 10px !important;
      white-space: nowrap;
      height: 34px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
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
            <?php if ($canEditMaps): ?>
            <button id="addSupplierBtn" class="btn btn-primary btn-slim">+ Add Supplier</button>
            <?php endif; ?>
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

            <?php if ($canEditMaps): ?>
            <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
              <button id="topAddServiceBtn" class="btn btn-primary btn-slim">+ Add Service</button>
              <button id="topEditServiceBtn" class="btn btn-slim" style="background:#3b82f6;color:#fff;border:none;box-shadow:0 2px 6px rgba(59,130,246,0.18);">Edit Service</button>
            </div>
            <?php endif; ?>

          </div>
          
          <!-- Map and Legend Wrapper -->
          <div style="display: flex; gap: 12px; flex: 1; min-height: 0; margin-bottom: 20px;">
            <!-- Map Container -->
            <div id="map" style="flex: 1; min-height: 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-bottom: 2px solid #cbd5e1;"></div>
            
            <!-- Legend Box -->
            <div id="supplierLegend" style="width: 280px; background: #e8e8e8; border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.12);">
              <div style="padding: 14px 16px; font-weight: 600; color: #1e293b; font-size: 13px;">
                Supplier Legend
              </div>
              <div id="supplierLegendContent" style="flex: 1; overflow-y: auto; padding: 10px 14px; font-size: 12px; color: #334155;">
                <div style="padding: 8px; color: #94a3b8; text-align: center;">No suppliers loaded</div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
  
  <!-- Modern Color Picker Styles -->
  <style>
    .color-popover { position:absolute; top:44px; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:12px; width:280px; padding:12px; box-shadow:0 12px 32px rgba(2,6,23,0.18); display:none; z-index:6000; }
    .color-popover .swatches { display:grid; grid-template-columns:repeat(8, 1fr); gap:6px; margin-bottom:10px; }
    .color-popover .swatch { width:24px; height:24px; border-radius:6px; border:1px solid rgba(15,23,42,0.08); cursor:pointer; }
    .color-popover .row { display:flex; align-items:center; gap:10px; margin:8px 0; }
    .color-popover .preview { width:28px; height:28px; border-radius:8px; border:1px solid rgba(15,23,42,0.08); }
    .color-popover input[type="text"] { flex:1; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:12px; }
    .color-popover input[type="range"] { width:100%; height:8px; border-radius:999px; outline:none; -webkit-appearance:none; background:linear-gradient(90deg, #f00, #ff0, #0f0, #0ff, #00f, #f0f, #f00); }
    .color-popover .sat { background:linear-gradient(90deg, #bbb, #fff); }
    .color-popover .light { background:linear-gradient(90deg, #000, #aaa, #fff); }
    .color-popover .actions { display:flex; justify-content:flex-end; gap:8px; margin-top:8px; }
    .color-popover .btn { padding:6px 10px; border-radius:8px; border:1px solid #cbd5e1; background:#f8fafc; color:#0f172a; cursor:pointer; font-size:12px; }
    .color-popover .btn.primary { background:#0ea5e9; border-color:#0ea5e9; color:#fff; }
  </style>
  
  <!-- Add Supplier Modal -->
  <div id="addSupplierModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.35);backdrop-filter: blur(6px);-webkit-backdrop-filter: blur(6px);align-items:center;justify-content:center;z-index:4000;padding:20px;overflow-y:auto;">
    <div style="background:#fff;border-radius:12px;padding:24px;max-width:600px;width:100%;box-shadow:0 8px 30px rgba(2,6,23,0.2);max-height:90vh;overflow-y:auto;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin:0 0 20px 0;">
        <div style="font-size:20px;color:#1e293b;font-weight:700;">Add New Supplier</div>
        <div style="display:flex;align-items:center;gap:12px;">
          <div style="font-size:13px;color:#475569;font-weight:600;">Service: <span id="addSupplierServiceName" style="font-weight:700;color:#0f172a;">—</span></div>
          <div id="addLinkedBadge" style="display:none;margin-left:12px; background:#eef2ff;color:#0f172a;padding:6px 10px;border-radius:8px;font-weight:600;font-size:13px;">
            Linked: <span id="addLinkedClientName"></span>
            <button id="clearAddLinked" type="button" style="margin-left:10px;background:transparent;border:none;color:#2563eb;cursor:pointer;font-weight:700;">×</button>
          </div>
          <div style="display:flex;align-items:center;gap:8px; position:relative;">
            <button type="button" id="addSupplierColorBtn" title="Set color for all suppliers with this name" style="width:34px;height:34px;border-radius:8px;border:1px solid #e6edf0;background:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;padding:6px;">
              <span id="addSupplierColorSwatch" style="display:block;width:18px;height:18px;border-radius:6px;background:#3b82f6;border:1px solid rgba(0,0,0,0.06);cursor:pointer;"></span>
            </button>
            <input type="color" id="addSupplierColorInput" style="display:none;" />
            <!-- Modern color popover (Add) -->
            <div id="addColorPopover" class="color-popover">
              <div class="swatches" id="addPickerSwatches"></div>
              <div class="row"><div class="preview" id="addPickerPreview"></div><input type="text" id="addPickerHex" placeholder="#RRGGBB" /></div>
              <div class="row" style="flex-direction:column; align-items:stretch; gap:8px;">
                <input type="range" min="0" max="360" value="210" id="addPickerHue" />
                <input class="sat" type="range" min="0" max="100" value="100" id="addPickerSat" />
                <input class="light" type="range" min="0" max="100" value="50" id="addPickerLight" />
              </div>
              <div class="actions">
                <button type="button" class="btn" data-role="close">Cancel</button>
                <button type="button" class="btn primary" data-role="apply">Apply</button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <form id="addSupplierForm" style="display:grid;gap:16px;">
        <div style="position:relative;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="position:relative;">
              <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Name *</label>
              <input type="text" name="name" id="addSupplierName" required autocomplete="off" class="autocomplete-input" data-field="name" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
              <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
            </div>
            <div style="position:relative;">
              <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Location Name</label>
              <input type="text" name="location_name" id="addSupplierLocationName" autocomplete="off" class="autocomplete-input" data-field="location_name" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
              <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
            </div>
          </div>
          <input type="hidden" name="color" id="addSupplierColor" />
          <input type="hidden" name="linked_client_id" id="addLinkedClientId" />
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
        <!-- Third row: Supply Method + Location Phone Number -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Supply Method</label>
            <input type="text" name="supply_method" autocomplete="off" class="autocomplete-input" data-field="supply_method" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Location Phone Number</label>
            <input type="tel" name="location_phone" autocomplete="off" class="autocomplete-input" data-field="location_phone" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
        </div>

        <!-- Fourth row: Sales Contact Name (full width) -->
        <div style="position:relative;">
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Sales Contact Name</label>
          <input type="text" name="sales_contact" autocomplete="off" class="autocomplete-input" data-field="sales_contact" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
          <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
        </div>

        <!-- Next row: Sales Contact Number + Email -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Sales Contact Number</label>
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
  <div id="editSupplierModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.35);backdrop-filter: blur(6px);-webkit-backdrop-filter: blur(6px);align-items:center;justify-content:center;z-index:4000;padding:20px;overflow-y:auto;">
    <div style="background:#fff;border-radius:12px;padding:24px;max-width:600px;width:100%;box-shadow:0 8px 30px rgba(2,6,23,0.2);max-height:90vh;overflow-y:auto;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin:0 0 20px 0;">
        <h3 style="margin:0;font-size:20px;color:#1e293b;">Edit Supplier</h3>
        <div style="display:flex;align-items:center;gap:8px; position:relative;">
          <!-- Color picker icon: clicking the swatch will open the native color picker -->
          <button type="button" id="editSupplierColorBtn" title="Set color for all suppliers with this name" style="width:34px;height:34px;border-radius:8px;border:1px solid #e6edf0;background:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;padding:6px;">
            <span id="editSupplierColorSwatch" style="display:block;width:18px;height:18px;border-radius:6px;background:#3b82f6;border:1px solid rgba(0,0,0,0.06);cursor:pointer;"></span>
          </button>
          <input type="color" id="editSupplierColorInput" style="display:none;" />
          <!-- Modern color popover (Edit) -->
          <div id="editColorPopover" class="color-popover">
            <div class="swatches" id="editPickerSwatches"></div>
            <div class="row"><div class="preview" id="editPickerPreview"></div><input type="text" id="editPickerHex" placeholder="#RRGGBB" /></div>
            <div class="row" style="flex-direction:column; align-items:stretch; gap:8px;">
              <input type="range" min="0" max="360" value="210" id="editPickerHue" />
              <input class="sat" type="range" min="0" max="100" value="100" id="editPickerSat" />
              <input class="light" type="range" min="0" max="100" value="50" id="editPickerLight" />
            </div>
            <div class="actions">
              <button type="button" class="btn" data-role="close">Cancel</button>
              <button type="button" class="btn primary" data-role="apply">Apply</button>
            </div>
          </div>
        </div>
      </div>
      <form id="editSupplierForm" style="display:grid;gap:16px;">
        <input type="hidden" name="id" id="editSupplierId" />
        <input type="hidden" name="color" id="editSupplierColor" />
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Name *</label>
            <input type="text" name="name" id="editSupplierName" required autocomplete="off" class="autocomplete-input" data-field="name" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Location Name</label>
            <input type="text" name="location_name" id="editSupplierLocationName" autocomplete="off" class="autocomplete-input" data-field="location_name" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
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
        <!-- Third row: Supply Method + Location Phone Number -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Supply Method</label>
            <input type="text" name="supply_method" id="editSupplierSupplyMethod" autocomplete="off" class="autocomplete-input" data-field="supply_method" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Location Phone Number</label>
            <input type="tel" name="location_phone" id="editSupplierLocationPhone" autocomplete="off" class="autocomplete-input" data-field="location_phone" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
            <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
          </div>
        </div>

        <!-- Fourth row: Sales Contact Name (full width) -->
        <div style="position:relative;">
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Sales Contact Name</label>
          <input type="text" name="sales_contact" id="editSupplierSalesContact" autocomplete="off" class="autocomplete-input" data-field="sales_contact" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
          <div class="autocomplete-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:1000;"></div>
        </div>

        <!-- Next row: Sales Contact Number + Email -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div style="position:relative;">
            <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Sales Contact Number</label>
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
        <div>
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Coordinates * <span style="font-weight:400;font-size:12px;color:#94a3b8">(format: lat, lng)</span></label>
          <input type="text" id="editSupplierCoordinates" placeholder="e.g. 41.0998, -80.6495" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
          <div id="editSupplierCoordError" class="coord-error" aria-live="polite" style="display:none;margin-top:6px;color:#ef4444;font-size:12px;"></div>
          <input type="hidden" name="latitude" id="editSupplierLatitude" />
          <input type="hidden" name="longitude" id="editSupplierLongitude" />
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

  <!-- Add Service Modal -->
  <div id="addServiceModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.35);backdrop-filter: blur(6px);-webkit-backdrop-filter: blur(6px);align-items:center;justify-content:center;z-index:4000;padding:20px;overflow-y:auto;">
    <div style="background:#fff;border-radius:12px;padding:24px;max-width:520px;width:100%;box-shadow:0 8px 30px rgba(2,6,23,0.2);max-height:90vh;overflow-y:auto;">
      <h3 style="margin:0 0 16px 0;font-size:20px;color:#1e293b;">Add Service</h3>
      <form id="addServiceForm" style="display:grid;gap:14px;">
        <div>
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Service Name *</label>
          <input type="text" id="addServiceName" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px;">
          <button type="button" id="cancelAddServiceBtn" class="btn" style="padding:10px 20px;">Cancel</button>
          <button type="submit" id="confirmAddServiceBtn" class="btn btn-primary" style="padding:10px 20px;">Add</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Service Modal -->
  <div id="editServiceModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.35);backdrop-filter: blur(6px);-webkit-backdrop-filter: blur(6px);align-items:center;justify-content:center;z-index:4000;padding:20px;overflow-y:auto;">
    <div style="background:#fff;border-radius:12px;padding:24px;max-width:520px;width:100%;box-shadow:0 8px 30px rgba(2,6,23,0.2);max-height:90vh;overflow-y:auto;">
      <h3 style="margin:0 0 16px 0;font-size:20px;color:#1e293b;">Edit Service</h3>
      <form id="editServiceForm" style="display:grid;gap:14px;">
        <input type="hidden" id="editServiceOldName" />
        <div>
          <label style="display:block;font-size:13px;margin-bottom:6px;color:#475569;font-weight:600;">Service Name *</label>
          <input type="text" id="editServiceNewName" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;" />
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px;">
          <button type="button" id="deleteServiceBtn" class="btn btn-danger" style="padding:10px 20px;background:#ef4444;border-color:#ef4444;color:#fff;">Delete</button>
          <button type="button" id="cancelEditServiceBtn" class="btn" style="padding:10px 20px;">Cancel</button>
          <button type="submit" id="confirmEditServiceBtn" class="btn btn-primary" style="padding:10px 20px;">Save</button>
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

      function cssEscape(value) {
        var v = String(value == null ? '' : value);
        if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(v);
        return v.replace(/[^a-zA-Z0-9_\-]/g, function(ch){ return '\\' + ch; });
      }
      // Delete service from Edit Service modal
      var deleteServiceBtn = document.getElementById('deleteServiceBtn');
      if (deleteServiceBtn) {
        deleteServiceBtn.addEventListener('click', function(e){
          e.preventDefault();
          var oldName = editServiceOldName ? editServiceOldName.value.trim() : (currentService || '');
          if (!oldName) { alert('No service selected to delete'); return; }
          if (!confirm('Deleting this service will also delete all associated suppliers. This action cannot be undone. Continue?')) return;
          deleteServiceBtn.disabled = true;
          deleteServiceBtn.textContent = 'Deleting...';
          var fd = new FormData();
          fd.append('service_name', oldName);
          fetch('../../api/delete_service.php', { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(data){
              if (data && data.success) {
                currentService = null;
                var ribbon = document.getElementById('mapRibbon');
                if (ribbon) ribbon.innerHTML = '';
                loadRibbon();
                try { populateRemoveServiceSelect(); } catch (e) {}
                closeModal(editServiceModal);
                resetSupplierDetails();
              } else {
                alert((data && data.message) ? data.message : 'Failed to delete service');
              }
            })
            .catch(function(err){ console.error(err); alert('Failed to delete service'); })
            .finally(function(){ deleteServiceBtn.disabled = false; deleteServiceBtn.textContent = 'Delete'; });
        });
      }

      // Locate Me button logic
      var locateBtn = document.getElementById('locateMeBtn');
      var locateMarker = null;
      if (locateBtn) {
        locateBtn.addEventListener('click', function() {
          if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser.');
            return;
          }
          locateBtn.disabled = true;
          locateBtn.textContent = 'Locating...';
          navigator.geolocation.getCurrentPosition(function(pos) {
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            map.setView([lat, lng], 14);
            // Remove previous marker
            if (locateMarker) { map.removeLayer(locateMarker); }
            locateMarker = L.marker([lat, lng], {
              icon: L.divIcon({
                className: 'custom-marker-icon',
                html: '<svg width="25" height="41" viewBox="0 0 25 41" xmlns="http://www.w3.org/2000/svg"><path d="M12.5 0C5.6 0 0 5.6 0 12.5c0 8.5 12.5 28.5 12.5 28.5S25 21 25 12.5C25 5.6 19.4 0 12.5 0z" fill="#06b6d4" stroke="#fff" stroke-width="1.5"/><circle cx="12.5" cy="12.5" r="5" fill="#fff" opacity="0.9"/></svg>',
                iconSize: [25, 41],
                iconAnchor: [12.5, 41],
                popupAnchor: [0, -41]
              })
            }).addTo(map);
            locateMarker.bindPopup('<b>You are here</b>').openPopup();
            locateBtn.disabled = false;
            locateBtn.textContent = 'Locate Me';
          }, function(err) {
            alert('Unable to retrieve your location.');
            locateBtn.disabled = false;
            locateBtn.textContent = 'Locate Me';
          });
        });
      }
      
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
      // Per-name color overrides selected by the user
      var supplierColorOverrides = {};
      var currentService = null;
      var activeLegendName = '';
      var legendSuppliersCache = [];
      
      function showSupplierDetails(supplier) {
        var detailsBox = document.getElementById('supplierDetailsBox');
        if (!detailsBox) return;

        // Build a cleaner, class-based HTML for the details box
        var html = '';
        html += '<div class="supplier-details-left">';
        html += '<div class="supplier-grid">';
        html += '<div class="supplier-name">' + (supplier.name || 'Unknown') + (supplier.location_name ? ' — <span style="font-weight:600;color:#475569;">' + supplier.location_name + '</span>' : '') + '</div>';
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
      
      // Geocode an address via Nominatim and plot the marker when found.
      // Requests are intentionally simple and rate-limited by sequential callers.
      function geocodeAndPlotMarker(supplier, popupContent) {
        try {
          var qparts = [];
          if (supplier.address) qparts.push(supplier.address);
          if (supplier.city) qparts.push(supplier.city);
          if (supplier.state) qparts.push(supplier.state);
          if (supplier.location_name && (!supplier.address || supplier.address.trim() === '')) qparts.push(supplier.location_name);
          var q = qparts.join(', ');
          if (!q) { return; }
          var url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q);
          // Use fetch to call Nominatim; browsers will send Referer header which Nominatim accepts.
          fetch(url, { method: 'GET' }).then(function(r){ return r.json(); }).then(function(data){
            if (data && data.length > 0) {
              var lat = parseFloat(data[0].lat);
              var lon = parseFloat(data[0].lon);
              if (!isNaN(lat) && !isNaN(lon)) {
                // update supplier object so it's available on marker and UI
                try { supplier.latitude = String(lat); supplier.longitude = String(lon); } catch(e){}
                plotMarker(supplier, popupContent, lat, lon);
              }
            }
          }).catch(function(e){ console.warn('Geocode failed for', q, e); });
        } catch(e) { console.warn('geocodeAndPlotMarker error', e); }
      }

      // Vertical wrap behavior: when user pans past near-polar latitudes, jump the view
      // so the map appears to 'roll over' from bottom/top. This creates a seamless
      // UX for panning vertically without permanently hitting a hard stop.
      (function enableVerticalWrap(){
        var WRAP_LAT_THRESHOLD = 85; // degrees latitude near pole to trigger wrap
        var WRAP_OFFSET = 170; // degrees to subtract/add to center latitude
        try {
          map.on('moveend', function() {
            try {
              var c = map.getCenter();
              if (c && typeof c.lat === 'number') {
                if (c.lat > WRAP_LAT_THRESHOLD) {
                  map.setView([c.lat - WRAP_OFFSET, c.lng], map.getZoom(), {animate:false});
                } else if (c.lat < -WRAP_LAT_THRESHOLD) {
                  map.setView([c.lat + WRAP_OFFSET, c.lng], map.getZoom(), {animate:false});
                }
              }
            } catch (e) { /* non-fatal */ }
          });
        } catch (e) { console.warn('Vertical wrap initialization failed', e); }
      })();
      
      // Normalize color from supplier object
      function normalizeSupplierColor(s) {
        try {
          var c = (s && (s.color || s.pin_color)) ? (s.color || s.pin_color) : '';
          if (typeof c === 'string') c = c.trim();
          // Accept hex like #RRGGBB or hsl(...)
          if (c && (/^#([0-9a-fA-F]{6})$/.test(c) || /^hsl\(/i.test(c))) return c;
          // Fallback to name-based color
          return getColorForSupplier(s && s.name ? s.name : '');
        } catch(e) { return getColorForSupplier(s && s.name ? s.name : ''); }
      }

      // Function to plot marker on map
      function plotMarker(supplier, popupContent, lat, lng) {
        // Prefer explicit DB color; otherwise derive from name
        var markerColor = normalizeSupplierColor(supplier);
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
      // Function to update the supplier legend
      function updateSupplierLegend(suppliers) {
        var legendContent = document.getElementById('supplierLegendContent');
        if (!legendContent) return;

        legendSuppliersCache = Array.isArray(suppliers) ? suppliers.slice() : [];
        
        if (!suppliers || suppliers.length === 0) {
          legendContent.innerHTML = '<div style="padding: 8px; color: #94a3b8; text-align: center;">No suppliers loaded</div>';
          return;
        }
        
        // Get unique suppliers and their colors
        var uniqueSuppliers = {};
        suppliers.forEach(function(supplier) {
          if (supplier && supplier.name) {
            if (!uniqueSuppliers[supplier.name]) {
              var color = normalizeSupplierColor(supplier);
              // Ensure we have a valid color
              if (!color) color = '#667eea';
              uniqueSuppliers[supplier.name] = color;
            }
          }
        });
        
        // Sort by name for consistent display
        var sortedNames = Object.keys(uniqueSuppliers).sort();
        
        // Build legend items as DOM elements
        legendContent.innerHTML = '';
        sortedNames.forEach(function(name) {
          var color = uniqueSuppliers[name];
          var itemDiv = document.createElement('div');
          itemDiv.className = 'legend-item';
          if (activeLegendName && activeLegendName.toLowerCase() === name.toLowerCase()) {
            itemDiv.classList.add('active');
          }
          itemDiv.style.display = 'flex';
          itemDiv.style.alignItems = 'center';
          itemDiv.style.gap = '10px';
          
          var swatch = document.createElement('div');
          swatch.className = 'legend-color-swatch';
          swatch.style.width = '12px';
          swatch.style.height = '12px';
          swatch.style.borderRadius = '50%';
          swatch.style.backgroundColor = color;
          swatch.style.flexShrink = '0';
          swatch.style.display = 'inline-block';
          
          var nameDiv = document.createElement('div');
          nameDiv.className = 'legend-supplier-name';
          nameDiv.style.fontSize = '13px';
          nameDiv.style.color = '#000000';
          nameDiv.textContent = name;
          nameDiv.title = name;
          
          itemDiv.appendChild(swatch);
          itemDiv.appendChild(nameDiv);
          itemDiv.addEventListener('click', function(e){
            e.stopPropagation(); // Prevent document click listener from firing
            var clicked = name.toString();
            if (activeLegendName && activeLegendName.toLowerCase() === clicked.toLowerCase()) {
              activeLegendName = '';
            } else {
              activeLegendName = clicked;
            }
            updateSupplierLegend(legendSuppliersCache);
            applyFilters(true);
          });
          legendContent.appendChild(itemDiv);
        });
      }
      
      // Close legend filter when clicking outside the legend
      document.addEventListener('click', function(e) {
        var supplierLegend = document.getElementById('supplierLegend');
        if (supplierLegend && !supplierLegend.contains(e.target)) {
          if (activeLegendName) {
            activeLegendName = '';
            updateSupplierLegend(legendSuppliersCache);
            applyFilters(true);
          }
        }
      });
      
      function clearMarkers() {
        if (markerCluster) {
          markerCluster.clearLayers();
        } else {
          currentMarkers.forEach(function(marker) {
            map.removeLayer(marker);
          });
        }
        currentMarkers = [];
        updateSupplierLegend([]);
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
            suppliersCache = (data.suppliers || []).map(function(s){
              // Normalize color property from API (color or pin_color)
              if (s && s.pin_color && !s.color) s.color = s.pin_color;
              return s;
            });
            if (data.suppliers.length === 0) {
              // No suppliers for this service
              updateSupplierLegend([]);
              updateSupplierCount();
              return map.setView([39.8283, -98.5795], 5);
            }
            var suppliersNeedingGeocode = [];
            var suppliersWithCoords = [];
            
            // Separate suppliers with and without coordinates
            data.suppliers.forEach(function(supplier) {
              if (supplier && supplier.pin_color && !supplier.color) supplier.color = supplier.pin_color;
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
                updateSupplierLegend(suppliersWithCoords);
                applyFilters(true);
                updateSupplierDropdown();
              }
            }
            addNextBatch();
            
            // Attempt to geocode suppliers without cached coordinates (rate-limited sequentially)
            if (suppliersNeedingGeocode.length > 0) {
              var geocodeIdx = 0;
              function geocodeNext() {
                if (geocodeIdx >= suppliersNeedingGeocode.length) {
                  // finished geocoding batch
                  updateSupplierLegend(suppliersWithCoords.concat(suppliersNeedingGeocode.filter(function(s){ return s.latitude && s.longitude; })));
                  applyFilters(true);
                  updateSupplierDropdown();
                  return;
                }
                var s = suppliersNeedingGeocode[geocodeIdx];
                // build popupContent similar to above
                try {
                  var popupContent = '<div style="font-size:13px;line-height:1.4;">' + '<strong style="font-size:15px;display:block;margin-bottom:6px;">' + (s.name || 'Unknown') + '</strong>';
                  if (s.material) popupContent += '<div><strong>Material:</strong> ' + s.material + '</div>';
                  if (s.location_type) popupContent += '<div><strong>Type:</strong> ' + s.location_type + '</div>';
                  if (s.address) popupContent += '<div><strong>Street Address:</strong> ' + s.address + '</div>';
                  if (s.city) popupContent += '<div><strong>City:</strong> ' + s.city + '</div>';
                  if (s.state) popupContent += '<div><strong>State:</strong> ' + s.state + '</div>';
                  if (s.sales_contact) popupContent += '<div><strong>Contact:</strong> ' + s.sales_contact + '</div>';
                  if (s.contact_number) popupContent += '<div><strong>Phone:</strong> ' + s.contact_number + '</div>';
                  if (s.email) popupContent += '<div><strong>Email:</strong> <a href="mailto:' + s.email + '">' + s.email + '</a></div>';
                  if (s.notes) popupContent += '<div style="margin-top:6px;padding-top:6px;border-top:1px solid #e2e8f0;"><em>' + s.notes + '</em></div>';
                  popupContent += '</div>';
                } catch(e) { var popupContent = '<div>' + (s.name || 'Unknown') + '</div>'; }
                geocodeAndPlotMarker(s, popupContent);
                geocodeIdx++;
                // Nominatim policy: avoid rapid-fire requests; wait ~1100ms between geocoding calls
                setTimeout(geocodeNext, 1100);
              }
              geocodeNext();
            }
            
            // applyFilters and updateSupplierDropdown are called after batch plotting completes
          } else {
            // No suppliers found - set default US center view
            updateSupplierLegend([]);
            map.setView([39.8283, -98.5795], 5);
          }
        })
        .catch(function(error) {
          console.error('Error loading suppliers:', error);
          updateSupplierLegend([]);
          map.setView([39.8283, -98.5795], 5);
        });
      }
      
      // Function to load and render ribbon buttons
      function loadRibbon() {
        var ribbon = document.getElementById('mapRibbon');
        if (!ribbon) return;

        // localStorage helpers for ribbon order fallback
        function saveRibbonOrderLocal(order) {
          try { localStorage.setItem('supplierRibbonOrder', JSON.stringify(order)); } catch (e) { console.warn('Failed to save ribbon order locally', e); }
        }
        function loadRibbonOrderLocal() {
          try { var v = localStorage.getItem('supplierRibbonOrder'); return v ? JSON.parse(v) : null; } catch (e) { return null; }
        }

        fetch('../../api/get_services.php', {
          method: 'GET',
          credentials: 'same-origin'
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
          if (data.success && data.services) {
            // Only clear ribbon if no services exist
            var services = data.services || [];
            if (services.length === 0) {
              ribbon.innerHTML = '<div style="padding:10px; color:#64748b; font-size:13px; font-style:italic;">No services available. Add one using the sidebar.</div>';
              return;
            }

            // Remove only buttons that do not match the current services list
            var existingBtns = Array.from(ribbon.querySelectorAll('.map-ribbon-btn'));
            existingBtns.forEach(function(btn){
              var n = btn.getAttribute('data-map');
              if (services.indexOf(n) === -1) {
                try { btn.remove(); } catch (e) { if (btn.parentNode) btn.parentNode.removeChild(btn); }
              }
            });
            existingBtns = Array.from(ribbon.querySelectorAll('.map-ribbon-btn'));
            var existingNames = existingBtns.map(function(btn){ return btn.getAttribute('data-map'); });

            // If user reordered locally previously, prefer that order as a client-side fallback
            var localOrder = loadRibbonOrderLocal();
            if (Array.isArray(localOrder) && localOrder.length > 0) {
              // filter localOrder to services present and then append any missing services
              var ordered = localOrder.filter(function(n){ return services.indexOf(n) !== -1; });
              services.forEach(function(s){ if (ordered.indexOf(s) === -1) ordered.push(s); });
              services = ordered;
            }

            services.forEach(function(serviceName, index) {
              if (!existingNames.includes(serviceName)) {
                var btn = document.createElement('button');
                btn.className = 'map-ribbon-btn';
                btn.setAttribute('data-map', serviceName);
                btn.textContent = serviceName.charAt(0).toUpperCase() + serviceName.slice(1).replace(/-/g, ' ');

                // Make ribbon items draggable (client-side reorder)
                btn.setAttribute('draggable', 'true');
                btn.style.cursor = 'grab';

                // Activate current service if set, otherwise activate first if none are active
                if (currentService && serviceName === currentService) {
                  btn.classList.add('active');
                } else if (index === 0 && !ribbon.querySelector('.map-ribbon-btn.active')) {
                  btn.classList.add('active');
                }

                // Drag handlers
                btn.addEventListener('dragstart', function(e){
                  window._draggedRibbonItem = this;
                  this.classList.add('dragging');
                  try { e.dataTransfer.setData('text/plain', this.getAttribute('data-map')); } catch (ex) {}
                });
                btn.addEventListener('dragend', function(){
                  this.classList.remove('dragging');
                  window._draggedRibbonItem = null;
                });

                btn.addEventListener('dragover', function(e){
                  e.preventDefault();
                  var dragged = window._draggedRibbonItem;
                  if (!dragged || dragged === this) return;
                  var rect = this.getBoundingClientRect();
                  var after = (e.clientX - rect.left) > (rect.width / 2);
                  var parent = this.parentNode;
                  if (after && this.nextSibling !== dragged) parent.insertBefore(dragged, this.nextSibling);
                  else if (!after && parent.firstChild !== dragged) parent.insertBefore(dragged, this);
                });

                btn.addEventListener('drop', function(e){
                  e.preventDefault();
                  try {
                    var order = Array.from(ribbon.querySelectorAll('.map-ribbon-btn')).map(function(b){ return b.getAttribute('data-map'); });
                    // Try save to server; if it fails, persist locally so refresh preserves order for this browser
                    fetch('../../api/update_service_order.php', { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ order: JSON.stringify(order) }) })
                      .then(function(res){ if (!res.ok) throw new Error('Server returned ' + res.status); return res.json(); })
                      .then(function(json){ if (json && json.success) { try { localStorage.removeItem('supplierRibbonOrder'); } catch(e){} } else { saveRibbonOrderLocal(order); } })
                      .catch(function(err){ console.warn('Persisting ribbon order to server failed, saving locally', err); saveRibbonOrderLocal(order); });
                  } catch (ex) { console.warn('Error handling drop', ex); }
                });

                btn.addEventListener('click', function(){
                  var selectedService = this.getAttribute('data-map');
                  ribbon.querySelectorAll('.map-ribbon-btn').forEach(function(b){ b.classList.remove('active'); });
                  this.classList.add('active');
                  loadSuppliers(selectedService);
                });

                ribbon.appendChild(btn);
              }
            });

            // Always select the first service in the ribbon on page load
            if (services.length > 0) {
              // clear any previous active state, then activate first button
              ribbon.querySelectorAll('.map-ribbon-btn').forEach(function(b){ b.classList.remove('active'); });
              var firstBtn = ribbon.querySelector('.map-ribbon-btn');
              if (firstBtn) {
                firstBtn.classList.add('active');
                // Load suppliers for the first service
                loadSuppliers(firstBtn.getAttribute('data-map'));
              }
            }
          }
        })
        .catch(function(error) {
          console.error('Error loading services:', error);
          ribbon.innerHTML = '<div style="padding:10px; color:#ef4444; font-size:13px;">Error loading services. Please refresh the page.</div>';
        });
      }
      
      // Initialize ribbon
      loadRibbon();

      /* =========================
         Add/Edit Service (Top Right) Modals
         ========================= */
      var topAddServiceBtn = document.getElementById('topAddServiceBtn');
      var topEditServiceBtn = document.getElementById('topEditServiceBtn');
      var addServiceModal = document.getElementById('addServiceModal');
      var editServiceModal = document.getElementById('editServiceModal');
      var addServiceForm = document.getElementById('addServiceForm');
      var editServiceForm = document.getElementById('editServiceForm');
      var addServiceNameInput = document.getElementById('addServiceName');
      var editServiceOldName = document.getElementById('editServiceOldName');
      var editServiceNewName = document.getElementById('editServiceNewName');
      var cancelAddServiceBtn = document.getElementById('cancelAddServiceBtn');
      var cancelEditServiceBtn = document.getElementById('cancelEditServiceBtn');
      var confirmAddServiceBtn = document.getElementById('confirmAddServiceBtn');
      var confirmEditServiceBtn = document.getElementById('confirmEditServiceBtn');

      function openModal(modalEl) {
        if (!modalEl) return;
        modalEl.style.display = 'flex';
        // Ensure Leaflet redraws to avoid clipped tiles when modal changes layout
        try { setTimeout(function(){ if (typeof map !== 'undefined' && map && map.invalidateSize) map.invalidateSize(); }, 50); } catch(e) {}
      }
      function closeModal(modalEl) {
        if (!modalEl) return;
        modalEl.style.display = 'none';
        try { setTimeout(function(){ if (typeof map !== 'undefined' && map && map.invalidateSize) map.invalidateSize(); }, 50); } catch(e) {}
      }

      if (topAddServiceBtn && addServiceModal) {
        topAddServiceBtn.addEventListener('click', function(){
          openModal(addServiceModal);
          if (addServiceNameInput) {
            addServiceNameInput.value = '';
            setTimeout(function(){ try { addServiceNameInput.focus(); } catch(e){} }, 50);
          }
        });
      }
      if (topEditServiceBtn && editServiceModal) {
        topEditServiceBtn.addEventListener('click', function(){
          if (!currentService) {
            alert('Select a service first.');
            return;
          }
          if (editServiceOldName) editServiceOldName.value = currentService;
          if (editServiceNewName) {
            editServiceNewName.value = currentService;
            setTimeout(function(){ try { editServiceNewName.focus(); editServiceNewName.select(); } catch(e){} }, 50);
          }
          openModal(editServiceModal);
        });
      }

      if (cancelAddServiceBtn) cancelAddServiceBtn.addEventListener('click', function(){ closeModal(addServiceModal); });
      if (cancelEditServiceBtn) cancelEditServiceBtn.addEventListener('click', function(){ closeModal(editServiceModal); });

      if (addServiceModal) {
        addServiceModal.addEventListener('click', function(e){ if (e.target === addServiceModal) closeModal(addServiceModal); });
      }
      if (editServiceModal) {
        editServiceModal.addEventListener('click', function(e){ if (e.target === editServiceModal) closeModal(editServiceModal); });
      }

      if (addServiceForm) {
        addServiceForm.addEventListener('submit', function(e){
          e.preventDefault();
          var name = addServiceNameInput ? addServiceNameInput.value.trim() : '';
          if (!name) { alert('Please enter a service name'); return; }
          if (confirmAddServiceBtn) { confirmAddServiceBtn.disabled = true; confirmAddServiceBtn.textContent = 'Adding...'; }
          var formData = new FormData();
          formData.append('service_name', name);
          fetch('../../api/add_service.php', { method: 'POST', credentials: 'same-origin', body: formData })
            .then(function(r){ return r.json(); })
            .then(function(data){
              if (data && data.success) {
                currentService = name;
                var ribbon = document.getElementById('mapRibbon');
                if (ribbon) ribbon.innerHTML = '';
                loadRibbon();
                try { populateRemoveServiceSelect(); } catch (e) {}
                closeModal(addServiceModal);
              } else {
                alert((data && data.message) ? data.message : 'Failed to add service');
              }
            })
            .catch(function(err){ console.error(err); alert('Failed to add service'); })
            .finally(function(){ if (confirmAddServiceBtn) { confirmAddServiceBtn.disabled = false; confirmAddServiceBtn.textContent = 'Add'; } });
        });
      }

      if (editServiceForm) {
        editServiceForm.addEventListener('submit', function(e){
          e.preventDefault();
          var oldName = editServiceOldName ? editServiceOldName.value.trim() : '';
          var newName = editServiceNewName ? editServiceNewName.value.trim() : '';
          if (!oldName || !newName) { alert('Service name required'); return; }
          if (confirmEditServiceBtn) { confirmEditServiceBtn.disabled = true; confirmEditServiceBtn.textContent = 'Saving...'; }
          var fd = new FormData();
          fd.append('old_name', oldName);
          fd.append('new_name', newName);
          fetch('../../api/rename_service.php', { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(data){
              if (data && data.success) {
                currentService = newName;
                var ribbon = document.getElementById('mapRibbon');
                if (ribbon) ribbon.innerHTML = '';
                loadRibbon();
                try { populateRemoveServiceSelect(); } catch (e) {}
                closeModal(editServiceModal);
              } else {
                alert((data && data.message) ? data.message : 'Failed to edit service');
              }
            })
            .catch(function(err){ console.error(err); alert('Failed to edit service'); })
            .finally(function(){ if (confirmEditServiceBtn) { confirmEditServiceBtn.disabled = false; confirmEditServiceBtn.textContent = 'Save'; } });
        });
      }
      
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

      // ===== Modern Color Picker Utilities =====
      function clamp(n, min, max){ return Math.max(min, Math.min(max, n)); }
      function hslToHex(h, s, l){
        h = (Number(h)||0)/360; s = clamp(Number(s)||0,0,100)/100; l = clamp(Number(l)||0,0,100)/100;
        var r, g, b;
        if (s === 0) { r = g = b = l; }
        else {
          var hue2rgb = function(p, q, t){ if (t<0) t+=1; if (t>1) t-=1; if (t<1/6) return p+(q-p)*6*t; if (t<1/2) return q; if (t<2/3) return p+(q-p)*(2/3 - t)*6; return p; };
          var q = l < 0.5 ? l * (1 + s) : l + s - l * s; var p = 2 * l - q;
          r = hue2rgb(p, q, h + 1/3); g = hue2rgb(p, q, h); b = hue2rgb(p, q, h - 1/3);
        }
        var toHex = function(x){ var v = Math.round(x*255).toString(16).padStart(2,'0'); return v; };
        return '#' + toHex(r) + toHex(g) + toHex(b);
      }
      function hexToHsl(hex){
        try{
          var m = (hex||'').trim().replace('#','');
          if (m.length===3) m = m.split('').map(function(c){return c+c;}).join('');
          if (m.length!==6) return {h:210,s:100,l:50};
          var r=parseInt(m.substr(0,2),16)/255, g=parseInt(m.substr(2,2),16)/255, b=parseInt(m.substr(4,2),16)/255;
          var max=Math.max(r,g,b), min=Math.min(r,g,b); var h,s,l=(max+min)/2;
          if (max===min){ h=s=0; } else {
            var d=max-min; s=l>0.5? d/(2-max-min) : d/(max+min);
            switch(max){ case r: h=(g-b)/d + (g<b?6:0); break; case g: h=(b-r)/d + 2; break; case b: h=(r-g)/d + 4; break; }
            h/=6;
          }
          return {h:Math.round(h*360), s:Math.round(s*100), l:Math.round(l*100)};
        }catch(e){ return {h:210,s:100,l:50}; }
      }
      function normalizeHex(v){ v=(v||'').trim(); if(!v) return ''; if(v[0]!=='#') v='#'+v; if(v.length===4){ v='#'+v[1]+v[1]+v[2]+v[2]+v[3]+v[3]; } return v.toUpperCase(); }
      function buildSwatches(container, onPick){
        var colors=['#ef4444','#f97316','#f59e0b','#84cc16','#10b981','#06b6d4','#3b82f6','#6366f1','#8b5cf6','#ec4899','#f43f5e','#14b8a6','#22c55e','#a3e635','#fde047','#fbbf24','#fb923c','#fda4af','#f0abfc','#c084fc','#93c5fd','#60a5fa','#34d399','#2dd4bf'];
        container.innerHTML='';
        colors.forEach(function(c){ var d=document.createElement('div'); d.className='swatch'; d.style.background=c; d.addEventListener('click',function(){ onPick(c); }); container.appendChild(d); });
      }
      function openColorPopover(pop, initialHex){
        if (!pop) return; pop.style.display='block';
        var hex = normalizeHex(initialHex||'#3b82f6');
        var hsl = hexToHsl(hex);
        var hue=pop.querySelector('[id$="Hue"]'); var sat=pop.querySelector('[id$="Sat"]'); var light=pop.querySelector('[id$="Light"]'); var hexIn=pop.querySelector('[id$="Hex"]'); var prev=pop.querySelector('[id$="Preview"]');
        if (hue) hue.value=hsl.h; if (sat) sat.value=hsl.s; if (light) light.value=hsl.l; if (hexIn) hexIn.value=hex; if (prev) prev.style.background=hex;
      }
      function closePopover(pop){ if(pop) pop.style.display='none'; }
      function wirePopover(pop, onApply){
        if (!pop || pop._wired) return; pop._wired=true;
        var hue=pop.querySelector('[id$="Hue"]'); var sat=pop.querySelector('[id$="Sat"]'); var light=pop.querySelector('[id$="Light"]'); var hexIn=pop.querySelector('[id$="Hex"]'); var prev=pop.querySelector('[id$="Preview"]');
        function refresh(){ var hex=hslToHex(hue.value, sat.value, light.value); if(prev) prev.style.background=hex; if(hexIn) hexIn.value=hex; }
        ;['input','change'].forEach(function(ev){ if(hue) hue.addEventListener(ev, refresh); if(sat) sat.addEventListener(ev, refresh); if(light) light.addEventListener(ev, refresh); });
        if (hexIn) hexIn.addEventListener('input', function(){ var hsl=hexToHsl(hexIn.value); if(hue) hue.value=hsl.h; if(sat) sat.value=hsl.s; if(light) light.value=hsl.l; refresh(); });
        var closeBtn=pop.querySelector('[data-role="close"]'); var applyBtn=pop.querySelector('[data-role="apply"]');
        if (closeBtn) closeBtn.addEventListener('click', function(){ closePopover(pop); });
        if (applyBtn) applyBtn.addEventListener('click', function(){ var hex=(hexIn?normalizeHex(hexIn.value):''); if(hex) onApply(hex); closePopover(pop); });
      }
      function applySupplierColorByName(targetName, color){
        if (!targetName || !color) return;
        supplierColorOverrides = supplierColorOverrides || {}; supplierColorOverrides[targetName] = color;
        (suppliersCache||[]).forEach(function(s){ if(s && s.name===targetName){ s.color=color; s.pin_color=color; } });
        (currentMarkers||[]).forEach(function(m){ try{ if(m && m.supplierData && m.supplierData.name===targetName){ m.setIcon(createColoredIcon(color)); } }catch(e){} });
        fetch('../../api/set_supplier_color.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'name='+encodeURIComponent(targetName)+'&color='+encodeURIComponent(color), credentials:'same-origin' }).catch(function(err){ console.error('set_supplier_color error', err); });
      }

      /* =========================
         Add Supplier Modal Logics
         ========================= */
      var addSupplierModal = document.getElementById('addSupplierModal');
      var addSupplierForm = document.getElementById('addSupplierForm');
      var cancelSupplierBtn = document.getElementById('cancelSupplierBtn');
      var addSupplierOpenBtn = document.getElementById('addSupplierBtn');
      // Add Supplier color picker elements
      var addSupplierColorBtn = document.getElementById('addSupplierColorBtn');
      var addSupplierColorSwatch = document.getElementById('addSupplierColorSwatch');
      var addSupplierColorInput = document.getElementById('addSupplierColorInput');
      var addSupplierColorHidden = document.getElementById('addSupplierColor');
      var addSupplierNameInput = document.getElementById('addSupplierName');

      // Open modal and set service label
      if (addSupplierOpenBtn && addSupplierModal) {
        addSupplierOpenBtn.addEventListener('click', function() {
          // Show modal
          addSupplierModal.style.display = 'flex';
          // Update the Service label to reflect the currently selected service
          try {
            var lbl = document.getElementById('addSupplierServiceName');
            if (lbl) lbl.textContent = currentService || 'None selected';
            // Reset the Add Supplier form and linked client state so each open is clean
            try {
              if (addSupplierForm) {
                addSupplierForm.reset();
                var linked = document.getElementById('addLinkedClientId'); if (linked) linked.value = '';
                var badge = document.getElementById('addLinkedBadge'); if (badge) badge.style.display = 'none';
                var badgeName = document.getElementById('addLinkedClientName'); if (badgeName) badgeName.textContent = '';
                var coordsInput = document.getElementById('addSupplierCoordinates'); if (coordsInput) coordsInput.value = '';
                var latHidden = document.getElementById('addSupplierLatitude'); var lngHidden = document.getElementById('addSupplierLongitude'); if (latHidden) latHidden.value = ''; if (lngHidden) lngHidden.value = '';
                // clear any autocomplete dropdowns
                var drops = addSupplierForm.querySelectorAll('.autocomplete-dropdown');
                drops.forEach(function(d){ d.innerHTML = ''; d.style.display = 'none'; });
              }
            } catch(ignore) {}
            // Also ensure map resizes to avoid clipping
            setTimeout(function(){ if (typeof map !== 'undefined' && map && map.invalidateSize) map.invalidateSize(); }, 60);
          } catch (e) { /* ignore */ }
          // Initialize color swatch/default for the typed name
          try {
            var nm = (addSupplierNameInput && addSupplierNameInput.value) ? addSupplierNameInput.value.trim() : '';
            var known = null;
            (suppliersCache || []).some(function(s){ if (s && s.name === nm && s.color) { known = s.color; return true; } return false; });
            var col = known || getColorForSupplier(nm || '');
            if (addSupplierColorSwatch) addSupplierColorSwatch.style.background = col;
            if (addSupplierColorHidden) addSupplierColorHidden.value = known || '';
          } catch(e){}
        });
      }

      // Close modal on cancel
      if (cancelSupplierBtn && addSupplierModal) {
        cancelSupplierBtn.addEventListener('click', function() {
          addSupplierModal.style.display = 'none';
          try { document.getElementById('addLinkedClientId').value = ''; document.getElementById('addLinkedBadge').style.display='none'; } catch(e){}
        });
      }

      // Close modal when clicking backdrop
      if (addSupplierModal) {
        addSupplierModal.addEventListener('click', function(e) {
          if (e.target === addSupplierModal) {
            addSupplierModal.style.display = 'none';
                try { document.getElementById('addLinkedClientId').value = ''; document.getElementById('addLinkedBadge').style.display='none'; } catch(e){}
          }
        });
      }

      // Clear linked client when clicking the badge clear button
      try {
        var clearAddLinkedBtn = document.getElementById('clearAddLinked');
        if (clearAddLinkedBtn) {
          clearAddLinkedBtn.addEventListener('click', function(){
            try { document.getElementById('addLinkedClientId').value = ''; } catch(e){}
            try { document.getElementById('addLinkedBadge').style.display = 'none'; } catch(e){}
          });
        }
      } catch(e) {}

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
        // Wire modern color picker + defaulting based on name
        try {
          var addPopover = document.getElementById('addColorPopover');
          if (addPopover) { wirePopover(addPopover, function(hex){
            try { if (addSupplierColorSwatch) addSupplierColorSwatch.style.background = hex; } catch(e){}
            try { if (addSupplierColorHidden) addSupplierColorHidden.value = hex; } catch(e){}
            var targetName = (addSupplierNameInput && addSupplierNameInput.value) ? addSupplierNameInput.value.trim() : '';
            if (targetName) applySupplierColorByName(targetName, hex);
          });
            var sw = document.getElementById('addPickerSwatches'); if (sw) buildSwatches(sw, function(c){
              try { if (addSupplierColorSwatch) addSupplierColorSwatch.style.background = c; } catch(e){}
              try { if (addSupplierColorHidden) addSupplierColorHidden.value = c; } catch(e){}
              var nm = (addSupplierNameInput && addSupplierNameInput.value) ? addSupplierNameInput.value.trim() : '';
              if (nm) applySupplierColorByName(nm, c);
              closePopover(addPopover);
            });
          }
          var openAddPopover = function(e){ if(e) e.preventDefault();
              var currentHex = (addSupplierColorHidden && addSupplierColorHidden.value) ? addSupplierColorHidden.value : (addSupplierColorSwatch ? window.getComputedStyle(addSupplierColorSwatch).backgroundColor : '#3b82f6');
              // Convert rgb(..) to hex if needed
              if ((currentHex||'').indexOf('rgb')===0) {
                try { var m=currentHex.match(/\d+/g); var r=parseInt(m[0]),g=parseInt(m[1]),b=parseInt(m[2]); currentHex='#'+[r,g,b].map(x=>x.toString(16).padStart(2,'0')).join(''); } catch(_) { currentHex='#3b82f6'; }
              }
              openColorPopover(addPopover, currentHex);
          };
          if (addSupplierColorBtn) addSupplierColorBtn.addEventListener('click', openAddPopover);
          if (addSupplierColorSwatch) addSupplierColorSwatch.addEventListener('click', openAddPopover);
          if (addSupplierNameInput) {
            addSupplierNameInput.addEventListener('input', function(){
              var nm = (this.value || '').trim();
              var known = null; (suppliersCache || []).some(function(s){ if (s && s.name === nm && (s.color || s.pin_color)) { known = (s.color || s.pin_color); return true; } return false; });
              var col = known || getColorForSupplier(nm || '');
              try { if (addSupplierColorSwatch) addSupplierColorSwatch.style.background = col; } catch(e){}
              try { if (addSupplierColorHidden) addSupplierColorHidden.value = known || ''; } catch(e){}
            });
          }
        } catch(e) { console.warn('Add Supplier color picker init failed', e); }

      // Fill Add Supplier fields from an existing supplier (by name)
      function fillAddSupplierFromSupplierName(name) {
        var target = (name || '').trim().toLowerCase();
        if (!target) return;
        var form = addSupplierForm;
        if (!form) return;

        function applySupplierData(s) {
          if (!s) return;
          var setVal = function(sel, val){ var el = form.querySelector(sel); if (el) el.value = val || ''; };
          setVal('[name="location_name"]', s.location_name);
          setVal('[name="location_type"]', s.location_type);
          setVal('[name="supply_method"]', s.supply_method);
          setVal('[name="location_phone"]', s.location_phone);
        }

        // Prefer cached suppliers for current service
        var found = null;
        try {
          (suppliersCache || []).some(function(s){
            if (s && s.name && s.name.toString().trim().toLowerCase() === target) { found = s; return true; }
            return false;
          });
        } catch(e) {}

        if (found) { applySupplierData(found); return; }

        // Fallback: fetch suppliers for current service and match by name
        try {
          var svc = (typeof currentService !== 'undefined' && currentService) ? String(currentService) : '';
          if (!svc) return;
          fetch('../../api/get_suppliers.php?service=' + encodeURIComponent(svc), { method:'GET', credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(data){
              if (!data || !data.suppliers) return;
              var match = null;
              data.suppliers.some(function(s){ if (s && s.name && s.name.toString().trim().toLowerCase() === target) { match = s; return true; } return false; });
              if (match) applySupplierData(match);
            })
            .catch(function(){ /* ignore */ });
        } catch(e) {}
      }

      // Client lookup helper (used by sales contact autocomplete if enabled)
      var clientsByServiceCache = {};
      function fetchClientsForService(q, cb) {
        var svc = (typeof currentService !== 'undefined' && currentService) ? String(currentService) : '';
        var key = svc + '||' + q;
        if (clientsByServiceCache[key]) { cb(clientsByServiceCache[key]); return; }
        var url = '../../api/get_clients_by_type.php?type=' + encodeURIComponent(svc) + '&q=' + encodeURIComponent(q || '');
        fetch(url, { method: 'GET', credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(data){ if (data && data.clients) { clientsByServiceCache[key] = data.clients; cb(data.clients); } else cb([]); })
          .catch(function(){ cb([]); });
      }

      var clientsAllCache = {};
      function fetchAllClients(q, cb) {
        var key = (q || '').toLowerCase();
        if (clientsAllCache[key]) { cb(clientsAllCache[key]); return; }
        var url = '../../api/get_clients_by_type.php?type=&q=' + encodeURIComponent(q || '');
        fetch(url, { method: 'GET', credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(data){ if (data && data.clients) { clientsAllCache[key] = data.clients; cb(data.clients); } else cb([]); })
          .catch(function(){ cb([]); });
      }

      // Sales Contact autocomplete: suggest client contacts and fill remaining fields on select
      try {
        var salesContactInput = addSupplierForm ? addSupplierForm.querySelector('[name="sales_contact"]') : null;
        if (salesContactInput) {
          function renderSalesContactSuggestions(dropdown, clients) {
            dropdown.innerHTML = '';
            dropdown.style.display = 'none';
            if (!clients || clients.length === 0) return;
            clients.forEach(function(c){
              var display = c.client_name || '';
              if (!display) return;
              var item = document.createElement('div');
              item.className = 'suggestion-item client-suggestion';
              item.dataset.clientId = c.client_id;
              item.textContent = display + (c.current_employer ? (' — ' + c.current_employer) : '');
              item.addEventListener('click', function(){
                try { salesContactInput.value = display; } catch(e){}
                try { dropdown.style.display = 'none'; } catch(e){}
                // set linked client id and show badge
                try { document.getElementById('addLinkedClientId').value = c.client_id; } catch(e){}
                try { var badge = document.getElementById('addLinkedBadge'); var badgeName = document.getElementById('addLinkedClientName'); if (badge && badgeName) { badgeName.textContent = display; badge.style.display='inline-block'; } } catch(e){}
                // Fill remaining form fields from client details
                try {
                  var form = addSupplierForm;
                  if (form) {
                    if (form.querySelector('[name="contact_number"]')) form.querySelector('[name="contact_number"]').value = c.contact_phone || '';
                    if (form.querySelector('[name="email"]')) form.querySelector('[name="email"]').value = c.client_email || '';
                    if (form.querySelector('[name="address"]')) form.querySelector('[name="address"]').value = c.client_address || '';
                    if (form.querySelector('[name="city"]')) form.querySelector('[name="city"]').value = c.city || '';
                    if (form.querySelector('[name="state"]')) form.querySelector('[name="state"]').value = c.state || '';
                  }
                } catch(e){ console.error('prefill sales contact failed', e); }
              });
              dropdown.appendChild(item);
            });
            dropdown.style.display = 'block';
          }

          // Show all clients on focus, then filter as user types
          salesContactInput.addEventListener('focus', function(){
            var dropdown = this.nextElementSibling; // autocomplete-dropdown
            if (!dropdown) return;
            fetchAllClients('', function(clients){
              renderSalesContactSuggestions(dropdown, clients);
            });
          });

          salesContactInput.addEventListener('input', function(ev){
            var q = (this.value || '').trim();
            var dropdown = this.nextElementSibling; // autocomplete-dropdown
            if (!dropdown) return;
            // typing a new contact clears any previously linked client
            try { document.getElementById('addLinkedClientId').value = ''; } catch(e){}
            try { document.getElementById('addLinkedBadge').style.display = 'none'; } catch(e){}
            fetchAllClients(q, function(clients){
              renderSalesContactSuggestions(dropdown, clients);
            });
          });
        }
      } catch(e) { console.warn('Sales contact autocomplete init failed', e); }
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
          // include selected color if present
          try { var cval = addSupplierColorHidden ? (addSupplierColorHidden.value || '').trim() : ''; if (cval) formData.set('color', cval); } catch(e){}

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
              try { document.getElementById('addLinkedClientId').value = ''; document.getElementById('addLinkedBadge').style.display='none'; } catch(e){}
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
                    location_name: form.querySelector('[name="location_name"]').value || '',
                    material: form.querySelector('[name="material"]').value || '',
                    sales_contact: form.querySelector('[name="sales_contact"]').value || '',
                    contact_number: form.querySelector('[name="contact_number"]').value || '',
                    supply_method: form.querySelector('[name="supply_method"]').value || '',
                    location_phone: form.querySelector('[name="location_phone"]').value || '',
                    email: form.querySelector('[name="email"]').value || '',
                    address: form.querySelector('[name="address"]').value || '',
                    city: form.querySelector('[name="city"]').value || '',
                    state: form.querySelector('[name="state"]').value || '',
                    location_type: form.querySelector('[name="location_type"]').value || '',
                    notes: form.querySelector('[name="notes"]').value || '',
                    latitude: form.querySelector('[name="latitude"]').value || '',
                    longitude: form.querySelector('[name="longitude"]').value || '',
                    service: currentService,
                    color: (addSupplierColorHidden ? (addSupplierColorHidden.value || '') : '')
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
                    (supplierObj.contact_number?('<div><strong>Sales Phone:</strong> ' + supplierObj.contact_number + '</div>'):'') +
                    (supplierObj.location_phone?('<div><strong>Location Phone:</strong> ' + supplierObj.location_phone + '</div>'):'') +
                    (supplierObj.supply_method?('<div><strong>Supply Method:</strong> ' + supplierObj.supply_method + '</div>'):'') +
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
                // If sales contact is new (not selected from list), create a new client record
                try {
                  var linkedClientId = document.getElementById('addLinkedClientId') ? (document.getElementById('addLinkedClientId').value || '') : '';
                  var salesName = (form.querySelector('[name="sales_contact"]')?.value || '').trim();
                  if (!linkedClientId && salesName) {
                    var clientForm = new FormData();
                    clientForm.append('client_name', salesName);
                    clientForm.append('contact_phone', form.querySelector('[name="contact_number"]')?.value || '');
                    clientForm.append('client_email', form.querySelector('[name="email"]')?.value || '');
                    clientForm.append('client_address', form.querySelector('[name="address"]')?.value || '');
                    clientForm.append('city', form.querySelector('[name="city"]')?.value || '');
                    clientForm.append('state', form.querySelector('[name="state"]')?.value || '');
                    clientForm.append('current_employer', form.querySelector('[name="name"]')?.value || '');
                    if (currentService) clientForm.append('client_type', currentService);
                    fetch('../../api/add_client.php', { method: 'POST', body: clientForm, credentials: 'same-origin' })
                      .then(function(r){ return r.json(); })
                      .then(function(res){ if (!res || !res.success) console.warn('add_client failed', res); })
                      .catch(function(e){ console.error('add_client error', e); });
                  }
                } catch (e) { console.error('Add client post-save failed', e); }
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
      // Close color popovers on outside click or ESC
      (function(){
        try{
          document.addEventListener('click', function(e){
            var addPop=document.getElementById('addColorPopover'); var editPop=document.getElementById('editColorPopover');
            var addBtn=document.getElementById('addSupplierColorBtn'); var editBtn=document.getElementById('editSupplierColorBtn');
            var clickInAddButton = addBtn && addBtn.contains(e.target);
            var clickInEditButton = editBtn && editBtn.contains(e.target);
            if (addPop && addPop.style.display==='block' && !addPop.contains(e.target) && !clickInAddButton) closePopover(addPop);
            if (editPop && editPop.style.display==='block' && !editPop.contains(e.target) && !clickInEditButton) closePopover(editPop);
          });
          document.addEventListener('keydown', function(e){ if (e.key==='Escape'){ closePopover(document.getElementById('addColorPopover')); closePopover(document.getElementById('editColorPopover')); }});
        }catch(_){}
      })();
      
      // Edit supplier modal elements
      var editSupplierModal = document.getElementById('editSupplierModal');
      var editSupplierForm = document.getElementById('editSupplierForm');
      var editSupplierColorBtn = document.getElementById('editSupplierColorBtn');
      var editSupplierColorSwatch = document.getElementById('editSupplierColorSwatch');
      var editSupplierColorInput = document.getElementById('editSupplierColorInput');
      var editSupplierColorHidden = document.getElementById('editSupplierColor');
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
        document.getElementById('editSupplierSupplyMethod').value = supplier.supply_method || '';
        document.getElementById('editSupplierLocationPhone').value = supplier.location_phone || '';
        document.getElementById('editSupplierSalesContact').value = supplier.sales_contact || '';
        document.getElementById('editSupplierContactNumber').value = supplier.contact_number || '';
        document.getElementById('editSupplierEmail').value = supplier.email || '';
        document.getElementById('editSupplierAddress').value = supplier.address || '';
        // Populate coordinates (combined input and hidden fields)
        var combined = '';
        if (supplier.latitude && supplier.longitude) {
          combined = supplier.latitude + ', ' + supplier.longitude;
        }
        var editCoordsEl = document.getElementById('editSupplierCoordinates');
        var editLatEl = document.getElementById('editSupplierLatitude');
        var editLngEl = document.getElementById('editSupplierLongitude');
        if (editCoordsEl) editCoordsEl.value = combined;
        if (editLatEl) editLatEl.value = supplier.latitude || '';
        if (editLngEl) editLngEl.value = supplier.longitude || '';
        document.getElementById('editSupplierCity').value = supplier.city || '';
        document.getElementById('editSupplierState').value = supplier.state || '';
        document.getElementById('editSupplierService').value = supplier.service || '';
        document.getElementById('editSupplierNotes').value = supplier.notes || '';
        // populate color swatch/hidden input (use supplier.color if available, otherwise leave empty)
        try {
          var col = supplier.color || supplier.pin_color || '';
          if (col) {
            if (editSupplierColorSwatch) editSupplierColorSwatch.style.background = col;
            if (editSupplierColorInput) editSupplierColorInput.value = col;
            if (editSupplierColorHidden) editSupplierColorHidden.value = col;
          } else {
            // derive default from name hashing but do not persist until user chooses
            if (editSupplierColorSwatch) editSupplierColorSwatch.style.background = getColorForSupplier(supplier.name || '');
            if (editSupplierColorInput) editSupplierColorInput.value = '';
            if (editSupplierColorHidden) editSupplierColorHidden.value = '';
          }
        } catch(e) {}
        
        // Show modal
        editSupplierModal.style.display = 'flex';
      };

      // Color pick behavior: open modern popover; native input remains as fallback
      try {
        if (true) {
          var editPopover = document.getElementById('editColorPopover');
          if (editPopover) {
            wirePopover(editPopover, function(hex){
              try { if (editSupplierColorSwatch) editSupplierColorSwatch.style.background = hex; } catch(e){}
              try { if (editSupplierColorHidden) editSupplierColorHidden.value = hex; } catch(e){}
              var targetName = (currentEditSupplier && currentEditSupplier.name) ? currentEditSupplier.name : (document.getElementById('editSupplierName') ? document.getElementById('editSupplierName').value : '');
              if (targetName) applySupplierColorByName(targetName, hex);
            });
            var sw2 = document.getElementById('editPickerSwatches'); if (sw2) buildSwatches(sw2, function(c){
              try { if (editSupplierColorSwatch) editSupplierColorSwatch.style.background = c; } catch(e){}
              try { if (editSupplierColorHidden) editSupplierColorHidden.value = c; } catch(e){}
              var nm = (currentEditSupplier && currentEditSupplier.name) ? currentEditSupplier.name : (document.getElementById('editSupplierName') ? document.getElementById('editSupplierName').value : '');
              if (nm) applySupplierColorByName(nm, c);
              closePopover(editPopover);
            });
          }
          var openEditPopover = function(e){ if(e) e.preventDefault();
              var currentHex = (editSupplierColorHidden && editSupplierColorHidden.value) ? editSupplierColorHidden.value : (editSupplierColorSwatch ? window.getComputedStyle(editSupplierColorSwatch).backgroundColor : '#3b82f6');
              if ((currentHex||'').indexOf('rgb')===0) {
                try { var m=currentHex.match(/\d+/g); var r=parseInt(m[0]),g=parseInt(m[1]),b=parseInt(m[2]); currentHex='#'+[r,g,b].map(x=>x.toString(16).padStart(2,'0')).join(''); } catch(_) { currentHex='#3b82f6'; }
              }
              openColorPopover(editPopover, currentHex);
          };
          if (editSupplierColorBtn) editSupplierColorBtn.addEventListener('click', openEditPopover);
          if (editSupplierColorSwatch) editSupplierColorSwatch.addEventListener('click', openEditPopover);

          editSupplierColorInput.addEventListener('input', function(e){
            var color = (this.value || '').trim();
            if (!color) return;
            // update swatch and hidden field
            try { if (editSupplierColorSwatch) editSupplierColorSwatch.style.background = color; } catch(e){}
            try { if (editSupplierColorHidden) editSupplierColorHidden.value = color; } catch(e){}

            // If we have a current supplier in edit, update all suppliers with same name
            try {
              var targetName = (currentEditSupplier && currentEditSupplier.name) ? currentEditSupplier.name : (document.getElementById('editSupplierName') ? document.getElementById('editSupplierName').value : '');
              if (!targetName) return;
              applySupplierColorByName(targetName, color);
            } catch(e) { console.error(e); }
          });
        }
      } catch(e) { console.warn('color picker init failed', e); }
      
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
        // Capture-phase listener: parse combined coords into hidden fields before other listeners
        editSupplierForm.addEventListener('submit', function(e) {
          var coordsInput = document.getElementById('editSupplierCoordinates');
          var latHidden = document.getElementById('editSupplierLatitude');
          var lngHidden = document.getElementById('editSupplierLongitude');
          var err = document.getElementById('editSupplierCoordError');
          if (coordsInput && latHidden && lngHidden) {
            var val = (coordsInput.value || '').trim();
            if (err) { err.textContent = ''; err.style.display = 'none'; }
            if (val) {
              var parsed = null;
              try { parsed = parseCoords(val); } catch (ex) { parsed = null; }
              if (!parsed || isNaN(Number(parsed.lat)) || isNaN(Number(parsed.lng))) {
                var m = (val || '').replace(/\u00A0/g, ' ').match(/-?\d+(?:\.\d+)?/g);
                if (m && m.length >= 2) {
                  parsed = { lat: parseFloat(m[0]), lng: parseFloat(m[1]) };
                }
              }
              if (parsed && !isNaN(Number(parsed.lat)) && !isNaN(Number(parsed.lng))) {
                latHidden.value = String(parsed.lat);
                lngHidden.value = String(parsed.lng);
              } else {
                if (err) { err.textContent = 'Invalid coordinates — enter as "lat, lng"'; err.style.display = 'block'; }
                e.preventDefault();
                return;
              }
            }
          }
        }, true); // capture

        editSupplierForm.addEventListener('submit', function(e) {
          e.preventDefault();

          var formData = new FormData(editSupplierForm);
          // Ensure latitude/longitude are present in FormData (fallback parse)
          try {
            var latHiddenEl = editSupplierForm.querySelector('[name="latitude"]');
            var lngHiddenEl = editSupplierForm.querySelector('[name="longitude"]');
            var coordsInputEl = document.getElementById('editSupplierCoordinates');
            var coordErrEl = document.getElementById('editSupplierCoordError');
            if (coordErrEl) { coordErrEl.textContent = ''; coordErrEl.style.display = 'none'; }
            var latValNow = latHiddenEl ? (latHiddenEl.value || '').trim() : '';
            var lngValNow = lngHiddenEl ? (lngHiddenEl.value || '').trim() : '';
            if ((!latValNow || !lngValNow) && coordsInputEl) {
              var parsedFallback = null;
              try { parsedFallback = parseCoords((coordsInputEl.value || '').trim()); } catch (ex) { parsedFallback = null; }
              if ((!parsedFallback || isNaN(Number(parsedFallback.lat)) || isNaN(Number(parsedFallback.lng))) && coordsInputEl) {
                var raw = (coordsInputEl.value || '').replace(/\u00A0/g, ' ');
                var m = raw.match(/-?\d+(?:\.\d+)?/g);
                if (m && m.length >= 2) parsedFallback = { lat: parseFloat(m[0]), lng: parseFloat(m[1]) };
              }
              if (parsedFallback && !isNaN(Number(parsedFallback.lat)) && !isNaN(Number(parsedFallback.lng))) {
                if (latHiddenEl) latHiddenEl.value = String(parsedFallback.lat);
                if (lngHiddenEl) lngHiddenEl.value = String(parsedFallback.lng);
                formData.set('latitude', String(parsedFallback.lat));
                formData.set('longitude', String(parsedFallback.lng));
              }
            }
          } catch (e) { console.error('Coordinate fallback parse error', e); }
          
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
      
      // Load field values from API or cache. Scoped to current service when available.
      function loadFieldValues(field, input, dropdown) {
        var service = (typeof currentService !== 'undefined' && currentService) ? String(currentService) : '';
        var cacheKey = field + '||' + service;
        if (autocompleteCache[cacheKey]) {
          // Use cached values for this field+service
          populateAutocomplete(input, dropdown, autocompleteCache[cacheKey]);
          return;
        }

        // Fetch from API, include service filter so backend can scope suggestions
        var url = '../../api/get_supplier_field_values.php?field=' + encodeURIComponent(field);
        if (service) url += '&service=' + encodeURIComponent(service);

        fetch(url, {
          method: 'GET',
          credentials: 'same-origin'
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
          if (data.success && data.values) {
            autocompleteCache[cacheKey] = data.values;
            populateAutocomplete(input, dropdown, data.values);
          }
        })
        .catch(function(error) {
          console.error('Error loading field values:', error);
        });
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
            if (input.id === 'addSupplierName') {
              fillAddSupplierFromSupplierName(value);
            }
          });
          
          dropdown.appendChild(item);
        });
        
        dropdown.style.display = 'block';
        input.style.borderRadius = '6px 6px 0 0';
      }
      
      // Filter autocomplete based on input (respect current service cache)
      function filterAutocomplete(input, dropdown, field) {
        var service = (typeof currentService !== 'undefined' && currentService) ? String(currentService) : '';
        var cacheKey = field + '||' + service;
        if (autocompleteCache[cacheKey]) {
          populateAutocomplete(input, dropdown, autocompleteCache[cacheKey]);
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
          state: (document.getElementById('filterState')?.value || '').trim().toLowerCase(),
          legendName: (activeLegendName || '').trim().toLowerCase()
        };
      }

      function markerMatchesFilters(supplier, filters) {
        if (!supplier) return false;
        if (filters.legendName && (!supplier.name || supplier.name.toLowerCase() !== filters.legendName)) return false;
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
          activeLegendName = '';
          updateSupplierLegend(legendSuppliersCache);
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
