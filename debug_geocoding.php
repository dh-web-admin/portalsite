<?php
$addresses = [
    'PO Box 1000, 585 Water Street South, St Marys, Ontario, Canada',
    '400 Seahorse Drive, Waukegan, Illinois, USA',
    'St Marys, Ontario, Canada',
    'Waukegan, Illinois, USA'
];

foreach ($addresses as $address) {
    echo "\nTesting: $address\n";
    
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $address,
        'format' => 'json',
        'limit' => 1,
        'countrycodes' => 'us,ca'
    ]);
    
    echo "URL: $url\n";
    
    $options = [
        'http' => [
            'header' => "User-Agent: PortalSite/1.0\r\n"
        ]
    ];
    $context = stream_context_create($options);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response) {
        $data = json_decode($response, true);
        echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Failed to get response\n";
    }
    
    sleep(1);
}
