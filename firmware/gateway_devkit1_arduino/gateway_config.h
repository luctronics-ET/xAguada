// ---------------------------------------------------------------------------
// AGUADA - Gateway ESP32 DevKit1 - Configurações
// Editar conforme ambiente local
// ---------------------------------------------------------------------------
#pragma once

// WiFi
#define GW_WIFI_SSID        "SEU_SSID"
#define GW_WIFI_PASSWORD    "SUA_SENHA"

// MQTT
#define GW_MQTT_BROKER      "192.168.0.117"   // IP do servidor MQTT
#define GW_MQTT_PORT        1883
#define GW_MQTT_TOPIC       "aguada/telemetry"

// Canal ESP-NOW (deve casar com os sensores)
#define GW_ESPNOW_CHANNEL   1

// Intervalos (ms)
#define GW_WIFI_CHECK_INTERVAL   5000
#define GW_MQTT_CHECK_INTERVAL   5000
#define GW_HEARTBEAT_INTERVAL    1000

// Tamanho máximo esperado do payload JSON vindo dos nodes
#define GW_JSON_MAX_SIZE 256
