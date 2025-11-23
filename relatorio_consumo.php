

<?php
// relatorio_consumo.php - Painel atualizado conforme regras
require_once __DIR__ . '/config.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) die("Erro DB: ".$mysqli->connect_error);

// HTTP Basic Auth para o painel
if (!isset($_SERVER['PHP_AUTH_USER'])) {
  header('WWW-Authenticate: Basic realm="Painel"');
  header('HTTP/1.0 401 Unauthorized');
  echo 'Autenticação requerida.';
  exit;
} else {
  if ($_SERVER['PHP_AUTH_USER'] !== ADMIN_USER || $_SERVER['PHP_AUTH_PW'] !== ADMIN_PASS) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Acesso negado.';
    exit;
  }
}

$filtro_data = $_GET['data'] ?? date("Y-m-d");

// Mapeamento dos sensores para nomes amigáveis

$sensor_nomes = [
  'Nano_02' => 'Res. Consumo',
  'Nano_03' => 'Cisterna IE01',
  'Nano_04' => 'Cisterna IE02',
  'Nano_01' => 'Res. Incêndio',
  'Nano_05A' => 'Res. Bombas IE03',
  'Nano_05B' => 'Cisterna IF01',
  'Nano_05C' => 'Cisterna IF02',
];

// Busca nodes disponíveis
$nodes_res = $mysqli->query("SELECT DISTINCT node FROM resumo_diario ORDER BY node ASC");
$nodes = [];
while($n = $nodes_res->fetch_assoc()) $nodes[] = $n['node'];

// Busca dados do dia para todos os sensores
$registros = [];
$stmt = $mysqli->prepare("SELECT * FROM resumo_diario WHERE data = ? ORDER BY node ASC");
$stmt->bind_param('s', $filtro_data);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()) $registros[$r['node']] = $r;
$stmt->close();

// Busca última leitura de cada sensor para data/hora
$ultimas = [];
foreach($nodes as $node) {
  $stmt = $mysqli->prepare("SELECT * FROM leituras WHERE node = ? AND DATE(ts) = ? ORDER BY ts DESC LIMIT 1");
  $stmt->bind_param('ss', $node, $filtro_data);
  $stmt->execute();
  $res = $stmt->get_result();
  $ultimas[$node] = $res->fetch_assoc();
  $stmt->close();
}

$mysqli->close();

// Função para cor do card
function cor_card($cap) {
  if ($cap > 60) return '#b6e7a0'; // verde
  if ($cap >= 40) return '#ffe680'; // amarelo
  return '#ffb3b3'; // vermelho
}

