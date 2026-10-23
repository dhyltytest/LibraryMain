<?php

session_start();

require_once 'config.php';
require_once 'functions.php';


/* ==========================================
   CHECK IF USER IS LOGGED IN
   ========================================== */

require_login();


/* ==========================================
   GET USER INFORMATION
   ========================================== */

$userId = $_SESSION['user_id'];

$userName = $_SESSION['full_name'] ?? '';

$userEmail = $_SESSION['email'] ?? '';

$userType = session_user_type();


/* ==========================================
   CHECK USER TYPE
   ========================================== */

if (
    $userType !== 'admin' &&
    $userType !== 'client'
) {

    session_destroy();

    header("Location: login.html");

    exit();

}


/* ==========================================
   USER INITIAL
   ========================================== */

$initial = strtoupper(
    substr($userName, 0, 1)
);


/* ==========================================
   DATABASE CONNECTION
   ========================================== */

$conn = new mysqli(
    DB_HOST,
    DB_USER,
    DB_PASS,
    DB_NAME
);


if ($conn->connect_error) {

    die(
        "Database connection failed: "
        . $conn->connect_error
    );

}


/* ==========================================
   GET TOTAL USERS
   ========================================== */

$totalUsers = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users"
);

if ($result) {

    $row = $result->fetch_assoc();

    $totalUsers = (int)$row['total'];

}


/* ==========================================
   GET TOTAL CLIENTS
   ========================================== */

$totalClients = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE user_type = 'client'"
);

if ($result) {

    $row = $result->fetch_assoc();

    $totalClients = (int)$row['total'];

}


/* ==========================================
   GET TOTAL ADMINS
   ========================================== */

$totalAdmins = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE user_type = 'admin'"
);

if ($result) {

    $row = $result->fetch_assoc();

    $totalAdmins = (int)$row['total'];

}


/* ==========================================
   GET TOTAL BORROWERS
   ========================================== */

$totalBorrowers = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM borrowers"
);

if ($result) {

    $row = $result->fetch_assoc();

    $totalBorrowers = (int)$row['total'];

}


/* ==========================================
   GET RECENT USERS
   ========================================== */

$recentUsers = $conn->query(
    "SELECT
        id,
        full_name,
        email,
        user_type
     FROM users
     ORDER BY id DESC
     LIMIT 6"
);


/* ==========================================
   CLOSE DATABASE
   ========================================== */

$conn->close();


/* ==========================================
   DISPLAY USER TYPE
   ========================================== */

