<?php
require_once __DIR__ . '/../../session_init.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
	header('Location: /auth/login.php');
	exit();
}

// Get user role for access checks
$email = $_SESSION['email'];
$roleStmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$roleStmt->bind_param('s', $email);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$user = $roleRes ? $roleRes->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$roleStmt->close();

if (!can_access($role, 'equipments')) {
	header('Location: /pages/dashboard/');
	exit();
}

$canEditEquipments = can_edit_page('equipments');

// Hide admin-only UI elements for users without edit permission on this module

if (empty($canEditEquipments)) {
		echo <<<'HTML'
<style>.admin-only, .edit-filter-btn, .edit-dimension-btn, .edit-tire-btn, .upload-btn, #uploadImagesBtn, .editEquipmentBtn, .delete-equipment, .uploadFilterBtn, .add-equipment-btn, #newEquipmentBtn, .warranty-add-btn, .equipment-open-edit { display: none !important; }</style>
HTML;
}


// Fetch equipment rows
$equipments = [];
$equipmentsError = null;


$sql = "SELECT equipment_id, dhss_equipment_number, type, operating_condition, location, current_hours, oil_status, air_filters, warranty, tires,
	vin, vehicle_year, make, model, engine, engine_serial_number, transmission, trans_serial_number, transfer_case_serial,
	front_differential_serial, middle_differential_serial, rear_differential_serial, dhcst_equipment_number
	FROM equipments
	ORDER BY equipment_id ASC";

try {
	$res = $conn->query($sql);
	if ($res === false) {
		$equipmentsError = $conn->error;
	} else {
		while ($row = $res->fetch_assoc()) {
			$equipments[] = $row;
		}
		$res->free();

						// Dynamically compute oil status per equipment from equipment_oil_parts
						try {
							// collect ids
							$ids = array_map(function($r){ return (int)($r['equipment_id'] ?? 0); }, $equipments);
							$ids = array_filter($ids);
							if (count($ids) > 0) {
								$in = implode(',', array_map('intval', $ids));
								$partsByEquip = [];
								$qr = $conn->query("SELECT * FROM equipment_oil_parts WHERE equipment_id IN (" . $in . ") ORDER BY id ASC");
								if ($qr) {
									while ($p = $qr->fetch_assoc()) {
										$eid = (int)($p['equipment_id'] ?? 0);
										if (!isset($partsByEquip[$eid])) $partsByEquip[$eid] = [];
										$partsByEquip[$eid][] = $p;
									}
									$qr->free();
								}
								// For each equipment, compute worst oil condition across its parts
								foreach ($equipments as &$eq) {
									$eid = (int)($eq['equipment_id'] ?? 0);
									$parts = $partsByEquip[$eid] ?? [];
									// if no parts, leave existing oil_status as-is
									if (count($parts) === 0) continue;
									$equipCurrent = is_numeric($eq['current_hours'] ?? null) ? floatval($eq['current_hours']) : 0.0;
									$worst = 'green';
									foreach ($parts as $p) {
										$partCurrent = is_numeric($p['current_hours'] ?? null) ? floatval($p['current_hours']) : 0.0;
										$diff = $equipCurrent - $partCurrent;
										if (!is_numeric($diff) || $diff < 0) $diff = 0;
										$oilLife = is_numeric($p['oil_life'] ?? null) ? floatval($p['oil_life']) : 0.0;
										$conditionPct = 100;
										if ($oilLife > 0) {
											$usedPercent = ($diff / $oilLife) * 100;
											$conditionPct = max(0, min(100, (int)round(100 - $usedPercent)));
										}
										if ($conditionPct <= 0) { $worst = 'red'; break; }
										if ($conditionPct < 20 && $worst !== 'red') { $worst = 'yellow'; }
									}
									// override oil_status for display
									$eq['oil_status'] = $worst;
								}
								unset($eq);
							}
						} catch (Throwable $e) {
							// ignore dynamic status errors and leave DB value
						}

						// Dynamically compute air filter status based on filter life/usage
						try {
							$filterIds = array_map(function($r){ return (int)($r['equipment_id'] ?? 0); }, $equipments);
							$filterIds = array_filter($filterIds);
							if (count($filterIds) > 0) {
								$in = implode(',', array_map('intval', $filterIds));
								$filtersByEquip = [];
								$airFilterUpdates = [];
								$qrFilters = $conn->query("SELECT equipment_id, hours, filter_life FROM filter_info WHERE equipment_id IN (" . $in . ") ORDER BY filter_id ASC");
								if ($qrFilters) {
									while ($f = $qrFilters->fetch_assoc()) {
										$eid = (int)($f['equipment_id'] ?? 0);
										if (!isset($filtersByEquip[$eid])) $filtersByEquip[$eid] = [];
										$filtersByEquip[$eid][] = $f;
									}
									$qrFilters->free();
								}
								foreach ($equipments as &$eq) {
									$eid = (int)($eq['equipment_id'] ?? 0);
									$filters = $filtersByEquip[$eid] ?? [];
									if (count($filters) === 0) continue;
									$equipHours = is_numeric($eq['current_hours'] ?? null) ? (float)$eq['current_hours'] : 0.0;
									$worstCondition = null;
									foreach ($filters as $filterRow) {
										$life = is_numeric($filterRow['filter_life'] ?? null) ? (float)$filterRow['filter_life'] : 0.0;
										if ($life <= 0) continue;
										$lastReset = is_numeric($filterRow['hours'] ?? null) ? (float)$filterRow['hours'] : 0.0;
										$hoursSince = $equipHours - $lastReset;
										if (!is_numeric($hoursSince) || $hoursSince < 0) $hoursSince = 0.0;
										$usedPercent = ($hoursSince / $life) * 100.0;
										$conditionPct = max(0, min(100, (int)round(100 - $usedPercent)));
										if ($worstCondition === null || $conditionPct < $worstCondition) {
											$worstCondition = $conditionPct;
										}
									}
									if ($worstCondition !== null) {
										$eq['air_filters_condition_pct'] = $worstCondition;
										if ($worstCondition <= 0) {
											$computedStatus = 'red';
										} elseif ($worstCondition < 20) {
											$computedStatus = 'yellow';
										} else {
											$computedStatus = 'green';
										}
										$eq['air_filters_status'] = $computedStatus;
										if (($eq['air_filters'] ?? null) !== $computedStatus) {
											$airFilterUpdates[] = [
												'status' => $computedStatus,
												'id' => $eid
											];
										}
										$eq['air_filters'] = $computedStatus;
									}
								}
								unset($eq);
								if (!empty($airFilterUpdates)) {
									try {
										$stmtUpdateAir = $conn->prepare('UPDATE equipments SET air_filters = ? WHERE equipment_id = ?');
										if ($stmtUpdateAir) {
											foreach ($airFilterUpdates as $upd) {
												$statusVal = $upd['status'];
												$idVal = $upd['id'];
												$stmtUpdateAir->bind_param('si', $statusVal, $idVal);
												$stmtUpdateAir->execute();
											}
											$stmtUpdateAir->close();
										}
									} catch (Throwable $inner) {
										// swallow persistence issues; UI still shows computed values
									}
								}
							}
						} catch (Throwable $e) {
							// ignore filter aggregation issues silently
						}
		// Custom sort: red engine (operating_condition) or oil_status first, then yellow, then green, then others
		usort($equipments, function($a, $b) {
			$getStatus = function($row) {
				$eng = strtolower(trim($row['operating_condition'] ?? ''));
				$oil = strtolower(trim($row['oil_status'] ?? ''));
				// Priority: red > yellow > green > other
				if ($eng === 'red' || $oil === 'red') return 0;
				if ($eng === 'yellow' || $oil === 'yellow') return 1;
				if ($eng === 'green' || $oil === 'green') return 2;
				return 3;
			};
			$aStatus = $getStatus($a);
			$bStatus = $getStatus($b);
			if ($aStatus !== $bStatus) return $aStatus - $bStatus;
			// fallback: keep original order (by equipment_id asc)
			return ($a['equipment_id'] ?? 0) - ($b['equipment_id'] ?? 0);
		});
	}
} catch (Throwable $e) {
	$equipmentsError = $e->getMessage();
}

function eq_normalize_status($value) {
	$val = strtolower(trim((string)$value));
	if ($val === '') return 'neutral';
	// Handle specific color values first (green, yellow, red)
	if ($val === 'green') return 'good';
	if ($val === 'yellow') return 'warn';
	if ($val === 'red') return 'bad';
	// Handle other common status values
	if (strpos($val, 'good') !== false || strpos($val, 'ok') !== false || strpos($val, 'pass') !== false || $val === 'yes') return 'good';
	if (strpos($val, 'warn') !== false || strpos($val, 'soon') !== false || strpos($val, 'due') !== false || strpos($val, 'needs') !== false) return 'warn';
	if (strpos($val, 'bad') !== false || strpos($val, 'fail') !== false || strpos($val, 'no') !== false || strpos($val, 'down') !== false || strpos($val, 'out') !== false) return 'bad';
	return 'neutral';
}

