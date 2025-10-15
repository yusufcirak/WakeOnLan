<?php
// Logging utilities for the Wake-on-LAN API

// Mask sensitive strings for logging (API keys, tokens)
// Shows first 3 and last 2 characters, masks the middle
function mask($s) {
  if (!$s) return '';
  if (strlen($s) <= 6) return $s;
  return substr($s,0,3) . '***' . substr($s,-2);
}

// Mask MAC address for logging (shows first and last octet)
// Example: AA:BB:CC:DD:EE:FF -> AA:**:**:**:**:FF
function maskMac($mac) {
  if (!$mac) return '';
  // Handle both : and - separators
  $separator = (strpos($mac, ':') !== false) ? ':' : '-';
  $parts = explode($separator, $mac);
  if (count($parts) !== 6) return $mac; // Invalid MAC, return as-is
  
  return $parts[0] . $separator . '**' . $separator . '**' . $separator . '**' . $separator . '**' . $separator . $parts[5];
}

// Mask IP address for logging (shows first and last octet)
// Example: 192.168.1.100 -> 192.***.***100
function maskIp($ip) {
  if (!$ip) return '';
  $parts = explode('.', $ip);
  if (count($parts) !== 4) return $ip; // Invalid IP, return as-is
  
  return $parts[0] . '.***.***.***' . substr($parts[3], -2);
}

// Check if IP is allowed for wake commands
// Currently allows all IPs - modify this function to restrict access if needed
function isAllowedIp($ip) {
  // Allow all IPs for now
  return true;
  
  // Uncomment below to restrict to specific IPs:
  /*
  $allowedIps = [
    '141.196.47.64',
    '188.3.233.166'
  ];
  
  return in_array($ip, $allowedIps, true);
  */
}

// Generate browser fingerprint for additional security
function getBrowserFingerprint() {
  return md5(
    ($_SERVER['HTTP_USER_AGENT'] ?? '') .
    ($_SERVER['HTTP_ACCEPT'] ?? '') .
    ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') .
    ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '') .
    ($_SERVER['HTTP_CONNECTION'] ?? '')
  );
}

// Rate limiting: Max 20 requests per minute per IP
// ESP32 requests are exempt from rate limiting
function checkRateLimit($ip, $maxRequests = 20) {
  // Skip rate limiting for ESP32 HTTPClient
  if (isESP32Request()) {
    return true; // Always allow ESP32 requests
  }
  
  $dir = __DIR__ . '/logs';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  
  $file = $dir . '/rate_limit.json';
  $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
  
  $now = time();
  $minute = floor($now / 60);
  
  // Initialize counter for this IP and minute
  if (!isset($data[$ip][$minute])) {
    $data[$ip][$minute] = 0;
  }
  
  $data[$ip][$minute]++;
  
  // Clean old entries (older than 2 hours)
  foreach ($data as $checkIp => $minutes) {
    foreach ($minutes as $min => $count) {
      if ($min < $minute - 120) {
        unset($data[$checkIp][$min]);
      }
    }
    // Remove empty IP entries
    if (empty($data[$checkIp])) {
      unset($data[$checkIp]);
    }
  }
  
  // Save updated data
  file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES));
  
  // Check if rate limit exceeded
  $isAllowed = $data[$ip][$minute] <= $maxRequests;
  
  // Log rate limit violation
  if (!$isAllowed) {
    log_security_incident('rate_limit', 'exceeded', [
      'requests_count' => $data[$ip][$minute],
      'max_allowed' => $maxRequests,
      'minute' => $minute
    ]);
  }
  
  return $isAllowed;
}
// Log security incidents (failed/unauthorized requests) to separate file
function log_security_incident($tag, $status, $extra = []) {
  $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  
  $incident = [
    'ts'         => date('c'),                          // ISO 8601 timestamp
    'real_ip'    => $clientIp,                          // Real IP (not masked for security analysis)
    'masked_ip'  => maskIp($clientIp),                  // Masked IP for privacy
    'fingerprint'=> getBrowserFingerprint(),            // Browser fingerprint
    'ua'         => $_SERVER['HTTP_USER_AGENT'] ?? '',  // Full user agent
    'referer'    => $_SERVER['HTTP_REFERER'] ?? '',     // Referer header
    'method'     => $_SERVER['REQUEST_METHOD'] ?? 'GET',// HTTP method
    'uri'        => $_SERVER['REQUEST_URI'] ?? '',      // Full request URI
    'tag'        => $tag,                               // API endpoint
    'status'     => $status,                            // Failure reason
    'headers'    => [
      'host'           => $_SERVER['HTTP_HOST'] ?? '',
      'accept'         => $_SERVER['HTTP_ACCEPT'] ?? '',
      'accept_language'=> $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
      'accept_encoding'=> $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
      'connection'     => $_SERVER['HTTP_CONNECTION'] ?? '',
      'x_forwarded_for'=> $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
      'x_real_ip'      => $_SERVER['HTTP_X_REAL_IP'] ?? '',
    ],
    'q'          => $_GET,                              // Query parameters
    'x'          => $extra,                             // Extra data
  ];
  
  // Sanitize sensitive data in query parameters for security log
  if (isset($incident['q']['key']))   $incident['q']['key']   = mask($incident['q']['key']);
  if (isset($incident['q']['mac']))   $incident['q']['mac']   = maskMac($incident['q']['mac']);
  if (isset($incident['q']['token'])) $incident['q']['token'] = mask($incident['q']['token']);
  
  // Create logs directory if it doesn't exist
  $dir = __DIR__ . '/logs';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $file = $dir . '/security.log';
  
  // Append security incident to file with file locking
  $fp = @fopen($file, 'a');
  if ($fp) {
    @flock($fp, LOCK_EX);  // Exclusive lock for writing
    fwrite($fp, json_encode($incident, JSON_UNESCAPED_SLASHES) . PHP_EOL);
    @flock($fp, LOCK_UN);
    fclose($fp);
  }
}

