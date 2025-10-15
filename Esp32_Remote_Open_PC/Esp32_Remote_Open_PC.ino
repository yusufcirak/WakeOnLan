/*
  ESP32 WoL Runner (Polling PHP Flat-File API) — DEBUG BUILD (FIXED)
  - Serial debug for every step (WiFi, HTTP, redirects, JSON, WOL, ACK)
  - Uses String for job id to avoid 64-bit overflow
  - Open Serial Monitor at 115200
*/

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <lwip/sockets.h>
#include <lwip/inet.h>
#include <time.h>

// ===================== USER CONFIG =====================
static const char* WIFI_SSID = "Your_Network_SSID";
static const char* WIFI_PASS = "Your_Network_Password";

// Root of your PHP endpoints (NO trailing slash)
static const char* HOST_BASE = "Your_Host_Base";

static const char* API_KEY   = "Your_Api_Key";
static const char* DEVICE_ID = "esp32-lanbox";

static const int   POLL_SECONDS    = 5;   // poll interval
static const int   WOL_PORT        = 9;   // UDP port (7 or 9 commonly)
static const bool  USE_INSECURE_TLS = true;  // set false and provide root CA below for real TLS validation

// Optional: provide your hosting root CA (PEM) if USE_INSECURE_TLS == false
/*
static const char* ROOT_CA_PEM = R"PEM(
-----BEGIN CERTIFICATE-----
... YOUR ROOT CA PEM ...
-----END CERTIFICATE-----
)PEM";
*/
static const bool  SYNC_TIME_FOR_TLS = false; // set true if you enable strict TLS
// =======================================================


// ================ OPTIONAL: STATUS LED =================
#ifdef LED_BUILTIN
#define HAVE_LED 1
#else
#define HAVE_LED 0
#endif

void ledInit() {
#if HAVE_LED
  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, LOW);
#endif
}
void ledBlink(int times = 1, int onMs = 80, int offMs = 80) {
#if HAVE_LED
  for (int i = 0; i < times; ++i) {
    digitalWrite(LED_BUILTIN, HIGH);
    delay(onMs);
    digitalWrite(LED_BUILTIN, LOW);
    delay(offMs);
  }
#endif
}

// ==================== DEBUG HELPERS ====================
String maskKey(const char* key) {
  String s = key ? String(key) : String("");
  if (s.length() <= 6) return s;
  return s.substring(0, 3) + "****" + s.substring(s.length() - 2);
}
void printIp(IPAddress ip) {
  Serial.printf("%u.%u.%u.%u", ip[0], ip[1], ip[2], ip[3]);
}
void printHttpStart(const char* tag, const String& url) {
  Serial.printf("\n[%s] GET %s\n", tag, url.c_str());
}
void printHttpResult(int code, const String& body) {
  Serial.printf("[HTTP] status=%d\n", code);
  String trimmed = body;
  if (trimmed.length() > 600) trimmed = trimmed.substring(0, 600) + "...(trim)";
  Serial.println("[HTTP] body:");
  Serial.println(trimmed);
}

// ==================== NET HELPERS ======================
WiFiClientSecure tls;

// Convert "AA:BB:CC:DD:EE:FF" -> 6 bytes
bool macStrToBytes(const char* macStr, uint8_t mac[6]) {
  int v[6];
  if (sscanf(macStr, "%x:%x:%x:%x:%x:%x", &v[0], &v[1], &v[2], &v[3], &v[4], &v[5]) != 6) {
    return false;
  }
  for (int i = 0; i < 6; ++i) mac[i] = (uint8_t)v[i];
  return true;
}

// Send Wake-on-LAN magic packet (broadcast by default; unicast if IP provided)
bool sendWol(const char* macStr, const char* unicastIp) {
  uint8_t mac[6];
  if (!macStrToBytes(macStr, mac)) {
    Serial.println("[WOL] mac parse failed");
    return false;
  }

  uint8_t pkt[102];
  memset(pkt, 0xFF, 6);
  for (int i = 0; i < 16; ++i) memcpy(pkt + 6 + i * 6, mac, 6);

  int sock = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP);
  if (sock < 0) {
    Serial.println("[WOL] socket() failed");
    return false;
  }

  int broadcastEnable = 1;
  setsockopt(sock, SOL_SOCKET, SO_BROADCAST, &broadcastEnable, sizeof(broadcastEnable));

  struct sockaddr_in addr;
  memset(&addr, 0, sizeof(addr));
  addr.sin_family = AF_INET;
  addr.sin_port   = htons(WOL_PORT);

  if (unicastIp && strlen(unicastIp) > 0) {
    addr.sin_addr.s_addr = inet_addr(unicastIp);
    Serial.printf("[WOL] sending unicast to %s\n", unicastIp);
  } else {
    addr.sin_addr.s_addr = INADDR_BROADCAST; // 255.255.255.255
    Serial.println("[WOL] sending broadcast to 255.255.255.255");
  }

  int sent = sendto(sock, (const char*)pkt, sizeof(pkt), 0, (struct sockaddr*)&addr, sizeof(addr));
  close(sock);

  bool ok = (sent == sizeof(pkt));
  Serial.printf("[WOL] sent=%d expected=%d -> %s\n", sent, (int)sizeof(pkt), ok ? "OK" : "FAIL");
  return ok;
}

