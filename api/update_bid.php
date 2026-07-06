<?php
// Temporary debug: log incoming requests to help diagnose HTML responses
// (Remove this block after debugging)
$__dbg = [];
$__dbg['time'] = date('c');
$__dbg['script'] = __FILE__;
$__dbg['request_uri'] = $_SERVER['REQUEST_URI'] ?? '';
$__dbg['method'] = $_SERVER['REQUEST_METHOD'] ?? '';
$__dbg['remote_addr'] = $_SERVER['REMOTE_ADDR'] ?? '';
$__dbg['server_name'] = $_SERVER['SERVER_NAME'] ?? '';
$__dbg['cookie_header'] = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : null;
$__dbg['cookies'] = $_COOKIE ?? [];
$__dbg['get'] = $_GET ?? [];
$__dbg['post_raw_length'] = strlen(file_get_contents('php://input'));
$__hdrs = [];
foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'HTTP_') === 0) {
        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
        $__hdrs[$name] = $v;
    }
}
$__dbg['headers'] = $__hdrs;
@file_put_contents(__DIR__ . '/update_bid_access.log', json_encode($__dbg) . PHP_EOL, FILE_APPEND);

header('Content-Type: application/json; charset=utf-8');

// Ensure no PHP warnings or whitespace break JSON output
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

// (Optional) Better mysqli error reporting during development
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_edit_api('Bid_tracking');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    // clear any buffered output to avoid mixing HTML with JSON
    @ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Accept both form-encoded and JSON
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data)) $input = $data;
}

$bidId = isset($input['bid_id']) ? intval($input['bid_id']) : 0;
if ($bidId <= 0) {
    http_response_code(400);
    @ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Missing or invalid bid_id']);
    exit();
}

// Discover updatable columns from the table schema (exclude id and timestamps)
$colsRes = $conn->query("SHOW COLUMNS FROM bids");
$allowed = [];
$numericCols = [];
if ($colsRes) {
    while ($c = $colsRes->fetch_assoc()) {
        $f = $c['Field'];
        if (in_array($f, ['bid_id','created_at','updated_at'], true)) continue;
        $allowed[] = $f;
        $t = isset($c['Type']) ? strtolower((string)$c['Type']) : '';
        if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double|real)\b/', $t)) {
            $numericCols[$f] = true;
        }
    }
}

function normalize_numeric_like_value($raw) {
    if ($raw === null) return null;
    $s = trim((string)$raw);
    if ($s === '') return $raw;
    // Accept comma-separated decimal strings (e.g. 123,123.4565)
    if (!preg_match('/^\$?\s*[+-]?\d[\d,]*(?:\.\d+)?\s*$/', $s)) return $raw;
    $cleaned = str_replace([',', '$', ' '], '', $s);
    if (preg_match('/^[+-]?\d+(?:\.\d+)?$/', $cleaned)) return $cleaned;
    return $raw;
}

// DEBUG: log the schema info for the `status` column so we know its runtime type
$stypeRes = $conn->query("SHOW FULL COLUMNS FROM bids LIKE 'status'");
if ($stypeRes) {
    $sinfo = $stypeRes->fetch_assoc();
    @file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " STATUS_COLUMN: " . json_encode($sinfo) . "\n", FILE_APPEND);
}

// Fetch current row before applying updates to detect status transitions
$currentRow = null;
$preStmt = $conn->prepare('SELECT * FROM bids WHERE bid_id = ? LIMIT 1');
if ($preStmt) {
    $preStmt->bind_param('i', $bidId);
    $preStmt->execute();
    $resPre = $preStmt->get_result();
    $currentRow = $resPre ? $resPre->fetch_assoc() : null;
    $preStmt->close();
}


// Build update list from provided input keys intersecting allowed columns
$updateFields = [];
$values = [];

foreach ($allowed as $col) {
    if (array_key_exists($col, $input)) {
        $updateFields[] = $col;
        $v = $input[$col];
        if ($v !== '' && isset($numericCols[$col])) {
            $v = normalize_numeric_like_value($v);
        }
        if ($v === '') $v = null;
        $values[] = $v;
    }
}

// DEBUG: dump input and computed update fields to a debug log to diagnose missing keys
@file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " INPUT: " . json_encode(array_keys($input)) . "\n", FILE_APPEND);
@file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " UPDATE_FIELDS: " . json_encode($updateFields) . "\n", FILE_APPEND);
@file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " VALUES: " . json_encode($values) . "\n", FILE_APPEND);

