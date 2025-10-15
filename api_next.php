<?php
// API endpoint for ESP32 to fetch next job from queue
// Device polls this endpoint to get pending wake commands

require __DIR__ . '/config.php';
require __DIR__ . '/log.php';

// Skip rate limiting for ESP32, apply to others
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!isESP32Request() && !checkRateLimit($clientIp, 30)) {
  log_event('next', [], 'rate_limit_exceeded');
  json_response(['ok'=>false,'err'=>'rate limit exceeded'], 429);
}

// Validate API key
$key = $_GET['key'] ?? '';
if ($key !== API_KEY) {
  log_event('next', [], 'unauthorized_key');
  json_response(['ok'=>false,'err'=>'unauthorized'], 401);
}

// Get device ID from request
$device = $_GET['device_id'] ?? '';
if (!$device) {
  log_event('next', [], 'missing_device_id');
  json_response(['ok'=>false,'err'=>'missing device_id'], 400);
}

// Load pending job for this device
$job = load_job($device);
if (!$job) {
  // No pending jobs
  log_event('next', ['device' => $device], 'no_jobs');
  json_response(['ok'=>true,'job'=>null]); 
}

// Mark job as taken and update timestamp
$job['status']    = 'taken';
$job['updated_at']= date('c');
save_job($device, $job);

// Log successful job fetch and return job details to ESP32
log_event('next', ['device' => $device, 'job_id' => $job['id']], 'success');
json_response(['ok'=>true,'job'=>[
  'id'      => $job['id'],
  'cmd'     => $job['cmd'],
  'payload' => $job['payload'],
]]);
