<?php

session_start();

require_once 'db.php';
require_once 'functions.php';

/* ==========================================
   REQUIRE LOGIN
   ========================================== */

require_login();

/* ==========================================
   CHECK USER ID
   ========================================== */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid user ID.");
}

$user_id = (int) $_GET['id'];

/* ==========================================
   GET USER FROM DATABASE
   ========================================== */

$user = find_user_by_id($user_id);

if (!$user) {
    die("User not found.");
}

/* ==========================================
   SAVE CHANGES
   ========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $user_type = trim($_POST['user_type'] ?? '');

    if ($full_name === '' || $email === '' || $user_type === '') {
        die("Please complete all required fields.");
    }

    if ($user_type !== 'admin' && $user_type !== 'client') {
        die("Invalid user type.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Please enter a valid email address.");
    }

    try {
        $conn = connect_db();

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $duplicate = $stmt->get_result();

        if ($duplicate->num_rows > 0) {
            $stmt->close();
            die("That email address is already being used.");
        }

        $stmt->close();

        $old_email = $user['email'];

        $stmt = $conn->prepare(
            "UPDATE users
            SET full_name = ?, email = ?, phone = ?, organization = ?, user_type = ?
            WHERE id = ?"
        );

        $stmt->bind_param("sssssi", $full_name, $email, $phone, $organization, $user_type, $user_id);

        if (!$stmt->execute()) {
            $stmt->close();
            die("Unable to update user: " . $stmt->error);
        }

        $stmt->close();

        if ($user_type === 'client') {
            $stmt = $conn->prepare(
                "UPDATE borrowers
                SET full_name = ?, email = ?, phone = ?, organization = ?
                WHERE email = ?"
            );

            if ($stmt) {
                $stmt->bind_param("sssss", $full_name, $email, $phone, $organization, $old_email);
                $stmt->execute();
                $stmt->close();
            }
        }

        if ($user_type === 'admin') {
            $stmt = $conn->prepare("DELETE FROM borrowers WHERE email = ?");

            if ($stmt) {
                $stmt->bind_param("s", $old_email);
                $stmt->execute();
                $stmt->close();
            }
        }

        header("Location: master_list.php?success=User updated successfully");
        exit();

    } catch (Exception $e) {
        die("Database error: " . $e->getMessage());
    }
}
?>
<!-- HTML Form content follows here -->