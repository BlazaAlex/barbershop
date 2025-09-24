<?php
// reserve.php
session_start();
require 'db.php';
$pdo = getDbConnection();


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barber_id = isset($_POST['barber_id']) ? (int)$_POST['barber_id'] : 0;
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $service = $_POST['service'] ?? '';
    $user_id = (int)$_SESSION['user_id'];

    // Check if the selected date is a Sunday
    $selected_day = date('N', strtotime($appointment_date));
    if ($selected_day == 7) { // 7 means Sunday
        echo '<!DOCTYPE html>
        <html>
        <head>
            <link rel="stylesheet" type="text/css" href="style.css">
            <script type="text/javascript">
                setTimeout(function() {
                    window.location.href = "reserve.php";
                }, 3000);
            </script>
        </head>
        <body>
            <div id="myModal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <p>Sorry, we are closed on Sundays. Please choose another date.</p>
                </div>
            </div>
            <script>
                document.querySelector(".close").onclick = function() {
                    document.getElementById("myModal").style.display = "none";
                }
            </script>
        </body>
        </html>';
        exit;
    }

    // Check if user exists
    $user_check_stmt = $pdo->prepare("SELECT id FROM b_zakaznici WHERE id = :id");
    $user_check_stmt->execute([':id' => $user_id]);
    if ($user_check_stmt->rowCount() === 0) {
        echo "Error: User ID $user_id does not exist in the users table.";
        exit;
    }

    // Calculate end time and whether service needs two slots
    $start_time = new DateTime($appointment_time);
    $duration = ($service === "Střih a úprava vousů") ? 60 : 30;
    $end_time = clone $start_time;
    $end_time->add(new DateInterval("PT{$duration}M"));

    $appointment_datetime = $appointment_date . ' ' . $start_time->format('H:i');
    // For two-slot service compute second slot
    $second_slot_datetime = null;
    if ($duration === 60) {
        $second_slot = clone $start_time;
        $second_slot->add(new DateInterval('PT30M'));
        $second_slot_datetime = $appointment_date . ' ' . $second_slot->format('H:i');
    }

    // Check if selected slots are already taken.
    // We will check for any reservation that matches either slot (for 60min service) or the one slot (30min).
    $placeholders = [];
    $params = [':barber_id' => $barber_id];
    $placeholders[] = ':appt1';
    $params[':appt1'] = $appointment_datetime . ':00'; // ensure seconds portion
    if ($second_slot_datetime !== null) {
        $placeholders[] = ':appt2';
        $params[':appt2'] = $second_slot_datetime . ':00';
    }

    $in_clause = implode(',', $placeholders);
    $check_sql = "SELECT id FROM b_rezervace WHERE barber_id = :barber_id AND appointment_date IN ($in_clause)";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute($params);
    if ($check_stmt->rowCount() > 0) {
        // Slot taken
        echo '<!DOCTYPE html>
        <html>
        <head>
            <link rel="stylesheet" type="text/css" href="style.css">
            <script type="text/javascript">
                setTimeout(function() {
                    document.getElementById("myModal").style.display = "none";
                }, 3000);
            </script>
        </head>
        <body>
            <div id="myModal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <p>Sorry, the selected time slot is already taken. Please choose another time.</p>
                </div>
            </div>
            <script>
                document.querySelector(".close").onclick = function() {
                    document.getElementById("myModal").style.display = "none";
                }
            </script>
        </body>
        </html>';
    } else {
        // Insert reservation(s) using transaction
        try {
            $pdo->beginTransaction();
            $insert_sql = "INSERT INTO b_rezervace (user_id, barber_id, appointment_date, service) VALUES (:user_id, :barber_id, :appointment_date, :service)";
            $insert_stmt = $pdo->prepare($insert_sql);

            // Insert first slot
            $insert_stmt->execute([
                    ':user_id' => $user_id,
                    ':barber_id' => $barber_id,
                    ':appointment_date' => $appointment_datetime . ':00',
                    ':service' => $service
            ]);

            // Insert second slot if needed
            if ($second_slot_datetime !== null) {
                $insert_stmt->execute([
                        ':user_id' => $user_id,
                        ':barber_id' => $barber_id,
                        ':appointment_date' => $second_slot_datetime . ':00',
                        ':service' => $service
                ]);
            }

            $pdo->commit();

            echo '<!DOCTYPE html>
                    <html>
                    <head>
                        <link rel="stylesheet" type="text/css" href="style.css">
                        <script type="text/javascript">
                            setTimeout(function() {
                                window.location.href = "index.php";
                            }, 1000);
                        </script>
                    </head>
                    <body>
                        <div id="myModal" class="modal">
                            <div class="modal-content">
                                <span class="close">&times;</span>
                                <p>Reservation created!</p>
                            </div>
                        </div>

                        <script>
                            document.querySelector(".close").onclick = function() {
                                document.getElementById("myModal").style.display = "none";
                            }
                        </script>
                    </body>
                    </html>';
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "Error creating reservation.";
        }
    }
}

// Fetch available barbers
$stmt_barbers = $pdo->prepare("SELECT id, name FROM b_barbers ORDER BY name");
$stmt_barbers->execute();
$barbers = $stmt_barbers->fetchAll();

// Define available times (full and half hours from 10:00 to 20:00)
$times = [];
$start = new DateTime('10:00');
$end = new DateTime('20:00');
$interval = new DateInterval('PT30M');
$period = new DatePeriod($start, $interval, $end);
foreach ($period as $time) {
    $times[] = $time->format('H:i');
}
?>
<form method="post" action="reserve.php">
    <link rel="stylesheet" type="text/css" href="style.css">
    Barber Name:
    <select name="barber_id" required>
        <?php foreach ($barbers as $barber): ?>
            <option value="<?php echo htmlspecialchars($barber['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($barber['name'], ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
    </select><br>
    Appointment Date: <input
            type="date"
            name="appointment_date"
            min="<?php echo date('Y-m-d'); ?>"
            value="<?php echo isset($_POST['appointment_date']) ? htmlspecialchars($_POST['appointment_date'], ENT_QUOTES, 'UTF-8') : ''; ?>"
            required><br>
    Appointment Time:
    <select name="appointment_time" required>
        <?php foreach ($times as $time): ?>
            <option value="<?php echo htmlspecialchars($time, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($time, ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
    </select><br>
    Service:
    <select name="service" required>
        <option value="Klasický střih">Klasický střih (30min; 520 Kč)</option>
        <option value="Střih a úprava vousů">Střih a úprava vousů (1hod; 690 Kč)</option>
        <option value="Úprava vousů">Úprava vousů (30min; 420 Kč)</option>
        <option value="Holení Hot Towel">Holení Hot Towel (30min; 450 Kč)</option>
        <option value="Barvení vousů">Barvení vousů (30min; 390 Kč)</option>
        <option value="Dětský střih do 10 let">Dětský střih do 10 let (30min; 390 Kč)</option>
    </select><br>
    <input type="submit" value="Make Reservation">
    <a href="index.php">Go back</a>
</form>
