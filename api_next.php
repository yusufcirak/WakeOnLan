<?php
// API endpoint for ESP32 to fetch next job from queue
// Device polls this endpoint to get pending wake commands

require __DIR__ . '/config.php';

// Validate API key
$key = $_GET['key'] ?? '';
require_key($key);

// Get device ID from request
$device = $_GET['device_id'] ?? '';
if (!$device) json_response(['ok'=>false,'err'=>'missing device_id'], 400);

// Load pending job for this device
$job = load_job($device);
if (!$job) {
  // No pending jobs
  json_response(['ok'=>true,'job'=>null]); 
}

// Mark job as taken and update timestamp
$job['status']    = 'taken';
$job['updated_at']= date('c');
save_job($device, $job);

// Return job details to ESP32
json_response(['ok'=>true,'job'=>[
  'id'      => $job['id'],
  'cmd'     => $job['cmd'],
  'payload' => $job['payload'],
]]);
