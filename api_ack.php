<?php
// API endpoint for acknowledging job completion
// ESP32 device calls this after executing a wake command

require __DIR__ . '/config.php';

// Validate API key
$key = $_GET['key'] ?? '';
require_key($key);

// Get parameters from request
$device = $_GET['device_id'] ?? '';
$id     = intval($_GET['id'] ?? 0);
$status = $_GET['status'] ?? '';

// Validate required parameters
if (!$device || !$id || !in_array($status, ['done','failed'], true)) {
  json_response(['ok'=>false,'err'=>'bad params'], 400);
}

// Load current job for this device
$job = load_job($device);
if (!$job || (int)$job['id'] !== $id) {
  // Job doesn't exist or ID doesn't match - this is OK
  json_response(['ok'=>true, 'note'=>'no matching job']);
}

// Update job status and timestamp
$job['status']     = $status;
$job['updated_at'] = date('c');

if ($status === 'done') {
  // Job completed successfully - remove from queue
  save_job($device, null); 
} else {
  // Job failed - keep in queue with updated status
  save_job($device, $job); 
}

json_response(['ok'=>true]);
