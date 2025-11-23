/**
 * AGUADA - sen_aguada_v1 (Payload Agregado Único)
 * Derivado de node_sensor_10 porém envia um JSON completo em cada ciclo.
 */
#ifndef CONFIG_H
#define CONFIG_H

#include "driver/gpio.h"

#define FIRMWARE_VERSION "sen_aguada_v1"

// MAC do gateway destino
static uint8_t gateway_mac[6] = {0x80, 0xf1, 0xb2, 0x50, 0x2e, 0xc4};

// Canal fixo ESP-NOW (igual ao gateway / rede)
#define ESPNOW_CHANNEL 11

// Pinos AJ-SR04M
#define TRIG_PIN GPIO_NUM_1
#define ECHO_PIN GPIO_NUM_0

// Detector de som
#define SOUND_IN_PIN GPIO_NUM_5

// LED
#define LED_STATUS GPIO_NUM_8

// Parâmetros Ultrassônico
#define MIN_DISTANCE_CM 20
#define MAX_DISTANCE_CM 450
#define SAMPLES_FOR_MEDIAN 11
#define ULTRASONIC_TIMEOUT_MS 30
#define TIMEOUT_US (ULTRASONIC_TIMEOUT_MS * 1000)

// Intervalo de envio (ms)
#define AGG_INTERVAL_MS 30000

// Tanque (exemplo) - ajustar conforme reservatório real
#define TANK_HEIGHT_CM       467.0f   // hcxagua em cm
#define SENSOR_OFFSET_CM     20.0f    // hsensor em cm
#define TANK_DIAMETER_M      5.10f    // diâmetro em metros

// Limites cálculo
#define MIN_PERCENTUAL 0.0f
#define MAX_PERCENTUAL 100.0f

// Tamanho máximo do payload
#define MAX_JSON_SIZE 260

#endif // CONFIG_H
