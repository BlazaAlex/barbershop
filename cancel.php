<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reservation_id'])) {
    $reservation_id = (int)$_POST['reservation_id'];

    try {
        $stmt = $conn->prepare("DELETE FROM b_rezervace WHERE id = ?");
        if ($stmt->execute([$reservation_id])) {
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
        } else {
            echo "Error cancelling reservation.";
        }
    } catch (PDOException $e) {
        echo "Error cancelling reservation: " . $e->getMessage();
    }
}
?>
