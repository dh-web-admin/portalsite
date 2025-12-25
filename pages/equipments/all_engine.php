<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

require_once __DIR__ . '/../../config/config.php';

// Get user role for sidebar
$email = $_SESSION['email'];
$roleStmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$roleStmt->bind_param('s', $email);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$user = $roleRes ? $roleRes->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';

// Check if developer is previewing as another role
if ($role === 'developer' && isset($_GET['preview_role'])) {
    $role = $_GET['preview_role'];
}

$roleStmt->close();

// Preserve preview mode in URLs
$previewParam = '';
if (isset($_GET['preview_role'])) {
    $previewParam = '?preview_role=' . urlencode($_GET['preview_role']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
    <meta name="theme-color" content="#667eea" />
    <title>All Engine</title>
    <link rel="stylesheet" href="../../assets/css/base.css" />
    <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
    <link rel="stylesheet" href="../../assets/css/dashboard.css" />
    <style>
    .engine-table-area {
      overflow-x: auto;
      margin: 0 auto;
      max-width: 98vw;
    }
    .engine-table {
      border-collapse: separate;
      border-spacing: 0;
      width: 100%;
      min-width: 1200px;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 24px rgba(30,41,59,0.08);
      overflow: hidden;
    }
    .engine-table thead th {
      position: sticky;
      top: 0;
      background: #f3f4f6;
      color: #22223b;
      font-size: 15px;
      font-weight: 700;
      padding: 16px 10px;
      border-bottom: 2px solid #e5e7eb;
      z-index: 2;
      text-align: left;
      letter-spacing: 0.01em;
    }
    .engine-table tbody td {
      padding: 14px 10px;
      font-size: 15px;
      color: #22223b;
      border-bottom: 1px solid #f1f1f1;
      background: #fff;
    }
    .engine-table tbody tr:nth-child(even) td {
      background: #f8fafc;
    }
    .engine-table tbody tr:hover td {
      background: #e0e7ff;
      transition: background 0.2s;
    }
    .engine-table tbody tr:last-child td {
      border-bottom: none;
    }
    @media (max-width: 1300px) {
      .engine-table { min-width: 900px; }
    }
    .engine-table-scroll-x {
      overflow-x: scroll !important;
      width: 100%;
      margin-bottom: 8px;
      height: 18px;
      background: #f3f4f6;
      border-radius: 8px 8px 0 0;
    }
    .engine-table-scroll-x::-webkit-scrollbar {
      height: 12px;
    }
    .engine-table-scroll-x::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 8px;
    }
    .engine-table-scroll-x::-webkit-scrollbar-track {
      background: #f3f4f6;
      border-radius: 8px;
    }
    .equipment-btn--secondary {
        padding: 10px 28px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 15px;
        background: #f3f4f6;
        color: #6b7280;
        border: none;
        text-decoration: none;
        display: inline-block;
        margin: 18px 0 0 0;
        transition: background 0.2s;
    }

    .equipment-btn--secondary:hover {
        background: #e5e7eb !important;
        color: #374151 !important;
        text-decoration: none !important;
    }
    .download-print-btn {
    padding: 10px 22px 10px 16px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    border: none;
    background: #667eea;
    color: #fff;
    margin-right: 18px;
    transition: background 0.18s, color 0.18s, box-shadow 0.18s, transform 0.1s;
    box-shadow: 0 2px 8px #0001;
    outline: none;
    text-align: center;
    min-width: 140px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.download-print-btn:last-child {
    margin-right: 0;
}
.download-print-btn .icon {
    font-size: 20px;
    display: inline-block;
    vertical-align: middle;
    transition: color 0.18s;
}
.download-print-btn:active, .download-print-btn.active {
    background: #667eea !important;
    color: #fff !important;
    box-shadow: 0 2px 8px #0001;
}
.download-print-btn:active .icon, .download-print-btn.active .icon {
    color: #fff !important;
    stroke: #fff !important;
}
.download-print-btn:hover, .download-print-btn:focus {
    background: #f3f4f6 !important;
    color: #3b4cca !important;
    box-shadow: 0 4px 16px #0002;
    transform: translateY(-2px) scale(1.04);
    text-decoration: none;
}
.download-print-btn:hover .icon, .download-print-btn:focus .icon {
    color: #3b4cca !important;
    stroke: #3b4cca !important;
}
    </style>
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
            <main class="content-area">
                <div class="main-content">
                    <h1 class="admin-page-title" style="text-align:center;margin-top:32px;margin-bottom:24px;">All Engine Cheat-Sheet</h1>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <div>
                            <a href="index.php<?php echo $previewParam; ?>" class="equipment-btn equipment-btn--secondary" style="padding: 10px 28px; border-radius: 8px; font-weight: 600; font-size: 15px; background: #f3f4f6; color: #6b7280; border: none; text-decoration: none; display: inline-block; margin: 0; transition: background 0.2s;">&larr; Back to Equipments</a>
                        </div>
                        <div>
                            <!-- Update SVGs to use currentColor for stroke so they inherit text color -->
<button id="downloadCsvBtn" class="download-print-btn" style="margin-right: 18px;">
    <span class="icon" aria-hidden="true" style="display:inline-flex;align-items:center;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
    </span>
    <span>Download CSV</span>
</button>
<button id="printTableBtn" class="download-print-btn">
    <span class="icon" aria-hidden="true" style="display:inline-flex;align-items:center;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="9" width="12" height="7" rx="2"/><path d="M6 17v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2"/><polyline points="6 9 6 4 18 4 18 9"/><line x1="9" y1="13" x2="15" y2="13"/></svg>
    </span>
    <span>Print Table</span>
</button>
                        </div>
                    </div>
                    <div class="table-area engine-table-area">
                        <div class="engine-table-scroll-x" style="overflow-x:auto;width:100%;margin-bottom:8px;">
                          <!-- This empty div will sync scroll with the table below -->
                          <div style="height:1px;width:1200px;"></div>
                        </div>
                        <table class="project-table equipment-table engine-table">
                            <thead>
                                <tr>
                                    <th>DHCST #</th>
                                    <th>DHSS #</th>
                                    <th>Type</th>
                                    <th>VIN</th>
                                    <th>Year</th>
                                    <th>Make</th>
                                    <th>Model</th>
                                    <th>Engine</th>
                                    <th>Engine Serial #</th>
                                    <th>Transmission</th>
                                    <th>Trans Serial #</th>
                                    <th>Transfer Case Serial</th>
                                    <th>Front Diff Serial</th>
                                    <th>Middle Diff Serial</th>
                                    <th>Rear Diff Serial</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT dhcst_equipment_number, dhss_equipment_number, type, vin, vehicle_year, make, model, engine, engine_serial_number, transmission, trans_serial_number, transfer_case_serial, front_differential_serial, middle_differential_serial, rear_differential_serial FROM equipments ORDER BY equipment_id DESC";
                                $result = $conn->query($sql);
                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($row['dhcst_equipment_number'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['dhss_equipment_number'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['type'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['vin'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['vehicle_year'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['make'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['model'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['engine'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['engine_serial_number'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['transmission'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['trans_serial_number'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['transfer_case_serial'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['front_differential_serial'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['middle_differential_serial'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['rear_differential_serial'] ?? '') . '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="15">No equipment found.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
    (function(){
        // Toggle users sub-nav
        var usersToggle = document.getElementById('usersToggle');
        var usersGroup = document.getElementById('usersGroup');
        if (usersToggle && usersGroup) {
            usersToggle.addEventListener('click', function(){
                usersGroup.classList.toggle('open');
            });
        }
    })();
    </script>
    <script src="../../assets/js/mobile-menu.js"></script>
    <script src="../../assets/js/logout-confirm.js"></script>
    <script>
    // Sync top scroll bar with table scroll
    (function() {
      var topScroll = document.querySelector('.engine-table-scroll-x');
      var tableArea = document.querySelector('.engine-table-area');
      if (topScroll && tableArea) {
        var table = tableArea.querySelector('table');
        if (table) {
          // Set the width of the top scroll bar to match the table
          topScroll.firstElementChild.style.width = table.scrollWidth + 'px';
          // Sync scroll positions
          topScroll.onscroll = function() {
            tableArea.scrollLeft = topScroll.scrollLeft;
          };
          tableArea.onscroll = function() {
            topScroll.scrollLeft = tableArea.scrollLeft;
          };
        }
      }
    })();
    document.getElementById('downloadCsvBtn').addEventListener('click', function() {
        var table = document.querySelector('.engine-table');
        var rows = Array.from(table.querySelectorAll('tr'));
        var csv = rows.map(function(row) {
            return Array.from(row.querySelectorAll('th,td')).map(function(cell) {
                var text = cell.innerText.replace(/\n/g, ' ').replace(/"/g, '""');
                return '"' + text + '"';
            }).join(',');
        }).join('\n');
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'all_engine_cheat_sheet.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
    document.getElementById('printTableBtn').addEventListener('click', function() {
        var table = document.querySelector('.engine-table-area');
        var printWindow = window.open('', '', 'height=600,width=1200');
        printWindow.document.write('<html><head><title>Print Table</title>');
        printWindow.document.write('<link rel="stylesheet" href="../../assets/css/base.css">');
        printWindow.document.write('<link rel="stylesheet" href="../../assets/css/admin-layout.css">');
        printWindow.document.write('<link rel="stylesheet" href="../../assets/css/dashboard.css">');
        printWindow.document.write('<link rel="stylesheet" href="style.css">');
        printWindow.document.write('</head><body >');
        printWindow.document.write(table.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    });
    document.getElementById('downloadCsvBtn').classList.add('download-print-btn');
    document.getElementById('printTableBtn').classList.add('download-print-btn');
    </script>
</body>
</html>

