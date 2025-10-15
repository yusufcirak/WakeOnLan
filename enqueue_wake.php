<?php
// API endpoint to enqueue wake-on-LAN commands
// Web interface calls this to add jobs to the queue

require __DIR__ . '/config.php';
require __DIR__ . '/log.php';     

// Check rate limiting first (applies to all IPs)
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIp, 10)) { // Max 10 requests per minute for wake commands
  log_event('enqueue', [], 'rate_limit_exceeded');
  json_response(['ok'=>false,'err'=>'rate limit exceeded'], 429);
}

// Check if client IP is allowed for wake commands
if (!isAllowedIp($clientIp)) {
  log_event('enqueue', [], 'unauthorized_ip');
  json_response(['ok'=>false,'err'=>'unauthorized ip'], 403);
}

// Validate API key
$key  = $_GET['key'] ?? '';
if ($key !== API_KEY) {
  log_event('enqueue', [], 'unauthorized_key');
  json_response(['ok'=>false,'err'=>'unauthorized'], 401);
}

// Get parameters from request
$device = $_GET['device_id'] ?? '';
$mac    = $_GET['mac'] ?? '';
$uip    = $_GET['unicast_ip'] ?? ''; // Optional unicast IP

// Validate required parameters
if (!$device || !$mac) {
  log_event('enqueue', [], 'missing_params');
  json_response(['ok'=>false,'err'=>'missing params'], 400);
}

// Validate MAC address format (XX:XX:XX:XX:XX:XX or XX-XX-XX-XX-XX-XX)
if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac)) {
  log_event('enqueue', [], 'invalid_mac');
  json_response(['ok'=>false,'err'=>'bad mac'], 400);
}

// Create new job with unique ID and current timestamp
$job = [
  'id'         => (int)(microtime(true) * 1000), // Unique timestamp-based ID
  'status'     => 'queued',                     
  'cmd'        => 'wake',
  'payload'    => ['mac'=>$mac, 'unicast_ip'=>$uip],
  'created_at' => date('c')
];

// Save job to queue file
if (!save_job($device, $job)) {
  log_event('enqueue', ['job_id' => $job['id']], 'fs_write_failed');
  json_response(['ok'=>false,'err'=>'fs write failed'], 500);
}

// Log successful enqueue and return success response
log_event('enqueue', ['job_id' => $job['id'], 'device' => $device], 'success');
json_response(['ok'=>true, 'queued'=>true, 'id'=>$job['id']]);