void ensureWifi() {
  if (WiFi.status() == WL_CONNECTED) return;

  Serial.printf("[WiFi] connecting to SSID='%s' ...\n", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);

  uint32_t t0 = millis();
  while (WiFi.status() != WL_CONNECTED) {
    ledBlink(1, 40, 200);
    delay(250);
    if (millis() - t0 > 20000) {  // 20s timeout
      Serial.println("[WiFi] timeout, retrying...");
      t0 = millis();
    }
  }
  Serial.print("[WiFi] connected, IP="); printIp(WiFi.localIP()); Serial.println();
}

bool httpGetJson(const char* tag, const String& url, DynamicJsonDocument& doc) {
  HTTPClient http;
  http.setTimeout(8000); // 8s
  http.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS);

  printHttpStart(tag, url);
  http.begin(tls, url);
  int code = http.GET();
  String body;
  if (code > 0) body = http.getString();
  http.end();

  printHttpResult(code, body);
  if (code != HTTP_CODE_OK) return false;

  DeserializationError err = deserializeJson(doc, body);
  if (err) {
    Serial.printf("[JSON] parse error: %s\n", err.c_str());
    return false;
  }
  return true;
}

// ==================== JOB HANDLING =====================
// CHANGED: accept job id as String to avoid overflow
bool ackJob(const String& jobIdStr, bool ok) {
  String url = String(HOST_BASE) +
               "/api_ack.php?key=" + API_KEY +
               "&device_id=" + DEVICE_ID +
               "&id=" + jobIdStr +
               "&status=" + (ok ? "done" : "failed");

  DynamicJsonDocument doc(256);
  bool res = httpGetJson("ACK", url, doc) && doc["ok"] == true;
  Serial.printf("[ACK] id=%s -> %s\n", jobIdStr.c_str(), res ? "OK" : "FAIL");
  return res;
}

void pollOnce() {
  String url = String(HOST_BASE) +
               "/api_next.php?key=" + API_KEY +
               "&device_id=" + DEVICE_ID;

  DynamicJsonDocument doc(1024);
  bool ok = httpGetJson("NEXT", url, doc);
  if (!ok) {
    Serial.println("[NEXT] request failed");
    return;
  }

  if (doc["ok"] != true) {
    Serial.println("[NEXT] ok=false");
    return;
  }

  if (doc["job"].isNull()) {
    Serial.println("[NEXT] no job");
    return;
  }

  // Parse job
  String jobIdStr = doc["job"]["id"].as<String>();           // CHANGED: String, not int
  const char* cmd = doc["job"]["cmd"] | "";
  const char* mac = doc["job"]["payload"]["mac"] | "";
  const char* uip = doc["job"]["payload"]["unicast_ip"] | "";

  Serial.printf("[JOB] id=%s cmd=%s mac=%s unicast_ip=%s\n",
                jobIdStr.c_str(), cmd, mac, uip);

  if (strcmp(cmd, "wake") != 0) {
    Serial.println("[JOB] unknown cmd -> ACK failed");
    ackJob(jobIdStr, false);
    return;
  }

  bool wolOk = sendWol(mac, uip);
  ackJob(jobIdStr, wolOk);
  ledBlink(wolOk ? 2 : 1, wolOk ? 60 : 200, 80);
}

// ====================== SETUP/LOOP =====================
void setupTimeIfNeeded() {
  if (!SYNC_TIME_FOR_TLS) return;
  Serial.println("[TIME] syncing NTP...");
  configTime(0, 0, "pool.ntp.org", "time.google.com");
  for (int i = 0; i < 30; ++i) {
    time_t now = time(nullptr);
    if (now > 1700000000) {
      Serial.printf("[TIME] synced: %ld\n", now);
      return;
    }
    delay(200);
  }
  Serial.println("[TIME] sync timeout (continuing)");
}

void setup() {
  Serial.begin(115200);
  delay(50);
  Serial.println("\n=== ESP32 WoL DEBUG (FIXED) ===");
  Serial.printf("[CFG] host=%s\n", HOST_BASE);
  Serial.printf("[CFG] device_id=%s\n", DEVICE_ID);
  Serial.printf("[CFG] api_key=%s\n", maskKey(API_KEY).c_str());
  Serial.printf("[CFG] tls=%s\n", USE_INSECURE_TLS ? "INSECURE" : "STRICT");

  ledInit();
  ensureWifi();

  if (USE_INSECURE_TLS) {
    tls.setInsecure();
    Serial.println("[TLS] setInsecure()");
  } else {
    setupTimeIfNeeded();
    // tls.setCACert(ROOT_CA_PEM);
    Serial.println("[TLS] strict CA set");
  }

  ledBlink(2, 80, 120);
}

void loop() {
  ensureWifi();
  pollOnce();
  delay(POLL_SECONDS * 1000);
}
