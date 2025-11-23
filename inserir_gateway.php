<?php
// inserir_gateway.php - recebe pacotes JSON individuais ou lista vindos do gateway (ESP-NOW / MQTT bridge)
// Cada objeto segue formato:
// {"mac":"dc:06:..","type":"IE01_distance_cm","value":25480,"battery":5000,"uptime":120,"rssi":-50}
// Tipos tratados:
//  * *_distance_cm  (distância em centésimos de cm) -> gera leitura em tabela leituras (dist, volume, percentual)
//  * *_valve_in / *_valve_out / *_sound_in -> persiste em estado_componentes
//  * distance_cm (sem prefixo) para nós simples (mapeados por MAC)
// Após cada batch, recalcula resumo_diario para nodes afetados (consumo/abastecimento + médias + componentes)

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if($mysqli->connect_errno){ http_response_code(500); die(json_encode(['status'=>'error','msg'=>'Erro DB'])); }

// Garantir colunas novas (idempotente)
$mysqli->query("ALTER TABLE resumo_diario ADD COLUMN volume_medio DECIMAL(10,2) NULL");
$mysqli->query("ALTER TABLE resumo_diario ADD COLUMN percentual_medio DECIMAL(5,2) NULL");
$mysqli->query("ALTER TABLE resumo_diario ADD COLUMN componentes_json TEXT NULL");

// Garantir tabela componentes
$mysqli->query("CREATE TABLE IF NOT EXISTS estado_componentes ( id INT AUTO_INCREMENT PRIMARY KEY, node VARCHAR(16) NOT NULL, componente VARCHAR(32) NOT NULL, valor TINYINT NOT NULL, ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(node), INDEX(componente) )");

// Carrega definição de reservatórios para cálculo dinâmico
$reservoirsPath = __DIR__ . '/firmware/config/reservoirs.json';
$reservoirsData = [];
if(file_exists($reservoirsPath)){
    $jsonRes = json_decode(file_get_contents($reservoirsPath), true);
    if(isset($jsonRes['reservoirs'])){
        foreach($jsonRes['reservoirs'] as $r){
            $reservoirsData[$r['id']] = $r;
        }
    }
}

// Mapeamentos necessários (node -> reservatório e MAC -> node)
$nodeToReservoir = [
    'Nano_01' => 'RCAV',
    'Nano_02' => 'RCON',
    'Nano_03' => 'IE01',
    'Nano_04' => 'IE02',
    'Nano_05' => 'RB03'
];
$macToNode = [
    // preencher conforme deployment real
    'dc:06:75:67:6a:cc' => 'Nano_01', // incêndio
    '20:6e:f1:6b:77:58' => 'Nano_02'  // consumo
];

function calcularVolumePercentual($reservoirId, $distancia_cm, $reservoirsData){
    if(!$reservoirId || !isset($reservoirsData[$reservoirId])) return [null,null];
    $r = $reservoirsData[$reservoirId];
    $tipo = $r['tipo'];
    $dim = $r['dimensoes'];
    $calc = isset($r['calculos']) ? $r['calculos'] : [];
    $hsensor = isset($dim['hsensor_cm']) ? $dim['hsensor_cm'] : 0; // altura entre topo e sensor
    // nível da água em cm
    $alturaTotal = isset($dim['altura_cm']) ? $dim['altura_cm'] : 0;
    $nivel_cm = $alturaTotal - ($distancia_cm - $hsensor);
    if($nivel_cm < 0) $nivel_cm = 0; if($nivel_cm > $alturaTotal) $nivel_cm = $alturaTotal;
    $volume_m3 = null; $percentual = null;
    if($tipo === 'cilindrico'){
        // usar raio calculado ou diametro
        $raio_m = isset($calc['raio_m']) ? $calc['raio_m'] : ((isset($dim['diametro_cm'])?$dim['diametro_cm']:0)/200);
        $volume_m3 = M_PI * $raio_m * $raio_m * ($nivel_cm/100);
        $volume_max = isset($calc['volume_max_m3']) ? $calc['volume_max_m3'] : $volume_m3;
        $percentual = $volume_max>0 ? ($volume_m3/$volume_max)*100 : null;
    } elseif($tipo === 'retangular') {
        $compr = isset($dim['comprimento_cm']) ? $dim['comprimento_cm']/100 : 0;
        $larg = isset($dim['largura_cm']) ? $dim['largura_cm']/100 : 0;
        $volume_m3 = $compr * $larg * ($nivel_cm/100);
        $volume_max = isset($calc['volume_max_m3']) ? $calc['volume_max_m3'] : $volume_m3;
        $percentual = $volume_max>0 ? ($volume_m3/$volume_max)*100 : null;
    }
    return [round($volume_m3,3), round($percentual,2)];
}

// Lê payload (array ou objeto)
$raw = file_get_contents('php://input');
if(!$raw){ http_response_code(400); echo json_encode(['status'=>'error','msg'=>'payload vazio']); exit; }

$payload = json_decode($raw, true);
if($payload===null){ http_response_code(400); echo json_encode(['status'=>'error','msg'=>'JSON inválido']); exit; }

