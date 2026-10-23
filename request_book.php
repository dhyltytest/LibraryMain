<?php

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_login();

if (session_user_type() !== 'client') {
    header('Location: index.php');
    exit();
}

$bookIdValue = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['book_id'] ?? '')
    : ($_GET['book_id'] ?? '');
$bookId = filter_var($bookIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($bookId === false) {
    http_response_code(404);
    die('The selected book was not found. Return to the book list and choose a book.');
}

$conn = connect_db();
$stmt = $conn->prepare(
    'SELECT id, title, author, category, quantity
     FROM books
     WHERE id = ? AND available = 1 AND quantity > 0
     LIMIT 1'
);

if (!$stmt) {
    die('Unable to look up the selected book.');
}

$stmt->bind_param('i', $bookId);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
    http_response_code(404);
    die('This book is not currently available. Return to the book list to choose another book.');
}

if (empty($_SESSION['book_request_csrf'])) {
    $_SESSION['book_request_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$submitted = isset($_GET['submitted']) && $_GET['submitted'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['book_request_csrf'], $csrfToken)) {
        http_response_code(400);
        $error = 'Your form session expired. Please try submitting the request again.';
    } else {
        $quantity = filter_var(
            $_POST['quantity'] ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => (int) $book['quantity']]]
        );
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($quantity === false) {
            $error = 'Choose a quantity between 1 and the available copies.';
        } elseif ($reason === '') {
            $error = 'Please tell us why you are requesting this book.';
        } elseif (strlen($reason) > 5000) {
            $error = 'The request reason must be 5,000 characters or fewer.';
        } else {
            $email = trim((string) ($_SESSION['email'] ?? ''));
            $stmt = $conn->prepare('SELECT id FROM borrowers WHERE email = ? LIMIT 1');

            if (!$stmt) {
                die('Unable to look up your borrower account.');
            }

            $stmt->bind_param('s', $email);
            $stmt->execute();
            $borrower = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$borrower) {
                $error = 'Your borrower profile could not be found. Please contact an administrator.';
            } else {
                $borrowerId = (int) $borrower['id'];
                $stmt = $conn->prepare(
                    "INSERT INTO book_requests (book_id, borrower_id, quantity, reason, status)
                     VALUES (?, ?, ?, ?, 'Pending')"
                );

                if (!$stmt) {
                    die('Unable to prepare your book request.');
                }

                $stmt->bind_param('iiis', $bookId, $borrowerId, $quantity, $reason);
                $stmt->execute();
                $stmt->close();
                $conn->close();

                $_SESSION['book_request_csrf'] = bin2hex(random_bytes(32));
                header('Location: request_book.php?book_id=' . $bookId . '&submitted=1');
                exit();
            }
        }
    }
}

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request a Book - BookBridge</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 28px 16px;
            background: #f4f7fb;
            color: #20242b;
        }

        .container {
            width: min(100%, 600px);
            margin: 0 auto;
        }

        .back-button {
            display: inline-block;
            margin-bottom: 18px;
            padding: 10px 15px;
            border-radius: 6px;
            background: #333;
            color: #fff;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
        }

        .card {
            padding: 28px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin: 0 0 8px;
            color: #2563eb;
            font-size: 26px;
        }

        .book-details,
        .notice {
            margin: 12px 0 22px;
            color: #596579;
            line-height: 1.5;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }

        input {
            max-width: 140px;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .field {
            margin-top: 18px;
        }

        button {
            margin-top: 18px;
            padding: 12px 18px;
            border: 0;
            border-radius: 6px;
            background: #2563eb;
            color: #fff;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 6px;
            line-height: 1.45;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        @media (max-width: 480px) {
            .card {
                padding: 22px 18px;
            }
        }
    </style>
</head>
<body>
    <main class="container">
        <a class="back-button" href="books.php">Back to Books</a>

        <section class="card">
            <h1>Request this book</h1>
            <p class="book-details">
                <strong><?= htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                Author: <?= htmlspecialchars($book['author'], ENT_QUOTES, 'UTF-8') ?>
                <?php if (!empty($book['category'])): ?>
                    <br>Category: <?= htmlspecialchars($book['category'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </p>

            <?php if ($submitted): ?>
                <div class="message success" role="status">
                    Your request has been submitted and is pending review.
                </div>
                <p class="notice">You can return to the book list to request another book.</p>
            <?php else: ?>
                <?php if ($error !== ''): ?>
                    <div class="message error" role="alert">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <p class="notice">Available copies: <?= (int) $book['quantity'] ?>. Your request will be sent using your signed-in account.</p>
                <form method="post">
                    <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['book_request_csrf'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="field">
                        <label for="quantity">Quantity</label>
                        <input id="quantity" name="quantity" type="number" min="1" max="<?= (int) $book['quantity'] ?>" value="1" required>
                    </div>

                    <div class="field">
                        <label for="reason">Why would you like this book?</label>
                        <textarea id="reason" name="reason" maxlength="5000" required></textarea>
                    </div>

                    <button type="submit">Submit Book Request</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
