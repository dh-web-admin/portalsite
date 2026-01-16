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
if (!can_access($role, 'Bid_tracking')) {
  header('Location: /pages/dashboard/');
  exit();
}

// Determine if current user can edit this page (used to show add button)
$canEditBidTracking = function_exists('can_edit_page') ? can_edit_page('Bid_tracking') : false;

// Load bids table
$bidColumns = [];
$bidRows = [];
$bidTableExists = false;

try {
  $tres = $conn->query("SHOW TABLES LIKE 'bids'");
  if ($tres && $tres->num_rows) {
    $bidTableExists = true;

    $colResult = $conn->query("SHOW COLUMNS FROM bids");
    if ($colResult) {
      while ($c = $colResult->fetch_assoc()) {
        // Exclude timestamp metadata and primary id columns from the UI
        if (in_array($c['Field'], ['created_at','updated_at','bid_id'], true)) continue;
        $bidColumns[] = $c['Field'];
      }
    }

    $rowResult = $conn->query("SELECT * FROM bids ORDER BY bid_id DESC");
    if ($rowResult) {
      while ($r = $rowResult->fetch_assoc()) $bidRows[] = $r;
    }
  }
} catch (Throwable $ex) {
  $bidTableExists = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Bid tracking</title>
  <link rel="stylesheet" href="../../assets/css/base.css" />
  <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="style.css" />
  <style>
    /* NOTE: Project style guide — no gradients allowed. Replaced gradient with solid color. */
    #addProjectBtn {
      background: #10b981;
      border: none;
      color: #ffffff;
      padding: 10px 16px;
      border-radius: 10px;
      font-weight: 700;
      font-size: 14px;
      box-shadow: 0 8px 22px rgba(16,185,129,0.12);
      transition: transform .12s ease, box-shadow .12s ease, opacity .12s ease;
      cursor: pointer;
      margin-top: 32px;
      margin-left: 40px;
    }
    #addProjectBtn:hover { transform: translateY(-2px); box-shadow: 0 12px 34px rgba(16,185,129,0.16); }
    #addProjectBtn:active { transform: translateY(0); box-shadow: 0 8px 22px rgba(16,185,129,0.12); }
    #addProjectBtn:disabled { opacity: 0.6; cursor: not-allowed; }

    #cancelAddProject {
      background: #ffffff;
      border: 1px solid #e6edf0;
      color: #0f172a;
      padding: 10px 14px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      transition: background .12s ease, transform .08s ease, box-shadow .12s ease;
      box-shadow: 0 1px 0 rgba(255,255,255,0.6) inset;
      margin-top: 8px;
      margin-right: 6px;
    }
    #cancelAddProject:hover { background: #fbfdfe; transform: translateY(-1px); }
    #cancelAddProject:active { transform: translateY(0); }

    #confirmAddProject {
      background: #10b981;
      border: none;
      color: #ffffff;
      padding: 10px 18px;
      border-radius: 8px;
      font-weight: 700;
      font-size: 14px;
      cursor: pointer;
      box-shadow: 0 8px 20px rgba(16,185,129,0.12);
      transition: transform .12s ease, box-shadow .12s ease, opacity .12s ease;
      margin-top: 8px;
      margin-left: 6px;
    }
    #confirmAddProject:hover { transform: translateY(-2px); box-shadow: 0 12px 34px rgba(16,185,129,0.16); }
    #confirmAddProject:active { transform: translateY(0); box-shadow: 0 8px 20px rgba(16,185,129,0.12); }
    #confirmAddProject:disabled { opacity: 0.6; cursor: not-allowed; }

    #projectToast {
      position: fixed;
      top: 24px;
      right: 24px;
      min-width: 220px;
      max-width: 420px;
      background: #f3f4f6;
      color: #0f172a;
      padding: 12px 14px;
      border-radius: 8px;
      box-shadow: 0 12px 30px rgba(2,6,23,0.08);
      display: none;
      align-items: center;
      gap: 10px;
      z-index: 6000;
      border: 1px solid rgba(15,23,42,0.06);
    }
    #projectToast.centered {
      top: 50% !important;
      left: 50% !important;
      right: auto !important;
      transform: translate(-50%, -50%) !important;
      box-shadow: 0 20px 50px rgba(2,6,23,0.12);
    }
    #projectToast.success { background: #f3f4f6; }
    #projectToast.error { background: #fee2e2; color: #7f1d1d; border-color: rgba(127,29,29,0.08); }
    #projectToast .msg { flex:1; font-weight:700; }
    #projectToast .close { background:transparent;border:0;color:rgba(15,23,42,0.7);cursor:pointer;font-weight:700;padding:6px;border-radius:6px }
  </style>
