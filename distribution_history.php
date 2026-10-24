
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

$distributions = [];
$error = '';

$stmt = $conn->prepare(
    "SELECT id FROM borrowers WHERE email = ? LIMIT 1"
);

if (!$stmt) {
    $error = "Unable to find your client account.";
} else {
    $stmt->bind_param("s", $clientEmail);
    $stmt->execute();
    $borrower = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($borrower) {
        $borrowerId = (int) $borrower['id'];

        $stmt = $conn->prepare("
            SELECT
                br.id AS request_id,
                br.quantity,
                br.status,
                br.requested_at,
                b.title,
                b.author,
                b.category
            FROM book_requests br
            INNER JOIN books b ON br.book_id = b.id
            WHERE br.borrower_id = ?
              AND br.status = 'Completed'
            ORDER BY br.requested_at DESC, br.id DESC
        ");

        if ($stmt) {
            $stmt->bind_param("i", $borrowerId);
            $stmt->execute();

            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $distributions[] = $row;
            }

            $stmt->close();
        } else {
            $error = "Unable to load your distribution history.";
        }
    }
}

$conn->close();

$totalRecords = count($distributions);
$totalBooks = 0;

foreach ($distributions as $item) {
    $totalBooks += (int) $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribution History - BookBridge</title>

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

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 30px 20px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 25px;
            padding: 12px 17px;
            background: #151922;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

        .back-link:hover {
            background: #303744;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .subtitle {
            color: #748094;
            margin-bottom: 25px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card, .history-card {
            background: white;
            border: 1px solid #e7eaf0;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 8px rgba(20, 30, 50, 0.04);
        }

        .stat-label {
            color: #748094;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 29px;
            font-weight: bold;
        }

        .history-card h2 {
            font-size: 20px;
            margin: 0 0 20px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 700px;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            color: #596579;
            font-size: 12px;
            text-transform: uppercase;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #dcfce7;
            color: #166534;
            font-size: 12px;
            font-weight: bold;
        }

        .empty {
            padding: 35px 15px;
            text-align: center;
            color: #748094;
            line-height: 1.7;
        }

        .message {
            padding: 14px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
        }

        @media (max-width: 650px) {
            .container {
                padding: 20px 12px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 25px;
            }

            .history-card {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <a class="back-link" href="client_dashboard.php">
        &larr; Back to Dashboard
    </a>

    <h1>Distribution History</h1>
    <p class="subtitle">
        View the educational books you have received through BookBridge.
    </p>

    <?php if ($error !== ''): ?>
        <div class="message">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Completed Requests</div>
            <div class="stat-number"><?= $totalRecords ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Books Received</div>
            <div class="stat-number"><?= $totalBooks ?></div>
        </div>
    </div>

    <div class="history-card">
        <h2>My Received Books</h2>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Book Title</th>
                    <th>Author</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Request Date</th>
                </tr>
                </thead>

                <tbody>
                <?php if (empty($distributions)): ?>
                    <tr>
                        <td colspan="6" class="empty">
                            No completed distributions yet.<br>
                            Books will appear here after an admin marks your approved request as distributed.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($distributions as $item): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['title']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($item['author'] ?? '') ?></td>
                            <td><?= htmlspecialchars($item['category'] ?? '') ?></td>
                            <td><?= (int) $item['quantity'] ?></td>
                            <td><span class="status">Completed</span></td>
                            <td><?= htmlspecialchars($item['requested_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