// Data formatada
$data_fmt = date('d/m/Y', strtotime($filtro_data));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>CMASM Aguada</title>
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: #f0f2f5; color: #333; }
header { background: #fff; padding: 15px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
h1 { margin: 0; font-size: 24px; color: #2c3e50; }
nav a { text-decoration: none; color: #555; margin-left: 20px; font-weight: 500; }
nav a.active { color: #007bff; border-bottom: 2px solid #007bff; }
.container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
h2 { color:#444; margin-top:30px; }
.subtitulo { color:#666; font-size:16px; margin-bottom:30px; text-align: center; }
.cards { display: flex; flex-wrap: wrap; gap: 18px; margin-bottom: 30px; justify-content: center; }
.card { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); min-width: 220px; max-width: 220px; padding: 18px 16px; display: flex; flex-direction: column; align-items: center; }
.card-header { font-weight: bold; font-size: 15px; margin-bottom: 8px; }
.card-body { font-size: 22px; font-weight: bold; margin-bottom: 8px; }
.card-body2 { font-size: 15px; font-weight: normal; margin-bottom: 8px; }
.card-footer { font-size: 12px; color: #888; margin-top: 6px; }
.verde { background: #b6e7a0 !important; }
.amarelo { background: #ffe680 !important; }
.vermelho { background: #ffb3b3 !important; }
canvas { border:1px solid #888; background:#fff; margin-top:20px; width: 100%; }
table { border-collapse: collapse; width:100%; background:white; margin-top:20px; }
th,td { border:1px solid #ccc; padding:6px 8px; text-align:center; font-size:13px; }
th { background:#eee; }
.filter-form { text-align: center; margin-bottom: 20px; }
.filter-form input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
.filter-form button { padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
</style>
</head>
<body>

<header>
    <h1>💧 CMASM Aguada</h1>
    <nav>
        <a href="dashboard.php">Monitoramento</a>
        <a href="relatorio_consumo.php" class="active">Relatório Histórico</a>
    </nav>
</header>

<div class="container">
    <div class="filter-form">
        <form method="GET">
            <label>Data: <input type="date" name="data" value="<?= $filtro_data ?>"></label>
            <button type="submit">Filtrar</button>
        </form>
    </div>

    <div class="subtitulo">Relatório Diário - <?= $data_fmt ?></div>

<div class="cards">
<?php foreach($nodes as $node): 
  $nome = $sensor_nomes[$node] ?? $node;
  $r = $registros[$node] ?? null;
  $u = $ultimas[$node] ?? null;
  $cap = isset($r['percentual_final']) ? floatval($r['percentual_final']) : 0;
  $cor = $cap > 60 ? 'verde' : ($cap >= 40 ? 'amarelo' : 'vermelho');
?>
  <div class="card <?= $cor ?>">
    <div class="card-header"><?= htmlspecialchars($nome) ?></div>
    <div class="card-body">
      Volume: <?= isset($r['volume_final']) ? number_format($r['volume_final'],1) : '--' ?> t<br>
      Capacidade: <?= isset($r['percentual_final']) ? number_format($r['percentual_final'],1) : '--' ?> %
    </div>
    <div class="card-body2">
      Consumo: <?= isset($r['consumo']) ? number_format($r['consumo'],1) : '--' ?> t<br>
      Abastecimento: <?= isset($r['abastecimento']) ? number_format($r['abastecimento'],1) : '--' ?> t
    </div>
    <div class="card-footer">
      <?php if($u && isset($u['ts'])): ?>
        <?= date('dH:i', strtotime($u['ts'])) ?>
      <?php else: ?>
        --
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<h2>Gráfico Volume e Capacidade</h2>
<canvas id="grafico" width="900" height="320"></canvas>

<h2>Leituras por Sensor (últimas 20)</h2>
<table>
<tr>
  <th>Sensor</th><th>Data/Hora</th><th>Volume (t)</th><th>Capacidade (%)</th><th>Consumo (t)</th><th>Abastecimento (t)</th>
</tr>
<?php
// Buscar últimas 20 leituras de cada sensor
$mysqli_tabela = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
foreach($nodes as $node):
  $nome = $sensor_nomes[$node] ?? $node;
  $q = $mysqli_tabela->prepare("SELECT * FROM leituras WHERE node=? AND DATE(ts)=? ORDER BY ts DESC LIMIT 5");
  $q->bind_param('ss', $node, $filtro_data);
  $q->execute();
  $res = $q->get_result();
  while($l = $res->fetch_assoc()):
?>
<tr>
  <td><?= htmlspecialchars($nome) ?></td>
  <td><?= date('d/m H:i', strtotime($l['ts'])) ?></td>
  <td><?= number_format($l['volume'],1) ?></td>
  <td><?= number_format($l['percentual'],1) ?></td>
  <td>--</td>
  <td>--</td>
</tr>
<?php endwhile; $q->close(); endforeach; $mysqli_tabela->close(); ?>
</table>

<script>
// Gráfico de barras para volume e capacidade
const dados = <?php echo json_encode(array_values($registros)); ?>;
const canvas = document.getElementById('grafico');
const ctx = canvas.getContext('2d');

// Ajuste de resolução
const dpr = window.devicePixelRatio || 1;
const rect = canvas.getBoundingClientRect();
canvas.width = rect.width * dpr;
canvas.height = rect.height * dpr;
ctx.scale(dpr, dpr);

const W = rect.width, H = rect.height;
ctx.clearRect(0,0,W,H);
const margin = 50;
const barW = 40;
const gap = 40;
const maxVol = Math.max(...dados.map(d=>parseFloat(d.volume_final)||0),1)*1.1;
const maxCap = 100;
// Eixos
ctx.strokeStyle = '#000';
ctx.beginPath();
ctx.moveTo(margin,margin);
ctx.lineTo(margin,H-margin);
ctx.lineTo(W-margin,H-margin);
ctx.stroke();
// Barras
dados.forEach((d,i)=>{
  const x = margin + gap + i*(barW+gap);
  // Volume
  const v = parseFloat(d.volume_final)||0;
  const h = ((v/maxVol)*(H-2*margin));
  ctx.fillStyle = '#4caf50';
  ctx.fillRect(x,H-margin-h,barW,h);
  // Capacidade
  const c = parseFloat(d.percentual_final)||0;
  const h2 = ((c/maxCap)*(H-2*margin));
  ctx.fillStyle = '#2196f3';
  ctx.fillRect(x+barW+4,H-margin-h2,barW/2,h2);
  // Label
  ctx.fillStyle = '#000';
  ctx.font = '13px Arial';
  ctx.save();
  ctx.translate(x+barW/2,H-margin+18);
  ctx.rotate(-0.25);
  ctx.fillText(d.node,0,0);
  ctx.restore();
});
// Legenda
ctx.fillStyle = '#4caf50'; ctx.fillRect(W-180,margin,20,12); ctx.fillStyle='#000'; ctx.fillText('Volume (t)',W-155,margin+12);
ctx.fillStyle = '#2196f3'; ctx.fillRect(W-180,margin+20,20,12); ctx.fillStyle='#000'; ctx.fillText('Capacidade (%)',W-155,margin+32);
</script>

</div> <!-- End Container -->
</body>
</html>