if ($userType === 'admin') {

    $displayType = 'Admin User';

} else {

    $displayType = 'Client User';

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
        BookShare Dashboard
    </title>


    <style>

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

            font-family: Arial, sans-serif;

        }


        body {

            background: #f5f7fb;

            color: #20242b;

        }


        /* ==========================================
           SIDEBAR
           ========================================== */

        .sidebar {

            position: fixed;

            left: 0;

            top: 0;

            bottom: 0;

            width: 245px;

            background: #151922;

            color: white;

            padding: 22px 14px;

        }


        .logo {

            padding: 0 12px 25px;

            font-size: 22px;

            font-weight: bold;

        }


        .logo span {

            display: block;

            margin-top: 5px;

            font-size: 11px;

            color: #8f98a8;

            font-weight: normal;

        }


        .menu-title {

            padding: 10px 12px;

            color: #727b8c;

            font-size: 10px;

            text-transform: uppercase;

            letter-spacing: 1px;

        }


        .menu a {

            display: block;

            padding: 11px 12px;

            margin: 3px 0;

            border-radius: 7px;

            color: #cbd0d8;

            text-decoration: none;

            font-size: 14px;

        }


        .menu a:hover {

            background: #252b38;

            color: white;

        }


        .menu a.active {

            background: #252b38;

            color: white;

        }


        .icon {

            display: inline-block;

            width: 28px;

            color: #9fa8b8;

        }


        .sidebar-bottom {

            position: absolute;

            bottom: 20px;

            left: 14px;

            right: 14px;

            border-top: 1px solid #2b303b;

            padding-top: 15px;

        }


        /* ==========================================
           MAIN
           ========================================== */

        .main {

            margin-left: 245px;

        }


        /* ==========================================
           TOP BAR
           ========================================== */

        .topbar {

            min-height: 70px;

            background: white;

            border-bottom: 1px solid #e7eaf0;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 15px 30px;

        }


        .breadcrumb {

            color: #7d8694;

            font-size: 13px;

        }


        .user {

            display: flex;

            align-items: center;

            gap: 10px;

        }


        .avatar {

            width: 38px;

            height: 38px;

            border-radius: 50%;

            background: #2563eb;

            color: white;

            display: flex;

            justify-content: center;

            align-items: center;

            font-weight: bold;

        }


        .user-info strong {

            display: block;

            font-size: 13px;

        }


        .user-info small {

            display: block;

            margin-top: 3px;

            color: #7d8694;

            font-size: 11px;

        }


        /* ==========================================
           CONTENT
           ========================================== */

        .content {

            padding: 30px;

        }


        .heading {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;

        }


        .heading h1 {

            font-size: 26px;

            margin-bottom: 6px;

        }


        .heading p {

            color: #7d8694;

            font-size: 13px;

        }


        .button {

            background: #2563eb;

            color: white;

            padding: 11px 16px;

            border-radius: 7px;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;

        }


        .button:hover {

            background: #1d4ed8;

        }


        /* ==========================================
           STATISTICS
           ========================================== */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;

            margin-bottom: 22px;

        }


        .card {

            background: white;

            border: 1px solid #e7eaf0;

            border-radius: 10px;

            padding: 20px;

            box-shadow:
                0 2px 7px
                rgba(20, 30, 50, 0.04);

        }


        .stat-title {

            font-size: 11px;

            color: #7d8694;

            text-transform: uppercase;

        }


        .stat-number {

            font-size: 28px;

            font-weight: bold;

            margin-top: 8px;

        }


        .stat-description {

            color: #7d8694;

            font-size: 12px;

            margin-top: 6px;

        }


        /* ==========================================
           DASHBOARD GRID
           ========================================== */

        .dashboard-grid {

            display: grid;

            grid-template-columns:
                1.4fr 0.8fr;

            gap: 20px;

        }


        .card-title {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 18px;

        }


        .card-title h2 {

            font-size: 17px;

        }


        .card-title a {

            color: #315dcc;

            font-size: 12px;

            text-decoration: none;

        }


        /* ==========================================
           TABLE
           ========================================== */

        table {

            width: 100%;

            border-collapse: collapse;

        }


        th {

            text-align: left;

            padding: 10px 8px;

            border-bottom: 1px solid #e8ebef;

            color: #8b93a0;

            font-size: 11px;

            text-transform: uppercase;

        }


        td {

            padding: 13px 8px;

            border-bottom: 1px solid #f0f2f5;

            font-size: 13px;

        }


        .badge {

            display: inline-block;

            padding: 5px 9px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;

        }


        .admin-badge {

            background: #eee9ff;

            color: #6941c6;

        }


        .client-badge {

            background: #e8f1ff;

            color: #315dcc;

        }


        /* ==========================================
           QUICK ACTIONS
           ========================================== */

        .quick-actions {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 10px;

        }


        .quick-actions a {

            border: 1px solid #e7eaf0;

            border-radius: 8px;

            padding: 15px;

            text-decoration: none;

            color: #20242b;

            font-size: 13px;

            font-weight: bold;

        }


        .quick-actions a:hover {

            border-color: #315dcc;

            color: #315dcc;

        }


        .quick-actions span {

            display: block;

            color: #7d8694;

            font-size: 11px;

            font-weight: normal;

            margin-top: 5px;

        }


        /* ==========================================
           USER INFO CARD
           ========================================== */

        .profile {

            margin-top: 20px;

        }


        .profile-row {

            display: flex;

            justify-content: space-between;

            padding: 10px 0;

            border-bottom: 1px solid #f0f2f5;

        }


        .profile-label {

            color: #7d8694;

            font-size: 12px;

        }


        .profile-value {

            font-size: 13px;

            font-weight: bold;

        }


        /* ==========================================
           MOBILE
           ========================================== */

        @media (max-width: 1000px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .dashboard-grid {

                grid-template-columns: 1fr;

            }

        }


        @media (max-width: 700px) {

            .sidebar {

                position: relative;

                width: 100%;

                height: auto;

                padding: 15px;

            }


            .sidebar-bottom {

                position: relative;

                left: auto;

                right: auto;

                bottom: auto;

                margin-top: 20px;

            }


            .main {

                margin-left: 0;

            }


            .topbar {

                padding: 15px 20px;

            }


            .content {

                padding: 20px;

            }


            .heading {

                display: block;

            }


            .heading .button {

                display: inline-block;

                margin-top: 15px;

            }


            .stats {

                grid-template-columns: 1fr;

            }


            table {

                display: block;

                overflow-x: auto;

            }

        }


        @media (max-width: 450px) {

            .topbar {

                display: block;

            }


            .user {

                margin-top: 12px;

            }


            .quick-actions {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


<!-- ==========================================
     SIDEBAR
     ========================================== -->

<aside class="sidebar">


    <div class="logo">

        BookShare

        <span>
            Book Donation & Redistribution
        </span>

    </div>


    <div class="menu-title">
        Main Menu
    </div>


    <nav class="menu">


        <a
            href="index.php"
            class="active"
        >

            <span class="icon">
                ▣
            </span>

            Dashboard

        </a>


        <?php if ($userType === 'admin') { ?>

            <a href="master_list.php">

                <span class="icon">
                    ♙
                </span>

                Master List

            </a>

        <?php } ?>


        <a href="books.php">

            <span class="icon">
                ▤
            </span>

            Books

        </a>


        <a href="donations.php">

            <span class="icon">
                ♧
            </span>

            Donations

        </a>


        <a href="requests.php">

            <span class="icon">
                □
            </span>

            Book Requests

        </a>


        <a href="distributions.php">

            <span class="icon">
                ↔
            </span>

            Distributions

        </a>


    </nav>


    <div class="menu-title">

        Account

    </div>


    <nav class="menu">


        <a href="profile.php">

            <span class="icon">
                ♟
            </span>

            My Profile

        </a>


        <?php if ($userType === 'admin') { ?>

            <a href="users.php">

                <span class="icon">
                    ♟
                </span>

                Users

            </a>


            <a href="reports.php">

                <span class="icon">
                    ▥
                </span>

                Reports

            </a>

        <?php } ?>


    </nav>


    <div class="sidebar-bottom">


        <nav class="menu">


            <a href="logout.php">

                <span class="icon">
                    ↪
                </span>

                Logout

            </a>


        </nav>


    </div>


</aside>


<!-- ==========================================
     MAIN
     ========================================== -->

<main class="main">


    <!-- TOP BAR -->

    <header class="topbar">


        <div class="breadcrumb">

            BookShare /

            <strong>
                Dashboard
            </strong>

        </div>


        <div class="user">


            <div class="avatar">

                <?php

                echo htmlspecialchars(
                    $initial
                );

                ?>

            </div>


            <div class="user-info">


                <strong>

                    <?php

                    echo htmlspecialchars(
                        $userName
                    );

                    ?>

                </strong>


                <small>

                    <?php

                    echo htmlspecialchars(
                        $displayType
                    );

                    ?>

                </small>


            </div>


        </div>


    </header>


    <!-- ==========================================
         CONTENT
         ========================================== -->

    <section class="content">


        <div class="heading">


            <div>


                <h1>

                    <?php

                    if ($userType === 'admin') {

                        echo "Admin Dashboard";

                    } else {

                        echo "Client Dashboard";

                    }

                    ?>

                </h1>


                <p>

                    <?php

                    if ($userType === 'admin') {

                        echo
                        "Manage books, donations, requests, users, and redistribution.";

                    } else {

                        echo
                        "Browse books, donate books, and manage your requests.";

                    }

                    ?>

                </p>


            </div>


            <?php if ($userType === 'admin') { ?>

                <a
                    href="master_list.php"
                    class="button"
                >

                    View Master List

                </a>

            <?php } ?>


        </div>


        <!-- ==========================================
             STATISTICS
             ========================================== -->

        <div class="stats">


            <?php if ($userType === 'admin') { ?>


                <div class="card">


                    <div class="stat-title">

                        Total Users

                    </div>


                    <div class="stat-number">

                        <?php

                        echo $totalUsers;

                        ?>

                    </div>


                    <div class="stat-description">

                        All registered accounts

                    </div>


                </div>


                <div class="card">


                    <div class="stat-title">

                        Client Users

                    </div>


                    <div class="stat-number">

                        <?php

                        echo $totalClients;

                        ?>

                    </div>


                    <div class="stat-description">

                        Registered clients

                    </div>


                </div>


                <div class="card">


                    <div class="stat-title">

                        Admin Users

                    </div>


                    <div class="stat-number">

                        <?php

                        echo $totalAdmins;

                        ?>

                    </div>


                    <div class="stat-description">

                        System administrators

                    </div>


                </div>


                <div class="card">


                    <div class="stat-title">

                        Borrowers

                    </div>


                    <div class="stat-number">

                        <?php

                        echo $totalBorrowers;

                        ?>

                    </div>


                    <div class="stat-description">

                        Client records

                    </div>


                </div>


            <?php } else { ?>


                <div class="card">


                    <div class="stat-title">

                        Available Books

                    </div>


                    <div class="stat-number">

                        —

                    </div>


                    <div class="stat-description">

                        Books available for redistribution

                    </div>


                </div>


                <div class="card">


                    <div class="stat-title">

                        My Donations

                    </div>


                    <div class="stat-number">

                        —

                    </div>


                    <div class="stat-description">

                        Your donated books

                    </div>


                </div>


                <div class="card">


                    <div class="stat-title">

                        My Requests

                    </div>


                    <div class="stat-number">

                        —

                    </div>


                    <div class="stat-description">

                        Your book requests

                    </div>


                </div>


                <div class="card">


                    <div class="stat-title">

                        Account

                    </div>


                    <div class="stat-number">

                        Active

                    </div>


                    <div class="stat-description">

                        Client User

                    </div>


                </div>


            <?php } ?>


        </div>


        <!-- ==========================================
             MAIN GRID
             ========================================== -->

        <div class="dashboard-grid">


            <!-- RECENT USERS -->

            <div class="card">


                <div class="card-title">


                    <h2>

                        Recent Users

                    </h2>


                    <?php if ($userType === 'admin') { ?>

                        <a href="master_list.php">

                            View All

                        </a>

                    <?php } ?>


                </div>


                <table>


                    <tr>


                        <th>
                            Name
                        </th>


                        <th>
                            Email
                        </th>


                        <th>
                            Type
                        </th>


                    </tr>


                    <?php


                    if (
                        $recentUsers &&
                        $recentUsers->num_rows > 0
                    ) {


                        while (
                            $user =
                            $recentUsers->fetch_assoc()
                        ) {


                    ?>


                    <tr>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $user['full_name']
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $user['email']
                            );

                            ?>

                        </td>


                        <td>


                            <?php

                            if (
                                $user['user_type']
                                === 'admin'
                            ) {

                            ?>


                                <span
                                    class="badge admin-badge"
                                >

                                    Admin

                                </span>


                            <?php

                            } else {

                            ?>


                                <span
                                    class="badge client-badge"
                                >

                                    Client

                                </span>


                            <?php

                            }

                            ?>


                        </td>


                    </tr>


                    <?php


                        }

                    } else {


                    ?>


                    <tr>


                        <td
                            colspan="3"
                            style="text-align:center;"
                        >

                            No users registered yet.

                        </td>


                    </tr>


                    <?php


                    }

                    ?>


                </table>


            </div>


            <!-- QUICK ACTIONS -->

            <div class="card">


                <div class="card-title">


                    <h2>

                        Quick Actions

                    </h2>


                </div>


                <div class="quick-actions">


                    <?php if ($userType === 'admin') { ?>


                        <a href="master_list.php">

                            Master List

                            <span>

                                View all users

                            </span>

                        </a>


                    <?php } ?>


                    <a href="books.php">

                        Books

                        <span>

                            Browse book inventory

                        </span>

                    </a>


                    <a href="donations.php">

                        Donations

                        <span>

                            Donate or manage books

                        </span>

                    </a>


                    <a href="requests.php">

                        Book Requests

                        <span>

                            Request available books

                        </span>

                    </a>


                    <a href="distributions.php">

                        Distributions

                        <span>

                            Track redistribution

                        </span>

                    </a>


                    <a href="profile.php">

                        My Profile

                        <span>

                            View your account

                        </span>

                    </a>


                </div>


            </div>


        </div>


        <!-- ==========================================
             PROFILE
             ========================================== -->

        <div class="card profile">


            <div class="card-title">


                <h2>

                    Account Information

                </h2>


            </div>


            <div class="profile-row">


                <span class="profile-label">

                    Full Name

                </span>


                <span class="profile-value">

                    <?php

                    echo htmlspecialchars(
                        $userName
                    );

                    ?>

                </span>


            </div>


            <div class="profile-row">


                <span class="profile-label">

                    Email

                </span>


                <span class="profile-value">

                    <?php

                    echo htmlspecialchars(
                        $userEmail
                    );

                    ?>

                </span>


            </div>


            <div class="profile-row">


                <span class="profile-label">

                    Account Type

                </span>


                <span class="profile-value">

                    <?php

                    echo htmlspecialchars(
                        $displayType
                    );

                    ?>

                </span>


            </div>


        </div>


    </section>


</main>


</body>

</html>