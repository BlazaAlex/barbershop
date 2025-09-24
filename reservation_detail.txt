<?php
// reservation_detail.php
session_start();
require 'db.php';
$pdo = getDbConnection();


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "No reservation ID provided.";
    exit;
}

$reservation_id = (int)$_GET['id'];

// Fetch reservation details
$sql = "
    SELECT r.id, r.appointment_date, r.service, b.name AS barber_name,
           u.username AS customer_name, u.email, u.phone, u.name AS customer_firstname, u.surname AS customer_surname
    FROM b_rezervace r
    JOIN b_barbers b ON r.barber_id = b.id
    JOIN b_zakaznici u ON r.user_id = u.id
    WHERE r.id = :id
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $reservation_id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    echo "No reservation found with that ID.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    $sql_delete = "DELETE FROM b_rezervace WHERE id = :id";
    $stmt_delete = $pdo->prepare($sql_delete);
    try {
        $stmt_delete->execute([':id' => $reservation_id]);
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
                    <p>Reservation cancelled successfully!</p>
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
    } catch (PDOException $e) {
        echo "Error deleting reservation.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Details</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<h1>Reservation Details</h1>
<p>Customer Name: <?php echo htmlspecialchars($reservation['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
<p>Customer Email: <?php echo htmlspecialchars($reservation['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
<p>Customer Phone: <?php echo htmlspecialchars($reservation['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
<p>Customer Full Name: <?php echo htmlspecialchars(($reservation['customer_firstname'] ?? '') . ' ' . ($reservation['customer_surname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
<p>Barber Name: <?php echo htmlspecialchars($reservation['barber_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
<p>Appointment Date: <?php echo htmlspecialchars($reservation['appointment_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
<p>Service: <?php echo htmlspecialchars($reservation['service'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>

<form method="post" action="reservation_detail.php?id=<?php echo htmlspecialchars($reservation_id, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="submit" name="cancel" value="Cancel Reservation">
</form>

<a href="index.php">Back to Home</a>
</body>
</html>
