
<?php
require_once __DIR__ . '/config.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if($mysqli->connect_errno){ 
    die("Erro DB: ".$mysqli->connect_error); 
}

// garante que a tabela resumo_diario exista
$mysqli->query("CREATE TABLE IF NOT EXISTS resumo_diario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data DATE NOT NULL,
    node VARCHAR(16) NOT NULL,
    consumo DECIMAL(10,2) DEFAULT 0,
    abastecimento DECIMAL(10,2) DEFAULT 0,
    volume_final DECIMAL(10,2),
    percentual_final DECIMAL(5,2),
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (data, node)
)");

$data = json_decode(file_get_contents('php://input'), true);
if($data){
    // Validações básicas de tipos e ranges (evita dados corrompidos)
    $node = isset($data['node']) ? substr(trim($data['node']),0,16) : null;
    $temp = isset($data['temp']) ? floatval($data['temp']) : null;
    $hum = isset($data['hum']) ? floatval($data['hum']) : null;
    $dist = isset($data['dist']) ? floatval($data['dist']) : null;
    $volume = isset($data['volume']) ? floatval($data['volume']) : null;
    $percentual = isset($data['percentual']) ? floatval($data['percentual']) : null;

    if (!$node || $temp===null || $hum===null || $dist===null || $volume===null || $percentual===null) {
        http_response_code(400); echo json_encode(['status'=>'error','msg'=>'dados invalidos']); exit;
    }

    // insere leitura
    $stmt = $mysqli->prepare("INSERT INTO leituras(node,temp,hum,dist,volume,percentual) VALUES(?,?,?,?,?,?)");
    $stmt->bind_param("sddddd",$node,$temp,$hum,$dist,$volume,$percentual);
    $stmt->execute();
    $stmt->close();

    // atualiza resumo_diario
    $dataHoje = date("Y-m-d");

    $sql = "SELECT volume, percentual FROM leituras WHERE node=? AND DATE(ts)=? ORDER BY ts ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ss", $node, $dataHoje);
    $stmt->execute();
    $res = $stmt->get_result();

    $consumo=0; $abastecimento=0; $ultimoVol=null; 
    $percentual_final=0; $volume_final=0;
    while($row=$res->fetch_assoc()){
        if($ultimoVol!==null){
            $delta = $row['volume'] - $ultimoVol;
            if($delta<0) $consumo += abs($delta);
            if($delta>0) $abastecimento += $delta;
        }
        $ultimoVol = $row['volume'];
        $volume_final = $row['volume'];
        $percentual_final = $row['percentual'];
    }
    $res->free(); $stmt->close();

    $stmt2 = $mysqli->prepare(
      "INSERT INTO resumo_diario (data,node,consumo,abastecimento,volume_final,percentual_final) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE consumo=?, abastecimento=?, volume_final=?, percentual_final=?, atualizado_em=NOW()"
    );
    $stmt2->bind_param("ssdddddddd", 
        $dataHoje,$node,$consumo,$abastecimento,$volume_final,$percentual_final,
        $consumo,$abastecimento,$volume_final,$percentual_final
    );
    $stmt2->execute();
    $stmt2->close();

    echo json_encode(["status"=>"ok"]);
} else {
    http_response_code(400); echo json_encode(["status"=>"error","msg"=>"JSON ausente ou inválido"]);
}
$mysqli->close();
?>
?>

