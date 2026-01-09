<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json');

if (isset($conn)) $GLOBALS['conn'] = $conn;
require_edit_api('maps');

// Auth enforced by require_edit_api('maps')

// Validate required fields
if (!isset($_POST['id']) || empty($_POST['id'])) {
  echo json_encode([
    'success' => false,
    'message' => 'Supplier ID is required'
  ]);
  exit;
}

try {
  $id = intval($_POST['id']);
  
  // Prepare DELETE statement
  $sql = "DELETE FROM suppliers WHERE id = ?";
  
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $id);
  
  if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
      echo json_encode([
        'success' => true,
        'message' => 'Supplier deleted successfully'
      ]);
    } else {
      echo json_encode([
        'success' => false,
        'message' => 'Supplier not found'
      ]);
    }
  } else {
    echo json_encode([
      'success' => false,
      'message' => 'Failed to delete supplier: ' . $stmt->error
    ]);
  }
  
  $stmt->close();
  $conn->close();
  
} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'message' => 'Error: ' . $e->getMessage()
  ]);
}
?>
