<?php
function getDbConnection() {
    $host = "db";         // service name from docker-compose
    $db   = "appdb";      // your barbershop database
    $user = "appuser";    // must match docker-compose or MySQL grants
    $pass = "apppass";
    $charset = "utf8mb4";

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => true,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        die("❌ Connection failed: " . $e->getMessage());
    }
}
