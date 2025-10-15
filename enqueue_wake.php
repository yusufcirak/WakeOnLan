<?php
// API endpoint to enqueue wake-on-LAN commands
// Web interface calls this to add jobs to the queue

require __DIR__ . '/config.php';
require __DIR__ . '/log.php';     
log_event('enqueue');            // Log this request

// Validate API key
$key  = $_GET['key'] ?? '';
require_key($key);

// Get parameters from request
$device = $_GET['device_id'] ?? '';
$mac    = $_GET['mac'] ?? '';
$uip    = $_GET['unicast_ip'] ?? ''; // Optional unicast IP

// Validate required parameters
if (!$device || !$mac) {
  json_response(['ok'=>false,'err'=>'missing params'], 400);
}

// Validate MAC address format (XX:XX:XX:XX:XX:XX or XX-XX-XX-XX-XX-XX)
if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac)) {
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
  json_response(['ok'=>false,'err'=>'fs write failed'], 500);
}

// Return success response with job ID
json_response(['ok'=>true, 'queued'=>true, 'id'=>$job['id']]);