</head>
<body class="admin-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
      <main class="content-area">
        <div class="main-content">

          <div class="toolbar" style="display:flex;align-items:center;justify-content:flex-start;padding:8px 0;gap:12px;">
            <?php if (!empty($canEditBidTracking)) { ?>
              <button id="addProjectBtn" class="btn btn-primary">add Project +</button>
            <?php } ?>
          </div>

          <div style="padding:16px 40px;">
            <div style="overflow:auto;border:1px solid #e6edf0;border-radius:8px;padding:8px;background:#fff;">
              <?php if (!$bidTableExists) { ?>
                <div style="padding:12px;color:#7f1d1d;background:#fff5f5;border:1px solid rgba(127,29,29,0.06);border-radius:6px;margin-bottom:8px;">Bids table not found in the database.</div>
              <?php } ?>
              <table style="width:100%;border-collapse:collapse;font-size:13px;text-align:left;">
                <thead>
                  <tr>
                    <?php
                      if ($bidTableExists && !empty($bidColumns)) {
                        foreach ($bidColumns as $col) {
                          // Skip the native status column from the regular headers (we render status separately)
                          if ($col === 'status') continue;
                          // Insert an empty header cell before DHSS project # for the status pill (no header text)
                          if ($col === 'dhss_project_number') {
                            echo '<th style="text-align:left;padding:8px;border-bottom:1px solid #eef2f7;background:#fbfdfe;font-weight:700;color:#334155;width:120px;white-space:nowrap;"></th>';
                          }
                          $label = ($col === 'dhss_project_number') ? 'dhss project #' : str_replace('_',' ',$col);
                          if ($col === 'dhss_project_number') {
                            echo '<th style="text-align:center;padding:8px;border-bottom:1px solid #eef2f7;background:#fbfdfe;font-weight:700;color:#334155;width:90px;white-space:nowrap;">' . htmlspecialchars($label) . '</th>';
                          } else {
                            echo '<th style="text-align:left;padding:8px;border-bottom:1px solid #eef2f7;background:#fbfdfe;font-weight:700;color:#334155;">' . htmlspecialchars($label) . '</th>';
                          }
                        }
                      } else {
                        echo '<th style="text-align:left;padding:8px;">No columns</th>';
                      }
                    ?>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$bidTableExists) { ?>
                    <tr><td style="padding:12px;text-align:left;color:#64748b;" colspan="1">Table not available.</td></tr>
                  <?php } else if (empty($bidRows)) { ?>
                    <tr><td style="padding:12px;text-align:left;color:#64748b;" colspan="<?php echo max(1, count($bidColumns)+1); ?>">No bids found.</td></tr>
                  <?php } else { ?>
                    <?php foreach ($bidRows as $r) { ?>
                      <tr data-bid='<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8"); ?>' style="cursor:pointer;">
                        <?php foreach ($bidColumns as $col) { 
                            if ($col === 'status') continue; ?>

                          <?php if ($col === 'dhss_project_number') {
                            $statusRaw = isset($r['status']) ? $r['status'] : '';
                            $statusKey = strtolower(trim((string)$statusRaw));
                            $normalized = preg_replace('/[^a-z0-9]/', '', $statusKey);

                            $label = $statusRaw;
                            $color = '#374151';
                            $font = 'Arial, sans-serif';
                            if ($normalized === 'won') { $color = '#10b981'; $label = 'won'; $font = 'Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial'; }
                            else if ($normalized === 'completed') { $color = '#3b82f6'; $label = 'completed'; $font = 'Tahoma, Verdana, Segoe UI, sans-serif'; }
                            else if ($normalized === 'lost') { $color = '#ef4444'; $label = 'lost'; $font = 'Georgia, "Times New Roman", Times, serif'; }
                            else if ($normalized === 'didntbid' || $normalized === 'didnt') { $color = '#f97316'; $label = "didn't bid"; $font = '"Courier New", Courier, monospace'; }
                            else { $label = $statusRaw ? $statusRaw : 'pending'; }

                          ?>
                            <td style="padding:8px;border-bottom:1px solid #f1f5f9;color:<?php echo $color; ?>;vertical-align:top;word-break:break-word;font-weight:600;width:120px;white-space:nowrap;font-family:<?php echo htmlspecialchars($font); ?>;">
                              <?php echo htmlspecialchars($label); ?>
                            </td>
                          <?php } ?>

                          <?php if ($col === 'dhss_project_number') { ?>
                            <td style="padding:8px;border-bottom:1px solid #f1f5f9;color:#0f172a;vertical-align:top;word-break:break-word;width:90px;white-space:nowrap;text-align:center;">
                              <?php echo htmlspecialchars(isset($r[$col]) ? $r[$col] : ''); ?>
                            </td>
                          <?php } else { ?>
                            <td style="padding:8px;border-bottom:1px solid #f1f5f9;color:#0f172a;vertical-align:top;word-break:break-word;">
                              <?php echo htmlspecialchars(isset($r[$col]) ? $r[$col] : ''); ?>
                            </td>
                          <?php } ?>

                        <?php } ?>
                      </tr>
                    <?php } ?>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Edit Bid Modal -->
          <div id="editBidModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.35);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);align-items:center;justify-content:center;z-index:4500;padding:20px;overflow-y:auto;">
            <div style="background:#fff;border-radius:12px;padding:16px;max-width:700px;width:100%;box-shadow:0 8px 30px rgba(2,6,23,0.12);max-height:90vh;overflow-y:auto;">
              <form id="editBidForm" style="display:block;">
                <input type="hidden" id="editBidId" name="bid_id" />
                <div style="margin-bottom:12px;text-align:center;">
                  <select id="editStatus" name="status" style="min-width:90px;padding:6px 36px 6px 6px;border:0;background:transparent;appearance:none;-webkit-appearance:none;-moz-appearance:none;color:#374151;font-weight:600;background-image:url('data:image/svg+xml;utf8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' width=\'16\' height=\'16\'%3E%3Cpath fill=\'currentColor\' d=\'M7 10l5 5 5-5z\'/%3E%3C/svg%3E');background-repeat:no-repeat;background-position:right 10px center;background-size:16px;">
                    <option value="won" style="color:#10b981;">won</option>
                    <option value="lost" style="color:#ef4444;">lost</option>
                    <option value="pending" selected style="color:#374151;">pending</option>
                    <option value="didn't bid" style="color:#f97316;">didn't bid</option>
                    <option value="completed" style="color:#3b82f6;">completed</option>
                  </select>
                </div>

                <div style="display:flex;justify-content:flex-start;align-items:center;margin-bottom:12px;gap:12px;">
                  <div style="display:flex;flex-direction:column;flex:1;">
                    <label style="font-weight:600;color:#475569;margin-bottom:6px;">Project_name -</label>
                    <input type="text" id="editProjectName" name="project_name" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;" />
                  </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
                  <?php foreach ($bidColumns as $col) {
                    if ($col === 'project_name' || $col === 'status') continue;
                    $label = ($col === 'dhss_project_number') ? 'dhss project #' : str_replace('_',' ', $col);
                  ?>
                    <div>
                      <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;"><?php echo htmlspecialchars($label); ?></label>
                      <input type="text" data-col="<?php echo htmlspecialchars($col); ?>" name="<?php echo htmlspecialchars($col); ?>" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />
                    </div>
                  <?php } ?>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;">
                  <button type="button" id="closeEditBid" style="background:#fff;border:1px solid #e6edf0;color:#0f172a;padding:10px 14px;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
                  <button type="submit" id="saveEditBid" style="background:#10b981;border:none;color:#fff;padding:10px 16px;border-radius:8px;font-weight:700;cursor:pointer;">Save</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Add Project Modal -->
          <div id="addProjectModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.35);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);align-items:center;justify-content:center;z-index:4000;padding:20px;overflow-y:auto;">
            <div style="background:#fff;border-radius:12px;padding:20px;max-width:520px;width:100%;box-shadow:0 8px 30px rgba(2,6,23,0.12);max-height:90vh;overflow-y:auto;">
              <form id="addProjectForm" style="display:grid;gap:12px;">
                <div style="display:flex;justify-content:flex-start;align-items:center;">
                  <div style="font-size:18px;font-weight:700;color:#0f172a;">Add new Project</div>
                </div>
                <div>
                  <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">DHSS Project Number</label>
                  <input type="text" id="dhssProjectNumber" name="dhss_project_number" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;" />
                </div>
                <div>
                  <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">Project Name</label>
                  <input type="text" id="projectName" name="project_name" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;" />
                </div>
                <div>
                  <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">Bid Date</label>
                  <input type="date" id="bidDate" name="bid_date" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;" />
                </div>
                <hr />
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
                  <div>
                    <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">Project City</label>
                    <input type="text" id="projectCity" name="project_city" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">Project County</label>
                    <input type="text" id="projectCounty" name="project_county" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">Project State</label>
                    <input type="text" id="projectState" name="project_state" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />
                  </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px;">
                  <button type="button" id="cancelAddProject" class="btn">Cancel</button>
                  <button type="submit" id="confirmAddProject" class="btn btn-primary">Create Project</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Toast notification -->
          <div id="projectToast" role="status" aria-live="polite">
            <div class="msg"></div>
            <button class="close" aria-label="Dismiss">×</button>
          </div>

        </div>
      </main>
    </div>
  </div>

  <script>
    // Make toast GLOBAL so other scripts can call it
    window.showToast = function(message, type) {
      var t = document.getElementById('projectToast');
      if (!t) return;
      var msg = t.querySelector('.msg');
      var close = t.querySelector('.close');
      msg.textContent = message;

      t.classList.remove('success','error','centered');
      if (type === 'success') t.classList.add('success','centered');
      else if (type === 'error') t.classList.add('error');

      t.style.display = 'flex';
      clearTimeout(t._hideTimer);
      t._hideTimer = setTimeout(hideToast, 3000);

      function hideToast() {
        t.style.display = 'none';
        t.classList.remove('success','error','centered');
      }
      close.onclick = function(){ clearTimeout(t._hideTimer); hideToast(); };
    };

    (function(){
      var usersToggle = document.getElementById('usersToggle');
      var usersGroup = document.getElementById('usersGroup');
      if (usersToggle && usersGroup) {
        usersToggle.addEventListener('click', function(){
          usersGroup.classList.toggle('open');
        });
      }

      // Add Project modal behavior
      var addBtn = document.getElementById('addProjectBtn');
      var addModal = document.getElementById('addProjectModal');
      var cancelBtn = document.getElementById('cancelAddProject');
      var addForm = document.getElementById('addProjectForm');
      var dhssInput = document.getElementById('dhssProjectNumber');

      function openAddModal() {
        if (!addModal) return;
        fetch('../../api/get_next_project_number.php', { credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(data){ if (data && data.suggested && dhssInput) dhssInput.value = data.suggested; })
          .catch(function(){})
          .finally(function(){ addModal.style.display = 'flex'; });
      }

      if (addBtn) addBtn.addEventListener('click', openAddModal);
      if (cancelBtn) cancelBtn.addEventListener('click', function(){ if (addModal) addModal.style.display = 'none'; });

      if (addForm) {
        addForm.addEventListener('submit', function(e){
          e.preventDefault();
          var fd = new FormData(addForm);

          var submitBtn = document.getElementById('confirmAddProject');
          if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Creating...'; }

          fetch('../../api/create_project.php', { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(data){
              if (data && data.success) {
                showToast('Project added', 'success');
                if (addModal) addModal.style.display = 'none';
                setTimeout(function(){ window.location.reload(); }, 900);
              } else {
                var msg = (data && data.message) ? data.message : 'Failed to create project';
                showToast(msg, 'error');
              }
            })
            .catch(function(){ showToast('Failed to create project', 'error'); })
            .finally(function(){
              if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Create Project'; }
            });
        });
      }
    })();
  </script>

  <script>
    (function(){
      var bidColumns = <?php echo json_encode($bidColumns); ?> || [];
      var updateUrl = '../../api/update_bid.php'; // ✅ WORKS on local + Railway for /pages/... structure

      // Helper: set status control color based on status value
      function setStatusColor(val) {
        var s = (val || '').toString().toLowerCase().replace(/[^a-z0-9]/g,'');
        var color = '#374151';
        var font = 'Arial, sans-serif';
        if (s === 'won') { color = '#10b981'; font = 'Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial'; }
        else if (s === 'completed') { color = '#3b82f6'; font = 'Tahoma, Verdana, Segoe UI, sans-serif'; }
        else if (s === 'lost') { color = '#ef4444'; font = 'Georgia, "Times New Roman", Times, serif'; }
        else if (s === 'didntbid' || s === 'didnt') { color = '#f97316'; font = '"Courier New", Courier, monospace'; }
        else { font = 'Arial, sans-serif'; }
        var el = document.getElementById('editStatus');
        if (el) {
          el.style.color = color;
          el.style.fontFamily = font;
        }
      }

      function openEditModal(bidObj) {
        var modal = document.getElementById('editBidModal');
        if (!modal) return;

        var idInput = document.getElementById('editBidId');
        if (idInput) idInput.value = bidObj.bid_id || '';

        var pn = document.getElementById('editProjectName');
        if (pn) pn.value = bidObj.project_name || '';

        // status select normalize and apply color
        var st = document.getElementById('editStatus');
        if (st) {
          var v = (bidObj.status || '').toString().trim().toLowerCase();
          if (v === "didnt bid" || v === "didntbid" || v === "didn'tbid" || v === "didnt") v = "didn't bid";
          if (!v) v = "pending";
          st.value = v;
          setStatusColor(v);
        }

        // fill other fields
        bidColumns.forEach(function(col){
          if (col === 'project_name') return;
          var input = modal.querySelector('input[data-col="' + col + '"]');
          if (input) input.value = (bidObj[col] !== undefined && bidObj[col] !== null) ? bidObj[col] : '';
        });

        modal.style.display = 'flex';
      }

      function closeEditModal() {
        var modal = document.getElementById('editBidModal');
        if (!modal) return;
        modal.style.display = 'none';
      }

      document.addEventListener('DOMContentLoaded', function(){
        var rows = document.querySelectorAll('table tbody tr[data-bid]');
        rows.forEach(function(r){
          r.addEventListener('click', function(){
            try {
              var bidObj = JSON.parse(r.getAttribute('data-bid'));
              openEditModal(bidObj);
            } catch(e) {
              console.error('Row JSON parse failed', e);
            }
          });
        });

        var closeBtn = document.getElementById('closeEditBid');
        if (closeBtn) closeBtn.addEventListener('click', closeEditModal);

        // update color when user changes selection
        var statusEl = document.getElementById('editStatus');
        if (statusEl) {
          statusEl.addEventListener('change', function(){ setStatusColor(this.value); });
        }

        var editForm = document.getElementById('editBidForm');
        if (editForm) {
          editForm.addEventListener('submit', function(e){
            e.preventDefault();

            var fd = new FormData(editForm);

            // Debug
            try {
              var obj = {};
              fd.forEach(function(v,k){ obj[k] = v; });
              console.log('Submitting update_bid:', obj);
            } catch(err) {}

            var saveBtn = document.getElementById('saveEditBid');
            if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving...'; }

            // Use the environment-safe `updateUrl` defined earlier instead of hardcoded path
            var theUpdateUrl = (typeof updateUrl !== 'undefined' && updateUrl) ? updateUrl : '../../api/update_bid.php';
            console.log('Final update URL used:', theUpdateUrl);

            fetch(theUpdateUrl, { method: 'POST', credentials: 'same-origin', body: fd })
              .then(function(r){
                console.log('HTTP status:', r.status);
                var ct = (r.headers.get('content-type') || '').toLowerCase();
                console.log('content-type:', ct);
                return r.text().then(function(text){
                  console.log('raw response (first 300 chars):', (text || '').slice(0,300));
                  // If server returned HTML (likely a login redirect), handle gracefully
                  if (ct.indexOf('text/html') !== -1 || String(text || '').trim().toLowerCase().indexOf('<!doctype html') === 0) {
                    console.warn('update_bid: received HTML response, likely redirect to login or server error');
                    // Show specific message and redirect to login so user can re-authenticate
                    try { showToast('Session expired - please sign in again', 'error'); } catch(e){ console.warn('showToast missing', e); }
                    setTimeout(function(){ window.location.href = '/auth/login.php'; }, 900);
                    throw new Error('Non-JSON HTML response');
                  }
                  try {
                    return JSON.parse(text);
                  } catch (e) {
                    console.error('JSON parse error:', e, 'raw:', (text||'').slice(0,300));
                    throw e;
                  }
                });
              })
              .then(function(data){
                console.log('update_bid response', data);
                if (data && data.success) {
                  console.log('update_bid: success — closing modal then showing toast');
                  try { closeEditModal(); } catch(e) { console.warn('closeEditModal error', e); }
                  try { showToast('Saved', 'success'); } catch(e) { console.warn('showToast error', e); }
                  setTimeout(function(){ window.location.reload(); }, 800);
                } else {
                  var msg = (data && data.message) ? data.message : 'Failed to save';
                  console.warn('update_bid: server returned failure', msg);
                  try { showToast(msg, 'error'); } catch(e) { console.warn('showToast error', e); }
                }
              })
              .catch(function(err){
                console.error('update_bid fetch error', err);
                showToast('Failed to save', 'error');
              })
              .finally(function(){
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
              });
          });
        }
      });
    })();
  </script>
</body>
</html>
