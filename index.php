<?php
// index.php - painel único simplificado com níveis + controles de válvulas/bombas
require_once __DIR__ . '/config.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if($mysqli->connect_errno) die('Erro DB: '.$mysqli->connect_error);

$hoje = date('Y-m-d');

// Últimas leituras por node
$stmt = $mysqli->prepare("SELECT node, dist, volume, percentual, ts FROM leituras WHERE DATE(ts)=? ORDER BY ts DESC");
$stmt->bind_param('s',$hoje); $stmt->execute(); $res=$stmt->get_result();
$ultimaLeitura = [];
while($r=$res->fetch_assoc()){ if(!isset($ultimaLeitura[$r['node']])) $ultimaLeitura[$r['node']]=$r; }
$res->free(); $stmt->close();

// Estados atuais de componentes
$res2 = $mysqli->query("SELECT node, componente, valor FROM estado_atual ORDER BY node, componente");
$estados = [];
while($r=$res2->fetch_assoc()){ $estados[$r['node']][$r['componente']] = $r['valor']; }
$res2->free();

$mysqli->close();

$nomes = [
 'Nano_01' => 'Res. Incêndio',
 'Nano_02' => 'Res. Consumo',
 'Nano_03' => 'Cisterna IE01',
 'Nano_04' => 'Cisterna IE02',
 'Nano_05' => 'Res. Bombas IE03'
];

function corCapacidade($perc){
    if($perc>60) return '#d4edda';
    if($perc>=40) return '#fff3cd';
    return '#f8d7da';
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8"/>
<title>Aguada - Painel Local</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Arial,sans-serif;background:#f0f2f5;padding:20px}
h1{color:#333;margin-bottom:20px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px}
.card{background:#fff;border-radius:8px;padding:14px;box-shadow:0 2px 6px rgba(0,0,0,.15)}
.card h2{font-size:16px;margin-bottom:10px;text-align:center;color:#333}
.nivel{padding:10px;border-radius:6px;margin-bottom:10px;text-align:center}
.nivel div{margin:4px 0;font-size:14px}
.nivel .big{font-size:18px;font-weight:700}
.controles{display:flex;flex-direction:column;gap:6px}
.linha{display:flex;justify-content:space-between;align-items:center;padding:6px 8px;background:#f9f9f9;border-radius:4px;font-size:12px}
.botoes{display:flex;gap:4px}
.btn{padding:4px 10px;border:none;border-radius:4px;cursor:pointer;font-size:11px;font-weight:600}
.btn-on{background:#28a745;color:#fff}
.btn-off{background:#dc3545;color:#fff}
.btn:hover{opacity:0.85}
.footer{margin-top:8px;font-size:11px;color:#666;text-align:right}
nav{margin-bottom:15px}
nav a{margin-right:15px;text-decoration:none;color:#0069d9;font-size:14px}
nav a:hover{text-decoration:underline}
</style>
</head>
<body>
<h1>🚰 Aguada - Painel de Controle</h1>
<nav>
  <a href="index.php">Painel</a>
  <a href="relatorio_horario.php">Relatório Horário</a>
  <a href="documentacao.php">Documentação</a>
</nav>

<div class="grid">
<?php foreach($nomes as $node=>$nome): 
  $leitura = $ultimaLeitura[$node] ?? null;
  $comp = $estados[$node] ?? [];
  $perc = $leitura ? $leitura['percentual'] : 0;
  $cor = corCapacidade($perc);
?>
  <div class="card">
    <h2><?= htmlspecialchars($nome) ?></h2>
    <div class="nivel" style="background:<?= $cor ?>">
      <?php if($leitura): ?>
        <div class="big"><?= number_format($leitura['volume'],1) ?> m³</div>
        <div>Capacidade: <?= number_format($leitura['percentual'],1) ?>%</div>
        <div>Distância: <?= number_format($leitura['dist'],1) ?> cm</div>
      <?php else: ?>
        <div class="big">-- m³</div>
        <div>Sem leitura hoje</div>
      <?php endif; ?>
    </div>
    
    <div class="controles">
      <!-- Válvula Entrada -->
      <div class="linha">
        <span>🚪 Válvula Entrada</span>
        <div class="botoes">
          <button class="btn btn-on" onclick="controlar('<?= $node ?>','valve_in',1)">ABRIR</button>
          <button class="btn btn-off" onclick="controlar('<?= $node ?>','valve_in',0)">FECHAR</button>
        </div>
      </div>
      <div style="font-size:11px;color:#555;padding-left:8px">
        Estado: <?= isset($comp['valve_in']) ? ($comp['valve_in']?'<b style="color:green">ABERTA</b>':'<b style="color:red">FECHADA</b>') : '--' ?>
      </div>
      
      <!-- Válvula Saída -->
      <div class="linha">
        <span>🚪 Válvula Saída</span>
        <div class="botoes">
          <button class="btn btn-on" onclick="controlar('<?= $node ?>','valve_out',1)">ABRIR</button>
          <button class="btn btn-off" onclick="controlar('<?= $node ?>','valve_out',0)">FECHAR</button>
        </div>
      </div>
      <div style="font-size:11px;color:#555;padding-left:8px">
        Estado: <?= isset($comp['valve_out']) ? ($comp['valve_out']?'<b style="color:green">ABERTA</b>':'<b style="color:red">FECHADA</b>') : '--' ?>
      </div>
      
      <!-- Bomba (se aplicável) -->
      <?php if(in_array($node,['Nano_05'])): ?>
      <div class="linha">
        <span>⚙️ Bomba</span>
        <div class="botoes">
          <button class="btn btn-on" onclick="controlar('<?= $node ?>','bomba',1)">LIGAR</button>
          <button class="btn btn-off" onclick="controlar('<?= $node ?>','bomba',0)">DESLIGAR</button>
        </div>
      </div>
      <div style="font-size:11px;color:#555;padding-left:8px">
        Estado: <?= isset($comp['bomba']) ? ($comp['bomba']?'<b style="color:green">LIGADA</b>':'<b style="color:gray">DESLIGADA</b>') : '--' ?>
      </div>
      <?php endif; ?>
    </div>
    
    <?php if($leitura): ?>
    <div class="footer"><?= date('d/m/Y H:i', strtotime($leitura['ts'])) ?></div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<script>
function controlar(node, componente, valor){
  fetch('controlar_componente.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({node, componente, valor, usuario:'web'})
  })
  .then(r=>r.json())
  .then(d=>{
    if(d.status==='ok'){
      alert('Comando enviado: '+componente+' = '+(valor?'ON':'OFF'));
      location.reload();
    } else {
      alert('Erro: '+d.msg);
    }
  })
  .catch(e=>alert('Erro de comunicação: '+e));
}

// Auto-refresh a cada 30s
setTimeout(()=>location.reload(), 30000);
</script>
</body>
</html>
