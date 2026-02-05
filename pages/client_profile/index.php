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
$clientStmt = $conn->prepare('SELECT client_id, client_name, client_number, client_email, client_address, city, state, notes, family_details, `current_role`, previous_employment, past_projects FROM clients ORDER BY client_name ASC');
if ($clientStmt) {
	$clientStmt->execute();
	$clientResult = $clientStmt->get_result();
	while ($row = $clientResult->fetch_assoc()) {
		$clients[] = $row;
	}
	$clientStmt->close();
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
		.admin-container { text-align: left; }
		.welcome-section { justify-content: flex-start; }
		.welcome-logo { margin-left: 0; }
		.header-actions { justify-content: flex-start; }
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
	</style>
</head>
<body class="admin-page">
	<div class="admin-container">
		<?php include __DIR__ . '/../../partials/portalheader.php'; ?>
		<div class="admin-layout">
			<?php include __DIR__ . '/../../partials/sidebar.php'; ?>
			<main class="content-area">
				<div class="main-content">
					<div style="margin-top:12px;margin-bottom:6px;display:flex;gap:10px;align-items:center;justify-content:center;width:100%;">
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
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Client Name</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Client Number</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Client Email</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Client Address</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">City</th>
									<th style="padding:12px 14px;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">State</th>
								</tr>
							</thead>
							<tbody>
								<?php if (empty($clients)): ?>
								<tr>
									<td colspan="6" style="padding:12px 14px;text-align:center;color:#64748b;">No clients found</td>
								</tr>
								<?php else: ?>
									<?php foreach ($clients as $client): ?>
									<tr class="client-row" data-client-id="<?php echo htmlspecialchars($client['client_id']); ?>" data-client-name="<?php echo htmlspecialchars($client['client_name']); ?>" data-client-number="<?php echo htmlspecialchars($client['client_number'] ?? ''); ?>" data-client-email="<?php echo htmlspecialchars($client['client_email'] ?? ''); ?>" data-client-address="<?php echo htmlspecialchars($client['client_address'] ?? ''); ?>" data-city="<?php echo htmlspecialchars($client['city'] ?? ''); ?>" data-state="<?php echo htmlspecialchars($client['state'] ?? ''); ?>" data-notes="<?php echo htmlspecialchars($client['notes'] ?? ''); ?>" data-family-details="<?php echo htmlspecialchars($client['family_details'] ?? ''); ?>" data-current-role="<?php echo htmlspecialchars($client['current_role'] ?? ''); ?>" data-previous-employment="<?php echo htmlspecialchars($client['previous_employment'] ?? ''); ?>" data-past-projects="<?php echo htmlspecialchars($client['past_projects'] ?? ''); ?>" style="border-bottom:1px solid #e2e8f0;cursor:pointer;transition:background 0.2s ease;" onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='';">
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['client_name']); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['client_number'] ?? ''); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['client_email'] ?? ''); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['client_address'] ?? ''); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['city'] ?? ''); ?></td>
										<td style="padding:12px 14px;color:#0f172a;"><?php echo htmlspecialchars($client['state'] ?? ''); ?></td>
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
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Name</label>
									<input type="text" id="clientNameInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" required />
								</div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Number</label>
									<input type="text" id="clientNumberInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Email</label>
									<input type="email" id="clientEmailInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
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
						<div style="margin-bottom:16px;">					<h4 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 12px 0;">Current Role</h4>
					<input type="text" id="currentRoleInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" placeholder="Enter current role" />
				</div>
				<div style="margin-bottom:16px;">							<h4 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 12px 0;">Previous Employment</h4>
					<textarea id="previousEmploymentInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;resize:vertical;min-height:100px;" placeholder="Add previous employment details..."></textarea>
						</div>
						<div style="margin-top:24px;padding-top:16px;border-top:2px solid #e2e8f0;">
							<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;">Past Projects/Interactions</h3>
							<div style="margin-bottom:16px;">
								<textarea id="pastProjectsInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;resize:vertical;min-height:100px;" placeholder="Add details about past projects or interactions..."></textarea>
							</div>
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
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Name</label>
									<input type="text" id="editClientNameInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" required />
								</div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Number</label>
									<input type="text" id="editClientNumberInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
								<div>
									<label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">Client Email</label>
									<input type="email" id="editClientEmailInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" />
								</div>
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
						<div style="margin-bottom:16px;">					<h4 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 12px 0;">Current Role</h4>
					<input type="text" id="editCurrentRoleInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;" placeholder="Enter current role" />
				</div>
				<div style="margin-bottom:16px;">							<h4 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 12px 0;">Previous Employment</h4>
					<textarea id="editPreviousEmploymentInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;resize:vertical;min-height:100px;" placeholder="Add previous employment details..."></textarea>
						</div>
						<div style="margin-top:24px;padding-top:16px;border-top:2px solid #e2e8f0;">
							<h3 style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 16px 0;">Past Projects/Interactions</h3>
							<div style="margin-bottom:16px;">
								<textarea id="editPastProjectsInput" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;resize:vertical;min-height:100px;" placeholder="Add details about past projects or interactions..."></textarea>
							</div>
						</div>
				</div>
			</div>
		</div>
	</div>

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
									<label style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px;">Client Number</label>
									<p style="margin:0;color:#0f172a;font-size:14px;" id="viewClientNumber">-</p>
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
								<h4 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 8px 0;">Current Role</h4>
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
			var addModal = document.getElementById('addClientModal');
			var openAddBtn = document.getElementById('openAddClientModal');
			var cancelAddBtn = document.getElementById('cancelAddClientModal');
			var saveAddBtn = document.getElementById('saveAddClientModal');
			var addForm = document.getElementById('addClientForm');
			
			var editModal = document.getElementById('editClientModal');
			var cancelEditBtn = document.getElementById('cancelEditClientModal');
			var saveEditBtn = document.getElementById('saveEditClientModal');
			var editForm = document.getElementById('editClientForm');
			
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

			// Build client list for autocomplete
			clientRows.forEach(function(row) {
				allClients.push({
					id: row.getAttribute('data-client-id'),
					name: row.getAttribute('data-client-name'),
					number: row.getAttribute('data-client-number'),
					email: row.getAttribute('data-client-email'),
					address: row.getAttribute('data-client-address'),
					city: row.getAttribute('data-city'),
					state: row.getAttribute('data-state'),
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
					var email = row.getAttribute('data-client-email').toLowerCase();
					var address = row.getAttribute('data-client-address').toLowerCase();
					var city = row.getAttribute('data-city').toLowerCase();

					if (name.includes(term) || number.includes(term) || 
						email.includes(term) || address.includes(term) || city.includes(term)) {
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
			function closeAddModal(){ if (addModal) { addModal.style.display = 'none'; addModal.setAttribute('aria-hidden', 'true'); } if (addForm) addForm.reset(); }
			function openEditModal(clientId, data){ 
				document.getElementById('editClientId').value = clientId;
				document.getElementById('editClientNameInput').value = data.client_name || '';
				document.getElementById('editClientNumberInput').value = data.client_number || '';
				document.getElementById('editClientEmailInput').value = data.client_email || '';
				document.getElementById('editClientAddressInput').value = data.client_address || '';
				document.getElementById('editClientCityInput').value = data.city || '';
				document.getElementById('editClientStateInput').value = data.state || '';
				document.getElementById('editClientNotesInput').value = data.notes || '';
				document.getElementById('editFamilyDetailsInput').value = data.family_details || '';
				document.getElementById('editCurrentRoleInput').value = data.current_role || '';
				document.getElementById('editPreviousEmploymentInput').value = data.previous_employment || '';
				document.getElementById('editPastProjectsInput').value = data.past_projects || '';
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
				document.getElementById('viewClientNumber').textContent = data.client_number || '-';
				document.getElementById('viewClientEmail').textContent = data.client_email || '-';
				document.getElementById('viewClientAddress').textContent = data.client_address || '-';
				document.getElementById('viewClientCity').textContent = data.city || '-';
				document.getElementById('viewClientState').textContent = data.state || '-';
				document.getElementById('viewClientNotes').textContent = data.notes || '-';
				document.getElementById('viewFamilyDetails').textContent = data.family_details || '-';
				document.getElementById('viewCurrentRole').textContent = data.current_role || '-';
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
			document.querySelectorAll('.client-row').forEach(function(row){
				row.addEventListener('click', function(){
					var clientId = this.getAttribute('data-client-id');
					var data = {
						client_name: this.getAttribute('data-client-name'),
						client_number: this.getAttribute('data-client-number'),
						client_email: this.getAttribute('data-client-email'),
						client_address: this.getAttribute('data-client-address'),
						city: this.getAttribute('data-city'),
						state: this.getAttribute('data-state'),
						notes: this.getAttribute('data-notes'),
						family_details: this.getAttribute('data-family-details') || '',
						current_role: this.getAttribute('data-current-role') || '',
						previous_employment: this.getAttribute('data-previous-employment') || '',
						past_projects: this.getAttribute('data-past-projects') || ''
					};
					openViewModal(clientId, data);
				});
			});
			
			if (saveAddBtn) {
				saveAddBtn.addEventListener('click', function(){
					var clientName = document.getElementById('clientNameInput').value.trim();
					if (!clientName) { alert('Client Name is required'); return; }
					
					var formData = new FormData();
					formData.append('client_name', clientName);
					formData.append('client_number', document.getElementById('clientNumberInput').value.trim());
					formData.append('client_email', document.getElementById('clientEmailInput').value.trim());
					formData.append('client_address', document.getElementById('clientAddressInput').value.trim());
					formData.append('city', document.getElementById('clientCityInput').value.trim());
					formData.append('state', document.getElementById('clientStateInput').value.trim());
					formData.append('notes', document.getElementById('clientNotesInput').value.trim());
					formData.append('family_details', document.getElementById('familyDetailsInput').value.trim());
					formData.append('current_role', document.getElementById('currentRoleInput').value.trim());
					formData.append('previous_employment', document.getElementById('previousEmploymentInput').value.trim());
					formData.append('past_projects', document.getElementById('pastProjectsInput').value.trim());
					
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
					formData.append('client_email', document.getElementById('editClientEmailInput').value.trim());
					formData.append('client_address', document.getElementById('editClientAddressInput').value.trim());
					formData.append('city', document.getElementById('editClientCityInput').value.trim());
					formData.append('state', document.getElementById('editClientStateInput').value.trim());
					formData.append('notes', document.getElementById('editClientNotesInput').value.trim());
					formData.append('family_details', document.getElementById('editFamilyDetailsInput').value.trim());
					formData.append('current_role', document.getElementById('editCurrentRoleInput').value.trim());
					formData.append('previous_employment', document.getElementById('editPreviousEmploymentInput').value.trim());
					formData.append('past_projects', document.getElementById('editPastProjectsInput').value.trim());
					
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
