<?php
// Script para popular resumo_diario baseado nos dados de leituras
require_once __DIR__ . '/config.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) die("Erro DB: ".$mysqli->connect_error);

// Buscar todos os nodes e datas distintas
$result = $mysqli->query("SELECT DISTINCT node, DATE(ts) as data FROM leituras ORDER BY data DESC, node");
$processados = 0;

while($row = $result->fetch_assoc()) {
    $node = $row['node'];
    $data = $row['data'];
    
    // Buscar todas as leituras do node nesta data
    $stmt = $mysqli->prepare("SELECT volume, percentual FROM leituras WHERE node=? AND DATE(ts)=? ORDER BY ts ASC");
    $stmt->bind_param("ss", $node, $data);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $consumo = 0;
    $abastecimento = 0;
    $ultimoVol = null;
    $volume_final = 0;
    $percentual_final = 0;
    
    while($l = $res->fetch_assoc()) {
        if($ultimoVol !== null) {
            $delta = $l['volume'] - $ultimoVol;
            if($delta < 0) $consumo += abs($delta);
            if($delta > 0) $abastecimento += $delta;
        }
        $ultimoVol = $l['volume'];
        $volume_final = $l['volume'];
        $percentual_final = $l['percentual'];
    }
    $res->free();
    $stmt->close();
    
    // Inserir/atualizar resumo_diario
    $stmt2 = $mysqli->prepare(
        "INSERT INTO resumo_diario (data,node,consumo,abastecimento,volume_final,percentual_final) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE consumo=?, abastecimento=?, volume_final=?, percentual_final=?, atualizado_em=NOW()"
    );
    $stmt2->bind_param("ssdddddddd", 
        $data, $node, $consumo, $abastecimento, $volume_final, $percentual_final,
        $consumo, $abastecimento, $volume_final, $percentual_final
    );
    $stmt2->execute();
    $stmt2->close();
    
    $processados++;
}

$mysqli->close();
echo "Processados $processados registros de resumo_diario";
?>
