<?php
// index.php
session_start();
require 'db.php';
$pdo = getDbConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// Fetch reservations
if ($is_admin) {
    $sql = "
        SELECT r.id, r.appointment_date, r.service, b.name AS barber_name, u.username AS customer_name
        FROM b_rezervace r
        JOIN b_barbers b ON r.barber_id = b.id
        JOIN b_zakaznici u ON r.user_id = u.id
        ORDER BY r.appointment_date
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} else {
    $sql = "
        SELECT r.id, r.appointment_date, r.service, b.name AS barber_name
        FROM b_rezervace r
        JOIN b_barbers b ON r.barber_id = b.id
        WHERE r.user_id = ?
        ORDER BY r.appointment_date
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
}
$reservations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barber Shop Reservation System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<h1>Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'guest') ?>!</h1>

<nav>
    <a href="reserve.php">Make a Reservation</a> |
    <a href="logout.php">Logout</a>
</nav>

<h2>Reservations</h2>
<?php if (count($reservations) > 0): ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
        <tr>
            <th>Date & Time</th>
            <th>Service</th>
            <th>Barber</th>
            <?php if ($is_admin): ?>
                <th>Customer</th>
            <?php endif; ?>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($reservations as $res): ?>
            <tr>
                <td><?= htmlspecialchars($res['appointment_date']) ?></td>
                <td><?= htmlspecialchars($res['service']) ?></td>
                <td><?= htmlspecialchars($res['barber_name']) ?></td>
                <?php if ($is_admin): ?>
                    <td><?= htmlspecialchars($res['customer_name']) ?></td>
                <?php endif; ?>
                <td>
                    <form action="cancel.php" method="POST" style="display:inline;">
                        <input type="hidden" name="reservation_id" value="<?= $res['id'] ?>">
                        <button type="submit">Cancel</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No reservations found.</p>
<?php endif; ?>
</body>
</html>
