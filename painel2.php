<?php
// painel2.php - Painel com cards, gráfico e tabela diária + salva consumo/abastecimento
require_once __DIR__ . '/config.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    die("Erro DB: " . $mysqli->connect_error);
}

// Garantir criação da tabela leituras (se não existir)
$mysqli->query("CREATE TABLE IF NOT EXISTS leituras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  node VARCHAR(16),
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  temp FLOAT,
  hum FLOAT,
  dist FLOAT,
  volume FLOAT,
  percentual FLOAT
)");

// Garantir criação da tabela consumo_abastecimento
$mysqli->query("CREATE TABLE IF NOT EXISTS consumo_abastecimento (
  id INT AUTO_INCREMENT PRIMARY KEY,
  node VARCHAR(16),
  data DATE,
  consumo FLOAT,
  abastecimento FLOAT,
  UNIQUE(node, data)
)");

$hoje = date("Y-m-d");
$dataLegivel = date("d/m/Y");

$res = $mysqli->query("SELECT * FROM leituras WHERE DATE(ts)='$hoje' ORDER BY ts ASC");
$leituras = [];
while ($row = $res->fetch_assoc()) $leituras[] = $row;
$res->free();

function calcularConsumoAbastecimento($leiturasNode) {
    $consumo=0; $abastecimento=0;
    for ($i=1; $i<count($leiturasNode); $i++) {
        $diff = $leiturasNode[$i]['volume'] - $leiturasNode[$i-1]['volume'];
        if ($diff < 0) $consumo += abs($diff);
        if ($diff > 0) $abastecimento += $diff;
    }
    return ["consumo"=>$consumo,"abastecimento"=>$abastecimento];
}

$nomes = [
  "Nano_02" => "Res. Consumo",
  "Nano_03" => "Cisterna IE01",
  "Nano_04" => "Cisterna IE02",
  "Nano_01" => "Res. Incêndio",
  "Nano_05A"=> "Res. Bombas IE03",
  "Nano_05B"=> "Cisterna IF01",
  "Nano_05C"=> "Cisterna IF02"
];

$porNode = [];
foreach ($leituras as $l) {
    $porNode[$l['node']][] = $l;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>CMASM Aguada</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f0f2f5; }
