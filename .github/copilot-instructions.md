# xAguada - Instruções para Agentes de Código (AI)

Falar sempre em português (pt-BR).

## Guia Rápido (Use Antes de Codar)
**Escopo**: Monitoramento hidráulico (reservatórios/cisternas) integrando firmware (Arduino / ESP32-C3) + backend PHP/MySQL + dashboards PHP.
**Fluxo Core**: Sensor → distância (cm) → volume (m³) → percentual → POST (`inserir_leitura.php`) → cálculo consumo/abastecimento → dashboards.
**Tabelas**: `leituras` (raw) e `resumo_diario` (agregado diário via UPSERT).
**Arquivos-chave**: Backend (`inserir_leitura.php`, `processar_resumo.php`, `painel2.php`), Fórmulas/tanques (`referencias.txt`), Regras (`regras painel aguada.txt`), Firmware (`firmware/**`), Arduino template (`NANO1_castelo_uncendio.ino`).
**Mapeamento Nomes**: Ver bloco "Sensor Mapping" abaixo para não quebrar dashboards.
**Cores Painel** (%): >60 verde, 40–60 amarelo, <40 vermelho (não alterar sem alinhar regra visual).
**Recalcular Diário**: uso de `processar_resumo.php` (não duplicar lógica em outro script).
**Mock/Sandbox**: `inserir_demo.php` gera leituras sintéticas; usar antes de alterar lógica de agregação.
**Não Fazer**: 1) Alterar fórmula de volume sem atualizar dimensões em firmware e `referencias.txt`. 2) Renomear `NODE_NAME` sem refletir mapeamento. 3) Introduzir libs JS grandes (gráficos usam Canvas vanilla).
**Padrões PHP**: Estilo procedural simples; manter validação mínima + evitar excesso de OO. Sanitizar entrada JSON só se mudar formato.
**Payload Esperado (Arduino)**: distancia(cm), volume(m3), percentual → POST JSON (temp/hum podem ser 0). Para ESP32-C3 nodes: observar telemetria MQTT/ESP-NOW separada.
**Firmware Build (ESP-IDF)**: `idf.py set-target esp32c3` → `idf.py build` → `idf.py -p <porta> flash monitor`. Ajustar parâmetros em `config.h` / `config_pins.h` (não hardcode em `main.c`).
**Filtros de Sensor**: Mediana 11 amostras (ESP32) / intervalos 5–10s; manter se ajustar porque dashboards assumem suavização básica.
**Consumo vs Abastecimento**: Diferenças de volume: negativo soma em consumo; positivo soma em abastecimento. Não misturar sinais.
**Expansões**: Novos sensores: adicionar no mapeamento, ajustar tanque (altura/diâmetro), garantir UNIQUE(data,node) continua válido.
**Debug Rápido**: Usar `curl http://localhost/xaguada/inserir_demo.php` e inspecionar cartões em `painel2.php`.
**Performance Sensível**: Evitar loops pesados no PHP em cada request; agregação diária já recalcula tudo—otimize só se volume de leituras crescer muito.
**Falhas Comuns**: IP duplicado (corrigir sketch), volume incoerente (checar offset + constante), ausência de dados (ver conectividade/MQTT fallback HTTP).
**Checklist Antes de Commit**: 1) Não quebrou nomes de nodes. 2) Fórmulas coerentes. 3) Mock ainda funciona. 4) Painel carrega sem erros JS.

## Referência Completa (Original)
## Project Overview
IoT hydraulic asset management system for CMASM (Centro de Mísseis e Armas Submarinas da Marinha). Monitors water reservoirs using Arduino Nano sensors with ultrasonic distance measurements, sending data via Ethernet (ENC28J60) to a PHP/MySQL backend running on XAMPP.

## Architecture

### Hardware Layer (Arduino Nano)
- **Sensor nodes**: `Nano_01` through `Nano_05C` (7 total)
- **Components**: HC-SR04 ultrasonic sensor, ENC28J60 Ethernet shield
- **Data flow**: Distance → Volume → Percentage → HTTP POST to `inserir_leitura.php`
- **Network**: Fixed IPs on 192.168.10.x/192.168.0.x subnet
- **Update frequency**: 5-second intervals (configurable in Arduino code)

### Backend Layer (PHP/MySQL)
- **Database**: `sensores_db` with two tables:
  - `leituras`: raw sensor readings (id, node, ts, temp, hum, dist, volume, percentual)
  - `resumo_diario`: daily aggregates (data, node, consumo, abastecimento, volume_final, percentual_final)
- **API endpoint**: `inserir_leitura.php` accepts JSON POST with node data
- **Auto-calculation**: Consumption (sum of volume drops) and supply (sum of volume increases) computed on insert

