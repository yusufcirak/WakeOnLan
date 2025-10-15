<?php
// Configuration file for Wake-on-LAN API
// Set your API key and default device ID here
const API_KEY   = 'Your_Api_Key'; 
const DEVICE_ID = 'esp32-lanbox';
const QUEUE_DIR = __DIR__ . '/queue'; 

// Create queue directory if it doesn't exist
if (!is_dir(QUEUE_DIR)) {
  mkdir(QUEUE_DIR, 0755, true);
}

// Send JSON response with proper headers and HTTP status code
function json_response($data, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  exit;
}

// Validate API key and return 401 if invalid
function require_key($key) {
  if ($key !== API_KEY) json_response(['ok'=>false,'err'=>'unauthorized'], 401);
}

// Generate safe file path for device queue file
// Sanitizes device name to prevent directory traversal
function file_path_for($device) {
  return QUEUE_DIR . '/' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $device) . '.json';
}


// Load job data from device queue file with file locking
// Returns null if file doesn't exist or contains invalid data
function load_job($device) {
  $path = file_path_for($device);
  if (!file_exists($path)) return null;
  $fp = fopen($path, 'r');
  if (!$fp) return null;
  flock($fp, LOCK_SH); // Shared lock for reading
  $txt = stream_get_contents($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  if ($txt === false || $txt === '') return null;
  $arr = json_decode($txt, true);
  return is_array($arr) ? $arr : null;
}


// Save job data to device queue file with atomic write
// If $arr is null, deletes the file (clears the queue)
// Uses temporary file + rename for atomic operation
function save_job($device, $arr) {
  $path = file_path_for($device);
  if ($arr === null) {
    @unlink($path); // Delete queue file
    return true;
  }
  $tmp = $path . '.tmp';
  $fp = fopen($tmp, 'w');
  if (!$fp) return false;
  flock($fp, LOCK_EX); // Exclusive lock for writing
  fwrite($fp, json_encode($arr, JSON_UNESCAPED_SLASHES));
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  return rename($tmp, $path); // Atomic rename
}
