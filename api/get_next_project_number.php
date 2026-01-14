<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

// This endpoint returns a suggested next DHSS project number in the format YYNNNN
// where YY = last two digits of current year and NNNN is a zero-padded sequence.
// Behavior: Only `bids` (or detected table) is considered. If no existing value for
// the current year is found, suggestion starts at YY0001.

// Permission: read-only access is fine; require login
if (!isset($_SESSION['email'])) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Not authenticated']);
  exit();
}

try {
  $year = (int)date('y'); // two-digit year
  $prefix = str_pad((string)$year, 2, '0', STR_PAD_LEFT);

  // Candidate column names to look for
  $candidateCols = ['DHSS_Project_Number','DHSSProjectNumber','dhss_project_number','dhss_project','DHSS_ProjectNumber'];
  $foundCol = null;
  $foundTable = null;

  // Only check the `bids` table (do not use Projects table)
  $tbl = 'bids';
  $colRes = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tbl) . "`");
  if ($colRes) {
    while ($col = $colRes->fetch_assoc()) {
      $name = $col['Field'];
      if (in_array($name, $candidateCols, true)) { $foundCol = $name; $foundTable = $tbl; break; }
    }
  }

  $nextSeq = null;
  if ($foundCol && $foundTable) {
    $sql = "SELECT MAX(`" . $foundCol . "`) AS maxval FROM `" . $conn->real_escape_string($foundTable) . "` WHERE `" . $foundCol . "` LIKE ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
      $like = $prefix . '%';
      $stmt->bind_param('s', $like);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $stmt->close();
      if ($row && !empty($row['maxval'])) {
        $maxval = preg_replace('/[^0-9]/', '', $row['maxval']);
        if (strlen($maxval) >= 6) {
          $seq = intval(substr($maxval, 2));
        } else {
          $seq = intval($maxval);
        }
        $nextSeq = $seq + 1;
      }
    }
  }

  // If we couldn't compute a next sequence (no rows for this year or no table/column), start at 1
  if ($nextSeq === null) {
    $nextSeq = 1;
  }

  if ($nextSeq < 1) $nextSeq = 1;
  $seqPart = str_pad((string)$nextSeq, 4, '0', STR_PAD_LEFT);
  $suggested = $prefix . $seqPart;

  echo json_encode(['success' => true, 'suggested' => $suggested, 'year_prefix' => $prefix, 'sequence' => (int)$nextSeq]);
  exit();
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error']);
  exit();
}