if (empty($updateFields)) {
    http_response_code(400);
    @ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'No updatable fields provided']);
    exit();
}

$setParts = array_map(function($c){ return "`" . $c . "` = ?"; }, $updateFields);
$sql = 'UPDATE bids SET ' . implode(', ', $setParts) . ' WHERE bid_id = ? LIMIT 1';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    @ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
    exit();
}

// Bind params (treat all update fields as strings, bid_id as int)
$types = str_repeat('s', count($values)) . 'i';
$params = array_merge($values, [$bidId]);

$bindParams = array_merge([$types], $params);
$refs = [];
foreach ($bindParams as $k => $v) { $refs[$k] = &$bindParams[$k]; }

call_user_func_array([$stmt, 'bind_param'], $refs);

try {
    $ok = $stmt->execute();
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    @ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $e->getMessage(), 'code' => $e->getCode()]);
    $stmt->close();
    exit();
}

if ($ok === false) {
    http_response_code(500);
    @ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
    $stmt->close();
    exit();
}

$stmt->close();

// Return the updated row
$rstmt = $conn->prepare('SELECT * FROM bids WHERE bid_id = ? LIMIT 1');
if ($rstmt) {
    $rstmt->bind_param('i', $bidId);
    $rstmt->execute();
    $res = $rstmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    @file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " RETURNED_ROW: " . json_encode($row) . "\n", FILE_APPEND);
    $rstmt->close();

    // If status transitioned to 'won', send notification to all users
    try {
        $prevStatus = isset($currentRow['status']) ? strtolower(preg_replace('/[^a-z0-9]/', '', $currentRow['status'])) : '';
        $newStatus = isset($row['status']) ? strtolower(preg_replace('/[^a-z0-9]/', '', $row['status'])) : '';
        if ($prevStatus !== 'won' && $newStatus === 'won') {
            // create a project checklist entry mirroring this bid
            $createdProject = null;
            try {
                $pname = isset($row['project_name']) ? $row['project_name'] : '';
                $pcity = isset($row['project_city']) ? $row['project_city'] : '';
                $pcounty = isset($row['project_county']) ? $row['project_county'] : '';
                $pstate = isset($row['project_state']) ? $row['project_state'] : '';
                // attempt to use a coordinates field if present on bids
                $pcoords = '';
                if (isset($row['coordinates'])) $pcoords = $row['coordinates'];
                elseif (isset($row['project_coordinates'])) $pcoords = $row['project_coordinates'];

                $clientName = '';
                if (!empty($row['client_winner'])) {
                    $cw = $row['client_winner'];
                    if (is_numeric($cw)) {
                        $gq = $conn->prepare('SELECT COALESCE(general_contractor_name, general_contractor) AS name FROM general_contractor WHERE id = ? LIMIT 1');
                        if ($gq) { $gq->bind_param('i', $cw); $gq->execute(); $gr = $gq->get_result(); $grow = $gr ? $gr->fetch_assoc() : null; if ($grow) $clientName = $grow['name']; $gq->close(); }
                    } else {
                        $clientName = $cw;
                    }
                }

                // Insert into Projects table with available columns
                $cols = ['Project_Name','City','County','State','Coordinates','Client'];
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $types = 'sssss'; // five strings (we'll add client separately)
                $stmtCols = '`' . implode('`,`', $cols) . '`';
                $ins = $conn->prepare('INSERT INTO `Projects` (' . $stmtCols . ') VALUES (' . $placeholders . ')');
                if ($ins) {
                    $ins->bind_param('ssssss', $pname, $pcity, $pcounty, $pstate, $pcoords, $clientName);
                    if ($ins->execute()) {
                        $createdProject = ['project_id' => $ins->insert_id, 'project_name' => $pname];
                    }
                    $ins->close();
                }
            } catch (Throwable $e) {
                @file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " CREATE_PROJECT_ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            }

            // load mail helper
            require_once __DIR__ . '/../auth/mailjet_helper.php';
            // fetch all users
            $usersRes = $conn->query("SELECT email, name FROM users WHERE email IS NOT NULL AND email != ''");
            $emails = [];
            if ($usersRes) {
                while ($u = $usersRes->fetch_assoc()) {
                    if (!empty($u['email'])) $emails[] = $u;
                }
            }

            // build message
            $projectName = isset($row['project_name']) ? $row['project_name'] : '';
            $projAddrParts = [];
            if (!empty($row['project_address'])) $projAddrParts[] = $row['project_address'];
            if (!empty($row['project_city'])) $projAddrParts[] = $row['project_city'];
            if (!empty($row['project_county'])) $projAddrParts[] = $row['project_county'];
            if (!empty($row['project_state'])) $projAddrParts[] = $row['project_state'];
            $projectAddress = implode(', ', $projAddrParts);
            $gc = '';
            if (!empty($row['client_winner'])) {
                // try to resolve client_winner id to name
                $cw = $row['client_winner'];
                if (is_numeric($cw)) {
                    $gq = $conn->prepare('SELECT COALESCE(general_contractor_name, general_contractor) AS name FROM general_contractor WHERE id = ? LIMIT 1');
                    if ($gq) { $gq->bind_param('i', $cw); $gq->execute(); $gr = $gq->get_result(); $grow = $gr ? $gr->fetch_assoc() : null; if ($grow) $gc = $grow['name']; $gq->close(); }
                } else {
                    $gc = $row['client_winner'];
                }
            }

                $subject = 'Project Won — ' . ($projectName ? $projectName : 'Project');
                $text = "Good morning " . (isset($u['name']) ? $u['name'] : 'user') . ",\n\nWe have won " . $projectName . "\n\nProject: " . $projectName . "\nProject Address: " . $projectAddress . "\nGeneral Contractor: " . $gc . "\n\nThis project was created in Project Checklist.\n";
                $html = "<div style='font-family: Arial, sans-serif; max-width:600px; color:#0f172a;'>" .
                    "<div style='background:#0b76ef;color:#fff;padding:12px;border-radius:6px;'><h2 style=\"margin:0;font-size:18px;\">Project Won Notification</h2></div>" .
                    "<div style='padding:16px;background:#fff;border:1px solid #eef2f6;border-top:0;border-radius:0 0 6px 6px;'>" .
                    "<div style=\"font-size:15px;margin-bottom:12px;\">Good morning " . htmlspecialchars(isset($u['name']) ? $u['name'] : 'user') . ",</div>" .
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

                if (function_exists('sendMail')) {
                    foreach ($emails as $u) {
                        $to = $u['email'];
                        // check user's preferences: only send win notifications to users who enabled it (default: enabled)
                        try {
                            $prefStmt = $conn->prepare('SELECT preferred_days FROM bids_email WHERE email = ? LIMIT 1');
                            $prefStmt->bind_param('s', $to);
                            $prefStmt->execute();
                            $prefRes = $prefStmt->get_result();
                            $prefRow = $prefRes ? $prefRes->fetch_assoc() : null;
                            $prefStmt->close();
                            $sendToUser = true;
                            if ($prefRow && isset($prefRow['preferred_days'])) {
                                $pd = $prefRow['preferred_days'];
                                $decoded = json_decode($pd, true);
                                if (is_array($decoded)) {
                                    if (isset($decoded['win_opted'])) {
                                        $sendToUser = intval($decoded['win_opted']) ? true : false;
                                    } else {
                                        // legacy array -> assume enabled
                                        $sendToUser = true;
                                    }
                                } else {
                                    $sendToUser = true; // fallback
                                }
                            } else {
                                // no pref row -> default enabled
                                $sendToUser = true;
                            }
                        } catch (Throwable $e) {
                            $sendToUser = true;
                        }

                        if ($sendToUser) {
                            // append a short note how to turn off these notifications
                            // already includes note in HTML/text; ensure recipient name in plaintext
                            $personalText = $text; // uses $u when available below
                            $resMail = sendMail($to, $subject, $personalText, $html);
                            @file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " MAIL_SEND: " . json_encode(['to'=>$to,'res'=>$resMail]) . "\n", FILE_APPEND);
                        } else {
                            @file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " SKIP_MAIL_USER_OPT_OUT: " . $to . "\n", FILE_APPEND);
                        }
                    }
                }
            
            // attach created project info to response (if any)
            if (!empty($createdProject)) {
                $row['created_project'] = $createdProject;
            }
        }
    } catch (Throwable $e) {
        @file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " MAIL_ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    @ob_end_clean();
    echo json_encode(['success' => true, 'bid' => $row, 'created_project' => isset($row['created_project']) ? $row['created_project'] : null]);
    exit();
}

@ob_end_clean();
echo json_encode(['success' => true]);
exit();
