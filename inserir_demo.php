<?php
// Script para inserir dados demo na tabela leituras
//http://localhost/xaguada/inserir_demo.php
require_once __DIR__ . '/config.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) die('Erro DB: '.$mysqli->connect_error);
$nodes = ['Nano_02','Nano_01','Nano_05A','Nano_03','Nano_04','Nano_05B','Nano_05C'];
$faker = function($min, $max, $dec=1) { return round($min + ($max-$min)*mt_rand()/mt_getrandmax(), $dec); };
for ($i=1; $i<=10; $i++) {
  $node = $nodes[array_rand($nodes)];
  $dist = rand(50, 400);
  $volume = rand(100, 800)/10;
  $percentual = rand(10, 100);
  $temp = $faker(18, 35, 1);
  $hum = $faker(30, 90, 1);
  $mysqli->query("INSERT INTO leituras (node,temp,hum,dist,volume,percentual) VALUES ('$node',$temp,$hum,$dist,$volume,$percentual)");
}
$mysqli->close();
echo "Demo inserido";
