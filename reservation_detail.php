<?php
global $conn;
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    echo "No reservation ID provided.";
    exit;
}

$reservation_id = $_GET['id'];

// Fetch reservation details using a prepared statement
$sql = "
    SELECT r.id, r.appointment_date, r.service, b.name as barber_name, u.username as customer_name, u.email, u.phone, u.name, u.surname 
    FROM b_rezervace r
    JOIN b_barbers b ON r.barber_id = b.id
    JOIN b_zakaznici u ON r.user_id = u.id
    WHERE r.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "No reservation found with that ID.";
    exit;
}

$reservation = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel'])) {
    // Delete reservation using a prepared statement
    $sql_delete = "DELETE FROM b_rezervace WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $reservation_id);
    if ($stmt_delete->execute()) {
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
            <!-- Modal -->
            <div id="myModal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <p>Reservation cancelled successfully!</p>
                </div>
            </div>

            <script>
                // Close the modal if the user clicks on the close button
                document.querySelector(".close").onclick = function() {
                    document.getElementById("myModal").style.display = "none";
                }
            </script>
        </body>
        </html>';
        exit;
    } else {
        echo "Error deleting reservation: " . $stmt_delete->error;
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
<p>Customer Name: <?php echo htmlspecialchars($reservation['customer_name']); ?></p>
<p>Customer Email: <?php echo htmlspecialchars($reservation['email']); ?></p>
<p>Customer Phone: <?php echo htmlspecialchars($reservation['phone']); ?></p>
<p>Customer Full Name: <?php echo htmlspecialchars($reservation['name']) . ' ' . htmlspecialchars($reservation['surname']); ?></p>
<p>Barber Name: <?php echo htmlspecialchars($reservation['barber_name']); ?></p>
<p>Appointment Date: <?php echo htmlspecialchars($reservation['appointment_date']); ?></p>
<p>Service: <?php echo htmlspecialchars($reservation['service']); ?></p>

<form method="post" action="reservation_detail.php?id=<?php echo htmlspecialchars($reservation_id); ?>">
    <input type="submit" name="cancel" value="Cancel Reservation">
</form>

<a href="index.php">Back to Home</a>
</body>
</html>
