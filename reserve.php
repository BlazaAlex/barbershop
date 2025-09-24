<?php
session_start();
require 'db.php';
$pdo = getDbConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barber_id = isset($_POST['barber_id']) ? (int)$_POST['barber_id'] : 0;
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $service = $_POST['service'] ?? '';
    $user_id = $is_admin && !empty($_POST['user_id']) ? (int)$_POST['user_id'] : (int)$_SESSION['user_id'];

    // Check if the selected date is a Sunday
    $selected_day = date('N', strtotime($appointment_date));
    if ($selected_day == 7) {
        echo '<p>Sorry, we are closed on Sundays. Please choose another date.</p>';
        exit;
    }

    // Verify that user exists
    $user_check_stmt = $pdo->prepare("SELECT id FROM b_zakaznici WHERE id = :id");
    $user_check_stmt->execute([':id' => $user_id]);
    if ($user_check_stmt->rowCount() === 0) {
        echo "Error: User ID $user_id does not exist.";
        exit;
    }

    // Duration
    $start_time = new DateTime($appointment_time);
    $duration = ($service === "Střih a úprava vousů") ? 60 : 30;
    $end_time = clone $start_time;
    $end_time->add(new DateInterval("PT{$duration}M"));

    $appointment_datetime = $appointment_date . ' ' . $start_time->format('H:i');
    $second_slot_datetime = null;
    if ($duration === 60) {
        $second_slot = clone $start_time;
        $second_slot->add(new DateInterval('PT30M'));
        $second_slot_datetime = $appointment_date . ' ' . $second_slot->format('H:i');
    }

    // Check conflicts
    $params = [':barber_id' => $barber_id, ':appt1' => $appointment_datetime . ':00'];
    $placeholders = [':appt1'];
    if ($second_slot_datetime !== null) {
        $params[':appt2'] = $second_slot_datetime . ':00';
        $placeholders[] = ':appt2';
    }
    $check_sql = "SELECT id FROM b_rezervace WHERE barber_id = :barber_id AND appointment_date IN (" . implode(',', $placeholders) . ")";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute($params);

    if ($check_stmt->rowCount() > 0) {
        echo '<p>Sorry, the selected time slot is already taken. Please choose another time.</p>';
    } else {
        try {
            $pdo->beginTransaction();
            $insert_sql = "INSERT INTO b_rezervace (user_id, barber_id, appointment_date, service)
                           VALUES (:user_id, :barber_id, :appointment_date, :service)";
            $insert_stmt = $pdo->prepare($insert_sql);

            // First slot
            $insert_stmt->execute([
                    ':user_id' => $user_id,
                    ':barber_id' => $barber_id,
                    ':appointment_date' => $appointment_datetime . ':00',
                    ':service' => $service
            ]);

            // Second slot if needed
            if ($second_slot_datetime !== null) {
                $insert_stmt->execute([
                        ':user_id' => $user_id,
                        ':barber_id' => $barber_id,
                        ':appointment_date' => $second_slot_datetime . ':00',
                        ':service' => $service
                ]);
            }

            $pdo->commit();
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "Error creating reservation: " . $e->getMessage();
        }
    }
}

// Barbers
$stmt_barbers = $pdo->prepare("SELECT id, name FROM b_barbers ORDER BY name");
$stmt_barbers->execute();
$barbers = $stmt_barbers->fetchAll();

// Times
$times = [];
$start = new DateTime('10:00');
$end = new DateTime('20:00');
$interval = new DateInterval('PT30M');
$period = new DatePeriod($start, $interval, $end);
foreach ($period as $time) {
    $times[] = $time->format('H:i');
}

// If admin, fetch customers
$customers = [];
if ($is_admin) {
    $stmt_customers = $pdo->query("SELECT id, username FROM b_zakaznici ORDER BY username");
    $customers = $stmt_customers->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Make a Reservation</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav>
    <a href="index.php">Home</a> |
    <a href="logout.php">Logout</a>
</nav>

<h2>Make a Reservation</h2>
<form method="post" action="reserve.php">
    <label>Barber:
        <select name="barber_id" required>
            <?php foreach ($barbers as $barber): ?>
                <option value="<?= htmlspecialchars($barber['id']) ?>"><?= htmlspecialchars($barber['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label><br>

    <?php if ($is_admin): ?>
        <label>Customer:
            <select name="user_id" required>
                <?php foreach ($customers as $cust): ?>
                    <option value="<?= htmlspecialchars($cust['id']) ?>"><?= htmlspecialchars($cust['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </label><br>
    <?php endif; ?>

    <label>Date:
        <input type="date" name="appointment_date" min="<?= date('Y-m-d') ?>" required>
    </label><br>

    <label>Time:
        <select name="appointment_time" required>
            <?php foreach ($times as $time): ?>
                <option value="<?= htmlspecialchars($time) ?>"><?= htmlspecialchars($time) ?></option>
            <?php endforeach; ?>
        </select>
    </label><br>

    <label>Service:
        <select name="service" required>
            <option value="Klasický střih">Klasický střih (30min; 520 Kč)</option>
            <option value="Střih a úprava vousů">Střih a úprava vousů (1hod; 690 Kč)</option>
            <option value="Úprava vousů">Úprava vousů (30min; 420 Kč)</option>
            <option value="Holení Hot Towel">Holení Hot Towel (30min; 450 Kč)</option>
            <option value="Barvení vousů">Barvení vousů (30min; 390 Kč)</option>
            <option value="Dětský střih do 10 let">Dětský střih do 10 let (30min; 390 Kč)</option>
        </select>
    </label><br>

    <input type="submit" value="Make Reservation">
    <a href="index.php">Cancel</a>
</form>
</body>
</html>
