<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
// Indicate this is an API endpoint to prevent UI injection from permissions helper
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

require_edit_api('Bid_tracking');

$projectName = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept both form-encoded and JSON
    $input = $_POST;
    if (empty($input)) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (is_array($data)) $input = $data;
    }

    $projectName = isset($input['project_name']) ? trim($input['project_name']) : '';
    $dhss = isset($input['dhss_project_number']) ? trim($input['dhss_project_number']) : null;
    $bidDate = isset($input['bid_date']) ? trim($input['bid_date']) : null;
    $city = isset($input['project_city']) ? trim($input['project_city']) : null;
    $county = isset($input['project_county']) ? trim($input['project_county']) : null;
    $state = isset($input['project_state']) ? trim($input['project_state']) : null;

    if ($projectName === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Project name is required']);
        exit();
    }

    // Insert into bids table
    $sql = 'INSERT INTO bids (dhss_project_number, project_name, bid_date, project_city, project_county, project_state) VALUES (?,?,?,?,?,?)';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error]);
        exit();
    }

    // Normalize empty strings to NULL for optional fields
    if ($dhss === '') $dhss = null;
    if ($bidDate === '') $bidDate = null;
    if ($city === '') $city = null;
    if ($county === '') $county = null;
    if ($state === '') $state = null;

    $stmt->bind_param('ssssss', $dhss, $projectName, $bidDate, $city, $county, $state);

    // Attempt insert with retry on duplicate DHSS number
    $maxRetries = 10;
    $attempt = 0;
    try {
        while (true) {
            try {
                $ok = $stmt->execute();
                break; // success
            } catch (mysqli_sql_exception $e) {
                // Duplicate entry error code is 1062
                if ($e->getCode() == 1062 && $dhss) {
                    // Compute a new DHSS by incrementing the numeric suffix for the same year prefix
                    $prefix = substr($dhss, 0, 2);
                    $q = $conn->prepare('SELECT MAX(dhss_project_number) AS maxval FROM bids WHERE dhss_project_number LIKE ?');
                    if ($q) {
                        $like = $prefix . '%';
                        $q->bind_param('s', $like);
                        $q->execute();
                        $res = $q->get_result();
                        $row = $res ? $res->fetch_assoc() : null;
                        $q->close();
                        $maxval = $row['maxval'] ?? null;
                        $nextSeq = 1;
                        if ($maxval) {
                            $digits = preg_replace('/[^0-9]/', '', $maxval);
                            if (strlen($digits) >= 6) {
                                $seq = intval(substr($digits, 2));
                            } else {
                                $seq = intval($digits);
                            }
                            $nextSeq = $seq + 1;
                        }
                        $dhss = $prefix . str_pad((string)$nextSeq, 4, '0', STR_PAD_LEFT);
                        // rebind the new value
                        $stmt->bind_param('ssssss', $dhss, $projectName, $bidDate, $city, $county, $state);
                        $attempt++;
                        if ($attempt >= $maxRetries) {
                            throw $e; // give up and bubble up
                        }
                        // retry loop
                        continue;
                    } else {
                        throw $e; // can't compute fallback
                    }
                }
                throw $e; // rethrow other DB errors
            }
        }
    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        $msg = $e->getMessage();
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $msg, 'code' => $e->getCode()]);
        $stmt->close();
        exit();
    }

    $insertId = $stmt->insert_id;
    $stmt->close();

    // Fetch inserted row to return
    $rstmt = $conn->prepare('SELECT * FROM bids WHERE bid_id = ? LIMIT 1');
    if ($rstmt) {
        $rstmt->bind_param('i', $insertId);
        $rstmt->execute();
        $res = $rstmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $rstmt->close();
        echo json_encode(['success' => true, 'project' => $row]);
        exit();
    }

    echo json_encode(['success' => true, 'project' => ['bid_id' => $insertId, 'project_name' => $projectName]]);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
exit();
