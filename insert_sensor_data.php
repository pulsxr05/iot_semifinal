<?php
$hostname = "localhost";
$username = "root";
$password = "";
$database = "iot";  // Make sure this is correct

$conn = mysqli_connect($hostname, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check if POST data is available
if (isset($_POST['water_level']) && isset($_POST['ph_value'])) {
    $water_level = floatval($_POST['water_level']);
    $ph_value = floatval($_POST['ph_value']);
    $is_ready = isset($_POST['is_ready']) ? intval($_POST['is_ready']) : 0;

    // Ensure water level is between 0 and 175 (since max ultrasonic reading is 175 cm)
    if ($water_level < 0) {
        $water_level = 0;
    } elseif ($water_level > 175) {
        $water_level = 175;
    }

    // Prepare and execute the SQL query
    $stmt = $conn->prepare("INSERT INTO sensor_data (water_level, ph_value, is_ready) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ddi", $water_level, $ph_value, $is_ready);
        if ($stmt->execute()) {
            echo "Data inserted successfully.";
        } else {
            echo "Error executing query: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Error preparing query: " . $conn->error;
    }
} else {
    echo "";
}

// Query the latest data from the database
$query = "SELECT water_level, ph_value, is_ready, timestamp FROM sensor_data ORDER BY timestamp DESC LIMIT 1"; // Get the most recent entry
$result = mysqli_query($conn, $query);

$latest_data = [];
if ($result && mysqli_num_rows($result) > 0) {
    $latest_data = mysqli_fetch_assoc($result);
} else {
    $latest_data = [
        'water_level' => 'N/A',
        'ph_value' => 'N/A',
        'is_ready' => 'N/A',
        'timestamp' => 'N/A'
    ];
}

// Water level status based on the new interpretation
$water_level_status = "Water is full.";
if ($latest_data['water_level'] > 0 && $latest_data['water_level'] <= 5) {
    $water_level_status = "Water is low.";
} elseif ($latest_data['water_level'] > 5 && $latest_data['water_level'] <= 85) {
    $water_level_status = "Water is semi full.";  // Semi full if water level is between 5 and 85 cm
} elseif ($latest_data['water_level'] > 85 && $latest_data['water_level'] <= 115) {
    $water_level_status = "Water is semi full.";  // Adjusted for semi full range
} elseif ($latest_data['water_level'] > 115 && $latest_data['water_level'] <= 175) {
    $water_level_status = "Water is full.";  // Above 115 cm is considered full
} elseif ($latest_data['water_level'] == 175) {
    $water_level_status = "Water is empty.";
}

// Check the pH level
$ph_status = "pH level is safe.";
if ($latest_data['ph_value'] < 6 || $latest_data['ph_value'] > 7) {
    $ph_status = "pH level is not safe.";
}

// Check the 'is_ready' status
$is_ready_status = "Water is not ready.";
if ($latest_data['is_ready'] == 1) {
    $is_ready_status = "Water is ready.";
}

echo "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Sensor Data</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #e6f7ff; /* Light blue background */
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            flex-direction: column;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            width: 90%;
            max-width: 1000px;
            overflow: auto;
        }
        h2 {
            text-align: center;
            color: #0066cc; /* Blue color for the heading */
        }
        .latest-data {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .box {
            background-color: #f0f8ff;
            border: 1px solid #0066cc;
            padding: 15px;
            text-align: center;
            width: 30%;
            border-radius: 8px;
        }
        .box h3 {
            color: #0066cc;
            font-size: 18px;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: center;
            border: 1px solid #dddddd;
        }
        th {
            background-color: #0066cc; /* Dark blue for table header */
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #d9e6f7; /* Light blue hover effect */
        }
        .message {
            text-align: center;
            font-size: 18px;
            color: #0066cc;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h2>Sensor Data</h2>
        
        <!-- Display latest data in separate boxes -->
        <div class='latest-data'>
            <div class='box'>
                <h3>Latest Water Level</h3>
                <p>" . htmlspecialchars($latest_data['water_level']) . " cm</p>
                <p>" . $water_level_status . "</p>
            </div>
            <div class='box'>
                <h3>Latest pH Value</h3>
                <p>" . htmlspecialchars($latest_data['ph_value']) . "</p>
                <p>" . $ph_status . "</p>
            </div>
            <div class='box'>
                <h3>Is Ready</h3>
                <p>" . $is_ready_status . "</p>
            </div>
        </div>";

        // Query the database to fetch all sensor data
        $query = "SELECT * FROM sensor_data ORDER BY timestamp DESC"; // Adjust table name if needed
        $result = mysqli_query($conn, $query);

        if ($result) {
            echo "<table id='sensorTable'>
                    <tr>
                        <th>Water Level</th>
                        <th>pH Value</th>
                        <th>Is Ready</th>
                        <th>Timestamp</th>
                    </tr>";
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>
                        <td>" . htmlspecialchars($row['water_level']) . " cm</td>
                        <td>" . htmlspecialchars($row['ph_value']) . "</td>
                        <td>" . htmlspecialchars($row['is_ready']) . "</td>
                        <td>" . htmlspecialchars($row['timestamp']) . "</td>
                      </tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='message'>No data found.</p>";
        }

echo "
    </div>
</body>
</html>";

$conn->close();
?>
