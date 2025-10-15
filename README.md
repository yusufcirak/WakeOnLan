# WakeOnLan
ESP32 Wake-on-LAN Remote System

A complete Wake-on-LAN (WoL) system consisting of a web interface and ESP32 device that can remotely wake up computers on your network.

## 🚀 Features

- **Web Interface**: Modern, responsive web UI for queuing wake commands
- **ESP32 Device**: Polls for commands and sends WoL magic packets
- **Queue System**: File-based job queue with atomic operations
- **Security**: API key authentication and request logging
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

## 🔒 Security Features

- **API Key Authentication**: All endpoints require valid API key
- **Request Logging**: All API calls are logged with sanitized data
- **Input Validation**: MAC addresses and parameters are validated
- **File Locking**: Atomic operations prevent race conditions
- **Data Sanitization**: Sensitive data is masked in logs

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

## 📊 Status Indicators

### Web Interface
- **Ready**: System ready for commands
- **Sending**: Command being transmitted
- **Success**: Command queued successfully
- **Error**: Various error states with descriptions

### ESP32 LED (if available)
- **Single blink**: Command failed
- **Double blink**: Command successful
- **Rapid blinking**: WiFi connecting

## 🔄 Job Lifecycle

1. **Queued**: Job created via web interface
2. **Taken**: ESP32 fetched the job
3. **Done**: Job completed successfully (removed from queue)
4. **Failed**: Job failed (kept in queue for retry)

## 📝 Logging

All API requests are logged to `logs/requests.log` with:
- Timestamp (ISO 8601 format)
- Client IP address
- User agent
- Request parameters (with sensitive data masked)
- Event type (enqueue, next, ack)

## 🤝 Contributing

Feel free to submit issues, feature requests, or pull requests to improve this project.

## 📄 License

This project is open source. Use it freely for personal or commercial projects.

---

**Note**: Make sure to change default API keys and configure proper security measures before deploying to production environments.