h1 { color: #333; margin-bottom:5px; }
h2 { margin-top:40px; }
.subtitulo { font-size:14px; color:#555; margin-bottom:20px; }

.cards { display: flex; flex-wrap: wrap; gap: 20px; }
.card {
  flex: 1 1 250px;
  background: white; border-radius: 10px; padding: 15px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.card h3 { margin: 0 0 10px; text-align:center; }
.linha1 { font-size: 18px; font-weight:bold; text-align:center; }
.linha2 { font-size:14px; text-align:center; margin-top:10px; }
.footer { font-size:11px; text-align:right; color:#555; margin-top:10px; }

.verde { background:#d4edda; }
.amarelo { background:#fff3cd; }
.vermelho { background:#f8d7da; }

table { border-collapse: collapse; width:100%; margin-top:20px; background:white; }
th,td { border:1px solid #ccc; padding:6px 8px; text-align:center; font-size:13px; }
th { background:#eee; }
canvas { border:1px solid #888; background:#fff; margin-top:20px; }
.legenda { margin-top:5px; font-size:13px; }
.legenda span { display:inline-block; margin-right:15px; }
.linhaAzul { width:20px; height:2px; background:blue; display:inline-block; vertical-align:middle; margin-right:5px; }
.linhaVermelha { width:20px; height:2px; background:red; display:inline-block; vertical-align:middle; margin-right:5px; border-bottom:1px dashed red; }
</style>
</head>
<body>

<h1>CMASM Aguada</h1>
<div class="subtitulo">- Informações sobre recursos hídricos do dia <?= $dataLegivel ?> -</div>

<div class="cards">
<?php
$primeira_linha = ["Nano_02", "Nano_01", "Nano_05A"];
foreach ($primeira_linha as $node) {
    if (!isset($nomes[$node])) continue;
    $nome = $nomes[$node];
    $dados = $porNode[$node] ?? [];
    $ultimo = end($dados);
    $consAbs = calcularConsumoAbastecimento($dados);
    $cap = isset($ultimo['percentual']) ? $ultimo['percentual'] : 0;
    $classe="verde"; if($cap<=60 && $cap>40) $classe="amarelo"; if($cap<=40) $classe="vermelho";
    $dh = isset($ultimo['ts']) ? date("dHi", strtotime($ultimo['ts'])) : "--";
    $stmt = $mysqli->prepare("
        INSERT INTO consumo_abastecimento(node,data,consumo,abastecimento)
        VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE consumo=VALUES(consumo), abastecimento=VALUES(abastecimento)
    ");
    $stmt->bind_param("ssdd", $node, $hoje, $consAbs['consumo'], $consAbs['abastecimento']);
    $stmt->execute();
?>
  <div class="card <?= $classe ?>">
    <h3><?= $nome ?></h3>
    <div class="linha1">
      Volume: <?= isset($ultimo['volume']) ? number_format($ultimo['volume'],1) : "--" ?> t<br>
      Capacidade: <?= isset($ultimo['percentual']) ? number_format($ultimo['percentual'],1) : "--" ?> %
    </div>
    <div class="linha2">
      Consumo: <?= number_format($consAbs['consumo'],1) ?> t<br>
      Abastecimento: <?= number_format($consAbs['abastecimento'],1) ?> t
    </div>
    <div class="footer">Data/Hora: <?= $dh ?></div>
  </div>
<?php $stmt->close(); } ?>
</div>
<div class="cards">
<?php
$segunda_linha = ["Nano_03", "Nano_04", "Nano_05B", "Nano_05C"];
foreach ($segunda_linha as $node) {
    if (!isset($nomes[$node])) continue;
    $nome = $nomes[$node];
    $dados = $porNode[$node] ?? [];
    $ultimo = end($dados);
    $consAbs = calcularConsumoAbastecimento($dados);
    $cap = isset($ultimo['percentual']) ? $ultimo['percentual'] : 0;
    $classe="verde"; if($cap<=60 && $cap>40) $classe="amarelo"; if($cap<=40) $classe="vermelho";
    $dh = isset($ultimo['ts']) ? date("dHi", strtotime($ultimo['ts'])) : "--";
    $stmt = $mysqli->prepare("
        INSERT INTO consumo_abastecimento(node,data,consumo,abastecimento)
        VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE consumo=VALUES(consumo), abastecimento=VALUES(abastecimento)
    ");
    $stmt->bind_param("ssdd", $node, $hoje, $consAbs['consumo'], $consAbs['abastecimento']);
    $stmt->execute();
?>
  <div class="card <?= $classe ?>">
    <h3><?= $nome ?></h3>
    <div class="linha1">
      Volume: <?= isset($ultimo['volume']) ? number_format($ultimo['volume'],1) : "--" ?> t<br>
      Capacidade: <?= isset($ultimo['percentual']) ? number_format($ultimo['percentual'],1) : "--" ?> %
    </div>
    <div class="linha2">
      Consumo: <?= number_format($consAbs['consumo'],1) ?> t<br>
      Abastecimento: <?= number_format($consAbs['abastecimento'],1) ?> t
    </div>
    <div class="footer">Data/Hora: <?= $dh ?></div>
  </div>
<?php $stmt->close(); } $mysqli->close(); ?>
</div>

<h2>📈 Gráfico Capacidade do Dia</h2>
<canvas id="grafico" width="800" height="300"></canvas>
<div class="legenda">
  <span><span class="linhaAzul"></span>Real</span>
  <span><span class="linhaVermelha"></span>Média Móvel</span>
</div>

<h2>📊 Últimas 20 Leituras</h2>
<table>
<tr>
  <th>ID</th><th>Nó</th><th>Data/Hora</th>
  <th>Temp (°C)</th><th>Umid (%)</th><th>Dist (cm)</th>
  <th>Volume (t)</th><th>Cap (%)</th>
</tr>
<?php foreach (array_slice(array_reverse($leituras),0,20) as $l): ?>
<tr>
  <td><?= $l['id'] ?></td>
  <td><?= $l['node'] ?></td>
  <td><?= $l['ts'] ?></td>
  <td><?= number_format($l['temp'],1) ?></td>
  <td><?= number_format($l['hum'],1) ?></td>
  <td><?= number_format($l['dist'],1) ?></td>
  <td><?= number_format($l['volume'],1) ?></td>
  <td><?= number_format($l['percentual'],1) ?></td>
</tr>
<?php endforeach; ?>
</table>

<script>
// Gráfico capacidade diária (últimas leituras)
const dados = <?= json_encode(array_reverse($leituras)) ?>;
const ctx = document.getElementById("grafico").getContext("2d");
const W=ctx.canvas.width,H=ctx.canvas.height;
ctx.clearRect(0,0,W,H);

const margin=40;
const plotW=W-margin*2, plotH=H-margin*2;
const series=dados.map(d=>parseFloat(d.percentual));

if(series.length>0){
  const maxY=100,minY=0;
  function y2px(y){ return H-margin-((y-minY)/(maxY-minY))*plotH; }
  function x2px(i){ return margin + (series.length>1 ? (i/(series.length-1))*plotW : plotW/2); }

  // eixos
  ctx.strokeStyle="#000"; 
  ctx.beginPath();
  ctx.moveTo(margin,margin); ctx.lineTo(margin,H-margin); 
  ctx.lineTo(W-margin,H-margin); 
  ctx.stroke();

  // curva real (azul)
  ctx.strokeStyle="blue"; ctx.beginPath();
  series.forEach((v,i)=>{
    const x=x2px(i), y=y2px(v);
    if(i==0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
  });
  ctx.stroke();

  // curva média móvel (vermelha tracejada)
  const janela=5;
  const media=[];
  for(let i=0;i<series.length;i++){
    let ini=Math.max(0,i-janela+1);
    let sub=series.slice(ini,i+1);
    let m=sub.reduce((a,b)=>a+b,0)/sub.length;
    media.push(m);
  }

  ctx.strokeStyle="red"; ctx.setLineDash([5,5]);
  ctx.beginPath();
  media.forEach((v,i)=>{
    const x=x2px(i), y=y2px(v);
    if(i==0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
  });
  ctx.stroke();
  ctx.setLineDash([]);
}
</script>

</body>
</html>

