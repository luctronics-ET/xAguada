/**
 * AGUADA - sen_aguada_v1
 * Envia JSON agregado único via ESP-NOW:
 * {
 *   "mac": "AA:BB:CC:DD:EE:FF",
 *   "type": "distancia_cm",
 *   "distancia_cm": <int>,
 *   "nivel_cm": <float>,
 *   "nivel_max_cm": <float>,
 *   "volume_max_m3": <float>,
 *   "volume_m3": <float>,
 *   "percentual": <float>,
 *   "som": <0|1>,
 *   "vdc": <float>,
 *   "temp_c": <float>,
 *   "rssi": <int>
 * }
 */

#include <stdio.h>
#include <string.h>
#include <math.h>
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "esp_system.h"
#include "esp_wifi.h"
#include "esp_event.h"
#include "esp_now.h"
#include "esp_log.h"
#include "nvs_flash.h"
#include "driver/gpio.h"
#include "esp_timer.h"
#include "config.h"

static const char *TAG = "SEN_AGUADA_V1";

// MAC do node
static uint8_t node_mac[6];
static char node_mac_str[18];

// Estatísticas
static uint32_t packets_sent = 0;
static uint32_t packets_failed = 0;

// ========= GPIO =========
static void init_gpio(void) {
    gpio_reset_pin(TRIG_PIN);
    gpio_set_direction(TRIG_PIN, GPIO_MODE_OUTPUT);
    gpio_set_level(TRIG_PIN, 0);

    gpio_reset_pin(ECHO_PIN);
    gpio_set_direction(ECHO_PIN, GPIO_MODE_INPUT);

    gpio_reset_pin(SOUND_IN_PIN);
    gpio_set_direction(SOUND_IN_PIN, GPIO_MODE_INPUT);
    gpio_set_pull_mode(SOUND_IN_PIN, GPIO_PULLDOWN_ONLY);

    gpio_reset_pin(LED_STATUS);
    gpio_set_direction(LED_STATUS, GPIO_MODE_OUTPUT);
    gpio_set_level(LED_STATUS, 0);

    ESP_LOGI(TAG, "GPIO OK (TRIG=%d, ECHO=%d, SOM=%d, LED=%d)", TRIG_PIN, ECHO_PIN, SOUND_IN_PIN, LED_STATUS);
}

// ========= Leitura Ultrassônica =========
static int read_ultrasonic_distance_x100(void) {
    gpio_set_level(TRIG_PIN, 0);
    esp_rom_delay_us(2);
    gpio_set_level(TRIG_PIN, 1);
    esp_rom_delay_us(10);
    gpio_set_level(TRIG_PIN, 0);

    int64_t timeout = esp_timer_get_time();
    while (gpio_get_level(ECHO_PIN) == 0) {
        if ((esp_timer_get_time() - timeout) > TIMEOUT_US) return -1; // timeout subida
    }
    int64_t start = esp_timer_get_time();
    while (gpio_get_level(ECHO_PIN) == 1) {
        if ((esp_timer_get_time() - start) > TIMEOUT_US) return -1; // timeout pulso longo
    }
    int64_t duration = esp_timer_get_time() - start;
    int dist_x100 = (int)((duration * 343) / 200); // cm*100
    if (dist_x100 < MIN_DISTANCE_CM*100 || dist_x100 > MAX_DISTANCE_CM*100) return -2;
    return dist_x100;
}

static int read_distance_filtered_x100(void) {
    int samples[SAMPLES_FOR_MEDIAN];
    int valid = 0;
    for (int i=0;i<SAMPLES_FOR_MEDIAN;i++) {
        int d = read_ultrasonic_distance_x100();
        if (d>0) samples[valid++] = d;
        vTaskDelay(pdMS_TO_TICKS(150));
    }
    if (valid==0) return -1;
    for (int i=0;i<valid-1;i++) {
        for(int j=0;j<valid-i-1;j++) {
            if(samples[j]>samples[j+1]) {int t=samples[j];samples[j]=samples[j+1];samples[j+1]=t;}
        }
    }
    return samples[valid/2];
}

// ========= ESP-NOW Callback =========
static void espnow_send_cb(const esp_now_send_info_t *info, esp_now_send_status_t status) {
    if (status == ESP_NOW_SEND_SUCCESS) packets_sent++; else packets_failed++;
}

// ========= Init ESP-NOW =========
static void init_espnow(void) {
    esp_err_t ret = nvs_flash_init();
    if (ret == ESP_ERR_NVS_NO_FREE_PAGES || ret == ESP_ERR_NVS_NEW_VERSION_FOUND) {
        ESP_ERROR_CHECK(nvs_flash_erase());
        ret = nvs_flash_init();
    }
    ESP_ERROR_CHECK(ret);

    ESP_ERROR_CHECK(esp_netif_init());
    ESP_ERROR_CHECK(esp_event_loop_create_default());
    wifi_init_config_t cfg = WIFI_INIT_CONFIG_DEFAULT();
    ESP_ERROR_CHECK(esp_wifi_init(&cfg));
    ESP_ERROR_CHECK(esp_wifi_set_storage(WIFI_STORAGE_RAM));
    ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_STA));
    ESP_ERROR_CHECK(esp_wifi_start());

    ESP_ERROR_CHECK(esp_wifi_get_mac(WIFI_IF_STA, node_mac));
    snprintf(node_mac_str,sizeof(node_mac_str),"%02X:%02X:%02X:%02X:%02X:%02X",node_mac[0],node_mac[1],node_mac[2],node_mac[3],node_mac[4],node_mac[5]);
    ESP_LOGI(TAG,"MAC Node: %s", node_mac_str);

    ESP_ERROR_CHECK(esp_wifi_set_channel(ESPNOW_CHANNEL, WIFI_SECOND_CHAN_NONE));
    ESP_LOGI(TAG,"Canal fixo: %d", ESPNOW_CHANNEL);

    ESP_ERROR_CHECK(esp_now_init());
    ESP_ERROR_CHECK(esp_now_register_send_cb(espnow_send_cb));

    esp_now_peer_info_t peer = {};
    memcpy(peer.peer_addr, gateway_mac, 6);
    peer.channel = ESPNOW_CHANNEL;
    peer.encrypt = false;
    ESP_ERROR_CHECK(esp_now_add_peer(&peer));
    ESP_LOGI(TAG,"Peer gateway adicionado");
}

