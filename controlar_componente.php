<?php
// controlar_componente.php - API para alterar estado de válvulas/bombas via botões
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if($mysqli->connect_errno){ http_response_code(500); die(json_encode(['status'=>'error','msg'=>'DB error'])); }

// Garantir tabelas existem
$mysqli->query("CREATE TABLE IF NOT EXISTS comandos (id INT AUTO_INCREMENT PRIMARY KEY, node VARCHAR(16), componente VARCHAR(32), valor TINYINT, usuario VARCHAR(64), ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(node), INDEX(ts))");
$mysqli->query("CREATE TABLE IF NOT EXISTS estado_atual (node VARCHAR(16), componente VARCHAR(32), valor TINYINT, ultima_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(node, componente))");

$data = json_decode(file_get_contents('php://input'), true);
if(!$data || !isset($data['node']) || !isset($data['componente']) || !isset($data['valor'])){
    http_response_code(400); 
    echo json_encode(['status'=>'error','msg'=>'Campos obrigatórios: node, componente, valor']);
    exit;
}

$node = substr(trim($data['node']), 0, 16);
$componente = substr(trim($data['componente']), 0, 32);
$valor = intval($data['valor']);
$usuario = isset($data['usuario']) ? substr(trim($data['usuario']), 0, 64) : 'web';

// Registrar comando
$stmt = $mysqli->prepare("INSERT INTO comandos(node, componente, valor, usuario) VALUES (?,?,?,?)");
$stmt->bind_param('ssis', $node, $componente, $valor, $usuario);
$stmt->execute();
$stmt->close();

// Atualizar estado atual
$stmt2 = $mysqli->prepare("INSERT INTO estado_atual(node, componente, valor) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valor=?, ultima_atualizacao=NOW()");
$stmt2->bind_param('ssii', $node, $componente, $valor, $valor);
$stmt2->execute();
$stmt2->close();

$mysqli->close();
echo json_encode(['status'=>'ok','node'=>$node,'componente'=>$componente,'valor'=>$valor]);
?>
