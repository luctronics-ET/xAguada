<?php
// documentacao.php - índice simples para arquivos em Documents/
function listar($dir){
    $base = __DIR__ . '/Documents/' . $dir;
    if(!is_dir($base)) return [];
    $files = scandir($base); $out=[];
    foreach($files as $f){ if($f==='.'||$f==='..') continue; if(is_file($base.'/'.$f)) $out[]=$f; }
    sort($out); return $out;
}
$grupos = [
 'instrucoes' => 'Instruções Operacionais',
 'formularios' => 'Formulários',
 'relatorios' => 'Modelos de Relatórios'
];
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8" />
<title>Documentação Aguada</title>
<style>
body{font-family:Arial;margin:20px;background:#f0f2f5}
h1{color:#333;margin-bottom:4px}
h2{margin-top:28px;color:#444}
ul{list-style:disc;padding-left:20px}
a{text-decoration:none;color:#0069d9}
a:hover{text-decoration:underline}
.box{background:#fff;padding:14px 18px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;margin-top:15px}
.item{background:#fff;padding:12px 14px;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.item h3{margin:0 0 8px;font-size:16px;color:#222}
.tag{display:inline-block;background:#e2e3e5;color:#333;font-size:11px;padding:2px 6px;border-radius:4px;margin:2px 2px 0 0}
</style>
</head>
<body>
<h1>Documentação do Sistema Aguada</h1>
<p>Referência rápida dos arquivos de operação, manutenção e relatórios.</p>
<div class="grid">
<?php foreach($grupos as $dir=>$titulo): $lista=listar($dir); ?>
  <div class="item">
    <h3><?= htmlspecialchars($titulo) ?></h3>
    <?php if(!$lista): ?>
      <p><em>Sem arquivos.</em></p>
    <?php else: ?>
      <?php foreach($lista as $f): $path = 'Documents/'.$dir.'/'.$f; ?>
        <div><a href="<?= htmlspecialchars($path) ?>" target="_blank"><?= htmlspecialchars($f) ?></a></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
</body>
</html>