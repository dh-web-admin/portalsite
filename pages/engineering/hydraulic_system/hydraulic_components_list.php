<?php
// All Components — parts/spec list for the Hydraulic Systems diagram.
// Reachable via the "View all components" button on hydraulic_systems.php;
// not a hub-level section (not in _sections.php), and lives alongside its
// sibling hydraulic-system pages in this subfolder, so it builds its own
// chrome instead of going through the parent folder's shared partials.
require_once __DIR__ . '/../../../session_init.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/config.php';

$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();

require_once __DIR__ . '/../../../partials/permissions.php';
$hasEditPermission = can_edit_page('engineering');

$components = [];
$tableMissing = false;
$check = $conn->query("SHOW TABLES LIKE 'hydraulic_component_specs'");
if ($check && $check->num_rows > 0) {
    // Natural hierarchy on `number`: whole number, then its letter-suffixed
    // callouts, then its decimal sub-parts, before moving to the next whole
    // number — e.g. 1, 1a, 1b, 1.1, 1.2, 2, 2a, 2b, ...
    $result = $conn->query(
        "SELECT * FROM hydraulic_component_specs
         ORDER BY
             CAST(REGEXP_SUBSTR(number, '^[0-9]+') AS UNSIGNED) ASC,
             CASE
                 WHEN number REGEXP '^[0-9]+$'   THEN 0
                 WHEN number REGEXP '^[0-9]+[.]' THEN 2
                 ELSE 1
             END ASC,
             CASE
                 WHEN number REGEXP '^[0-9]+[.]'
                     THEN CAST(REGEXP_SUBSTR(number, '[0-9]+$') AS UNSIGNED)
                 ELSE 0
             END ASC,
             number ASC"
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $components[] = $row;
        }
    }
} else {
    $tableMissing = true;
}

