<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

// Get all suppliers missing coordinates
$stmt = $conn->prepare("
    SELECT id, name, address, city, state 
    FROM suppliers 
    WHERE (latitude IS NULL OR longitude IS NULL)
    AND address IS NOT NULL 
    AND city IS NOT NULL
    LIMIT 5
");

$stmt->execute();
$result = $stmt->get_result();
$suppliers = [];

while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}
$stmt->close();

if (empty($suppliers)) {
    echo json_encode([
        'success' => true,
        'message' => 'All suppliers have coordinates',
        'geocoded' => 0
    ]);
    exit();
}

$geocoded = 0;
$failed = [];

foreach ($suppliers as $supplier) {
    // Build address string
    $addressParts = [];
    if (!empty($supplier['address'])) $addressParts[] = $supplier['address'];
    if (!empty($supplier['city'])) $addressParts[] = $supplier['city'];
    if (!empty($supplier['state'])) $addressParts[] = $supplier['state'];
    
    // Determine country based on state/province
    $canadianProvinces = ['ON', 'Ontario', 'QC', 'Quebec', 'BC', 'British Columbia', 'AB', 'Alberta', 
                          'MB', 'Manitoba', 'SK', 'Saskatchewan', 'NS', 'Nova Scotia', 'NB', 'New Brunswick',
                          'PE', 'Prince Edward Island', 'NL', 'Newfoundland', 'YT', 'Yukon', 'NT', 'Northwest Territories', 'NU', 'Nunavut'];
    $isCanada = false;
    foreach ($canadianProvinces as $province) {
        if (stripos($supplier['state'], $province) !== false) {
            $isCanada = true;
            break;
        }
    }
    
    // Add country to address
    $addressParts[] = $isCanada ? 'Canada' : 'USA';
    $fullAddress = implode(', ', $addressParts);
    
    // Try geocoding with full address first
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $fullAddress,
        'format' => 'json',
        'limit' => 1,
        'countrycodes' => 'us,ca'
    ]);
    
    // Add user agent as required by Nominatim
    $options = [
        'http' => [
            'header' => "User-Agent: PortalSite/1.0\r\n"
        ]
    ];
    $context = stream_context_create($options);
    
    $response = @file_get_contents($url, false, $context);
    $data = $response ? json_decode($response, true) : null;
    
    // If full address fails, try just city, state, country
    if (empty($data) || !isset($data[0]['lat'])) {
        $cityStateCountry = $supplier['city'] . ', ' . $supplier['state'] . ', ' . ($isCanada ? 'Canada' : 'USA');
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $cityStateCountry,
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'us,ca'
        ]);
        
        sleep(1); // Rate limit between attempts
        $response = @file_get_contents($url, false, $context);
        $data = $response ? json_decode($response, true) : null;
    }
    
    if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
        $lat = floatval($data[0]['lat']);
        $lng = floatval($data[0]['lon']);
        
        // Update database
        $updateStmt = $conn->prepare("UPDATE suppliers SET latitude = ?, longitude = ? WHERE id = ?");
        $updateStmt->bind_param('ddi', $lat, $lng, $supplier['id']);
        
        if ($updateStmt->execute()) {
            $geocoded++;
        } else {
            $failed[] = $supplier['name'];
        }
        
        $updateStmt->close();
    } else {
        $failed[] = $supplier['name'];
    }
    
    // Rate limiting: wait 1 second between requests (Nominatim requirement)
    if ($supplier !== end($suppliers)) {
        sleep(1);
    }
}

echo json_encode([
    'success' => true,
    'geocoded' => $geocoded,
    'failed' => $failed,
    'message' => "Geocoded $geocoded supplier(s)"
]);
