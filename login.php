<?php

session_start();

require_once 'db.php';
require_once 'functions.php';

/* ==========================================
   CHECK FORM SUBMISSION
   ========================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.html");
    exit();
}

/* ==========================================
   GET LOGIN DATA
   ========================================== */

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    showError("Please enter your email and password.");
}

/* ==========================================
   AUTHENTICATE USER
   ========================================== */

try {
    $conn = connect_db();

    $stmt = $conn->prepare(
        "SELECT id, full_name, email, password_hash, user_type 
         FROM users 
         WHERE email = ?"
    );

    if (!$stmt) {
        showError("Database error: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        showError("Invalid email or password.");
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if (!password_verify($password, $user['password_hash'])) {
        showError("Invalid password.");
    }

    // Save session variables consistently
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];

    $userType = strtolower(trim((string) ($user['user_type'] ?? '')));
    $_SESSION['user_type'] = $userType;
    $_SESSION['role']      = $userType;

    header("Location: index.php");
    exit();

} catch (Exception $e) {
    showError("Connection error: " . $e->getMessage());
}

/* ==========================================
   ERROR PAGE FUNCTION
   ========================================== */

function showError($message)
{
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Error - BookShare</title>
    <style>
        * { box-sizing: border-box; font-family: Arial, sans-serif; }
        body { margin: 0; min-height: 100vh; background: #f4f7fb; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .error-container { width: 100%; max-width: 450px; background: white; padding: 40px 30px; border-radius: 12px; text-align: center; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.10); }
        .error-icon { width: 65px; height: 65px; margin: 0 auto 20px; background: #dc2626; color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 32px; font-weight: bold; }
        h1 { margin: 0; color: #2563eb; font-size: 30px; }
        h2 { margin-top: 15px; color: #dc2626; font-size: 22px; }
        p { color: #666; font-size: 15px; line-height: 1.6; }
        .back-button { display: block; width: 100%; margin-top: 25px; padding: 13px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; }
        .back-button:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">!</div>
        <h1>BookShare</h1>
        <h2>Login Failed</h2>
        <p><?php echo htmlspecialchars($message); ?></p>
        <a href="login.html" class="back-button">← Back to Login</a>
    </div>
</body>
</html>
<?php
    exit();
}