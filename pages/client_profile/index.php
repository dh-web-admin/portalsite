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

// Fetch all clients
$clients = [];
$clientStmt = $conn->prepare('SELECT client_id, client_name, client_number, client_type, union_status, contact_phone, client_email, client_address, city, state, website, notes, family_details, current_employer, previous_employment, past_projects FROM clients ORDER BY client_name ASC');
if ($clientStmt) {
	$clientStmt->execute();
	$clientResult = $clientStmt->get_result();
	while ($row = $clientResult->fetch_assoc()) {
		$clients[] = $row;
	}
	$clientStmt->close();
}

// Fetch distinct client types for autocomplete/select list
$clientTypes = [];
$typeStmt = $conn->prepare("SELECT DISTINCT client_type FROM clients WHERE client_type IS NOT NULL AND client_type <> '' ORDER BY client_type ASC");
if ($typeStmt) {
	$typeStmt->execute();
	$typeRes = $typeStmt->get_result();
	while ($row = $typeRes->fetch_assoc()) {
		$clientTypes[] = $row['client_type'];
	}
	$typeStmt->close();
}

// Fetch distinct current employers for autocomplete list
$currentEmployers = [];
$empStmt = $conn->prepare("SELECT DISTINCT current_employer FROM clients WHERE current_employer IS NOT NULL AND current_employer <> '' ORDER BY current_employer ASC");
if ($empStmt) {
	$empStmt->execute();
	$empRes = $empStmt->get_result();
	while ($row = $empRes->fetch_assoc()) {
		$currentEmployers[] = $row['current_employer'];
	}
	$empStmt->close();
}

