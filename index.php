<?php
// index.php
global $pdo;
session_start();
require 'db.php';
$pdo = getDbConnection();


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Define time slots (every half-hour from 10:00 AM to 8:00 PM)
$start_time = new DateTime('10:00');
$end_time = new DateTime('20:00');
$interval = new DateInterval('PT30M'); // 30 minute interval

$time_slots = [];
$period = new DatePeriod($start_time, $interval, $end_time);
foreach ($period as $time) {
    $time_slots[] = $time;
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// Fetch reservations
if ($is_admin) {
    $sql = "
        SELECT r.id, r.appointment_date, r.service, b.name AS barber_name, u.username AS customer_name
        FROM b_rezervace r
        JOIN b_barbers b ON r.barber_id = b.id
        JOIN b_zakaznici u ON r.user_id = u.id
        ORDER BY r.appointment_date
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $reservations = $stmt->fetchAll();
} else {
    // For non-admins we fetch reservations (without customer_name)
    $sql = "
        SELECT r.id, r.appointment_date, r.service, b.name AS barber_name
        FROM b_rezervace r
        JOIN b_barbers b ON r.barber_id = b.id
        ORDER BY r.appointment_date
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $reservations = $stmt->fetchAll();
}

// Fetch barbers
$stmt_barbers = $pdo->prepare("SELECT id, name FROM b_barbers ORDER BY name");
$stmt_barbers->execute();
$barbers = $stmt_barbers->fetchAll();

// Generate dates for the next 7 days, excluding Sundays
$dates = [];
for ($i = 0; $i < 7; $i++) {
    $current_date = (new DateTime())->add(new DateInterval("P{$i}D"));
    if ((int)$current_date->format('N') < 7) { // Exclude Sundays (N=7)
        $dates[] = $current_date;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barber Shop Reservation System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<h1>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'guest', ENT_QUOTES, 'UTF-8'); ?>!</h1>
<?php if (isset($_SESSION['username'])): ?>
<a href="reserve.php">Make a Reservation</a><br>
<?php endif; ?>