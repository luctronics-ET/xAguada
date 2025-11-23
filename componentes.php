<?php
// componentes.php - visão dos estados atuais de válvulas e detectores por node
require_once __DIR__ . '/config.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if($mysqli->connect_errno) die('Erro DB: '.$mysqli->connect_error);

// Garantir existência da tabela (idempotente)
$mysqli->query("CREATE TABLE IF NOT EXISTS estado_componentes (\n  id INT AUTO_INCREMENT PRIMARY KEY,\n  node VARCHAR(16) NOT NULL,\n  componente VARCHAR(32) NOT NULL,\n  valor TINYINT NOT NULL,\n  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n  INDEX(node), INDEX(componente)\n)");

$hoje = date('Y-m-d');

// Último estado por componente (agrupado)
$sql = "SELECT node, componente, valor, MAX(ts) as ts FROM estado_componentes WHERE DATE(ts)=? GROUP BY node, componente ORDER BY node, componente";
$stmt = $mysqli->prepare($sql); $stmt->bind_param('s',$hoje); $stmt->execute(); $res = $stmt->get_result();
$estados = [];
while($r=$res->fetch_assoc()){ $estados[$r['node']][] = $r; }
$res->free(); $stmt->close();

// Últimas leituras de volume para referência de nível
$stmt2 = $mysqli->prepare("SELECT node, volume, percentual, ts FROM leituras WHERE DATE(ts)=? ORDER BY ts DESC");
$stmt2->bind_param('s',$hoje); $stmt2->execute(); $res2=$stmt2->get_result();
$nivel = [];
while($l=$res2->fetch_assoc()){ if(!isset($nivel[$l['node']])) $nivel[$l['node']]=$l; }
$res2->free(); $stmt2->close();

$mysqli->close();

$nomes = [
 'Nano_01' => 'Res. Incêndio',
 'Nano_02' => 'Res. Consumo',
 'Nano_03' => 'Cisterna IE01',
 'Nano_04' => 'Cisterna IE02',
 'Nano_05' => 'Res. Bombas IE03'
];

function classeValor($comp,$val){
    if(str_contains($comp,'valve')) return $val? 'aberta':'fechada';
    if(str_contains($comp,'sound')) return $val? 'som':'silencio';
    return $val? 'on':'off';
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8" />
<title>Estados de Componentes</title>
<style>
body{font-family:Arial;background:#f0f2f5;margin:20px}
h1{color:#333;margin-bottom:10px}
table{border-collapse:collapse;width:100%;background:#fff;margin-top:20px}
th,td{border:1px solid #ccc;padding:6px 8px;font-size:13px;text-align:center}
th{background:#eee}
.tag{display:inline-block;padding:2px 6px;border-radius:4px;font-size:11px;font-weight:600}
.aberta{background:#d4edda;color:#155724}
.fechada{background:#f8d7da;color:#721c24}
.som{background:#fff3cd;color:#856404}
.silencio{background:#d1ecf1;color:#0c5460}
.on{background:#cfe2ff;color:#084298}
.off{background:#e2e3e5;color:#41464b}
.volbox{font-size:12px;color:#555;margin-top:4px}
.grid{display:flex;flex-wrap:wrap;gap:18px;margin-top:10px}
.card{flex:1 1 260px;min-width:240px;background:#fff;padding:12px 14px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1)}
.card h3{margin:0 0 8px;font-size:16px;color:#333;text-align:center}
.compLinha{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #eee;font-size:12px}
.compLinha:last-child{border-bottom:none}
@media(max-width:700px){.grid{flex-direction:column}}
</style>
</head>
<body>
<h1>Estados de Componentes (Hoje)</h1>
<div class="grid">
<?php foreach($estados as $node=>$lista): $ninfo=$nivel[$node]??null; ?>
  <div class="card">
    <h3><?= htmlspecialchars($nomes[$node]??$node) ?></h3>
    <?php if($ninfo): ?>
      <div class="volbox">Volume: <?= number_format($ninfo['volume'],1) ?> m³ | Cap: <?= number_format($ninfo['percentual'],1) ?> %</div>
    <?php else: ?>
      <div class="volbox">Sem leitura hoje</div>
    <?php endif; ?>
    <?php foreach($lista as $c): $classe=classeValor($c['componente'],$c['valor']); ?>
      <div class="compLinha"><span><?= htmlspecialchars($c['componente']) ?></span><span class="tag <?= $classe ?>"><?= strtoupper($classe) ?></span></div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
</div>

<h2>Tabela Detalhada</h2>
<table>
 <tr><th>Nó</th><th>Componente</th><th>Valor</th><th>Último TS</th></tr>
 <?php foreach($estados as $node=>$lista): foreach($lista as $c): ?>
 <tr>
  <td><?= htmlspecialchars($node) ?></td>
  <td><?= htmlspecialchars($c['componente']) ?></td>
  <td><?= intval($c['valor']) ?></td>
  <td><?= htmlspecialchars($c['ts']) ?></td>
 </tr>
 <?php endforeach; endforeach; ?>
</table>
</body>
</html>