// Normaliza para array
if(isset($payload['type'])){ $payload = [$payload]; }
if(!is_array($payload)){ http_response_code(400); echo json_encode(['status'=>'error','msg'=>'Formato não suportado']); exit; }

$nodesAfetados = [];
$agoraData = date('Y-m-d');

foreach($payload as $pkt){
    $type = isset($pkt['type']) ? $pkt['type'] : null;
    $value = isset($pkt['value']) ? $pkt['value'] : null;
    $mac = isset($pkt['mac']) ? strtolower($pkt['mac']) : null;
    if(!$type || $value===null){ continue; }

    // Determinar node
    $node = null;
    // Prefixos IE01 / IE02 etc
    if(str_starts_with($type,'IE01_')) $node = 'Nano_03';
    elseif(str_starts_with($type,'IE02_')) $node = 'Nano_04';
    elseif(str_ends_with($type,'_distance_cm')){
        // fallback já coberto pelos prefixos acima
    } elseif($type==='distance_cm' && $mac && isset($macToNode[$mac])) {
        $node = $macToNode[$mac];
    }
    // Para tipos valve/sound usar prefixo mesmo
    if(!$node && $mac && isset($macToNode[$mac])) $node = $macToNode[$mac];
    if(!$node) continue; // ignorar se não mapeado

    $nodesAfetados[$node] = true;
    $reservoirId = $nodeToReservoir[$node] ?? null;

    if(str_contains($type,'distance_cm')){
        // value é distancia *100
        $dist_cm = $value/100.0;
        list($volume_m3,$percentual) = calcularVolumePercentual($reservoirId, $dist_cm, $reservoirsData);
        // Inserir em leituras (temp/hum zeros por não existir aqui)
        $stmt = $mysqli->prepare("INSERT INTO leituras(node,temp,hum,dist,volume,percentual) VALUES(?,?,?,?,?,?)");
        $temp=0; $hum=0;
        $stmt->bind_param('sddddd',$node,$temp,$hum,$dist_cm,$volume_m3,$percentual);
        $stmt->execute();
        $stmt->close();
    } else {
        // componente (valve / sound)
        $componente = $type; // manter label original
        $valor = intval($value);
        $stmt = $mysqli->prepare("INSERT INTO estado_componentes(node,componente,valor) VALUES(?,?,?)");
        $stmt->bind_param('ssi',$node,$componente,$valor);
        $stmt->execute();
        $stmt->close();
    }
}

// Recalcula resumo_diario para nodes afetados (similar a inserir_leitura + médias + componentes)
foreach(array_keys($nodesAfetados) as $node){
    $stmt = $mysqli->prepare("SELECT volume, percentual FROM leituras WHERE node=? AND DATE(ts)=? ORDER BY ts ASC");
    $stmt->bind_param('ss',$node,$agoraData);
    $stmt->execute();
    $res = $stmt->get_result();
    $consumo=0;$abastecimento=0;$ultimoVol=null;$volume_final=0;$percentual_final=0;
    $somaVol=0;$somaPerc=0;$count=0;
    while($row=$res->fetch_assoc()){
        if($ultimoVol!==null){
            $delta=$row['volume']-$ultimoVol;
            if($delta<0) $consumo+=abs($delta);
            elseif($delta>0) $abastecimento+=$delta;
        }
        $ultimoVol=$row['volume'];
        $volume_final=$row['volume'];
        $percentual_final=$row['percentual'];
        $somaVol+=$row['volume'];
        $somaPerc+=$row['percentual'];
        $count++;
    }
    $res->free(); $stmt->close();
    $volume_medio = $count>0 ? $somaVol/$count : null;
    $percentual_medio = $count>0 ? $somaPerc/$count : null;

    // Componentes mais recentes (último estado por componente hoje)
    $compStmt = $mysqli->prepare("SELECT componente, valor FROM estado_componentes WHERE node=? AND DATE(ts)=? ORDER BY ts DESC");
    $compStmt->bind_param('ss',$node,$agoraData);
    $compStmt->execute();
    $compRes = $compStmt->get_result();
    $compEstados = [];
    while($c=$compRes->fetch_assoc()){
        if(!isset($compEstados[$c['componente']])){ // primeiro encontrado (mais recente)
            $compEstados[$c['componente']] = $c['valor'];
        }
    }
    $compRes->free(); $compStmt->close();
    $componentes_json = json_encode($compEstados);

    $stmt2 = $mysqli->prepare("INSERT INTO resumo_diario (data,node,consumo,abastecimento,volume_final,percentual_final,volume_medio,percentual_medio,componentes_json) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE consumo=?, abastecimento=?, volume_final=?, percentual_final=?, volume_medio=?, percentual_medio=?, componentes_json=?, atualizado_em=NOW()");
    $stmt2->bind_param('ssdddddddsddddddss',
        $agoraData,$node,$consumo,$abastecimento,$volume_final,$percentual_final,$volume_medio,$percentual_medio,$componentes_json,
        $consumo,$abastecimento,$volume_final,$percentual_final,$volume_medio,$percentual_medio,$componentes_json
    );
    $stmt2->execute();
    $stmt2->close();
}

echo json_encode(['status'=>'ok','nodes'=>array_keys($nodesAfetados),'count'=>count($payload)]);
$mysqli->close();
?>