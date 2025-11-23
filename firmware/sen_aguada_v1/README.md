# AGUADA - sen_aguada_v1

Versão derivada de `node_sensor_10` que envia um único JSON agregado por ciclo (30s) ao invés de múltiplos pacotes separados.

## Formato do Payload
```json
{
  "mac": "AA:BB:CC:DD:EE:FF",
  "type": "distancia_cm",
  "distancia_cm": 245.32,
  "nivel_cm": 221.00,
  "nivel_max_cm": 447.00,
  "volume_max_m3": 91.234,
  "volume_m3": 45.612,
  "percentual": 50.01,
  "som": 1,
  "vdc": 0.00,
  "temp_c": 0.00,
  "rssi": -55
}
```

## Cálculos
- `nivel_max_cm = TANK_HEIGHT_CM - SENSOR_OFFSET_CM`
- `nivel_cm = nivel_max_cm - distancia_cm`
- `base_area = π * (diâmetro/2)^2`
- `volume_m3 = (nivel_cm / 100) * base_area`
- `percentual = volume_m3 / volume_max_m3 * 100`

## Ajuste de Parâmetros
Em `config.h`:
```c
#define TANK_HEIGHT_CM    467.0f
#define SENSOR_OFFSET_CM  20.0f
#define TANK_DIAMETER_M   5.10f
```
Atualize conforme cada reservatório (se múltiplos, será necessário futuramente mapeamento por MAC).

## Limitações
- `vdc` e `temp_c` placeholders (0.00) até adicionar hardware/ADC.
- Sem retry/fila (se ESP-NOW falhar, pacote se perde).
- Som (campo `som`) reflete nível lógico em `SOUND_IN_PIN`.

## Build / Flash
```bash
idf.py set-target esp32c3
idf.py build
idf.py -p <porta> flash monitor
```

## Próximos Passos Sugeridos
1. Implementar leitura real de tensão (ADC) e temperatura.
2. Adicionar compressão / deadband para reduzir tráfego.
3. Múltiplos perfis de tanque via tabela MAC→dimensões.
