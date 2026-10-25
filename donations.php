
<?php
session_start();
require_once __DIR__ . '/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
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

$title = '';
$author = '';
$category = '';
$description = '';
$quantity = 1;

$categories = [
    'Education',
    'Science',
    'Technology',
    'Mathematics',
    'Literature',
    'History',
    'Reference',
    'Other'
];

$borrowerId = 0;

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

/* ADMIN: APPROVE OR REJECT A DONATION */
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId = (int)($_POST['book_id'] ?? 0);
    $newStatus = (string)($_POST['donation_status'] ?? '');

    if (
        $bookId <= 0 ||
        !in_array($newStatus, ['Approved', 'Rejected'], true)
    ) {
        $error = 'Invalid donation or status.';
    } else {
        try {
            $conn->begin_transaction();

            $available = ($newStatus === 'Approved') ? 1 : 0;

            $stmt = $conn->prepare(
                "UPDATE books
                 SET donation_status = ?, available = ?
                 WHERE id = ? AND donation_status = 'Pending'"
            );
            $stmt->bind_param('sii', $newStatus, $available, $bookId);
            $stmt->execute();
            $updatedBook = $stmt->affected_rows;
            $stmt->close();

            if ($updatedBook !== 1) {
                throw new Exception(
                    'The book could not be updated. It may no longer be pending.'
                );
            }

            $stmt = $conn->prepare(
                "UPDATE donations
                 SET status = ?
                 WHERE book_id = ? AND status = 'Pending'"
            );
            $stmt->bind_param('si', $newStatus, $bookId);
            $stmt->execute();
            $updatedDonation = $stmt->affected_rows;
            $stmt->close();

            if ($updatedDonation !== 1) {
                throw new Exception(
                    'The matching donation record was not found. The changes were cancelled.'
                );
            }

            $conn->commit();

            $message = 'Donation #' . $bookId . ' has been ' .
                strtolower($newStatus) . '.';
        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

/* CLIENT: SUBMIT A DONATION */
if (!$isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
    $author = trim((string)($_POST['author'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $quantity = (int)($_POST['quantity'] ?? 0);

    if ($borrowerId <= 0) {
        $error = 'Your account is not linked to a client record in the borrowers table.';
    } elseif (
        $title === '' ||
        $author === '' ||
        !in_array($category, $categories, true) ||
        $quantity < 1 ||
        $quantity > 10000
    ) {
        $error = 'Please enter a valid title, author, category, and quantity.';
    } else {
        try {
            $conn->begin_transaction();

            $donationStatus = 'Pending';
            $available = 0;

            $stmt = $conn->prepare(
                "INSERT INTO books
                 (title, author, category, donor_id, description,
                  quantity, donation_status, available)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                'sssisisi',
                $title,
                $author,
                $category,
                $borrowerId,
                $description,
                $quantity,
                $donationStatus,
                $available
            );

            $stmt->execute();
            $bookId = (int)$conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare(
                "INSERT INTO donations
                 (donor_id, book_id, quantity, description, status)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                'iiiss',
                $borrowerId,
                $bookId,
                $quantity,
                $description,
                $donationStatus
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $message = 'Thank you! Your donation was saved and is waiting for admin approval.';

            $title = '';
            $author = '';
            $category = '';
            $description = '';
            $quantity = 1;
        } catch (Throwable $e) {
            $conn->rollback();
            $error = 'Unable to save the donation: ' . $e->getMessage();
        }
    }
}

/* ADMIN: LOAD DONATIONS FROM BOTH TABLES */
$donations = false;

if ($isAdmin) {
    $donations = $conn->query(
        "SELECT
            b.id,
            d.id AS donation_id,
            b.title,
            b.author,
            b.category,
            d.quantity,
            d.description,
            b.donation_status,
            d.donated_at AS created_at,
            borrower.full_name AS donor_name,
            borrower.email AS donor_email
         FROM donations d
         INNER JOIN books b ON b.id = d.book_id
         LEFT JOIN borrowers borrower ON borrower.id = d.donor_id
         ORDER BY
            CASE WHEN b.donation_status = 'Pending' THEN 0 ELSE 1 END,
            d.id DESC"
    );
}

/* CLIENT: LOAD APPROVED BOOKS */
$books = false;

if (!$isAdmin) {
    $books = $conn->query(
        "SELECT id, title, author, category, quantity
         FROM books
         WHERE available = 1
           AND quantity > 0
           AND donation_status = 'Approved'
         ORDER BY id DESC
         LIMIT 20"
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Donations - BookBridge</title>
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
button{border:0;border-radius:7px;padding:10px 13px;color:white;cursor:pointer}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:850px}
th,td{text-align:left;padding:12px;border-bottom:1px solid #e5e7eb;vertical-align:top}
th{background:#eff6ff}
.success,.error{padding:12px;border-radius:7px;margin-bottom:14px}
.success{background:#dcfce7;color:#166534}
.error{background:#fee2e2;color:#991b1b}
.approve{background:#15803d}
.reject{background:#b91c1c}
.actions{display:flex;gap:7px;flex-wrap:wrap}
.actions form{margin:0}
.status{font-weight:bold}
.status-pending{color:#92400e}
.status-approved{color:#166534}
.status-rejected{color:#b91c1c}
@media(max-width:650px){.panel{padding:16px}header{padding:18px}th,td{padding:9px;font-size:13px}}
</style>
</head>
<body>
<header>
    <h1><?= $isAdmin ? 'Manage Donations' : 'Donate Books' ?></h1>
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
    <h2>All Donations</h2>

    <?php if ($donations->num_rows === 0): ?>
        <p>No donations have been submitted yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Donor</th>
            <th>Email</th>
            <th>Book</th>
            <th>Category</th>
            <th>Quantity</th>
            <th>Description</th>
            <th>Date</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($donation = $donations->fetch_assoc()): ?>
        <tr>
            <td><?= (int)$donation['id'] ?></td>
            <td><?= htmlspecialchars($donation['donor_name'] ?? 'Donor unavailable') ?></td>
            <td><?= htmlspecialchars($donation['donor_email'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($donation['title']) ?><br>
                <small><?= htmlspecialchars($donation['author']) ?></small>
            </td>
            <td><?= htmlspecialchars($donation['category'] ?? '') ?></td>
            <td><?= (int)$donation['quantity'] ?></td>
            <td><?= nl2br(htmlspecialchars($donation['description'] ?? '')) ?></td>
            <td><?= htmlspecialchars($donation['created_at'] ?? '') ?></td>
            <td class="status status-<?= strtolower($donation['donation_status'] ?? 'pending') ?>">
                <?= htmlspecialchars($donation['donation_status'] ?? 'Pending') ?>
            </td>
            <td>
                <?php if ($donation['donation_status'] === 'Pending'): ?>
                <div class="actions">
                    <form method="post" action="donations.php">
                        <input type="hidden" name="book_id" value="<?= (int)$donation['id'] ?>">
                        <input type="hidden" name="donation_status" value="Approved">
                        <button class="approve" type="submit">Approve</button>
                    </form>
                    <form method="post" action="donations.php">
                        <input type="hidden" name="book_id" value="<?= (int)$donation['id'] ?>">
                        <input type="hidden" name="donation_status" value="Rejected">
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
    <h2>Submit a Book Donation</h2>
    <p>Your donation will be reviewed by an administrator before becoming available in the catalog.</p>

    <?php if ($borrowerId <= 0): ?>
        <div class="error">Your account is not linked to a client record in the borrowers table.</div>
    <?php else: ?>
    <form method="post" action="donations.php">
        <label for="title">Book Title</label>
        <input type="text" id="title" name="title" maxlength="150"
               value="<?= htmlspecialchars($title) ?>" required>

        <label for="author">Author</label>
        <input type="text" id="author" name="author" maxlength="150"
               value="<?= htmlspecialchars($author) ?>" required>

        <label for="category">Category</label>
        <select id="category" name="category" required>
            <option value="">Select a category</option>
            <?php foreach ($categories as $item): ?>
                <option value="<?= htmlspecialchars($item) ?>"
                    <?= $category === $item ? 'selected' : '' ?>>
                    <?= htmlspecialchars($item) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="description">Description (optional)</label>
        <textarea id="description" name="description" rows="3" maxlength="2000"><?= htmlspecialchars($description) ?></textarea>

        <label for="quantity">Number of Copies</label>
        <input type="number" id="quantity" name="quantity" min="1" max="10000"
               value="<?= (int)$quantity ?>" required>

        <button type="submit">Submit Donation</button>
    </form>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Available Books in the Catalog</h2>
    <?php if ($books && $books->num_rows > 0): ?>
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Book ID</th>
            <th>Title</th>
            <th>Author</th>
            <th>Category</th>
            <th>Available Copies</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($book = $books->fetch_assoc()): ?>
        <tr>
            <td><?= (int)$book['id'] ?></td>
            <td><?= htmlspecialchars($book['title']) ?></td>
            <td><?= htmlspecialchars($book['author'] ?? '') ?></td>
            <td><?= htmlspecialchars($book['category'] ?? '') ?></td>
            <td><?= (int)$book['quantity'] ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
        <p>No approved books are currently available.</p>
    <?php endif; ?>
</section>

<?php endif; ?>

</main>
</body>
</html>
