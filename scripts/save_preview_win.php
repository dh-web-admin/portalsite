<?php
// Generates an HTML preview for the project-win email using the template from api/update_bid.php
$dst = __DIR__ . '/preview_win.html';
require_once __DIR__ . '/../config/config.php';
// Get one recent bid that has project_name
$res = $conn->query("SELECT * FROM bids WHERE project_name IS NOT NULL AND project_name != '' LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;
if (!$row) {
    $row = [
        'project_name' => 'Sample Project',
        'project_address' => '123 Main St',
        'project_city' => 'Anytown',
        'project_county' => 'AnyCounty',
        'project_state' => 'AS',
        'client_winner' => 'Acme GC'
    ];
}
$projectName = isset($row['project_name']) ? $row['project_name'] : '';
$projAddrParts = [];
if (!empty($row['project_address'])) $projAddrParts[] = $row['project_address'];
if (!empty($row['project_city'])) $projAddrParts[] = $row['project_city'];
if (!empty($row['project_county'])) $projAddrParts[] = $row['project_county'];
if (!empty($row['project_state'])) $projAddrParts[] = $row['project_state'];
$projectAddress = implode(', ', $projAddrParts);
$gc = '';
if (!empty($row['client_winner'])) $gc = $row['client_winner'];

$html = "<div style='font-family: Arial, sans-serif; max-width:600px; color:#0f172a;'>" .
    "<div style='background:#0b76ef;color:#fff;padding:12px;border-radius:6px;'><h2 style=\"margin:0;font-size:18px;\">Project Won Notification</h2></div>" .
    "<div style='padding:16px;background:#fff;border:1px solid #eef2f6;border-top:0;border-radius:0 0 6px 6px;'>" .
    "<div style=\"font-size:15px;margin-bottom:12px;\">Great News Sam Doe,</div>" .
    "<div style=\"background:#f1f8ff;border-left:4px solid #0b76ef;padding:12px;border-radius:6px;margin-bottom:12px;\">" .
    "<div style=\"font-size:16px;color:#0b1726;\">We have won " . htmlspecialchars($projectName) . "</div>" .
    "<div style=\"color:#475569;margin-top:8px;line-height:1.45;\">" .
    "<div><strong>Project:</strong> " . htmlspecialchars($projectName) . "</div>" .
    "<div><strong>Address:</strong> " . htmlspecialchars($projectAddress) . "</div>" .
    "<div><strong>General Contractor:</strong> " . htmlspecialchars($gc) . "</div>" .
    "</div></div>" .
    "<div style=\"font-size:13px;color:#334155;\">This project was created in Project Checklist.</div>" .
    "<div style=\"margin-top:12px;font-size:13px;color:#6b7280;\">To stop receiving project-win notifications, open the Bid Tracking bell icon → Email Notifications and toggle \"Enable project win notification\".</div>" .
    "</div></div>";

file_put_contents($dst, "<!-- Project Won Preview -->\n" . $html);

echo "Wrote preview to scripts/preview_win.html\n";
