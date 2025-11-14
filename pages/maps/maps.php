<?php
require_once __DIR__ . '/../../config/session_init.php';

// Require login
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: ../../auth/login.php');
    exit();
}

// Include DB and permissions
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';

// Resolve user role and enforce access to maps
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();

if (!can_access($role, 'maps')) {
  header('Location: ../dashboard/');
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Services & Suppliers</title>
    <style>
      :root{
        --bg: #f4f6fb; /* page background */
        --card: #ffffff; /* panel / card background */
        --muted: #556174; /* muted text */
        --accent: #123e8a; /* primary action */
        --accent-2: #0b6fb2; /* secondary */
        --border: #e6eef4; /* subtle borders */
        --pill: #f1f6ff; /* copy pill */
        --success: #0f9d58;
        --danger: #c53030;
        --info: #0b6fb2;
      }
      html, body { height: 100%; }
      body {
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
        margin: 18px;
        background-color: var(--bg);
        color: #0f172a;
        -webkit-font-smoothing:antialiased;
        /* subtle dotted texture (no gradients) */
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='40' height='40'><circle cx='2' cy='2' r='1' fill='%23eef4fb' /></svg>");
        background-repeat: repeat;
      }
      h2 { color: var(--accent); margin-bottom: 6px; }
      .ribbon {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-bottom: 14px;
        flex-wrap: wrap;
      }
      .svc-btn {
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--card);
        cursor: pointer;
        font-weight: 600;
        color: #0f172a;
        transition: box-shadow .12s ease, transform .08s ease;
      }
      .svc-btn:hover{ transform: translateY(-1px); box-shadow: 0 8px 18px rgba(15,23,42,0.06); }
      .svc-btn.active { background-color: var(--accent); color: #fff; border-color: transparent; }
      #saveAllBtn.svc-btn{ background-color: var(--accent); color:#fff; border: none; }
      .panel { background: var(--card); border: 1px solid var(--border); padding: 14px; border-radius: 12px; }
      .panel:before{ content:''; display:block; height:6px; border-radius:8px; margin-bottom:12px; background-color: transparent; }
      table { width: 100%; border-collapse: collapse; font-size: 14px; }
      th, td { padding: 10px 12px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: middle; }
      th { background: var(--card); font-weight: 700; color: #071230; font-size: 13px; position: sticky; top: 0; z-index: 2; }
      tbody tr:hover{ background: #fff; }
      .meta { color: var(--muted); margin-bottom: 8px; }
      .loading { color: var(--muted); }
      .addr-text { color: #0f172a; display:inline-block; max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
      .coord-input { padding:6px 8px; border-radius:6px; border:1px solid var(--border); background:#fff; }
      .coord-input.invalid { border-color: var(--danger); box-shadow: 0 0 0 4px rgba(197,48,48,0.06); }
      .coord-error { color: var(--danger); font-size: 12px; margin-top:6px; display:none; }
      .coord-input:disabled { background:#f8fafc; color: #465769; }
      .copy-btn{ background: var(--pill); border:1px solid #d7e9ff; color: var(--accent-2); }
      .svc-btn.copy-btn{ padding:6px 8px; font-weight:600; }
      .svc-btn.save-btn{ background: #f8fafc; border:1px solid #dfeaf7; color: var(--accent); }
      .svc-btn.save-btn[disabled]{ opacity:0.6; cursor:not-allowed; }
      #toast{ position: fixed; right: 18px; bottom: 18px; min-width: 220px; max-width: 420px; padding: 10px 14px; border-radius: 10px; box-shadow: 0 8px 30px rgba(2,6,23,0.12); display:none; color:#fff; font-weight:600; z-index:9999; }
      #toast.success{ background-color: var(--success); }
      #toast.error{ background-color: var(--danger); }
      @media (max-width: 880px){
        .addr-text{ max-width:200px; }
        .coord-input{ width: 100px; }
      }
      /* Geocode stats pill */
      .geocode-stats{
        display:inline-block;
        background: linear-gradient(180deg, rgba(11,111,178,0.06), rgba(11,111,178,0.03));
        color: var(--accent-2);
        font-weight: 700;
        padding: 8px 14px;
        border-radius: 14px;
        font-size: 15px;
        border: 1px solid rgba(11,111,178,0.14);
        box-shadow: 0 8px 20px rgba(12,44,88,0.06);
        margin-bottom: 12px;
        transition: transform .18s cubic-bezier(.2,.8,.2,1), box-shadow .18s;
      }
      .geocode-stats.pulse{
        transform: translateY(-4px) scale(1.02);
        box-shadow: 0 14px 34px rgba(12,44,88,0.12);
      }
    </style>
  </head>
  <body>
    <h2>Services and Suppliers</h2>
    <div class="meta">
      Select a service to list all suppliers (full table view)
    </div>

    <div id="geocodeStats" class="geocode-stats" aria-live="polite">Geocoded: 0 of 0</div>

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
      <div class="ribbon" id="servicesRibbon">
        <div class="loading">Loading services…</div>
      </div>
      <div style="margin-left:auto;display:flex;gap:8px;align-items:center;">
        <button id="saveAllBtn" class="svc-btn" <?php if(!in_array($role, ['admin','developer','data_entry'])) echo 'disabled title="Admin, developer, or data_entry required to save"'; ?>>Save Changes</button>
        <button id="refreshBtn" class="svc-btn" title="Refresh now">Refresh</button>
      </div>
    </div>

    <div class="panel">
      <div id="tableMeta" style="margin-bottom: 10px">
        Select a service to view suppliers. <span id="lastUpdated" style="margin-left:12px;color:#94a3b8;font-size:12px"></span>
      </div>
      <div style="overflow: auto; max-height: 70vh">
        <table id="suppliersTable">
          <thead>
            <tr id="tableHeader">
              <!-- headers inserted dynamically -->
            </tr>
          </thead>
          <tbody id="tableBody">
            <!-- rows inserted dynamically -->
          </tbody>
        </table>
      </div>
    </div>
    <div id="toast" aria-hidden="true"></div>

    <script>
      (function () {
        var servicesRibbon = document.getElementById("servicesRibbon");
        var tableBody = document.getElementById("tableBody");
        var tableHeader = document.getElementById("tableHeader");
        var tableMeta = document.getElementById("tableMeta");
        var lastUpdatedEl = document.getElementById('lastUpdated');
        var refreshBtn = document.getElementById('refreshBtn');

        // Track currently selected service so periodic updates don't change the tab unexpectedly
        var activeService = null;
        var servicesPollInterval = 30000; // 30s
        var suppliersPollInterval = 30000; // 30s
        var servicesTimer = null;
        var suppliersTimer = null;
        // Whether current user can edit supplier coordinates (admins/developers)
        var userCanEdit = <?php echo (in_array($role, ['admin','developer','data_entry']) ? 'true' : 'false'); ?>;

        // Columns to display in the suppliers table (requested subset)
        var columns = [
          "name",
          "address",
          "city",
          "state",
          "copy",
          "coordinates"
        ];

        function renderHeader() {
          tableHeader.innerHTML = "";
          columns.forEach(function (c) {
            var th = document.createElement("th");
            th.textContent = c
              .replace(/_/g, " ")
              .replace(/\b\w/g, function (m) {
                return m.toUpperCase();
              });
            tableHeader.appendChild(th);
          });
          // Actions column for per-row save
          var act = document.createElement('th'); act.textContent = 'Actions'; tableHeader.appendChild(act);
        }

        function setLoadingServices() {
          servicesRibbon.innerHTML =
            '<div class="loading">Loading services…</div>';
        }

        function updateLastUpdated() {
          var d = new Date();
          if (lastUpdatedEl) lastUpdatedEl.textContent = 'Last updated: ' + d.toLocaleString();
        }

        function loadServices() {
          setLoadingServices();
          fetch("../../api/get_services.php", { credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                  // If there are no existing buttons, create them in order
                  if (!(data && data.success && Array.isArray(data.services))) {
                    servicesRibbon.innerHTML = '<div class="loading">No services available.</div>';
                    tableMeta.textContent = "No services found.";
                    return;
                  }

                  var services = data.services;

                  // Get current service buttons in DOM (preserve order)
                  var existingBtns = Array.from(servicesRibbon.querySelectorAll('.svc-btn'));
                  var existingNames = existingBtns.map(function(b){ return b.getAttribute('data-service'); });

                  // Remove buttons for services that no longer exist
                  existingBtns.forEach(function(b){
                    var sname = b.getAttribute('data-service');
                    if (services.indexOf(sname) === -1) {
                      b.remove();
                    }
                  });

                  // After removals, recompute existing names
                  existingBtns = Array.from(servicesRibbon.querySelectorAll('.svc-btn'));
                  existingNames = existingBtns.map(function(b){ return b.getAttribute('data-service'); });

                  // Append any new services to the right (preserve existing order)
                  services.forEach(function(s) {
                    if (existingNames.indexOf(s) === -1) {
                      var btn = document.createElement('button');
                      btn.className = 'svc-btn';
                      btn.textContent = s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, ' ');
                      btn.setAttribute('data-service', s);
                      btn.addEventListener('click', function() {
                        servicesRibbon.querySelectorAll('.svc-btn').forEach(function(b){ b.classList.remove('active'); });
                        btn.classList.add('active');
                        activeService = s;
                        loadSuppliersForService(s);
                      });
                      servicesRibbon.appendChild(btn);
                    }
                  });

                  // If activeService exists and still present, refresh it
                  if (activeService && services.indexOf(activeService) !== -1) {
                    loadSuppliersForService(activeService);
                  } else if (!activeService && services.length > 0) {
                    // No active service previously, default to first fetched
                    activeService = services[0];
                    var firstBtn = servicesRibbon.querySelector('.svc-btn');
                    if (firstBtn) firstBtn.classList.add('active');
                    loadSuppliersForService(activeService);
                  }

                  updateLastUpdated();
            })
            .catch(function (err) {
              servicesRibbon.innerHTML = '<div class="loading">Error loading services</div>';
              console.error(err);
              tableMeta.textContent = "Error loading services.";
            });
        }

        function loadSuppliersForService(service) {
          // set active service so periodic refresh knows what to refresh
          activeService = service;

          tableMeta.textContent = 'Loading suppliers for "' + service + '"…';
          tableBody.innerHTML = "";
          renderHeader();

          fetch("../../api/get_suppliers.php?service=" + encodeURIComponent(service), { credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (!data || !data.success) {
                tableMeta.textContent = "Failed to load suppliers.";
                return;
              }
              var suppliers = data.suppliers || [];
              // update geocode stats for this service
              try { updateGeocodeStats(suppliers); } catch(e) { console.error(e); }
              tableMeta.textContent = suppliers.length + " supplier" + (suppliers.length === 1 ? "" : "s") + ' for "' + service + '".';
                if (suppliers.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="' + (columns.length + 1) + '">No suppliers for this service.</td></tr>';
                updateLastUpdated();
                return;
              }

              var frag = document.createDocumentFragment();
              suppliers.forEach(function (sup) {
                var tr = document.createElement('tr');
                if (sup.id) tr.setAttribute('data-id', sup.id);

                // Build columns
                columns.forEach(function (col) {
                  var td = document.createElement('td');
                  var val = (sup[col] === null || typeof sup[col] === 'undefined') ? '' : sup[col];

                  if (col === 'address') {
                    var span = document.createElement('span');
                    span.className = 'addr-text';
                    span.textContent = String(val);
                    span.style.cursor = 'pointer';
                    span.title = 'Click to copy address';
                    span.addEventListener('click', function () {
                      var text = span.textContent || '';
                      if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function () {
                          span.style.background = '#e6fffa';
                          setTimeout(function () { span.style.background = ''; }, 500);
                        }, function () { alert('Copy failed'); });
                      } else {
                        var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select();
                        try { document.execCommand('copy'); span.style.background = '#e6fffa'; setTimeout(function () { span.style.background = ''; }, 500); } catch (e) { alert('Copy not supported'); }
                        document.body.removeChild(ta);
                      }
                    });
                    td.appendChild(span);
                  } else if (col === 'copy') {
                    var btn = document.createElement('button');
                    btn.className = 'svc-btn copy-btn';
                    btn.textContent = 'Copy';
                    btn.title = 'Copy address, city, state';
                    btn.addEventListener('click', function () {
                      var formatted = [sup.address || '', sup.city || '', sup.state || ''].filter(function (x) { return x && String(x).trim() !== ''; }).join(', ');
                      if (!formatted) { alert('No address available to copy'); return; }
                      if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(formatted).then(function () { var old = btn.textContent; btn.textContent = 'Copied'; setTimeout(function () { btn.textContent = old; }, 900); }, function () { alert('Copy failed'); });
                      } else {
                        var ta = document.createElement('textarea'); ta.value = formatted; document.body.appendChild(ta); ta.select();
                        try { document.execCommand('copy'); var old = btn.textContent; btn.textContent = 'Copied'; setTimeout(function () { btn.textContent = old; }, 900); } catch (e) { alert('Copy not supported'); }
                        document.body.removeChild(ta);
                      }
                    });
                    td.appendChild(btn);
                  } else if (col === 'coordinates') {
                    var input = document.createElement('input');
                    input.type = 'text';
                    // build combined coords if both lat & lng exist
                    var latValTmp = (sup.latitude !== null && typeof sup.latitude !== 'undefined') ? String(sup.latitude).trim() : '';
                    var lngValTmp = (sup.longitude !== null && typeof sup.longitude !== 'undefined') ? String(sup.longitude).trim() : '';
                    input.value = (latValTmp !== '' && lngValTmp !== '') ? (latValTmp + ', ' + lngValTmp) : '';
                    input.className = 'coord-input coords-input';
                    input.style.width = '240px';
                    input.placeholder = 'lat, lng';
                    input.readOnly = false;
                    input.disabled = false;
                    td.appendChild(input);
                    var err = document.createElement('div'); err.className = 'coord-error'; err.setAttribute('aria-live','polite'); td.appendChild(err);
                  } else {
                    td.textContent = String(val);
                  }

                  tr.appendChild(td);
                });

                // Actions column: per-row Save button
                var actionTd = document.createElement('td');
                var perSave = document.createElement('button');
                perSave.className = 'svc-btn save-btn';
                perSave.textContent = 'Save';
                perSave.dataset.state = 'save';
                perSave.style.minWidth = '68px';
                if (!userCanEdit) { perSave.disabled = true; perSave.title = 'Admin, developer, or data_entry required to save'; }
                perSave.addEventListener('click', function(){
                  var row = this.closest('tr');
                  var sid = row.getAttribute('data-id');
                  var coordsInput = row.querySelector('.coords-input');
                  var coordsVal = coordsInput ? coordsInput.value.trim() : '';

                  // If currently in 'edit' state, switch to editable mode
                  if (perSave.dataset.state === 'edit') {
                    if (coordsInput) { coordsInput.disabled = false; coordsInput.readOnly = false; }
                    perSave.dataset.state = 'save';
                    perSave.textContent = 'Save';
                    return;
                  }

                  if (!sid) { showToast('Missing supplier id', false); return; }
                  // clear prior field error for this input
                  clearFieldError(coordsInput);
                  if (!coordsVal) { setFieldError(coordsInput, 'Enter coordinates as "lat, lng" (e.g. 41.02, -80.74)'); return; }
                  var parsed = parseCoords(coordsVal);
                  if (!parsed) { setFieldError(coordsInput, 'Invalid coordinates — enter as "lat, lng"'); return; }
                  var latVal = String(parsed.lat);
                  var lngVal = String(parsed.lng);
                  perSave.disabled = true; var old = perSave.textContent; perSave.textContent = 'Saving...';
                  var form = new FormData(); form.append('id', sid); form.append('latitude', latVal); form.append('longitude', lngVal);
                  fetch('../../api/update_supplier_coordinates.php', { method: 'POST', body: form, credentials: 'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(resp){
                      if (resp && resp.success) {
                        // lock coords input and switch to Edit state
                        if (coordsInput) { coordsInput.disabled = true; coordsInput.readOnly = true; }
                        clearFieldError(coordsInput);
                        perSave.dataset.state = 'edit';
                        perSave.textContent = 'Edit';
                        perSave.disabled = false;
                        showToast('Saved', true);
                      } else {
                        showToast((resp && resp.message) || 'Failed to save', false); perSave.disabled = false; perSave.textContent = old;
                      }
                    })
                    .catch(function(err){ console.error(err); alert('Failed to save'); perSave.disabled = false; perSave.textContent = old; });
                });
                // Determine initial state: if supplier already has latitude and longitude, render coords input as read-only and show Edit
                try {
                  var initialLat = (sup.latitude !== null && typeof sup.latitude !== 'undefined') ? String(sup.latitude).trim() : '';
                  var initialLng = (sup.longitude !== null && typeof sup.longitude !== 'undefined') ? String(sup.longitude).trim() : '';
                  var coordsInputEl = tr.querySelector('.coords-input');
                  if (initialLat !== '' && initialLng !== '') {
                    if (coordsInputEl) { coordsInputEl.disabled = true; coordsInputEl.readOnly = true; }
                    perSave.dataset.state = 'edit';
                    perSave.textContent = 'Edit';
                  } else {
                    if (coordsInputEl) { coordsInputEl.disabled = false; coordsInputEl.readOnly = false; }
                    perSave.dataset.state = 'save';
                    perSave.textContent = 'Save';
                  }
                } catch (e) { /* ignore */ }

                actionTd.appendChild(perSave);
                tr.appendChild(actionTd);
                frag.appendChild(tr);
              });
              tableBody.appendChild(frag);
              // update geocode stats again after rendering
              try { updateGeocodeStats(suppliers); } catch(e) { console.error(e); }
              updateLastUpdated();
            })
            .catch(function (err) {
              console.error(err);
              tableMeta.textContent = "Error loading suppliers.";
              tableBody.innerHTML = '<tr><td colspan="' + (columns.length + 1) + '">Error loading suppliers.</td></tr>';
            });
        }

        // wire refresh button
        if (refreshBtn) {
          refreshBtn.addEventListener('click', function(){
            // immediate refresh
            loadServices();
            if (activeService) loadSuppliersForService(activeService);
          });
        }

        // wire save-all button
        var saveAllBtn = document.getElementById('saveAllBtn');
          if (saveAllBtn) {
          if (!userCanEdit) { saveAllBtn.disabled = true; saveAllBtn.title = 'Admin, developer, or data_entry required to save'; }
            saveAllBtn.addEventListener('click', function(){
            if (!activeService) { alert('No service selected'); return; }
            var rows = Array.from(tableBody.querySelectorAll('tr[data-id]'));
            if (rows.length === 0) { alert('No suppliers to save'); return; }
            // Only include rows where the coords field is non-empty (user intends to save)
            // Clear any prior inline validation UI
            clearAllFieldErrors();
            var candidates = rows.map(function(row){
              var id = row.getAttribute('data-id');
              var coords = row.querySelector('.coords-input') ? row.querySelector('.coords-input').value.trim() : '';
              var lat = '';
              var lng = '';
              if (coords) {
                var p = parseCoords(coords);
                if (p) { lat = String(p.lat); lng = String(p.lng); }
              }
              return { id: id, latitude: lat, longitude: lng, row: row };
            }).filter(function(u){ return u.id && (u.latitude !== '' || u.longitude !== ''); });

            if (candidates.length === 0) { showToast('No changed coordinates to save. Edit coordinates for rows you want to update.', false); return; }

            // Validate candidates: require both lat and lng to be present and numeric
            var invalid = candidates.find(function(u){ return u.latitude === '' || u.longitude === '' || isNaN(Number(u.latitude)) || isNaN(Number(u.longitude)); });
            if (invalid) {
              var inp = invalid.row.querySelector('.coords-input');
              setFieldError(inp, 'Please provide numeric latitude and longitude (lat, lng)');
              showToast('Please correct highlighted rows before saving.', false);
              return;
            }

            (async function(){
              saveAllBtn.disabled = true; var oldText = saveAllBtn.textContent; saveAllBtn.textContent = 'Saving...';
              var anyFailed = false;
              for (var i=0;i<candidates.length;i++){
                var u = candidates[i];
                var form = new FormData(); form.append('id', u.id); form.append('latitude', u.latitude); form.append('longitude', u.longitude);
                try {
                  var res = await fetch('../../api/update_supplier_coordinates.php', { method: 'POST', body: form, credentials: 'same-origin' });
                  var json = await res.json();
                  if (!(json && json.success)) { anyFailed = true; console.error('Failed update', u.id, json); }
                } catch (e) { anyFailed = true; console.error(e); }
              }
              if (!anyFailed) {
                saveAllBtn.textContent = 'Saved';
                showToast('All coordinates saved', true);
                setTimeout(function(){ saveAllBtn.textContent = oldText; }, 900);
              } else {
                showToast('One or more updates failed', false);
                saveAllBtn.textContent = oldText;
              }
              saveAllBtn.disabled = !userCanEdit;
              if (activeService) loadSuppliersForService(activeService);
            })();
          });
        }

        // small toast helper
        function showToast(msg, success){
          try{
            var t = document.getElementById('toast');
            if(!t) return;
            t.textContent = msg || '';
            t.className = success ? 'success' : 'error';
            t.style.display = 'block';
            t.setAttribute('aria-hidden','false');
            setTimeout(function(){ t.style.opacity = '1'; }, 20);
            setTimeout(function(){ t.style.opacity = '0'; setTimeout(function(){ t.style.display='none'; t.setAttribute('aria-hidden','true'); },300); }, 2500);
          }catch(e){ console.error(e); }
        }

        // Field-level inline validation helpers
        function setFieldError(input, msg) {
          try {
            if (!input) return;
            input.classList.add('invalid');
            var err = input.closest('td') ? input.closest('td').querySelector('.coord-error') : null;
            if (err) { err.textContent = msg || ''; err.style.display = 'block'; }
            input.focus();
          } catch (e) { console.error(e); }
        }

        function clearFieldError(input) {
          try {
            if (!input) return;
            input.classList.remove('invalid');
            var err = input.closest('td') ? input.closest('td').querySelector('.coord-error') : null;
            if (err) { err.textContent = ''; err.style.display = 'none'; }
          } catch (e) { console.error(e); }
        }

        function clearAllFieldErrors() {
          try {
            Array.from(document.querySelectorAll('.coord-error')).forEach(function(e){ e.textContent=''; e.style.display='none'; });
            Array.from(document.querySelectorAll('.coords-input.invalid')).forEach(function(i){ i.classList.remove('invalid'); });
          } catch (e) { console.error(e); }
        }

        // Update geocode statistics display (geocoded vs total)
        function updateGeocodeStats(suppliers) {
          var el = document.getElementById('geocodeStats');
          if (!el) return;
          if (!Array.isArray(suppliers)) {
            el.textContent = 'Geocoded: 0 of 0';
            return;
          }
          var total = suppliers.length;
          var geocoded = suppliers.filter(function(s){
            var lat = (s.latitude !== null && typeof s.latitude !== 'undefined') ? String(s.latitude).trim() : '';
            var lng = (s.longitude !== null && typeof s.longitude !== 'undefined') ? String(s.longitude).trim() : '';
            return lat !== '' && lng !== '' && !isNaN(Number(lat)) && !isNaN(Number(lng));
          }).length;
          el.textContent = 'Geocoded: ' + geocoded + ' of ' + total;
          // pulse animation to draw attention when counts update
          try {
            el.classList.remove('pulse');
            // force reflow
            void el.offsetWidth;
            el.classList.add('pulse');
            setTimeout(function(){ el.classList.remove('pulse'); }, 700);
          } catch(e) { /* ignore */ }
        }

        // Parse a combined coordinate string like "41.0230, -80.7498" (allow variations)
        function parseCoords(input) {
          if (!input || typeof input !== 'string') return null;
          // remove enclosing parentheses and trim
          var s = input.trim().replace(/^\(+|\)+$/g, '').trim();
          // split by comma
          var parts = s.split(',');
          if (parts.length < 2) return null;
          var a = parts[0].trim();
          var b = parts[1].trim();
          // Accept if numeric
          var lat = Number(a);
          var lng = Number(b);
          if (isNaN(lat) || isNaN(lng)) return null;
          return { lat: lat, lng: lng };
        }

        // start periodic polling
        servicesTimer = setInterval(function(){ loadServices(); }, servicesPollInterval);
        suppliersTimer = setInterval(function(){ if (activeService) loadSuppliersForService(activeService); }, suppliersPollInterval);

        // initial render
        renderHeader();
        loadServices();
      })();
    </script>
  </body>
</html>
