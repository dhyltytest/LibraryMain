<?php

session_start();

require_once 'db.php';
require_once 'functions.php';

/* ==========================================
   REQUIRE LOGIN AND CLIENT ROLE
   ========================================== */

require_login();

if (session_user_type() !== 'client') {
    header("Location: index.php");
    exit();
}

$clientName = $_SESSION['full_name'] ?? '';
$clientEmail = $_SESSION['email'] ?? '';
$initial = strtoupper(substr($clientName, 0, 1));

?>