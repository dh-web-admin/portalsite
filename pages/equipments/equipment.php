<?php
require_once __DIR__ . '/../../session_init.php';
require_once __DIR__ . '/../../config/config.php';

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_equipment']) && isset($_POST['equipment_id'])) {
    $deleteId = (int)$_POST['equipment_id'];
    if ($deleteId > 0) {
        $stmt = $conn->prepare('DELETE FROM equipments WHERE equipment_id = ?');
        $stmt->bind_param('i', $deleteId);
        $stmt->execute();
        header('Location: index.php');
        exit();
    }
}

// Get equipment ID from query string
$equipmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($equipmentId <= 0) {
    echo "<h2>Invalid equipment ID.</h2>";
    exit();
}

// Fetch equipment details
$stmt = $conn->prepare('SELECT * FROM equipments WHERE equipment_id = ? LIMIT 1');
$stmt->bind_param('i', $equipmentId);
$stmt->execute();
$res = $stmt->get_result();
$equipment = $res ? $res->fetch_assoc() : null;

if (!$equipment) {
    echo "<h2>Equipment not found.</h2>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Equipment Details - <?php echo htmlspecialchars($equipment['dhcst_equipment_number'] ?? 'N/A'); ?></title>
    <link rel="stylesheet" href="../../assets/css/base.css" />
    <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
    <link rel="stylesheet" href="../../assets/css/dashboard.css" />
    <style>
        .equipment-detail-page {
            padding: 20px 48px;
            max-width: 1600px;
            margin: 0 auto;
        }

        .equipment-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
            padding: 6px 12px;
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: 6px;
            color: #0f172a;
            font-weight: 700;
            font-size: 12px;
            text-decoration: none;
            box-shadow: 0 1px 3px rgba(2, 6, 23, 0.05);
            transition: all 0.2s ease;
        }

        .equipment-back-btn:hover {
            background: #f8fafc;
            border-color: rgba(15, 23, 42, 0.2);
            transform: translateX(-2px);
        }

        .equipment-detail-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid rgba(15, 23, 42, 0.08);
        }

        .equipment-detail-title {
            margin: 0;
            font-size: 20px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        .equipment-detail-subtitle {
            margin: 3px 0 0 0;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }

        .equipment-detail-actions {
            display: flex;
            gap: 12px;
        }

        .equipment-action-btn {
            padding: 7px 14px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            cursor: pointer;
            border: 1px solid rgba(15, 23, 42, 0.12);
            background: #ffffff;
            color: #0f172a;
            transition: all 0.2s ease;
        }

        .equipment-action-btn:hover {
            background: #f8fafc;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(2, 6, 23, 0.1);
        }

        .equipment-action-btn--primary {
            background: #3b82f6;
            border-color: #2563eb;
            color: #ffffff;
        }

        .equipment-action-btn--primary:hover {
            background: #2563eb;
        }

        .equipment-content-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
            margin-bottom: 24px;
        }
    .equipment-content-grid {
        display: flex;
        flex-direction: row;
        justify-content: flex-start;
        align-items: flex-start;
        gap: 32px;
        margin-bottom: 32px;
    }
        .equipment-card {
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 10px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(2, 6, 23, 0.04);
            min-width: 340px;
            max-width: 420px;
            width: 100%;
        }
        .equipment-info-grid,
        .equipment-status-grid,
        .equipment-specs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .equipment-info-item,
        .equipment-status-item {
            min-width: 0;
        }
        .equipment-info-value,
        .equipment-status-badge {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            padding: 7px 10px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            min-height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.3;
            text-align: center;
            width: 100%;
        }

        .equipment-card {
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 10px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(2, 6, 23, 0.04);
        }

        .equipment-card-header {
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(15, 23, 42, 0.08);
        }

        .equipment-card-title {
            margin: 0;
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .equipment-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .equipment-info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .equipment-info-label {
            font-size: 10px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            line-height: 1.2;
        }

        .equipment-info-value {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            padding: 7px 10px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            min-height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.3;
            text-align: center;
        }

        .equipment-info-value:empty::before {
            content: '—';
            color: #cbd5e1;
        }

        .equipment-status-card {
            background: #ffffff;
        }

        .equipment-status-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .equipment-status-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: #ffffff;
            border-radius: 6px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 1px 3px rgba(2, 6, 23, 0.03);
        }

        .equipment-status-label {
            font-size: 11px;
            font-weight: 700;
            color: #475569;
        }

        .equipment-status-badge {
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .equipment-status-badge--good {
            background: rgba(22, 163, 74, 0.12);
            color: #166534;
        }

        .equipment-status-badge--warn {
            background: rgba(234, 179, 8, 0.12);
            color: #92400e;
        }

        .equipment-status-badge--bad {
            background: rgba(239, 68, 68, 0.12);
            color: #991b1b;
        }

        .equipment-status-badge--neutral {
            background: rgba(100, 116, 139, 0.12);
            color: #475569;
        }

        .equipment-full-width-card {
            grid-column: 1 / -1;
        }

        .equipment-specs-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .equipment-future-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 2px solid rgba(15, 23, 42, 0.08);
        }

        .equipment-tabs {
            display: flex;
            gap: 2px;
            margin-bottom: 16px;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 8px;
            overflow-x: auto;
        }

        .equipment-tab {
            padding: 8px 16px;
            background: transparent;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 800;
            color: #64748b;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s ease;
        }

        .equipment-tab:hover {
            background: #e2e8f0;
        }

        .equipment-tab.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 1px 3px rgba(2, 6, 23, 0.08);
        }

        .equipment-history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12px;
        }

        .equipment-history-table thead th {
            text-align: left;
            padding: 10px 12px;
            background: #f8fafc;
            border-bottom: 2px solid rgba(15, 23, 42, 0.08);
            font-weight: 800;
            font-size: 11px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.03em;
        }

        .equipment-history-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.04);
            color: #0f172a;
        }

        .equipment-history-table tbody tr:hover {
            background: #f8fafc;
        }

        @media (max-width: 1200px) {
            .equipment-content-grid {
                grid-template-columns: 1fr;
            }
            
            .equipment-specs-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .equipment-info-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .equipment-status-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .equipment-detail-page {
                padding: 24px 20px;
            }

            .equipment-info-grid,
            .equipment-specs-grid,
            .equipment-status-grid {
                grid-template-columns: 1fr;
            }

            .equipment-detail-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .equipment-detail-actions {
                width: 100%;
                flex-direction: column;
            }

            .equipment-action-btn {
                width: 100%;
            }

            .equipment-tabs {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
            <main class="content-area">
                <div class="equipment-detail-page">

                    <div style="display: flex; flex-direction: column; align-items: center; margin-bottom: 12px;">
                        <div style="align-self: flex-start;">
                            <a href="index.php" class="equipment-back-btn">
                                <span>←</span>
                                <span>Back to Equipments</span>
                            </a>
                        </div>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this equipment?');" style="margin-top: 8px;">
                            <input type="hidden" name="equipment_id" value="<?php echo $equipmentId; ?>">
                            <button type="submit" name="delete_equipment" class="equipment-action-btn" style="border-color:#ef4444; color:#b91c1c; font-weight:700; min-width:180px;">Delete Equipment</button>
                        </form>
                    </div>

                    <div class="equipment-detail-header">
                        <div>
                            <h1 class="equipment-detail-title">
                                <?php echo htmlspecialchars($equipment['dhcst_equipment_number'] ?? 'Equipment Details'); ?>
                            </h1>
                            <p class="equipment-detail-subtitle">
                                <?php echo htmlspecialchars($equipment['type'] ?? 'N/A'); ?> 
                                <?php if (!empty($equipment['year'])): ?>
                                    • <?php echo htmlspecialchars($equipment['year']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="equipment-detail-actions">
                            <button class="equipment-action-btn" onclick="window.print()">Print</button>
                            <button class="equipment-action-btn equipment-action-btn--primary">Edit Equipment</button>
                        </div>
                    </div>

                    <div class="equipment-content-grid">
                        <!-- Main Information Card -->
                        <div class="equipment-card">
                            <div class="equipment-card-header">
                                <h2 class="equipment-card-title">Equipment Information</h2>
                            </div>
                            <div class="equipment-info-grid">
                                <div class="equipment-info-item">
                                    <div class="equipment-info-label">DHCST Equipment Number</div>
                                    <div class="equipment-info-value"><?php echo htmlspecialchars($equipment['dhcst_equipment_number'] ?? ''); ?></div>
                                </div>
                                <div class="equipment-info-item">
                                    <div class="equipment-info-label">DHSS Equipment Number</div>
                                    <div class="equipment-info-value"><?php echo htmlspecialchars($equipment['dhss_equipment_number'] ?? ''); ?></div>
                                </div>
                                <div class="equipment-info-item">
                                    <div class="equipment-info-label">Type</div>
                                    <div class="equipment-info-value"><?php echo htmlspecialchars($equipment['type'] ?? ''); ?></div>
                                </div>
                                <div class="equipment-info-item">
                                    <div class="equipment-info-label">Year</div>
                                    <div class="equipment-info-value"><?php echo htmlspecialchars($equipment['year'] ?? ''); ?></div>
                                </div>
                                <div class="equipment-info-item">
                                    <div class="equipment-info-label">VIN</div>
                                    <div class="equipment-info-value"><?php echo htmlspecialchars($equipment['vin'] ?? ''); ?></div>
                                </div>
                                <div class="equipment-info-item">
                                    <div class="equipment-info-label">Location</div>
                                    <div class="equipment-info-value"><?php echo htmlspecialchars($equipment['location'] ?? ''); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Technical Specifications Card -->
                        <div class="equipment-card">
                            <div class="equipment-card-header">
                                <h2 class="equipment-card-title">Technical Specifications</h2>
                            </div>
                            <div class="equipment-info-grid">
                                <div class="equipment-info-item">
                                    <div class="equipment-info-label">Make</div>
                                    <div class="equipment-info-value"><?php echo htmlspecialchars($equipment['make'] ?? ''); ?></div>
                                </div>
                                <div class="equipment-info-item">
                                    <div class="equipment-info-label">Model</div>
                                    <div class="equipment-info-value"><?php echo htmlspecialchars($equipment['model'] ?? ''); ?></div>
                                </div>
                                <div class="equipment-info-item">
                                    <div class="equipment-info-label">Engine</div>
                                    <div class="equipment-info-value"><?php echo htmlspecialchars($equipment['engine'] ?? ''); ?></div>
                                </div>
                                <div class="equipment-info-item">
                                    <div class="equipment-info-label">Engine Serial Number</div>
                                    <div class="equipment-info-value"><?php echo htmlspecialchars($equipment['engine_serial_number'] ?? ''); ?></div>
                                </div>
                                <div class="equipment-info-item">
                                    <div class="equipment-info-label">Transmission</div>
                                    <div class="equipment-info-value"><?php echo htmlspecialchars($equipment['transmission'] ?? ''); ?></div>
                                </div>
                                <div class="equipment-info-item">
                                    <div class="equipment-info-label">Trans Serial Number</div>
                                    <div class="equipment-info-value"><?php echo htmlspecialchars($equipment['trans_serial_number'] ?? ''); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Card -->
                        <div class="equipment-card equipment-status-card">
                            <div class="equipment-card-header">
                                <h2 class="equipment-card-title">Current Status</h2>
                            </div>
                            <div class="equipment-status-grid">
                                <div class="equipment-status-item">
                                    <span class="equipment-status-label">Operating Condition</span>
                                    <span class="equipment-status-badge equipment-status-badge--<?php 
                                        $cond = strtolower($equipment['operating_condition'] ?? '');
                                        echo (strpos($cond, 'good') !== false) ? 'good' : ((strpos($cond, 'warn') !== false) ? 'warn' : 'neutral');
                                    ?>">
                                        <?php echo htmlspecialchars($equipment['operating_condition'] ?? 'N/A'); ?>
                                    </span>
                                </div>
                                <div class="equipment-status-item">
                                    <span class="equipment-status-label">Current Hours</span>
                                    <span class="equipment-status-badge equipment-status-badge--neutral">
                                        <?php echo htmlspecialchars($equipment['current_hours'] ?? '0'); ?> hrs
                                    </span>
                                </div>
                                <div class="equipment-status-item">
                                    <span class="equipment-status-label">Oil Status</span>
                                    <span class="equipment-status-badge equipment-status-badge--<?php 
                                        $oil = strtolower($equipment['oil_status'] ?? '');
                                        echo (strpos($oil, 'good') !== false) ? 'good' : ((strpos($oil, 'due') !== false) ? 'warn' : 'neutral');
                                    ?>">
                                        <?php echo htmlspecialchars($equipment['oil_status'] ?? 'N/A'); ?>
                                    </span>
                                </div>
                                <div class="equipment-status-item">
                                    <span class="equipment-status-label">Air Filters</span>
                                    <span class="equipment-status-badge equipment-status-badge--<?php 
                                        $air = strtolower($equipment['air_filters'] ?? '');
                                        echo (strpos($air, 'good') !== false) ? 'good' : ((strpos($air, 'needs') !== false) ? 'warn' : 'neutral');
                                    ?>">
                                        <?php echo htmlspecialchars($equipment['air_filters'] ?? 'N/A'); ?>
                                    </span>
                                </div>
                                <div class="equipment-status-item">
                                    <span class="equipment-status-label">Tires</span>
                                    <span class="equipment-status-badge equipment-status-badge--<?php 
                                        $tires = strtolower($equipment['tires'] ?? '');
                                        echo (strpos($tires, 'good') !== false) ? 'good' : 'neutral';
                                    ?>">
                                        <?php echo htmlspecialchars($equipment['tires'] ?? 'N/A'); ?>
                                    </span>
                                </div>
                                <div class="equipment-status-item">
                                    <span class="equipment-status-label">Warranty</span>
                                    <span class="equipment-status-badge equipment-status-badge--<?php 
                                        $warranty = $equipment['warranty'] ?? null;
                                        if ($warranty && strtotime($warranty) >= time()) {
                                            echo 'good';
                                        } else {
                                            echo 'bad';
                                        }
                                    ?>">
                                        <?php echo $warranty ? htmlspecialchars($warranty) : 'N/A'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                    <!-- Future Sections Placeholder -->
                    <div class="equipment-future-section">
                        <div class="equipment-tabs">
                            <button class="equipment-tab active">Equipment History</button>
                            <button class="equipment-tab">Filters</button>
                            <button class="equipment-tab">Tires</button>
                            <button class="equipment-tab">Oil</button>
                            <button class="equipment-tab">Manuals</button>
                            <button class="equipment-tab">Warranty</button>
                            <button class="equipment-tab">Parts</button>
                            <button class="equipment-tab">Dimensions</button>
                            <button class="equipment-tab">Photos</button>
                        </div>

                        <div class="equipment-card">
                            <div class="equipment-card-header">
                                <h2 class="equipment-card-title">Equipment History</h2>
                            </div>
                            <div style="overflow-x: auto;">
                                <table class="equipment-history-table">
                                    <thead>
                                        <tr>
                                            <th>Date Reported</th>
                                            <th>Reported Issues</th>
                                            <th>Mechanic Diagnosis</th>
                                            <th>Date Repaired</th>
                                            <th>Repair Mechanic</th>
                                            <th>Part</th>
                                            <th>Photo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 24px; color: #94a3b8;">
                                                No history records yet. Add equipment issues and repairs to track maintenance history.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>