# WakeOnLan
ESP32 Wake-on-LAN Remote System

A complete Wake-on-LAN (WoL) system consisting of a web interface and ESP32 device that can remotely wake up computers on your network.

## 🚀 Features

- **Web Interface**: Modern, responsive web UI for queuing wake commands
- **ESP32 Device**: Polls for commands and sends WoL magic packets
- **Queue System**: File-based job queue with atomic operations
- **Advanced Security**: Multi-layered protection with rate limiting, fingerprinting, and detailed logging
- **Smart Logging**: Dual log system with privacy masking and ESP32 optimization
- **Rate Limiting**: DDoS protection with ESP32 bypass for normal operation
- **Flexible**: Supports both broadcast and unicast wake packets
- **Real-time**: Live status updates and error handling

## 📁 Project Structure

```
WakeOnLan/
├── README.md                    # This file
├── index.html                   # Web interface
├── config.php                   # Configuration and shared functions
├── enqueue_wake.php            # API endpoint to queue wake commands
├── api_next.php                # API endpoint for ESP32 to fetch jobs
├── api_ack.php                 # API endpoint for job acknowledgment
├── log.php                     # Logging utilities
├── queue/                      # Job queue directory (auto-created)
├── logs/                       # Request logs directory (auto-created)
│   ├── requests.log           # All API requests (masked data)
│   ├── security.log           # Security incidents (detailed info)
│   └── rate_limit.json        # Rate limiting data
└── Esp32_Remote_Open_PC/
    └── Esp32_Remote_Open_PC.ino # ESP32 Arduino code
```

## 🛠️ Setup Instructions

### 1. Web Server Setup

1. **Upload PHP files** to your web server
2. **Configure API settings** in `config.php`:
   ```php
   const API_KEY   = 'your_secure_api_key_here';
   const DEVICE_ID = 'esp32-lanbox';  // or your preferred device ID
   ```
3. **Set permissions** for queue and logs directories (755 recommended)
4. **Access the web interface** at `http://your-server.com/index.html`

### 2. ESP32 Setup

1. **Install required libraries** in Arduino IDE:
   - WiFi (built-in)
   - WiFiClientSecure (built-in)
   - HTTPClient (built-in)
   - ArduinoJson (install via Library Manager)

2. **Configure the ESP32 code** in `Esp32_Remote_Open_PC.ino`:
   ```cpp
   static const char* WIFI_SSID = "Your_Network_SSID";
   static const char* WIFI_PASS = "Your_Network_Password";
   static const char* HOST_BASE = "https://your-server.com";
   static const char* API_KEY   = "your_secure_api_key_here";
   static const char* DEVICE_ID = "esp32-lanbox";
   ```

3. **Upload the code** to your ESP32 device
4. **Monitor Serial output** at 115200 baud for debugging

## 🌐 API Endpoints

### Web Interface → Server
- **POST** `enqueue_wake.php` - Queue a new wake command
  - Parameters: `key`, `device_id`, `mac`, `unicast_ip` (optional)

### ESP32 → Server
- **GET** `api_next.php` - Fetch next job from queue
  - Parameters: `key`, `device_id`
- **GET** `api_ack.php` - Acknowledge job completion
  - Parameters: `key`, `device_id`, `id`, `status` (done/failed)

## 📋 Usage

1. **Open the web interface** in your browser
2. **Enter your API key** (same as configured in config.php)
3. **Specify target device** MAC address (format: AA:BB:CC:DD:EE:FF)
4. **Optional**: Enter unicast IP for directed wake packets
5. **Click "Wake!"** to queue the command
6. **ESP32 device** will automatically pick up and execute the command

## 🔧 Configuration Options

### Web Server (`config.php`)
- `API_KEY`: Secure authentication key
- `DEVICE_ID`: Default ESP32 device identifier
- `QUEUE_DIR`: Directory for job queue files

### ESP32 (`Esp32_Remote_Open_PC.ino`)
- `WIFI_SSID/WIFI_PASS`: Network credentials
- `HOST_BASE`: Your web server URL
- `POLL_SECONDS`: How often to check for new jobs (default: 5s)
- `WOL_PORT`: UDP port for wake packets (default: 9)
- `USE_INSECURE_TLS`: Set to false for production with proper certificates

## 🔒 Advanced Security Features

### **Multi-Layer Protection**
- **API Key Authentication**: All endpoints require valid API key
- **Rate Limiting**: Prevents DDoS attacks (10 req/min for wake commands)
- **IP Whitelisting**: Optional IP restriction (currently disabled)
- **Browser Fingerprinting**: Tracks suspicious behavior patterns
- **ESP32 Bypass**: Smart filtering for legitimate device polling

