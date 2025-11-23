/**
 * AGUADA - Gateway v1.0 (ESP32 DevKit1)
 * ESP-NOW Receiver -> MQTT Publisher
 * Adaptado da versão ESP32-C3 para placa DevKit (pino LED diferente e callback legado).
 */

#include <WiFi.h>
#include <esp_now.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include "gateway_config.h"

// ---------------------------------------------------------------------------
// HARDWARE
// ---------------------------------------------------------------------------
// LED interno do ESP32 DevKit1 normalmente está no GPIO 2
#ifndef LED_BUILTIN
#define LED_BUILTIN 2
#endif

// ---------------------------------------------------------------------------
// GLOBAIS
// ---------------------------------------------------------------------------
WiFiClient espClient;
PubSubClient mqttClient(espClient);

unsigned long lastWifiCheck = 0;
unsigned long lastMqttCheck = 0;
unsigned long lastHeartbeat = 0;
bool ledState = false;

// ---------------------------------------------------------------------------
// CALLBACK ESP-NOW (assinatura legado para ESP32 clássico)
// ---------------------------------------------------------------------------
void onDataRecv(const uint8_t * mac, const uint8_t *incomingData, int len) {
  if (len <= 0 || len >= GW_JSON_MAX_SIZE) {
    Serial.println("✗ Payload inválido ou muito grande");
    return;
  }

  char jsonStr[GW_JSON_MAX_SIZE];
  memcpy(jsonStr, incomingData, len);
  jsonStr[len] = '\0';

  Serial.println();
  Serial.println("╔════════════════════════════════════════════════════╗");
  Serial.printf("║ ✓ ESP-NOW recebido de: %02X:%02X:%02X:%02X:%02X:%02X (%d bytes)\n", 
                mac[0], mac[1], mac[2], mac[3], mac[4], mac[5], len);
  Serial.println("╠════════════════════════════════════════════════════╣");
  Serial.print("║ Dados brutos: ");
  Serial.println(jsonStr);
  Serial.println("╚════════════════════════════════════════════════════╝");

  StaticJsonDocument<GW_JSON_MAX_SIZE> doc;
  DeserializationError error = deserializeJson(doc, jsonStr);
  if (error) {
    Serial.print("✗ JSON inválido: ");
    Serial.println(error.c_str());
    return;
  }

  const char* node_mac = doc["mac"] | "(sem mac)";
  const char* type     = doc["type"] | "(sem type)";
  int value            = doc["value"] | -999;
  int rssi             = doc["rssi"] | 0;

  Serial.println();
  Serial.println("╔════════════════════════════════════════════════════╗");
  Serial.printf("║ ✓ JSON válido de: %s\n", node_mac);
  Serial.println("╠════════════════════════════════════════════════════╣");
  Serial.printf("║   Type:    %s\n", type);
  Serial.printf("║   Value:   %d\n", value);
  Serial.printf("║   RSSI:    %d dBm\n", rssi);
  Serial.println("╚════════════════════════════════════════════════════╝");

  if (mqttClient.connected()) {
    if (mqttClient.publish(GW_MQTT_TOPIC, jsonStr)) {
      Serial.println("→ Publicado no MQTT com sucesso!");
    } else {
      Serial.println("✗ Falha ao publicar no MQTT");
    }
  } else {
    Serial.println("✗ MQTT desconectado. Dados não enviados.");
  }
}

// ---------------------------------------------------------------------------
// WIFI
// ---------------------------------------------------------------------------
void connectWiFi() {
  Serial.println();
  Serial.println("Conectando ao WiFi...");
  WiFi.mode(WIFI_STA);
  WiFi.begin(GW_WIFI_SSID, GW_WIFI_PASSWORD);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println();
    Serial.println("✓ WiFi conectado!");
    Serial.print("  IP: "); Serial.println(WiFi.localIP());
    Serial.print("  MAC: "); Serial.println(WiFi.macAddress());
  } else {
    Serial.println();
    Serial.println("✗ Falha ao conectar WiFi");
  }
}

