// ---------------------------------------------------------------------------
// AGUADA - Gateway ESP32 DevKit1 (Mínimo)
// Somente receber ESP-NOW e enviar via HTTP POST ao servidor
// ---------------------------------------------------------------------------
#pragma once

// WiFi
#define MIN_WIFI_SSID        "SEU_SSID"
#define MIN_WIFI_PASSWORD    "SUA_SENHA"

// Endpoint HTTP (usar IP do servidor XAMPP ou outro backend)
// Exemplo: "http://192.168.0.117/xaguada/inserir_leitura.php"
#define MIN_SERVER_ENDPOINT  "http://192.168.0.117/xaguada/inserir_leitura.php"

// Canal ESP-NOW utilizado pelos sensores (ajustar se diferente)
#define MIN_ESPNOW_CHANNEL   1

// Timeout de conexão WiFi (ms)
#define MIN_WIFI_TIMEOUT_MS  15000

// Tamanho máximo do payload recebido (bytes)
#define MIN_JSON_MAX_SIZE    256

// ---------------------------------------------------------------------------
// CÁLCULO DE VOLUME (opcional para quando sensor só envia distancia)
// Fórmula cilindro vertical: volume = (H_TOTAL - OFFSET - dist_m) * π * (DIAM/2)^2
// Ajuste conforme o reservatório real.
// ---------------------------------------------------------------------------
#define MIN_TANK_HEIGHT_M     4.67    // hcxagua
#define MIN_SENSOR_OFFSET_M   0.20    // hsensor (distância entre transdutor e tampo líquido máximo)
#define MIN_TANK_DIAMETER_M   5.10    // diametro

// Nome padrão do node caso JSON não traga 'node'
#define MIN_DEFAULT_NODE      "Nano_01"

// Número máximo de tentativas de POST em falha
#define MIN_POST_MAX_RETRIES  3

