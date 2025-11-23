<?php
// relatorio_horario.php - volume por hora do Castelo de Consumo (Nano_02)
require_once __DIR__ . '/config.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if($mysqli->connect_errno) die('Erro DB: '.$mysqli->connect_error);

$data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
$node = 'Nano_02'; // Castelo de Consumo

// Buscar leituras do dia agrupadas por hora
$stmt = $mysqli->prepare("SELECT HOUR(ts) as hora, AVG(volume) as vol_medio, AVG(percentual) as perc_medio, COUNT(*) as qtd FROM leituras WHERE node=? AND DATE(ts)=? GROUP BY HOUR(ts) ORDER BY hora ASC");
$stmt->bind_param('ss', $node, $data);
$stmt->execute();
$res = $stmt->get_result();
$porHora = [];
while($r=$res->fetch_assoc()) $porHora[$r['hora']] = $r;
$res->free(); $stmt->close();

// Resumo do dia
$stmt2 = $mysqli->prepare("SELECT consumo, abastecimento, volume_final, percentual_final, volume_medio, percentual_medio FROM resumo_diario WHERE node=? AND data=?");
$stmt2->bind_param('ss', $node, $data);
$stmt2->execute();
$resumo = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

$mysqli->close();
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8"/>
<title>Relatório Horário - Castelo Consumo</title>
<style>
body{font-family:Arial;background:#f0f2f5;padding:20px}
h1{color:#333;margin-bottom:10px}
nav{margin-bottom:15px}
nav a{margin-right:15px;text-decoration:none;color:#0069d9}
nav a:hover{text-decoration:underline}
form{margin:15px 0;padding:12px;background:#fff;border-radius:6px;display:inline-block}
table{border-collapse:collapse;width:100%;max-width:700px;background:#fff;margin-top:15px}
th,td{border:1px solid #ccc;padding:8px;text-align:center;font-size:13px}
th{background:#eee}
.resumo{background:#fff;padding:14px;border-radius:6px;max-width:700px;margin-top:20px}
.resumo h3{margin:0 0 10px;font-size:16px;color:#333}
.resumo div{margin:6px 0;font-size:14px}
</style>
</head>
<body>
<h1>📊 Relatório Horário - Castelo de Consumo</h1>
<nav>
  <a href="index.php">Painel</a>
  <a href="relatorio_horario.php">Relatório Horário</a>
  <a href="documentacao.php">Documentação</a>
</nav>

<form method="get" action="relatorio_horario.php">
  <label>Data: <input type="date" name="data" value="<?= htmlspecialchars($data) ?>"/></label>
  <button type="submit">Filtrar</button>
</form>

<table>
<tr>
  <th>Hora</th>
  <th>Volume Médio (m³)</th>
  <th>Capacidade Média (%)</th>
  <th>Leituras</th>
</tr>
<?php for($h=0; $h<24; $h++): $dados = $porHora[$h] ?? null; ?>
<tr>
  <td><?= str_pad($h,2,'0',STR_PAD_LEFT) ?>:00</td>
  <td><?= $dados ? number_format($dados['vol_medio'],2) : '--' ?></td>
  <td><?= $dados ? number_format($dados['perc_medio'],2) : '--' ?></td>
  <td><?= $dados ? $dados['qtd'] : 0 ?></td>
</tr>
<?php endfor; ?>
</table>

<?php if($resumo): ?>
<div class="resumo">
  <h3>Resumo do Dia (<?= date('d/m/Y', strtotime($data)) ?>)</h3>
  <div><b>Consumo Total:</b> <?= number_format($resumo['consumo'],2) ?> m³</div>
  <div><b>Abastecimento Total:</b> <?= number_format($resumo['abastecimento'],2) ?> m³</div>
  <div><b>Volume Final:</b> <?= number_format($resumo['volume_final'],2) ?> m³ (<?= number_format($resumo['percentual_final'],2) ?>%)</div>
  <div><b>Volume Médio:</b> <?= number_format($resumo['volume_medio'],2) ?> m³ (<?= number_format($resumo['percentual_medio'],2) ?>%)</div>
</div>
<?php else: ?>
<p style="margin-top:15px;color:#666"><em>Sem resumo calculado para esta data.</em></p>
<?php endif; ?>

</body>
</html>
