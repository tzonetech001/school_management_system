<?php
// 1. Tell the mobile app (and browser) to expect JSON data
header("Content-Type: application/json; charset=UTF-8");

// 2. Allow requests from outside your website domain (Crucial for mobile apps)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// 3. Connect to your existing database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "muyovozi";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    // Return an error message if the connection fails
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// 4. Fetch your data
$sql = "SELECT id, name, price FROM products";
$result = $conn->query($sql);

$products = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// 5. Turn the PHP array into JSON and print it
http_response_code(200);
echo json_encode($products);

$conn->close();
?>