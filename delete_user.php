<?php

session_start();

require_once 'db.php';
require_once 'functions.php';

/* ==========================================
   REQUIRE LOGIN
   ========================================== */

require_login();

/* ==========================================
   GET USER ID
   ========================================== */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid user ID.");
}

$user_id = (int) $_GET['id'];

/* ==========================================
   FIND USER IN DATABASE
   ========================================== */

$user = find_user_by_id($user_id);

if (!$user) {
    die("User not found.");
}

$email = $user['email'];
$user_type = $user['user_type'];

/* ==========================================
   DELETE USER
   ========================================== */

try {
    $conn = connect_db();

    /* ==========================================
       DELETE FROM BORROWERS IF CLIENT
       ========================================== */

    if ($user_type === 'client') {
        $stmt = $conn->prepare("DELETE FROM borrowers WHERE email = ?");

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();
        }
    }

    /* ==========================================
       DELETE FROM USERS
       ========================================== */

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");

    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        $stmt->close();
        die("Unable to delete user: " . $stmt->error);
    }

    $stmt->close();

    /* ==========================================
       RETURN TO MASTER LIST
       ========================================== */

    header("Location: master_list.php?success=User deleted successfully");
    exit();

} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

?>