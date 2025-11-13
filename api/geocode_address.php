<?php
header('Content-Type: application/json; charset=utf-8');

// Simple server-side geocoding proxy using Nominatim so the browser doesn't have to call it directly.
// Accepts POST or GET param 'address' and returns JSON: { success: true, lat: ..., lon: ... }

// Get raw address
$address = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
} else {
    $address = isset($_GET['address']) ? trim($_GET['address']) : '';
}

if (!$address) {
    echo json_encode(['success' => false, 'message' => 'Missing address']);
    exit;
}

// Build Nominatim URL
$params = http_build_query([
    'format' => 'json',
    'q' => $address,
    'countrycodes' => 'us,ca',
    'limit' => 1
]);
$url = 'https://nominatim.openstreetmap.org/search?' . $params;


$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Increase timeout to allow for slower network responses
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
// Set a proper User-Agent as required by Nominatim's usage policy
curl_setopt($ch, CURLOPT_USERAGENT, 'PortalSite/1.0 (+https://yourdomain.example)');

$resp = curl_exec($ch);
$errno = curl_errno($ch);
$err = curl_error($ch);
curl_close($ch);

// If cURL failed or returned empty, attempt a fallback using file_get_contents with a stream context.
if ($errno || !$resp) {
    // Log for debugging when available (not shown to user beyond JSON)
    $curlError = $err ?: 'empty response';

    // Try file_get_contents fallback (uses allow_url_fopen)
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: PortalSite/1.0\r\n",
            'timeout' => 20
        ]
    ];
    $context = stream_context_create($opts);
    $fallbackResp = @file_get_contents($url, false, $context);
    if ($fallbackResp) {
        $resp = $fallbackResp;
    } else {
        echo json_encode(['success' => false, 'message' => 'Geocoding request failed', 'error' => 'cURL error: '.$curlError]);
        exit;
    }
}

$data = json_decode($resp, true);
if (!$data || !is_array($data) || count($data) === 0) {
    echo json_encode(['success' => false, 'message' => 'No results']);
    exit;
}

$lat = isset($data[0]['lat']) ? $data[0]['lat'] : null;
$lon = isset($data[0]['lon']) ? $data[0]['lon'] : null;

if ($lat === null || $lon === null) {
    echo json_encode(['success' => false, 'message' => 'Malformed response']);
    exit;
}

echo json_encode(['success' => true, 'lat' => $lat, 'lon' => $lon]);
exit;
