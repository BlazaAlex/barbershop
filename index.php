<?php
session_start();
require 'db.php';
$pdo = getDbConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// Get today's date
$today = (new DateTime())->format('Y-m-d');

// Fetch reservations for today (top table)
if ($is_admin) {
    $sql_today = "
        SELECT r.id, r.appointment_date, r.service, b.name AS barber_name,
               u.username AS customer_name, u.email, u.phone, u.name AS customer_firstname, u.surname AS customer_surname
        FROM b_rezervace r
        JOIN b_barbers b ON r.barber_id = b.id
        JOIN b_zakaznici u ON r.user_id = u.id
        WHERE DATE(r.appointment_date) = :today
        ORDER BY r.appointment_date
    ";
    $stmt_today = $pdo->prepare($sql_today);
    $stmt_today->execute([':today' => $today]);
} else {
    $sql_today = "
        SELECT r.id, r.appointment_date, r.service, b.name AS barber_name
        FROM b_rezervace r
        JOIN b_barbers b ON r.barber_id = b.id
        WHERE r.user_id = :user_id AND DATE(r.appointment_date) = :today
        ORDER BY r.appointment_date
    ";
    $stmt_today = $pdo->prepare($sql_today);
    $stmt_today->execute([':user_id' => $_SESSION['user_id'], ':today' => $today]);
}
$today_reservations = $stmt_today->fetchAll();

// Fetch all barbers (needed for timetable)
$stmt_barbers = $pdo->prepare("SELECT id, name FROM b_barbers ORDER BY name");
$stmt_barbers->execute();
$barbers = $stmt_barbers->fetchAll(PDO::FETCH_KEY_PAIR); // id => name

// Combine today and upcoming week reservations for timetable (admins only)
$week_schedule = [];
if ($is_admin) {
    $sql_week = "
        SELECT r.id, r.appointment_date, r.service, r.barber_id,
               u.username AS customer_name, u.email, u.phone, u.name AS customer_firstname, u.surname AS customer_surname
        FROM b_rezervace r
        JOIN b_zakaznici u ON r.user_id = u.id
        WHERE DATE(r.appointment_date) >= :today
        ORDER BY r.appointment_date
    ";
    $stmt_week = $pdo->prepare($sql_week);
    $stmt_week->execute([':today' => $today]);
    $week_reservations_raw = $stmt_week->fetchAll();

    foreach ($week_reservations_raw as $res) {
        $dt = new DateTime($res['appointment_date']);
        $date = $dt->format('Y-m-d');
        $time = $dt->format('H:i');
        $week_schedule[$date][$time][$res['barber_id']] = $res['customer_name']
                . " (" . $res['service'] . ")"
                . " | " . ($res['email'] ?? '')
                . " | " . ($res['phone'] ?? '');
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
    <style>
        .contact-info { display: none; margin-top: 5px; padding: 5px; border: 1px solid #ccc; background: #f9f9f9; font-size: 0.9em; }
        .contact-btn { margin-top: 3px; display: inline-block; padding: 3px 6px; background: #007BFF; color: #fff; border: none; border-radius: 3px; cursor: pointer; }
        .contact-btn:hover { background: #0056b3; }
        table { border-collapse: collapse; margin-bottom: 20px; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: top; }
        td.booked { background: #ffd; }
    </style>
</head>
<body>

<h1>Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'guest') ?>!</h1>

<nav>
    <a href="reserve.php">Make a Reservation</a> |
    <a href="logout.php">Logout</a>
</nav>

<?php if ($is_admin && $week_schedule): ?>
    <h2>Weekly Schedule (Including Today)</h2>
    <table>
        <thead>
        <tr>
            <th>Day / Barber</th>
            <?php foreach ($time_slots as $time): ?>
                <th><?= $time ?></th>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($week_days as $date => $day_label): ?>
            <?php foreach ($barbers as $barber_id => $barber_name): ?>
                <tr>
                    <?php if ($barber_id === array_key_first($barbers)): ?>
                        <td rowspan="<?= count($barbers) ?>"><?= $day_label ?></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($barber_name) ?></td>
                    <?php foreach ($time_slots as $time): ?>
                        <?php
                        $cell_text = $week_schedule[$date][$time][$barber_id] ?? '';
                        $cell_class = $cell_text ? 'booked' : '';
                        ?>
                        <td class="<?= $cell_class ?>" <?= $cell_class ? "title='" . htmlspecialchars($cell_text, ENT_QUOTES) . "'" : "" ?>>
                            <?= htmlspecialchars($cell_text ? $res['customer_name'] . " (" . $res['service'] . ")" : '') ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
