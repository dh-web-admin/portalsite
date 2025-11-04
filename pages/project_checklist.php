<?php
require_once __DIR__ . '/../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Include database configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

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
if (!can_access($role, 'project_checklist')) {
  header('Location: ../pages/dashboard.php');
  exit();
}
// Handle form submissions (save / cancel / create new)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Cancel -> reload
  if (isset($_POST['cancel'])) {
    header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']));
    exit();
  }

  // Save updates for existing projects
  if (isset($_POST['save']) && !empty($_POST['data']) && is_array($_POST['data'])) {
    foreach ($_POST['data'] as $id => $fields) {
      $id = (int)$id;
      if ($id <= 0) continue;
      // Build SET clause dynamically
      $sets = [];
      $types = '';
      $values = [];
      foreach ($fields as $col => $val) {
        // sanitize column names (allow only letters, numbers, underscore)
        if (!preg_match('/^[A-Za-z0-9_]+$/', $col)) continue;
        $sets[] = "`$col` = ?";
        $types .= 's';
        $values[] = $val;
      }
      if (count($sets) === 0) continue;
      $sql = "UPDATE `Projects` SET " . implode(', ', $sets) . " WHERE Project_ID = ? LIMIT 1";
      $stmt = $conn->prepare($sql);
      if ($stmt) {
        // bind params dynamically
        $types .= 'i';
        $values[] = $id;
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
      }
    }
    // after save redirect to avoid resubmission
    header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']));
    exit();
  }

  // Create new project
  if (isset($_POST['create']) && !empty($_POST['new']) && is_array($_POST['new'])) {
    $new = $_POST['new'];
    $cols = [];
    $placeholders = [];
    $types = '';
    $values = [];
    foreach ($new as $col => $val) {
      if (!preg_match('/^[A-Za-z0-9_]+$/', $col)) continue;
      $cols[] = "`$col`";
      $placeholders[] = '?';
      $types .= 's';
      $values[] = $val;
    }
    if (count($cols) > 0) {
      $sql = "INSERT INTO `Projects` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
      $stmt = $conn->prepare($sql);
      if ($stmt) {
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
      }
    }
    header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']));
    exit();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Project Checklist</title>
  <link rel="stylesheet" href="../assets/css/base.css" />
  <link rel="stylesheet" href="../assets/css/admin-layout.css" />
  <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body class="admin-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../partials/sidebar.php'; ?>
      <main class="content-area">
        <div class="main-content">
            <?php
            // Fetch projects (wrap in try/catch so a missing table produces a friendly message)
            $projects = [];
            try {
                $res = $conn->query("SELECT * FROM `Projects` ORDER BY Project_ID DESC");
                if ($res) {
                    while ($r = $res->fetch_assoc()) $projects[] = $r;
                    $res->free();
                }
            } catch (mysqli_sql_exception $ex) {
                // Friendly error: likely the `Projects` table does not exist in the configured DB.
                $dbName = isset($database) ? $database : '(unknown)';
                ?>
                <div style="padding:16px;border:1px solid #e0a0a0;background:#fff6f6;color:#900;border-radius:6px;margin-bottom:12px;">
                  <strong>Database error:</strong>
                  <div style="margin-top:8px;">Could not read the <code>Projects</code> table from database <code><?php echo htmlspecialchars($dbName); ?></code>.</div>
                  <div style="margin-top:8px;">Error message: <?php echo htmlspecialchars($ex->getMessage()); ?></div>
                  <div style="margin-top:8px;">Possible fixes:
                    <ul>
                      <li>Point this app to the correct database (set environment variables / update <code>config/config.php</code>).</li>
                      <li>Import or create the <code>Projects</code> table in your local DB (schema/migration).</li>
                      <li>Run this page in production where your Railway DB is available.</li>
                    </ul>
                  </div>
                </div>
                <?php
                // Stop rendering the rest of the page (no projects to show)
                echo '</div></div></main></div></div></body></html>';
                exit();
            }

            // columns to display (db_column => Label)
            $cols = [
              'Project_Name' => 'Project Name','City'=>'City','County'=>'County','State'=>'State','Coordinates'=>'Coordinates','Client'=>'Client','Anticipated_Start_Date'=>'Anticipated Start Date','State_License'=>'State License','City_License'=>'City License','Get_Contract'=>'Get Contract','Review_and_Sign_Contract'=>'Review and sign Contract','Get_Tax_Exempt_Form'=>'Get Tax Exempt Form','Complete_Vendor_Form'=>'Complete Vendor Form','Send_W9'=>'Send W9','Send_BWC'=>'Send BWC','Updated_BWC'=>'Updated BWC','Request_Certificate_of_INS'=>'Request Certificate of INS','Send_Certificate_of_INS'=>'Send Certificate of INS','Send_to_Lawyer'=>'Send to Lawyer','Request_NOC'=>'Request NOC','Send_NOF'=>'Send NOF','File_NOC_NOF'=>'File NOC/NOF','Get_Signed_Quote'=>'Get signed Quote','Complete_Win_Packet'=>'Complete Win Packet','Create_Foreman_Field_Folder'=>'Create Foreman Field Folder','Add_to_Project_Calendar'=>'Add to Project Calendar','Soil_Testing'=>'Soil Testing','Soil_Sampling'=>'Soil Sampling','Lab'=>'Lab','Mix_Design_Sent'=>'Mix Design Sent','Results'=>'Results','Mix_Design_Approval'=>'Mix Design Approval','Call_OUPS'=>'Call OUPS','Schedule_Mobilization'=>'Schedule Mobilization','Schedule_Field_Testing'=>'Schedule Field Testing','Get_Field_Testing_Results'=>'Get Field Testing Results','Send_Submittals'=>'Send Submittals','Schedule_Fuel'=>'Schedule Fuel','Fuel_Supplier'=>'Fuel Supplier','Selected_Material_Supplier'=>'Selected Material Supplier','Schedule_Material'=>'Schedule Material','Selected_Trucking_Company'=>'Selected Trucking Company','Schedule_Trucker'=>'Schedule Trucker','Hotel'=>'Hotel','Find_Water'=>'Find Water','Water_Semi'=>'Water Semi','Schedule_Men'=>'Schedule Men','Grade_File'=>'Grade File','Cure_Type'=>'Cure Type','Schedule_Cure'=>'Schedule Cure','Cure_Provider'=>'Cure Provider','Turn_in_Paperwork'=>'Turn in Paperwork','AIA'=>'AIA','Process_Field_Paperwork'=>'Process Field Paperwork','Review_Processed_Paperwork'=>'Review Processed Paperwork','Invoice'=>'Invoice','Sign_Change_Order'=>'Sign Change Order','Send_Signed_Change_Order'=>'Send Signed Change Order','Send_Supplier_Lein_Waiver'=>'Send Supplier Lein Waiver','Supplier_Lein_Waiver'=>'Supplier Lein Waiver','DHSS_Lein_Waiver'=>'DHSS Lein Waiver'
            ];
            ?>

            <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px;">
              <div style="flex:1;">
                <!-- Filter tabs -->
                <div class="filter-tabs" style="display:flex;gap:8px;align-items:center;">
                  <form method="get" id="filterForm">
                    <input type="hidden" name="status" value="" id="statusInput" />
                  </form>
                  <button type="button" class="nav-btn" onclick="setStatus('ongoing')">Ongoing</button>
                  <button type="button" class="nav-btn" onclick="setStatus('completed')">Completed</button>
                  <button type="button" class="nav-btn" onclick="setStatus('cancelled')">Cancelled</button>
                </div>
              </div>
              <div style="flex:0 0 auto;">
                <button id="newBtn" class="nav-btn" type="button">New Project</button>
              </div>
            </div>

            <script>
            function setStatus(s){
              document.getElementById('statusInput').value = s;
              document.getElementById('filterForm').submit();
            }
            </script>

            <!-- New project form (hidden by default) -->
            <div id="newForm" style="display:none;border:1px solid #ddd;padding:12px;margin-bottom:12px;border-radius:6px;background:#fff;">
              <form method="post">
                <div style="display:flex;gap:8px;align-items:center;">
                  <input name="new[Project_Name]" placeholder="Project Name" />
                  <input name="new[City]" placeholder="City" />
                  <input name="new[State]" placeholder="State" />
                  <button type="submit" name="create" class="nav-btn">Create</button>
                  <button type="button" class="nav-btn" onclick="document.getElementById('newForm').style.display='none'">Cancel</button>
                </div>
              </form>
            </div>

            <script>document.getElementById('newBtn').addEventListener('click', function(){var f=document.getElementById('newForm');f.style.display=f.style.display==='none'?'block':'none';});</script>

            <form method="post">
              <div style="overflow:auto;max-width:100%;">
                <table class="data-table" style="width:100%;border-collapse:collapse;">
                  <thead>
                    <tr>
                      <th style="border:1px solid #ddd;padding:6px;">ID</th>
                      <?php foreach ($cols as $db => $label): ?>
                        <th style="border:1px solid #ddd;padding:6px;white-space:nowrap;"><?php echo htmlspecialchars($label); ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($projects as $p): ?>
                    <tr>
                      <td style="border:1px solid #ddd;padding:6px;"><?php echo (int)$p['Project_ID']; ?></td>
                      <?php foreach ($cols as $db => $label): ?>
                        <td style="border:1px solid #ddd;padding:4px;">
                          <input style="width:200px;" type="text" name="data[<?php echo (int)$p['Project_ID']; ?>][<?php echo htmlspecialchars($db); ?>]" value="<?php echo htmlspecialchars($p[$db] ?? ''); ?>" />
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div style="margin-top:12px;display:flex;gap:8px;">
                <button type="submit" name="save" class="nav-btn">Save Changes</button>
                <button type="submit" name="cancel" class="nav-btn">Cancel</button>
              </div>
            </form>


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
    })();
  </script>
</body>
</html>
