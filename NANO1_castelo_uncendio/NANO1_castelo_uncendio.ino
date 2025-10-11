// Arduino Nano Ethernet Client Ultrasonic Distance Sensor 
// versão 01.AGO2025
//envia dados para MySQL usando PHP XAMPP. ABRIR XAMPP, INICIAR APACHE E MySQL,>> localhost/aguada
// Ethernet Shield ENC28J60 SO-12 SCK-13 CS-10 SI-11 RESET-RST GND-GND VCC-3.3V
// Ultrasonic distance sensor HC-SR04 Echo-D5 Trig-D6 VCC-5V GND-GND
// LCD I2C  SCL-A5 SDA-A4 VCC-5V GND-GND

// SENSOR DE NIVEL AGUADA PARA CASTELO DE INCENDIO CMASM AGO2025


#include <UIPEthernet.h>
#include <SPI.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// ----- Configuração de rede -----
byte mac[] = { 0xDE,0xAD,0xBE,0xEF,0xFE,0x01 };

// IP fixo do Arduino
IPAddress ip(192, 168, 10, 200);        // IP do Nano
IPAddress gateway(192, 168, 10, 1);   // IP do roteador gateway
IPAddress subnet(255, 255, 255, 0);

// IP do servidor XAMPP
IPAddress server(192, 168, 0, 141);   // IP do servidor
EthernetClient client;

// ----- LCD 16x2 I2C -----
LiquidCrystal_I2C lcd(0x27,16,2);

// ----- Sensor HC-SR04 -----
#define TRIGPIN 6
#define ECHOPIN 5

// ----- Identificação do nó -----
char NODE_NAME[] = "Nano_01";

// ----- Funções -----
float lerDistanciaCm() {
  digitalWrite(TRIGPIN, LOW); delayMicroseconds(2);
  digitalWrite(TRIGPIN, HIGH); delayMicroseconds(10);
  digitalWrite(TRIGPIN, LOW);
  long dur = pulseIn(ECHOPIN, HIGH, 30000); // timeout ~30ms (~5m)
  return (dur==0)? NAN : dur*0.0343/2.0;
}

float calcularVolume(float distCm){
  float hsensor=0.2, hcxagua=4.67, diametro=5.10;
  // distCm convertido para metros
  float distM = distCm / 100.0;
  float volume = (hcxagua - hsensor - distM) * 3.1416 * (diametro/2)*(diametro/2);
  return volume;
}

float calcularPercentual(float volume){
  float hsensor=0.2, hcxagua=4.67, diametro=5.10;
  float volTotal = 3.1416*(diametro/2)*(diametro/2)*(hcxagua-hsensor);
  return (volume/volTotal)*100.0;
}

void setup() {
  pinMode(TRIGPIN, OUTPUT);
  pinMode(ECHOPIN, INPUT);
  
  lcd.init();
  lcd.backlight();
  
  // Inicializa Ethernet com IP fixo
  Ethernet.begin(mac, ip, gateway, gateway, subnet);
  delay(1000);
}

void loop() {
  float dist = lerDistanciaCm();
  float volume = calcularVolume(dist);
  float perc = calcularPercentual(volume);

  // ----- Mostrar no LCD -----
  lcd.clear();
  lcd.setCursor(0,0); lcd.print("Vol:"); lcd.print(volume,1); lcd.print("m3");
  lcd.setCursor(0,1); lcd.print("Cap:"); lcd.print(perc,1); lcd.print("%");

  // ----- Enviar dados para o servidor -----
  if(client.connect(server,80)){
    String postData = "{\"node\":\""+String(NODE_NAME)+"\",\"temp\":0,\"hum\":0,\"dist\":"+String(dist)+",\"volume\":"+String(volume)+",\"percentual\":"+String(perc)+"}";
    
    client.println("POST /inserir_leitura.php HTTP/1.1");
    client.print("Host: "); client.println(server);
    client.println("Content-Type: application/json");
    client.print("Content-Length: "); client.println(postData.length());
    client.println();
    client.println(postData);
    client.stop();
  }

  delay(5000); // envia a cada 5 segundos
}
