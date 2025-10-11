CREATE DATABASE IF NOT EXISTS sensores_db;
USE sensores_db;

CREATE TABLE IF NOT EXISTS leituras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  node VARCHAR(16),
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  temp FLOAT,
  hum FLOAT,
  dist FLOAT,
  volume FLOAT,
  percentual FLOAT
);

CREATE TABLE IF NOT EXISTS resumo_diario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data DATE NOT NULL,
  node VARCHAR(16) NOT NULL,
  consumo DECIMAL(10,2) DEFAULT 0,
  abastecimento DECIMAL(10,2) DEFAULT 0,
  volume_final DECIMAL(10,2),
  percentual_final DECIMAL(5,2),
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY (data, node)
);

