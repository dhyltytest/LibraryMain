<?php

require_once 'db.php';
require_once 'functions.php';

/* ==========================================
   CHECK FORM SUBMISSION
   ========================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.html");
    exit();
}

/* ==========================================
   GET FORM DATA
   ========================================== */

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$organization = trim($_POST['organization'] ?? '');
$user_type = trim($_POST['role'] ?? '');

/* ==========================================
   VALIDATION
   ========================================== */

if (
    $full_name === '' ||
    $email === '' ||
    $password === '' ||
    $user_type === ''
) {
    die("Please complete all required fields.");
}

if (
    $user_type !== 'admin' &&
    $user_type !== 'client'
) {
    die("Invalid user type.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Please enter a valid email address.");
}

/* ==========================================
   REGISTER USER
   ========================================== */

$result = register_user($full_name, $email, $password, $phone, $organization, $user_type);

if (!$result['success']) {
    die("Registration failed: " . $result['message']);
}

/* ==========================================
   ADD CLIENT TO BORROWERS TABLE
   ========================================== */

if ($user_type === 'client') {
    try {
        $conn = connect_db();
        $stmt = $conn->prepare(
            "INSERT INTO borrowers
            (full_name, email, phone, organization)
            VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssss", $full_name, $email, $phone, $organization);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        die("Client registration failed: " . $e->getMessage());
    }
}

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Registration Successful - BookShare
    </title>


    <style>

        * {

            box-sizing: border-box;

            font-family: Arial, sans-serif;

        }


        body {

            margin: 0;

            min-height: 100vh;

            background: #f4f7fb;

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 20px;

        }


        .success-container {

            width: 100%;

            max-width: 500px;

            background: white;

            padding: 45px 35px;

            border-radius: 12px;

            text-align: center;

            box-shadow:
                0 8px 25px
                rgba(0, 0, 0, 0.10);

        }


        .checkmark {

            width: 70px;

            height: 70px;

            margin: 0 auto 20px;

            background: #22c55e;

            color: white;

            border-radius: 50%;

            display: flex;

            justify-content: center;

            align-items: center;

            font-size: 38px;

            font-weight: bold;

        }


        h1 {

            margin: 0;

            color: #2563eb;

            font-size: 30px;

        }


        h2 {

            margin-top: 15px;

            color: #222;

            font-size: 22px;

        }


        p {

            color: #666;

            font-size: 15px;

            line-height: 1.6;

        }


        .login-button {

            display: block;

            width: 100%;

            margin-top: 25px;

            padding: 13px;

            background: #2563eb;

            color: white;

            text-decoration: none;

            border-radius: 6px;

            font-weight: bold;

            font-size: 16px;

        }


        .login-button:hover {

            background: #1d4ed8;

        }


        @media (max-width: 600px) {

            .success-container {

                padding: 35px 25px;

            }


            h1 {

                font-size: 26px;

            }


            h2 {

                font-size: 20px;

            }

        }

    </style>

</head>


<body>


    <div class="success-container">


        <div class="checkmark">

            ✓

        </div>


        <h1>

            BookShare

        </h1>


        <h2>

            Registration Successful!

        </h2>


        <p>

            Your account has been successfully created.

            You can now log in using the email address
            and password you registered with.

        </p>


        <a
            href="login.html"
            class="login-button"
        >

            Go to Login

        </a>


    </div>


</body>

</html>