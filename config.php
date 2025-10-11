<?php
// config.php - configurações do projeto
// Ajuste valores conforme seu ambiente (não commitar credenciais sensíveis)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP default root without password
define('DB_NAME', 'sensores_db');

// Credenciais para acesso ao painel (HTTP Basic Auth)
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'senha123'); // altere após instalar

// URL base usada pelo script de testes
define('BASE_URL', 'http://localhost/xaguada');

?>
