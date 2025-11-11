<?php
require_once __DIR__ . '/config/config.php';

echo "Testing geocoding API...\n\n";

// Get suppliers missing coordinates
$stmt = $conn->prepare("
    SELECT id, name, address, city, state 
    FROM suppliers 
    WHERE (latitude IS NULL OR longitude IS NULL)
    AND address IS NOT NULL 
    AND city IS NOT NULL
    LIMIT 3
");

$stmt->execute();
$result = $stmt->get_result();
$suppliers = [];

while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}
$stmt->close();

if (empty($suppliers)) {
    echo "✓ All suppliers have coordinates!\n";
    exit();
}

echo "Found " . count($suppliers) . " suppliers to geocode:\n";

foreach ($suppliers as $supplier) {
    echo "\n  → {$supplier['name']} ({$supplier['city']}, {$supplier['state']})\n";
    
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
    echo "    Full Address: $fullAddress\n";
    
    // Geocode using Nominatim - try full address first
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $fullAddress,
        'format' => 'json',
        'limit' => 1,
        'countrycodes' => 'us,ca'
    ]);
    
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
        echo "    Full address failed, trying city/state...\n";
        sleep(1);
        
        $cityStateCountry = $supplier['city'] . ', ' . $supplier['state'] . ', ' . ($isCanada ? 'Canada' : 'USA');
        echo "    Fallback: $cityStateCountry\n";
        
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $cityStateCountry,
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'us,ca'
        ]);
        
        $response = @file_get_contents($url, false, $context);
        $data = $response ? json_decode($response, true) : null;
    }
    
    if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
        $lat = floatval($data[0]['lat']);
        $lng = floatval($data[0]['lon']);
        
        echo "    Found: $lat, $lng\n";
        
        // Update database
        $updateStmt = $conn->prepare("UPDATE suppliers SET latitude = ?, longitude = ? WHERE id = ?");
        $updateStmt->bind_param('ddi', $lat, $lng, $supplier['id']);
        
        if ($updateStmt->execute()) {
            echo "    ✓ Updated in database\n";
        } else {
            echo "    ✗ Failed to update database\n";
        }
        
        $updateStmt->close();
    } else {
        echo "    ✗ No coordinates found\n";
    }
    
    // Rate limiting: wait 1 second between requests
    if ($supplier !== end($suppliers)) {
        echo "    Waiting 1 second...\n";
        sleep(1);
    }
}

echo "\n✓ Test complete!\n";
