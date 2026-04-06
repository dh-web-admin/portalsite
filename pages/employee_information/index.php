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
                          <div class="resource-name"><?php echo htmlspecialchars($display); ?></div>
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

            <div><!-- main right area intentionally left blank --></div>
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
          var btn = ev.target.closest('.emp-item');
          if (!btn) return;
          var prev = empList.querySelector('.emp-item.active');
          if (prev) prev.classList.remove('active');
          btn.classList.add('active');
          var id = btn.getAttribute('data-user-id');
          var detail = document.getElementById('empDetail');
          if (detail) {
            detail.innerHTML = '<div>Loading...</div>';
            // For now just show basic info client-side; can fetch full details via API later
            var name = btn.querySelector('.emp-name').textContent.trim();
            var role = btn.querySelector('.emp-role').textContent.trim();
            detail.innerHTML = '<h3>' + name + '</h3><p><strong>Role:</strong> ' + role + '</p><p><strong>ID:</strong> ' + id + '</p>';
          }
        });
      }
    })();
  </script>
</body>
</html>