### Frontend Layer
- **Main dashboard**: `painel2.php` - production interface with cards, graphs, tables
- **Simple monitor**: `painel.php` - basic readings view
- **Reports**: `relatorio_consumo.php` - filtered daily consumption analysis
- **Auth**: HTTP Basic Auth (credentials in `config.php`)

## Sensor Mapping (Critical Reference)
```php
"Nano_01" => "Res. Incêndio"       // 80t fire combat system elevated reservoir  
"Nano_02" => "Res. Consumo"        // 80t consumption elevated reservoir
"Nano_03" => "Cisterna IE01"       // 250t cistern
"Nano_04" => "Cisterna IE02"       // 250t cistern
"Nano_05"=> "Res. Bombas IE03"    // Pump aux reservoir
"Nano_06"=> "Cisterna IF01"       // Cistern IF01
"Nano_07"=> "Cisterna IF02"       // Cistern IF02
```

## Key Conventions

### Volume Calculations (Arduino)
```cpp
// Constants defined per sensor
float hsensor=0.2, hcxagua=4.67, diametro=5.10;
volume = (hcxagua - hsensor - distM) * π * (diametro/2)²
percentual = (volume / volTotal) * 100
```
**Important**: Each sensor may have different tank dimensions - check Arduino sketch before modifying.

### Consumption/Supply Logic
- **Consumption**: Sum of negative deltas (volume drops) throughout the day
- **Supply**: Sum of positive deltas (volume increases) throughout the day
- **Calculation**: On every insert via `inserir_leitura.php`, entire day is recalculated from `leituras` table
- **Storage**: Saved to `resumo_diario` with UPSERT pattern (`ON DUPLICATE KEY UPDATE`)

### Card Color Rules (painel2.php)
- Green: capacity > 60%
- Yellow: 40% ≤ capacity ≤ 60%
- Red: capacity < 40%

### DateTime Format
- Display format: `dHHMM` (e.g., "301745" = day 30, 17:45)
- Database: MySQL TIMESTAMP (default CURRENT_TIMESTAMP)

## Development Workflows

### Local Setup (XAMPP)
1. Start Apache + MySQL in XAMPP Control Panel
2. Import `database.sql` to create `sensores_db`
3. Configure `config.php` with DB credentials
4. Access via `http://localhost/xaguada/painel2.php`

### Testing with Mock Data
```bash
# Generate demo readings
curl http://localhost/xaguada/inserir_demo.php
```

### Arduino Deployment
1. Update MAC address (must be unique per node)
2. Set fixed IP in sketch (`IPAddress ip(...)`)
3. Configure server IP to match XAMPP host
4. Verify `NODE_NAME` matches database convention
5. Flash via Arduino IDE (requires ENC28J60 library)

### Recalculating Historical Data
```bash
# Rebuild resumo_diario from leituras
curl http://localhost/xaguada/processar_resumo.php
```

## Data Flow Example
```
Arduino HC-SR04 (distance 245.3cm)
  ↓ calcularVolume()
Volume: 67.8 m³ / Percentual: 84.8%
  ↓ HTTP POST JSON
inserir_leitura.php
  ↓ INSERT INTO leituras
  ↓ Calculate day's consumption/supply
  ↓ UPSERT resumo_diario
painel2.php renders card (green background)
```

## Graphics Implementation
- **Library**: Vanilla JavaScript Canvas API (no external libs)
- **Types**: Vertical bar charts, line graphs with moving average
- **Moving average**: 5-point window (`janela=5`)
- **Update**: Auto-refresh every 10s via AJAX polling (painel.php)

## Common Pitfalls
- **Volume calculation mismatch**: Arduino formulas must match tank specs in `referencias.txt`
- **Network IP conflicts**: Each Arduino needs unique IP; server IP must match XAMPP host
- **Timezone issues**: PHP uses server timezone; Arduino sends data immediately (no RTC)
- **Duplicate key errors**: `resumo_diario` uses UNIQUE(data, node) - this is intentional for UPSERT
- **Missing temp/hum**: Current sensors don't have DHT22; set to 0 in POST data

## File Reference
- **Production dashboards**: `painel2.php`, `relatorio_consumo.php`
- **API endpoint**: `inserir_leitura.php` (accepts POST JSON)
- **Batch processing**: `processar_resumo.php` (rebuild aggregates)
- **Arduino template**: `NANO1_castelo_uncendio/NANO1_castelo_uncendio.ino`
- **Business rules**: `regras painel aguada.txt` (Portuguese spec)
- **System topology**: `referencias.txt` (detailed infrastructure)

## Portuguese-Specific Terms
- **Consumo**: Consumption (water usage)
- **Abastecimento**: Supply (water refill)
- **Capacidade**: Capacity (percentage full)
- **Reservatório/Cisterna**: Reservoir/Cistern (water tank)
- **Leituras**: Readings (sensor data)
