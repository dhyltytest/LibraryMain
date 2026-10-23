<?php

require_once __DIR__ . '/db.php';

$conn = connect_db();
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
    <title>Available Books - BookBridge</title>
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

        .page-header {
            display: flex;
            width: min(100%, 900px);
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin: 0 auto;
        }

        h1 {
            margin: 0;
            color: #2563eb;
            text-align: center;
        }

        .back-button,
        .request-button {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            white-space: nowrap;
        }

        .back-button {
            background: #333;
            color: #fff;
        }

        .back-button:hover {
            background: #222;
        }

        .book-list {
            width: min(100%, 900px);
            margin: 30px auto;
        }

        .book {
            margin-bottom: 15px;
            padding: 20px;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }

        .book h2 {
            margin: 0 0 12px;
            color: #1d4ed8;
        }

        .book p {
            margin: 8px 0;
        }

        .book-actions {
            margin-top: 18px;
        }

        .request-button {
            background: #2563eb;
            color: #fff;
        }

        .request-button:hover {
            background: #1d4ed8;
        }

        .empty-state {
            padding: 28px;
            border-radius: 10px;
            background: #fff;
            color: #596579;
            text-align: center;
        }

        @media (max-width: 600px) {
            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            h1 {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <header class="page-header">
        <a class="back-button" href="index.php">Back to Dashboard</a>
        <h1>BookBridge - Available Books</h1>
    </header>

    <main class="book-list">
        <?php if (!$books): ?>
            <p class="empty-state">There are no available books right now.</p>
        <?php else: ?>
            <?php foreach ($books as $book): ?>
                <article class="book">
                    <h2><?= htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p><strong>Author:</strong> <?= htmlspecialchars($book['author'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($book['category'])): ?>
                        <p><strong>Category:</strong> <?= htmlspecialchars($book['category'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if (!empty($book['description'])): ?>
                        <p><?= nl2br(htmlspecialchars($book['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                    <?php endif; ?>
                    <p><strong>Available copies:</strong> <?= (int) $book['quantity'] ?></p>
                    <div class="book-actions">
                        <a class="request-button" href="request_book.php?book_id=<?= (int) $book['id'] ?>">
                            Request this book
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>
