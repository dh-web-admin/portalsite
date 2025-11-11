<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Basic auth: ensure user is logged in
if (!isset($_SESSION['email'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated']);
  exit;
}

$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$column = isset($_POST['column']) ? trim($_POST['column']) : '';
$value = isset($_POST['value']) ? $_POST['value'] : '';

if ($project_id <= 0 || $column === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Missing parameters']);
  exit;
}

// Whitelist of editable columns (must match table column names)
// Exclude Project_ID and Project_Name (project name intentionally not editable here)
// NOTE: Keep this list exactly matched to DB column names used in pages/project_checklist/index.php
$editable = array(
  'Status','City','County','State','Coordinates','Client','Anticipated_Start_Date','State_License','City_License','Get_Contract','Review_and_Sign_Contract','Get_Tax_Exempt_Form','Complete_Vendor_Form','Send_W9','Send_BWC','Updated_BWC','Request_Certificate_of_INS','Send_Certificate_of_INS','Send_to_Lawyer','Request_NOC','Send_NOF','File_NOC_NOF','Get_Signed_Quote','Complete_Win_Packet','Create_Foreman_Field_Folder','Add_to_Project_Calendar','Soil_Testing','Soil_Sampling','Lab','Mix_Design_Sent','Results','Mix_Design_Approval','Call_OUPS','Schedule_Mobilization','Schedule_Field_Testing','Get_Field_Testing_Results','Send_Submittals','Schedule_Fuel','Fuel_Supplier','Selected_Material_Supplier','Schedule_Material','Selected_Trucking_Company','Schedule_Trucker','Hotel','Find_Water','Water_Semi','Schedule_Men','Grade_File','Cure_Type','Schedule_Cure','Cure_Provider','Turn_in_Paperwork','AIA','Process_Field_Paperwork','Review_Processed_Paperwork','Invoice','Sign_Change_Order','Send_Signed_Change_Order','Send_Supplier_Lein_Waiver','Supplier_Lein_Waiver','DHSS_Lein_Waiver'
);

if (!in_array($column, $editable, true)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Column not editable: ' . $column]);
  exit;
}

// Ensure column exists in table to avoid SQL errors
$colCheck = $conn->query("SHOW COLUMNS FROM `Projects` LIKE '" . $conn->real_escape_string($column) . "'");
if (!($colCheck && $colCheck->num_rows > 0)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Column does not exist']);
  exit;
}

// Update using a prepared statement; column name injected only after whitelist check
$sql = "UPDATE `Projects` SET `" . $column . "` = ? WHERE `Project_ID` = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
  exit;
}

$stmt->bind_param('si', $value, $project_id);
if ($stmt->execute()) {
  echo json_encode(['success' => true, 'message' => 'Saved']);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Save failed']);
}
$stmt->close();
?>