// Check if request is from ESP32 HTTPClient
function isESP32Request() {
  $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
  return (strpos($userAgent, 'ESP32HTTPClient') !== false);
}

// Log API request with sanitized parameters
// $tag: event type (enqueue, next, ack)
// $extra: additional data to log
// $status: success, failed, unauthorized_ip, unauthorized_key, etc.
function log_event($tag, $extra = [], $status = 'success') {
  // Skip logging successful ESP32 requests to reduce log noise
  if (isESP32Request() && $status === 'success') {
    return; // Don't log successful ESP32 polling
  }
  
  $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  
  $line = [
    'ts'     => date('c'),                            // ISO 8601 timestamp
    'ip'     => maskIp($clientIp),                    // Masked client IP
    'ua'     => $_SERVER['HTTP_USER_AGENT'] ?? '',    // User agent
    'tag'    => $tag,                                 // Event type
    'status' => $status,                              // Request status
    'q'      => $_GET,                                // Query parameters
    'x'      => $extra,                               // Extra data
  ];
  
  // Sanitize sensitive data in query parameters
  if (isset($line['q']['key']))   $line['q']['key']   = mask($line['q']['key']);
  if (isset($line['q']['mac']))   $line['q']['mac']   = maskMac($line['q']['mac']); // Mask MAC address
  if (isset($line['q']['token'])) $line['q']['token'] = mask($line['q']['token']);

  // Create logs directory if it doesn't exist
  $dir = __DIR__ . '/logs';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $file = $dir . '/requests.log';

  // Append log entry to file with file locking
  $fp = @fopen($file, 'a');
  if ($fp) {
    @flock($fp, LOCK_EX);  // Exclusive lock for writing
    fwrite($fp, json_encode($line, JSON_UNESCAPED_SLASHES) . PHP_EOL);
    @flock($fp, LOCK_UN);
    fclose($fp);
  }
  
  // If this is a security incident, also log to security file
  $securityStatuses = [
    'unauthorized_ip', 'unauthorized_key', 'missing_params', 
    'missing_device_id', 'invalid_mac', 'bad_params', 'fs_write_failed',
    'rate_limit_exceeded'
  ];
  
  if (in_array($status, $securityStatuses)) {
    log_security_incident($tag, $status, $extra);
  }
}
