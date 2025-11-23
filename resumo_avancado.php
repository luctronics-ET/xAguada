<?php
// resumo_avancado.php - visão avançada do resumo_diario incluindo médias e estados (componentes_json)
require_once __DIR__ . '/config.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if($mysqli->connect_errno) die('Erro DB: '.$mysqli->connect_error);

$hoje = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
$stmt = $mysqli->prepare("SELECT * FROM resumo_diario WHERE data=? ORDER BY node ASC");
$stmt->bind_param('s',$hoje); $stmt->execute(); $res = $stmt->get_result();
$linhas = []; while($r=$res->fetch_assoc()) $linhas[]=$r; $res->free(); $stmt->close();
$mysqli->close();

$nomes = [
 'Nano_01' => 'Res. Incêndio',
 'Nano_02' => 'Res. Consumo',
 'Nano_03' => 'Cisterna IE01',
 'Nano_04' => 'Cisterna IE02',
 'Nano_05' => 'Res. Bombas IE03'
];
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8" />
<title>Resumo Avançado - <?= htmlspecialchars($hoje) ?></title>
<style>
body{font-family:Arial;margin:20px;background:#f0f2f5}
h1{color:#333;margin-bottom:8px}
form{margin-bottom:15px}
table{border-collapse:collapse;width:100%;background:#fff}
th,td{border:1px solid #ccc;padding:6px 8px;font-size:12px;text-align:center}
th{background:#eee}
.small{font-size:11px;color:#555}
.tag{display:inline-block;padding:2px 5px;border-radius:4px;font-size:10px;margin:1px}
.VALVE{background:#d4edda;color:#155724}
.SOUND{background:#fff3cd;color:#856404}
.OUTRO{background:#e2e3e5;color:#41464b}
.wrap{display:flex;flex-wrap:wrap;justify-content:center}
</style>
</head>
<body>
<h1>Resumo Avançado - <?= htmlspecialchars($hoje) ?></h1>
<form method="get" action="resumo_avancado.php">
  <label>Data: <input type="date" name="data" value="<?= htmlspecialchars($hoje) ?>" /></label>
  <button type="submit">Filtrar</button>
</form>
<table>
 <tr>
  <th>Nó</th><th>Nome</th><th>Consumo (m³)</th><th>Abastecimento (m³)</th>
  <th>Vol Final</th><th>% Final</th><th>Vol Médio</th><th>% Médio</th>
  <th>Componentes</th>
 </tr>
 <?php foreach($linhas as $l): $comp = $l['componentes_json']? json_decode($l['componentes_json'], true):[]; ?>
 <tr>
  <td><?= htmlspecialchars($l['node']) ?></td>
  <td><?= htmlspecialchars($nomes[$l['node']]??$l['node']) ?></td>
  <td><?= number_format($l['consumo'],2) ?></td>
  <td><?= number_format($l['abastecimento'],2) ?></td>
  <td><?= number_format($l['volume_final'],2) ?></td>
  <td><?= number_format($l['percentual_final'],2) ?></td>
  <td><?= $l['volume_medio']!==null?number_format($l['volume_medio'],2):'--' ?></td>
  <td><?= $l['percentual_medio']!==null?number_format($l['percentual_medio'],2):'--' ?></td>
  <td style="text-align:left">
    <div class="wrap">
    <?php foreach($comp as $k=>$v): $tipo='OUTRO'; if(str_contains($k,'valve')) $tipo='VALVE'; elseif(str_contains($k,'sound')) $tipo='SOUND'; ?>
      <span class="tag <?= $tipo ?>" title="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?>: <?= intval($v) ?></span>
    <?php endforeach; if(!$comp) echo '<span class="small">--</span>'; ?>
    </div>
  </td>
 </tr>
 <?php endforeach; ?>
</table>
<p class="small">Atualizado via processamento incremental no endpoint gateway.</p>
</body>
</html>