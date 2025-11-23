<?php
// painel.php - Painel simples de leituras
require_once __DIR__ . '/config.php';

/* Basic HTTP auth
if (!isset($_SERVER['PHP_AUTH_USER'])) {
  header('WWW-Authenticate: Basic realm="Painel"');
  header('HTTP/1.0 401 Unauthorized');
  echo 'Autenticação requerida.'; exit;
}
if ($_SERVER['PHP_AUTH_USER'] !== ADMIN_USER || $_SERVER['PHP_AUTH_PW'] !== ADMIN_PASS) {
  header('HTTP/1.0 403 Forbidden'); echo 'Acesso negado.'; exit;
}
*/
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) die('Erro DB: '.$mysqli->connect_error);
$res = $mysqli->query("SELECT * FROM leituras ORDER BY id DESC LIMIT 50");
$leituras = [];
while($r=$res->fetch_assoc()) $leituras[]=$r;
$res->free();
$mysqli->close();
$leituras = array_reverse($leituras);
$ultima = end($leituras);

if (isset($_GET['ajax']) && $_GET['ajax']=='1') { header('Content-Type: application/json'); echo json_encode($leituras); exit; }
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Painel Sensores</title>
  <style>
    body{font-family:Arial;margin:20px;background:#f0f2f5}
    h1{color:#333}
    .cards{display:flex;gap:20px;flex-wrap:wrap}
    .card{background:#fff;padding:16px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.1);min-width:160px;text-align:center}
    .card h3{margin:0;font-size:14px;color:#555}
    .card p{margin:8px 0 0;font-size:20px;font-weight:700}
    table{border-collapse:collapse;width:100%;background:#fff}
    th,td{border:1px solid #ccc;padding:6px 8px;text-align:center}
    th{background:#eee}
    canvas{border:1px solid #888;background:#fff;margin-top:20px}
  </style>
</head>
<body>
  <h1>Painel de Monitoramento</h1>
  <div class="cards">
    <div class="card"><h3>Distância</h3><p id="card_dist"><?= isset($ultima['dist'])?number_format($ultima['dist'],1).' cm':'--' ?></p></div>
    <div class="card"><h3>Volume</h3><p id="card_vol"><?= isset($ultima['volume'])?number_format($ultima['volume'],1).' m³':'--' ?></p></div>
    <div class="card"><h3>Capacidade</h3><p id="card_cap"><?= isset($ultima['percentual'])?number_format($ultima['percentual'],1).' %':'--' ?></p></div>
  </div>
  <h2>Leituras Recentes</h2>
  <table id="tabela_leituras"><tr><th>ID</th><th>Nó</th><th>Data</th><th>Dist (cm)</th><th>Volume</th><th>Cap (%)</th></tr>
  <?php foreach($leituras as $l): ?>
  <tr><td><?=$l['id']?></td><td><?=htmlspecialchars($l['node'])?></td><td><?=$l['ts']?></td><td><?=number_format($l['dist'],1)?></td><td><?=number_format($l['volume'],1)?></td><td><?=number_format($l['percentual'],1)?></td></tr>
  <?php endforeach; ?></table>
  <h2>Gráfico Volume e Capacidade</h2>
  <canvas id="grafico2" width="800" height="300"></canvas>
  <script>
  function desenharGrafico(id,labels,series,cores,leg){const c=document.getElementById(id).getContext('2d');const W=c.canvas.width,H=c.canvas.height;c.clearRect(0,0,W,H);const m=40,pW=W-m*2,pH=H-m*2;let all=[];series.forEach(s=>all=all.concat(s));const max=Math.max(...all)*1.1||1,min=Math.min(...all)*0.9||0;const y2p=y=>H-m-((y-min)/(max-min||1))*pH;const x2p=i=>m+(labels.length>1?(i/(labels.length-1))*pW:pW/2);c.strokeStyle='#000';c.beginPath();c.moveTo(m,m);c.lineTo(m,H-m);c.lineTo(W-m,H-m);c.stroke();c.strokeStyle='#ccc';c.font='10px Arial';for(let j=0;j<=5;j++){let v=min+j*(max-min)/5;let y=y2p(v);c.beginPath();c.moveTo(m,y);c.lineTo(W-m,y);c.stroke();c.fillText(v.toFixed(1),5,y+3);}series.forEach((s,i)=>{c.strokeStyle=cores[i];c.beginPath();s.forEach((v,idx)=>{let x=x2p(idx),y=y2p(v);if(idx===0)c.moveTo(x,y);else c.lineTo(x,y)});c.stroke()});leg.forEach((t,i)=>{c.fillStyle=cores[i];c.fillText(t,W-120,20+i*15)})}
  function atualizar(){fetch('painel.php?ajax=1').then(r=>r.json()).then(d=>{if(!Array.isArray(d)||d.length===0)return;const u=d[d.length-1];document.getElementById('card_dist').innerText=parseFloat(u.dist).toFixed(1)+' cm';document.getElementById('card_vol').innerText=parseFloat(u.volume).toFixed(1)+' m³';document.getElementById('card_cap').innerText=parseFloat(u.percentual).toFixed(1)+' %';const t=document.getElementById('tabela_leituras');t.innerHTML='<tr><th>ID</th><th>Nó</th><th>Data</th><th>Dist (cm)</th><th>Volume</th><th>Cap (%)</th></tr>';d.forEach(r=>{t.innerHTML+=`<tr><td>${r.id}</td><td>${r.node}</td><td>${r.ts}</td><td>${parseFloat(r.dist).toFixed(1)}</td><td>${parseFloat(r.volume).toFixed(1)}</td><td>${parseFloat(r.percentual).toFixed(1)}</td></tr>`});const labels=d.map(x=>x.ts);const vols=d.map(x=>parseFloat(x.volume));const caps=d.map(x=>parseFloat(x.percentual));desenharGrafico('grafico2',labels,[vols,caps],['green','purple'],['Vol','Cap']);}).catch(e=>console.error(e))}
  atualizar();setInterval(atualizar,10000);
  </script>
</body>
</html>
