<?php
global $conn;
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Only if HTTPS is enabled
session_start();

include 'db.php';
/* Timeout implementation
    $timeout_duration = 1800;

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?message=session_expired");
    exit();
}
 * */

$_SESSION['last_activity'] = time();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $failed_attempts_key = "failed_attempts_{$username}";
    $lockout_time_key = "lockout_time_{$username}";

    if (isset($_SESSION[$failed_attempts_key]) && $_SESSION[$failed_attempts_key] >= 5) {
        if (time() - $_SESSION[$lockout_time_key] < 300) {
            echo "Too many login attempts. Please try again later.";
            exit();
        } else {
            unset($_SESSION[$failed_attempts_key], $_SESSION[$lockout_time_key]);
        }
    }

    $stmt = $conn->prepare("SELECT * FROM b_zakaznici WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            unset($_SESSION[$failed_attempts_key]);
            header("Location: index.php");
            exit();
        }
    }
    $_SESSION[$failed_attempts_key] = (isset($_SESSION[$failed_attempts_key]) ? $_SESSION[$failed_attempts_key] : 0) + 1;
    if ($_SESSION[$failed_attempts_key] >= 5) {
        $_SESSION[$lockout_time_key] = time();
    }
    echo "Invalid username or password";
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self';">
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<form method="post" action="login.php">
    Username: <input type="text" name="username" required><br>
    Password: <input type="password" name="password" required><br>
    <input type="submit" value="Login">
</form>
<p>Don't have an account? <a href="register.php">Register here</a></p>
</body>
</html>
