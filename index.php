<?php
// index.php
session_start();
require 'db.php';

$pdo = getDbConnection();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// --- Prepare dates ---
$today = (new DateTime())->format('Y-m-d');

// --- Fetch today's reservations ---
if ($is_admin) {
    $sql_today = "SELECT r.id, r.appointment_date, r.service, b.name AS barber_name,
                  u.username AS customer_name, u.email, u.phone, u.name AS customer_firstname,
                  u.surname AS customer_surname
                  FROM b_rezervace r
                  JOIN b_barbers b ON r.barber_id = b.id
                  JOIN b_zakaznici u ON r.user_id = u.id
                  WHERE DATE(r.appointment_date) = :today
                  ORDER BY r.appointment_date";
    $stmt_today = $pdo->prepare($sql_today);
    $stmt_today->execute([':today' => $today]);
} else {
    $sql_today = "SELECT r.id, r.appointment_date, r.service, b.name AS barber_name
                  FROM b_rezervace r
                  JOIN b_barbers b ON r.barber_id = b.id
                  WHERE r.user_id = :user_id AND DATE(r.appointment_date) = :today
                  ORDER BY r.appointment_date";
    $stmt_today = $pdo->prepare($sql_today);
    $stmt_today->execute([
            ':user_id' => $_SESSION['user_id'],
            ':today' => $today
    ]);
}
$today_reservations = $stmt_today->fetchAll();

// --- Fetch barbers ---
$stmt_barbers = $pdo->prepare("SELECT id, name FROM b_barbers ORDER BY name");
$stmt_barbers->execute();
$barbers = $stmt_barbers->fetchAll(PDO::FETCH_KEY_PAIR);

// --- Weekly schedule (admins only) ---
$week_schedule = [];
if ($is_admin) {
    $sql_week = "SELECT r.id, r.appointment_date, r.service, r.barber_id, u.username AS customer_name
                 FROM b_rezervace r
                 JOIN b_zakaznici u ON r.user_id = u.id
                 WHERE DATE(r.appointment_date) > :today
                 ORDER BY r.appointment_date";
    $stmt_week = $pdo->prepare($sql_week);
    $stmt_week->execute([':today' => $today]);
    $week_reservations_raw = $stmt_week->fetchAll();

    foreach ($week_reservations_raw as $res) {
        $dt = new DateTime($res['appointment_date']);
        $date = $dt->format('Y-m-d');
        $time = $dt->format('H:i');
        $week_schedule[$date][$time][$res['barber_id']] = $res['customer_name'] . " (" . $res['service'] . ")";
    }
}

// --- Time slots ---
$time_slots = [];
$start_time = new DateTime('10:00');
$end_time = new DateTime('20:00');
$interval = new DateInterval('PT30M');
$period = new DatePeriod($start_time, $interval, $end_time);
foreach ($period as $time) {
    $time_slots[] = $time->format('H:i');
}

// --- Helper: can cancel ---
function canCancel($res, $is_admin) {
    if ($is_admin) return true;
    $now = new DateTime();
    $appt_time = new DateTime($res['appointment_date']);
    $diff = $now->diff($appt_time);
    return ($appt_time > $now && $diff->days >= 1);
}

// --- Prepare next 7 days (excluding Sunday) ---
$week_days = [];
$now = new DateTime();
for ($i = 0; $i < 7; $i++) {
    $d = (clone $now)->add(new DateInterval("P{$i}D"));
    if ((int)$d->format('N') < 7) {
        $week_days[$d->format('Y-m-d')] = $d->format('D, d M');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barber Shop Reservation</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .contact-info { display: none; margin-top: 5px; padding: 5px; border: 1px solid #ccc; background: #f9f9f9; font-size: 0.9em; }
        .contact-btn { margin-top: 3px; display: inline-block; padding: 3px 6px; background: #007BFF; color: #fff; border: none; border-radius: 3px; cursor: pointer; }
        .contact-btn:hover { background: #0056b3; }
        table { border-collapse: collapse; margin-bottom: 20px; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: top; }
        td.booked { background: #ffd; }
    </style>
    <script>
        function toggleContact(id) {
            const elem = document.getElementById(id);
            elem.style.display = (elem.style.display === 'block') ? 'none' : 'block';
        }
    </script>
</head>
<body>
<h1>Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'guest') ?>!</h1>
<nav>
    <a href="reserve.php">Make a Reservation</a> |
    <a href="logout.php">Logout</a>
</nav>

<h2>Today's Reservations</h2>
<?php if ($today_reservations): ?>
    <table>
        <thead>
        <tr>
            <th>Date & Time</th>
            <th>Service</th>
            <th>Barber</th>
            <?php if ($is_admin) echo '<th>Customer</th>'; ?>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($today_reservations as $index => $res): ?>
            <tr>
                <td><?= htmlspecialchars($res['appointment_date']) ?></td>
                <td><?= htmlspecialchars($res['service']) ?></td>
                <td><?= htmlspecialchars($res['barber_name']) ?></td>
                <?php if ($is_admin): ?>
                    <td>
                        <?= htmlspecialchars($res['customer_name']) ?><br>
                        <button class="contact-btn" onclick="toggleContact('cinfo<?= $index ?>')">Contact Info</button>
                        <div id="cinfo<?= $index ?>" class="contact-info">
                            <p>Email: <?= htmlspecialchars($res['email'] ?? '') ?></p>
                            <p>Phone: <?= htmlspecialchars($res['phone'] ?? '') ?></p>
                            <p>Full Name: <?= htmlspecialchars(($res['customer_firstname'] ?? '') . ' ' . ($res['customer_surname'] ?? '')) ?></p>
                        </div>
                    </td>
                <?php endif; ?>
                <td>
                    <?php if (canCancel($res, $is_admin)): ?>
                        <form action="cancel.php" method="POST" style="display:inline;">
                            <input type="hidden" name="reservation_id" value="<?= $res['id'] ?>">
                            <button type="submit">Cancel</button>
                        </form>
                    <?php else: ?>
                        <span>Cannot cancel</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No reservations for today.</p>
<?php endif; ?>

<?php if ($is_admin && !empty($week_schedule)): ?>
    <h2>Weekly Schedule</h2>
    <table>
        <thead>
        <tr>
            <th>Day / Hour</th>
            <?php foreach ($time_slots as $time) echo "<th>$time</th>"; ?>
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
                        <td><?= $week_schedule[$date][$time][$barber_id] ?? '' ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</body>
</html>
