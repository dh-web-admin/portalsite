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
$actualRole = $user ? $user['role'] : 'laborer';
$role = $actualRole;
$stmt->close();

// Enforce access control for this page
if (!can_access($role, 'employee_information')) {
  header('Location: /pages/dashboard/');
  exit();
}

// Fetch list of employees for left column
$employees = [];
// Detect whether first_name/last_name columns exist (older schemas may not)
$hasFirst = false;
$hasLast = false;
$c1 = $conn->query("SHOW COLUMNS FROM users LIKE 'first_name'");
if ($c1 && $c1->num_rows > 0) $hasFirst = true;
$c2 = $conn->query("SHOW COLUMNS FROM users LIKE 'last_name'");
if ($c2 && $c2->num_rows > 0) $hasLast = true;
// detect legacy profile_image column
$hasProfileImage = false;
$c3 = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
if ($c3 && $c3->num_rows > 0) $hasProfileImage = true;

$select = 'SELECT u.id, u.email, u.role, COALESCE(u.name, "") AS name';
if ($hasProfileImage) {
  $select .= ', COALESCE(ud.profile_picture, u.profile_image) AS picture';
} else {
  $select .= ', ud.profile_picture AS picture';
}
if ($hasFirst) {
  $select .= ', u.first_name';
} else {
  $select .= ", '' AS first_name";
}
if ($hasLast) {
  $select .= ', u.last_name';
} else {
  $select .= ", '' AS last_name";
}

$select .= ' FROM users u LEFT JOIN user_details ud ON ud.user_id = u.id';
if ($hasLast || $hasFirst) {
  $select .= ' ORDER BY u.last_name ASC, u.first_name ASC';
} else {
  $select .= ' ORDER BY u.email ASC';
}

