<?php
$servername = "localhost";
$username = "blazeal";
$password = "Vs28903817";
$dbname = "blazeal";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

