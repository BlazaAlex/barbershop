<?php
global $conn;
session_start();
include 'db.php';

// Define time slots (for example, every hour from 9 AM to 5 PM)
$start_time = new DateTime('09:00');
$end_time = new DateTime('17:00');
$interval = new DateInterval('PT1H'); // 1 hour interval

$time_slots = [];
$period = new DatePeriod($start_time, $interval, $end_time);
foreach ($period as $time) {
    $time_slots[] = $time;
}

// Fetch reservations with barber names
$sql_reservations = "
    SELECT r.user_id, r.appointment_date, r.service, b.name as barber_name 
    FROM b_rezervace r
    JOIN b_barbers b ON r.barber_id = b.id
    ORDER BY r.appointment_date
";
$result_reservations = $conn->query($sql_reservations);

$reservations = [];
if ($result_reservations->num_rows > 0) {
    while($row = $result_reservations->fetch_assoc()) {
        $reservations[] = $row;
    }
}

// Fetch barbers
$sql_barbers = "SELECT id, name FROM b_barbers ORDER BY name";
$result_barbers = $conn->query($sql_barbers);

$barbers = [];
if ($result_barbers->num_rows > 0) {
    while($row = $result_barbers->fetch_assoc()) {
        $barbers[] = $row;
    }
}

// Debug: Print fetched reservations and barbers
echo "<pre>";
print_r($reservations);
print_r($barbers);
echo "</pre>";
?>

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barber Shop Reservation System</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        .occupied {
            background-color: #ffdddd;
        }
        .free {
            background-color: #ddffdd;
        }
    </style>
</head>
<body>
<h1>Welcome, <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest'; ?>!</h1>
<?php if (isset($_SESSION['username'])): ?>
    <a href="reserve.php">Make a Reservation</a><br>
    <a href="logout.php">Logout</a>
<?php else: ?>
    <a href="login.php">Login</a>
    <a href="register.php">Register</a>
<?php endif; ?>

<h2>Reservations</h2>
<table>
    <thead>
    <tr>
        <th>Barber Name</th>
        <?php foreach ($time_slots as $slot): ?>
            <th><?php echo $slot->format('H:i'); ?></th>
        <?php endforeach; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($barbers as $barber): ?>
        <tr>
            <td><?php echo $barber['name']; ?></td>
            <?php foreach ($time_slots as $slot): ?>
                <?php
                $slot_str = $slot->format('Y-m-d H:i:s');
                $found = false;
                foreach ($reservations as $reservation) {
                    if ($reservation['barber_name'] == $barber['name'] && (new DateTime($reservation['appointment_date']))->format('Y-m-d H:i:s') == $slot_str) {
                        echo "<td class='occupied'>{$reservation['service']}</td>";
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    echo "<td class='free'>Free</td>";
                }
                ?>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