### **Intelligent Logging System**
- **Dual Log Files**: Separate normal and security incident logs
- **Privacy Protection**: IP and MAC addresses are masked
- **Data Sanitization**: Sensitive information is automatically redacted
- **ESP32 Optimization**: Successful polling requests are filtered out
- **Detailed Forensics**: Full HTTP headers and browser fingerprints for security events

### **Data Protection**
- **MAC Masking**: `AA:BB:CC:DD:EE:FF` → `AA:**:**:**:**:FF`
- **IP Masking**: `192.168.1.100` → `192.***.***00`
- **API Key Masking**: `secret123key` → `sec***ey`
- **File Locking**: Atomic operations prevent race conditions

## 🐛 Troubleshooting

### Web Interface Issues
- Check browser console for JavaScript errors
- Verify API key matches server configuration
- Ensure server has write permissions for queue/logs directories

### ESP32 Issues
- Monitor Serial output at 115200 baud
- Verify WiFi credentials and network connectivity
- Check API key and server URL configuration
- Ensure target device supports Wake-on-LAN

### Network Issues
- Verify ESP32 and target device are on same network
- Check router settings for UDP broadcast packets
- Try unicast mode if broadcast doesn't work
- Ensure target device has WoL enabled in BIOS/UEFI

## 📊 Status Indicators & Monitoring

### Web Interface
- **Ready**: System ready for commands
- **Sending**: Command being transmitted
- **Success**: Command queued successfully
- **Error**: Various error states with descriptions
- **Rate Limited**: Too many requests (HTTP 429)

### ESP32 LED (if available)
- **Single blink**: Command failed
- **Double blink**: Command successful
- **Rapid blinking**: WiFi connecting

### Security Monitoring
- **Normal Logs**: `logs/requests.log` - All legitimate requests
- **Security Logs**: `logs/security.log` - Suspicious activities only
- **Rate Limiting**: Automatic protection against abuse
- **Real-time Alerts**: Immediate logging of security incidents

## 🔄 Job Lifecycle

1. **Queued**: Job created via web interface
2. **Taken**: ESP32 fetched the job
3. **Done**: Job completed successfully (removed from queue)
4. **Failed**: Job failed (kept in queue for retry)

## 📝 Advanced Logging System

### **Normal Request Log** (`logs/requests.log`)
- All API requests except successful ESP32 polling
- Masked IP addresses and sensitive data
- Timestamp, user agent, and request parameters
- Event types: enqueue, next, ack

### **Security Incident Log** (`logs/security.log`)
- Detailed forensic information for security events
- Real IP addresses (for security analysis)
- Full HTTP headers and browser fingerprints
- Rate limiting violations and unauthorized access attempts

### **Log Examples**

**Normal Request:**
```json
{
  "ts": "2025-10-15T22:51:00+03:00",
  "ip": "192.***.***64",
  "status": "success",
  "tag": "enqueue"
}
```

**Security Incident:**
```json
{
  "ts": "2025-10-15T22:51:00+03:00",
  "real_ip": "192.168.1.50",
  "fingerprint": "a1b2c3d4e5f6...",
  "status": "rate_limit_exceeded",
  "ua": "Mozilla/5.0...",
  "headers": {"x_forwarded_for": "10.0.0.1"}
}
```

## ⚙️ Configuration Options

### **Security Settings** (`log.php`)
```php
// Enable/disable IP restrictions
function isAllowedIp($ip) {
  return true; // Currently allows all IPs
}

// Rate limiting thresholds
checkRateLimit($ip, 10);  // Max requests per minute
```

### **ESP32 Detection**
- Automatic detection via `ESP32HTTPClient` user agent
- Bypasses rate limiting for normal operation
- Filters successful polling from logs

## 🛡️ Security Best Practices

1. **Change default API key** in `config.php`
2. **Monitor security logs** regularly
3. **Enable IP restrictions** if needed for high-security environments
4. **Set proper file permissions** (755 for directories, 644 for files)
5. **Use HTTPS** in production
6. **Regular log rotation** to manage disk space

## 🤝 Contributing

Feel free to submit issues, feature requests, or pull requests to improve this project.

## 📄 License

This project is open source. Use it freely for personal or commercial projects.

---

**Security Note**: This system includes enterprise-grade security features including rate limiting, browser fingerprinting, and detailed forensic logging. Monitor the security logs regularly and adjust rate limits based on your usage patterns.
