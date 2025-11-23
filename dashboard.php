<?php
// dashboard.php - Painel Unificado (Monitoramento em Tempo Real)
require_once __DIR__ . '/config.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) die("Erro DB: " . $mysqli->connect_error);

$nomes = [
 "Nano_01" => "Res. Incêndio",
 "Nano_02" => "Res. Consumo",
 "Nano_03" => "Cisterna IE01",
 "Nano_04" => "Cisterna IE02",
 "Nano_05A" => "Res. Bombas IE03",
 "Nano_05B" => "Cisterna IF01",
 "Nano_05C" => "Cisterna IF02"
];

// AJAX Handler
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $hoje = date('Y-m-d');
    
    // 1. Últimas leituras (Status atual)
    $latest = [];
    $sql = "SELECT l.* FROM leituras l INNER JOIN (SELECT node, MAX(id) as max_id FROM leituras GROUP BY node) m ON l.id = m.max_id";
    $res = $mysqli->query($sql);
    if($res) while($row = $res->fetch_assoc()) $latest[$row['node']] = $row;
    
    // 2. Resumo diário (Consumo/Abastecimento)
    $daily = [];
    $stmt = $mysqli->prepare("SELECT * FROM resumo_diario WHERE data = ?");
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $resD = $stmt->get_result();
    while($row = $resD->fetch_assoc()) $daily[$row['node']] = $row;
    
    // 3. Histórico para gráfico (últimas 24h)
    $history = [];
    $resH = $mysqli->query("SELECT node, ts, percentual FROM leituras WHERE ts >= NOW() - INTERVAL 24 HOUR ORDER BY ts ASC");
    if($resH) while($row = $resH->fetch_assoc()) $history[] = $row;

    echo json_encode(['latest' => $latest, 'daily' => $daily, 'history' => $history]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>CMASM Aguada - Monitoramento</title>
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: #f0f2f5; color: #333; }
header { background: #fff; padding: 15px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
h1 { margin: 0; font-size: 24px; color: #2c3e50; }
nav a { text-decoration: none; color: #555; margin-left: 20px; font-weight: 500; }
nav a.active { color: #007bff; border-bottom: 2px solid #007bff; }
.container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
.subtitulo { text-align: center; color: #666; margin-bottom: 20px; }

.cards-row { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; margin-bottom: 20px; }
.card {
  flex: 1 1 280px; max-width: 350px;
  background: white; border-radius: 12px; padding: 20px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
  transition: transform 0.2s;
}
.card:hover { transform: translateY(-2px); }
.card h3 { margin: 0 0 15px; text-align: center; font-size: 18px; color: #444; border-bottom: 1px solid #eee; padding-bottom: 10px; }
.card-main { text-align: center; margin-bottom: 15px; }
.vol-big { font-size: 28px; font-weight: bold; color: #2c3e50; }
.cap-big { font-size: 16px; color: #7f8c8d; }
.card-stats { display: flex; justify-content: space-between; font-size: 14px; background: #f8f9fa; padding: 10px; border-radius: 8px; }
.stat-item { text-align: center; }
.stat-label { display: block; font-size: 11px; color: #999; text-transform: uppercase; }
.stat-val { font-weight: 600; color: #555; }
.card-footer { font-size: 11px; text-align: right; color: #aaa; margin-top: 10px; }

/* Cores de status */
.status-verde { border-top: 5px solid #28a745; }
.status-amarelo { border-top: 5px solid #ffc107; }
.status-vermelho { border-top: 5px solid #dc3545; }

/* Gráfico */
.chart-container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-top: 30px; }
canvas { width: 100%; height: 350px; }
</style>
</head>
<body>

<header>
    <h1>💧 CMASM Aguada</h1>
    <nav>
        <a href="dashboard.php" class="active">Monitoramento</a>
        <a href="relatorio_consumo.php">Relatório Histórico</a>
    </nav>
</header>

<div class="container">
    <div class="subtitulo">Monitoramento em Tempo Real - <?= date("d/m/Y") ?></div>

    <!-- Linha 1: Principais -->
    <div class="cards-row" id="row1">
        <!-- Preenchido via JS -->
    </div>

    <!-- Linha 2: Cisternas -->
    <div class="cards-row" id="row2">
        <!-- Preenchido via JS -->
    </div>

    <div class="chart-container">
        <h3>📈 Capacidade (%) - Últimas 24h</h3>
        <canvas id="mainChart"></canvas>
    </div>
</div>

<script>
const nomes = <?= json_encode($nomes) ?>;
const row1Nodes = ["Nano_02", "Nano_01", "Nano_05A"];
const row2Nodes = ["Nano_03", "Nano_04", "Nano_05B", "Nano_05C"];

function getStatusClass(cap) {
    if (cap > 60) return 'status-verde';
    if (cap >= 40) return 'status-amarelo';
    return 'status-vermelho';
}

function renderCard(node, data, daily) {
    const nome = nomes[node] || node;
    const vol = data ? parseFloat(data.volume).toFixed(1) : '--';
    const cap = data ? parseFloat(data.percentual).toFixed(1) : '--';
    const cons = daily ? parseFloat(daily.consumo).toFixed(1) : '0.0';
    const abs = daily ? parseFloat(daily.abastecimento).toFixed(1) : '0.0';
    const ts = data ? new Date(data.ts).toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'}) : '--:--';
    const statusClass = data ? getStatusClass(parseFloat(data.percentual)) : '';

    return `
    <div class="card ${statusClass}">
        <h3>${nome}</h3>
        <div class="card-main">
            <div class="vol-big">${vol} <small>m³</small></div>
            <div class="cap-big">${cap}% Cheio</div>
        </div>
        <div class="card-stats">
            <div class="stat-item">
                <span class="stat-label">Consumo</span>
                <span class="stat-val">⬇ ${cons} t</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Abastec.</span>
                <span class="stat-val">⬆ ${abs} t</span>
            </div>
        </div>
        <div class="card-footer">Atualizado às ${ts}</div>
    </div>`;
}

function updateDashboard() {
    fetch('dashboard.php?ajax=1')
        .then(r => r.json())
        .then(d => {
            // Render Row 1
            const r1 = document.getElementById('row1');
            r1.innerHTML = row1Nodes.map(n => renderCard(n, d.latest[n], d.daily[n])).join('');

            // Render Row 2
            const r2 = document.getElementById('row2');
            r2.innerHTML = row2Nodes.map(n => renderCard(n, d.latest[n], d.daily[n])).join('');

            // Render Chart
            drawChart(d.history);
        })
        .catch(e => console.error("Erro atualização:", e));
}

// Simple Canvas Chart Engine (Multi-line)
function drawChart(history) {
    const canvas = document.getElementById('mainChart');
    const ctx = canvas.getContext('2d');
    // Ajuste de resolução
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);
    
    const W = rect.width;
    const H = rect.height;
    const pad = 40;
    
    ctx.clearRect(0, 0, W, H);
    
    // Group by node
    const series = {};
    history.forEach(h => {
        if(!series[h.node]) series[h.node] = [];
        series[h.node].push({t: new Date(h.ts).getTime(), v: parseFloat(h.percentual)});
    });

    // Find min/max time for X axis
    const times = history.map(h => new Date(h.ts).getTime());
    const minT = Math.min(...times);
    const maxT = Math.max(...times);
    const timeRange = maxT - minT || 1;

    // Y Axis (0 to 100%)
    const maxY = 100;
    
    function getX(t) { return pad + ((t - minT) / timeRange) * (W - pad * 2); }
    function getY(v) { return H - pad - (v / maxY) * (H - pad * 2); }

    // Draw Axes
    ctx.strokeStyle = '#ddd';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(pad, pad); ctx.lineTo(pad, H - pad); ctx.lineTo(W - pad, H - pad);
    ctx.stroke();

    // Draw Grid & Labels Y
    ctx.fillStyle = '#888';
    ctx.font = '10px Arial';
    ctx.textAlign = 'right';
    for(let i=0; i<=5; i++) {
        let val = i * 20;
        let y = getY(val);
        ctx.fillText(val + '%', pad - 5, y + 3);
        ctx.beginPath(); ctx.moveTo(pad, y); ctx.lineTo(W - pad, y); ctx.strokeStyle='#eee'; ctx.stroke();
    }

    // Draw Lines
    const colors = ['#e74c3c', '#3498db', '#9b59b6', '#f1c40f', '#2ecc71', '#e67e22', '#1abc9c'];
    let cIdx = 0;
    
    Object.keys(series).forEach(node => {
        const pts = series[node];
        if(pts.length < 2) return;
        
        ctx.strokeStyle = colors[cIdx % colors.length];
        ctx.lineWidth = 2;
        ctx.beginPath();
        
        pts.forEach((p, i) => {
            const x = getX(p.t);
            const y = getY(p.v);
            if(i===0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        });
        ctx.stroke();
        
        // Legend (Simple)
        ctx.fillStyle = colors[cIdx % colors.length];
        ctx.fillText(nomes[node] || node, W - pad, pad + 20 + (cIdx * 15));
        cIdx++;
    });
}

// Init
updateDashboard();
setInterval(updateDashboard, 10000); // 10s refresh
</script>

</body>
</html>
