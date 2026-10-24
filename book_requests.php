
<?php
session_start();
require_once __DIR__ . '/db.php';

$conn = connect_db();

if (empty($_SESSION['user_id']) && empty($_SESSION['email'])) {
    header('Location: login.html');
    exit;
}

$email = trim((string)($_SESSION['email'] ?? ''));

$userType = strtolower(trim((string)(
    $_SESSION['user_type'] ?? $_SESSION['role'] ?? ''
)));

$isAdmin = in_array($userType, ['admin', 'admin user'], true);

$message = '';
$error = '';
$borrowerId = 0;

/* ADMIN: APPROVE OR REJECT REQUESTS */
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $newStatus = (string)($_POST['status'] ?? '');

    if ($requestId <= 0 || !in_array($newStatus, ['Approved', 'Rejected'], true)) {
        $error = 'Invalid request.';
    } else {
        $stmt = $conn->prepare(
            "UPDATE book_requests
             SET status = ?
             WHERE id = ? AND status = 'Pending'"
        );

        $stmt->bind_param('si', $newStatus, $requestId);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = 'Request #' . $requestId . ' has been ' . strtolower($newStatus) . '.';
        } else {
            $error = 'Unable to update the request. It may no longer be pending.';
        }

        $stmt->close();
    }
}

/* CLIENT: FIND THE BORROWER RECORD */
if (!$isAdmin && $email !== '') {
    $stmt = $conn->prepare(
        "SELECT id FROM borrowers WHERE email = ? LIMIT 1"
    );

    $stmt->bind_param('s', $email);
    $stmt->execute();

    $borrower = $stmt->get_result()->fetch_assoc();

    if ($borrower) {
        $borrowerId = (int)$borrower['id'];
    }

    $stmt->close();
}