function hc_fmt($v)
{
    return $v === null ? '' : htmlspecialchars((string) $v);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
  <meta name="theme-color" content="#667eea" />
  <title>All Components — Hydraulic Systems</title>
  <link rel="stylesheet" href="../../../assets/css/base.css" />
  <link rel="stylesheet" href="../../../assets/css/admin-layout.css" />
  <link rel="stylesheet" href="../../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../style.css?v=<?php echo @filemtime(__DIR__ . '/../style.css'); ?>" />
  <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css'); ?>" />
</head>
<body class="admin-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../../../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../../../partials/sidebar.php'; ?>
      <main class="content-area">
        <div class="main-content">
          <div class="eng-topbar">
            <a href="hydraulic_systems.php" class="eng-back-btn">&larr; Back</a>
            <nav class="eng-breadcrumb">
              <a href="../index.php">Engineering</a>
              <span aria-hidden="true">/</span>
              <a href="hydraulic_systems.php">Hydraulic Systems</a>
              <span aria-hidden="true">/</span>
              <span class="current">All Components</span>
            </nav>
          </div>

          <?php if ($tableMissing): ?>
            <p class="eng-panel-placeholder">
              The <code>hydraulic_component_specs</code> table doesn't exist yet — run
              <code>migrations/20260910_hydraulic_component_specs.sql</code>, then add rows.
            </p>
          <?php endif; ?>

          <div class="hc-list-wrap">
            <table class="hc-list-table">
              <thead>
                <tr>
                  <th>Number</th>
                  <th>Part No</th>
                  <th>Description</th>
                  <th class="hc-col-mfrdesc">Manufacturer Description</th>
                  <th>Material</th>
                  <th>QTY</th>
                  <th>Length</th>
                  <th>ID (inches)</th>
                  <th>OD (inches)</th>
                  <th>Wall Thickness</th>
                  <th>Pressure rating (PSI)</th>
                  <th>Burst Pressure (PSI)</th>
                  <th>Manufacturer</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($hasEditPermission): ?>
                  <tr class="hc-add-trigger-row">
                    <td colspan="13">
                      <button type="button" id="hcAddToggleBtn" class="hc-add-toggle-btn" aria-expanded="false">+ Add Component</button>
                    </td>
                  </tr>
                  <tr class="hc-add-input-row" id="hcAddInputRow" hidden>
                    <td><input type="text" id="hcf_number" placeholder="e.g. 1a" required></td>
                    <td><input type="text" id="hcf_part_no"></td>
                    <td><input type="text" id="hcf_description"></td>
                    <td><input type="text" id="hcf_manufacturer_description"></td>
                    <td><input type="text" id="hcf_material"></td>
                    <td><input type="number" id="hcf_qty" step="1" min="0" value="1"></td>
                    <td><input type="number" id="hcf_length" step="any"></td>
                    <td><input type="number" id="hcf_id_inches" step="any"></td>
                    <td><input type="number" id="hcf_od_inches" step="any"></td>
                    <td><input type="number" id="hcf_wall_thickness" step="any"></td>
                    <td><input type="number" id="hcf_pressure_rating_psi" step="1"></td>
                    <td><input type="number" id="hcf_burst_pressure_psi" step="1"></td>
                    <td>
                      <input type="text" id="hcf_manufacturer">
                      <div class="hc-add-actions">
                        <button type="button" id="hcAddSaveBtn" class="hc-add-save-btn">Save</button>
                        <button type="button" id="hcAddCancelBtn" class="hc-add-cancel-btn">Cancel</button>
                      </div>
                    </td>
                  </tr>
                  <tr class="hc-add-error-row" id="hcAddErrorRow" hidden>
                    <td colspan="13" class="hc-add-error" id="hcAddError"></td>
                  </tr>
                <?php endif; ?>
                <?php if (empty($components)): ?>
                  <tr id="hcEmptyRow"><td colspan="13" class="hc-list-empty">No components added yet.</td></tr>
                <?php else: foreach ($components as $c): ?>
                  <tr class="hc-data-row" data-spec="<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES); ?>" data-number="<?php echo hc_fmt($c['number']); ?>" tabindex="0" role="button" aria-label="Open details for component <?php echo hc_fmt($c['number']); ?>">
                    <td><?php echo hc_fmt($c['number']); ?></td>
                    <td><?php echo hc_fmt($c['part_no']); ?></td>
                    <td><?php echo hc_fmt($c['description']); ?></td>
                    <td class="hc-col-mfrdesc"><span class="hc-clamp-2"><?php echo hc_fmt($c['manufacturer_description']); ?></span></td>
                    <td><?php echo hc_fmt($c['material']); ?></td>
                    <td><?php echo hc_fmt($c['qty']); ?></td>
                    <td><?php echo hc_fmt($c['length']); ?></td>
                    <td><?php echo hc_fmt($c['id_inches']); ?></td>
                    <td><?php echo hc_fmt($c['od_inches']); ?></td>
                    <td><?php echo hc_fmt($c['wall_thickness']); ?></td>
                    <td><?php echo hc_fmt($c['pressure_rating_psi']); ?></td>
                    <td><?php echo hc_fmt($c['burst_pressure_psi']); ?></td>
                    <td><?php echo hc_fmt($c['manufacturer']); ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

          <div class="hc-modal-overlay" id="hcModalOverlay" hidden>
            <div class="hc-modal" role="dialog" aria-modal="true" aria-labelledby="hcModalTitle">
              <div class="hc-modal-header">
                <h2 class="hc-modal-title" id="hcModalTitle">Component <span id="hcModalNumber"></span></h2>
                <button type="button" class="hc-modal-close" id="hcModalCloseBtn" aria-label="Close">&times;</button>
              </div>
              <div class="hc-modal-body">
                <div class="hc-modal-grid">
                  <label>Number
                    <input type="text" id="hcm_number" <?php echo $hasEditPermission ? '' : 'readonly'; ?>>
                  </label>
                  <label>Part No
                    <input type="text" id="hcm_part_no" <?php echo $hasEditPermission ? '' : 'readonly'; ?>>
                  </label>
                  <label>Material
                    <input type="text" id="hcm_material" <?php echo $hasEditPermission ? '' : 'readonly'; ?>>
                  </label>
                  <label>Manufacturer
                    <input type="text" id="hcm_manufacturer" <?php echo $hasEditPermission ? '' : 'readonly'; ?>>
                  </label>
                  <label>QTY
                    <input type="number" step="1" min="0" id="hcm_qty" <?php echo $hasEditPermission ? '' : 'readonly'; ?>>
                  </label>
                  <label>Length
                    <input type="number" step="any" id="hcm_length" <?php echo $hasEditPermission ? '' : 'readonly'; ?>>
                  </label>
                  <label>ID (inches)
                    <input type="number" step="any" id="hcm_id_inches" <?php echo $hasEditPermission ? '' : 'readonly'; ?>>
                  </label>
                  <label>OD (inches)
                    <input type="number" step="any" id="hcm_od_inches" <?php echo $hasEditPermission ? '' : 'readonly'; ?>>
                  </label>
                  <label>Wall Thickness
                    <input type="number" step="any" id="hcm_wall_thickness" <?php echo $hasEditPermission ? '' : 'readonly'; ?>>
                  </label>
                  <label>Pressure Rating (PSI)
                    <input type="number" step="1" id="hcm_pressure_rating_psi" <?php echo $hasEditPermission ? '' : 'readonly'; ?>>
                  </label>
                  <label>Burst Pressure (PSI)
                    <input type="number" step="1" id="hcm_burst_pressure_psi" <?php echo $hasEditPermission ? '' : 'readonly'; ?>>
                  </label>
                  <label class="hc-modal-full">Description
                    <textarea id="hcm_description" rows="2" <?php echo $hasEditPermission ? '' : 'readonly'; ?>></textarea>
                  </label>
                  <label class="hc-modal-full">Manufacturer Description
                    <textarea id="hcm_manufacturer_description" rows="4" <?php echo $hasEditPermission ? '' : 'readonly'; ?>></textarea>
                  </label>
                </div>
                <p class="hc-add-error" id="hcModalError" hidden></p>
              </div>
              <div class="hc-modal-footer">
                <?php if ($hasEditPermission): ?>
                  <button type="button" class="hc-add-save-btn" id="hcModalSaveBtn">Save</button>
                <?php endif; ?>
                <button type="button" class="hc-add-cancel-btn" id="hcModalCancelBtn">Close</button>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script>
    (function () {
      var pairs = [['usersToggle', 'usersGroup'], ['devToggle', 'devGroup'], ['maintenanceToggle', 'maintenanceGroup']];
      pairs.forEach(function (p) {
        var btn = document.getElementById(p[0]);
        var grp = document.getElementById(p[1]);
        if (btn && grp) {
          btn.addEventListener('click', function () { grp.classList.toggle('open'); });
        }
      });
    })();
  </script>
  <script>
    (function () {
      var toggleBtn = document.getElementById('hcAddToggleBtn');
      var inputRow  = document.getElementById('hcAddInputRow');
      var errorRow  = document.getElementById('hcAddErrorRow');
      var errorCell = document.getElementById('hcAddError');
      var saveBtn   = document.getElementById('hcAddSaveBtn');
      var cancelBtn = document.getElementById('hcAddCancelBtn');
      if (!toggleBtn || !inputRow) { return; }

      var fields = [
        'number', 'part_no', 'description', 'manufacturer_description', 'material',
        'qty', 'length', 'id_inches', 'od_inches', 'wall_thickness',
        'pressure_rating_psi', 'burst_pressure_psi', 'manufacturer'
      ];

      function showError(msg) {
        errorCell.textContent = msg;
        errorRow.hidden = false;
      }
      function clearError() {
        errorRow.hidden = true;
        errorCell.textContent = '';
      }
      function resetFields() {
        fields.forEach(function (f) {
          var el = document.getElementById('hcf_' + f);
          if (el) { el.value = (f === 'qty') ? '1' : ''; }
        });
      }
      function open() {
        inputRow.hidden = false;
        toggleBtn.setAttribute('aria-expanded', 'true');
        var first = document.getElementById('hcf_number');
        if (first) { first.focus(); }
      }
      function close() {
        inputRow.hidden = true;
        clearError();
        resetFields();
        toggleBtn.setAttribute('aria-expanded', 'false');
      }

      toggleBtn.addEventListener('click', function () {
        if (inputRow.hidden) { open(); } else { close(); }
      });
      if (cancelBtn) { cancelBtn.addEventListener('click', close); }

      if (saveBtn) {
        saveBtn.addEventListener('click', function () {
          var numberEl = document.getElementById('hcf_number');
          if (!numberEl.value.trim()) {
            showError('Number is required.');
            numberEl.focus();
            return;
          }
          clearError();
          saveBtn.disabled = true;
          saveBtn.textContent = 'Saving…';

          var body = new URLSearchParams();
          fields.forEach(function (f) {
            var el = document.getElementById('hcf_' + f);
            if (el) { body.append(f, el.value); }
          });

          fetch('../../../api/add_hydraulic_component_spec.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
          })
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
            .then(function (res) {
              if (res.ok && res.data && res.data.success) {
                window.location.reload();
              } else {
                showError((res.data && res.data.message) || 'Failed to add component.');
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
              }
            })
            .catch(function () {
              showError('Network error — please try again.');
              saveBtn.disabled = false;
              saveBtn.textContent = 'Save';
            });
        });
      }
    })();
  </script>
  <script>
    (function () {
      var overlay      = document.getElementById('hcModalOverlay');
      var closeBtn      = document.getElementById('hcModalCloseBtn');
      var cancelBtn     = document.getElementById('hcModalCancelBtn');
      var saveBtn       = document.getElementById('hcModalSaveBtn');
      var errorEl       = document.getElementById('hcModalError');
      var titleNumberEl = document.getElementById('hcModalNumber');
      if (!overlay) { return; }

      var fields = [
        'number', 'part_no', 'description', 'manufacturer_description', 'material',
        'qty', 'length', 'id_inches', 'od_inches', 'wall_thickness',
        'pressure_rating_psi', 'burst_pressure_psi', 'manufacturer'
      ];
      var currentId = null;

      function openModal(spec) {
        currentId = spec.id;
        titleNumberEl.textContent = spec.number || '';
        fields.forEach(function (f) {
          var el = document.getElementById('hcm_' + f);
          if (el) { el.value = (spec[f] === null || spec[f] === undefined) ? '' : spec[f]; }
        });
        errorEl.hidden = true;
        errorEl.textContent = '';
        overlay.hidden = false;
      }
      function closeModal() {
        overlay.hidden = true;
        currentId = null;
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
      }

      document.querySelectorAll('.hc-data-row').forEach(function (row) {
        function trigger() {
          var raw = row.getAttribute('data-spec');
          if (!raw) { return; }
          try { openModal(JSON.parse(raw)); } catch (e) { /* malformed data, ignore */ }
        }
        row.addEventListener('click', trigger);
        row.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); trigger(); }
        });
      });

      if (closeBtn)  { closeBtn.addEventListener('click', closeModal); }
      if (cancelBtn) { cancelBtn.addEventListener('click', closeModal); }
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) { closeModal(); }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hidden) { closeModal(); }
      });

      if (saveBtn) {
        saveBtn.addEventListener('click', function () {
          var numberEl = document.getElementById('hcm_number');
          if (!numberEl.value.trim()) {
            errorEl.textContent = 'Number is required.';
            errorEl.hidden = false;
            numberEl.focus();
            return;
          }
          errorEl.hidden = true;
          saveBtn.disabled = true;
          saveBtn.textContent = 'Saving…';

          var body = new URLSearchParams();
          body.append('id', currentId);
          fields.forEach(function (f) {
            var el = document.getElementById('hcm_' + f);
            if (el) { body.append(f, el.value); }
          });

          fetch('../../../api/update_hydraulic_component_spec.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
          })
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
            .then(function (res) {
              if (res.ok && res.data && res.data.success) {
                window.location.reload();
              } else {
                errorEl.textContent = (res.data && res.data.message) || 'Failed to save changes.';
                errorEl.hidden = false;
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
              }
            })
            .catch(function () {
              errorEl.textContent = 'Network error — please try again.';
              errorEl.hidden = false;
              saveBtn.disabled = false;
              saveBtn.textContent = 'Save';
            });
        });
      }
    })();
  </script>
  <script>
    // Deep link from the diagram's side panel (?highlight=<number>) —
    // scroll straight to that part's row(s) and flash them briefly so
    // there's no hunting through the full list.
    (function () {
      var number = new URLSearchParams(window.location.search).get('highlight');
      if (!number) { return; }
      var rows = Array.prototype.slice.call(document.querySelectorAll('.hc-data-row[data-number="' + CSS.escape(number) + '"]'));
      if (!rows.length) { return; }
      rows[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      rows.forEach(function (row) { row.classList.add('hc-row-highlight'); });
      setTimeout(function () {
        rows.forEach(function (row) { row.classList.remove('hc-row-highlight'); });
      }, 2600);
    })();
  </script>
  <script src="../../../assets/js/mobile-menu.js"></script>
  <script src="../../../assets/js/logout-confirm.js"></script>
</body>
</html>
