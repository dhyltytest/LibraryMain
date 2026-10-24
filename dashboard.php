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

$clientName = $_SESSION['full_name'];
$clientEmail = $_SESSION['email'];
$initial = strtoupper(substr($clientName, 0, 1));

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
        Client Dashboard - BookBridge
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


        /* SIDEBAR */

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


        /* MAIN */

        .main {

            margin-left: 245px;

        }


        /* TOPBAR */

        .topbar {

            height: 70px;

            background: white;

            border-bottom: 1px solid #e4e7ec;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 32px;

        }


        .breadcrumb {

            color: #8a929f;

            font-size: 13px;

        }


        .user {

            display: flex;

            align-items: center;

            gap: 10px;

        }


        .avatar {

            width: 40px;

            height: 40px;

            border-radius: 50%;

            background: #e8eefc;

            color: #315dcc;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: bold;

        }


        .user-info small {

            display: block;

            color: #8a929f;

            margin-top: 3px;

        }


        /* CONTENT */

        .content {

            padding: 30px 35px;

        }


        .heading {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;

        }


        .heading h1 {

            font-size: 28px;

        }


        .heading p {

            color: #7a8391;

            font-size: 14px;

            margin-top: 7px;

        }


        .button {

            display: inline-block;

            background: #315dcc;

            color: white;

            padding: 11px 17px;

            border-radius: 6px;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;

        }


        .button:hover {

            background: #244da9;

        }


        /* STATS */

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


        /* DASHBOARD GRID */

        .dashboard-grid {

            display: grid;

            grid-template-columns:
                1.2fr 0.8fr;

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


        /* QUICK ACTIONS */

        .quick-actions {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 10px;

        }


        .quick-actions a {

            border: 1px solid #e7eaf0;

            border-radius: 8px;

            padding: 16px;

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


        /* PROFILE */

        .profile {

            margin-top: 20px;

        }


        .profile-content {

            display: flex;

            align-items: center;

            gap: 15px;

        }


        .profile-avatar {

            width: 55px;

            height: 55px;

            border-radius: 50%;

            background: #315dcc;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;

            font-weight: bold;

        }


        .profile-info strong {

            display: block;

        }


        .profile-info p {

            color: #7d8694;

            font-size: 13px;

            margin-top: 4px;

        }


        .info-box {

            margin-top: 20px;

            line-height: 1.7;

            color: #667085;

            font-size: 14px;

        }


        footer {

            text-align: center;

            color: #9aa1ad;

            font-size: 11px;

            padding: 25px 0;

        }


        @media (max-width: 1000px) {

            .stats {

                grid-template-columns:
                    1fr 1fr;

            }

            .dashboard-grid {

                grid-template-columns:
                    1fr;

            }

        }


        @media (max-width: 700px) {

            .sidebar {

                width: 190px;

            }

            .main {

                margin-left: 190px;

            }

            .stats {

                grid-template-columns:
                    1fr;

            }

        }

    </style>

</head>


<body>


<!-- SIDEBAR -->

<aside class="sidebar">


    <div class="logo">

        ● BookBridge

        <span>
            SDG 4 · Quality Education
        </span>

    </div>


    <div class="menu-title">
        Main
    </div>


    <nav class="menu">


        <a
            href="client_dashboard.php"
            class="active"
        >

            <span class="icon">▣</span>

            Dashboard

        </a>


        <a href="books.php">

            <span class="icon">▤</span>

            Browse Books

        </a>


        <a href="donate.php">

            <span class="icon">♧</span>

            Donate a Book

        </a>


        <a href="book_requests.php">

            <span class="icon">□</span>

            Request a Book

        </a>


    </nav>


    <div class="menu-title">

        My Activity

    </div>


    <nav class="menu">


        <a href="my_donations.php">

            <span class="icon">♧</span>

            My Donations

        </a>


        <a href="book_requests.php">

            <span class="icon">□</span>

            My Requests

        </a>


       <a class="nav-link" href="distributions.php">↔ Distributions</a>


    </nav>


    <div class="sidebar-bottom">

        <nav class="menu">

            <a href="logout.php">

                <span class="icon">↪</span>

                Logout

            </a>

        </nav>

    </div>


</aside>


<!-- MAIN -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">


        <div class="breadcrumb">

            BookShare /
            <strong>
                Client Dashboard
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
                        $clientName
                    );

                    ?>

                </strong>


                <small>
                    Client User
                </small>


            </div>


        </div>


    </header>


    <!-- CONTENT -->

    <section class="content">


        <div class="heading">


            <div>

                <h1>
                    Client Dashboard
                </h1>


                <p>
                    Find, donate, and request
                    educational books.
                </p>

            </div>


            <a
                href="donate.php"
                class="button"
            >
                + Donate a Book
            </a>


        </div>


        <!-- STATISTICS -->

        <div class="stats">


            <div class="card">

                <div class="stat-title">
                    Available Books
                </div>

                <div class="stat-number">
                    —
                </div>

                <div class="stat-description">
                    Book inventory
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


        </div>


        <!-- MAIN CONTENT -->

        <div class="dashboard-grid">


            <!-- QUICK ACTIONS -->

            <div class="card">


                <div class="card-title">

                    <h2>
                        What would you like to do?
                    </h2>

                </div>


                <div class="quick-actions">


                    <a href="books.php">

                        Browse Books

                        <span>
                            Find available
                            educational books
                        </span>

                    </a>


                    <a href="donate.php">

                        Donate a Book

                        <span>
                            Share books you
                            no longer need
                        </span>

                    </a>


                    <a href="book_requests.php">

                        Request a Book

                        <span>
                            Ask for a book
                            you need
                        </span>

                    </a>


                    <a href="my_donations.php">

                        My Donations

                        <span>
                            Check your
                            donation records
                        </span>

                    </a>


                    <a href="my_requests.php">

                        My Requests

                        <span>
                            Track request status
                        </span>

                    </a>


                    <a href="distribution_history.php">

                        History

                        <span>
                            View redistribution
                            history
                        </span>

                    </a>


                </div>


            </div>


            <!-- ACCOUNT -->

            <div class="card">


                <div class="card-title">

                    <h2>
                        My Account
                    </h2>

                </div>


                <div class="profile-content">


                    <div class="profile-avatar">

                        <?php

                        echo htmlspecialchars(
                            $initial
                        );

                        ?>

                    </div>


                    <div class="profile-info">


                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $clientName
                            );

                            ?>

                        </strong>


                        <p>

                            <?php

                            echo htmlspecialchars(
                                $clientEmail
                            );

                            ?>

                        </p>


                    </div>


                </div>


                <div class="info-box">

                    <strong>
                        Account Type:
                    </strong>

                    Client User

                    <br><br>

                    Your account is active and
                    ready to participate in the
                    BookShare platform.

                </div>


            </div>


        </div>


        <!-- SDG INFORMATION -->

        <div
            class="card"
            style="margin-top:20px;"
        >


            <div class="card-title">

                <h2>
                    SDG 4: Quality Education
                </h2>


            </div>
            


            <p
                style="
                    color:#667085;
                    font-size:14px;
                    line-height:1.7;
                "
            >

                BookShare connects people who
                have usable educational books
                with learners and communities
                that need them.

                Every donation can help improve
                access to learning resources and
                support Quality Education.

            </p>


        </div>


        <footer>

            BookShare · Book Donation &
            Redistribution Platform ·
            SDG 4: Quality Education

        </footer>


    </section>


</main>


</body>

</html>