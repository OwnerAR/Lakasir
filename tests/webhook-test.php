<?php

$webhookUrl = 'http://localhost:8000/api/webhook/whatsapp';
$apiKey = 'a43f92f0d6fcdab11467611aa0c061992a77f4047ff3954173d8ed4266de8e1f'; // The API key we created earlier

$testData = [
    'number' => '+919876543210',
    'name' => 'Test Customer',
    'message' => 'Hello, this is a test message',
    'message_type' => 'text'
];

// Initialize cURL
$ch = curl_init($webhookUrl);

// Set cURL options
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey
    ]
]);

// Send the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for errors
if (curl_errno($ch)) {
    echo 'Curl error: ' . curl_error($ch) . "\n";
} else {
    echo "HTTP Status Code: " . $httpCode . "\n";
    echo "Response:\n";
    echo json_encode(json_decode($response), JSON_PRETTY_PRINT) . "\n";
}

curl_close($ch); 