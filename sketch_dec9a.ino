#include <WiFi.h>
#include <HTTPClient.h>

#define SensorPin 34   // GPIO pin for pH sensor
#define trigPin 27     // GPIO pin for ultrasonic sensor Trigger
#define echoPin 26     // GPIO pin for ultrasonic sensor Echo
#define pressurePin 23 // GPIO pin for pressure plate sensor

// Wi-Fi credentials
const char* ssid = "ZTE_2.4G_NE5x69";       // Replace with your WiFi SSID
const char* password = "66KccUsE"; // Replace with your WiFi password

// Server details
const char* serverURL = "http://192.168.1.14/my_iot/insert_sensor_data.php"; // Replace with your PHP server URL

void setup() {
  Serial.begin(9600);
  WiFi.begin(ssid, password);

  // Connect to Wi-Fi
  Serial.print("Connecting to Wi-Fi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.print(".");
  }
  Serial.println("\nConnected to Wi-Fi!");
  
  pinMode(trigPin, OUTPUT);  // Set the trigger pin as an output
  pinMode(echoPin, INPUT);   // Set the echo pin as an input
  pinMode(pressurePin, INPUT); // Set the pressure plate pin as input
}

void loop() {
  // Ultrasonic sensor logic
  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);

  long duration = pulseIn(echoPin, HIGH);
  
  long distanceCm = 0; // Declare distanceCm before usage
  
  if (duration == 0) {
    Serial.println("No echo detected");
  } else {
    distanceCm = (duration / 29) / 2; // Convert duration to cm
    Serial.print("Duration: ");
    Serial.println(duration);
    Serial.print("Distance (cm): ");
    Serial.println(distanceCm);
  }

  // pH sensor logic
  int buf[10];
  for (int i = 0; i < 10; i++) {
    buf[i] = analogRead(SensorPin);
    delay(10);
  }

  int avgValue = 0;
  for (int i = 2; i < 8; i++) {
    avgValue += buf[i];
  }

  float millivolts = (float)avgValue * 3.3 / 4095.0 / 6;
  float phValue = 2.3 * millivolts; // Calibration factor may need adjustment

  // Pressure plate sensor logic
  int pressureValue = digitalRead(pressurePin); // Read the pressure plate sensor

  // If pressure plate is pressed, set is_ready to 1 (water is ready)
  int is_ready = (pressureValue == HIGH) ? 1 : 0;

  // Print values to Serial Monitor
  Serial.print("Water Level (cm): ");
  Serial.println(distanceCm);
  Serial.print("pH Value: ");
  Serial.println(phValue, 2);
  Serial.print("Pressure Plate (is_ready): ");
  Serial.println(is_ready);

  // Prepare data for sending
  String postData = "water_level=" + String(distanceCm) +
                    "&ph_value=" + String(phValue, 2) +
                    "&is_ready=" + String(is_ready);

  // Send data to server
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(serverURL);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");

    int httpCode = http.POST(postData);
    if (httpCode > 0) {
      Serial.println("Data sent to server successfully.");
      Serial.println("Response: " + http.getString());
    } else {
      Serial.println("Error sending data to server.");
    }

    http.end();
  } else {
    Serial.println("Wi-Fi disconnected. Reconnecting...");
    WiFi.reconnect();
  }

  delay(5000); // Wait 5 seconds before sending data again
}
