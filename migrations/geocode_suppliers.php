<?php
/**
 * Geocode Script - Batch geocode all suppliers missing coordinates
 * Run this script to geocode existing suppliers in the database
 * 
 * Usage: php geocode_suppliers.php
 */

require_once __DIR__ . '/../config/config.php';

echo "Starting geocoding process...\n\n";

// Fetch all suppliers without coordinates
$result = $conn->query("SELECT id, name, address, city, state FROM suppliers WHERE latitude IS NULL OR longitude IS NULL");

if (!$result || $result->num_rows === 0) {
    echo "No suppliers need geocoding.\n";
    exit;
}

$suppliers = [];
while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}

echo "Found " . count($suppliers) . " suppliers to geocode.\n\n";

$successCount = 0;
$failCount = 0;

foreach ($suppliers as $index => $supplier) {
    $num = $index + 1;
    echo "[$num/" . count($suppliers) . "] Geocoding: " . $supplier['name'] . " - ";
    
    // Build address
    $addressParts = [];
    if ($supplier['address']) $addressParts[] = $supplier['address'];
    if ($supplier['city']) $addressParts[] = $supplier['city'];
    if ($supplier['state']) $addressParts[] = $supplier['state'];
    
    $fullAddress = implode(', ', $addressParts);
    
    if (empty($fullAddress)) {
        echo "SKIP (no address)\n";
        $failCount++;
        continue;
    }
    
    // Geocode using Nominatim
    $url = 'https://nominatim.openstreetmap.org/search?format=json&q=' . urlencode($fullAddress) . '&countrycodes=us,ca&limit=1';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PortalSite/1.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        echo "FAIL (HTTP $httpCode)\n";
        $failCount++;
        sleep(1); // Rate limit
        continue;
    }
    
    $data = json_decode($response, true);
    
    if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
        $lat = floatval($data[0]['lat']);
        $lng = floatval($data[0]['lon']);
        
        // Update database
        $stmt = $conn->prepare("UPDATE suppliers SET latitude = ?, longitude = ? WHERE id = ?");
        $stmt->bind_param('ddi', $lat, $lng, $supplier['id']);
        
        if ($stmt->execute()) {
            echo "SUCCESS ($lat, $lng)\n";
            $successCount++;
        } else {
            echo "FAIL (database error)\n";
            $failCount++;
        }
        
        $stmt->close();
    } else {
        echo "FAIL (not found)\n";
        $failCount++;
    }
    
    // Rate limit: 1 request per second
    sleep(1);
}

echo "\n===================\n";
echo "Geocoding complete!\n";
echo "Success: $successCount\n";
echo "Failed: $failCount\n";
echo "===================\n";

$conn->close();
?>
