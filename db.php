<?php
// db.php - creates a PDO connection in $pdo
$servername = "db";
$username = "appuser";
$password = "apppass";
$dbname = "appdb";
$charset = "utf8mb4";

$dsn = "mysql:host={$servername};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // In production, do not echo errors — log them instead.
    die("Database connection failed: " . $e->getMessage());
}
