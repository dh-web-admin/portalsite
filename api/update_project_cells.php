<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['email'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated']);
  exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!is_array($data) || empty($data['changes'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid payload']);
  exit;
}

$changes = $data['changes'];
// Basic validation of structure
foreach ($changes as $c) {
  if (!isset($c['project_id']) || !isset($c['column']) || !array_key_exists('value', $c)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid change item']);
    exit;
  }
}

// Whitelist same as single-update endpoint. Keep in sync.
// NOTE: Keep this list exactly matched to DB column names used in pages/project_checklist/index.php
$editable = array(
  'Status','City','County','State','Coordinates','Client','Anticipated_Start_Date','State_License','City_License','Get_Contract','Review_and_Sign_Contract','Get_Tax_Exempt_Form','Complete_Vendor_Form','Send_W9','Send_BWC','Updated_BWC','Request_Certificate_of_INS','Send_Certificate_of_INS','Send_to_Lawyer','Request_NOC','Send_NOF','File_NOC_NOF','Get_Signed_Quote','Complete_Win_Packet','Create_Foreman_Field_Folder','Add_to_Project_Calendar','Soil_Testing','Soil_Sampling','Lab','Mix_Design_Sent','Results','Mix_Design_Approval','Call_OUPS','Schedule_Mobilization','Schedule_Field_Testing','Get_Field_Testing_Results','Send_Submittals','Schedule_Fuel','Fuel_Supplier','Selected_Material_Supplier','Schedule_Material','Selected_Trucking_Company','Schedule_Trucker','Hotel','Find_Water','Water_Semi','Schedule_Men','Grade_File','Cure_Type','Schedule_Cure','Cure_Provider','Turn_in_Paperwork','AIA','Process_Field_Paperwork','Review_Processed_Paperwork','Invoice','Sign_Change_Order','Send_Signed_Change_Order','Send_Supplier_Lein_Waiver','Supplier_Lein_Waiver','DHSS_Lein_Waiver'
);

// Verify all columns in payload are editable
foreach ($changes as $c) {
  if (!in_array($c['column'], $editable, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Column not editable: ' . $c['column']]);
    exit;
  }
}

// Begin transaction and apply updates
if (!$conn->begin_transaction()) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to start transaction']);
  exit;
}

$results = [];
try {
  foreach ($changes as $c) {
    $project_id = intval($c['project_id']);
    $column = $c['column'];
    $value = $c['value'];

    // Check column exists
    $colCheck = $conn->query("SHOW COLUMNS FROM `Projects` LIKE '" . $conn->real_escape_string($column) . "'");
    if (!($colCheck && $colCheck->num_rows > 0)) {
      throw new Exception('Column does not exist: ' . $column);
    }

    $sql = "UPDATE `Projects` SET `" . $column . "` = ? WHERE `Project_ID` = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('DB prepare failed');
    $stmt->bind_param('si', $value, $project_id);
    if (!$stmt->execute()) {
      $stmt->close();
      throw new Exception('Update failed for project ' . $project_id);
    }
    $stmt->close();
    $results[] = ['project_id' => $project_id, 'column' => $column, 'success' => true];
  }

  $conn->commit();
  echo json_encode(['success' => true, 'results' => $results]);
} catch (Exception $ex) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
}

?>
