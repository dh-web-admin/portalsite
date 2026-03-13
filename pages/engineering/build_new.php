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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
  <meta name="theme-color" content="#667eea" />
  <title>Build New Equipment</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/base.css" />
  <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <style>
    /* ── Page-level font override ── */
    .content-area, .content-area * {
      font-family: 'DM Sans', sans-serif;
    }

    /* ── Page Header Card ── */
    #equipmentHeader {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 20px 26px;
      box-shadow: 0 1px 3px rgba(15,23,42,0.06), 0 4px 16px rgba(15,23,42,0.04);
      display: grid;
      gap: 0;
    }

    /* ── Top row: title + buttons ── */
    #equipmentTitleRow {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    #equipmentTitleRow h1 {
      margin: 0;
      font-size: 1.45rem;
      font-weight: 700;
      color: #0f172a;
      letter-spacing: -0.3px;
    }

    /* ── Breadcrumb eyebrow ── */
    #equipmentEyebrow {
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #94a3b8;
      margin-bottom: 6px;
    }

    /* ── Button group ── */
    .header-btn-group {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* ── Back button ── */
    #backToEngineeringBtn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 9px 16px;
      background: #ffffff;
      color: #475569;
      border: 1.5px solid #cbd5e1;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 700;
      font-size: 0.86rem;
      letter-spacing: 0.01em;
      cursor: pointer;
      transition: background 0.18s, color 0.18s, transform 0.12s;
      white-space: nowrap;
    }
    #backToEngineeringBtn:hover {
      background: #f8fafc;
      color: #334155;
      transform: translateY(-1px);
    }
    #backToEngineeringBtn:active { transform: translateY(0); }

    /* ── Save Draft button ── */
    #saveEngineeringItemsBtn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 9px 20px;
      background: #1e40af;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 700;
      font-size: 0.88rem;
      letter-spacing: 0.01em;
      cursor: pointer;
      transition: background 0.18s, box-shadow 0.18s, transform 0.12s;
      box-shadow: 0 1px 3px rgba(30,64,175,0.18);
      white-space: nowrap;
    }
    #saveEngineeringItemsBtn:hover {
      background: #1d3fa8;
      transform: translateY(-1px);
    }
    #saveEngineeringItemsBtn:active { transform: translateY(0); }

    /* ── Deploy button ── */
    #deployEquipmentBtn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 9px 20px;
      background: #ffffff;
      color: #16a34a;
      border: 1.5px solid #16a34a;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 700;
      font-size: 0.88rem;
      letter-spacing: 0.01em;
      cursor: pointer;
      transition: background 0.18s, color 0.18s, transform 0.12s;
      white-space: nowrap;
    }
    #deployEquipmentBtn:hover {
      background: #f0fdf4;
      transform: translateY(-1px);
    }
    #deployEquipmentBtn:active { transform: translateY(0); }

    /* ── Equipment identity strip ── */
    #equipmentInfoDisplay {
      display: none;
      margin-top: 16px;
      padding-top: 16px;
      border-top: 1px solid #f1f5f9;
    }
    #equipmentInfoDisplay.visible {
      display: flex;
      align-items: center;
    }

    /* Single inline identity block */
    #equipmentIdentityBar {
      display: flex;
      align-items: center;
      gap: 0;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      overflow: hidden;
      font-size: 14px;
    }

    .equip-id-segment {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 18px;
    }
    .equip-id-segment + .equip-id-segment {
      border-left: 1px solid #e2e8f0;
    }

    .equip-id-label {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #94a3b8;
      white-space: nowrap;
    }
    .equip-id-value {
      font-weight: 700;
      color: #0f172a;
      font-size: 14px;
      white-space: nowrap;
    }
    /* Equipment # uses mono */
    #displayEquipmentNumber {
      font-family: 'DM Mono', monospace;
      font-size: 13px;
      font-weight: 600;
      color: #1e40af;
    }
    /* Type */
    #displayEquipmentType {
      font-size: 13px;
      font-weight: 600;
      color: #374151;
    }

    /* ── Divider ── */
    .header-divider {
      border: none;
      border-top: 1px solid #e9edf2;
      margin: 22px 0 26px;
      width: 100%;
    }

    /* ── Deploy confirm modal ── */
    #deployConfirmModal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15,23,42,0.5);
      z-index: 9000;
      align-items: center;
      justify-content: center;
    }
    #deployConfirmModal.open {
      display: flex;
    }
    #deployConfirmBox {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 20px 60px rgba(15,23,42,0.2);
      padding: 32px 32px 26px;
      width: min(440px, 92vw);
      display: grid;
      gap: 20px;
    }
    #deployConfirmBox h3 {
      margin: 0;
      font-size: 1.15rem;
      font-weight: 700;
      color: #0f172a;
    }
    #deployConfirmBox p {
      margin: 0;
      font-size: 14px;
      color: #475569;
      line-height: 1.6;
    }
    .deploy-confirm-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    .deploy-btn-no {
      padding: 9px 22px;
      background: #fff;
      border: 1.5px solid #cbd5e1;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 700;
      font-size: 0.88rem;
      color: #334155;
      cursor: pointer;
      transition: background 0.15s;
    }
    .deploy-btn-no:hover { background: #f8fafc; }
    .deploy-btn-yes {
      padding: 9px 22px;
      background: #16a34a;
      border: none;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 700;
      font-size: 0.88rem;
      color: #fff;
      cursor: pointer;
      transition: background 0.15s;
    }
    .deploy-btn-yes:hover { background: #15803d; }

    /* ── Deploy success modal ── */
    #deploySuccessModal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15,23,42,0.5);
      z-index: 9050;
      align-items: center;
      justify-content: center;
    }
    #deploySuccessModal.open {
      display: flex;
    }
    #deploySuccessBox {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 20px 60px rgba(15,23,42,0.2);
      padding: 30px 30px 24px;
      width: min(460px, 92vw);
      display: grid;
      gap: 16px;
    }
    #deploySuccessBox h3 {
      margin: 0;
      font-size: 1.15rem;
      font-weight: 700;
      color: #166534;
    }
    #deploySuccessMessage {
      margin: 0;
      font-size: 14px;
      color: #334155;
      line-height: 1.6;
      white-space: pre-line;
    }
    .deploy-success-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    .deploy-btn-continue {
      padding: 9px 22px;
      background: #1d4ed8;
      border: none;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 700;
      font-size: 0.88rem;
      color: #fff;
      cursor: pointer;
      transition: background 0.15s;
    }
    .deploy-btn-continue:hover { background: #1e40af; }
  </style>
</head>
<body class="admin-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
      <main class="content-area">
        <div class="main-content">

          <!-- ══════════════════════════════════════════
               PAGE HEADER
               ══════════════════════════════════════════ -->
          <div id="equipmentHeader">
            <!-- Eyebrow / breadcrumb -->
            <div id="equipmentEyebrow">Equipment &rsaquo; New Build</div>

            <!-- Title row -->
            <div id="equipmentTitleRow">
              <h1>Build New Equipment</h1>
              <div class="header-btn-group">
                <button id="backToEngineeringBtn" type="button">
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M10.5 3L5.5 8L10.5 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  Back to Engineering
                </button>
                <button id="saveEngineeringItemsBtn">
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M13.5 4.5L6 12L2.5 8.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  Save Draft
                </button>
                <button id="deployEquipmentBtn">
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M8 2L8 11M8 2L5 5M8 2L11 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M3 13h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  </svg>
                  Deploy
                </button>
              </div>
            </div>

            <!-- Equipment identity bar (shown once data loads) -->
            <div id="equipmentInfoDisplay">
              <div id="equipmentIdentityBar">
                <!-- EQ # -->
                <div class="equip-id-segment">
                  <span class="equip-id-label">EQ #</span>
                  <span id="displayEquipmentNumber">—</span>
                </div>
                <!-- Type -->
                <div class="equip-id-segment">
                  <span class="equip-id-label">Type</span>
                  <span id="displayEquipmentType">—</span>
                </div>
              </div>
            </div>
          </div>
          <!-- /PAGE HEADER -->

          <!-- Deploy confirm modal -->
          <div id="deployConfirmModal">
            <div id="deployConfirmBox">
              <div>
                <h3>Deploy Equipment?</h3>
              </div>
              <p>Are you sure you want to deploy this equipment? This action will make it live and available across the system.</p>
              <div class="deploy-confirm-actions">
                <button class="deploy-btn-no" id="deployConfirmNo">No, cancel</button>
                <button class="deploy-btn-yes" id="deployConfirmYes">Yes, deploy</button>
              </div>
            </div>
          </div>

          <div id="deploySuccessModal">
            <div id="deploySuccessBox">
              <div>
                <h3>Equipment Deployed Successfully</h3>
              </div>
              <p id="deploySuccessMessage"></p>
              <div class="deploy-success-actions">
                <button class="deploy-btn-continue" id="deploySuccessContinue">Continue to Equipment</button>
              </div>
            </div>
          </div>

          <hr class="header-divider" />

          <div style="display:flex;flex-direction:row;gap:32px;margin-bottom:24px;align-items:flex-start;">
            <div id="engItemPanel" style="background:#f5f7fa;border:1.5px solid #d1d5db;border-radius:12px;padding:24px 18px;min-width:210px;max-width:260px;width:240px;height:calc(100vh - 320px);box-sizing:border-box;box-shadow:2px 0 8px #e0e4ea;overflow-y:auto;overflow-x:hidden;display:flex;flex-direction:column;align-items:flex-start;gap:10px;flex-shrink:0;scrollbar-width:thin;scrollbar-color:#a2a9b3 #f5f7fa;">
              <div id="engItemList" style="display:flex;flex-direction:column;align-items:flex-start;gap:10px;width:100%;"></div>
            </div>
            <div id="engItemDetails" style="flex:1;padding-top:10px;min-width:0;"></div>
          </div>

          <div id="specificPartsModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:2200;align-items:center;justify-content:center;padding:24px;">
            <div style="background:#fff;border-radius:14px;box-shadow:0 24px 60px rgba(15,23,42,0.22);width:min(1100px,96vw);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
              <div style="display:flex;justify-content:space-between;align-items:center;padding:18px 22px;border-bottom:1px solid #e2e8f0;gap:16px;">
                <div>
                  <h2 style="margin:0;font-size:22px;color:#0f172a;">Select Specific Parts</h2>
                  <p style="margin:6px 0 0;color:#64748b;font-size:14px;">Choose engineering items, member assemblies, and specific parts.</p>
                </div>
                <button type="button" id="closeSpecificPartsModalBtn" style="background:transparent;border:none;font-size:28px;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">&times;</button>
              </div>

              <div style="padding:18px 22px;border-bottom:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
                <div id="specificPartsStatus" style="font-size:14px;color:#334155;font-weight:600;">Loading engineering items...</div>
                <div id="specificPartsSelectionSummary" style="font-size:13px;color:#64748b;">No selections yet</div>
              </div>

              <div id="specificPartsModalBody" style="padding:22px;overflow:auto;display:grid;gap:16px;background:#fff;"></div>

              <div style="display:flex;justify-content:flex-end;gap:10px;padding:18px 22px;border-top:1px solid #e2e8f0;background:#fff;">
                <button type="button" id="cancelSpecificPartsModalBtn" style="padding:10px 16px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;color:#334155;font-weight:700;cursor:pointer;">Cancel</button>
                <button type="button" id="applySpecificPartsBtn" style="padding:10px 16px;background:#5b7fa3;border:none;border-radius:8px;color:#fff;font-weight:700;cursor:pointer;">Apply Selection</button>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script>
    (function(){
      // Load equipment info from localStorage
      var equipmentData = null;
      try {
        var stored = localStorage.getItem('buildNewEquipment');
        if (!stored) {
          stored = localStorage.getItem('buildNewEquipmentCurrent');
        }
        if (stored) {
          equipmentData = JSON.parse(stored);
          var displayNumber = document.getElementById('displayEquipmentNumber');
          var displayType   = document.getElementById('displayEquipmentType');
          var infoDisplay   = document.getElementById('equipmentInfoDisplay');

          if (displayNumber) displayNumber.textContent = equipmentData.number || '—';
          if (displayType)   displayType.textContent   = equipmentData.type   || '—';
          if (infoDisplay)   infoDisplay.classList.add('visible');

          localStorage.setItem('buildNewEquipmentCurrent', JSON.stringify(equipmentData));
          localStorage.removeItem('buildNewEquipment');
        }
      } catch (e) {
        console.warn('Could not load equipment data from localStorage');
      }

      var apiBase = '../../api';
      var specificPartsState = {
        items: [],
        loaded: false,
        selections: {},
        expandedItemId: null
      };

      var usersToggle = document.getElementById('usersToggle');
      var usersGroup = document.getElementById('usersGroup');
      if (usersToggle && usersGroup) {
        usersToggle.addEventListener('click', function(){ usersGroup.classList.toggle('open'); });
      }

      var devToggle = document.getElementById('devToggle');
      var devGroup = document.getElementById('devGroup');
      if (devToggle && devGroup) {
        devToggle.addEventListener('click', function(){ devGroup.classList.toggle('open'); });
      }

      var maintenanceToggle = document.getElementById('maintenanceToggle');
      var maintenanceGroup = document.getElementById('maintenanceGroup');
      if (maintenanceToggle && maintenanceGroup) {
        maintenanceToggle.addEventListener('click', function(){ maintenanceGroup.classList.toggle('open'); });
      }

      var selectSpecificPartsBtn     = document.getElementById('selectSpecificPartsBtn');
      var specificPartsModal         = document.getElementById('specificPartsModal');
      var closeSpecificPartsModalBtn = document.getElementById('closeSpecificPartsModalBtn');
      var cancelSpecificPartsModalBtn= document.getElementById('cancelSpecificPartsModalBtn');
      var applySpecificPartsBtn      = document.getElementById('applySpecificPartsBtn');
      var specificPartsModalBody     = document.getElementById('specificPartsModalBody');
      var specificPartsStatus        = document.getElementById('specificPartsStatus');
      var specificPartsSelectionSummary = document.getElementById('specificPartsSelectionSummary');

      function escapeHtml(value) {
        return String(value == null ? '' : value)
          .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
      }

      function ensureItemSelection(itemId) {
        if (!specificPartsState.selections[itemId]) {
          specificPartsState.selections[itemId] = { itemSelected:false, mode:'', materialId:'', partKeys:{} };
        }
        return specificPartsState.selections[itemId];
      }

      function partSelectionKey(part) {
        if (part && part.id != null && String(part.id) !== '') {
          return 'material-part:' + String(part.id);
        }
        return [
          part && part.number ? part.number : '',
          part && part.name ? part.name : '',
          part && part.make ? part.make : ''
        ].join('|');
      }

      function getMostRecentId(items) {
        return items && items.length && items[0] && items[0].id ? String(items[0].id) : '';
      }

      function selectAllParts(item, selection) {
        selection.partKeys = {};
        (item || []).forEach(function(part) {
          var key = partSelectionKey(part);
          if (key) selection.partKeys[key] = true;
        });
      }

      function updateSpecificPartsSummary() {
        var itemCount=0, materialCount=0, partsCount=0;
        Object.keys(specificPartsState.selections).forEach(function(itemId) {
          var sel = specificPartsState.selections[itemId];
          if (!sel) return;
          if (sel.itemSelected)  itemCount += 1;
          if (sel.materialId)    materialCount += 1;
          Object.keys(sel.partKeys||{}).forEach(function(key){ if (sel.partKeys[key]) partsCount += 1; });
        });
        specificPartsSelectionSummary.textContent = itemCount+' item(s), '+materialCount+' assembly selection(s), '+partsCount+' part(s) selected';
      }

      function buildRadioList(name, items, selectedValue, formatter, emptyText) {
        if (!items || !items.length) {
          return '<div style="padding:10px 12px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;color:#64748b;font-size:13px;">'+escapeHtml(emptyText)+'</div>';
        }
        return items.map(function(item) {
          var idValue = String(item.id||'');
          var checked = selectedValue && String(selectedValue)===idValue ? ' checked' : '';
          var checkVisible = checked ? 'inline-flex' : 'none';
          return '<label style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;">'
            +'<input type="radio" name="'+escapeHtml(name)+'" value="'+escapeHtml(idValue)+'"'+checked+' />'
            +'<span style="display:block;line-height:1.4;color:#0f172a;font-size:14px;">'+formatter(item)+'</span>'
            +'<span style="margin-left:auto;display:'+checkVisible+';align-items:center;justify-content:center;color:#16a34a;font-weight:800;font-size:15px;">&#10003;</span>'
            +'</label>';
        }).join('');
      }

      function buildCheckboxList(itemId, parts, selectedMap) {
        if (!parts || !parts.length) {
          return '<div style="padding:10px 12px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;color:#64748b;font-size:13px;">No parts available for this member assembly.</div>';
        }
        return parts.map(function(part) {
          var key = partSelectionKey(part);
          var checked = selectedMap && selectedMap[key] ? ' checked' : '';
          var secondary = [];
          if (part.part_number)   secondary.push('Part No: '+part.part_number);
          if (part.make)          secondary.push('Make: '+part.make);
          if (part.material_type) secondary.push('Type: '+part.material_type);
          if (part.quantity)      secondary.push('Qty: '+part.quantity);
          return '<label style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;">'
            +'<input type="checkbox" data-item-id="'+escapeHtml(itemId)+'" data-part-key="'+escapeHtml(key)+'"'+checked+' />'
            +'<span style="display:block;line-height:1.4;">'
              +'<span style="display:block;color:#0f172a;font-size:14px;font-weight:600;">'+escapeHtml(part.name||part.part_name||'Unnamed part')+'</span>'
              +'<span style="display:block;color:#64748b;font-size:12px;">'+escapeHtml(secondary.join(' | ')||'No additional details')+'</span>'
            +'</span></label>';
        }).join('');
      }

      function renderSpecificPartsModal() {
        if (!specificPartsState.items.length) {
          specificPartsModalBody.innerHTML = '<div style="padding:16px;border:1px dashed #cbd5e1;border-radius:10px;color:#64748b;">No engineering items were found.</div>';
          specificPartsStatus.textContent = 'No engineering items available';
          updateSpecificPartsSummary();
          return;
        }
        specificPartsStatus.textContent = 'Select what to carry into the new equipment build';
        specificPartsModalBody.innerHTML = specificPartsState.items.map(function(item) {
          var sel = ensureItemSelection(item.id);
          var isExpanded = String(specificPartsState.expandedItemId||'') === String(item.id);
          var materialsMarkup = buildRadioList('member_assembly_'+item.id, item.materials, sel.materialId, function(m){ var l=[]; if(m.number) l.push(m.number); if(m.name) l.push(m.name); return escapeHtml(l.join(' - ')||'Unnamed member assembly'); }, 'No member assemblies available for this item.');
          var selectedMaterialParts = sel.materialId && item.materialPartsById ? (item.materialPartsById[String(sel.materialId)] || []) : [];
          var partsMarkup = sel.materialId
            ? buildCheckboxList(item.id, selectedMaterialParts, sel.partKeys)
            : '<div style="padding:10px 12px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;color:#64748b;font-size:13px;">Select a member assembly to view its parts.</div>';
          var allRecentActive = sel.mode==='all-most-recent';
          var specificActive  = sel.mode==='specific';
          var detailsMarkup   = isExpanded && specificActive
            ? '<div style="padding:18px;display:grid;gap:18px;border-top:1px solid #dbe4ee;background:#fff;">'
                +'<div><div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:10px;">Bill of Materials</div><div style="display:grid;gap:10px;">'+materialsMarkup+'</div></div>'
                +'<div><div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:10px;">Parts and Suppliers</div><div style="display:grid;gap:10px;max-height:260px;overflow:auto;padding-right:4px;">'+partsMarkup+'</div></div>'
              +'</div>'
            : '';
          return '<section style="border:1px solid #dbe4ee;border-radius:14px;overflow:hidden;background:#f8fafc;">'
            +'<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 18px;background:#eef4f9;">'
              +'<div><div style="font-size:18px;font-weight:700;color:#0f172a;">'+escapeHtml(item.name||('Engineering Item #'+item.id))+'</div></div>'
              +'<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end;">'
                +'<button type="button" data-role="select-all-recent" data-item-id="'+escapeHtml(item.id)+'" style="padding:8px 12px;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;background:'+(allRecentActive?'#0f766e':'#5b7fa3')+';color:#fff;">Select All Most Recent</button>'
                +'<button type="button" data-role="select-specific"   data-item-id="'+escapeHtml(item.id)+'" style="padding:8px 12px;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;background:'+(specificActive?'#1d4ed8':'#64748b')+';color:#fff;">Select Specific</button>'
              +'</div>'
            +'</div>'
            +detailsMarkup
            +'</section>';
        }).join('');
        attachSpecificPartsHandlers();
        updateSpecificPartsSummary();
      }

      function attachSpecificPartsHandlers() {
        Array.prototype.forEach.call(specificPartsModalBody.querySelectorAll('[data-role="select-all-recent"]'), function(button) {
          button.addEventListener('click', function() {
            var itemId = this.getAttribute('data-item-id');
            var sel    = ensureItemSelection(itemId);
            var item   = specificPartsState.items.find(function(e){ return String(e.id)===String(itemId); });
            sel.itemSelected = true; sel.mode = 'all-most-recent';
            sel.materialId = item ? getMostRecentId(item.materials) : '';
            var selectedParts = item && sel.materialId && item.materialPartsById ? (item.materialPartsById[String(sel.materialId)] || []) : [];
            selectAllParts(selectedParts, sel);
            specificPartsState.expandedItemId = null;
            renderSpecificPartsModal();
          });
        });
        Array.prototype.forEach.call(specificPartsModalBody.querySelectorAll('[data-role="select-specific"]'), function(button) {
          button.addEventListener('click', function() {
            var itemId = this.getAttribute('data-item-id');
            var sel    = ensureItemSelection(itemId);
            sel.itemSelected = true; sel.mode = 'specific';
            specificPartsState.expandedItemId = String(specificPartsState.expandedItemId||'')===String(itemId) ? null : itemId;
            renderSpecificPartsModal();
          });
        });
        Array.prototype.forEach.call(specificPartsModalBody.querySelectorAll('input[type="radio"]'), function(input) {
          input.addEventListener('change', function() {
            var name   = this.name||'';
            var value  = this.value||'';
            var itemId = name.split('_').pop();
            var sel    = ensureItemSelection(itemId);
            sel.itemSelected = true; sel.mode = 'specific';
            if (name.indexOf('member_assembly_')===0) {
              sel.materialId = value;
              sel.partKeys = {};
            }
            updateSpecificPartsSummary();
            renderSpecificPartsModal();
          });
        });
        Array.prototype.forEach.call(specificPartsModalBody.querySelectorAll('input[type="checkbox"][data-part-key]'), function(input) {
          input.addEventListener('change', function() {
            var itemId  = this.getAttribute('data-item-id');
            var partKey = this.getAttribute('data-part-key');
            var sel     = ensureItemSelection(itemId);
            sel.itemSelected = true; sel.mode = 'specific';
            sel.partKeys[partKey] = !!this.checked;
            updateSpecificPartsSummary();
          });
        });
      }

      function loadSpecificPartsData() {
        specificPartsStatus.textContent = 'Loading engineering items...';
        specificPartsModalBody.innerHTML = '<div style="padding:16px;border:1px dashed #cbd5e1;border-radius:10px;color:#64748b;">Loading data...</div>';
        fetch(apiBase+'/get_engineering_items.php', { credentials:'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(data) {
            if (!data||!data.success||!Array.isArray(data.items)) throw new Error('Unable to load engineering items');
            return Promise.all(data.items.map(function(item) {
              ensureItemSelection(item.id);
              return fetch(apiBase+'/get_engineering_materials.php?item_id='+encodeURIComponent(item.id), {credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .catch(function(){ return {success:false,materials:[]}; })
                .then(function(materialResult) {
                  var materials = materialResult&&materialResult.success&&Array.isArray(materialResult.materials) ? materialResult.materials : [];
                  return Promise.all(materials.map(function(material) {
                    return fetch(apiBase+'/get_material_parts.php?material_id='+encodeURIComponent(material.id), {credentials:'same-origin'})
                      .then(function(r){ return r.json(); })
                      .catch(function(){ return {success:false,parts:[]}; })
                      .then(function(partsResult) {
                        return {
                          materialId: material.id,
                          parts: partsResult&&partsResult.success&&Array.isArray(partsResult.parts) ? partsResult.parts : []
                        };
                      });
                  })).then(function(materialPartsResults) {
                    var materialPartsById = {};
                    materialPartsResults.forEach(function(entry) {
                      materialPartsById[String(entry.materialId)] = entry.parts || [];
                    });
                    return {
                      id: item.id,
                      name: item.name,
                      materials: materials,
                      materialPartsById: materialPartsById
                    };
                  });
                })
                .then(function(resultItem) {
                  return resultItem;
                });
            }));
          })
          .then(function(items) {
            specificPartsState.items  = items;
            specificPartsState.loaded = true;
            renderSpecificPartsModal();
          })
          .catch(function(error) {
            specificPartsStatus.textContent = 'Failed to load engineering data';
            specificPartsModalBody.innerHTML = '<div style="padding:16px;border:1px solid #fecaca;background:#fef2f2;border-radius:10px;color:#991b1b;">'+escapeHtml(error&&error.message?error.message:'Failed to load engineering data.')+'</div>';
          });
      }

      function openSpecificPartsModal() {
        if (!specificPartsModal) return;
        specificPartsModal.style.display = 'flex';
        if (!specificPartsState.loaded) loadSpecificPartsData();
        else renderSpecificPartsModal();
      }

      function closeSpecificPartsModal() {
        if (!specificPartsModal) return;
        specificPartsModal.style.display = 'none';
      }

      if (selectSpecificPartsBtn)      selectSpecificPartsBtn.addEventListener('click', openSpecificPartsModal);
      if (closeSpecificPartsModalBtn)  closeSpecificPartsModalBtn.addEventListener('click', closeSpecificPartsModal);
      if (cancelSpecificPartsModalBtn) cancelSpecificPartsModalBtn.addEventListener('click', closeSpecificPartsModal);
      if (applySpecificPartsBtn) {
        applySpecificPartsBtn.addEventListener('click', function() {
          updateSpecificPartsSummary();
          closeSpecificPartsModal();
        });
      }
      if (specificPartsModal) {
        specificPartsModal.addEventListener('click', function(event) {
          if (event.target === specificPartsModal) closeSpecificPartsModal();
        });
      }
      document.addEventListener('keydown', function(event) {
        if (event.key==='Escape' && specificPartsModal && specificPartsModal.style.display==='flex') closeSpecificPartsModal();
      });

      // ============================================================
      // Engineering Panel
      // ============================================================
      var engSelectedItem         = null;
      var engBomDropdownBusy      = false;
      var engPartsDropdownBusy    = false;
      var engDraftId              = equipmentData ? (equipmentData.draftId || null) : null;
      var engCheckedItems         = {};
      var engCheckedMaterials     = {};
      var engCheckedParts         = {};
      var engPartSelectedVersions = {};

      function buildBomPartKey(itemId, materialId, part) {
        var partToken = (part && part.engineering_part_id) ? ('ep-'+String(part.engineering_part_id)) : ('n-'+String(part && part.number ? part.number : '')+'-'+String(part && part.name ? part.name : ''));
        return String(itemId) + '|' + String(materialId) + '|' + partToken;
      }

      function extractPartVersions(drawings) {
        var seen = {};
        var versions = [];
        (drawings || []).forEach(function(d) {
          var v = (d && d.version) ? String(d.version).toLowerCase() : 'v1';
          if (!seen[v]) {
            seen[v] = true;
            versions.push(v);
          }
        });
        versions.sort(function(a,b){ return (parseInt(b.replace(/\D/g,''),10)||1) - (parseInt(a.replace(/\D/g,''),10)||1); });
        return versions.length ? versions : ['v1'];
      }

      function parseBomPartKey(partKey) {
        var parsed = { itemId: 0, materialId: 0, partId: null };
        if (!partKey) return parsed;
        var tokens = String(partKey).split('|');
        if (tokens.length < 3) return parsed;
        parsed.itemId = parseInt(tokens[0], 10) || 0;
        parsed.materialId = parseInt(tokens[1], 10) || 0;
        var partToken = tokens.slice(2).join('|');
        if (partToken.indexOf('ep-') === 0) {
          var idNum = parseInt(partToken.slice(3), 10);
          parsed.partId = idNum > 0 ? idNum : null;
        }
        return parsed;
      }

      function buildDraftSelectionRows() {
        var rows = [];
        var dedupe = {};

        function pushRow(itemId, materialId, partId, version) {
          var iid = parseInt(itemId, 10) || 0;
          if (iid <= 0) return;
          var mid = parseInt(materialId, 10) || 0;
          var pid = parseInt(partId, 10) || 0;
          var ver = (version == null || String(version).trim() === '') ? null : String(version).toLowerCase();
          var rowKey = [iid, mid || 0, pid || 0, ver || ''].join('|');
          if (dedupe[rowKey]) return;
          dedupe[rowKey] = true;
          rows.push({
            item_id: iid,
            material_id: mid > 0 ? mid : null,
            part_id: pid > 0 ? pid : null,
            version: ver
          });
        }

        Object.keys(engCheckedItems).forEach(function(itemId) {
          if (!engCheckedItems[itemId]) return;
          pushRow(itemId, null, null, null);

          var materialMap = engCheckedMaterials[itemId] || {};
          Object.keys(materialMap).forEach(function(materialId) {
            if (!materialMap[materialId]) return;
            pushRow(itemId, materialId, null, null);
          });
        });

        Object.keys(engCheckedParts).forEach(function(partKey) {
          if (!engCheckedParts[partKey]) return;
          var parsed = parseBomPartKey(partKey);
          if (!parsed.itemId || !parsed.materialId) return;
          if (!engCheckedItems[String(parsed.itemId)]) return;
          var matMap = engCheckedMaterials[String(parsed.itemId)] || {};
          if (!matMap[String(parsed.materialId)]) return;
          pushRow(parsed.itemId, parsed.materialId, parsed.partId, engPartSelectedVersions[partKey] || 'v1');
        });

        return rows;
      }

      function setEngItemCardStyle(div, isSelected, isHover) {
        div.style.background   = isSelected ? '#dbeafe' : (isHover ? '#e8edf2' : '#f5f7fa');
        div.style.borderColor  = isSelected ? '#3b82f6' : '#d1d5db';
        div.style.color        = isSelected ? '#1d4ed8' : '#1f2937';
      }

      function showEngSelectionPrompt(item) {
        var details = document.getElementById('engItemDetails');
        if (!details) return;
        details.innerHTML = '';

        var titleRow = document.createElement('div');
        titleRow.style.cssText = 'display:flex;align-items:center;margin-bottom:18px;';
        var titleEl = document.createElement('h2');
        titleEl.textContent = item.name || ('Item #'+item.id);
        titleEl.style.cssText = 'margin:0;font-size:1.35em;font-weight:700;color:#1f2937;';
        titleRow.appendChild(titleEl);
        details.appendChild(titleRow);

        var message = document.createElement('div');
        message.textContent = 'Please select this engineering item to choose further options.';
        message.style.cssText = 'padding:18px 20px;background:#f8fafc;border:1px solid #dbe4ee;border-radius:10px;color:#475569;font-size:0.98em;font-weight:600;';
        details.appendChild(message);
      }

      function renderEngItems(items) {
        var list = document.getElementById('engItemList');
        if (!list) return;
        list.innerHTML = '';
        var sorted = items.slice().sort(function(a,b){ return (a.name||'').toLowerCase().localeCompare((b.name||'').toLowerCase()); });
        sorted.forEach(function(item) {
          var div = document.createElement('div');
          div.setAttribute('data-eng-item-id', item.id);
          div.style.cssText = 'width:100%;box-sizing:border-box;padding:10px 14px;border-radius:8px;cursor:pointer;font-weight:600;font-size:0.97em;border:1.5px solid #d1d5db;transition:background 0.15s,border-color 0.15s;display:flex;align-items:center;gap:10px;';

          var checkbox = document.createElement('input');
          checkbox.type             = 'checkbox';
          checkbox.style.cssText    = 'width:16px;height:16px;cursor:pointer;flex-shrink:0;';
          checkbox.checked          = !!engCheckedItems[String(item.id)];
          checkbox.addEventListener('click', function(e) {
            e.stopPropagation();
            engCheckedItems[String(item.id)] = checkbox.checked;
            if (engSelectedItem && String(engSelectedItem.id) === String(item.id)) {
              ['engBomDropdown','engPartsDropdown'].forEach(function(id){ var el=document.getElementById(id); if(el) el.remove(); });
              if (checkbox.checked) {
                showEngDetails(item);
              } else {
                showEngSelectionPrompt(item);
              }
            }
          });

          var nameSpan = document.createElement('span');
          nameSpan.textContent = item.name || ('Item #'+item.id);
          div.appendChild(checkbox);
          div.appendChild(nameSpan);
          setEngItemCardStyle(div, false, false);
          div.addEventListener('mouseenter', function() { if (engSelectedItem&&String(engSelectedItem.id)===String(item.id)) return; setEngItemCardStyle(div,false,true); });
          div.addEventListener('mouseleave', function() { if (engSelectedItem&&String(engSelectedItem.id)===String(item.id)) return; setEngItemCardStyle(div,false,false); });
          div.addEventListener('click', function() {
            if (engSelectedItem) {
              var prev = document.querySelector('[data-eng-item-id="'+engSelectedItem.id+'"]');
              if (prev) setEngItemCardStyle(prev, false, false);
            }
            ['engBomDropdown','engPartsDropdown'].forEach(function(id){ var el=document.getElementById(id); if(el) el.remove(); });
            engSelectedItem = item;
            setEngItemCardStyle(div, true, false);
            if (engCheckedItems[String(item.id)]) {
              showEngDetails(item);
            } else {
              showEngSelectionPrompt(item);
            }
          });
          list.appendChild(div);
        });
      }

      function fetchEngItems() {
        fetch(apiBase+'/get_engineering_items.php', { credentials:'same-origin' })
          .then(function(res){ return res.json(); })
          .then(function(data) {
            if (data&&data.success&&Array.isArray(data.items)) {
              renderEngItems(data.items);
              loadDraftEngItems();
            }
          })
          .catch(function(err){ console.error('Failed to fetch engineering items:', err); });
      }

      function loadDraftEngItems() {
        if (!engDraftId) return;
        fetch(apiBase+'/get_draft_engineering_items.php?draft_id='+encodeURIComponent(engDraftId), { credentials:'same-origin' })
          .then(function(res){ return res.json(); })
          .then(function(data) {
            if (!(data && data.success)) return;

            if (data.item_ids&&data.item_ids.length>0) {
              data.item_ids.forEach(function(id) {
                engCheckedItems[String(id)] = true;
              });
            }

            if (data.selection_rows && data.selection_rows.length > 0) {
              data.selection_rows.forEach(function(row) {
                var itemId = row && row.item_id ? String(row.item_id) : '';
                if (!itemId) return;
                engCheckedItems[itemId] = true;

                var materialId = row.material_id ? String(row.material_id) : '';
                if (materialId) {
                  engCheckedMaterials[itemId] = engCheckedMaterials[itemId] || {};
                  engCheckedMaterials[itemId][materialId] = true;
                }

                var partId = row.part_id ? parseInt(row.part_id, 10) : 0;
                if (materialId && partId > 0) {
                  var pKey = itemId + '|' + materialId + '|ep-' + partId;
                  engCheckedParts[pKey] = true;
                  if (row.version) {
                    engPartSelectedVersions[pKey] = String(row.version).toLowerCase();
                  }
                }
              });
            }

            Object.keys(engCheckedItems).forEach(function(id) {
              var card = document.querySelector('[data-eng-item-id="'+id+'"]');
              if (card) {
                var cb=card.querySelector('input[type="checkbox"]');
                if (cb) cb.checked=true;
              }
            });
          })
          .catch(function(err){ console.error('Failed to load draft items:', err); });
      }

      function showEngDetails(item) {
        var details = document.getElementById('engItemDetails');
        if (!details) return;
        details.innerHTML = '';

        var titleRow = document.createElement('div');
        titleRow.style.cssText = 'display:flex;align-items:center;margin-bottom:18px;';
        var titleEl = document.createElement('h2');
        titleEl.textContent = item.name || ('Item #'+item.id);
        titleEl.style.cssText = 'margin:0;font-size:1.35em;font-weight:700;color:#1f2937;';
        titleRow.appendChild(titleEl);
        details.appendChild(titleRow);

        var ul = document.createElement('ul');
        ul.style.cssText = 'list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:0;';

        [{label:'Bill of materials',   handler:function(li){ handleEngBomClick(item,li); }},
         {label:'Parts and Suppliers', handler:function(li){ handleEngPartsClick(item,li); }}
        ].forEach(function(section) {
          var li = document.createElement('li');
          li.style.borderBottom = '1px solid #e5e7eb';

          var row = document.createElement('div');
          row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:13px 16px;cursor:pointer;background:#fff;border-radius:6px;transition:background 0.15s;';

          var label = document.createElement('span');
          label.textContent = section.label;
          label.style.cssText = 'font-weight:600;font-size:0.97em;color:#374151;';

          var chevronSvg = document.createElement('span');
          chevronSvg.innerHTML = '<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M5 7L9 11L13 7" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
          chevronSvg.style.cssText = 'display:inline-flex;align-items:center;';

          row.addEventListener('mouseenter', function(){ row.style.background='#f3f4f6'; });
          row.addEventListener('mouseleave', function(){ row.style.background='#fff'; });
          row.addEventListener('click', function(){ section.handler(li); });
          row.appendChild(label);
          row.appendChild(chevronSvg);
          li.appendChild(row);
          ul.appendChild(li);
        });
        details.appendChild(ul);
      }

      function handleEngBomClick(item, liElement) {
        if (engBomDropdownBusy) return;
        engBomDropdownBusy = true;
        var existingDropdown = document.getElementById('engBomDropdown');
        if (existingDropdown) { existingDropdown.remove(); engBomDropdownBusy=false; return; }

        fetch(apiBase+'/get_engineering_bom.php?item_id='+item.id)
          .then(function(res){ return res.json(); })
          .then(function(data) {
            var dropdown = document.createElement('div');
            dropdown.id = 'engBomDropdown';
            dropdown.style.cssText = 'position:relative;display:block;background:#f7f9fc;border:1px solid #d1d5db;border-top:none;border-radius:0 0 6px 6px;width:100%;max-height:0;opacity:0;transform:translateY(-4px);transition:max-height 0.24s ease,opacity 0.2s ease,transform 0.2s ease;overflow:hidden;pointer-events:none;z-index:1;margin:0 0 10px 0;padding:10px 0;box-sizing:border-box;';

            var hasBoms = data.success&&data.boms&&data.boms.length>0;
            if (hasBoms) {
              var bomsByVersion = {};
              data.boms.forEach(function(bom) {
                var vk = (bom.version||'v1').toLowerCase().replace(/\s+/g,'');
                if (!bomsByVersion[vk]) bomsByVersion[vk]=[];
                bomsByVersion[vk].push(bom);
              });
              var sortedVersions = Object.keys(bomsByVersion).sort(function(a,b){ return (parseInt(b.replace(/\D/g,''),10)||0)-(parseInt(a.replace(/\D/g,''),10)||0); });

              function createEngBomVersionBox(versionKey, headerLabel, withPreviousToggle, previousContainer) {
                var versionBox = document.createElement('div');
                versionBox.style.cssText = 'margin:4px 10px;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;background:#fff;';
                var versionHeader = document.createElement('div');
                versionHeader.style.cssText = 'display:flex;align-items:center;padding:8px 12px;background:#f1f5f9;border-bottom:1px solid #e5e7eb;gap:10px;';
                var versionLabel = document.createElement('span');
                versionLabel.textContent = headerLabel;
                versionLabel.style.cssText = 'font-weight:700;font-size:0.92em;color:#1f2937;flex:1;';
                versionHeader.appendChild(versionLabel);
                if (bomsByVersion[versionKey]&&bomsByVersion[versionKey][0]&&bomsByVersion[versionKey][0].file_url) {
                  var downloadBtn = document.createElement('a');
                  downloadBtn.href = bomsByVersion[versionKey][0].file_url;
                  downloadBtn.textContent = 'Download '+versionKey.toUpperCase();
                  downloadBtn.target = '_blank';
                  downloadBtn.style.cssText = 'padding:4px 12px;background:#5b7fa3;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;font-size:0.85em;';
                  versionHeader.appendChild(downloadBtn);
                }
                if (withPreviousToggle) {
                  var previousToggle = document.createElement('span');
                  previousToggle.textContent = 'Click to view previous versions';
                  previousToggle.style.cssText = 'font-weight:600;font-size:0.88em;color:#5b7fa3;cursor:pointer;margin-left:auto;';
                  previousToggle.addEventListener('click', function() {
                    if (!previousContainer) return;
                    var isHidden = previousContainer.style.display==='none';
                    previousContainer.style.display = isHidden ? 'block' : 'none';
                    previousToggle.textContent = isHidden ? 'Hide previous versions' : 'Click to view previous versions';
                    dropdown.style.maxHeight = (dropdown.scrollHeight+40)+'px';
                  });
                  versionHeader.appendChild(previousToggle);
                }
                versionBox.appendChild(versionHeader);
                bomsByVersion[versionKey].forEach(function(bom, index) {
                  var bomRow = document.createElement('div');
                  bomRow.style.cssText = 'padding:8px 12px;display:flex;align-items:center;font-size:0.93em;color:#1f2937;'+(index<bomsByVersion[versionKey].length-1?'border-bottom:1px solid #f1f5f9;':'');
                  var nameSpan = document.createElement('span');
                  nameSpan.textContent = bom.document_name||'BOM';
                  nameSpan.style.flex = '1';
                  bomRow.appendChild(nameSpan);
                  versionBox.appendChild(bomRow);
                });
                return versionBox;
              }

              var currentVersionKey = sortedVersions[0];
              var previousContainer = document.createElement('div');
              previousContainer.style.display = 'none';
              dropdown.appendChild(createEngBomVersionBox(currentVersionKey,'Current Version: '+currentVersionKey.toUpperCase(),sortedVersions.length>1,previousContainer));
              sortedVersions.slice(1).forEach(function(vk){ previousContainer.appendChild(createEngBomVersionBox(vk,'Version: '+vk.toUpperCase(),false,null)); });
              dropdown.appendChild(previousContainer);

              var separator = document.createElement('div');
              separator.style.cssText = 'border-top:1px solid #e5e7eb;margin:4px 0;';
              dropdown.appendChild(separator);
            }

            if (!hasBoms) {
              var emptyBomState = document.createElement('div');
              emptyBomState.id = 'engBomEmptyState';
              emptyBomState.style.cssText = 'display:none;padding:10px 16px 6px 16px;font-size:0.92em;color:#6b7280;font-style:italic;';
              emptyBomState.textContent = 'No BOMs available.';
              dropdown.appendChild(emptyBomState);
            }

            var materialsHeader = document.createElement('div');
            materialsHeader.style.cssText = 'padding:12px 16px 6px 16px;font-weight:700;font-size:0.92em;color:#334155;border-top:1px solid #e5e7eb;margin-top:8px;';
            materialsHeader.textContent = 'Member Assemblies';
            dropdown.appendChild(materialsHeader);

            if (liElement.parentNode) liElement.parentNode.insertBefore(dropdown, liElement.nextSibling);

            fetch(apiBase+'/get_engineering_materials.php?item_id='+item.id)
              .then(function(res){ return res.json(); })
              .then(function(materialData) {
                if (materialData.success&&materialData.materials&&materialData.materials.length>0) {
                  var materialsContainer = document.createElement('div');
                  materialsContainer.style.padding = '6px 12px 10px 12px';

                  materialData.materials.forEach(function(material) {
                    var itemMaterialChecks = engCheckedMaterials[String(item.id)] || (engCheckedMaterials[String(item.id)] = {});
                    var materialWrapper = document.createElement('div');
                    materialWrapper.classList.add('eng-material-wrapper');
                    materialWrapper.style.cssText = 'border:2px solid #d1d5db;border-radius:6px;margin-bottom:8px;overflow:hidden;';

                    var materialRow = document.createElement('div');
                    materialRow.style.cssText = 'padding:10px 12px;display:flex;align-items:center;font-size:0.93em;color:#1f2937;background:#f9fafb;cursor:pointer;';

                    var numSpan = document.createElement('span');
                    numSpan.textContent = '#'+material.number;
                    numSpan.style.cssText = 'font-weight:700;color:#5b7fa3;margin-right:12px;min-width:50px;';

                    var assemblyCheckbox = document.createElement('input');
                    assemblyCheckbox.type = 'checkbox';
                    assemblyCheckbox.style.cssText = 'width:16px;height:16px;cursor:pointer;flex-shrink:0;margin-right:10px;';
                    assemblyCheckbox.checked = !!itemMaterialChecks[String(material.id)];

                    var nameSpan = document.createElement('span');
                    nameSpan.textContent = material.name;
                    nameSpan.style.flex = '1';

                    var chevron = document.createElement('span');
                    chevron.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M4.5 6L8 9.5L11.5 6" stroke="#888" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                    chevron.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;padding:4px;border-radius:4px;transition:transform 0.2s;';

                    function setPartsVisibility(isChecked) {
                      itemMaterialChecks[String(material.id)] = !!isChecked;
                      var existingMd = materialWrapper.querySelector('.eng-material-dropdown');
                      if (!isChecked) {
                        if (existingMd) existingMd.remove();
                        materialWrapper.style.borderColor = '#d1d5db';
                        chevron.style.transform = 'rotate(0deg)';
                        setTimeout(function(){ var bd=document.getElementById('engBomDropdown'); if(bd) bd.style.maxHeight=(bd.scrollHeight+40)+'px'; }, 50);
                        return;
                      }

                      materialWrapper.style.borderColor = '#3b82f6';
                      if (existingMd) {
                        chevron.style.transform = 'rotate(180deg)';
                        return;
                      }

                      chevron.style.transform = 'rotate(180deg)';
                      var materialDropdown = document.createElement('div');
                      materialDropdown.classList.add('eng-material-dropdown');
                      materialDropdown.style.cssText = 'padding:12px 16px;background:#ffffff;border-top:2px solid #e5e7eb;';
                      materialWrapper.appendChild(materialDropdown);
                      var bd = document.getElementById('engBomDropdown');
                      if (bd) bd.style.maxHeight=(bd.scrollHeight+40)+'px';

                      fetch(apiBase+'/get_material_parts.php?material_id='+material.id)
                        .then(function(res){ return res.json(); })
                        .then(function(partsData) {
                          if (partsData.success&&partsData.parts&&partsData.parts.length>0) {
                            var partsListContainer = document.createElement('div');
                            partsListContainer.style.cssText = 'margin-top:8px;border-top:1px solid #e5e7eb;padding-top:8px;';
                            partsData.parts.forEach(function(part) {
                              var partKey = buildBomPartKey(item.id, material.id, part);
                              var isPartChecked = !!engCheckedParts[partKey];
                              var partRow = document.createElement('div');
                              partRow.style.cssText = 'padding:10px 12px 10px 20px;background:#fafbfc;border-radius:4px;margin-bottom:4px;margin-left:8px;font-size:0.88em;display:flex;align-items:center;gap:16px;border:1px solid #e5e7eb;border-left:3px solid #5b7fa3;';

                              var partCheckbox = document.createElement('input');
                              partCheckbox.type = 'checkbox';
                              partCheckbox.style.cssText = 'width:16px;height:16px;cursor:pointer;flex-shrink:0;';
                              partCheckbox.checked = isPartChecked;

                              var pNum=document.createElement('span'); pNum.textContent='#'+part.number; pNum.style.cssText='font-weight:700;color:#5b7fa3;min-width:40px;';
                              var pName=document.createElement('span'); pName.textContent=part.name; pName.style.cssText='color:#1f2937;font-weight:600;min-width:120px;';
                              var pMake=document.createElement('span'); pMake.innerHTML='<span style="color:#6b7280;">Make:</span> '+(part.make||'-'); pMake.style.minWidth='150px';
                              var pMatType=document.createElement('span'); pMatType.innerHTML='<span style="color:#6b7280;">Material Type:</span> '+(part.material_type||'-'); pMatType.style.minWidth='180px';
                              var pQty=document.createElement('span'); pQty.innerHTML='<span style="color:#6b7280;">Quantity:</span> '+(part.quantity||'-'); pQty.style.minWidth='100px';

                              var partVersionMeta = document.createElement('span');
                              partVersionMeta.style.cssText = 'margin-left:auto;color:#334155;font-size:12px;font-weight:700;display:none;';

                              var partVersionChangeBtn = document.createElement('button');
                              partVersionChangeBtn.type = 'button';
                              partVersionChangeBtn.textContent = 'Change version';
                              partVersionChangeBtn.style.cssText = 'font-size:12px;font-weight:700;color:#1d4ed8;background:transparent;border:none;padding:0;cursor:pointer;display:none;';
                              partVersionChangeBtn.addEventListener('click', function(e) {
                                e.stopPropagation();
                              });

                              var versionWrap = document.createElement('div');
                              versionWrap.style.cssText = 'display:none;margin:6px 0 0 46px;padding:8px 10px;border:1px solid #dbe4ee;border-radius:6px;background:#fff;';

                              function updateBomHeight() {
                                var bdx = document.getElementById('engBomDropdown');
                                if (bdx) setTimeout(function(){ bdx.style.maxHeight = (bdx.scrollHeight + 40) + 'px'; }, 30);
                              }

                              function renderVersionSelector(versions) {
                                versionWrap.innerHTML = '';
                                var selectedVersion = engPartSelectedVersions[partKey] || versions[0];
                                if (versions.indexOf(selectedVersion) === -1) {
                                  selectedVersion = versions[0];
                                }
                                engPartSelectedVersions[partKey] = selectedVersion;
                                partVersionMeta.textContent = 'Selected Version: ' + String(selectedVersion).toUpperCase();
                                partVersionMeta.style.display = partCheckbox.checked ? 'inline' : 'none';

                                var optionsWrap = document.createElement('div');
                                optionsWrap.style.cssText = 'display:none;';
                                var selectorTitle = document.createElement('div');
                                selectorTitle.textContent = versions.length > 1 ? 'Select version (one only)' : 'Version';
                                selectorTitle.style.cssText = 'font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;';
                                optionsWrap.appendChild(selectorTitle);

                                partVersionChangeBtn.style.display = (versions.length > 1 && partCheckbox.checked) ? 'inline' : 'none';
                                partVersionChangeBtn.textContent = 'Change version';
                                partVersionChangeBtn.onclick = function(e) {
                                  e.stopPropagation();
                                  var opening = optionsWrap.style.display === 'none';
                                  optionsWrap.style.display = opening ? 'block' : 'none';
                                  partVersionChangeBtn.textContent = opening ? 'Hide versions' : 'Change version';
                                  versionWrap.style.display = opening ? 'block' : 'none';
                                  updateBomHeight();
                                };

                                versions.forEach(function(ver) {
                                  var option = document.createElement('label');
                                  option.style.cssText = 'display:flex;align-items:center;gap:8px;margin:4px 0;cursor:pointer;color:#1f2937;font-size:12px;';
                                  var radio = document.createElement('input');
                                  radio.type = 'radio';
                                  radio.name = 'bom_part_version_' + partKey.replace(/[^a-zA-Z0-9_\-]/g, '_');
                                  radio.value = ver;
                                  radio.checked = String(selectedVersion) === String(ver);
                                  radio.addEventListener('click', function(e){ e.stopPropagation(); });
                                  radio.addEventListener('change', function() {
                                    if (!this.checked) return;
                                    engPartSelectedVersions[partKey] = ver;
                                    partVersionMeta.textContent = 'Selected Version: ' + String(ver).toUpperCase();
                                    optionsWrap.style.display = 'none';
                                    partVersionChangeBtn.textContent = 'Change version';
                                    versionWrap.style.display = 'none';
                                    updateBomHeight();
                                  });
                                  var txt = document.createElement('span');
                                  txt.textContent = String(ver).toUpperCase();
                                  option.appendChild(radio);
                                  option.appendChild(txt);
                                  optionsWrap.appendChild(option);
                                });

                                versionWrap.appendChild(optionsWrap);
                              }

                              function loadPartVersionsThenRender() {
                                if (!part.engineering_part_id) {
                                  renderVersionSelector(['v1']);
                                  return;
                                }
                                fetch(apiBase + '/get_engineering_part_drawings.php?item_id=' + encodeURIComponent(item.id) + '&part_id=' + encodeURIComponent(part.engineering_part_id))
                                  .then(function(res){ return res.json(); })
                                  .then(function(drawData) {
                                    var versions = (drawData && drawData.success) ? extractPartVersions(drawData.drawings) : ['v1'];
                                    renderVersionSelector(versions);
                                  })
                                  .catch(function() {
                                    renderVersionSelector(['v1']);
                                  });
                              }

                              function setPartCheckedState(checked) {
                                engCheckedParts[partKey] = !!checked;
                                partCheckbox.checked = !!checked;
                                if (!checked) {
                                  versionWrap.style.display = 'none';
                                  partVersionMeta.style.display = 'none';
                                  partVersionChangeBtn.style.display = 'none';
                                  partVersionChangeBtn.textContent = 'Change version';
                                  partRow.style.borderColor = '#e5e7eb';
                                  partRow.style.background = '#fafbfc';
                                  updateBomHeight();
                                  return;
                                }
                                partRow.style.borderColor = '#93c5fd';
                                partRow.style.background = '#eff6ff';
                                loadPartVersionsThenRender();
                                updateBomHeight();
                              }

                              partCheckbox.addEventListener('click', function(e) {
                                e.stopPropagation();
                                setPartCheckedState(partCheckbox.checked);
                              });

                              partRow.addEventListener('click', function() {
                                setPartCheckedState(!partCheckbox.checked);
                              });

                              versionWrap.addEventListener('click', function(e) {
                                e.stopPropagation();
                              });

                              partRow.appendChild(partCheckbox);
                              partRow.appendChild(pNum); partRow.appendChild(pName); partRow.appendChild(pMake); partRow.appendChild(pMatType); partRow.appendChild(pQty); partRow.appendChild(partVersionMeta); partRow.appendChild(partVersionChangeBtn);
                              partsListContainer.appendChild(partRow);
                              partsListContainer.appendChild(versionWrap);

                              if (isPartChecked) {
                                setPartCheckedState(true);
                              }
                            });
                            materialDropdown.appendChild(partsListContainer);
                          } else {
                            var noPartsMsg=document.createElement('div');
                            noPartsMsg.textContent='No parts added yet.';
                            noPartsMsg.style.cssText='font-size:0.88em;color:#9ca3af;font-style:italic;';
                            materialDropdown.appendChild(noPartsMsg);
                          }
                          var bd2=document.getElementById('engBomDropdown');
                          if (bd2) setTimeout(function(){ bd2.style.maxHeight=(bd2.scrollHeight+40)+'px'; },50);
                        });
                    }

                    assemblyCheckbox.addEventListener('click', function(e) {
                      e.stopPropagation();
                      setPartsVisibility(assemblyCheckbox.checked);
                    });

                    materialRow.addEventListener('click', function() {
                      assemblyCheckbox.checked = !assemblyCheckbox.checked;
                      setPartsVisibility(assemblyCheckbox.checked);
                    });

                    materialRow.appendChild(assemblyCheckbox);
                    materialRow.appendChild(numSpan);
                    materialRow.appendChild(nameSpan);
                    materialRow.appendChild(chevron);
                    materialWrapper.appendChild(materialRow);

                    if (assemblyCheckbox.checked) {
                      setPartsVisibility(true);
                    }

                    materialsContainer.appendChild(materialWrapper);
                  });

                  dropdown.appendChild(materialsContainer);
                  var bomEmpty = dropdown.querySelector('#engBomEmptyState');
                  if (bomEmpty) bomEmpty.style.display='none';
                } else {
                  if (!hasBoms) { var bomEmpty2=dropdown.querySelector('#engBomEmptyState'); if(bomEmpty2) bomEmpty2.style.display='block'; }
                  var emptyMat=document.createElement('div');
                  emptyMat.textContent='No member assemblies added yet.';
                  emptyMat.style.cssText='padding:8px 16px 10px 16px;font-size:0.88em;color:#9ca3af;font-style:italic;';
                  dropdown.appendChild(emptyMat);
                }
                setTimeout(function(){
                  dropdown.style.maxHeight  = (dropdown.scrollHeight+40)+'px';
                  dropdown.style.opacity    = '1';
                  dropdown.style.transform  = 'translateY(0)';
                  dropdown.style.pointerEvents = 'auto';
                }, 10);
              });
          })
          .catch(function(err){ console.error('Error loading BOMs:', err); })
          .finally(function(){ engBomDropdownBusy=false; });
      }

      function handleEngPartsClick(item, liElement) {
        if (engPartsDropdownBusy) return;
        engPartsDropdownBusy = true;
        var existingDropdown = document.getElementById('engPartsDropdown');
        if (existingDropdown) { existingDropdown.remove(); engPartsDropdownBusy=false; return; }

        fetch(apiBase+'/get_engineering_item_parts.php?item_id='+item.id)
          .then(function(res){ return res.json(); })
          .then(function(data) {
            var dropdown = document.createElement('div');
            dropdown.id = 'engPartsDropdown';
            dropdown.style.cssText = 'position:relative;display:block;background:#f7f9fc;border:1px solid #d1d5db;border-top:none;border-radius:0 0 6px 6px;width:100%;max-height:0;opacity:0;transform:translateY(-4px);transition:max-height 0.24s ease,opacity 0.2s ease,transform 0.2s ease;overflow:hidden;pointer-events:none;z-index:1;margin:0 0 10px 0;padding:10px 0;box-sizing:border-box;';

            var hasParts = data.success&&data.parts&&data.parts.length>0;
            if (hasParts) {
              var partsList = {};
              data.parts.forEach(function(part) {
                if (!partsList[part.part_name]) {
                  partsList[part.part_name] = { part_name:part.part_name, nsn_number:part.nsn_number||'', quantity:part.quantity||1, makes:[] };
                }
                if (part.make) partsList[part.part_name].makes.push({ make:part.make, supplierName:part.supplier_name||'' });
              });

              var partsContainer = document.createElement('div');
              partsContainer.style.cssText = 'margin-top:8px;border-top:1px solid #e5e7eb;padding-top:8px;';

              Object.keys(partsList).forEach(function(partName) {
                var pd = partsList[partName];
                var partRow = document.createElement('div');
                partRow.style.cssText = 'display:flex;align-items:center;padding:10px 12px;gap:16px;border-radius:4px;background:#fafbfc;margin-bottom:6px;transition:background 0.15s;';

                var nameSpan=document.createElement('span'); nameSpan.textContent=pd.part_name; nameSpan.style.cssText='color:#1f2937;font-weight:500;min-width:150px;';
                var makesText=pd.makes.length>0?pd.makes.map(function(m){ return m.make; }).filter(Boolean).join(', ')||'-':'-';
                var makeSpan=document.createElement('span'); makeSpan.innerHTML='<span style="color:#6b7280;">Make:</span> '+makesText; makeSpan.style.minWidth='200px';
                var nsnSpan=document.createElement('span'); nsnSpan.innerHTML='<span style="color:#6b7280;">NSN:</span> '+(pd.nsn_number||'-'); nsnSpan.style.minWidth='150px';
                var qtySpan=document.createElement('span'); qtySpan.innerHTML='<span style="color:#6b7280;">Quantity:</span> '+(pd.quantity||'-'); qtySpan.style.minWidth='100px';

                partRow.appendChild(nameSpan); partRow.appendChild(makeSpan); partRow.appendChild(nsnSpan); partRow.appendChild(qtySpan);
                partRow.addEventListener('mouseenter', function(){ partRow.style.background='#f3f4f6'; });
                partRow.addEventListener('mouseleave', function(){ partRow.style.background='#fafbfc'; });
                partsContainer.appendChild(partRow);
              });
              dropdown.appendChild(partsContainer);
            } else {
              var emptyState=document.createElement('div');
              emptyState.textContent='No parts available.';
              emptyState.style.cssText='padding:10px 16px 6px 16px;font-size:0.92em;color:#6b7280;font-style:italic;';
              dropdown.appendChild(emptyState);
            }

            if (liElement.parentNode) liElement.parentNode.insertBefore(dropdown, liElement.nextSibling);
            setTimeout(function(){
              dropdown.style.maxHeight  = (dropdown.scrollHeight+100)+'px';
              dropdown.style.opacity    = '1';
              dropdown.style.transform  = 'translateY(0)';
              dropdown.style.pointerEvents = 'auto';
            }, 10);
          })
          .catch(function(err){ console.error('Error fetching parts:', err); })
          .finally(function(){ engPartsDropdownBusy=false; });
      }

      fetchEngItems();

      var backToEngineeringBtn = document.getElementById('backToEngineeringBtn');
      if (backToEngineeringBtn) {
        backToEngineeringBtn.addEventListener('click', function() {
          window.location.href = 'index.php';
        });
      }

      // ── Deploy modal (no functionality yet) ──
      var deployEquipmentBtn   = document.getElementById('deployEquipmentBtn');
      var deployConfirmModal   = document.getElementById('deployConfirmModal');
      var deployConfirmNo      = document.getElementById('deployConfirmNo');
      var deployConfirmYes     = document.getElementById('deployConfirmYes');
      var deploySuccessModal   = document.getElementById('deploySuccessModal');
      var deploySuccessMessage = document.getElementById('deploySuccessMessage');
      var deploySuccessContinue = document.getElementById('deploySuccessContinue');
      var deployRedirectUrl = null;

      if (deployEquipmentBtn) {
        deployEquipmentBtn.addEventListener('click', function() {
          deployConfirmModal.classList.add('open');
        });
      }
      if (deployConfirmNo) {
        deployConfirmNo.addEventListener('click', function() {
          deployConfirmModal.classList.remove('open');
        });
      }
      if (deployConfirmYes) {
        deployConfirmYes.addEventListener('click', function() {
          var equipmentNumber = equipmentData && equipmentData.number ? String(equipmentData.number).trim() : '';
          var equipmentType = equipmentData && equipmentData.type ? String(equipmentData.type).trim() : '';
          if (!equipmentNumber || !equipmentType) {
            alert('Equipment number and type are required before deploy. Please go back and fill them in.');
            return;
          }

          var selectionRows = buildDraftSelectionRows();
          var hasSelectedParts = selectionRows.some(function(row) {
            return row && row.part_id;
          });
          if (!hasSelectedParts) {
            alert('Please select at least one part before deploying.');
            return;
          }

          deployConfirmYes.disabled = true;
          deployConfirmYes.textContent = 'Deploying...';

          fetch(apiBase + '/deploy_engineering_build.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              draft_id: engDraftId || null,
              equipment_number: equipmentNumber,
              equipment_type: equipmentType,
              selection_rows: selectionRows
            })
          })
          .then(function(res) { return res.json(); })
          .then(function(data) {
            if (!data || !data.success) {
              throw new Error((data && data.message) ? data.message : 'Deploy failed');
            }

            deployConfirmModal.classList.remove('open');

            try {
              localStorage.removeItem('buildNewEquipment');
              localStorage.removeItem('buildNewEquipmentCurrent');
            } catch (e) {}

            deployRedirectUrl = '../equipments/equipment.php?id=' + encodeURIComponent(data.equipment_id);
            if (deploySuccessMessage) {
              var partsInserted = parseInt(data.parts_inserted || 0, 10);
              var specsInserted = parseInt(data.specs_inserted || 0, 10);
              var partsSkipped = parseInt(data.parts_skipped || 0, 10);
              deploySuccessMessage.textContent =
                'Equipment ID: #' + String(data.equipment_id) + '\n'
                + 'Parts saved: ' + String(partsInserted) + '\n'
                + 'Specifications saved: ' + String(specsInserted) + '\n'
                + 'Skipped parts: ' + String(partsSkipped);
            }
            if (deploySuccessModal) {
              deploySuccessModal.classList.add('open');
            }
          })
          .catch(function(err) {
            var rawMsg = (err && err.message) ? String(err.message) : 'Unknown error';
            var cleanMsg = rawMsg.replace(/^Deploy failed:\s*/i, '');
            alert('Deploy failed: ' + cleanMsg);
          })
          .finally(function() {
            deployConfirmYes.disabled = false;
            deployConfirmYes.textContent = 'Yes, deploy';
          });
        });
      }
      if (deployConfirmModal) {
        deployConfirmModal.addEventListener('click', function(e) {
          if (e.target === deployConfirmModal) deployConfirmModal.classList.remove('open');
        });
      }
      if (deploySuccessContinue) {
        deploySuccessContinue.addEventListener('click', function() {
          if (deployRedirectUrl) {
            window.location.href = deployRedirectUrl;
          } else if (deploySuccessModal) {
            deploySuccessModal.classList.remove('open');
          }
        });
      }
      if (deploySuccessModal) {
        deploySuccessModal.addEventListener('click', function(e) {
          if (e.target === deploySuccessModal) {
            if (deployRedirectUrl) {
              window.location.href = deployRedirectUrl;
            } else {
              deploySuccessModal.classList.remove('open');
            }
          }
        });
      }
      document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (deployConfirmModal) deployConfirmModal.classList.remove('open');
        if (deploySuccessModal && deploySuccessModal.classList.contains('open')) {
          if (deployRedirectUrl) {
            window.location.href = deployRedirectUrl;
          } else {
            deploySuccessModal.classList.remove('open');
          }
        }
      });

      var saveEngineeringItemsBtn = document.getElementById('saveEngineeringItemsBtn');
      if (saveEngineeringItemsBtn) {
        saveEngineeringItemsBtn.addEventListener('click', function() {
          if (!engDraftId) {
            alert('No draft ID found. Please re-open this page from the drafts list.');
            return;
          }
          var checkedIds = Object.keys(engCheckedItems).filter(function(id){ return engCheckedItems[id]; });
          var selectionRows = buildDraftSelectionRows();
          saveEngineeringItemsBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="animation:spin 0.8s linear infinite"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2.5" stroke-dasharray="40" stroke-dashoffset="10"/></svg> Saving…';
          saveEngineeringItemsBtn.disabled = true;

          fetch(apiBase+'/save_draft_engineering_items.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/json' },
            body: JSON.stringify({ draft_id:engDraftId, item_ids:checkedIds, selection_rows:selectionRows })
          })
          .then(function(res){ return res.json(); })
          .then(function(data) {
            if (data.success) {
              saveEngineeringItemsBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M13.5 4.5L6 12L2.5 8.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg> Saved!';
              saveEngineeringItemsBtn.style.background = '#16a34a';
              setTimeout(function(){
                saveEngineeringItemsBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M13.5 4.5L6 12L2.5 8.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg> Save Draft';
                saveEngineeringItemsBtn.style.background = '#1e40af';
                saveEngineeringItemsBtn.disabled = false;
              }, 2000);
            } else {
              alert(data.message||'Failed to save');
              saveEngineeringItemsBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M13.5 4.5L6 12L2.5 8.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg> Save Draft';
              saveEngineeringItemsBtn.disabled = false;
            }
          })
          .catch(function(err){
            alert('Error saving: '+err.message);
            saveEngineeringItemsBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M13.5 4.5L6 12L2.5 8.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg> Save Draft';
            saveEngineeringItemsBtn.disabled = false;
          });
        });
      }

    })();
  </script>
  <script src="../../assets/js/mobile-menu.js"></script>
  <script src="../../assets/js/logout-confirm.js"></script>
</body>
</html>