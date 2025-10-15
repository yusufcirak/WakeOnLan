<?php
// API endpoint for acknowledging job completion
// ESP32 device calls this after executing a wake command

require __DIR__ . '/config.php';
require __DIR__ . '/log.php';

// Check rate limiting
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIp, 20)) { // Max 20 requests per minute for acknowledgments
  log_event('ack', [], 'rate_limit_exceeded');
  json_response(['ok'=>false,'err'=>'rate limit exceeded'], 429);
}

// Validate API key
$key = $_GET['key'] ?? '';
if ($key !== API_KEY) {
  log_event('ack', [], 'unauthorized_key');
  json_response(['ok'=>false,'err'=>'unauthorized'], 401);
}

// Get parameters from request
$device = $_GET['device_id'] ?? '';
$id     = intval($_GET['id'] ?? 0);
$status = $_GET['status'] ?? '';

// Validate required parameters
if (!$device || !$id || !in_array($status, ['done','failed'], true)) {
  log_event('ack', ['device' => $device, 'id' => $id, 'status' => $status], 'bad_params');
  json_response(['ok'=>false,'err'=>'bad params'], 400);
}

// Load current job for this device
$job = load_job($device);
if (!$job || (int)$job['id'] !== $id) {
  // Job doesn't exist or ID doesn't match - this is OK
  log_event('ack', ['device' => $device, 'id' => $id], 'no_matching_job');
  json_response(['ok'=>true, 'note'=>'no matching job']);
}

// Update job status and timestamp
$job['status']     = $status;
$job['updated_at'] = date('c');

if ($status === 'done') {
  // Job completed successfully - remove from queue
  save_job($device, null);
  log_event('ack', ['device' => $device, 'job_id' => $id], 'job_completed');
} else {
  // Job failed - keep in queue with updated status
  save_job($device, $job);
  log_event('ack', ['device' => $device, 'job_id' => $id], 'job_failed');
}

json_response(['ok'=>true]);