function eq_format_warranty($dateValue) {
	if ($dateValue === null || $dateValue === '') {
		return ['label' => '—', 'state' => 'neutral'];
	}
	$ts = strtotime((string)$dateValue);
	if ($ts === false) {
		return ['label' => (string)$dateValue, 'state' => 'neutral'];
	}
	$today = strtotime(date('Y-m-d'));
	if ($ts >= $today) {
		return ['label' => date('Y-m-d', $ts), 'state' => 'good'];
	}
	return ['label' => date('Y-m-d', $ts), 'state' => 'bad'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Equipments</title>
	<link rel="stylesheet" href="../../assets/css/base.css">
	<link rel="stylesheet" href="../../assets/css/admin-layout.css">
	<link rel="stylesheet" href="../../assets/css/dashboard.css">
	<link rel="stylesheet" href="style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/style.css')); ?>">
	<style>
		/* Force Add Equipment button to be green and pill-shaped */
		#newEquipmentBtn.equipment-btn--green {
			background: #22c55e !important;
			color: #fff !important;
			border-radius: 999px !important;
			box-shadow: 0 4px 16px rgba(34,197,94,0.10);
		}
		#newEquipmentBtn.equipment-btn--green:hover {
			background: #16a34a !important;
		}
		.equipment-btn--green {
			background: #22c55e !important;
			color: #fff !important;
			border-radius: 999px !important;
			box-shadow: 0 4px 16px rgba(34,197,94,0.10);
		}
		
		/* Equipment number cell with edit icon */
		.equipment-number-cell {
			position: relative;
			display: flex;
			width: 100%;
			align-items: center;
			gap: 8px;
		}
		
		.equipment-edit-icon {
			opacity: 0;
			transition: opacity 0.2s, transform 0.2s;
			cursor: pointer;
			font-size: 16px;
			color: #667eea;
			padding: 4px;
			border-radius: 4px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			margin-left: auto;
		}
		
		.equipment-number-cell:hover .equipment-edit-icon {
			opacity: 1;
		}
		.equipment-hours { cursor: pointer; }
		.equipment-hours-cell, .equipment-inline-cell { position: relative; display: flex; width: 100%; align-items: center; gap: 8px; }
		.hours-edit-icon, .cell-edit-icon { opacity: 0; transition: opacity 0.2s, transform 0.2s; cursor: pointer; font-size: 12px; color: #667eea; padding: 4px 6px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid rgba(102, 126, 234, 0.4); background: rgba(102, 126, 234, 0.08); }
		.hours-edit-icon, .cell-edit-icon { margin-left: auto; }
		.equipment-hours-cell:hover .hours-edit-icon, .equipment-inline-cell:hover .cell-edit-icon { opacity: 1; }
		.hours-edit-icon:hover, .cell-edit-icon:hover { background: rgba(102, 126, 234, 0.18); transform: scale(1.05); }
		.warranty-cell { position: relative; display: inline-flex; align-items: center; gap: 10px; padding-right: 58px; }
		.warranty-add-btn { position: absolute; right: 0; top: 50%; transform: translateY(-50%); opacity: 0; pointer-events: none; transition: opacity 0.2s, transform 0.2s; cursor: pointer; font-size: 12px; color: #2563eb; padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(37, 99, 235, 0.35); background: rgba(37, 99, 235, 0.08); font-weight: 700; }
		.warranty-cell:hover .warranty-add-btn { opacity: 1; pointer-events: auto; }
		.warranty-add-btn:hover { background: rgba(37, 99, 235, 0.16); transform: translateY(-50%) scale(1.03); }

		/* Type / Location modals */
		.type-modal__dialog, .location-modal__dialog { max-width: 420px; }
		.type-modal__header { background: #2563eb; }
		.location-modal__header { background: #0ea5e9; }
		.inline-modal__form { padding: 24px 28px; }
		.inline-modal__grid { display: flex; flex-direction: column; gap: 14px; }
		
		.equipment-edit-icon:hover {
			background: rgba(102, 126, 234, 0.1);
			transform: scale(1.1);
		}

		/* Hours quick-edit modal */
		.hours-modal__dialog { max-width: 420px; }
		.hours-modal__header { background: #475569; }
		.hours-modal__form { padding: 24px 28px; }
		.hours-modal__grid { display: flex; flex-direction: column; gap: 14px; }
		
		/* Fix modal overlay and dialog clickability */
		.equipment-modal {
			position: fixed;
			top: 0;
			left: 0;
			width: 100vw;
			height: 100vh;
			background: rgba(0,0,0,0.5);
			z-index: 9999 !important;
			pointer-events: auto !important;
			display: none;
			align-items: center;
			justify-content: center;
			padding: 20px;
		}
		.equipment-modal.is-open {
			display: flex;
		}
		.equipment-modal__dialog {
			background: white;
			border-radius: 16px;
			box-shadow: 0 20px 60px rgba(0,0,0,0.3);
			z-index: 10000 !important;
			pointer-events: auto;
			width: 100%;
			max-width: 900px;
			max-height: 90vh;
			overflow-y: auto;
			animation: slideUp 0.3s ease-out;
		}
		@keyframes slideUp {
			from {
				opacity: 0;
				transform: translateY(30px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}
		.equipment-modal__header {
			padding: 24px 32px;
			border-bottom: 1px solid #e5e7eb;
			display: flex;
			justify-content: space-between;
			align-items: center;
			background: #22c55e;
			color: white;
			border-radius: 16px 16px 0 0;
		}
		.equipment-modal__header--edit {
			background: #667eea;
		}
		.equipment-modal__title {
			margin: 0;
			font-size: 24px;
			font-weight: 600;
		}
		.equipment-icon-btn {
			background: rgba(255,255,255,0.2);
			border: none;
			color: white;
			font-size: 28px;
			width: 36px;
			height: 36px;
			border-radius: 8px;
			cursor: pointer;
			display: flex;
			align-items: center;
			justify-content: center;
			transition: all 0.2s;
			line-height: 1;
		}
		.equipment-icon-btn:hover {
			background: rgba(255,255,255,0.3);
			transform: scale(1.1);
		}
		.equipment-form {
			padding: 32px;
		}
		.equipment-form__grid {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 24px;
			margin-bottom: 32px;
		}
		.equipment-form__field {
			display: flex;
			flex-direction: column;
		}
		.equipment-form__field label {
			font-weight: 600;
			margin-bottom: 8px;
			color: #374151;
			font-size: 14px;
		}
		.equipment-form__field input,
		.equipment-form__field select {
			padding: 12px 16px;
			border: 2px solid #e5e7eb;
			border-radius: 8px;
			font-size: 15px;
			transition: all 0.2s;
			background: white;
		}
		.equipment-form__field input:focus,
		.equipment-form__field select:focus {
			outline: none;
			border-color: #667eea;
			box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
		}
		.equipment-form__actions {
			display: flex;
			justify-content: flex-end;
			gap: 12px;
			padding-top: 24px;
			border-top: 1px solid #e5e7eb;
		}
		.equipment-btn {
			padding: 12px 32px;
			border-radius: 8px;
			font-weight: 600;
			font-size: 15px;
			cursor: pointer;
			transition: all 0.2s;
			border: none;
		}
		.equipment-btn:not(.equipment-btn--secondary):not(.equipment-btn--green) {
			background: #667eea;
			color: white;
		}
		.equipment-btn:not(.equipment-btn--secondary):not(.equipment-btn--green):hover {
			transform: translateY(-2px);
			box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
		}
		.equipment-btn--secondary {
			background: #f3f4f6;
			color: #6b7280;
		}
		.equipment-btn--secondary:hover {
			background: #e5e7eb;
		}
		.equipment-form__error {
			background: #fee;
			border: 1px solid #fcc;
			color: #c33;
			padding: 12px 16px;
			border-radius: 8px;
			margin-top: 16px;
		}
		.equipment-topbar {
			display: flex;
			justify-content: center;
			align-items: center;
			gap: 18px;
			margin-bottom: 18px;
		}
		.main-content {
			margin-top: 80px;
		}
		.equipment-table {
			background: #b0b3b8;
			margin-left: 10px;
			margin-right: 10px;
			width: auto;
			min-width: 1200px;
			max-width: none;
		}
		.table-container {
			display: flex;
			justify-content: center;
		}
		.equipment-row--green,
		.equipment-row--yellow,
		.equipment-table tbody tr,
		.equipment-table tr,
		.equipment-table tr:nth-child(even),
		.equipment-table tr:nth-child(odd),
		.equipment-table tbody tr:hover,
		.equipment-table tr:hover,
		.equipment-table td,
		.equipment-table th {
			background-color: #e0e1e3 !important;
		}
		.equipment-row--red {
			background-color: #ffe6e6 !important;
		}
		/* Stronger specificity to override all other color rules */
		.equipment-table .equipment-row--font-red td,
		.equipment-table .equipment-row--font-red a,
		.equipment-table .equipment-row--font-red span,
		.equipment-table .equipment-row--font-red {
			color: #e53935 !important;
		}
		.equipment-table .equipment-row--font-red * {
			color: #e53935 !important;
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
					<section class="equipment-page" aria-label="Equipment management">
						<div class="equipment-topbar" role="region" aria-label="Equipment actions">
							<button id="newEquipmentBtn" class="equipment-btn equipment-btn--green" type="button">Add Equipment</button>
							<div class="equipment-ribbon" aria-label="Cheat sheets">
								<a href="all_engine.php" class="equipment-ribbon__item">All Eng Cheat Sheet</a>
								<a href="all_filters.php" class="equipment-ribbon__item">Filter Cheat Sheet</a>
								<a href="all_tires.php" class="equipment-ribbon__item">Tire Cheat Sheet</a>
								<a href="all_dimensions.php" class="equipment-ribbon__item">Dimension Cheat Sheet</a>
							</div>
							<div class="equipment-ribbon" aria-label="Reports">
								<a href="all_engine_reports.php" class="equipment-ribbon__item equipment-ribbon__item--danger">Engine Reports</a>
								<a href="all_oil_change_reports.php" class="equipment-ribbon__item">Oil Change Reports</a>
								<a href="all_filter_change_reports.php" class="equipment-ribbon__item">Filter Change Reports</a>
							</div>
						</div>

						<?php if ($equipmentsError): ?>
							<div class="equipment-alert equipment-alert--error" role="alert">
								<strong>Database error:</strong>
								<span><?php echo htmlspecialchars($equipmentsError); ?></span>
								<div class="equipment-alert__hint">Run the migration: <code>php migrations/create_equipments_table.php</code></div>
							</div>
						<?php endif; ?>

						<div class="table-area">
							<div class="table-wrap">
								<div class="table-container" role="region" aria-label="Equipment table">
									<table class="project-table equipment-table" role="table" aria-label="Equipment list">
										<thead>
											<tr>
												<th scope="col">
													<span class="equip-th">
														<span class="equip-th__label">Equipment #</span>
														<button class="equip-sort-btn" type="button" aria-label="Sort equipment number" data-sort="equipment_number">▾</button>
													</span>
												</th>
												<th scope="col">
													<span class="equip-th">
														<span class="equip-th__label">Type</span>
														<button class="equip-sort-btn" type="button" aria-label="Filter type" data-sort="type">▾</button>
													</span>
												</th>
												<th scope="col">Location</th>
												<th scope="col">
													<span class="equip-th">
														<span class="equip-th__label">Current Hours</span>
														<button class="equip-sort-btn" type="button" aria-label="Sort current hours" data-sort="current_hours">▾</button>
													</span>
												</th>
												<th scope="col">
													<span class="equip-th">
														<span class="equip-th__label">Operating<br>Condition</span>
														<button class="equip-sort-btn" type="button" aria-label="Sort operating condition" data-sort="operating_condition">▾</button>
													</span>
												</th>
												<th scope="col">
													<span class="equip-th">
														<span class="equip-th__label">Oil Status</span>
														<button class="equip-sort-btn" type="button" aria-label="Sort oil status" data-sort="oil_status">▾</button>
													</span>
												</th>
												<th scope="col">Air Filters</th>
												<th scope="col">Tires</th>
												<th scope="col">Warranty</th>
											</tr>
										</thead>
										<tbody>
											<?php if (count($equipments) === 0): ?>
												<tr>
													<td class="equipment-empty" colspan="9">No equipment yet. Once rows exist in the database, they'll show up here.</td>
												</tr>
											<?php else: ?>
												<?php $eqIndex = 0; ?>
												<?php foreach ($equipments as $eq): ?>
													<?php
														$opState = eq_normalize_status($eq['operating_condition'] ?? '');
														$oilState = eq_normalize_status($eq['oil_status'] ?? '');
														$tiresState = eq_normalize_status($eq['tires'] ?? '');
														$warranty = eq_format_warranty($eq['warranty'] ?? null);
														$eqNumSort = strtolower(trim((string)($eq['dhss_equipment_number'] ?? '')));
														$hoursSort = is_numeric($eq['current_hours'] ?? null) ? (float)$eq['current_hours'] : 0.0;
													?>
													<?php
														$val = trim((string)($eq['operating_condition'] ?? ''));
														$rowColor = '';
														$isRedEngine = ($eq['operating_condition'] ?? '') === 'red';
														$isRedOil = ($eq['oil_status'] ?? '') === 'red';
														if ($val === 'green') $rowColor = 'equipment-row--green';
														elseif ($val === 'yellow') $rowColor = 'equipment-row--yellow';
														elseif ($val === 'red') $rowColor = 'equipment-row--red';
														$rowFontRed = ($isRedEngine || $isRedOil) ? ' equipment-row--font-red' : '';
													?>
													<tr
													   class="<?php echo $rowColor . $rowFontRed; ?>"
													   data-equipment-id="<?php echo (int)$eq['equipment_id']; ?>"
													   data-equipment-number="<?php echo htmlspecialchars($eq['dhss_equipment_number'] ?? ''); ?>"
													   data-type="<?php echo htmlspecialchars($eq['type'] ?? ''); ?>"
													   data-operating-condition="<?php echo htmlspecialchars($eq['operating_condition'] ?? ''); ?>"
													   data-location="<?php echo htmlspecialchars($eq['location'] ?? ''); ?>"
													   data-current-hours="<?php echo htmlspecialchars($eq['current_hours'] ?? '0'); ?>"
													   data-oil-status="<?php echo htmlspecialchars($eq['oil_status'] ?? ''); ?>"
													   data-vin="<?php echo htmlspecialchars($eq['vin'] ?? ''); ?>"
													   data-vehicle-year="<?php echo htmlspecialchars($eq['vehicle_year'] ?? ''); ?>"
													   data-make="<?php echo htmlspecialchars($eq['make'] ?? ''); ?>"
													   data-model="<?php echo htmlspecialchars($eq['model'] ?? ''); ?>"
													   data-engine="<?php echo htmlspecialchars($eq['engine'] ?? ''); ?>"
													   data-engine-serial-number="<?php echo htmlspecialchars($eq['engine_serial_number'] ?? ''); ?>"
													   data-transmission="<?php echo htmlspecialchars($eq['transmission'] ?? ''); ?>"
													   data-trans-serial-number="<?php echo htmlspecialchars($eq['trans_serial_number'] ?? ''); ?>"
													   data-transfer-case-serial="<?php echo htmlspecialchars($eq['transfer_case_serial'] ?? ''); ?>"
													   data-front-differential-serial="<?php echo htmlspecialchars($eq['front_differential_serial'] ?? ''); ?>"
													   data-middle-differential-serial="<?php echo htmlspecialchars($eq['middle_differential_serial'] ?? ''); ?>"
													   data-rear-differential-serial="<?php echo htmlspecialchars($eq['rear_differential_serial'] ?? ''); ?>"
													   data-dhcst-equipment-number="<?php echo htmlspecialchars($eq['dhcst_equipment_number'] ?? ''); ?>"
													   data-dhss-equipment-number="<?php echo htmlspecialchars($eq['dhss_equipment_number'] ?? ''); ?>"
													   data-original-index="<?php echo (int)$eqIndex; ?>"
													   data-sort-equipment-number="<?php echo htmlspecialchars($eqNumSort); ?>"
													   data-sort-operating-condition="<?php echo htmlspecialchars($opState); ?>"
													   data-sort-oil-status="<?php echo htmlspecialchars($oilState); ?>"
													   data-sort-current-hours="<?php echo htmlspecialchars((string)$hoursSort); ?>"
													   data-warranty-href="<?php echo htmlspecialchars('Warranty.php?id=' . (int)$eq['equipment_id'], ENT_QUOTES); ?>"
													>
														<td>
															<div class="equipment-number-cell">
																<?php if (!empty($canEditEquipments)): ?>
																	<button type="button" class="equipment-number equipment-open-edit" title="Edit equipment">
																		<?php echo htmlspecialchars((string)($eq['dhss_equipment_number'] ?? '')); ?>
																	</button>
																<?php else: ?>
																	<span class="equipment-number">
																		<?php echo htmlspecialchars((string)($eq['dhss_equipment_number'] ?? '')); ?>
																	</span>
																<?php endif; ?>
																<span class="equipment-edit-icon admin-only" title="Edit equipment">Edit</span>
															</div>
														</td>
														<td>
															<div class="equipment-inline-cell">
																<span><?php echo htmlspecialchars((string)($eq['type'] ?? '')); ?></span>
																<span class="cell-edit-icon cell-edit-type admin-only" title="Edit type">Edit</span>
															</div>
														</td>
														<td>
															<div class="equipment-inline-cell">
																<span><?php echo htmlspecialchars((string)($eq['location'] ?? '')); ?></span>
																<span class="cell-edit-icon cell-edit-location admin-only" title="Edit location">Edit</span>
															</div>
														</td>
														<td>
															<div class="equipment-hours-cell">
																<span class="equipment-hours"><?php echo htmlspecialchars((string)($eq['current_hours'] ?? '0')); ?></span>
																<span class="hours-edit-icon admin-only" title="Edit hours">Edit</span>
															</div>
														</td>
														<td>
															<?php $val = trim((string)($eq['operating_condition'] ?? '')); ?>
															<?php
															$svgMap = [
																'green' => 'greenengine.svg',
																'yellow' => 'yellowengine.svg',
																'red' => 'redengine.svg'
															];
															?>
															<?php if ($val === '' || !isset($svgMap[$val])): ?>
																<span class="equipment-pill equipment-pill--neutral">—</span>
															<?php else: ?>
																<a href="equipment.php?id=<?php echo (int)$eq['equipment_id']; ?>">
																	<img src="images/<?php echo htmlspecialchars($svgMap[$val]); ?>" alt="<?php echo htmlspecialchars($val); ?> engine" style="height:28px;vertical-align:middle;" />
																</a>
															<?php endif; ?>
														</td>
														<?php $val = trim((string)($eq['oil_status'] ?? ''));
															$oilHref = 'oil_status.php?id=' . (int)$eq['equipment_id']; ?>
														<td <?php if ($val !== '' ) { echo 'onclick="window.location=\'' . htmlspecialchars($oilHref, ENT_QUOTES) . '\';" style="cursor:pointer;"'; } ?>>
															<?php
															$oilSvgMap = [
																'green' => 'greenoil.svg',
																'yellow' => 'yellowoil.svg',
																'red' => 'redoil.svg'
															];
															?>
															<?php if ($val === '' || !isset($oilSvgMap[$val])): ?>
																<span class="equipment-pill equipment-pill--neutral">—</span>
															<?php else: ?>
																<a class="oil-status-link" href="oil_status.php?id=<?php echo (int)$eq['equipment_id']; ?>">
																	<img src="images/<?php echo htmlspecialchars($oilSvgMap[$val]); ?>" alt="<?php echo htmlspecialchars($val); ?> oil" style="height:28px;vertical-align:middle;" />
																</a>
															<?php endif; ?>
														</td>
														<?php
														$filterCondition = isset($eq['air_filters_condition_pct']) ? (float)$eq['air_filters_condition_pct'] : null;
														$filterStatus = $eq['air_filters_status'] ?? ($eq['air_filters'] ?? null);
														$filterHref = 'Airfilters.php?id=' . (int)$eq['equipment_id'];
														$filterSvgMap = [
															'green' => 'greenfilter.svg',
															'yellow' => 'yellowfilter.svg',
															'red' => 'redfilter.svg'
														];
														?>
														<td class="airfilters-cell" <?php if ($filterStatus !== null && isset($filterSvgMap[$filterStatus])) { echo 'onclick="window.location=\'' . htmlspecialchars($filterHref, ENT_QUOTES) . '\';" style="cursor:pointer;"'; } ?>>
															<?php if ($filterStatus === null || !isset($filterSvgMap[$filterStatus])): ?>
																<span class="equipment-pill equipment-pill--neutral">—</span>
															<?php else: ?>
																<a class="airfilters-status-link" href="Airfilters.php?id=<?php echo (int)$eq['equipment_id']; ?>" title="Air filters condition: <?php echo htmlspecialchars((string)$filterCondition); ?>%">
																	<img src="images/<?php echo htmlspecialchars($filterSvgMap[$filterStatus]); ?>" alt="<?php echo htmlspecialchars($filterStatus); ?> air filter" style="height:28px;vertical-align:middle;" />
																</a>
															<?php endif; ?>
														</td>
														<td style="text-align:center;">
															<?php $tiresHref = 'Tires.php?id=' . (int)$eq['equipment_id']; ?>
															<a href="<?php echo htmlspecialchars($tiresHref, ENT_QUOTES); ?>" title="View tires" style="display:inline-block;">
																<img src="images/tires.svg" alt="Tires" style="height:28px;vertical-align:middle;" />
															</a>
														</td>
														<td>
															<div class="warranty-cell">
																<?php
																$warrantyFiles = 0;
																$stmtWarranty = $conn->prepare("SELECT COUNT(*) as cnt FROM equipment_uploads WHERE equipment_id = ? AND field = 'warranty'");
																$stmtWarranty->bind_param('i', $eq['equipment_id']);
																$stmtWarranty->execute();
																$resWarranty = $stmtWarranty->get_result();
																if ($rowWarranty = $resWarranty->fetch_assoc()) {
																	$warrantyFiles = (int)$rowWarranty['cnt'];
																}
																$stmtWarranty->close();
																?>
																<span class="warranty-display">
																	<?php if ($warrantyFiles > 0): ?>
																		<a href="Warranty.php?id=<?php echo (int)$eq['equipment_id']; ?>" title="View warranty" style="display:inline-block;">
																			<img src="images/warrenty.svg" alt="Warranty" style="height:28px;vertical-align:middle;" />
																		</a>
																	<?php else: ?>
																		<img src="images/nowarrenty.svg" alt="No warranty" title="No warranty uploaded" style="height:28px;vertical-align:middle;opacity:0.7;" />
																	<?php endif; ?>
																</span>
																<?php if (!empty($canEditEquipments) && $warrantyFiles === 0): ?>
																	<button type="button" class="warranty-add-btn" title="Upload warranty">Add</button>
																<?php endif; ?>
															</div>
														</td>
													</tr>
													<?php $eqIndex++; ?>
												<?php endforeach; ?>
											<?php endif; ?>
										</tbody>
									</table>
									<div id="equipSortMenu" class="equip-sort-menu" aria-hidden="true"></div>
								</div>
							</div>
						</div>
					</section>
				</div>
			</main>
		</div>
	</div>

	<!-- Add Equipment Modal -->
	<div id="newEquipmentModal" class="equipment-modal" aria-hidden="true">
		<div class="equipment-modal__dialog" role="dialog" aria-modal="true" aria-label="Add equipment">
			<div class="equipment-modal__header">
				<h3 class="equipment-modal__title">Add Equipment</h3>
				<button id="closeNewEquipmentModal" class="equipment-icon-btn" type="button" aria-label="Close">×</button>
			</div>
			<form id="newEquipmentForm" class="equipment-form" enctype="multipart/form-data">
				<div class="equipment-form__grid">
					<div class="equipment-form__field">
						<label for="eq_dhss_equipment_number">Equipment # (DHSS)</label>
						<input id="eq_dhss_equipment_number" name="dhss_equipment_number" type="text" required />
					</div>
					<div class="equipment-form__field">
						<label for="eq_type">Type</label>
						<input id="eq_type" name="type" type="text" required list="equipment-type-list" autocomplete="off" />
						<datalist id="equipment-type-list">
							<?php
							// Collect unique types for datalist
							$uniqueTypes = [];
							foreach ($equipments as $eq) {
								$type = trim($eq['type'] ?? '');
								if ($type !== '' && !in_array($type, $uniqueTypes, true)) {
									$uniqueTypes[] = $type;
								}
							}
							foreach ($uniqueTypes as $type) {
								echo '<option value="' . htmlspecialchars($type) . '"></option>';
							}
						?>
						</datalist>
					</div>
					<div class="equipment-form__field">
						<label for="eq_operating_condition">Operating Condition</label>
						<select id="eq_operating_condition" name="operating_condition">
							<option value="">Select...</option>
							<option value="green">Green</option>
							<option value="yellow">Yellow</option>
							<option value="red">Red</option>
						</select>
					</div>
					<div class="equipment-form__field">
						<label for="eq_location">Location</label>
						<input id="eq_location" name="location" type="text" />
					</div>
					<div class="equipment-form__field">
						<label for="eq_current_hours">Current Hours</label>
						<input id="eq_current_hours" name="current_hours" type="number" step="0.1" min="0" value="0" />
					</div>
					<div class="equipment-form__field">
						<label for="eq_oil_status">Oil Status</label>
						<select id="eq_oil_status" name="oil_status">
							<option value="">Select...</option>
							<option value="green">Green</option>
							<option value="yellow">Yellow</option>
							<option value="red">Red</option>
						</select>
					</div>
					<div class="equipment-form__field">
						<label for="eq_air_filters_status">Air Filter Status</label>
						<select id="eq_air_filters_status" name="air_filters">
							<option value="">Select...</option>
							<option value="green">Green</option>
							<option value="yellow">Yellow</option>
							<option value="red">Red</option>
						</select>
					</div>
					<div class="equipment-form__field">
						<label for="eq_warranty">Warranty</label>
						<input id="eq_warranty" name="warranty" type="file" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" />
					</div>
					<!-- DHCST and DHSS Equipment # moved to Additional Details -->
					<hr style="grid-column:1/-1;margin:18px 0 8px 0;border:0;border-top:1.5px solid #e5e7eb;background:none;">
					<div style="display:flex;justify-content:flex-end;align-items:center;grid-column:1/-1;margin-bottom:8px;">
						<button type="button" id="showAdditionalDetailsBtn" style="height:38px;display:flex;align-items:center;gap:4px;padding:0 14px;border-radius:8px;border:1px solid #e5e7eb;background:#f3f4f6;font-weight:600;cursor:pointer;">
							<span>Additional Details</span>
							<span id="additionalDetailsIcon" style="font-size:18px;transition:transform 0.2s;">▼</span>
						</button>
					</div>
					<div id="additionalDetailsFields" style="display:none;grid-column:1/-1;gap:24px;">
						<div class="equipment-form__field">
							<label for="eq_dhcst_equipment_number">DHCST Equipment #</label>
							<input id="eq_dhcst_equipment_number" name="dhcst_equipment_number" type="text" />
						</div>
						<div class="equipment-form__field">
							<label for="eq_dhss_equipment_number_display">DHSS Equipment #</label>
							<input id="eq_dhss_equipment_number_display" type="text" readonly disabled style="background:#f3f4f6;" />
						</div>
						<div class="equipment-form__field">
							<label for="eq_vin">VIN Number</label>
							<input id="eq_vin" name="vin" type="text" />
						</div>
						<div class="equipment-form__field">
							<label for="eq_vehicle_year">Year</label>
							<input id="eq_vehicle_year" name="vehicle_year" type="text" />
						</div>
						<div class="equipment-form__field">
							<label for="eq_make">Make</label>
							<input id="eq_make" name="make" type="text" />
						</div>
						<div class="equipment-form__field">
							<label for="eq_model">Model</label>
							<input id="eq_model" name="model" type="text" />
						</div>
						<div class="equipment-form__field">
							<label for="eq_engine">Engine</label>
							<input id="eq_engine" name="engine" type="text" />
						</div>
						<div class="equipment-form__field">
							<label for="eq_engine_serial_number">Engine Serial Number</label>
							<input id="eq_engine_serial_number" name="engine_serial_number" type="text" />
						</div>
						<div class="equipment-form__field">
							<label for="eq_transmission">Transmission</label>
							<input id="eq_transmission" name="transmission" type="text" />
						</div>
						<div class="equipment-form__field">
							<label for="eq_trans_serial_number">Transmission Serial Number</label>
							<input id="eq_trans_serial_number" name="trans_serial_number" type="text" />
						</div>
						<div class="equipment-form__field">
							<label for="eq_transfer_case_serial">TRANSFER CASE SERIAL</label>
							<input id="eq_transfer_case_serial" name="transfer_case_serial" type="text" />
						</div>
						<div class="equipment-form__field">
							<label for="eq_front_differential_serial">FRONT DIFFERENTIAL SERIAL</label>
							<input id="eq_front_differential_serial" name="front_differential_serial" type="text" />
						</div>
						<div class="equipment-form__field">
							<label for="eq_middle_differential_serial">MIDDLE DIFFERENTIAL SERIAL</label>
							<input id="eq_middle_differential_serial" name="middle_differential_serial" type="text" />
						</div>
						<div class="equipment-form__field">
							<label for="eq_rear_differential_serial">REAR DIFFERENTIAL SERIAL</label>
							<input id="eq_rear_differential_serial" name="rear_differential_serial" type="text" />
						</div>
					</div>
				</div>
				<div class="equipment-form__actions">
					<button id="cancelNewEquipment" class="equipment-btn equipment-btn--secondary" type="button">Cancel</button>
					<button id="saveNewEquipment" class="equipment-btn" type="submit">Save</button>
				</div>
				<div id="newEquipmentError" class="equipment-form__error" role="alert" style="display:none;"></div>
			</form>
		</div>
	</div>

	<!-- Edit Equipment Modal -->
	<div id="editEquipmentModal" class="equipment-modal" aria-hidden="true">
		<div class="equipment-modal__dialog" role="dialog" aria-modal="true" aria-label="Edit equipment">
			<div class="equipment-modal__header equipment-modal__header--edit">
				<h3 class="equipment-modal__title">Edit Equipment</h3>
				<button id="closeEditEquipmentModal" class="equipment-icon-btn" type="button" aria-label="Close">×</button>
			</div>
			   <form id="editEquipmentForm" class="equipment-form" enctype="multipart/form-data">
				   <input type="hidden" id="edit_equipment_id" name="equipment_id" />
				   <?php
				   // Prepare to show previews of uploaded files for the selected equipment in the edit modal
				   $editUploads = [
				   	   'warranty' => []
				   	];
				   if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
					   $eid = (int)$_GET['edit_id'];
					   $stmt = $conn->prepare("SELECT field, file_url, id FROM equipment_uploads WHERE equipment_id = ?");
					   $stmt->bind_param('i', $eid);
					   $stmt->execute();
					   $res = $stmt->get_result();
					   while ($row = $res->fetch_assoc()) {
						   $f = $row['field'];
						   if (isset($editUploads[$f])) $editUploads[$f][] = $row;
					   }
					   $stmt->close();
				   }
				   ?>
				   <div class="equipment-form__grid">
				   	   <div class="equipment-form__field">
					<label for="edit_dhss_equipment_number">Equipment # (DHSS)</label>
					<input id="edit_dhss_equipment_number" name="dhss_equipment_number" type="text" required />
				   	   </div>
				   	   <div class="equipment-form__field">
				   	   	   <label for="edit_type">Type</label>
				   	   	   <input id="edit_type" name="type" type="text" required />
				   	   </div>
				   	   <div class="equipment-form__field">
				   	   	   <label for="edit_current_hours">Current Hours</label>
				   	   	   <input id="edit_current_hours" name="current_hours" type="number" step="0.1" min="0" />
				   	   </div>
				   	   <div class="equipment-form__field">
				   	   	   <label for="edit_location">Location</label>
				   	   	   <input id="edit_location" name="location" type="text" />
				   	   </div>
				   	   <!-- Operating Condition and Oil Status removed from Edit modal per request -->
				   	   <div class="equipment-form__field">
				   	   	   <label for="edit_warranty">Warranty</label>
				   	   	   <label class="equipment-file-label add-more-btn" id="warranty_file_label" for="edit_warranty">
				   	   	   	   Add More
				   	   	   	   <input id="edit_warranty" name="warranty[]" type="file" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" multiple style="display:none;" />
				   	   	   </label>
				   	   	   <div class="equipment-upload-preview" data-field="warranty"></div>
				   	   </div>
					   <!-- DHCST and DHSS Equipment # moved to Additional Details -->
					   <hr style="grid-column:1/-1;margin:18px 0 8px 0;border:0;border-top:1.5px solid #e5e7eb;background:none;">
					   <div style="display:flex;justify-content:flex-end;align-items:center;grid-column:1/-1;margin-bottom:8px;">
						   <button type="button" id="showEditAdditionalDetailsBtn" style="height:38px;display:flex;align-items:center;gap:4px;padding:0 14px;border-radius:8px;border:1px solid #e5e7eb;background:#f3f4f6;font-weight:600;cursor:pointer;">
							   <span>Additional Details</span>
							   <span id="editAdditionalDetailsIcon" style="font-size:18px;transition:transform 0.2s;">▼</span>
						   </button>
					   </div>
					   <div id="editAdditionalDetailsFields" style="display:none;grid-column:1/-1;gap:24px;">
						   <div class="equipment-form__field">
							   <label for="edit_dhcst_equipment_number">DHCST Equipment #</label>
							   <input id="edit_dhcst_equipment_number" name="dhcst_equipment_number" type="text" />
						   </div>
						   <!-- DHSS Equipment # field is shown above; avoid duplicate input here -->
						   <div class="equipment-form__field">
							   <label for="edit_vin">VIN Number</label>
							   <input id="edit_vin" name="vin" type="text" />
						   </div>
						   <div class="equipment-form__field">
							   <label for="edit_vehicle_year">Year</label>
							   <input id="edit_vehicle_year" name="vehicle_year" type="text" />
						   </div>
						   <div class="equipment-form__field">
							   <label for="edit_make">Make</label>
							   <input id="edit_make" name="make" type="text" />
						   </div>
						   <div class="equipment-form__field">
							   <label for="edit_model">Model</label>
							   <input id="edit_model" name="model" type="text" />
						   </div>
						   <div class="equipment-form__field">
							   <label for="edit_engine">Engine</label>
							   <input id="edit_engine" name="engine" type="text" />
						   </div>
						   <div class="equipment-form__field">
							   <label for="edit_engine_serial_number">Engine Serial Number</label>
							   <input id="edit_engine_serial_number" name="engine_serial_number" type="text" />
						   </div>
						   <div class="equipment-form__field">
							   <label for="edit_transmission">Transmission</label>
							   <input id="edit_transmission" name="transmission" type="text" />
						   </div>
						   <div class="equipment-form__field">
							   <label for="edit_trans_serial_number">Transmission Serial Number</label>
							   <input id="edit_trans_serial_number" name="trans_serial_number" type="text" />
						   </div>
						   <div class="equipment-form__field">
							   <label for="edit_transfer_case_serial">TRANSFER CASE SERIAL</label>
							   <input id="edit_transfer_case_serial" name="transfer_case_serial" type="text" />
						   </div>
						   <div class="equipment-form__field">
							   <label for="edit_front_differential_serial">FRONT DIFFERENTIAL SERIAL</label>
							   <input id="edit_front_differential_serial" name="front_differential_serial" type="text" />
						   </div>
						   <div class="equipment-form__field">
							   <label for="edit_middle_differential_serial">MIDDLE DIFFERENTIAL SERIAL</label>
							   <input id="edit_middle_differential_serial" name="middle_differential_serial" type="text" />
						   </div>
						   <div class="equipment-form__field">
							   <label for="edit_rear_differential_serial">REAR DIFFERENTIAL SERIAL</label>
							   <input id="edit_rear_differential_serial" name="rear_differential_serial" type="text" />
						   </div>
					   </div>
				   </div>
				   <div class="equipment-form__actions">
					   <button id="cancelEditEquipment" class="equipment-btn equipment-btn--secondary" type="button">Cancel</button>
					   <button id="deleteEditEquipment" class="equipment-btn equipment-btn--danger" type="button" style="margin-right:auto;background:#dc2626;color:#fff;">Delete</button>
					   <button id="saveEditEquipment" class="equipment-btn" type="submit">Update Equipment</button>
				   </div>
				<div id="editEquipmentError" class="equipment-form__error" role="alert" style="display:none;"></div>
			</form>
		</div>
	</div>

	<!-- Edit Hours Modal -->
	<div id="hoursModal" class="equipment-modal" aria-hidden="true">
		<div class="equipment-modal__dialog hours-modal__dialog" role="dialog" aria-modal="true" aria-label="Edit equipment hours">
			<div class="equipment-modal__header hours-modal__header">
				<h3 class="equipment-modal__title">Edit Current Hours</h3>
				<button id="closeHoursModal" class="equipment-icon-btn" type="button" aria-label="Close">×</button>
			</div>
			<form id="hoursForm" class="equipment-form hours-modal__form">
				<input type="hidden" id="hours_equipment_id" name="equipment_id" />
				<div class="hours-modal__grid">
					<label class="equipment-form__field">
						<span style="font-weight:700;color:#374151;margin-bottom:6px;">Current Hours</span>
						<input id="hours_value" name="current_hours" type="number" step="0.1" min="0" required />
					</label>
				</div>
				<div class="equipment-form__actions" style="padding-top:8px;">
					<button id="cancelHoursEdit" class="equipment-btn equipment-btn--secondary" type="button">Cancel</button>
					<button id="saveHoursBtn" class="equipment-btn" type="submit">Save</button>
				</div>
				<div id="hoursError" class="equipment-form__error" role="alert" style="display:none;"></div>
			</form>
		</div>
	</div>

	<!-- Edit Type Modal -->
	<div id="typeModal" class="equipment-modal" aria-hidden="true">
		<div class="equipment-modal__dialog type-modal__dialog" role="dialog" aria-modal="true" aria-label="Edit equipment type">
			<div class="equipment-modal__header type-modal__header">
				<h3 class="equipment-modal__title">Edit Type</h3>
				<button id="closeTypeModal" class="equipment-icon-btn" type="button" aria-label="Close">×</button>
			</div>
			<form id="typeForm" class="equipment-form inline-modal__form">
				<input type="hidden" id="type_equipment_id" name="equipment_id" />
				<div class="inline-modal__grid">
					<label class="equipment-form__field">
						<span style="font-weight:700;color:#374151;margin-bottom:6px;">Type</span>
						<input id="type_value" name="type" type="text" required />
					</label>
				</div>
				<div class="equipment-form__actions" style="padding-top:8px;">
					<button id="cancelTypeEdit" class="equipment-btn equipment-btn--secondary" type="button">Cancel</button>
					<button id="saveTypeBtn" class="equipment-btn" type="submit">Save</button>
				</div>
				<div id="typeError" class="equipment-form__error" role="alert" style="display:none;"></div>
			</form>
		</div>
	</div>

	<!-- Edit Location Modal -->
	<div id="locationModal" class="equipment-modal" aria-hidden="true">
		<div class="equipment-modal__dialog location-modal__dialog" role="dialog" aria-modal="true" aria-label="Edit equipment location">
			<div class="equipment-modal__header location-modal__header">
				<h3 class="equipment-modal__title">Edit Location</h3>
				<button id="closeLocationModal" class="equipment-icon-btn" type="button" aria-label="Close">×</button>
			</div>
			<form id="locationForm" class="equipment-form inline-modal__form">
				<input type="hidden" id="location_equipment_id" name="equipment_id" />
				<div class="inline-modal__grid">
					<label class="equipment-form__field">
						<span style="font-weight:700;color:#374151;margin-bottom:6px;">Location</span>
						<input id="location_value" name="location" type="text" required />
					</label>
				</div>
				<div class="equipment-form__actions" style="padding-top:8px;">
					<button id="cancelLocationEdit" class="equipment-btn equipment-btn--secondary" type="button">Cancel</button>
					<button id="saveLocationBtn" class="equipment-btn" type="submit">Save</button>
				</div>
				<div id="locationError" class="equipment-form__error" role="alert" style="display:none;"></div>
			</form>
		</div>
	</div>

	<!-- Add Warranty Modal -->
	<div id="warrantyModal" class="equipment-modal" aria-hidden="true">
		<div class="equipment-modal__dialog" role="dialog" aria-modal="true" aria-label="Add warranty">
			<div class="equipment-modal__header" style="background:#0ea5e9;">
				<h3 class="equipment-modal__title">Add Warranty</h3>
				<button id="closeWarrantyModal" class="equipment-icon-btn" type="button" aria-label="Close">×</button>
			</div>
			<form id="warrantyForm" class="equipment-form" enctype="multipart/form-data">
				<input type="hidden" id="warranty_equipment_id" name="equipment_id" />
				<div class="equipment-form__grid" style="grid-template-columns:1fr;">
					<div class="equipment-form__field">
						<label for="warranty_files">Upload Files</label>
						<input id="warranty_files" name="files[]" type="file" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" />
					</div>
				</div>
				<div class="equipment-form__actions">
					<button id="cancelWarranty" class="equipment-btn equipment-btn--secondary" type="button">Cancel</button>
					<button id="saveWarranty" class="equipment-btn" type="submit">Upload</button>
				</div>
				<div id="warrantyError" class="equipment-form__error" role="alert" style="display:none;"></div>
			</form>
		</div>
	</div>

	<script>
		(function(){
			'use strict';

			// Sort functionality
			var tbody = document.querySelector('.equipment-table tbody');
			var buttons = document.querySelectorAll('.equip-sort-btn');
			var sortMenu = document.getElementById('equipSortMenu');
			var menuOpen = false;
			var currentSortKey = null;

			function closeMenu(){
				if (!menuOpen) return;
				sortMenu.style.display = 'none';
				sortMenu.setAttribute('aria-hidden','true');
				sortMenu.innerHTML = '';
				menuOpen = false;
				currentSortKey = null;
			}

			function getRows(){
				return Array.prototype.slice.call(tbody.querySelectorAll('tr'))
					.filter(function(tr){ return tr.getAttribute('data-equipment-id'); });
			}

			function stableFallback(a, b){
				var ai = parseInt(a.getAttribute('data-original-index') || '0', 10);
				var bi = parseInt(b.getAttribute('data-original-index') || '0', 10);
				return ai - bi;
			}

			function replaceRows(rows){
				var emptyRow = tbody.querySelector('tr:not([data-equipment-id])');
				rows.forEach(function(tr){ tbody.appendChild(tr); });
				if (emptyRow) tbody.appendChild(emptyRow);
			}

			function applyTextSort(direction){
				var rows = getRows();
				rows.sort(function(a, b){
					var av = (a.getAttribute('data-sort-equipment-number') || '').toLowerCase();
					var bv = (b.getAttribute('data-sort-equipment-number') || '').toLowerCase();
					if (av < bv) return direction === 'asc' ? -1 : 1;
					if (av > bv) return direction === 'asc' ? 1 : -1;
					return stableFallback(a, b);
				});
				replaceRows(rows);
			}

			function applyHoursSort(direction){
				var rows = getRows();
				rows.sort(function(a, b){
					var av = parseFloat(a.getAttribute('data-sort-current-hours') || '0');
					var bv = parseFloat(b.getAttribute('data-sort-current-hours') || '0');
					if (av < bv) return direction === 'asc' ? -1 : 1;
					if (av > bv) return direction === 'asc' ? 1 : -1;
					return stableFallback(a, b);
				});
				replaceRows(rows);
			}

			function applyStatusSort(key, preferred){
				var order;
				if (preferred === 'good') order = ['good', 'warn', 'bad', 'neutral'];
				else if (preferred === 'warn') order = ['warn', 'good', 'bad', 'neutral'];
				else order = ['bad', 'warn', 'good', 'neutral'];

				var rows = getRows();
				rows.sort(function(a, b){
					var attr = key === 'operating_condition' ? 'data-sort-operating-condition' : 'data-sort-oil-status';
					var av = a.getAttribute(attr) || 'neutral';
					var bv = b.getAttribute(attr) || 'neutral';
					var ai = order.indexOf(av); if (ai === -1) ai = order.length;
					var bi = order.indexOf(bv); if (bi === -1) bi = order.length;
					if (ai < bi) return -1;
					if (ai > bi) return 1;
					return stableFallback(a, b);
				});
				replaceRows(rows);
			}

			var currentTypeFilter = 'all';

			function applyTypeFilter(typeValue){
				currentTypeFilter = typeValue;
				var rows = getRows();
				rows.forEach(function(tr){
					if (typeValue === 'all') {
						tr.style.display = '';
					} else {
						var rowType = (tr.getAttribute('data-type') || '').trim();
						if (rowType.toLowerCase() === typeValue.toLowerCase()) {
							tr.style.display = '';
						} else {
							tr.style.display = 'none';
						}
					}
				});
			}

			function openMenuFor(btn, key){
				currentSortKey = key;
				sortMenu.innerHTML = '';
				sortMenu.setAttribute('aria-hidden','false');
				sortMenu.style.display = 'block';
				menuOpen = true;

				var rect = btn.getBoundingClientRect();
				var menuWidth = 190;
				var scrollX = window.pageXOffset || window.scrollX || 0;
				var scrollY = window.pageYOffset || window.scrollY || 0;
				var left = rect.left + scrollX;
				var top = rect.bottom + scrollY + 6;
				var maxLeft = (scrollX + window.innerWidth) - menuWidth - 10;
				if (left > maxLeft) left = Math.max(10, maxLeft);
				sortMenu.style.left = left + 'px';
				sortMenu.style.top = top + 'px';

				function addOption(label, action, isActive){
					var opt = document.createElement('button');
					opt.type = 'button';
					opt.className = 'equip-sort-option';
					if (isActive) {
						opt.style.background = 'rgba(15, 23, 42, 0.08)';
						opt.style.fontWeight = '900';
					}
					opt.textContent = label;
					opt.addEventListener('click', function(e){
						e.preventDefault();
						e.stopPropagation();
						action();
						closeMenu();
					});
					sortMenu.appendChild(opt);
				}

				if (key === 'type') {
					// Get all unique types from table rows
					var rows = getRows();
					var types = [];
					var typeMap = {};
					rows.forEach(function(tr){
						var rowType = (tr.getAttribute('data-type') || '').trim();
						if (rowType && !typeMap[rowType.toLowerCase()]) {
							typeMap[rowType.toLowerCase()] = rowType;
							types.push(rowType);
						}
					});
					types.sort(function(a, b){
						return a.localeCompare(b);
					});
					
					// Add "All" option as default
					addOption('All', function(){ applyTypeFilter('all'); }, currentTypeFilter === 'all');
					// Add each unique type
					types.forEach(function(type){
						addOption(type, function(){ applyTypeFilter(type); }, currentTypeFilter.toLowerCase() === type.toLowerCase());
					});
				} else if (key === 'operating_condition' || key === 'oil_status') {
					addOption('Green', function(){ applyStatusSort(key, 'good'); });
					addOption('Yellow', function(){ applyStatusSort(key, 'warn'); });
					addOption('Red', function(){ applyStatusSort(key, 'bad'); });
				} else if (key === 'current_hours') {
					addOption('Highest', function(){ applyHoursSort('desc'); });
					addOption('Lowest', function(){ applyHoursSort('asc'); });
				} else if (key === 'equipment_number') {
					addOption('A → Z', function(){ applyTextSort('asc'); });
					addOption('Z → A', function(){ applyTextSort('desc'); });
				}
			}

			buttons.forEach(function(btn){
				btn.addEventListener('click', function(e){
					e.preventDefault();
					e.stopPropagation();
					var key = btn.getAttribute('data-sort');
					if (menuOpen && currentSortKey === key) {
						closeMenu();
						return;
					}
					openMenuFor(btn, key);
				});
			});

			document.addEventListener('click', function(e){ 
				// Don't close if clicking inside the sort menu
				if (menuOpen && sortMenu && !sortMenu.contains(e.target)) {
					closeMenu();
				}
			});
			document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && menuOpen) closeMenu(); });
			window.addEventListener('resize', function(){ if (menuOpen) closeMenu(); });
			window.addEventListener('scroll', function(){ if (menuOpen) closeMenu(); }, true);

			// Add Equipment Modal functionality
			var newBtn = document.getElementById('newEquipmentBtn');
			var newModal = document.getElementById('newEquipmentModal');
			var closeNewBtn = document.getElementById('closeNewEquipmentModal');
			var cancelNewBtn = document.getElementById('cancelNewEquipment');
			var newForm = document.getElementById('newEquipmentForm');
			var newErrBox = document.getElementById('newEquipmentError');
			var saveNewBtn = document.getElementById('saveNewEquipment');

			function openNewModal(){
				if (!newModal) return;
				newModal.classList.add('is-open');
				newModal.setAttribute('aria-hidden','false');
				if (newErrBox) { newErrBox.style.display = 'none'; newErrBox.textContent = ''; }
				var first = document.getElementById('eq_equipment_number');
				if (first) first.focus();
			}

			function closeNewModal(){
				if (!newModal) return;
				newModal.classList.remove('is-open');
				newModal.setAttribute('aria-hidden','true');
				if (newForm) newForm.reset();
				if (newErrBox) { newErrBox.style.display = 'none'; newErrBox.textContent = ''; }
			}

			if (newBtn) newBtn.addEventListener('click', function(e){ 
				e.preventDefault();
				e.stopPropagation();
				openNewModal(); 
			});
			if (closeNewBtn) closeNewBtn.addEventListener('click', function(){ closeNewModal(); });
			if (cancelNewBtn) cancelNewBtn.addEventListener('click', function(){ closeNewModal(); });
			if (newModal) newModal.addEventListener('click', function(e){ if (e.target === newModal) closeNewModal(); });
			document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && newModal && newModal.classList.contains('is-open')) closeNewModal(); });

			var dhssPrimary = document.getElementById('eq_dhss_equipment_number');
			var dhssDisplay = document.getElementById('eq_dhss_equipment_number_display');
			function syncDhssDisplay(){
				if (!dhssPrimary || !dhssDisplay) return;
				dhssDisplay.value = dhssPrimary.value;
			}
			if (dhssPrimary && dhssDisplay) {
				dhssPrimary.addEventListener('input', syncDhssDisplay);
				dhssPrimary.addEventListener('change', syncDhssDisplay);
				syncDhssDisplay();
			}
			if (newForm && dhssDisplay) {
				newForm.addEventListener('reset', function(){ dhssDisplay.value = ''; });
			}

			if (newForm) newForm.addEventListener('submit', function(e){
				e.preventDefault();
				if (saveNewBtn) { saveNewBtn.disabled = true; saveNewBtn.textContent = 'Saving...'; }
				if (newErrBox) { newErrBox.style.display = 'none'; newErrBox.textContent = ''; }

				var fd = new FormData(newForm);
				fetch('../../api/add_equipment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function(r){ return r.json().then(function(j){ return { ok: r.ok, json: j }; }); })
					.then(function(res){
						if (!res.ok || !res.json || !res.json.success) {
							var msg = (res.json && res.json.message) ? res.json.message : 'Failed to save equipment';
							if (newErrBox) { newErrBox.textContent = msg; newErrBox.style.display = 'block'; }
							return;
						}
						window.location.reload();
					})
					.catch(function(){
						if (newErrBox) { newErrBox.textContent = 'Network error while saving'; newErrBox.style.display = 'block'; }
					})
					.finally(function(){
						if (saveNewBtn) { saveNewBtn.disabled = false; saveNewBtn.textContent = 'Save'; }
					});
			});

			// Edit Equipment Modal functionality
			var editModal = document.getElementById('editEquipmentModal');
			var closeEditBtn = document.getElementById('closeEditEquipmentModal');
			var cancelEditBtn = document.getElementById('cancelEditEquipment');
			var editForm = document.getElementById('editEquipmentForm');
			var editErrBox = document.getElementById('editEquipmentError');
			var saveEditBtn = document.getElementById('saveEditEquipment');


			function openEditModal(row){
				if (!editModal || !row) return;
				var equipmentId = row.getAttribute('data-equipment-id');
				var equipmentNumber = row.getAttribute('data-equipment-number');
				var type = row.getAttribute('data-type');
				var operatingCondition = row.getAttribute('data-operating-condition');
				var location = row.getAttribute('data-location');
				var currentHours = row.getAttribute('data-current-hours');
				var oilStatus = row.getAttribute('data-oil-status');


				document.getElementById('edit_equipment_id').value = equipmentId || '';
				document.getElementById('edit_dhss_equipment_number').value = equipmentNumber || '';
				document.getElementById('edit_type').value = type || '';
				document.getElementById('edit_location').value = location || '';
				document.getElementById('edit_current_hours').value = currentHours || '0';
				// Operating condition and oil status fields removed from edit modal
				// DHCST and DHSS Equipment Number
				var dhcst = row.getAttribute('data-dhcst-equipment-number') || '';
				var dhcstInput = document.getElementById('edit_dhcst_equipment_number');
				if (dhcstInput) dhcstInput.value = dhcst;

				// Additional Details fields (clear by default)
				var additionalFields = [
					'vin',
					'vehicle_year',
					'make',
					'model',
					'engine',
					'engine_serial_number',
					'transmission',
					'trans_serial_number',
					'transfer_case_serial',
					'front_differential_serial',
					'middle_differential_serial',
					'rear_differential_serial'
				];
				additionalFields.forEach(function(field) {
					var el = document.getElementById('edit_' + field);
					if (el) el.value = row.getAttribute('data-' + field.replace(/_/g, '-')) || '';
				});

				// Clear previous previews
				['warranty'].forEach(function(field){
					var preview = document.querySelector('.equipment-upload-preview[data-field="'+field+'"]');
					if (preview) preview.innerHTML = '';
				});

				// Fetch and show uploaded files for this equipment
				if (equipmentId) {
					fetch('../../api/get_equipment_uploads.php?equipment_id=' + encodeURIComponent(equipmentId))
					.then(function(r){ return r.json(); })
					.then(function(data){
						if (!data.success) return;
						   ['warranty'].forEach(function(field){
							   var preview = document.querySelector('.equipment-upload-preview[data-field="'+field+'"]');
							   var label = document.getElementById(field + '_file_label');
							   if (preview) {
								   preview.innerHTML = '';
								   var hasFiles = (data.uploads && data.uploads[field] && data.uploads[field].length > 0);
								   if (label) {
									   label.childNodes[0].nodeValue = hasFiles ? 'Add More' : 'Browse...';
								   }
								   if (hasFiles) {
									   data.uploads[field].forEach(function(file){
										   var a = document.createElement('a');
										   a.href = file.file_url;
										   a.target = '_blank';
										   a.textContent = file.file_url.split('/').pop();
										   a.style.display = 'block';
										   a.style.marginTop = '4px';
										   preview.appendChild(a);
									   });
								   }
							   }
						   });
					});
				}

				editModal.classList.add('is-open');
				editModal.setAttribute('aria-hidden','false');
				if (editErrBox) { editErrBox.style.display = 'none'; editErrBox.textContent = ''; }
				var first = document.getElementById('edit_dhss_equipment_number');
				if (first) first.focus();
			}

			function closeEditModal(){
				if (!editModal) return;
				editModal.classList.remove('is-open');
				editModal.setAttribute('aria-hidden','true');
				if (editForm) editForm.reset();
				if (editErrBox) { editErrBox.style.display = 'none'; editErrBox.textContent = ''; }
			}

			// Attach click handlers to first-column buttons and edit icons
			document.addEventListener('click', function(e){
				if (e.target.classList.contains('equipment-open-edit')) {
					var rowBtn = e.target.closest('tr');
					if (rowBtn && rowBtn.getAttribute('data-equipment-id')) {
						e.preventDefault();
						e.stopPropagation();
						openEditModal(rowBtn);
						return;
					}
				}
				if (e.target.classList.contains('equipment-edit-icon')) {
					var row = e.target.closest('tr');
					if (row && row.getAttribute('data-equipment-id')) {
						e.preventDefault();
						e.stopPropagation();
						openEditModal(row);
					}
				}
			});

			// Hours quick-edit
			var hoursModal = document.getElementById('hoursModal');
			var closeHoursBtn = document.getElementById('closeHoursModal');
			var cancelHoursBtn = document.getElementById('cancelHoursEdit');
			var hoursForm = document.getElementById('hoursForm');
			var hoursInput = document.getElementById('hours_value');
			var hoursEquipmentId = document.getElementById('hours_equipment_id');
			var hoursErrBox = document.getElementById('hoursError');
			var saveHoursBtn = document.getElementById('saveHoursBtn');

			var typeModal = document.getElementById('typeModal');
			var closeTypeBtn = document.getElementById('closeTypeModal');
			var cancelTypeBtn = document.getElementById('cancelTypeEdit');
			var typeForm = document.getElementById('typeForm');
			var typeInput = document.getElementById('type_value');
			var typeEquipmentId = document.getElementById('type_equipment_id');
			var typeErrBox = document.getElementById('typeError');
			var saveTypeBtn = document.getElementById('saveTypeBtn');

			var locationModal = document.getElementById('locationModal');
			var closeLocationBtn = document.getElementById('closeLocationModal');
			var cancelLocationBtn = document.getElementById('cancelLocationEdit');
			var locationForm = document.getElementById('locationForm');
			var locationInput = document.getElementById('location_value');
			var locationEquipmentId = document.getElementById('location_equipment_id');
			var locationErrBox = document.getElementById('locationError');
			var saveLocationBtn = document.getElementById('saveLocationBtn');

			var warrantyModal = document.getElementById('warrantyModal');
			var closeWarrantyBtn = document.getElementById('closeWarrantyModal');
			var cancelWarrantyBtn = document.getElementById('cancelWarranty');
			var warrantyForm = document.getElementById('warrantyForm');
			var warrantyEquipmentId = document.getElementById('warranty_equipment_id');
			var warrantyFilesInput = document.getElementById('warranty_files');
			var warrantyErrBox = document.getElementById('warrantyError');
			var saveWarrantyBtn = document.getElementById('saveWarranty');
			var activeWarrantyDisplay = null;

			function openHoursModal(row){
				if (!hoursModal || !row) return;
				hoursModal.classList.add('is-open');
				hoursModal.setAttribute('aria-hidden','false');
				var eqId = row.getAttribute('data-equipment-id') || '';
				var currentHours = row.getAttribute('data-current-hours') || '0';
				hoursEquipmentId.value = eqId;
				hoursInput.value = currentHours;
				if (hoursErrBox) { hoursErrBox.style.display = 'none'; hoursErrBox.textContent = ''; }
				if (hoursInput) hoursInput.focus();
			}

			function closeHoursModal(){
				if (!hoursModal) return;
				hoursModal.classList.remove('is-open');
				hoursModal.setAttribute('aria-hidden','true');
				if (hoursForm) hoursForm.reset();
				if (hoursErrBox) { hoursErrBox.style.display = 'none'; hoursErrBox.textContent = ''; }
			}

			function openTypeModal(row){
				if (!typeModal || !row) return;
				typeModal.classList.add('is-open');
				typeModal.setAttribute('aria-hidden','false');
				var eqId = row.getAttribute('data-equipment-id') || '';
				var typeVal = row.getAttribute('data-type') || '';
				typeEquipmentId.value = eqId;
				typeInput.value = typeVal;
				if (typeErrBox) { typeErrBox.style.display = 'none'; typeErrBox.textContent = ''; }
				if (typeInput) typeInput.focus();
			}

			function closeTypeModal(){
				if (!typeModal) return;
				typeModal.classList.remove('is-open');
				typeModal.setAttribute('aria-hidden','true');
				if (typeForm) typeForm.reset();
				if (typeErrBox) { typeErrBox.style.display = 'none'; typeErrBox.textContent = ''; }
			}

			function openLocationModal(row){
				if (!locationModal || !row) return;
				locationModal.classList.add('is-open');
				locationModal.setAttribute('aria-hidden','false');
				var eqId = row.getAttribute('data-equipment-id') || '';
				var locVal = row.getAttribute('data-location') || '';
				locationEquipmentId.value = eqId;
				locationInput.value = locVal;
				if (locationErrBox) { locationErrBox.style.display = 'none'; locationErrBox.textContent = ''; }
				if (locationInput) locationInput.focus();
			}

			function openWarrantyModal(row, displayEl){
				if (!warrantyModal || !row) return;
				warrantyModal.classList.add('is-open');
				warrantyModal.setAttribute('aria-hidden','false');
				var eqId = row.getAttribute('data-equipment-id') || '';
				warrantyEquipmentId.value = eqId;
				activeWarrantyDisplay = displayEl || null;
				if (warrantyErrBox) { warrantyErrBox.style.display = 'none'; warrantyErrBox.textContent = ''; }
				if (warrantyFilesInput) warrantyFilesInput.value = '';
			}

			function closeWarrantyModal(){
				if (!warrantyModal) return;
				warrantyModal.classList.remove('is-open');
				warrantyModal.setAttribute('aria-hidden','true');
				if (warrantyForm) warrantyForm.reset();
				if (warrantyErrBox) { warrantyErrBox.style.display = 'none'; warrantyErrBox.textContent = ''; }
				activeWarrantyDisplay = null;
			}

			function closeLocationModal(){
				if (!locationModal) return;
				locationModal.classList.remove('is-open');
				locationModal.setAttribute('aria-hidden','true');
				if (locationForm) locationForm.reset();
				if (locationErrBox) { locationErrBox.style.display = 'none'; locationErrBox.textContent = ''; }
			}

			document.addEventListener('click', function(e){
				var hoursEl = e.target.closest('.equipment-hours');
				if (hoursEl) {
					var row = hoursEl.closest('tr[data-equipment-id]');
					if (row) {
						e.preventDefault();
						e.stopPropagation();
						openHoursModal(row);
					}
				}
				if (e.target.classList.contains('hours-edit-icon')) {
					var hRow = e.target.closest('tr[data-equipment-id]');
					if (hRow) {
						e.preventDefault();
						e.stopPropagation();
						openHoursModal(hRow);
					}
				}
				if (e.target.classList.contains('cell-edit-type')) {
					var tRow = e.target.closest('tr[data-equipment-id]');
					if (tRow) {
						e.preventDefault();
						e.stopPropagation();
						openTypeModal(tRow);
					}
				}
				if (e.target.classList.contains('cell-edit-location')) {
					var lRow = e.target.closest('tr[data-equipment-id]');
					if (lRow) {
						e.preventDefault();
						e.stopPropagation();
						openLocationModal(lRow);
					}
				}
				if (e.target.classList.contains('warranty-add-btn')) {
					var wRow = e.target.closest('tr[data-equipment-id]');
					if (wRow) {
						e.preventDefault();
						e.stopPropagation();
						var displayEl = e.target.closest('.warranty-cell') ? e.target.closest('.warranty-cell').querySelector('.warranty-display') : null;
						openWarrantyModal(wRow, displayEl);
					}
				}
			});

			if (closeHoursBtn) closeHoursBtn.addEventListener('click', function(){ closeHoursModal(); });
			if (cancelHoursBtn) cancelHoursBtn.addEventListener('click', function(){ closeHoursModal(); });
			if (hoursModal) hoursModal.addEventListener('click', function(e){ if (e.target === hoursModal) closeHoursModal(); });
			document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && hoursModal && hoursModal.classList.contains('is-open')) closeHoursModal(); });

			if (closeTypeBtn) closeTypeBtn.addEventListener('click', function(){ closeTypeModal(); });
			if (cancelTypeBtn) cancelTypeBtn.addEventListener('click', function(){ closeTypeModal(); });
			if (typeModal) typeModal.addEventListener('click', function(e){ if (e.target === typeModal) closeTypeModal(); });
			document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && typeModal && typeModal.classList.contains('is-open')) closeTypeModal(); });

			if (closeLocationBtn) closeLocationBtn.addEventListener('click', function(){ closeLocationModal(); });
			if (cancelLocationBtn) cancelLocationBtn.addEventListener('click', function(){ closeLocationModal(); });
			if (locationModal) locationModal.addEventListener('click', function(e){ if (e.target === locationModal) closeLocationModal(); });
			document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && locationModal && locationModal.classList.contains('is-open')) closeLocationModal(); });

			if (closeWarrantyBtn) closeWarrantyBtn.addEventListener('click', function(){ closeWarrantyModal(); });
			if (cancelWarrantyBtn) cancelWarrantyBtn.addEventListener('click', function(){ closeWarrantyModal(); });
			if (warrantyModal) warrantyModal.addEventListener('click', function(e){ if (e.target === warrantyModal) closeWarrantyModal(); });
			document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && warrantyModal && warrantyModal.classList.contains('is-open')) closeWarrantyModal(); });

			if (hoursForm) hoursForm.addEventListener('submit', function(e){
				e.preventDefault();
				if (!hoursEquipmentId || !hoursEquipmentId.value) return;
				if (saveHoursBtn) { saveHoursBtn.disabled = true; saveHoursBtn.textContent = 'Saving...'; }
				if (hoursErrBox) { hoursErrBox.style.display = 'none'; hoursErrBox.textContent = ''; }

				var fd = new FormData();
				fd.append('equipment_id', hoursEquipmentId.value);
				fd.append('current_hours', hoursInput.value);

				fetch('../../api/update_equipment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function(r){ return r.json().then(function(j){ return { ok: r.ok, json: j }; }); })
					.then(function(res){
						if (!res.ok || !res.json || !res.json.success) {
							var msg = (res.json && res.json.message) ? res.json.message : 'Failed to update hours';
							if (hoursErrBox) { hoursErrBox.textContent = msg; hoursErrBox.style.display = 'block'; }
							return;
						}
						var row = document.querySelector('tr[data-equipment-id="' + hoursEquipmentId.value + '"]');
						if (row) {
							row.setAttribute('data-current-hours', hoursInput.value);
							row.setAttribute('data-sort-current-hours', hoursInput.value);
							var cell = row.querySelector('.equipment-hours');
							if (cell) cell.textContent = hoursInput.value;
						}
						showSiteNotification('Hours updated successfully!', 'success');
						closeHoursModal();
					})
					.catch(function(){
						if (hoursErrBox) { hoursErrBox.textContent = 'Network error while updating hours'; hoursErrBox.style.display = 'block'; }
					})
					.finally(function(){
						if (saveHoursBtn) { saveHoursBtn.disabled = false; saveHoursBtn.textContent = 'Save'; }
					});
			});

			if (typeForm) typeForm.addEventListener('submit', function(e){
				e.preventDefault();
				if (!typeEquipmentId || !typeEquipmentId.value) return;
				if (saveTypeBtn) { saveTypeBtn.disabled = true; saveTypeBtn.textContent = 'Saving...'; }
				if (typeErrBox) { typeErrBox.style.display = 'none'; typeErrBox.textContent = ''; }

				var fd = new FormData();
				fd.append('equipment_id', typeEquipmentId.value);
				fd.append('type', typeInput.value);

				fetch('../../api/update_equipment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function(r){ return r.json().then(function(j){ return { ok: r.ok, json: j }; }); })
					.then(function(res){
						if (!res.ok || !res.json || !res.json.success) {
							var msg = (res.json && res.json.message) ? res.json.message : 'Failed to update type';
							if (typeErrBox) { typeErrBox.textContent = msg; typeErrBox.style.display = 'block'; }
							return;
						}
						var row = document.querySelector('tr[data-equipment-id="' + typeEquipmentId.value + '"]');
						if (row) {
							row.setAttribute('data-type', typeInput.value);
							var cell = row.querySelector('.equipment-inline-cell span');
							if (cell) cell.textContent = typeInput.value;
						}
						showSiteNotification('Type updated successfully!', 'success');
						closeTypeModal();
					})
					.catch(function(){
						if (typeErrBox) { typeErrBox.textContent = 'Network error while updating type'; typeErrBox.style.display = 'block'; }
					})
					.finally(function(){
						if (saveTypeBtn) { saveTypeBtn.disabled = false; saveTypeBtn.textContent = 'Save'; }
					});
			});

			if (locationForm) locationForm.addEventListener('submit', function(e){
				e.preventDefault();
				if (!locationEquipmentId || !locationEquipmentId.value) return;
				if (saveLocationBtn) { saveLocationBtn.disabled = true; saveLocationBtn.textContent = 'Saving...'; }
				if (locationErrBox) { locationErrBox.style.display = 'none'; locationErrBox.textContent = ''; }

				var fd = new FormData();
				fd.append('equipment_id', locationEquipmentId.value);
				fd.append('location', locationInput.value);

				fetch('../../api/update_equipment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function(r){ return r.json().then(function(j){ return { ok: r.ok, json: j }; }); })
					.then(function(res){
						if (!res.ok || !res.json || !res.json.success) {
							var msg = (res.json && res.json.message) ? res.json.message : 'Failed to update location';
							if (locationErrBox) { locationErrBox.textContent = msg; locationErrBox.style.display = 'block'; }
							return;
						}
						var row = document.querySelector('tr[data-equipment-id="' + locationEquipmentId.value + '"]');
						if (row) {
							row.setAttribute('data-location', locationInput.value);
							var cell = row.querySelector('td:nth-child(4) .equipment-inline-cell span');
							if (cell) cell.textContent = locationInput.value;
						}
						showSiteNotification('Location updated successfully!', 'success');
						closeLocationModal();
					})
					.catch(function(){
						if (locationErrBox) { locationErrBox.textContent = 'Network error while updating location'; locationErrBox.style.display = 'block'; }
					})
					.finally(function(){
						if (saveLocationBtn) { saveLocationBtn.disabled = false; saveLocationBtn.textContent = 'Save'; }
					});
			});

			if (warrantyForm) warrantyForm.addEventListener('submit', function(e){
				e.preventDefault();
				if (!warrantyEquipmentId || !warrantyEquipmentId.value) return;
				if (!warrantyFilesInput || warrantyFilesInput.files.length === 0) {
					if (warrantyErrBox) { warrantyErrBox.textContent = 'Please select at least one file.'; warrantyErrBox.style.display = 'block'; }
					return;
				}
				if (saveWarrantyBtn) { saveWarrantyBtn.disabled = true; saveWarrantyBtn.textContent = 'Uploading...'; }
				if (warrantyErrBox) { warrantyErrBox.style.display = 'none'; warrantyErrBox.textContent = ''; }

				var fd = new FormData();
				fd.append('equipment_id', warrantyEquipmentId.value);
				fd.append('field', 'warranty');
				Array.prototype.forEach.call(warrantyFilesInput.files, function(file){ fd.append('files[]', file); });

				fetch('../../api/add_equipment_upload.php', { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function(r){ return r.json().then(function(j){ return { ok: r.ok, json: j }; }); })
					.then(function(res){
						if (!res.ok || !res.json || !res.json.success) {
							var msg = (res.json && (res.json.error || res.json.errors && res.json.errors.join('; '))) ? (res.json.error || res.json.errors.join('; ')) : 'Failed to upload warranty';
							if (warrantyErrBox) { warrantyErrBox.textContent = msg; warrantyErrBox.style.display = 'block'; }
							return;
						}
						if (activeWarrantyDisplay) {
							var row = activeWarrantyDisplay.closest('tr[data-equipment-id]');
							var href = row ? (row.getAttribute('data-warranty-href') || '#') : '#';
								activeWarrantyDisplay.innerHTML = '<a href="' + href + '" title="View warranty" style="display:inline-block;"><img src="images/warrenty.svg" alt="Warranty" style="height:28px;vertical-align:middle;" /></a>';
						}
						showSiteNotification('Warranty uploaded successfully!', 'success');
						closeWarrantyModal();
					})
					.catch(function(){
						if (warrantyErrBox) { warrantyErrBox.textContent = 'Network error while uploading warranty'; warrantyErrBox.style.display = 'block'; }
					})
					.finally(function(){
						if (saveWarrantyBtn) { saveWarrantyBtn.disabled = false; saveWarrantyBtn.textContent = 'Upload'; }
					});
			});

			if (closeEditBtn) closeEditBtn.addEventListener('click', function(){ closeEditModal(); });
			if (cancelEditBtn) cancelEditBtn.addEventListener('click', function(){ closeEditModal(); });
			if (editModal) editModal.addEventListener('click', function(e){ if (e.target === editModal) closeEditModal(); });
			document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && editModal && editModal.classList.contains('is-open')) closeEditModal(); });

			if (editForm) editForm.addEventListener('submit', function(e){
				e.preventDefault();
				if (saveEditBtn) { saveEditBtn.disabled = true; saveEditBtn.textContent = 'Updating...'; }
				if (editErrBox) { editErrBox.style.display = 'none'; editErrBox.textContent = ''; }

				var fd = new FormData(editForm);
				fetch('../../api/update_equipment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function(r){ return r.json().then(function(j){ return { ok: r.ok, json: j }; }); })
					.then(function(res){
						if (!res.ok || !res.json || !res.json.success) {
							var msg = (res.json && res.json.message) ? res.json.message : 'Failed to update equipment';
							if (editErrBox) { editErrBox.textContent = msg; editErrBox.style.display = 'block'; }
							return;
						}
						showSiteNotification('Equipment updated successfully!', 'success');
						setTimeout(function(){ window.location.reload(); }, 1200);
					})
					.catch(function(){
						if (editErrBox) { editErrBox.textContent = 'Network error while updating'; editErrBox.style.display = 'block'; }
					})
					.finally(function(){
						if (saveEditBtn) { saveEditBtn.disabled = false; saveEditBtn.textContent = 'Update Equipment'; }
					});
			});

			// Site-level notification bar
			function showSiteNotification(message, type) {
				var existing = document.getElementById('siteNotificationBar');
				if (existing) existing.remove();
				var bar = document.createElement('div');
				bar.id = 'siteNotificationBar';
				bar.textContent = message;
				bar.style.position = 'fixed';
				bar.style.top = '0';
				bar.style.left = '0';
				bar.style.width = '100%';
				bar.style.zIndex = '10001';
				bar.style.padding = '18px 0';
				bar.style.textAlign = 'center';
				bar.style.fontSize = '18px';
				bar.style.fontWeight = 'bold';
				bar.style.background = (type === 'success') ? '#22c55e' : '#dc2626';
				bar.style.color = '#fff';
				bar.style.boxShadow = '0 2px 8px #0002';
				document.body.appendChild(bar);
				setTimeout(function(){
					if (bar.parentNode) bar.parentNode.removeChild(bar);
				}, 2000);
			}

			// Delete Equipment logic for Edit Modal
			var deleteBtn = document.getElementById('deleteEditEquipment');
			if (deleteBtn) {
				deleteBtn.addEventListener('click', function() {
					var eqid = document.getElementById('edit_equipment_id').value;
					if (!eqid || !confirm('Are you sure you want to delete this equipment? This cannot be undone.')) return;
					deleteBtn.disabled = true;
					fetch('../../api/delete_equipment.php', {
						method: 'POST',
						body: new URLSearchParams({ equipment_id: eqid }),
						credentials: 'same-origin'
					})
					.then(function(r){ return r.json().then(function(j){ return { ok: r.ok, json: j }; }); })
					.then(function(res){
						if (!res.ok || !res.json || !res.json.success) {
							var msg = (res.json && res.json.message) ? res.json.message : 'Failed to delete equipment';
							if (editErrBox) { editErrBox.textContent = msg; editErrBox.style.display = 'block'; }
							deleteBtn.disabled = false;
							return;
						}
						window.location.reload();
					})
					.catch(function(){
						if (editErrBox) { editErrBox.textContent = 'Network error while deleting'; editErrBox.style.display = 'block'; }
						deleteBtn.disabled = false;
					});
				});
			}

			// Users toggle
			var usersToggle = document.getElementById('usersToggle');
			var usersGroup = document.getElementById('usersGroup');
			if (usersToggle && usersGroup) {
				usersToggle.addEventListener('click', function(){
					usersGroup.classList.toggle('open');
				});
			}
		// Equipment Uploads: Warranty (Edit Modal)
		['warranty'].forEach(function(field){
			var input = document.getElementById('edit_' + field);
			var label = document.getElementById(field + '_file_label');
			var preview = document.querySelector('.equipment-upload-preview[data-field="'+field+'"]');
			if (!input || !label || !preview) return;

			input.addEventListener('change', function(e){
				var files = Array.from(input.files);
				var equipmentId = document.getElementById('edit_equipment_id').value;
				if (!equipmentId || files.length === 0) return;
				label.childNodes[0].nodeValue = 'Uploading...';
				var fd = new FormData();
				fd.append('equipment_id', equipmentId);
				fd.append('field', field);
				files.forEach(function(file){
					fd.append('files[]', file);
				});
				fetch('../../api/add_equipment_upload.php', {
					method: 'POST',
					body: fd,
					credentials: 'same-origin'
				})
				.then(function(r){ return r.json(); })
				.then(function(data){
					label.childNodes[0].nodeValue = 'Add More';
					if (!data.success) {
						showSiteNotification('Upload failed: ' + (data.message || 'Unknown error'), 'error');
						return;
					}
					// Refresh preview for this field
					fetch('../../api/get_equipment_uploads.php?equipment_id=' + encodeURIComponent(equipmentId))
						.then(function(r){ return r.json(); })
						.then(function(data){
							if (!data.success) return;
							if (preview) {
								preview.innerHTML = '';
								var hasFiles = (data.uploads && data.uploads[field] && data.uploads[field].length > 0);
								if (label) {
									label.childNodes[0].nodeValue = hasFiles ? 'Add More' : 'Browse...';
								}
								if (hasFiles) {
									data.uploads[field].forEach(function(file){
										var a = document.createElement('a');
										a.href = file.file_url;
										a.target = '_blank';
										a.textContent = file.file_url.split('/').pop();
										a.style.display = 'block';
										a.style.marginTop = '4px';
										preview.appendChild(a);
									});
								} else {
									preview.innerHTML = '<span style="color:#888;">No file uploaded for this equipment.</span>';
								}
							}
						});
				})
				.catch(function(){
					label.childNodes[0].nodeValue = 'Add More';
					showSiteNotification('Network error while uploading', 'error');
				});
			});
		});
		// End Equipment Uploads logic
	})();
	</script>
	<script src="../../assets/js/mobile-menu.js"></script>
	<script>

	</script>
