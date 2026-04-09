<?php
echo "Testing Tasks API Endpoints\n";
echo "============================\n\n";

// Test 1: Get all tasks
echo "1. Testing GET /tasks.php\n";
$ch = curl_init('http://localhost:8000/tasks.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if ($data && $data['success']) {
    echo "✓ Success! Found {$data['total']} tasks\n";
    foreach($data['tasks'] as $task) {
        echo "  - {$task['title']} ({$task['status']}, {$task['priority']})\n";
    }
} else {
    echo "✗ Error: " . ($data['message'] ?? 'Unknown error') . "\n";
}

echo "\n2. Testing GET /tasks.php?status=pending\n";
$ch = curl_init('http://localhost:8000/tasks.php?status=pending');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if ($data && $data['success']) {
    echo "✓ Success! Found {$data['total']} pending tasks\n";
} else {
    echo "✗ Error: " . ($data['message'] ?? 'Unknown error') . "\n";
}

echo "\n3. Testing GET /task_detail.php?id=1\n";
$ch = curl_init('http://localhost:8000/task_detail.php?id=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if ($data && $data['success']) {
    echo "✓ Success! Task: {$data['task']['title']}\n";
    echo "  Status: {$data['task']['status']}\n";
    echo "  Priority: {$data['task']['priority']}\n";
    echo "  Comments: " . count($data['task']['comments']) . "\n";
    echo "  Attachments: " . count($data['task']['attachments']) . "\n";
} else {
    echo "✗ Error: " . ($data['message'] ?? 'Unknown error') . "\n";
}

echo "\nAll API tests completed!\n";
?>