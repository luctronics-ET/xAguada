CREATE DATABASE IF NOT EXISTS sensores_db;
USE sensores_db;

-- Leituras dos sensores (distância -> volume/percentual calculado)
CREATE TABLE IF NOT EXISTS leituras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  node VARCHAR(16) NOT NULL,
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  dist FLOAT NOT NULL,
  volume FLOAT NOT NULL,
  percentual FLOAT NOT NULL,
  INDEX(node),
  INDEX(ts)
);

-- Resumo diário agregado
CREATE TABLE IF NOT EXISTS resumo_diario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data DATE NOT NULL,
  node VARCHAR(16) NOT NULL,
  consumo DECIMAL(10,2) DEFAULT 0,
  abastecimento DECIMAL(10,2) DEFAULT 0,
  volume_final DECIMAL(10,2),
  percentual_final DECIMAL(5,2),
  volume_medio DECIMAL(10,2),
  percentual_medio DECIMAL(5,2),
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY (data, node)
);

-- Log de comandos manuais (botões de válvulas/bombas)
CREATE TABLE IF NOT EXISTS comandos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  node VARCHAR(16) NOT NULL,
  componente VARCHAR(32) NOT NULL,
  valor TINYINT NOT NULL,
  usuario VARCHAR(64),
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(node),
  INDEX(ts)
);

-- Estados atuais de componentes
CREATE TABLE IF NOT EXISTS estado_atual (
  node VARCHAR(16) NOT NULL,
  componente VARCHAR(32) NOT NULL,
  valor TINYINT NOT NULL,
  ultima_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(node, componente)
);

