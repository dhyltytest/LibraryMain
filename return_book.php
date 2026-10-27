
<?php
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_login();

if (session_user_type() !== 'client') {
    header("Location: index.php");
    exit;
}

$conn = connect_db();
$email = trim((string) ($_SESSION['email'] ?? ''));
$message = '';
$error = '';

$stmt = $conn->prepare(
    "SELECT id FROM borrowers WHERE email = ? LIMIT 1"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$borrower = $stmt->get_result()->fetch_assoc();
$stmt->close();

$borrowerId = (int) ($borrower['id'] ?? 0);

if ($borrowerId <= 0) {
    die("Your account is not linked to a client record.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionId = (int) ($_POST['transaction_id'] ?? 0);

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare(
            "SELECT bt.book_id, b.title
             FROM borrow_transactions bt
             JOIN books b ON b.id = bt.book_id
             WHERE bt.id = ?
               AND bt.borrower_id = ?
               AND bt.status = 'Borrowed'
               AND bt.returned_at IS NULL
             FOR UPDATE"
        );

        $stmt->bind_param("ii", $transactionId, $borrowerId);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$book) {
            throw new Exception(
                "This book cannot be returned. It may already be returned."
            );
        }

        $stmt = $conn->prepare(
            "UPDATE borrow_transactions
             SET status = 'Returned', returned_at = NOW()
             WHERE id = ?
               AND borrower_id = ?
               AND status = 'Borrowed'
               AND returned_at IS NULL"
        );

        $stmt->bind_param("ii", $transactionId, $borrowerId);
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            throw new Exception("The return could not be recorded.");
        }
        $stmt->close();

        $stmt = $conn->prepare(
            "UPDATE reports
             SET return_date = CURDATE(), status = 'Returned'
             WHERE borrower_id = ?
               AND book_title = ?
               AND return_date IS NULL
             ORDER BY id DESC
             LIMIT 1"
        );

        $stmt->bind_param("is", $borrowerId, $book['title']);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $message = "Your book return has been recorded.";
    } catch (Throwable $e) {
        $conn->rollback();
        $error = $e instanceof mysqli_sql_exception
            ? "Unable to record the return. Please check your database columns."
            : $e->getMessage();
    }
}

$stmt = $conn->prepare(
    "SELECT bt.id, bt.borrowed_at, bt.book_id, b.title
     FROM borrow_transactions bt
     JOIN books b ON b.id = bt.book_id
     WHERE bt.borrower_id = ?
       AND bt.status = 'Borrowed'
       AND bt.returned_at IS NULL
     ORDER BY bt.borrowed_at DESC"
);

$stmt->bind_param("i", $borrowerId);
$stmt->execute();
$books = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Return Books - BookBridge</title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    color: #20242b;
}
header {
    background: #2563eb;
    color: white;
    padding: 20px;
}
main {
    max-width: 900px;
    margin: 30px auto;
    padding: 0 18px;
}
.book, .message {
    background: white;
    padding: 22px;
    margin-bottom: 16px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
}
button {
    background: #2563eb;
    color: white;
    border: 0;
    padding: 11px 16px;
    border-radius: 6px;
    cursor: pointer;
}
button:hover { background: #1d4ed8; }
.success { color: #166534; background: #dcfce7; }
.error { color: #991b1b; background: #fee2e2; }
a { color: #2563eb; }
</style>
</head>
<body>
<header>
    <h2>BookBridge — Return Books</h2>
</header>

<main>
    <p><a href="client_dashboard.php">← Back to Dashboard</a></p>
    <h1>My Borrowed Books</h1>

    <?php if ($message !== ''): ?>
        <div class="message success">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($books->num_rows > 0): ?>
        <?php while ($book = $books->fetch_assoc()): ?>
            <section class="book">
                <h3><?= htmlspecialchars($book['title']) ?></h3>
                <p>Borrowed on:
                    <?= htmlspecialchars($book['borrowed_at'] ?? 'N/A') ?>
                </p>

                <form method="POST">
                    <input type="hidden" name="transaction_id"
                           value="<?= (int) $book['id'] ?>">
                    <button type="submit">Return Book</button>
                </form>
            </section>
        <?php endwhile; ?>
    <?php else: ?>
        <section class="book">
            <p>You have no books waiting to be returned.</p>
        </section>
    <?php endif; ?>
</main>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