$q = $conn->prepare($select);
if ($q) {
  $q->execute();
  $res = $q->get_result();
  while ($row = $res->fetch_assoc()) {
    $employees[] = $row;
  }
  $q->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Employee Information</title>
  <link rel="stylesheet" href="../../assets/css/base.css" />
  <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="style.css" />
  <!-- reuse scheduling styles for the left resource sidebar layout -->
  <link rel="stylesheet" href="../scheduling/style.css" />
  <style>
    /* highlight selected employee in sidebar */
    .resource-card.active{ background: #f1f5ff; border-left: 4px solid #7c3aed; }
    .resource-card{ transition: background .15s ease, border-left .15s ease; }
    /* make selected name in header more visible */
    #selectedEmployee { margin-left: 14px; color: #475569; font-size: 15px; font-weight:600; }
  </style>
</head>
<body class="admin-page scheduling-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
      <main class="content-area scheduling-page">
        <div class="main-content scheduling-page">
          <section class="scheduling-shell">
            <aside class="resource-sidebar" aria-label="Employees list">
              <div class="resource-group">
                <div class="resource-section-title">Employees</div>
                <div class="resource-list" id="empList">
                  <?php if (!empty($employees)): ?>
                    <?php foreach ($employees as $emp):
                        // prefer `name` column if present; fall back to first/last, then email
                        $display = '';
                        if (!empty($emp['name'])) {
                          $display = $emp['name'];
                        } else {
                          $display = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?: $emp['email'];
                        }
                      $initials = '';
                      $parts = preg_split('/\s+/', trim($display));
                      if (!empty($parts[0])) $initials .= strtoupper(substr($parts[0],0,1));
                      if (!empty($parts[1])) $initials .= strtoupper(substr($parts[1],0,1));
                      if ($initials === '' && $display !== '') $initials = strtoupper(substr($display,0,2));
                      $picture = !empty($emp['picture']) ? htmlspecialchars($emp['picture']) : '';
                    ?>
                      <div class="resource-card" data-user-id="<?php echo (int)$emp['id']; ?>">
                        <div class="resource-avatar">
                          <?php if ($picture): ?>
                            <img src="<?php echo $picture; ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%" />
                          <?php else: ?>
                            <?php echo htmlspecialchars($initials); ?>
                          <?php endif; ?>
                        </div>
                        <div class="resource-texts">
                          <div class="resource-name"><a class="emp-open-link" href="index.php?user_id=<?php echo (int)$emp['id']; ?>" target="_blank" style="color:inherit;text-decoration:none;"><?php echo htmlspecialchars($display); ?></a></div>
                          <div class="resource-sub"><?php echo htmlspecialchars(ucfirst($emp['role'] ?: 'user')); ?></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="resource-empty">No employees found</div>
                  <?php endif; ?>
                </div>
              </div>
            </aside>

            <div class="employee-details" style="min-width:60%;padding:18px;">
              <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                <h2 style="margin:0;font-size:18px;color:#0f172a;">Employee Details</h2>
                <div id="selectedEmployee"></div>
              </div>

              <div id="employeeFields" style="display:grid;grid-template-columns:1fr;gap:18px;">
                <!-- Sections will be inserted here -->
              </div>

              <!-- Hidden datalists for autocomplete (can be populated server-side or by an API later) -->
              <datalist id="datalist-operating-skills"></datalist>
              <datalist id="datalist-life-skills"></datalist>
              <datalist id="datalist-certifications"></datalist>
              <datalist id="datalist-background"></datalist>
            </div>
          </section>
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

      // employee list selection behavior
      var empList = document.getElementById('empList');
      if (empList) {
        empList.addEventListener('click', function(ev){
          var btn = ev.target.closest('.resource-card');
          if (!btn) return;
          var prev = empList.querySelector('.resource-card.active');
          if (prev) prev.classList.remove('active');
          btn.classList.add('active');
          var id = btn.getAttribute('data-user-id');
          window.selectedEmployeeId = id;
          // update selected name display in main area
          var sel = document.getElementById('selectedEmployee');
          var name = btn.querySelector('.resource-name') ? btn.querySelector('.resource-name').textContent.trim() : '';
          var role = btn.querySelector('.resource-sub') ? btn.querySelector('.resource-sub').textContent.trim() : '';
          if (sel) sel.textContent = name ? (name + (role ? ' — ' + role : '')) : ('ID: ' + id);
        });
      }
    })();

    // Employee fields UI
    (function(){
      var sections = [
        { key: 'operating', title: 'Operating Skills', listId: 'datalist-operating-skills' },
        { key: 'life', title: 'Life Skills', listId: 'datalist-life-skills' },
        { key: 'certs', title: 'Certifications', listId: 'datalist-certifications' },
        { key: 'background', title: 'Background', listId: 'datalist-background' }
      ];

      var container = document.getElementById('employeeFields');
      if (!container) return;

      // helper to create a single input row and append to a list
      // readonly: boolean - whether the input should be readonly and removal hidden
      function createRow(list, val, datalistId, readonly){
        if (typeof readonly === 'undefined') readonly = true;
        var row = document.createElement('div');
        row.style.display = 'flex';
        row.style.gap = '8px';
        row.style.alignItems = 'center';
        row.style.justifyContent = 'flex-start';

        var inp = document.createElement('input');
        inp.type = 'text';
        inp.placeholder = 'Add...';
        inp.style.flex = '1 1 auto';
        inp.style.maxWidth = '760px';
        inp.style.width = '100%';
        inp.style.padding = '8px';
        inp.style.border = '1px solid #cbd5e1';
        inp.style.borderRadius = '6px';
        if (datalistId) inp.setAttribute('list', datalistId);
        if (val) inp.value = val;
        inp.readOnly = !!readonly;

        var rem = document.createElement('button');
        rem.type = 'button';
        rem.textContent = '✕';
        rem.title = 'Remove';
        rem.style.padding = '6px 10px';
        rem.style.borderRadius = '8px';
        rem.style.border = '1px solid #fee2e2';
        rem.style.background = '#fff5f5';
        rem.style.cursor = 'pointer';
        rem.style.flex = '0 0 36px';
        rem.style.width = '36px';
        rem.style.marginLeft = '8px';
        rem.addEventListener('click', function(){ try{ list.removeChild(row); }catch(e){} });
        rem.style.display = readonly ? 'none' : 'inline-block';

        row.appendChild(inp);
        row.appendChild(rem);
        list.appendChild(row);
        return { input: inp, removeBtn: rem, row: row };
      }

      function createSection(s){
        var wrap = document.createElement('div');
        wrap.className = 'emp-section';
        wrap.style.border = '1px solid #e6edf0';
        wrap.style.padding = '10px';
        wrap.style.borderRadius = '8px';
        var header = document.createElement('div');
        header.style.display = 'flex';
        header.style.alignItems = 'center';
        header.style.gap = '8px';
        var h = document.createElement('div'); h.textContent = s.title; h.style.fontWeight = '700'; h.style.color = '#334155';
        var addBtn = document.createElement('button'); addBtn.type = 'button'; addBtn.textContent = '+'; addBtn.title = 'Add';
        addBtn.style.marginLeft = '8px'; addBtn.style.padding = '6px 10px'; addBtn.style.borderRadius = '8px'; addBtn.style.border = '1px solid #e6edf0'; addBtn.style.background = '#fff'; addBtn.style.cursor = 'pointer';

        // edit button (pencil icon) to toggle edit mode for this section
        var editBtn = document.createElement('button'); editBtn.type = 'button'; editBtn.title = 'Edit';
        editBtn.style.marginLeft = '8px'; editBtn.style.padding = '6px 8px'; editBtn.style.borderRadius = '8px'; editBtn.style.border = '1px solid transparent'; editBtn.style.background = 'transparent'; editBtn.style.cursor = 'pointer';
        var img = document.createElement('img'); img.src = '../../assets/images/pencil.svg'; img.alt = 'edit'; img.style.height = '16px'; img.style.width = '16px'; img.style.verticalAlign = 'middle';
        editBtn.appendChild(img);

        header.appendChild(h); header.appendChild(addBtn); header.appendChild(editBtn);
        wrap.appendChild(header);

        var list = document.createElement('div'); list.className = 'emp-section-list'; list.style.display = 'grid'; list.style.gap = '8px'; list.style.marginTop = '10px';
        list.setAttribute('data-title', s.title);

        // addBtn should be disabled until edit mode is enabled
        addBtn.disabled = true;

        // track edit mode for this section
        var editMode = false;

        // toggles inputs and buttons in the section
        function setEditMode(enabled){
          editMode = !!enabled;
          // update add button
          addBtn.disabled = !editMode;
          // update remove buttons and inputs
          list.querySelectorAll('input').forEach(function(i){ i.readOnly = !editMode; });
          list.querySelectorAll('button').forEach(function(b){ if (b !== addBtn && b !== editBtn) b.style.display = editMode ? 'inline-block' : 'none'; });
          // update editBtn appearance
          if (editMode) {
            editBtn.textContent = 'Save';
            editBtn.style.background = '#10b981';
            editBtn.style.color = '#fff';
            editBtn.title = 'Save';
          } else {
            // restore pencil image
            editBtn.innerHTML = '';
            var im2 = document.createElement('img'); im2.src = '../../assets/images/pencil.svg'; im2.alt = 'edit'; im2.style.height = '16px'; im2.style.width = '16px'; im2.style.verticalAlign = 'middle';
            editBtn.appendChild(im2);
            editBtn.style.background = 'transparent';
            editBtn.style.color = '';
            editBtn.title = 'Edit';
          }
        }

        // add button behavior: only allowed in edit mode
        addBtn.addEventListener('click', function(){ if (!editMode) return; createRow(list, '', s.listId, false); });

        // add initial empty readonly input
        createRow(list, '', s.listId, true);

        // edit button click: if switching to edit mode, enable edits; if saving, collect and send save
        editBtn.addEventListener('click', function(){
          if (!editMode) {
            setEditMode(true);
            return;
          }

          // collect payload for all sections and save (section-level save uses full payload to avoid data loss)
          var payload = {};
          document.querySelectorAll('.emp-section').forEach(function(sec){
            var titleEl = sec.querySelector('div:first-child div');
            var title = titleEl ? titleEl.textContent.trim() : '';
            var arr = [];
            sec.querySelectorAll('input').forEach(function(i){ if (i.value && i.value.toString().trim()) arr.push(i.value.toString().trim()); });
            payload[title] = arr;
          });

          var userId = window.selectedEmployeeId || (document.querySelector('.resource-card') ? document.querySelector('.resource-card').getAttribute('data-user-id') : null);
          if (!userId) {
            alert('Please select an employee on the left before saving.');
            return;
          }

          fetch('../../api/save_employee_details.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, payload: payload })
          }).then(function(r){ return r.json(); }).then(function(res){
            if (res && res.success) {
              showToast('Saved');
              // reload saved details and exit edit mode
              loadDetailsForUser(userId);
              setEditMode(false);
            } else {
              showToast('Save failed', true);
            }
          }).catch(function(){ showToast('Save failed', true); });
        });

        wrap.appendChild(list);
        container.appendChild(wrap);
      }

      sections.forEach(createSection);

      function clearAndPopulate(data){
        // data is mapping title => [items]
        document.querySelectorAll('.emp-section-list').forEach(function(list){
          // remove all children
          while (list.firstChild) list.removeChild(list.firstChild);
          var title = list.getAttribute('data-title');
          var arr = (data && data[title]) ? data[title] : [];
          var section = sections.find(function(s){ return s.title === title; });
          var datalistId = section ? section.listId : null;
          if (arr.length === 0) {
            createRow(list, '', datalistId);
          } else {
            arr.forEach(function(v){ createRow(list, v, datalistId); });
          }
        });
      }

      function loadDetailsForUser(userId){
        if (!userId) return;
        fetch('../../api/get_employee_details.php?user_id=' + encodeURIComponent(userId), { credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(json){
            if (!json || !json.success) {
              clearAndPopulate(null);
              // still update header from sidebar if possible
              var card = document.querySelector('.resource-card[data-user-id="' + userId + '"]');
              var sel = document.getElementById('selectedEmployee');
              if (card && sel) {
                var name = card.querySelector('.resource-name') ? card.querySelector('.resource-name').textContent.trim() : '';
                var role = card.querySelector('.resource-sub') ? card.querySelector('.resource-sub').textContent.trim() : '';
                sel.textContent = name ? (name + (role ? ' — ' + role : '')) : ('ID: ' + userId);
              }
              return;
            }
            clearAndPopulate(json.data || {});
            // update header from sidebar
            var card = document.querySelector('.resource-card[data-user-id="' + userId + '"]');
            var sel = document.getElementById('selectedEmployee');
            if (card && sel) {
              var name = card.querySelector('.resource-name') ? card.querySelector('.resource-name').textContent.trim() : '';
              var role = card.querySelector('.resource-sub') ? card.querySelector('.resource-sub').textContent.trim() : '';
              sel.textContent = name ? (name + (role ? ' — ' + role : '')) : ('ID: ' + userId);
            }
          }).catch(function(){ clearAndPopulate(null); });
      }

      // Global save button removed; per-section save controls remain.

      function showToast(msg, isError){
        var t = document.createElement('div'); t.textContent = msg;
        t.style.position = 'fixed'; t.style.right = '20px'; t.style.bottom = '20px';
        t.style.background = isError ? '#ef4444' : '#10b981'; t.style.color = '#fff'; t.style.padding = '10px 14px'; t.style.borderRadius = '8px'; t.style.boxShadow = '0 8px 20px rgba(2,6,23,0.12)';
        document.body.appendChild(t);
        setTimeout(function(){ try{ document.body.removeChild(t);}catch(e){} }, 2200);
      }

      // Optionally populate datalists by fetching suggestions from an API endpoint if available
      try {
        var endpoint = '../../api/get_employee_field_suggestions.php';
        fetch(endpoint, { credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(json){
          if (!json) return;
          if (json.operating && Array.isArray(json.operating)) json.operating.forEach(function(v){ var o = document.createElement('option'); o.value = v; document.getElementById('datalist-operating-skills').appendChild(o); });
          if (json.life && Array.isArray(json.life)) json.life.forEach(function(v){ var o = document.createElement('option'); o.value = v; document.getElementById('datalist-life-skills').appendChild(o); });
          if (json.certs && Array.isArray(json.certs)) json.certs.forEach(function(v){ var o = document.createElement('option'); o.value = v; document.getElementById('datalist-certifications').appendChild(o); });
          if (json.background && Array.isArray(json.background)) json.background.forEach(function(v){ var o = document.createElement('option'); o.value = v; document.getElementById('datalist-background').appendChild(o); });
        }).catch(function(){});
      } catch(e){}

      // Load details when selecting an employee (hook to existing selection code)
      document.querySelectorAll('.resource-card').forEach(function(card){
        card.addEventListener('click', function(){
          var id = card.getAttribute('data-user-id');
          if (id) loadDetailsForUser(id);
        });
      });

      // Ensure clicking the employee name link opens a new tab and doesn't get swallowed by the card click
      document.querySelectorAll('.emp-open-link').forEach(function(link){
        link.addEventListener('click', function(ev){
          ev.stopPropagation();
          ev.preventDefault();
          try { window.open(link.href, '_blank', 'noopener'); } catch(e) { window.location.href = link.href; }
        });
      });

      // Auto-select employee on load: prefer `user_id` URL param, then active card, then first card
      (function autoSelectFirst(){
        try {
          var params = new URLSearchParams(window.location.search);
          var uid = params.get('user_id');
          if (uid) {
            var card = document.querySelector('.resource-card[data-user-id="' + uid + '"]');
            if (card) { try { card.click(); } catch(e) { card.classList.add('active'); loadDetailsForUser(uid); } return; }
          }
        } catch(e) {}

        var active = document.querySelector('.resource-card.active');
        if (active) {
          var id = active.getAttribute('data-user-id');
          if (id) loadDetailsForUser(id);
          return;
        }
        var first = document.querySelector('.resource-card');
        if (first) {
          try { first.click(); } catch(e) {}
        }
      })();
    })();
  </script>
</body>
</html>