void maintainWiFi() {
  if (millis() - lastWifiCheck > GW_WIFI_CHECK_INTERVAL) {
    lastWifiCheck = millis();
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("WiFi desconectado. Reconectando...");
      connectWiFi();
    }
  }
}

// ---------------------------------------------------------------------------
// MQTT
// ---------------------------------------------------------------------------
void connectMQTT() {
  while (!mqttClient.connected()) {
    Serial.println("Conectando ao MQTT...");
    String clientId = "AGUADA_GATEWAY_DEVKIT1_" + String(random(0xffff), HEX);
    if (mqttClient.connect(clientId.c_str())) {
      Serial.println("✓ MQTT conectado!");
    } else {
      Serial.print("✗ Falha MQTT, rc=");
      Serial.print(mqttClient.state());
      Serial.println(" Tentando novamente em 5s...");
      delay(5000);
    }
  }
}

void maintainMQTT() {
  if (millis() - lastMqttCheck > GW_MQTT_CHECK_INTERVAL) {
    lastMqttCheck = millis();
    if (!mqttClient.connected()) {
      Serial.println("MQTT desconectado. Reconectando...");
      connectMQTT();
    }
  }
  mqttClient.loop();
}

// ---------------------------------------------------------------------------
// SETUP
// ---------------------------------------------------------------------------
void setup() {
  Serial.begin(115200);
  delay(500);

  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, LOW);

  for (int i = 0; i < 3; i++) {
    digitalWrite(LED_BUILTIN, HIGH); delay(120);
    digitalWrite(LED_BUILTIN, LOW);  delay(120);
  }

  Serial.println();
  Serial.println("╔═══════════════════════════════════════════════════════════╗");
  Serial.println("║        AGUADA Gateway (ESP32 DevKit1) v1.0                ║");
  Serial.println("║        ESP-NOW → MQTT Bridge                              ║");
  Serial.println("╚═══════════════════════════════════════════════════════════╝");
  Serial.println();

  connectWiFi();

  // Canal fixo para alinhar com sensores
  WiFi.setSleep(false); // reduzir latência ESP-NOW
  Serial.printf("✓ Canal WiFi/ESP-NOW alvo: %d (definido pelos nodes)\n", GW_ESPNOW_CHANNEL);

  if (esp_now_init() != ESP_OK) {
    Serial.println("✗ Erro ao inicializar ESP-NOW");
    ESP.restart();
  }
  Serial.println("✓ ESP-NOW inicializado");

  esp_now_register_recv_cb(onDataRecv);
  Serial.println("✓ Callback ESP-NOW registrado");

  // Peer broadcast
  esp_now_peer_info_t peerInfo = {};
  memset(&peerInfo, 0, sizeof(peerInfo));
  for (int i = 0; i < 6; i++) peerInfo.peer_addr[i] = 0xFF;
  peerInfo.channel = GW_ESPNOW_CHANNEL;
  peerInfo.encrypt = false;
  if (esp_now_add_peer(&peerInfo) == ESP_OK) {
    Serial.println("✓ Peer broadcast adicionado");
  } else {
    Serial.println("✗ Falha ao adicionar peer broadcast");
  }

  mqttClient.setServer(GW_MQTT_BROKER, GW_MQTT_PORT);
  connectMQTT();

  Serial.println();
  Serial.println("✓ Gateway inicializado e pronto! Aguardando dados...");
}

// ---------------------------------------------------------------------------
// LOOP
// ---------------------------------------------------------------------------
void loop() {
  if (millis() - lastHeartbeat >= GW_HEARTBEAT_INTERVAL) {
    lastHeartbeat = millis();
    ledState = !ledState;
    digitalWrite(LED_BUILTIN, ledState);
  }
  maintainWiFi();
  maintainMQTT();
  delay(10);
}
