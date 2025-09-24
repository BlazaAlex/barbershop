<?php
session_start();
require 'db.php';
$pdo = getDbConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reservation_id'])) {
    $reservation_id = (int)$_POST['reservation_id'];

    // Fetch appointment time and user
    $stmt = $pdo->prepare("SELECT user_id, appointment_date FROM b_rezervace WHERE id = ?");
    $stmt->execute([$reservation_id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        echo "Reservation not found.";
        exit;
    }

    $appointment_time = new DateTime($reservation['appointment_date']);
    $now = new DateTime();

    // If not admin, check ownership and time restriction
    if (!$is_admin) {
        if ($reservation['user_id'] !== $_SESSION['user_id']) {
            echo "You are not allowed to cancel this reservation.";
            exit;
        }

        $diff = $now->diff($appointment_time);
        if ($appointment_time <= $now || $diff->days < 1) {
            echo "You cannot cancel less than 24 hours before your appointment. Contact the Administrator.";
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM b_rezervace WHERE id = ?");
        if ($stmt->execute([$reservation_id])) {
            header("Location: index.php?cancel=success");
            exit;
        } else {
            echo "Error cancelling reservation.";
        }
    } catch (PDOException $e) {
        echo "Error cancelling reservation: " . $e->getMessage();
    }
}
