<?php
global $conn;
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify Cloudflare Turnstile
    $secret_key = '0x4AAAAAAA1Y3nD2lDebWN-bjwdj4yLVwhQ'; // Replace with your Turnstile secret key
    $response = $_POST['cf-turnstile-response']; // Turnstile's response parameter
    $remote_ip = $_SERVER['REMOTE_ADDR'];

    // API URL to verify Turnstile
    $verify_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret' => $secret_key,
        'response' => $response,
        'remoteip' => $remote_ip
    ];

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verify_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    // Execute cURL request
    $result = curl_exec($ch);

    // Check if cURL request was successful
    if (curl_errno($ch)) {
        die('Error: "' . curl_error($ch) . '" - Code: ' . curl_errno($ch));
    }

    // Close cURL connection
    curl_close($ch);

    $result_json = json_decode($result);

    // Check if Turnstile verification was successful
    if (!$result_json->success) {
        die('Error: Turnstile verification failed. Please try again.');
    }

    // Proceed with registration logic (insert into the database, etc.)
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $name = $_POST['name'];
    $surname = $_POST['surname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $dial_code = $_POST['dial_code'];

    // Prepare and execute the query
    $sql = "INSERT INTO b_zakaznici (username, password, name, surname, email, phone) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $username, $password, $name, $surname, $email, $phone);
    if ($stmt->execute()) {
        echo '<!DOCTYPE html>
    <html>
    <head>
        <link rel="stylesheet" type="text/css" href="style.css">
        <script type="text/javascript">
            setTimeout(function() {
                window.location.href = "login.php";
            }, 3000);
        </script>
    </head>
    <body>
        <div id="myModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <p>Registration successful! You will be redirected to the login page in 3 seconds.</p>
            </div>
        </div>
    </body>
    </html>';
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<form method="post" action="register.php">
    <link rel="stylesheet" type="text/css" href="style.css">
    Name: <input type="text" name="name" required><br>
    Surname: <input type="text" name="surname" required><br>
    Username: <input type="text" name="username" required><br>
    Password: <input type="password" name="password" required><br>
    Email: <input type="email" name="email" required><br>
    Phone: <input type="text" name="phone" required><br>
    Dial Code:
    <select name="dial_code" required>
        <option value="+420">+420 (Czechia)</option>
        <option value="+1">+1 (USA)</option>
    </select><br>
    <div class="cf-turnstile" data-sitekey="0x4AAAAAAA1Y3ugzlmm1u2oc"></div> <!-- Replace YOUR_SITE_KEY -->
    <input type="submit" value="Register">
    <a href="login.php">Go back to main site</a>
</form>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