// ========= Leitura Som =========
static uint8_t read_sound(void){ return gpio_get_level(SOUND_IN_PIN); }

// ========= Leitura Tensão (placeholder) =========
static float read_voltage_vdc(void){ return 0.0f; } // TODO: ADC real se ligado

// ========= Leitura Temperatura (placeholder) =========
static float read_temp_c(void){ return 0.0f; }

// ========= Construir e Enviar JSON Agregado =========
static void send_aggregate_payload(void) {
    int distance_x100 = read_distance_filtered_x100();
    float distancia_cm = (distance_x100>0)? (distance_x100/100.0f) : -1.0f;

    float nivel_max_cm = (TANK_HEIGHT_CM - SENSOR_OFFSET_CM);
    float nivel_cm = (distancia_cm>=0)? (nivel_max_cm - distancia_cm) : -1.0f;
    if (nivel_cm < 0) nivel_cm = 0;
    if (nivel_cm > nivel_max_cm) nivel_cm = nivel_max_cm;

    // Volume
    float base_area_m2 = (float)M_PI * powf(TANK_DIAMETER_M/2.0f,2);
    float volume_max_m3 = (nivel_max_cm/100.0f) * base_area_m2; // cm->m
    float volume_m3 = (nivel_cm/100.0f) * base_area_m2;
    if (volume_m3 < 0) volume_m3 = 0;
    if (volume_m3 > volume_max_m3) volume_m3 = volume_max_m3;

    float percentual = (volume_max_m3>0)? (volume_m3/volume_max_m3)*100.0f : 0.0f;
    if (percentual < MIN_PERCENTUAL) percentual = MIN_PERCENTUAL;
    if (percentual > MAX_PERCENTUAL) percentual = MAX_PERCENTUAL;

    uint8_t som = read_sound();
    float vdc = read_voltage_vdc();
    float temp_c = read_temp_c();

    int rssi=0; esp_wifi_sta_get_rssi(&rssi); if(rssi==0) rssi=-50;

    char payload[MAX_JSON_SIZE];
    int len = snprintf(payload,sizeof(payload),
        "{\"mac\":\"%s\",\"type\":\"distancia_cm\",\"distancia_cm\":%.2f,\"nivel_cm\":%.2f,\"nivel_max_cm\":%.2f,\"volume_max_m3\":%.3f,\"volume_m3\":%.3f,\"percentual\":%.2f,\"som\":%u,\"vdc\":%.2f,\"temp_c\":%.2f,\"rssi\":%d}",
        node_mac_str, distancia_cm, nivel_cm, nivel_max_cm, volume_max_m3, volume_m3, percentual, som, vdc, temp_c, rssi);

    if (len <=0 || len >= MAX_JSON_SIZE) {
        ESP_LOGE(TAG,"Payload truncado ou erro de formatação (%d)", len);
        return;
    }

    ESP_LOGI(TAG,"→ %s", payload);
    esp_err_t result = esp_now_send(gateway_mac,(uint8_t*)payload,strlen(payload));
    if (result != ESP_OK) {
        packets_failed++; ESP_LOGW(TAG,"Falha envio ESP-NOW (%d)", result);
    }
}

// ========= Task Principal =========
static void aggregate_task(void *pv) {
    ESP_LOGI(TAG,"Intervalo agregado: %d ms", AGG_INTERVAL_MS);
    while(1){
        send_aggregate_payload();
        // Blink curto
        gpio_set_level(LED_STATUS,1); vTaskDelay(pdMS_TO_TICKS(120)); gpio_set_level(LED_STATUS,0);
        vTaskDelay(pdMS_TO_TICKS(AGG_INTERVAL_MS));
    }
}

// ========= Task Heartbeat Lento =========
static void heartbeat_task(void *pv){
    while(1){
        gpio_set_level(LED_STATUS,1); vTaskDelay(pdMS_TO_TICKS(80));
        gpio_set_level(LED_STATUS,0); vTaskDelay(pdMS_TO_TICKS(3920));
    }
}

// ========= MAIN =========
void app_main(void){
    ESP_LOGI(TAG,"\n╔════════════════════════════════════════╗");
    ESP_LOGI(TAG,"║    AGUADA - sen_aguada_v1             ║");
    ESP_LOGI(TAG,"║    Payload agregado único             ║");
    ESP_LOGI(TAG,"╚════════════════════════════════════════╝\n");

    init_gpio();
    for(int i=0;i<2;i++){ gpio_set_level(LED_STATUS,1); vTaskDelay(pdMS_TO_TICKS(200)); gpio_set_level(LED_STATUS,0); vTaskDelay(pdMS_TO_TICKS(200)); }
    init_espnow();

    xTaskCreate(aggregate_task,"aggregate",4096,NULL,5,NULL);
    xTaskCreate(heartbeat_task,"heartbeat",2048,NULL,3,NULL);

    ESP_LOGI(TAG,"✓ Sistema pronto (sen_aguada_v1)");
}
