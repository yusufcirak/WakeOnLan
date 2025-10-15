<?php
// Logging utilities for the Wake-on-LAN API

// Mask sensitive strings for logging (API keys, tokens)
// Shows first 3 and last 2 characters, masks the middle
function mask($s) {
  if (!$s) return '';
  if (strlen($s) <= 6) return $s;
  return substr($s,0,3) . '***' . substr($s,-2);
}
// Log API request with sanitized parameters
// $tag: event type (enqueue, next, ack)
// $extra: additional data to log
function log_event($tag, $extra = []) {
  $line = [
    'ts'   => date('c'),                              // ISO 8601 timestamp
    'ip'   => $_SERVER['REMOTE_ADDR'] ?? 'unknown',   // Client IP
    'ua'   => $_SERVER['HTTP_USER_AGENT'] ?? '',      // User agent
    'tag'  => $tag,                                   // Event type
    'q'    => $_GET,                                  // Query parameters
    'x'    => $extra,                                 // Extra data
  ];
  
  // Sanitize sensitive data in query parameters
  if (isset($line['q']['key']))   $line['q']['key']   = mask($line['q']['key']);
  if (isset($line['q']['mac']))   $line['q']['mac']   = $line['q']['mac'];        // MAC is not sensitive
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
}
