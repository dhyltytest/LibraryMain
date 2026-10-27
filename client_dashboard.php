```php
<?php
session_start();

require_once 'db.php';
require_once 'functions.php';

require_login();

if (session_user_type() !== 'client') {
    header("Location: index.php");
    exit();
}

$conn = connect_db();

$clientName = $_SESSION['full_name'] ?? 'Client';
$clientEmail = $_SESSION['email'] ?? '';
$initial = strtoupper(substr($clientName, 0, 1));

$availableBooks = 0;
$myDonations = 0;
$myRequests = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM books
     WHERE available = 1 AND quantity > 0"
);

if ($result) {
    $availableBooks = (int) $result->fetch_assoc()['total'];
}

$stmt = $conn->prepare(
    "SELECT id FROM borrowers WHERE email = ? LIMIT 1"
);

if ($stmt) {
    $stmt->bind_param("s", $clientEmail);
    $stmt->execute();
    $borrower = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($borrower) {
        $borrowerId = (int) $borrower['id'];

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM books
             WHERE donor_id = ?"
        );

        if ($stmt) {
            $stmt->bind_param("i", $borrowerId);
            $stmt->execute();
            $myDonations = (int) $stmt->get_result()
                ->fetch_assoc()['total'];
            $stmt->close();
        }

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM book_requests
             WHERE borrower_id = ?"
        );

        if ($stmt) {
            $stmt->bind_param("i", $borrowerId);
            $stmt->execute();
            $myRequests = (int) $stmt->get_result()
                ->fetch_assoc()['total'];
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - BookBridge</title>

    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            background: #f4f7fb;
            color: #20242b;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 245px;
            flex-shrink: 0;
            padding: 22px 14px;
            background: #151922;
            color: white;
        }

        .brand {
            padding: 0 12px;
            margin: 0 0 8px;
            color: white;
            font-size: 22px;
            font-weight: bold;
        }

        .subtitle {
            margin: 5px 12px 25px;
            color: #8f98a8;
            font-size: 11px;
        }

        .menu-heading {
            margin: 10px 0;
            padding: 10px 12px;
            color: #aab2c0;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-link {
            display: block;
            margin: 3px 0;
            padding: 11px 12px;
            border-radius: 7px;
            color: #cbd0d8;
            text-decoration: none;
            font-size: 14px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #252b38;
            color: white;
        }

        .main {
            flex: 1;
            min-width: 0;
            padding: 28px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 30px;
        }

        .breadcrumb {
            color: #64748b;
            font-size: 14px;
        }

        .user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            display: flex;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: bold;
        }

        .user-name {
            font-weight: bold;
        }

        .user-role {
            margin-top: 4px;
            color: #64748b;
            font-size: 12px;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 28px;
        }

        .intro {
            margin: 0 0 22px;
            color: #64748b;
        }

        .primary-button {
            display: inline-block;
            margin-bottom: 25px;
            padding: 12px 17px;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .primary-button:hover {
            background: #1d4ed8;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .stat,
        .panel,
        .action-card {
            padding: 21px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: white;
        }

        .stat-label {
            color: #64748b;
            font-size: 13px;
        }

        .stat-number {
            margin: 12px 0 8px;
            color: #2563eb;
            font-size: 28px;
            font-weight: bold;
        }

        .stat-description {
            color: #64748b;
            font-size: 12px;
        }

        h2 {
            margin: 0 0 17px;
            font-size: 19px;
        }

        .actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .action-card {
            display: block;
            color: inherit;
            text-decoration: none;
            transition: border-color 0.15s;
        }

        .action-card:hover {
            border-color: #2563eb;
        }

        .action-title {
            margin-bottom: 9px;
            color: #2563eb;
            font-weight: bold;
        }

        .action-description {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .account-details {
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .status {
            color: #15803d;
            font-weight: bold;
        }

        .sdg {
            margin-top: 22px;
            padding: 22px;
            border-radius: 10px;
            background: #eff6ff;
            line-height: 1.7;
        }

        .sdg h2 {
            color: #1d4ed8;
        }

        @media (max-width: 1000px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .actions {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 650px) {
            .layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                padding: 18px;
                border-right: 0;
                border-bottom: 1px solid #e5e7eb;
            }

            .subtitle {
                margin-bottom: 15px;
            }

            .menu-heading {
                margin-top: 15px;
            }

            .main {
                padding: 18px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            h1 {
                font-size: 24px;
            }

            .stats,
            .actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<div class="layout">

    <aside class="sidebar">
        <div class="brand">● BookBridge</div>
        <p class="subtitle">SDG 4 · Quality Education</p>

        <div class="menu-heading">Main</div>
        <a class="nav-link active" href="client_dashboard.php">▣ Dashboard</a>
        <a class="nav-link" href="books.php">▤ Browse Books</a>
        <a class="nav-link" href="donations.php">♧ Donate a Book</a>
        <a class="nav-link" href="book_requests.php">□ Request a Book</a>

        <div class="menu-heading">My Activity</div>
        <a class="nav-link" href="my_donations.php">♧ My Donations</a>
        <a class="nav-link" href="book_requests.php">□ My Requests</a>
        <a class="nav-link" href="return_book.php">↩ Return Books</a>
        <a class="nav-link" href="distribution_history.php">↔ Distribution History</a>

        <div class="menu-heading">Account</div>
        <a class="nav-link" href="profile.php">♟ My Profile</a>
        <a class="nav-link" href="logout.php">↪ Logout</a>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="breadcrumb">
                BookBridge / <strong>Client Dashboard</strong>
            </div>

            <div class="user">
                <div class="avatar">
                    <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div>
                    <div class="user-name">
                        <?= htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="user-role">Client User</div>
                </div>
            </div>
        </div>

        <h1>Client Dashboard</h1>
        <p class="intro">
            Find, donate, and request educational books.
        </p>

        <a class="primary-button" href="donations.php">+ Donate a Book</a>

        <section class="stats">
            <div class="stat">
                <div class="stat-label">Available Books</div>
                <div class="stat-number"><?= $availableBooks ?></div>
                <div class="stat-description">Book inventory</div>
            </div>

            <div class="stat">
                <div class="stat-label">My Donations</div>
                <div class="stat-number"><?= $myDonations ?></div>
                <div class="stat-description">Your donated books</div>
            </div>

            <div class="stat">
                <div class="stat-label">My Requests</div>
                <div class="stat-number"><?= $myRequests ?></div>
                <div class="stat-description">Your book requests</div>
            </div>

            <div class="stat">
                <div class="stat-label">Account</div>
                <div class="stat-number" style="font-size:23px;">Active</div>
                <div class="stat-description">Client User</div>
            </div>
        </section>

        <section>
            <h2>What would you like to do?</h2>

            <div class="actions">
                <a class="action-card" href="books.php">
                    <div class="action-title">Browse Books</div>
                    <div class="action-description">
                        Find available educational books.
                    </div>
                </a>

                <a class="action-card" href="donations.php">
                    <div class="action-title">Donate a Book</div>
                    <div class="action-description">
                        Share books you no longer need.
                    </div>
                </a>

                <a class="action-card" href="book_requests.php">
                    <div class="action-title">Request a Book</div>
                    <div class="action-description">
                        Ask for a book you need.
                    </div>
                </a>

                <a class="action-card" href="my_donations.php">
                    <div class="action-title">My Donations</div>
                    <div class="action-description">
                        Check your donation records.
                    </div>
                </a>

                <a class="action-card" href="book_requests.php">
                    <div class="action-title">My Requests</div>
                    <div class="action-description">
                        Track your request status.
                    </div>
                </a>

                <a class="action-card" href="return_book.php">
                    <div class="action-title">Return Books</div>
                    <div class="action-description">
                        View your borrowed books and their return status.
                    </div>
                </a>

                <a class="action-card" href="distribution_history.php">
                    <div class="action-title">Distribution History</div>
                    <div class="action-description">
                        View redistribution history.
                    </div>
                </a>
            </div>
        </section>

        <section class="panel">
            <h2>My Account</h2>
            <div class="account-details">
                <strong><?= htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') ?></strong><br>
                <?= htmlspecialchars($clientEmail, ENT_QUOTES, 'UTF-8') ?><br>
                <strong>Account Type:</strong> Client User<br>
                <span class="status">● Active</span><br>
                Your account is active and ready to participate in the BookBridge platform.
            </div>
        </section>

        <section class="sdg">
            <h2>SDG 4: Quality Education</h2>
            BookBridge connects people who have usable educational books with
            learners and communities that need them. Every donation can help
            improve access to learning resources and support Quality Education.
        </section>
    </main>
</div>
</body>
</html>
```