/* CLIENT: SUBMIT A BOOK REQUEST */
if (!$isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId = (int)($_POST['book_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));

    if ($borrowerId <= 0) {
        $error = 'Your account is not linked to a client record in the borrowers table.';
    } elseif ($bookId <= 0 || $quantity <= 0 || $reason === '') {
        $error = 'Please select a book, enter a valid quantity, and provide a reason.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, quantity, available
             FROM books
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->bind_param('i', $bookId);
        $stmt->execute();

        $book = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (
            !$book ||
            (int)$book['available'] !== 1 ||
            (int)$book['quantity'] < $quantity
        ) {
            $error = 'The selected book is unavailable or there are not enough copies.';
        } else {
            $status = 'Pending';

            $stmt = $conn->prepare(
                "INSERT INTO book_requests
                 (book_id, borrower_id, quantity, reason, status)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                'iiiss',
                $bookId,
                $borrowerId,
                $quantity,
                $reason,
                $status
            );

            if ($stmt->execute()) {
                $message = 'Your book request has been submitted successfully.';
            } else {
                $error = 'Unable to submit your request.';
            }

            $stmt->close();
        }
    }
}

/* LOAD ALL REQUESTS FOR ADMIN */
$allRequests = false;

if ($isAdmin) {
    $allRequests = $conn->query(
        "SELECT
            br.id,
            br.borrower_id,
            br.book_id,
            br.quantity,
            br.reason,
            br.status,
            br.requested_at,
            b.title AS book_title,
            borrower.full_name AS client_name,
            borrower.email AS client_email
         FROM book_requests br
         LEFT JOIN books b ON b.id = br.book_id
         LEFT JOIN borrowers borrower ON borrower.id = br.borrower_id
         ORDER BY
            CASE WHEN br.status = 'Pending' THEN 0 ELSE 1 END,
            br.id DESC"
    );

    if (!$allRequests) {
        die('Unable to load requests. Please check your database table columns.');
    }
}

/* LOAD AVAILABLE BOOKS FOR CLIENT */
$books = false;
$myRequests = false;

if (!$isAdmin) {
    $books = $conn->query(
        "SELECT id, title, author, quantity
         FROM books
         WHERE available = 1 AND quantity > 0
         ORDER BY title ASC"
    );

    if ($borrowerId > 0) {
        $stmt = $conn->prepare(
            "SELECT br.id, b.title, br.quantity, br.reason,
                    br.status, br.requested_at
             FROM book_requests br
             INNER JOIN books b ON b.id = br.book_id
             WHERE br.borrower_id = ?
             ORDER BY br.id DESC"
        );

        $stmt->bind_param('i', $borrowerId);
        $stmt->execute();

        $myRequests = $stmt->get_result();

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Book Requests - BookBridge</title>

<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;background:#f3f6fb;color:#1f2937}
header{background:#2563eb;color:white;padding:20px 6%;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
header h1{margin:0;font-size:25px}
header a{color:white;text-decoration:none;background:#1d4ed8;padding:10px 14px;border-radius:7px}
main{max-width:1400px;margin:30px auto;padding:0 18px}
.panel{background:white;padding:24px;border-radius:12px;margin-bottom:24px;box-shadow:0 3px 12px #0000000d}
h2{margin-top:0}
label{display:block;font-weight:bold;margin:14px 0 6px}
input,select,textarea{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:7px;font-size:15px}
button{background:#2563eb;color:white;border:0;border-radius:7px;padding:10px 13px;cursor:pointer;font-size:14px}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:850px}
th,td{text-align:left;padding:12px;border-bottom:1px solid #e5e7eb;vertical-align:top}
th{background:#eff6ff}
.success,.error{padding:12px;border-radius:7px;margin-bottom:14px}
.success{background:#dcfce7;color:#166534}
.error{background:#fee2e2;color:#991b1b}
.actions{display:flex;gap:7px;flex-wrap:wrap}
.actions form{margin:0}
.approve{background:#15803d}
.reject{background:#b91c1c}
.status{font-weight:bold}
.status-pending{color:#92400e}
.status-approved,.status-completed{color:#166534}
.status-rejected{color:#b91c1c}
.summary{color:#475569}
@media(max-width:650px){
    .panel{padding:16px}
    header{padding:18px}
    header h1{font-size:21px}
    th,td{padding:9px;font-size:13px}
}
</style>
</head>

<body>

<header>
    <h1>Book Requests</h1>
    <a href="index.php">Back to Dashboard</a>
</header>

<main>

<?php if ($message !== ''): ?>
    <div class="success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($isAdmin): ?>

<section class="panel">
    <h2>All Client Requests</h2>
    <p class="summary">
        Review book requests submitted by clients and approve or reject pending requests.
    </p>

    <?php if ($allRequests->num_rows === 0): ?>
        <p>No book requests have been submitted yet.</p>
    <?php else: ?>

    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Request ID</th>
                <th>Client Name</th>
                <th>Email</th>
                <th>Book</th>
                <th>Quantity</th>
                <th>Reason</th>
                <th>Date Requested</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
        <?php while ($request = $allRequests->fetch_assoc()): ?>
            <?php $statusClass = 'status-' . strtolower((string)$request['status']); ?>
            <tr>
                <td><?= (int)$request['id'] ?></td>

                <td>
                    <?= htmlspecialchars($request['client_name'] ?? 'Client record unavailable') ?>
                </td>

                <td>
                    <?= htmlspecialchars($request['client_email'] ?? 'N/A') ?>
                </td>

                <td>
                    <?= htmlspecialchars($request['book_title'] ?? 'Book unavailable') ?>
                </td>

                <td><?= (int)$request['quantity'] ?></td>

                <td>
                    <?= nl2br(htmlspecialchars($request['reason'] ?? '')) ?>
                </td>

                <td>
                    <?= htmlspecialchars($request['requested_at'] ?? '') ?>
                </td>

                <td class="status <?= htmlspecialchars($statusClass) ?>">
                    <?= htmlspecialchars($request['status']) ?>
                </td>

                <td>
                    <?php if ($request['status'] === 'Pending'): ?>
                    <div class="actions">

                        <form method="post" action="book_requests.php">
                            <input type="hidden" name="request_id"
                                   value="<?= (int)$request['id'] ?>">
                            <input type="hidden" name="status" value="Approved">
                            <button class="approve" type="submit">Approve</button>
                        </form>

                        <form method="post" action="book_requests.php">
                            <input type="hidden" name="request_id"
                                   value="<?= (int)$request['id'] ?>">
                            <input type="hidden" name="status" value="Rejected">
                            <button class="reject" type="submit">Reject</button>
                        </form>

                    </div>
                    <?php else: ?>
                        No action needed
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>

    <?php endif; ?>
</section>

<?php else: ?>

<section class="panel">
    <h2>Request a Book</h2>

    <?php if ($borrowerId <= 0): ?>
        <div class="error">
            Your account has no matching record in the borrowers table.
            The account must be linked to a client record before you can request books.
        </div>

    <?php elseif (!$books || $books->num_rows === 0): ?>
        <p>No books are currently available to request.</p>

    <?php else: ?>
        <form method="post" action="book_requests.php">
            <label for="book_id">Choose a book</label>

            <select name="book_id" id="book_id" required>
                <option value="">Select a book</option>

                <?php while ($book = $books->fetch_assoc()): ?>
                    <option value="<?= (int)$book['id'] ?>">
                        <?= htmlspecialchars($book['title']) ?>
                        — <?= htmlspecialchars($book['author'] ?? 'Unknown author') ?>
                        (<?= (int)$book['quantity'] ?> available)
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="quantity">Quantity</label>
            <input type="number" name="quantity" id="quantity"
                   min="1" value="1" required>

            <label for="reason">Reason for requesting</label>
            <textarea name="reason" id="reason" rows="3"
                      maxlength="1000" required></textarea>

            <button type="submit">Submit Request</button>
        </form>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>My Request History</h2>

    <?php if ($borrowerId <= 0): ?>
        <p>Your request history will appear once your account is linked to a client record.</p>

    <?php elseif ($myRequests && $myRequests->num_rows > 0): ?>

    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Request ID</th>
                <th>Book</th>
                <th>Quantity</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Date Requested</th>
            </tr>
        </thead>

        <tbody>
        <?php while ($request = $myRequests->fetch_assoc()): ?>
            <?php $statusClass = 'status-' . strtolower((string)$request['status']); ?>
            <tr>
                <td><?= (int)$request['id'] ?></td>
                <td><?= htmlspecialchars($request['title']) ?></td>
                <td><?= (int)$request['quantity'] ?></td>
                <td><?= nl2br(htmlspecialchars($request['reason'])) ?></td>
                <td class="status <?= htmlspecialchars($statusClass) ?>">
                    <?= htmlspecialchars($request['status']) ?>
                </td>
                <td><?= htmlspecialchars($request['requested_at'] ?? '') ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>

    <?php else: ?>
        <p>You have not submitted any book requests yet.</p>
    <?php endif; ?>
</section>

<?php endif; ?>

</main>
</body>
</html>
