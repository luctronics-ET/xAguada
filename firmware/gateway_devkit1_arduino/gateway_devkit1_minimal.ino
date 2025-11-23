/**
 * AGUADA - Gateway ESP32 DevKit1 (Versão Mínima)
 * Objetivo: Receber pacotes JSON via ESP-NOW e reenviar para servidor HTTP.
 * Posteriores expansões (MQTT, métricas, fila) serão adicionadas depois.
 */

#include <WiFi.h>
#include <esp_now.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "gateway_config_min.h"

#ifndef LED_BUILTIN
#define LED_BUILTIN 2 // DevKit1 geralmente usa GPIO2
#endif

// ---------------------------------------------------------------------------
// WIFI
// ---------------------------------------------------------------------------
static bool wifiReady = false;

void connectWiFiMinimal() {
  Serial.println("Conectando WiFi...");
  WiFi.mode(WIFI_STA);
  WiFi.begin(MIN_WIFI_SSID, MIN_WIFI_PASSWORD);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < MIN_WIFI_TIMEOUT_MS) {
    delay(500);
    Serial.print('.');
  }
  Serial.println();
  if (WiFi.status() == WL_CONNECTED) {
    wifiReady = true;
    Serial.print("✓ WiFi OK. IP: ");
    Serial.println(WiFi.localIP());
  } else {
    wifiReady = false;
    Serial.println("✗ Falha WiFi");
  }
}

// ---------------------------------------------------------------------------
// POST HTTP
// ---------------------------------------------------------------------------
bool postToServer(const char* jsonPayload) {
  if (!wifiReady) return false;
  HTTPClient http;
  http.begin(MIN_SERVER_ENDPOINT);
  http.addHeader("Content-Type", "application/json");
  int attempt = 0; int code = -1;
  while (attempt < MIN_POST_MAX_RETRIES) {
    code = http.POST((uint8_t*)jsonPayload, strlen(jsonPayload));
    if (code > 0) {
      Serial.printf("→ POST %s (status %d, tentativa %d)\n", MIN_SERVER_ENDPOINT, code, attempt+1);
      if (code >=200 && code <300) {
        String resp = http.getString();
        if (resp.length() > 0) {
          Serial.print("   Resposta: ");
          Serial.println(resp.substring(0, 120));
        }
        break;
      }
    } else {
      Serial.printf("✗ Erro POST (code=%d, tentativa %d)\n", code, attempt+1);
    }
    attempt++;
    if (attempt < MIN_POST_MAX_RETRIES) {
      delay(400); // pequeno backoff
    }
  }
  http.end();
  return code >= 200 && code < 300;
}

