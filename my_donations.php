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

$donations = [];
$error = '';

$stmt = $conn->prepare("
    SELECT id
    FROM borrowers
    WHERE email = ?
    LIMIT 1
");

$borrowerId = 0;

if ($stmt) {
    $stmt->bind_param("s", $clientEmail);
    $stmt->execute();
    $borrower = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($borrower) {
        $borrowerId = (int) $borrower['id'];
    }
}

if ($borrowerId > 0) {
    $stmt = $conn->prepare("
        SELECT id, title, author, category, quantity,
               donation_status, created_at
        FROM books
        WHERE donor_id = ?
        ORDER BY created_at DESC, id DESC
    ");

    if ($stmt) {
        $stmt->bind_param("i", $borrowerId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $donations[] = $row;
        }

        $stmt->close();
    } else {
        $error = 'Unable to load your donations.';
    }
}

$conn->close();

function donationStatus($status) {
    $status = strtolower(trim((string) $status));

    if ($status === 'approved') return 'Approved';
    if ($status === 'rejected') return 'Rejected';
    if ($status === 'distributed') return 'Distributed';

    return 'Pending';
}

function donationStatusClass($status) {
    $status = strtolower(trim((string) $status));

    if ($status === 'approved') return 'approved';
    if ($status === 'rejected') return 'rejected';
    if ($status === 'distributed') return 'distributed';

    return 'pending';
}

$totalDonations = count($donations);
$approvedCount = 0;
$pendingCount = 0;

foreach ($donations as $donation) {
    $status = strtolower((string) ($donation['donation_status'] ?? 'pending'));

    if ($status === 'approved') {
        $approvedCount++;
    } elseif ($status !== 'rejected' && $status !== 'distributed') {
        $pendingCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Donations - BookBridge</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #f4f7fb;
            color: #20242b;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .topbar h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .muted {
            color: #737b89;
            font-size: 14px;
            line-height: 1.5;
        }

        .user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #2563eb;
            color: white;
            font-weight: bold;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 22px;
            padding: 11px 16px;
            background: #151922;
            border: 1px solid #151922;
            border-radius: 8px;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: background 0.2s, border-color 0.2s;
        }

        .back-link:hover {
            background: #252b38;
            border-color: #252b38;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .stat {
            padding: 22px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
        }

        .stat-label {
            margin-bottom: 12px;
            color: #737b89;
            font-size: 13px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
        }

        .panel {
            padding: 24px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
        }

        .panel h2 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .table-wrap {
            width: 100%;
            margin-top: 22px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 650px;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px 12px;
            border-bottom: 1px solid #edf0f4;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
            color: #687182;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .pending {
            background: #fff4d6;
            color: #946200;
        }

        .approved {
            background: #dcfce7;
            color: #166534;
        }

        .rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .distributed {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .empty {
            padding: 45px 15px;
            text-align: center;
            color: #737b89;
        }

        .empty h3 {
            color: #20242b;
        }

        .button {
            display: inline-block;
            margin-top: 12px;
            padding: 11px 17px;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
        }

        .button:hover {
            background: #1d4ed8;
        }

        .error {
            padding: 12px;
            border-radius: 7px;
            background: #fee2e2;
            color: #991b1b;
        }

        @media (max-width: 700px) {
            body {
                padding: 18px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .summary {
                grid-template-columns: 1fr;
            }

            .panel {
                padding: 17px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <a class="back-link" href="client_dashboard.php">&larr; Back to Dashboard</a>

    <div class="topbar">
        <div>
            <h1>My Donations</h1>
            <div class="muted">
                Manage and track the books you have donated.
            </div>
        </div>

        <div class="user">
            <div class="avatar">
                <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div>
                <strong><?= htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') ?></strong>
                <div class="muted">Client User</div>
            </div>
        </div>
    </div>

    <div class="summary">
        <div class="stat">
            <div class="stat-label">Total Donations</div>
            <div class="stat-number"><?= $totalDonations ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Approved Donations</div>
            <div class="stat-number"><?= $approvedCount ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Pending Donations</div>
            <div class="stat-number"><?= $pendingCount ?></div>
        </div>
    </div>

    <section class="panel">
        <h2>Donation History</h2>
        <p class="muted">
            View the books you have submitted and their current status.
        </p>

        <?php if ($error !== ''): ?>

            <p class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>

        <?php elseif (empty($donations)): ?>

            <div class="empty">
                <h3>You haven't donated any books yet.</h3>
                <p>Your donation history will appear here after you submit a book.</p>
                <a class="button" href="donations.php">Donate a Book</a>
            </div>

        <?php else: ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Date Added</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($donations as $donation): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($donation['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars($donation['author'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($donation['category'] ?? 'Other', ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td><?= (int) ($donation['quantity'] ?? 0) ?></td>

                                <td>
                                    <span class="status <?= donationStatusClass($donation['donation_status'] ?? 'Pending') ?>">
                                        <?= htmlspecialchars(donationStatus($donation['donation_status'] ?? 'Pending'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if (!empty($donation['created_at'])): ?>
                                        <?= htmlspecialchars(date('M d, Y', strtotime($donation['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>

</div>
</body>
</html>
```