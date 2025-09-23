<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reservation_id'])) {
    $reservation_id = $_POST['reservation_id'];

    // Delete reservation
    $sql = "DELETE FROM b_rezervace WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $reservation_id);

    if ($stmt->execute()) {
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
    } else {
        echo "Error cancelling reservation: " . $conn->error;
    }

    $stmt->close();
}
?>
