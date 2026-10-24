
<?php
session_start();

require_once 'db.php';
require_once 'functions.php';

require_login();

if (session_user_type() !== 'admin') {
    header("Location: index.php");
    exit();
}

$conn = connect_db();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['distribute_id'])) {
    $requestId = (int) $_POST['distribute_id'];

    if ($requestId <= 0) {
        $error = "Invalid book request.";
    } else {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("
                SELECT
                    br.id,
                    br.book_id,
                    br.quantity,
                    br.status,
                    b.quantity AS stock
                FROM book_requests br
                INNER JOIN books b ON b.id = br.book_id
                WHERE br.id = ?
                FOR UPDATE
            ");

            if (!$stmt) {
                throw new Exception("Unable to check the request.");
            }

            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$request) {
                throw new Exception("The request could not be found.");
            }

            if ($request['status'] !== 'Approved') {
                throw new Exception("Only approved requests can be distributed.");
            }

            $quantity = (int) $request['quantity'];
            $stock = (int) $request['stock'];
            $bookId = (int) $request['book_id'];

            if ($quantity <= 0) {
                throw new Exception("The requested quantity is invalid.");
            }

            if ($stock < $quantity) {
                throw new Exception("There is not enough book stock to complete this distribution.");
            }

            $stmt = $conn->prepare("
                UPDATE books
                SET quantity = quantity - ?
                WHERE id = ? AND quantity >= ?
            ");

            if (!$stmt) {
                throw new Exception("Unable to update the book inventory.");
            }

            $stmt->bind_param("iii", $quantity, $bookId, $quantity);
            $stmt->execute();
            $updated = $stmt->affected_rows;
            $stmt->close();

            if ($updated !== 1) {
                throw new Exception("The book inventory could not be updated.");
            }

            $stmt = $conn->prepare("
                UPDATE book_requests
                SET status = 'Completed'
                WHERE id = ? AND status = 'Approved'
            ");

            if (!$stmt) {
                throw new Exception("Unable to update the request status.");
            }

            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $updated = $stmt->affected_rows;
            $stmt->close();

            if ($updated !== 1) {
                throw new Exception("The request status could not be updated.");
            }

            $conn->commit();
            $message = "Distribution completed. Inventory and request status were updated.";

        } catch (Throwable $e) {
            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
            }

            $error = $e->getMessage();
        }
    }
}

$approvedRequests = [];
$completedDistributions = [];

$sql = "
    SELECT
        br.id AS request_id,
        br.quantity,
        br.reason,
        br.requested_at,
        b.title,
        b.author,
        b.category,
        b.quantity AS stock,
        borrower.full_name,
        borrower.email
    FROM book_requests br
    INNER JOIN books b ON b.id = br.book_id
    INNER JOIN borrowers borrower ON borrower.id = br.borrower_id
    WHERE br.status = 'Approved'
    ORDER BY br.requested_at ASC, br.id ASC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $approvedRequests[] = $row;
    }
} else {
    $error = $error ?: "Unable to load approved requests. Check your database table structure.";
}

$sql = "
    SELECT
        br.id AS request_id,
        br.quantity,
        br.requested_at,
        b.title,
        b.author,
        b.category,
        borrower.full_name,
        borrower.email
    FROM book_requests br
    INNER JOIN books b ON b.id = br.book_id
    INNER JOIN borrowers borrower ON borrower.id = br.borrower_id
    WHERE br.status = 'Completed'
    ORDER BY br.requested_at DESC, br.id DESC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $completedDistributions[] = $row;
    }
} else {
    $error = $error ?: "Unable to load completed distributions. Check your database table structure.";
}

$conn->close();

$totalCompleted = count($completedDistributions);
$totalBooksDistributed = 0;

foreach ($completedDistributions as $item) {
    $totalBooksDistributed += (int) $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distributions - BookBridge</title>

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
            max-width: 1250px;
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

        .message, .error {
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .message {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card, .section {
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

        .section {
            margin-bottom: 25px;
        }

        .section h2 {
            font-size: 20px;
            margin: 0 0 20px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
            vertical-align: top;
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
            font-size: 12px;
            font-weight: bold;
        }

        .approved {
            background: #fef3c7;
            color: #92400e;
        }

        .completed {
            background: #dcfce7;
            color: #166534;
        }

        .distribute-button {
            display: inline-block;
            padding: 9px 12px;
            border: 0;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        .distribute-button:hover {
            background: #1d4ed8;
        }

        .empty {
            padding: 30px 15px;
            text-align: center;
            color: #748094;
        }

        @media (max-width: 700px) {
            .container {
                padding: 20px 12px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .section {
                padding: 15px;
            }

            h1 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <a class="back-link" href="index.php">
        &larr; Back to Admin Dashboard
    </a>

    <h1>Distribution Management</h1>
    <p class="subtitle">
        Manage approved book requests and review completed distributions.
    </p>

    <?php if ($message !== ''): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Approved Requests Awaiting Distribution</div>
            <div class="stat-number"><?= count($approvedRequests) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Completed Distributions</div>
            <div class="stat-number"><?= $totalCompleted ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Books Distributed</div>
            <div class="stat-number"><?= $totalBooksDistributed ?></div>
        </div>
    </div>

    <div class="section">
        <h2>Approved Requests</h2>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Client</th>
                    <th>Book</th>
                    <th>Quantity Requested</th>
                    <th>Available Stock</th>
                    <th>Request Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>

                <tbody>
                <?php if (empty($approvedRequests)): ?>
                    <tr>
                        <td colspan="7" class="empty">
                            No approved requests are waiting for distribution.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($approvedRequests as $item): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['full_name']) ?></strong><br>
                                <?= htmlspecialchars($item['email']) ?>
                            </td>

                            <td>
                                <strong><?= htmlspecialchars($item['title']) ?></strong><br>
                                <?= htmlspecialchars($item['author'] ?? '') ?>
                            </td>

                            <td><?= (int) $item['quantity'] ?></td>
                            <td><?= (int) $item['stock'] ?></td>
                            <td><?= htmlspecialchars($item['requested_at']) ?></td>

                            <td><span class="status approved">Approved</span></td>

                            <td>
                                <?php if (
                                    (int) $item['quantity'] > 0 &&
                                    (int) $item['stock'] >= (int) $item['quantity']
                                ): ?>
                                    <form method="POST" action="distributions.php">
                                        <input
                                            type="hidden"
                                            name="distribute_id"
                                            value="<?= (int) $item['request_id'] ?>"
                                        >

                                        <button class="distribute-button" type="submit">
                                            Mark as Distributed
                                        </button>
                                    </form>
                                <?php else: ?>
                                    Insufficient stock
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <h2>Completed Distribution History</h2>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Client</th>
                    <th>Book</th>
                    <th>Category</th>
                    <th>Quantity Distributed</th>
                    <th>Status</th>
                    <th>Request Date</th>
                </tr>
                </thead>

                <tbody>
                <?php if (empty($completedDistributions)): ?>
                    <tr>
                        <td colspan="6" class="empty">
                            No completed distributions yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($completedDistributions as $item): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['full_name']) ?></strong><br>
                                <?= htmlspecialchars($item['email']) ?>
                            </td>

                            <td>
                                <strong><?= htmlspecialchars($item['title']) ?></strong><br>
                                <?= htmlspecialchars($item['author'] ?? '') ?>
                            </td>

                            <td><?= htmlspecialchars($item['category'] ?? '') ?></td>
                            <td><?= (int) $item['quantity'] ?></td>
                            <td><span class="status completed">Completed</span></td>
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