// ---------------------------------------------------------------------------
// CALLBACK ESP-NOW (assinatura para DevKit1)
// ---------------------------------------------------------------------------
void onDataRecv(const uint8_t* mac, const uint8_t* incomingData, int len) {
  if (len <= 0 || len >= MIN_JSON_MAX_SIZE) {
    Serial.println("✗ Payload inválido ou excede limite");
    return;
  }
  char jsonStr[MIN_JSON_MAX_SIZE];
  memcpy(jsonStr, incomingData, len);
  jsonStr[len] = '\0';

  Serial.printf("\n[ESP-NOW] %02X:%02X:%02X:%02X:%02X:%02X (%d bytes)\n", mac[0], mac[1], mac[2], mac[3], mac[4], mac[5], len);
  Serial.print("Raw: ");
  Serial.println(jsonStr);

  // Opcional: validar JSON antes de enviar
  StaticJsonDocument<MIN_JSON_MAX_SIZE> doc;
  DeserializationError err = deserializeJson(doc, jsonStr);
  if (err) {
    Serial.print("✗ JSON inválido: ");
    Serial.println(err.c_str());
    return; // Se quiser enviar mesmo inválido, remover este return
  }

  // Se já vier no formato esperado (node, dist, volume, percentual) encaminha direto
  bool hasNode = doc.containsKey("node");
  bool hasDist = doc.containsKey("dist");
  bool hasVol  = doc.containsKey("volume");
  bool hasPerc = doc.containsKey("percentual");

  StaticJsonDocument<MIN_JSON_MAX_SIZE> out;
  if (hasNode && hasDist && hasVol && hasPerc) {
    // Copia direto
    out["node"] = doc["node"].as<const char*>();
    out["dist"] = doc["dist"].as<float>();
    out["volume"] = doc["volume"].as<float>();
    out["percentual"] = doc["percentual"].as<float>();
  } else {
    // Transformar caso sensor só envie nivel_cm
    const char* nodeName = hasNode ? doc["node"].as<const char*>() : MIN_DEFAULT_NODE;
    float distCm = 0.0f;
    if (hasDist) {
      distCm = doc["dist"].as<float>();
    } else if (doc.containsKey("value") && doc.containsKey("type") && strcmp(doc["type"].as<const char*>(), "nivel_cm") == 0) {
      distCm = doc["value"].as<float>();
    }
    // Converter para metros
    float distM = distCm / 100.0f;
    // Cálculo de volume
    float baseArea = PI * pow(MIN_TANK_DIAMETER_M/2.0f, 2);
    float totalUtil = (MIN_TANK_HEIGHT_M - MIN_SENSOR_OFFSET_M) * baseArea;
    float volume = (MIN_TANK_HEIGHT_M - MIN_SENSOR_OFFSET_M - distM) * baseArea; // m³
    if (volume < 0) volume = 0;
    if (volume > totalUtil) volume = totalUtil;
    float percentual = totalUtil > 0 ? (volume / totalUtil) * 100.0f : 0;

    out["node"] = nodeName;
    out["dist"] = distCm;          // em centímetros conforme backend espera
    out["volume"] = volume;        // m³
    out["percentual"] = percentual; // %
  }

  char sendBuf[MIN_JSON_MAX_SIZE];
  size_t n = serializeJson(out, sendBuf, sizeof(sendBuf));
  if (n == 0) {
    Serial.println("✗ Falha ao serializar JSON de saída");
    return;
  }

  Serial.print("→ Enviando JSON ao backend: ");
  Serial.println(sendBuf);
  if (!postToServer(sendBuf)) {
    Serial.println("✗ Falha ao enviar ao servidor");
  } else {
    Serial.println("✓ Enviado ao servidor");
  }
}

// ---------------------------------------------------------------------------
// SETUP
// ---------------------------------------------------------------------------
void setup() {
  Serial.begin(115200);
  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, LOW);

  // Blink simples inicial
  for (int i = 0; i < 2; i++) { digitalWrite(LED_BUILTIN, HIGH); delay(150); digitalWrite(LED_BUILTIN, LOW); delay(150); }

  Serial.println("=== AGUADA Gateway Mínimo (DevKit1) ===");
  connectWiFiMinimal();

  // Inicializar ESP-NOW
  if (esp_now_init() != ESP_OK) {
    Serial.println("✗ Erro ESP-NOW");
    return;
  }
  Serial.println("✓ ESP-NOW pronto");

  esp_now_register_recv_cb(onDataRecv);
  Serial.println("✓ Callback registrado");

  // Adicionar peer broadcast para receber de qualquer MAC
  esp_now_peer_info_t peerInfo = {};
  for (int i = 0; i < 6; i++) peerInfo.peer_addr[i] = 0xFF;
  peerInfo.channel = MIN_ESPNOW_CHANNEL;
  peerInfo.encrypt = false;
  if (esp_now_add_peer(&peerInfo) == ESP_OK) {
    Serial.println("✓ Peer broadcast adicionado");
  } else {
    Serial.println("✗ Falha ao adicionar peer broadcast");
  }

  Serial.println("Pronto. Aguardando pacotes...");
}

// ---------------------------------------------------------------------------
// LOOP
// ---------------------------------------------------------------------------
void loop() {
  // Pequeno heartbeat no LED (toggle lento)
  static unsigned long lastBlink = 0;
  if (millis() - lastBlink > 1000) {
    lastBlink = millis();
    digitalWrite(LED_BUILTIN, !digitalRead(LED_BUILTIN));
  }

  // Se WiFi caiu tenta reconectar (cheque leve)
  static unsigned long lastWifiCheck = 0;
  if (millis() - lastWifiCheck > 5000) {
    lastWifiCheck = millis();
    if (WiFi.status() != WL_CONNECTED) {
      wifiReady = false;
      Serial.println("WiFi caiu. Reconnect...");
      connectWiFiMinimal();
    }
  }

  delay(10);
}
