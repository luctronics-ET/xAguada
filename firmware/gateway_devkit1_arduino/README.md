# AGUADA - Gateway ESP32 DevKit1

Bridge ESP-NOW → MQTT para integrar sensores (nodes) com o backend via broker MQTT.

## Diferenças vs Versão ESP32-C3
- Callback ESP-NOW usa assinatura "legada" (`onDataRecv(const uint8_t* mac, const uint8_t* data, int len)`)
- LED builtin no DevKit1 está no GPIO 2.
- Mantido canal definido em `GW_ESPNOW_CHANNEL` (default 1) para alinhar sensores.

## Arquivos
- `gateway_devkit1_arduino.ino`: lógica principal (WiFi, ESP-NOW, MQTT)
- `gateway_config.h`: parâmetros editáveis (SSID, senha, broker, tópico, intervalos)

## Pré-Requisitos
Instalar bibliotecas no Arduino IDE:
1. PubSubClient
2. ArduinoJson
3. Suporte a placas ESP32 (Board Manager)

## Configuração
Editar `gateway_config.h`:
```cpp
#define GW_WIFI_SSID     "SEU_SSID"
#define GW_WIFI_PASSWORD "SUA_SENHA"
#define GW_MQTT_BROKER   "192.168.0.117"
#define GW_MQTT_TOPIC    "aguada/telemetry"
```

## Flash
1. Selecionar placa: "ESP32 Dev Module"
2. Baudrate monitor: 115200
3. Carregar sketch

## Operação
- Recebe pacotes JSON dos nodes via ESP-NOW (broadcast)
- Publica payload diretamente em `GW_MQTT_TOPIC` se conectado ao broker
- LED pisca a 1 Hz como heartbeat

## Extensões Futuras
- Reenvio em caso de falha MQTT (fila local)
- Métricas de latência / contagem de pacotes

## Troubleshooting
- Sem mensagens: verificar canal ESP-NOW dos sensores
- MQTT falhando: checar IP do broker e firewall
- LED fixo: watchdog travado → reset manual
