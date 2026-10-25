```php
<?php
session_start();

require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$conn = connect_db();

$userType = strtolower(trim((string) (
    $_SESSION['user_type'] ?? $_SESSION['role'] ?? ''
)));

$isAdmin = in_array($userType, ['admin', 'admin user'], true);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        http_response_code(403);
        exit('You are not authorized to add books.');
    }

    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if ($title === '' || $author === '') {
        $error = 'Please enter the book title and author.';
    } elseif ($quantity === false || $quantity === null || $quantity < 1) {
        $error = 'Quantity must be at least 1.';
    } else {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO books
                    (title, author, category, description, quantity,
                     available, donation_status, book_condition)
                 VALUES (?, ?, ?, ?, ?, 1, 'Approved', 'Good')"
            );

            $stmt->bind_param(
                'ssssi',
                $title,
                $author,
                $category,
                $description,
                $quantity
            );

            $stmt->execute();
            $stmt->close();

            header('Location: books.php?added=1');
            exit();
        } catch (Throwable $e) {
            $error = 'Unable to add the book. Check your books table structure and try again.';
        }
    }
}

if (isset($_GET['added'])) {
    $message = 'Book added successfully. It is now available in the book list.';
}

$result = $conn->query(
    "SELECT id, title, author, category, description, quantity
     FROM books
     WHERE available = 1 AND quantity > 0
     ORDER BY title ASC"
);

if (!$result) {
    die('Unable to load the available books.');
}

$books = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books - BookBridge</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            padding: 30px 18px;
            background: #f4f7fb;
            color: #20242b;
        }

        .container {
            width: min(100%, 900px);
            margin: auto;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        h1 {
            color: #2563eb;
            margin: 0;
        }

        h2 {
            color: #1d4ed8;
        }

        .back-button,
        .submit-button,
        .request-button {
            display: inline-block;
            padding: 11px 15px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }

        .back-button {
            background: #333;
            color: white;
        }

        .submit-button,
        .request-button {
            background: #2563eb;
            color: white;
        }

        .back-button:hover {
            background: #222;
        }

        .submit-button:hover,
        .request-button:hover {
            background: #1d4ed8;
        }

        .panel,
        .book {
            margin-bottom: 20px;
            padding: 22px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }

        .panel h2,
        .book h2 {
            margin-top: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        input,
        textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }

        textarea {
            resize: vertical;
            min-height: 90px;
        }

        .book p {
            margin: 8px 0;
            line-height: 1.5;
        }

        .book-actions {
            margin-top: 18px;
        }

        .notice {
            padding: 13px;
            margin-bottom: 18px;
            border-radius: 6px;
            background: #dcfce7;
            color: #166534;
        }

        .error {
            padding: 13px;
            margin-bottom: 18px;
            border-radius: 6px;
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-state {
            padding: 28px;
            border-radius: 10px;
            background: white;
            color: #596579;
            text-align: center;
        }

        @media (max-width: 600px) {
            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <header class="page-header">
        <a class="back-button"
           href="<?= $isAdmin ? 'index.php' : 'client_dashboard.php' ?>">
            Back to Dashboard
        </a>
        <h1>BookBridge - Books</h1>
    </header>

    <?php if ($message !== ''): ?>
        <div class="notice">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <section class="panel">
            <h2>Add a Book</h2>
            <p>Add books to the inventory so clients can request them.</p>

            <form method="POST" action="books.php">
                <div class="form-grid">
                    <div class="field">
                        <label for="title">Book Title *</label>
                        <input id="title" name="title" type="text"
                               maxlength="255" required>
                    </div>

                    <div class="field">
                        <label for="author">Author *</label>
                        <input id="author" name="author" type="text"
                               maxlength="255" required>
                    </div>

                    <div class="field">
                        <label for="category">Category</label>
                        <input id="category" name="category" type="text"
                               maxlength="100">
                    </div>

                    <div class="field">
                        <label for="quantity">Quantity *</label>
                        <input id="quantity" name="quantity" type="number"
                               min="1" value="1" required>
                    </div>

                    <div class="field full-width">
                        <label for="description">Description</label>
                        <textarea id="description" name="description"></textarea>
                    </div>

                    <div class="full-width">
                        <button class="submit-button" type="submit">
                            Add Book
                        </button>
                    </div>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section>
        <h2>Available Books</h2>

        <?php if (!$books): ?>
            <p class="empty-state">There are no available books right now.</p>
        <?php else: ?>
            <?php foreach ($books as $book): ?>
                <article class="book">
                    <h2>
                        <?= htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') ?>
                    </h2>

                    <p>
                        <strong>Author:</strong>
                        <?= htmlspecialchars($book['author'], ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <?php if (!empty($book['category'])): ?>
                        <p>
                            <strong>Category:</strong>
                            <?= htmlspecialchars($book['category'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($book['description'])): ?>
                        <p>
                            <?= nl2br(htmlspecialchars($book['description'], ENT_QUOTES, 'UTF-8')) ?>
                        </p>
                    <?php endif; ?>

                    <p>
                        <strong>Available copies:</strong>
                        <?= (int) $book['quantity'] ?>
                    </p>

                    <?php if (!$isAdmin): ?>
                        <div class="book-actions">
                            <a class="request-button"
                               href="request_book.php?book_id=<?= (int) $book['id'] ?>">
                                Request this book
                            </a>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
```