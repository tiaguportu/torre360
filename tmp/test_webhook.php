<?php
$url = 'http://localhost/api/webhooks/assinafy';
$data = ['event' => 'test', 'object' => ['id' => 'test_id']];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true
    ]
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "Response: " . $result . "\n";
foreach ($http_response_header as $header) {
    echo "Header: " . $header . "\n";
}