// Fetch distinct client names for autocomplete list
$clientNames = [];
$nameStmt = $conn->prepare("SELECT DISTINCT client_name FROM clients WHERE client_name IS NOT NULL AND client_name <> '' ORDER BY client_name ASC");
if ($nameStmt) {
	$nameStmt->execute();
	$nameRes = $nameStmt->get_result();
	while ($row = $nameRes->fetch_assoc()) {
		$clientNames[] = $row['client_name'];
	}
	$nameStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
	<meta name="theme-color" content="#667eea" />
	<title>Client Profile</title>
	<link rel="stylesheet" href="../../assets/css/base.css" />
	<link rel="stylesheet" href="../../assets/css/admin-layout.css" />
	<link rel="stylesheet" href="../../assets/css/dashboard.css" />
	<style>
		/* Match header alignment with other admin pages (see bid_tracking). */
		#viewClientModal { display:none; position:fixed; inset:0; background:rgba(2,6,23,0.6); z-index:9999; width:100vw; height:100vh; left:0; top:0; }
		#viewClientModal .modal-shell { position:fixed; inset:0; background:#f8fafc; display:flex; flex-direction:column; width:100vw; height:100vh; }
		#viewClientModal .modal-header { display:flex; align-items:center; justify-content:space-between; padding:16px 24px; border-bottom:1px solid #e2e8f0; background:#fff; flex-shrink:0; }
		#viewClientModal .modal-body { flex:1; overflow:auto; padding:24px; background:#f8fafc; width:100%; }
		#addClientModal { display:none; position:fixed; inset:0; background:rgba(2,6,23,0.6); z-index:2000; width:100vw; height:100vh; left:0; top:0; }
		#addClientModal .modal-shell { position:fixed; inset:0; background:#f8fafc; display:flex; flex-direction:column; width:100vw; height:100vh; }
		#addClientModal .modal-header { display:flex; align-items:center; justify-content:space-between; padding:16px 24px; border-bottom:1px solid #e2e8f0; background:#fff; flex-shrink:0; }
		#addClientModal .modal-body { flex:1; overflow:auto; padding:24px; background:#f8fafc; width:100%; }
		#addClientModal .modal-actions { display:flex; align-items:center; gap:10px; }
		#addClientModal .modal-close { background:transparent; border:none; font-size:28px; line-height:1; cursor:pointer; color:#64748b; }
		#editClientModal { display:none; position:fixed; inset:0; background:rgba(2,6,23,0.6); z-index:2000; width:100vw; height:100vh; left:0; top:0; }
		#editClientModal .modal-shell { position:fixed; inset:0; background:#f8fafc; display:flex; flex-direction:column; width:100vw; height:100vh; }
		#editClientModal .modal-header { display:flex; align-items:center; justify-content:space-between; padding:16px 24px; border-bottom:1px solid #e2e8f0; background:#fff; flex-shrink:0; }
		#editClientModal .modal-body { flex:1; overflow:auto; padding:24px; background:#f8fafc; width:100%; }
		#editClientModal .modal-actions { display:flex; align-items:center; gap:10px; }
		#editClientModal .modal-close { background:transparent; border:none; font-size:28px; line-height:1; cursor:pointer; color:#64748b; }
		.search-container { position: relative; width: 500px; max-width: 100%; }
		#clientSearchSuggestions { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-top: none; border-radius: 0 0 8px 8px; max-height: 300px; overflow-y: auto; display: none; z-index: 1000; box-shadow: 0 4px 12px rgba(2,6,23,0.1); }
		#clientSearchSuggestions .suggestion-item { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f0f1f3; }
		#clientSearchSuggestions .suggestion-item:hover { background: #f8fafc; }
		#clientSearchSuggestions .suggestion-item.highlight { background: #eff6ff; color: #0c63e4; font-weight: 600; }
		.hidden-row { display: none !important; }
		.type-suggestions { position: relative; }
		.type-suggestions-list { position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; max-height: 220px; overflow-y: auto; display: none; z-index: 1200; box-shadow: 0 4px 12px rgba(2,6,23,0.1); }
		.type-suggestions-list .type-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f1f3; font-size: 13px; color: #0f172a; }
		.type-suggestions-list .type-item:last-child { border-bottom: none; }
		.type-suggestions-list .type-item:hover { background: #f8fafc; }
		.type-suggestions-list .type-item.is-active { background: #eff6ff; color: #0c63e4; font-weight: 600; }
		.type-suggestions-list .type-empty { padding: 8px 12px; color: #94a3b8; font-size: 13px; }
		/* Left-align table headers and cells */
		table thead th,
		table tbody td { text-align: left; }
		/* Hide Client Type (first column) and Website (last column) */
		table thead th:first-child,
		table tbody td:first-child,
		table thead th:last-child,
		table tbody td:last-child { display: none; }
		/* Toast message */
		#clientToast {
			position: fixed;
			top: 24px;
			right: 24px;
			min-width: 220px;
			max-width: 420px;
			background: #f1f5f9;
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
		#clientToast.warn { background: #fef3c7; color: #92400e; border-color: rgba(146,64,14,0.12); }
		#clientToast .msg { flex: 1; font-weight: 700; }
		#clientToast .close { background: transparent; border: 0; color: rgba(15,23,42,0.7); cursor: pointer; font-weight: 700; padding: 6px; border-radius: 6px; }
	</style>
</head>
<body class="admin-page">
	<div class="admin-container">
		<?php include __DIR__ . '/../../partials/portalheader.php'; ?>
		<div class="admin-layout">
			<?php include __DIR__ . '/../../partials/sidebar.php'; ?>
			<main class="content-area">
				<div class="main-content">
					<div style="margin-top:12px;margin-bottom:6px;display:flex;gap:10px;align-items:center;justify-content:flex-start;width:100%;">
						<div class="search-container">
							<input type="text" id="clientSearch" placeholder="Search clients..." style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;box-shadow:0 2px 6px rgba(2,6,23,0.04);" />
							<div id="clientSearchSuggestions"></div>
						</div>
						<button type="button" id="openAddClientModal" style="padding:10px 16px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;transition:background 0.2s ease;white-space:nowrap;">+ Add</button>
					</div>
					<div style="margin-top:16px;border-radius:12px;overflow:hidden;box-shadow:0 6px 18px rgba(2,6,23,0.04);">
						<table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
							<thead>
								<tr style="background:#f8fafc;text-align:left;">
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Client Type</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Client Name</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Client Number</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Union Status</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Client Phone</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Client Email</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Address</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">City</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">State</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Website</th>
								</tr>
							</thead>
							<tbody>
								<?php if (empty($clients)): ?>
								<tr>
									<td colspan="10" style="padding:12px 14px;text-align:center;color:#64748b;">No clients found</td>
								</tr>
								<?php else: ?>
									<?php foreach ($clients as $client): ?>
									<tr class="client-row" data-client-id="<?php echo htmlspecialchars($client['client_id']); ?>" data-client-name="<?php echo htmlspecialchars($client['client_name']); ?>" data-client-number="<?php echo htmlspecialchars($client['client_number'] ?? ''); ?>" data-client-type="<?php echo htmlspecialchars($client['client_type'] ?? ''); ?>" data-union-status="<?php echo htmlspecialchars($client['union_status'] ?? ''); ?>" data-contact-phone="<?php echo htmlspecialchars($client['contact_phone'] ?? ''); ?>" data-client-email="<?php echo htmlspecialchars($client['client_email'] ?? ''); ?>" data-client-address="<?php echo htmlspecialchars($client['client_address'] ?? ''); ?>" data-city="<?php echo htmlspecialchars($client['city'] ?? ''); ?>" data-state="<?php echo htmlspecialchars($client['state'] ?? ''); ?>" data-website="<?php echo htmlspecialchars($client['website'] ?? ''); ?>" data-notes="<?php echo htmlspecialchars($client['notes'] ?? ''); ?>" data-family-details="<?php echo htmlspecialchars($client['family_details'] ?? ''); ?>" data-current-employer="<?php echo htmlspecialchars($client['current_employer'] ?? ''); ?>" data-previous-employment="<?php echo htmlspecialchars($client['previous_employment'] ?? ''); ?>" data-past-projects="<?php echo htmlspecialchars($client['past_projects'] ?? ''); ?>" style="border-bottom:1px solid #e2e8f0;cursor:pointer;transition:background 0.2s ease;" onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='';">
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['client_type'] ?? ''); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['client_name']); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['client_number'] ?? ''); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['union_status'] ?? ''); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['contact_phone'] ?? ''); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['client_email'] ?? ''); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['client_address'] ?? ''); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['city'] ?? ''); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['state'] ?? ''); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['website'] ?? ''); ?></td>
									</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
					<!-- Client Profile content will go here -->
				</div>
			</main>
		</div>
	</div>
	<div id="clientToast" role="status" aria-live="polite">
		<span class="msg"></span>
		<button type="button" class="close" aria-label="Close">Close</button>
	</div>

	<div id="addClientModal" aria-hidden="true">
		<div class="modal-shell">
			<div class="modal-header">
				<h2 style="margin:0;font-size:18px;color:#0f172a;">Add Client</h2>
				<div class="modal-actions">
					<button type="button" id="cancelAddClientModal" style="padding:8px 14px;background:#fff;color:#374151;border:1px solid #e5e7eb;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
					<button type="button" id="saveAddClientModal" style="padding:8px 14px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Save</button>
				</div>
			</div>
			<div class="modal-body">
				<div style="max-width:100%;display:grid;grid-template-columns:1fr 1px 1fr;gap:24px;">
					<div>
						<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;padding-bottom:8px;border-bottom:2px solid #e2e8f0;">Personal Detail</h3>
						<form id="addClientForm" style="display:contents;">
							<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;">
								<div class="type-suggestions">
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Name</label>
									<input type="text" id="clientNameInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" autocomplete="off" required />
									<div id="clientNameSuggestions" class="type-suggestions-list"></div>
								</div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Number</label>
									<input type="text" id="clientNumberInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
								<div></div>
							</div>
							<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;">
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Union Status</label>
									<select id="unionStatusInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;">
										<option value="">Select Status</option>
										<option value="Union">Union</option>
										<option value="Non-Union">Non-Union</option>
									</select>
								</div>
								<div></div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Phone</label>
									<input type="text" id="contactPhoneInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
							</div>
							<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;">
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Email</label>
									<input type="email" id="clientEmailInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Website</label>
									<input type="text" id="websiteInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
								<div></div>
							</div>
							<div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px;">
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Address</label>
									<input type="text" id="clientAddressInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">City</label>
									<input type="text" id="clientCityInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">State</label>
									<select id="clientStateInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;">
										<option value="">Select State</option>
										<option value="AL">Alabama</option>
										<option value="AK">Alaska</option>
										<option value="AZ">Arizona</option>
										<option value="AR">Arkansas</option>
										<option value="CA">California</option>
										<option value="CO">Colorado</option>
										<option value="CT">Connecticut</option>
										<option value="DE">Delaware</option>
										<option value="FL">Florida</option>
										<option value="GA">Georgia</option>
										<option value="HI">Hawaii</option>
										<option value="ID">Idaho</option>
										<option value="IL">Illinois</option>
										<option value="IN">Indiana</option>
										<option value="IA">Iowa</option>
										<option value="KS">Kansas</option>
										<option value="KY">Kentucky</option>
										<option value="LA">Louisiana</option>
										<option value="ME">Maine</option>
										<option value="MD">Maryland</option>
										<option value="MA">Massachusetts</option>
										<option value="MI">Michigan</option>
										<option value="MN">Minnesota</option>
										<option value="MS">Mississippi</option>
										<option value="MO">Missouri</option>
										<option value="MT">Montana</option>
										<option value="NE">Nebraska</option>
										<option value="NV">Nevada</option>
										<option value="NH">New Hampshire</option>
										<option value="NJ">New Jersey</option>
										<option value="NM">New Mexico</option>
										<option value="NY">New York</option>
										<option value="NC">North Carolina</option>
										<option value="ND">North Dakota</option>
										<option value="OH">Ohio</option>
										<option value="OK">Oklahoma</option>
										<option value="OR">Oregon</option>
										<option value="PA">Pennsylvania</option>
										<option value="RI">Rhode Island</option>
										<option value="SC">South Carolina</option>
										<option value="SD">South Dakota</option>
										<option value="TN">Tennessee</option>
										<option value="TX">Texas</option>
										<option value="UT">Utah</option>
										<option value="VT">Vermont</option>
										<option value="VA">Virginia</option>
										<option value="WA">Washington</option>
										<option value="WV">West Virginia</option>
										<option value="WI">Wisconsin</option>
										<option value="WY">Wyoming</option>
									</select>
								</div>
							</div>
							<div style="margin-top:16px;">
								<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Notes</label>
								<textarea id="clientNotesInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;resize:vertical;min-height:100px;" placeholder="Add any additional notes..."></textarea>
							</div>						<div style="margin-top:24px;padding-top:16px;border-top:2px solid #e2e8f0;">
							<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;">Family Details</h3>
							<div style="margin-bottom:16px;">
								<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Details</label>
								<textarea id="familyDetailsInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;resize:vertical;min-height:100px;" placeholder="Add family details..."></textarea>
							</div>
						</div>						</form>
					</div>
					<div style="background:#e2e8f0;width:1px;height:100%;"></div>
					<div>
						<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;padding-bottom:8px;border-bottom:2px solid #e2e8f0;">Employment Details</h3>
						<div style="margin-bottom:16px;" class="type-suggestions">
							<h4 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 12px 0;">Client Type</h4>
							<input type="text" id="clientTypeInput" list="clientTypeOptions" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" autocomplete="off" />
							<div id="clientTypeSuggestions" class="type-suggestions-list"></div>
						</div>
						<div style="margin-bottom:16px;" class="type-suggestions">
							<h4 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 12px 0;">Current Employer</h4>
							<input type="text" id="currentRoleInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" placeholder="Enter current employer" autocomplete="off" />
							<div id="currentEmployerSuggestions" class="type-suggestions-list"></div>
						</div>
				<div style="margin-bottom:16px;">							<h4 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 12px 0;">Previous Employment</h4>
					<textarea id="previousEmploymentInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;resize:vertical;min-height:100px;" placeholder="Add previous employment details..."></textarea>
						</div>
						<div style="margin-top:24px;padding-top:16px;border-top:2px solid #e2e8f0;">
							<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;">Past Projects/Interactions</h3>
							<div id="pastProjectsContainer" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px;"></div>
							<button type="button" id="addPastProjectBtn" style="align-self:flex-start;padding:8px 12px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;font-weight:600;cursor:pointer;">+ Add More</button>
						</div>
				</div>
			</div>
		</div>
	</div>

	<div id="editClientModal" aria-hidden="true">
		<div class="modal-shell">
			<div class="modal-header">
				<h2 style="margin:0;font-size:18px;color:#0f172a;">Client Detail</h2>
				<div class="modal-actions">
					<button type="button" id="cancelEditClientModal" style="padding:8px 14px;background:#fff;color:#374151;border:1px solid #e5e7eb;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
					<button type="button" id="saveEditClientModal" style="padding:8px 14px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Save</button>
				</div>
			</div>
			<div class="modal-body">
				<div style="max-width:100%;display:grid;grid-template-columns:1fr 1px 1fr;gap:24px;">
					<div>
						<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;padding-bottom:8px;border-bottom:2px solid #e2e8f0;">Personal Detail</h3>
						<form id="editClientForm" style="display:contents;">
							<input type="hidden" id="editClientId" />
							<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;">
								<div class="type-suggestions">
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Name</label>
									<input type="text" id="editClientNameInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" autocomplete="off" required />
									<div id="editClientNameSuggestions" class="type-suggestions-list"></div>
								</div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Number</label>
									<input type="text" id="editClientNumberInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
								<div></div>
							</div>
							<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;">
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Union Status</label>
									<select id="editUnionStatusInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;">
										<option value="">Select Status</option>
										<option value="Union">Union</option>
										<option value="Non-Union">Non-Union</option>
									</select>
								</div>
								<div></div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Phone</label>
									<input type="text" id="editContactPhoneInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
							</div>
							<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;">
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Email</label>
									<input type="email" id="editClientEmailInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Website</label>
									<input type="text" id="editWebsiteInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
								<div></div>
							</div>
							<div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px;">
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Address</label>
									<input type="text" id="editClientAddressInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">City</label>
									<input type="text" id="editClientCityInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">State</label>
									<select id="editClientStateInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;">
										<option value="">Select State</option>
										<option value="AL">Alabama</option>
										<option value="AK">Alaska</option>
										<option value="AZ">Arizona</option>
										<option value="AR">Arkansas</option>
										<option value="CA">California</option>
										<option value="CO">Colorado</option>
										<option value="CT">Connecticut</option>
										<option value="DE">Delaware</option>
										<option value="FL">Florida</option>
										<option value="GA">Georgia</option>
										<option value="HI">Hawaii</option>
										<option value="ID">Idaho</option>
										<option value="IL">Illinois</option>
										<option value="IN">Indiana</option>
										<option value="IA">Iowa</option>
										<option value="KS">Kansas</option>
										<option value="KY">Kentucky</option>
										<option value="LA">Louisiana</option>
										<option value="ME">Maine</option>
										<option value="MD">Maryland</option>
										<option value="MA">Massachusetts</option>
										<option value="MI">Michigan</option>
										<option value="MN">Minnesota</option>
										<option value="MS">Mississippi</option>
										<option value="MO">Missouri</option>
										<option value="MT">Montana</option>
										<option value="NE">Nebraska</option>
										<option value="NV">Nevada</option>
										<option value="NH">New Hampshire</option>
										<option value="NJ">New Jersey</option>
										<option value="NM">New Mexico</option>
										<option value="NY">New York</option>
										<option value="NC">North Carolina</option>
										<option value="ND">North Dakota</option>
										<option value="OH">Ohio</option>
										<option value="OK">Oklahoma</option>
										<option value="OR">Oregon</option>
										<option value="PA">Pennsylvania</option>
										<option value="RI">Rhode Island</option>
										<option value="SC">South Carolina</option>
										<option value="SD">South Dakota</option>
										<option value="TN">Tennessee</option>
										<option value="TX">Texas</option>
										<option value="UT">Utah</option>
										<option value="VT">Vermont</option>
										<option value="VA">Virginia</option>
										<option value="WA">Washington</option>
										<option value="WV">West Virginia</option>
										<option value="WI">Wisconsin</option>
										<option value="WY">Wyoming</option>
									</select>
								</div>
							</div>
							<div style="margin-top:16px;">
								<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Notes</label>
								<textarea id="editClientNotesInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;resize:vertical;min-height:100px;" placeholder="Add any additional notes..."></textarea>
							</div>						<div style="margin-top:24px;padding-top:16px;border-top:2px solid #e2e8f0;">
							<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;">Family Details</h3>
							<div style="margin-bottom:16px;">
								<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Details</label>
								<textarea id="editFamilyDetailsInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;resize:vertical;min-height:100px;" placeholder="Add family details..."></textarea>
							</div>
						</div>						</form>
					</div>
					<div style="background:#e2e8f0;width:1px;height:100%;"></div>
					<div>
						<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;padding-bottom:8px;border-bottom:2px solid #e2e8f0;">Employment Details</h3>
						<div style="margin-bottom:16px;" class="type-suggestions">
							<h4 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 12px 0;">Client Type</h4>
							<input type="text" id="editClientTypeInput" list="clientTypeOptions" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" autocomplete="off" />
							<div id="editClientTypeSuggestions" class="type-suggestions-list"></div>
						</div>
						<div style="margin-bottom:16px;" class="type-suggestions">
							<h4 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 12px 0;">Current Employer</h4>
							<input type="text" id="editCurrentRoleInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" placeholder="Enter current employer" autocomplete="off" />
							<div id="editCurrentEmployerSuggestions" class="type-suggestions-list"></div>
						</div>
				<div style="margin-bottom:16px;">							<h4 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 12px 0;">Previous Employment</h4>
					<textarea id="editPreviousEmploymentInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;resize:vertical;min-height:100px;" placeholder="Add previous employment details..."></textarea>
						</div>
						<div style="margin-top:24px;padding-top:16px;border-top:2px solid #e2e8f0;">
							<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;">Past Projects/Interactions</h3>
							<div id="editPastProjectsContainer" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px;"></div>
							<button type="button" id="editAddPastProjectBtn" style="align-self:flex-start;padding:8px 12px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;font-weight:600;cursor:pointer;">+ Add More</button>
						</div>
				</div>
			</div>
		</div>
	</div>

	<datalist id="clientTypeOptions">
		<?php foreach ($clientTypes as $type): ?>
			<option value="<?php echo htmlspecialchars((string)$type); ?>"></option>
		<?php endforeach; ?>
	</datalist>

	<div id="viewClientModal" aria-hidden="true">
		<div class="modal-shell">
			<div class="modal-header">
				<h2 style="margin:0;font-size:18px;color:#0f172a;" id="viewClientTitle">Client Details</h2>
				<div class="modal-actions">
					<button type="button" id="editViewClientModal" style="padding:8px 14px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Edit</button>
					<button type="button" id="deleteViewClientModal" style="padding:8px 14px;background:#ef4444;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Delete</button>
					<button type="button" id="closeViewClientModal" style="padding:8px 14px;background:#fff;color:#374151;border:1px solid #e5e7eb;border-radius:8px;font-weight:600;cursor:pointer;">Close</button>
				</div>
			</div>
			<div class="modal-body">
				<div style="max-width:1200px;margin:0 auto;">
					<div style="display:grid;grid-template-columns:1fr 1px 1fr;gap:24px;">
						<div>
							<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;padding-bottom:8px;border-bottom:2px solid #e2e8f0;">Personal Detail</h3>
							<div style="margin-bottom:16px;">
								<label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px;">Client Name</label>
								<p style="margin:0;color:#0f172a;font-size:14px;" id="viewClientName">-</p>
							</div>
							<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;">
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px;">Client Type</label>
									<p style="margin:0;color:#0f172a;font-size:14px;" id="viewClientType">-</p>
								</div>
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px;">Union Status</label>
									<p style="margin:0;color:#0f172a;font-size:14px;" id="viewUnionStatus">-</p>
								</div>
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px;">Client Number</label>
									<p style="margin:0;color:#0f172a;font-size:14px;" id="viewClientNumber">-</p>
								</div>
							</div>
							<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;">
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px;">Client Phone</label>
									<p style="margin:0;color:#0f172a;font-size:14px;" id="viewContactPhone">-</p>
								</div>
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px;">Client Email</label>
									<p style="margin:0;color:#0f172a;font-size:14px;" id="viewClientEmail">-</p>
								</div>
								<div></div>
							</div>
							<div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px;margin-bottom:16px;">
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px;">Client Address</label>
									<p style="margin:0;color:#0f172a;font-size:14px;" id="viewClientAddress">-</p>
								</div>
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px;">City</label>
									<p style="margin:0;color:#0f172a;font-size:14px;" id="viewClientCity">-</p>
								</div>
								<div>
									<label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px;">State</label>
									<p style="margin:0;color:#0f172a;font-size:14px;" id="viewClientState">-</p>
								</div>
							</div>
							<div style="margin-bottom:16px;">
								<label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px;">Website</label>
								<p style="margin:0;color:#0f172a;font-size:14px;" id="viewWebsite">-</p>
							</div>
							<div style="margin-bottom:16px;">
								<label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px;">Notes</label>
								<p style="margin:0;color:#0f172a;font-size:14px;white-space:pre-wrap;" id="viewClientNotes">-</p>
							</div>
							<div style="margin-top:24px;padding-top:16px;border-top:2px solid #e2e8f0;">
								<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;">Family Details</h3>
								<p style="margin:0;color:#0f172a;font-size:14px;white-space:pre-wrap;" id="viewFamilyDetails">-</p>
							</div>
						</div>
						<div style="background:#e2e8f0;width:1px;height:100%;"></div>
						<div>
							<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;padding-bottom:8px;border-bottom:2px solid #e2e8f0;">Employment Details</h3>
							<div style="margin-bottom:16px;">
								<h4 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 8px 0;">Current Employer</h4>
								<p style="margin:0;color:#0f172a;font-size:14px;" id="viewCurrentRole">-</p>
							</div>
							<div style="margin-bottom:16px;">
								<h4 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 8px 0;">Previous Employment</h4>
								<p style="margin:0;color:#0f172a;font-size:14px;white-space:pre-wrap;" id="viewPreviousEmployment">-</p>
							</div>
							<div style="margin-top:24px;padding-top:16px;border-top:2px solid #e2e8f0;">
								<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;">Past Projects/Interactions</h3>
								<p style="margin:0;color:#0f172a;font-size:14px;white-space:pre-wrap;" id="viewPastProjects">-</p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script>
		(function(){
			function hideToast() {
				var t = document.getElementById('clientToast');
				if (!t) return;
				clearTimeout(t._hideTimer);
				t.style.display = 'none';
				t.classList.remove('warn');
			}

			function showToast(message, type, opts) {
				var t = document.getElementById('clientToast');
				if (!t) return;
				var msg = t.querySelector('.msg');
				var close = t.querySelector('.close');
				var persist = !!(opts && opts.persist);
				msg.textContent = message;
				t.classList.remove('warn');
				if (type === 'warn') t.classList.add('warn');
				t.style.display = 'flex';
				clearTimeout(t._hideTimer);
				if (!persist) {
					t._hideTimer = setTimeout(hideToast, 3000);
				}
				close.onclick = function(){ hideToast(); };
			}

			var clientTypeOptions = <?php echo json_encode(array_values(array_unique($clientTypes)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || [];
			var currentEmployerOptions = <?php echo json_encode(array_values(array_unique($currentEmployers)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || [];
			var clientNameOptions = <?php echo json_encode(array_values(array_unique($clientNames)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || [];
			var addModal = document.getElementById('addClientModal');
			var openAddBtn = document.getElementById('openAddClientModal');
			var cancelAddBtn = document.getElementById('cancelAddClientModal');
			var saveAddBtn = document.getElementById('saveAddClientModal');
			var addForm = document.getElementById('addClientForm');
			
			var editModal = document.getElementById('editClientModal');
			var cancelEditBtn = document.getElementById('cancelEditClientModal');
			var saveEditBtn = document.getElementById('saveEditClientModal');
			var editForm = document.getElementById('editClientForm');
			var clientNameInput = document.getElementById('clientNameInput');
			var clientNameSuggestions = document.getElementById('clientNameSuggestions');
			var editClientNameInput = document.getElementById('editClientNameInput');
			var editClientNameSuggestions = document.getElementById('editClientNameSuggestions');
			var lastDuplicateName = '';
			var clientTypeInput = document.getElementById('clientTypeInput');
			var clientTypeSuggestions = document.getElementById('clientTypeSuggestions');
			var editClientTypeInput = document.getElementById('editClientTypeInput');
			var editClientTypeSuggestions = document.getElementById('editClientTypeSuggestions');
			var currentEmployerInput = document.getElementById('currentRoleInput');
			var currentEmployerSuggestions = document.getElementById('currentEmployerSuggestions');
			var editCurrentEmployerInput = document.getElementById('editCurrentRoleInput');
			var editCurrentEmployerSuggestions = document.getElementById('editCurrentEmployerSuggestions');
			var pastProjectsContainer = document.getElementById('pastProjectsContainer');
			var addPastProjectBtn = document.getElementById('addPastProjectBtn');
			var editPastProjectsContainer = document.getElementById('editPastProjectsContainer');
			var editAddPastProjectBtn = document.getElementById('editAddPastProjectBtn');
			
			var viewModal = document.getElementById('viewClientModal');
			var closeViewBtn = document.getElementById('closeViewClientModal');
			var editViewBtn = document.getElementById('editViewClientModal');
			var lastSelectedClientId = '';
			var lastSelectedData = null;
			
			// Search and autocomplete functionality
			var clientSearch = document.getElementById('clientSearch');
			var searchSuggestions = document.getElementById('clientSearchSuggestions');
			var clientRows = document.querySelectorAll('.client-row');
			var allClients = [];

			function normalizeType(val) {
				return (val || '').toString().trim();
			}

			function normalizeLower(val) {
				return normalizeType(val).toLowerCase();
			}

			function hasExactMatch(options, value) {
				var needle = normalizeLower(value);
				if (!needle) return false;
				return options.some(function(opt){ return normalizeLower(opt) === needle; });
			}

			function buildMatches(options, term) {
				var q = normalizeType(term).toLowerCase();
				if (!q) return options.slice(0, 8);
				var starts = [];
				var contains = [];
				options.forEach(function(t){
					var raw = normalizeType(t);
					var low = raw.toLowerCase();
					if (!low) return;
					if (low.indexOf(q) === 0) starts.push(raw);
					else if (low.indexOf(q) !== -1) contains.push(raw);
				});
				return starts.concat(contains).slice(0, 8);
			}

			function renderSuggestions(listEl, matches, emptyText) {
				if (!listEl) return;
				if (!matches || matches.length === 0) {
					listEl.innerHTML = '<div class="type-empty">' + escapeHtml(emptyText) + '</div>';
					listEl.style.display = 'block';
					return;
				}
				listEl.innerHTML = matches.map(function(t){
					return '<div class="type-item" data-value="' + escapeHtml(t) + '">' + escapeHtml(t) + '</div>';
				}).join('');
				listEl.style.display = 'block';
			}

			function attachAutocomplete(inputEl, listEl, options, emptyText, onSelect) {
				if (!inputEl || !listEl) return;
				var activeIndex = -1;
				var currentItems = [];

				function syncItems() {
					currentItems = Array.prototype.slice.call(listEl.querySelectorAll('.type-item'));
					if (activeIndex >= currentItems.length) activeIndex = -1;
					currentItems.forEach(function(item, idx){
						item.classList.toggle('is-active', idx === activeIndex);
					});
				}

				function openList() {
					if (listEl.style.display !== 'block') {
						listEl.style.display = 'block';
					}
					syncItems();
				}

				function closeList() {
					listEl.style.display = 'none';
					activeIndex = -1;
				}

				inputEl.addEventListener('input', function(){
					var matches = buildMatches(options, this.value);
					renderSuggestions(listEl, matches, emptyText);
					activeIndex = -1;
					openList();
				});
				inputEl.addEventListener('focus', function(){
					var matches = buildMatches(options, this.value);
					renderSuggestions(listEl, matches, emptyText);
					activeIndex = -1;
					openList();
				});
				inputEl.addEventListener('keydown', function(e){
					if (listEl.style.display !== 'block') return;
					if (e.key === 'ArrowDown') {
						e.preventDefault();
						syncItems();
						if (currentItems.length === 0) return;
						activeIndex = (activeIndex + 1) % currentItems.length;
						syncItems();
						currentItems[activeIndex].scrollIntoView({ block: 'nearest' });
					}
					if (e.key === 'ArrowUp') {
						e.preventDefault();
						syncItems();
						if (currentItems.length === 0) return;
						activeIndex = (activeIndex <= 0) ? currentItems.length - 1 : activeIndex - 1;
						syncItems();
						currentItems[activeIndex].scrollIntoView({ block: 'nearest' });
					}
					if (e.key === 'Enter') {
						if (activeIndex >= 0 && currentItems[activeIndex]) {
							e.preventDefault();
							var value = currentItems[activeIndex].getAttribute('data-value') || '';
							inputEl.value = value;
							closeList();
							if (typeof onSelect === 'function') onSelect(value);
						}
					}
					if (e.key === 'Escape') {
						closeList();
					}
				});
				listEl.addEventListener('click', function(e){
					var item = e.target.closest('.type-item');
					if (!item) return;
					var value = item.getAttribute('data-value') || '';
					inputEl.value = value;
					closeList();
					if (typeof onSelect === 'function') onSelect(value);
				});
				document.addEventListener('click', function(e){
					if (e.target === inputEl || listEl.contains(e.target)) return;
					closeList();
				});
			}

			function splitPastProjects(raw) {
				return (raw || '')
					.split(',')
					.map(function(x){ return x.trim(); })
					.filter(function(x){ return x.length > 0; });
			}

			function createPastProjectRow(value) {
				var row = document.createElement('div');
				row.style.cssText = 'display:flex;gap:8px;align-items:center;';
				var input = document.createElement('input');
				input.type = 'text';
				input.className = 'past-project-input';
				input.value = value || '';
				input.placeholder = 'Enter past project/interaction';
				input.style.cssText = 'flex:1;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;';
				var removeBtn = document.createElement('button');
				removeBtn.type = 'button';
				removeBtn.setAttribute('aria-label', 'Remove');
				removeBtn.textContent = 'X';
				removeBtn.style.cssText = 'border:none;background:transparent;color:#ef4444;font-size:16px;font-weight:700;cursor:pointer;line-height:1;';
				removeBtn.addEventListener('click', function(){
					row.remove();
				});
				row.appendChild(input);
				row.appendChild(removeBtn);
				return row;
			}

			function resetPastProjects(container, values) {
				if (!container) return;
				container.innerHTML = '';
				var items = Array.isArray(values) ? values : [];
				if (items.length === 0) items = [''];
				items.forEach(function(v){ container.appendChild(createPastProjectRow(v)); });
			}

			function readPastProjects(container) {
				if (!container) return '';
				var inputs = Array.prototype.slice.call(container.querySelectorAll('input.past-project-input'));
				var parts = inputs.map(function(i){ return i.value.trim(); }).filter(function(v){ return v.length > 0; });
				return parts.join(', ');
			}

			// Build client list for autocomplete
			clientRows.forEach(function(row) {
				allClients.push({
					id: row.getAttribute('data-client-id'),
					name: row.getAttribute('data-client-name'),
					number: row.getAttribute('data-client-number'),
					type: row.getAttribute('data-client-type'),
					unionStatus: row.getAttribute('data-union-status'),
					contactPhone: row.getAttribute('data-contact-phone'),
					email: row.getAttribute('data-client-email'),
					address: row.getAttribute('data-client-address'),
					city: row.getAttribute('data-city'),
					state: row.getAttribute('data-state'),
					website: row.getAttribute('data-website'),
					element: row
				});
			});

			function filterAndShowSuggestions(searchTerm) {
				if (!searchTerm.trim()) {
					searchSuggestions.style.display = 'none';
					return;
				}

				var term = searchTerm.toLowerCase();
				var matches = allClients.filter(function(client) {
					return client.name.toLowerCase().includes(term) ||
						client.number.toLowerCase().includes(term) ||
						client.email.toLowerCase().includes(term);
				}).slice(0, 8);

				if (matches.length === 0) {
					searchSuggestions.innerHTML = '<div class="suggestion-item" style="color: #94a3b8;">No clients found</div>';
					searchSuggestions.style.display = 'block';
					return;
				}

				searchSuggestions.innerHTML = matches.map(function(client) {
					return '<div class="suggestion-item" data-client-id="' + client.id + '">' +
						'<strong>' + escapeHtml(client.name) + '</strong>' +
						(client.number ? ' - ' + escapeHtml(client.number) : '') +
						'</div>';
				}).join('');

				searchSuggestions.style.display = 'block';

				// Add click handlers to suggestions
				document.querySelectorAll('#clientSearchSuggestions .suggestion-item').forEach(function(item) {
					item.addEventListener('click', function() {
						var clientId = this.getAttribute('data-client-id');
						selectClientFromSuggestion(clientId);
					});
				});
			}

			function selectClientFromSuggestion(clientId) {
				var client = allClients.find(function(c) { return c.id === clientId; });
				if (!client) return;

				// Set search input to client name
				clientSearch.value = client.name;
				searchSuggestions.style.display = 'none';

				// Filter table to show only this client
				filterTableByClientId(clientId);
			}

			function filterTableByClientId(clientId) {
				clientRows.forEach(function(row) {
					if (row.getAttribute('data-client-id') === clientId) {
						row.classList.remove('hidden-row');
					} else {
						row.classList.add('hidden-row');
					}
				});
			}

			function filterTableBySearchTerm(searchTerm) {
				if (!searchTerm.trim()) {
					// Show all rows
					clientRows.forEach(function(row) {
						row.classList.remove('hidden-row');
					});
					return;
				}

				var term = searchTerm.toLowerCase();
				clientRows.forEach(function(row) {
					var name = row.getAttribute('data-client-name').toLowerCase();
					var number = row.getAttribute('data-client-number').toLowerCase();
					var type = row.getAttribute('data-client-type').toLowerCase();
					var unionStatus = row.getAttribute('data-union-status').toLowerCase();
					var contactPhone = row.getAttribute('data-contact-phone').toLowerCase();
					var email = row.getAttribute('data-client-email').toLowerCase();
					var address = row.getAttribute('data-client-address').toLowerCase();
					var city = row.getAttribute('data-city').toLowerCase();
					var website = row.getAttribute('data-website').toLowerCase();

					if (name.includes(term) || number.includes(term) || type.includes(term) || unionStatus.includes(term) ||
						contactPhone.includes(term) || email.includes(term) ||
						address.includes(term) || city.includes(term) || website.includes(term)) {
						row.classList.remove('hidden-row');
					} else {
						row.classList.add('hidden-row');
					}
				});
			}

			function escapeHtml(text) {
				var map = {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#039;'
				};
				return text.replace(/[&<>"']/g, function(m) { return map[m]; });
			}

			function openEditForExistingClient(name) {
				var needle = normalizeLower(name);
				if (!needle) return;
				var match = allClients.find(function(c){ return normalizeLower(c.name) === needle; });
				if (!match || !match.element) return;
				closeAddModal();
				openEditModal(match.id, buildClientDataFromRow(match.element));
			}

			attachAutocomplete(clientNameInput, clientNameSuggestions, clientNameOptions, 'No matching client names', function(value){
				if (hasExactMatch(clientNameOptions, value)) openEditForExistingClient(value);
			});
			attachAutocomplete(editClientNameInput, editClientNameSuggestions, clientNameOptions, 'No matching client names');
						if (clientNameInput) {
							clientNameInput.addEventListener('input', function(){
								var name = this.value.trim();
								if (!name) { lastDuplicateName = ''; hideToast(); return; }
								if (hasExactMatch(clientNameOptions, name)) {
									if (normalizeLower(name) !== normalizeLower(lastDuplicateName)) {
										showToast('This name already exists.', 'warn', { persist: true });
										lastDuplicateName = name;
									}
								} else {
									lastDuplicateName = '';
									hideToast();
								}
							});
						}
			attachAutocomplete(clientTypeInput, clientTypeSuggestions, clientTypeOptions, 'No matching client types');
			attachAutocomplete(editClientTypeInput, editClientTypeSuggestions, clientTypeOptions, 'No matching client types');
			attachAutocomplete(currentEmployerInput, currentEmployerSuggestions, currentEmployerOptions, 'No matching employers');
			attachAutocomplete(editCurrentEmployerInput, editCurrentEmployerSuggestions, currentEmployerOptions, 'No matching employers');

			if (addPastProjectBtn) {
				addPastProjectBtn.addEventListener('click', function(){
					if (!pastProjectsContainer) return;
					pastProjectsContainer.appendChild(createPastProjectRow(''));
				});
			}
			if (editAddPastProjectBtn) {
				editAddPastProjectBtn.addEventListener('click', function(){
					if (!editPastProjectsContainer) return;
					editPastProjectsContainer.appendChild(createPastProjectRow(''));
				});
			}

			resetPastProjects(pastProjectsContainer, []);

			// Search input event listeners
			if (clientSearch) {
				clientSearch.addEventListener('input', function() {
					filterAndShowSuggestions(this.value);
					filterTableBySearchTerm(this.value);
				});

				clientSearch.addEventListener('keydown', function(e) {
					if (e.key === 'Enter') {
						e.preventDefault();
						searchSuggestions.style.display = 'none';
						filterTableBySearchTerm(this.value);
					}
				});

				clientSearch.addEventListener('blur', function() {
					setTimeout(function() {
						searchSuggestions.style.display = 'none';
					}, 200);
				});

				clientSearch.addEventListener('focus', function() {
					if (this.value.trim()) {
						filterAndShowSuggestions(this.value);
					}
				});
			}

			// Close suggestions when clicking outside
			document.addEventListener('click', function(e) {
				if (e.target !== clientSearch && !searchSuggestions.contains(e.target)) {
					searchSuggestions.style.display = 'none';
				}
			});
			
			function openAddModal(){ if (addModal) { addModal.style.display = 'block'; addModal.setAttribute('aria-hidden', 'false'); } }
			function closeAddModal(){ if (addModal) { addModal.style.display = 'none'; addModal.setAttribute('aria-hidden', 'true'); } if (addForm) addForm.reset(); resetPastProjects(pastProjectsContainer, []); lastDuplicateName = ''; hideToast(); }
			function openEditModal(clientId, data){ 
				document.getElementById('editClientId').value = clientId;
				document.getElementById('editClientNameInput').value = data.client_name || '';
				document.getElementById('editClientNumberInput').value = data.client_number || '';
				document.getElementById('editClientTypeInput').value = data.client_type || '';
				document.getElementById('editUnionStatusInput').value = data.union_status || '';
				document.getElementById('editContactPhoneInput').value = data.contact_phone || '';
				document.getElementById('editClientEmailInput').value = data.client_email || '';
				document.getElementById('editClientAddressInput').value = data.client_address || '';
				document.getElementById('editClientCityInput').value = data.city || '';
				document.getElementById('editClientStateInput').value = data.state || '';
				document.getElementById('editWebsiteInput').value = data.website || '';
				document.getElementById('editClientNotesInput').value = data.notes || '';
				document.getElementById('editFamilyDetailsInput').value = data.family_details || '';
				document.getElementById('editCurrentRoleInput').value = data.current_employer || '';
				document.getElementById('editPreviousEmploymentInput').value = data.previous_employment || '';
				resetPastProjects(editPastProjectsContainer, splitPastProjects(data.past_projects || ''));
				if (editModal) {
					// Move modal to body to avoid container constraints
					if (editModal.parentElement !== document.body) {
						document.body.appendChild(editModal);
					}
					editModal.style.setProperty('display', 'block', 'important');
					editModal.style.position = 'fixed';
					editModal.style.inset = '0';
					editModal.style.width = '100vw';
					editModal.style.height = '100vh';
					editModal.style.background = 'rgba(2,6,23,0.6)';
					editModal.style.zIndex = '2000';
					editModal.style.opacity = '1';
					editModal.style.visibility = 'visible';
					editModal.style.pointerEvents = 'auto';
					editModal.setAttribute('aria-hidden', 'false');
					var editShell = editModal.querySelector('.modal-shell');
					if (editShell) {
						editShell.style.position = 'fixed';
						editShell.style.inset = '0';
						editShell.style.width = '100vw';
						editShell.style.height = '100vh';
						editShell.style.background = '#f8fafc';
						editShell.style.setProperty('display', 'flex', 'important');
						editShell.style.flexDirection = 'column';
						editShell.style.opacity = '1';
						editShell.style.visibility = 'visible';
					}
					document.body.style.overflow = 'hidden';
				}
			}
			function closeEditModal(){
				if (editModal) {
					editModal.style.setProperty('display', 'none', 'important');
					editModal.style.opacity = '';
					editModal.style.visibility = '';
					editModal.style.pointerEvents = '';
					editModal.setAttribute('aria-hidden', 'true');
				}
				if (editForm) editForm.reset();
				document.body.style.overflow = '';
				closeViewModal();
			}
			
			function openViewModal(clientId, data){
				lastSelectedClientId = clientId || '';
				lastSelectedData = data || null;
				document.getElementById('viewClientTitle').textContent = data.client_name || 'Client Details';
				document.getElementById('viewClientName').textContent = data.client_name || '-';
				document.getElementById('viewClientType').textContent = data.client_type || '-';
				document.getElementById('viewUnionStatus').textContent = data.union_status || '-';
				document.getElementById('viewClientNumber').textContent = data.client_number || '-';
				document.getElementById('viewContactPhone').textContent = data.contact_phone || '-';
				document.getElementById('viewClientEmail').textContent = data.client_email || '-';
				document.getElementById('viewClientAddress').textContent = data.client_address || '-';
				document.getElementById('viewClientCity').textContent = data.city || '-';
				document.getElementById('viewClientState').textContent = data.state || '-';
				document.getElementById('viewWebsite').textContent = data.website || '-';
				document.getElementById('viewClientNotes').textContent = data.notes || '-';
				document.getElementById('viewFamilyDetails').textContent = data.family_details || '-';
				document.getElementById('viewCurrentRole').textContent = data.current_employer || '-';
				document.getElementById('viewPreviousEmployment').textContent = data.previous_employment || '-';
				document.getElementById('viewPastProjects').textContent = data.past_projects || '-';
				if (viewModal) {
					if (viewModal.parentElement !== document.body) {
						document.body.appendChild(viewModal);
					}
					viewModal.style.display = 'block';
					viewModal.setAttribute('aria-hidden', 'false');
					document.body.style.overflow = 'hidden';
				}
			}
			
			function closeViewModal(){
				if (viewModal) {
					viewModal.style.display = 'none';
					viewModal.setAttribute('aria-hidden', 'true');
				}
				document.body.style.overflow = '';
			}
			
			if (openAddBtn) openAddBtn.addEventListener('click', openAddModal);
			if (cancelAddBtn) cancelAddBtn.addEventListener('click', closeAddModal);
			if (cancelEditBtn) cancelEditBtn.addEventListener('click', closeEditModal);
			if (closeViewBtn) closeViewBtn.addEventListener('click', closeViewModal);
			if (editViewBtn) {
				editViewBtn.addEventListener('click', function(){
					if (!lastSelectedClientId || !lastSelectedData) {
						return;
					}
					openEditModal(lastSelectedClientId, lastSelectedData);
					closeViewModal();
				});
			}

			// Delete client functionality
			var deleteViewBtn = document.getElementById('deleteViewClientModal');
			if (deleteViewBtn) {
				deleteViewBtn.addEventListener('click', function(){
					if (!lastSelectedClientId) {
						alert('No client selected');
						return;
					}
					if (!confirm('Are you sure you want to delete this client? This action cannot be undone.')) {
						return;
					}
					
					var formData = new FormData();
					formData.append('client_id', lastSelectedClientId);
					
					deleteViewBtn.disabled = true;
					deleteViewBtn.textContent = 'Deleting...';
					
					fetch('../../api/delete_client.php', {
						method: 'POST',
						body: formData,
						credentials: 'same-origin'
					})
					.then(function(r){ return r.json(); })
					.then(function(data){
						if (data.success) {
							alert('Client deleted successfully!');
							closeViewModal();
							window.location.reload();
						} else {
							alert('Error: ' + (data.message || 'Failed to delete client'));
						}
					})
					.catch(function(err){
						console.error(err);
						alert('Network error while deleting client');
					})
					.finally(function(){
						deleteViewBtn.disabled = false;
						deleteViewBtn.textContent = 'Delete';
					});
				});
			}
			
			// Click handlers for client rows
			function buildClientDataFromRow(row) {
				return {
					client_name: row.getAttribute('data-client-name'),
					client_number: row.getAttribute('data-client-number'),
					client_type: row.getAttribute('data-client-type'),
					union_status: row.getAttribute('data-union-status'),
					contact_phone: row.getAttribute('data-contact-phone'),
					client_email: row.getAttribute('data-client-email'),
					client_address: row.getAttribute('data-client-address'),
					city: row.getAttribute('data-city'),
					state: row.getAttribute('data-state'),
					website: row.getAttribute('data-website'),
					notes: row.getAttribute('data-notes'),
					family_details: row.getAttribute('data-family-details') || '',
					current_employer: row.getAttribute('data-current-employer') || '',
					previous_employment: row.getAttribute('data-previous-employment') || '',
					past_projects: row.getAttribute('data-past-projects') || ''
				};
			}

			document.querySelectorAll('.client-row').forEach(function(row){
				row.addEventListener('click', function(){
					var clientId = this.getAttribute('data-client-id');
					var data = buildClientDataFromRow(this);
					openViewModal(clientId, data);
				});
			});

			try {
				var params = new URLSearchParams(window.location.search || '');
				var openId = params.get('client_id');
				if (openId) {
					var targetRow = document.querySelector('.client-row[data-client-id="' + openId + '"]');
					if (targetRow) {
						openViewModal(openId, buildClientDataFromRow(targetRow));
					}
				}
			} catch(e) {}
			
			if (saveAddBtn) {
				saveAddBtn.addEventListener('click', function(){
					var clientName = document.getElementById('clientNameInput').value.trim();
					if (!clientName) { alert('Client Name is required'); return; }
						if (hasExactMatch(clientNameOptions, clientName)) {
							showToast('This name already exists.', 'warn', { persist: true });
							return;
						}
					
					var formData = new FormData();
					formData.append('client_name', clientName);
					formData.append('client_number', document.getElementById('clientNumberInput').value.trim());
						formData.append('client_type', document.getElementById('clientTypeInput').value.trim());
						formData.append('union_status', document.getElementById('unionStatusInput').value.trim());
						formData.append('contact_phone', document.getElementById('contactPhoneInput').value.trim());
					formData.append('client_email', document.getElementById('clientEmailInput').value.trim());
					formData.append('client_address', document.getElementById('clientAddressInput').value.trim());
					formData.append('city', document.getElementById('clientCityInput').value.trim());
					formData.append('state', document.getElementById('clientStateInput').value.trim());
						formData.append('website', document.getElementById('websiteInput').value.trim());
					formData.append('notes', document.getElementById('clientNotesInput').value.trim());
					formData.append('family_details', document.getElementById('familyDetailsInput').value.trim());
					formData.append('current_employer', document.getElementById('currentRoleInput').value.trim());
					formData.append('previous_employment', document.getElementById('previousEmploymentInput').value.trim());
					formData.append('past_projects', readPastProjects(pastProjectsContainer));
					
					saveAddBtn.disabled = true;
					saveAddBtn.textContent = 'Saving...';
					
					fetch('../../api/add_client.php', {
						method: 'POST',
						body: formData,
						credentials: 'same-origin'
					})
					.then(function(r){ return r.json(); })
					.then(function(data){
						if (data.success) {
							alert('Client added successfully!');
							closeAddModal();
							window.location.reload();
						} else {
							alert('Error: ' + (data.message || 'Failed to add client'));
						}
					})
					.catch(function(err){
						console.error(err);
						alert('Network error while adding client');
					})
					.finally(function(){
						saveAddBtn.disabled = false;
						saveAddBtn.textContent = 'Save';
					});
				});
			}
			
			if (addModal) {
				addModal.addEventListener('click', function(e){ if (e.target === addModal) closeAddModal(); });
			}

			if (editModal) {
				editModal.addEventListener('click', function(e){ if (e.target === editModal) closeEditModal(); });
			}

			if (viewModal) {
				viewModal.addEventListener('click', function(e){ if (e.target === viewModal) closeViewModal(); });
			}

			if (saveEditBtn) {
				saveEditBtn.addEventListener('click', function(){
					var clientId = document.getElementById('editClientId').value;
					var clientName = document.getElementById('editClientNameInput').value.trim();
					if (!clientName) { alert('Client Name is required'); return; }
					
					var formData = new FormData();
					formData.append('client_id', clientId);
					formData.append('client_name', clientName);
					formData.append('client_number', document.getElementById('editClientNumberInput').value.trim());
					formData.append('client_type', document.getElementById('editClientTypeInput').value.trim());
					formData.append('union_status', document.getElementById('editUnionStatusInput').value.trim());
					formData.append('contact_phone', document.getElementById('editContactPhoneInput').value.trim());
					formData.append('client_email', document.getElementById('editClientEmailInput').value.trim());
					formData.append('client_address', document.getElementById('editClientAddressInput').value.trim());
					formData.append('city', document.getElementById('editClientCityInput').value.trim());
					formData.append('state', document.getElementById('editClientStateInput').value.trim());
					formData.append('website', document.getElementById('editWebsiteInput').value.trim());
					formData.append('notes', document.getElementById('editClientNotesInput').value.trim());
					formData.append('family_details', document.getElementById('editFamilyDetailsInput').value.trim());
					formData.append('current_employer', document.getElementById('editCurrentRoleInput').value.trim());
					formData.append('previous_employment', document.getElementById('editPreviousEmploymentInput').value.trim());
					formData.append('past_projects', readPastProjects(editPastProjectsContainer));
					
					saveEditBtn.disabled = true;
					saveEditBtn.textContent = 'Saving...';
					
					fetch('../../api/update_client.php', {
						method: 'POST',
						body: formData,
						credentials: 'same-origin'
					})
					.then(function(r){ return r.json(); })
					.then(function(data){
						if (data.success) {
							alert('Client updated successfully!');
							closeEditModal();
							window.location.reload();
						} else {
							alert('Error: ' + (data.message || 'Failed to update client'));
						}
					})
					.catch(function(err){
						console.error(err);
						alert('Network error while updating client');
					})
					.finally(function(){
						saveEditBtn.disabled = false;
						saveEditBtn.textContent = 'Save';
					});
				});
			}

			// Add More Previous Employment functionality for Add Modal
			var addMoreBtn = document.getElementById('addMorePrevEmployment');
			var prevEmploymentContainer = document.getElementById('previousEmploymentContainer');
			if (addMoreBtn && prevEmploymentContainer) {
				addMoreBtn.addEventListener('click', function(){
					var newInputDiv = document.createElement('div');
					newInputDiv.style.cssText = 'display:flex;gap:8px;align-items:flex-end;margin-bottom:8px;';
					newInputDiv.innerHTML = '<div style="flex:1;"><input type="text" class="previousEmploymentInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" placeholder="Enter previous employment" /></div><button type="button" class="removePrevEmployment" style="padding:10px;background:none;color:#ef4444;border:none;font-weight:700;cursor:pointer;font-size:20px;line-height:1;">&times;</button>';
					prevEmploymentContainer.appendChild(newInputDiv);
					var removeBtn = newInputDiv.querySelector('.removePrevEmployment');
					if (removeBtn) {
						removeBtn.addEventListener('click', function(){
							newInputDiv.remove();
						});
					}
				});
			}
			
			// Add More Previous Employment functionality for Edit Modal
			var editAddMoreBtn = document.getElementById('editAddMorePrevEmployment');
			var editPrevEmploymentContainer = document.getElementById('editPreviousEmploymentContainer');
			if (editAddMoreBtn && editPrevEmploymentContainer) {
				editAddMoreBtn.addEventListener('click', function(){
					var newInputDiv = document.createElement('div');
					newInputDiv.style.cssText = 'display:flex;gap:8px;align-items:flex-end;margin-bottom:8px;';
					newInputDiv.innerHTML = '<div style="flex:1;"><input type="text" class="editPreviousEmploymentInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" placeholder="Enter previous employment" /></div><button type="button" class="removeEditPrevEmployment" style="padding:10px;background:none;color:#ef4444;border:none;font-weight:700;cursor:pointer;font-size:20px;line-height:1;">&times;</button>';
					editPrevEmploymentContainer.appendChild(newInputDiv);
					var removeBtn = newInputDiv.querySelector('.removeEditPrevEmployment');
					if (removeBtn) {
						removeBtn.addEventListener('click', function(){
							newInputDiv.remove();
						});
					}
				});
			}

			var usersToggle = document.getElementById('usersToggle');
			var usersGroup = document.getElementById('usersGroup');
			if (usersToggle && usersGroup) {
				usersToggle.addEventListener('click', function(){
					usersGroup.classList.toggle('open');
				});
			}

			// Toggle dev options sub-nav
			var devToggle = document.getElementById('devToggle');
			var devGroup = document.getElementById('devGroup');
			if (devToggle && devGroup) {
				devToggle.addEventListener('click', function(){
					devGroup.classList.toggle('open');
				});
			}
			// Toggle maintenance sub-nav
			var maintenanceToggle = document.getElementById('maintenanceToggle');
			var maintenanceGroup = document.getElementById('maintenanceGroup');
			if (maintenanceToggle && maintenanceGroup) {
				maintenanceToggle.addEventListener('click', function(){
					maintenanceGroup.classList.toggle('open');
				});
			}
		})();
	</script>
	<script src="../../assets/js/mobile-menu.js"></script>
	<script src="../../assets/js/logout-confirm.js"></script>
</body>
</html>
