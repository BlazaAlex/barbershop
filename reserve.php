<?php
global $conn;
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $barber_id = $_POST['barber_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $service = $_POST['service'];
    $user_id = $_SESSION['user_id'];

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
                }, 3000); // 3000 milliseconds = 3 seconds
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

    // Check if user_id exists in the users table
    $user_check_sql = "SELECT id FROM b_zakaznici WHERE id = ?";
    $user_check_stmt = $conn->prepare($user_check_sql);
    $user_check_stmt->bind_param('i', $user_id);
    $user_check_stmt->execute();
    $user_check_result = $user_check_stmt->get_result();

    if ($user_check_result->num_rows === 0) {
        echo "Error: User ID $user_id does not exist in the users table.";
        exit;
    }

    // Calculate end time and check if service needs two slots
    $start_time = new DateTime($appointment_time);
    $duration = ($service == "Střih a úprava vousů") ? 60 : 30; // 60 minutes for this service
    $end_time = clone $start_time;
    $end_time->add(new DateInterval("PT{$duration}M"));

    $appointment_datetime = $appointment_date . ' ' . $appointment_time;
    $end_time_str = $end_time->format('H:i');

    // Check if the selected slots are already taken
    $check_sql = "SELECT id FROM b_rezervace 
                  WHERE barber_id = ? 
                  AND appointment_date BETWEEN ? AND ?";
    $check_stmt = $conn->prepare($check_sql);
    $str = $appointment_date . ' ' . $end_time_str;
    $check_stmt->bind_param('iss', $barber_id, $appointment_datetime, $str);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // If the slot is already taken, show an error message
        echo '<!DOCTYPE html>
        <html>
        <head>
            <link rel="stylesheet" type="text/css" href="style.css">
            <script type="text/javascript">
                setTimeout(function() {
		        document.getElementById("myModal").style.display = "none";
                }, 3000); // 3000 milliseconds = 3 seconds
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

        // Insert reservation(s)
        $sql = "INSERT INTO b_rezervace (user_id, barber_id, appointment_date, service) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iiss', $user_id, $barber_id, $appointment_datetime, $service);

        if ($service == "Střih a úprava vousů") {
            // Insert two slots for this service
            $stmt->execute();

            $second_slot_time = clone $start_time;
            $second_slot_time->add(new DateInterval('PT30M'));
            $second_slot_datetime = $appointment_date . ' ' . $second_slot_time->format('H:i');
            $stmt->bind_param('iiss', $user_id, $barber_id, $second_slot_datetime, $service);
            $stmt->execute();
        } else {
            // Insert one slot for standard services
            $stmt->execute();
        }

        echo '<!DOCTYPE html>
                    <html>
                    <head>
                        <link rel="stylesheet" type="text/css" href="style.css">
                        <script type="text/javascript">
                            setTimeout(function() {
                                window.location.href = "index.php";
                            }, 1000); // 1000 milliseconds = 1 second
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
    }
}

// Fetch available barbers
$sql_barbers = "SELECT id, name FROM b_barbers ORDER BY name";
$result_barbers = $conn->query($sql_barbers);

$barbers = [];
if ($result_barbers->num_rows > 0) {
    while ($row = $result_barbers->fetch_assoc()) {
        $barbers[] = $row;
    }
}

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
            <option value="<?php echo $barber['id']; ?>"><?php echo $barber['name']; ?></option>
        <?php endforeach; ?>
    </select><br>
    Appointment Date: <input
            type="date"
            name="appointment_date"
            min="<?php echo date('Y-m-d'); ?>"
            value="<?php echo isset($_POST['appointment_date']) ? $_POST['appointment_date'] : ''; ?>"
            required><br>
    Appointment Time:
    <select name="appointment_time" required>
        <?php foreach ($times as $time): ?>
            <option value="<?php echo $time; ?>"><?php echo $time; ?></option>
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