</body>
</html>
<style>
	.add-more-btn {
		cursor: pointer;
		display: inline-block;
		margin-bottom: 6px;
		padding: 7px 18px;
		background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%);
		color: #fff;
		font-weight: 600;
		border-radius: 22px;
		border: none;
		font-size: 1rem;
		box-shadow: 0 2px 8px #0001;
		transition: background 0.2s, box-shadow 0.2s, transform 0.1s;
		position: relative;
		outline: none;
		text-align: center;
		width: auto;
		min-width: 110px;
		max-width: 220px;
	}
	.add-more-btn:hover, .add-more-btn:focus {
		background: linear-gradient(90deg, #16a34a 0%, #22c55e 100%);
		box-shadow: 0 4px 16px #0002;
		transform: translateY(-2px) scale(1.04);
		color: #fff;
		text-decoration: none;
	}
	.add-more-btn input[type="file"] {
		display: none;
	}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
	// Add Equipment Modal Additional Details toggle
	var showAdditionalDetailsBtn = document.getElementById('showAdditionalDetailsBtn');
	var additionalDetailsFields = document.getElementById('additionalDetailsFields');
	var additionalDetailsIcon = document.getElementById('additionalDetailsIcon');
	if (showAdditionalDetailsBtn && additionalDetailsFields && additionalDetailsIcon) {
		showAdditionalDetailsBtn.addEventListener('click', function() {
			var isOpen = additionalDetailsFields.style.display === 'grid';
			if (isOpen) {
				additionalDetailsFields.style.display = 'none';
				additionalDetailsIcon.style.transform = '';
			} else {
				additionalDetailsFields.style.display = 'grid';
				additionalDetailsIcon.style.transform = 'rotate(180deg)';
			}
		});
	}

	// Edit Equipment Modal Additional Details toggle
	var showEditAdditionalDetailsBtn = document.getElementById('showEditAdditionalDetailsBtn');
	var editAdditionalDetailsFields = document.getElementById('editAdditionalDetailsFields');
	var editAdditionalDetailsIcon = document.getElementById('editAdditionalDetailsIcon');
	if (showEditAdditionalDetailsBtn && editAdditionalDetailsFields && editAdditionalDetailsIcon) {
		showEditAdditionalDetailsBtn.addEventListener('click', function() {
			var isOpen = editAdditionalDetailsFields.style.display === 'grid';
			if (isOpen) {
				editAdditionalDetailsFields.style.display = 'none';
				editAdditionalDetailsIcon.style.transform = '';
			} else {
				editAdditionalDetailsFields.style.display = 'grid';
				editAdditionalDetailsIcon.style.transform = 'rotate(180deg)';
			}
		});
	}
});
</script>