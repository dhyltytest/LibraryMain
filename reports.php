```php
<?php
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_login();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = connect_db();

$userType = strtolower(trim((string)(
    $_SESSION['user_type'] ??
    $_SESSION['role'] ??
    session_user_type()
)));

$isAdmin = in_array($userType, ['admin', 'admin user'], true);
$email = trim((string)($_SESSION['email'] ?? ''));

$message = '';
$error = '';
$borrowerId = 0;
$reports = [];

/* FIND CLIENT ID */
if (!$isAdmin && $email !== '') {
    $clientStmt = $conn->prepare(
        "SELECT id FROM borrowers WHERE email = ? LIMIT 1"
    );

    $clientStmt->bind_param('s', $email);
    $clientStmt->execute();

    $borrower = $clientStmt->get_result()->fetch_assoc();
    $clientStmt->close();

    if ($borrower) {
        $borrowerId = (int) $borrower['id'];
    }
}

/* SAVE PENALTY AND ADMIN NOTE */
if (
    $isAdmin &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['report_id'])
) {
    $reportId = (int) ($_POST['report_id'] ?? 0);
    $penalty = trim((string) ($_POST['penalty'] ?? 'None'));
    $adminNote = trim((string) ($_POST['admin_note'] ?? ''));

    if ($reportId <= 0) {
        $error = 'Invalid report ID.';
    } elseif (strlen($penalty) > 255) {
        $error = 'The penalty must not exceed 255 characters.';
    } else {
        try {
            $updateStmt = $conn->prepare(
                "UPDATE reports
                 SET penalty = ?, admin_note = ?
                 WHERE id = ?"
            );

            $updateStmt->bind_param(
                'ssi',
                $penalty,
                $adminNote,
                $reportId
            );

            $updateStmt->execute();

            if ($updateStmt->affected_rows >= 0) {
                $message = 'Penalty and admin note saved successfully.';
            }

            $updateStmt->close();
        } catch (Throwable $e) {
            if (isset($updateStmt) && $updateStmt instanceof mysqli_stmt) {
                try {
                    $updateStmt->close();
                } catch (Throwable $ignored) {
                }
            }

            $error = 'Unable to save the report. Please try again.';
        }
    }
}

/* LOAD REPORTS */
if ($isAdmin) {
    $sql = "
        SELECT
            r.id,
            r.borrower_id,
            r.book_title,
            r.borrow_date,
            r.due_date,
            r.return_date,
            r.status,
            r.penalty,
            r.admin_note,
            r.created_at,
            b.full_name,
            b.email,
            CASE
                WHEN r.return_date IS NULL
                     AND r.due_date < CURDATE()
                     AND LOWER(r.status) NOT IN ('returned', 'completed')
                THEN DATEDIFF(CURDATE(), r.due_date)
                ELSE 0
            END AS days_overdue
        FROM reports r
        LEFT JOIN borrowers b ON b.id = r.borrower_id
        ORDER BY
            CASE
                WHEN r.return_date IS NULL
                     AND r.due_date < CURDATE()
                     AND LOWER(r.status) NOT IN ('returned', 'completed')
                THEN 0
                ELSE 1
            END,
            r.due_date ASC,
            r.id DESC
    ";

    $result = $conn->query($sql);
} else {
    $sql = "
        SELECT
            id,
            borrower_id,
            book_title,
            borrow_date,
            due_date,
            return_date,
            status,
            penalty,
            admin_note,
            created_at,
            CASE
                WHEN return_date IS NULL
                     AND due_date < CURDATE()
                     AND LOWER(status) NOT IN ('returned', 'completed')
                THEN DATEDIFF(CURDATE(), due_date)
                ELSE 0
            END AS days_overdue
        FROM reports
        WHERE borrower_id = ?
        ORDER BY due_date ASC, id DESC
    ";

    $clientReportsStmt = $conn->prepare($sql);
    $clientReportsStmt->bind_param('i', $borrowerId);
    $clientReportsStmt->execute();
    $result = $clientReportsStmt->get_result();
}

while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
}

if (isset($clientReportsStmt) && $clientReportsStmt instanceof mysqli_stmt) {
    $clientReportsStmt->close();
}

$conn->close();

/* REPORT TOTALS */
$totalReports = count($reports);
$totalOverdue = 0;
$totalReturned = 0;

foreach ($reports as $report) {
    if ((int) $report['days_overdue'] > 0) {
        $totalOverdue++;
    }

    if (
        $report['return_date'] !== null ||
        strtolower((string) $report['status']) === 'returned'
    ) {
        $totalReturned++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reports - BookBridge</title>

<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;background:#f3f6fb;color:#1f2937}
header{background:#2563eb;color:white;padding:20px 6%;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
header h1{margin:0;font-size:25px}
header a{color:white;text-decoration:none;background:#1d4ed8;padding:10px 14px;border-radius:7px}
main{max-width:1450px;margin:30px auto;padding:0 18px}
.panel{background:white;padding:24px;border-radius:12px;margin-bottom:24px;box-shadow:0 3px 12px #0000000d}
h2{margin-top:0}
.stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:24px}
.stat{background:white;padding:20px;border-radius:12px;box-shadow:0 3px 12px #0000000d}
.stat-label{font-size:13px;color:#64748b;margin-bottom:8px}
.stat-number{font-size:28px;font-weight:bold}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:1100px}
th,td{text-align:left;padding:12px;border-bottom:1px solid #e5e7eb;vertical-align:top}
th{background:#eff6ff}
input,textarea{width:100%;padding:9px;border:1px solid #cbd5e1;border-radius:6px;font:inherit;margin:5px 0 10px}
textarea{min-width:150px;min-height:65px}
button{border:0;border-radius:7px;padding:10px 13px;color:white;background:#2563eb;cursor:pointer}
button:hover{background:#1d4ed8}
.success,.error{padding:12px;border-radius:7px;margin-bottom:14px}
.success{background:#dcfce7;color:#166534}
.error{background:#fee2e2;color:#991b1b}
.overdue{color:#b91c1c;font-weight:bold}
.returned{color:#166534;font-weight:bold}
.pending{color:#92400e;font-weight:bold}
.empty{text-align:center;padding:25px;color:#64748b}
@media(max-width:650px){header{padding:18px}main{padding:0 12px}.panel{padding:15px}.stats{grid-template-columns:1fr}}
</style>
</head>

<body>
<header>
    <h1><?= $isAdmin ? 'Borrowing Reports' : 'My Borrowing Reports' ?></h1>
    <a href="index.php">Back to Dashboard</a>
</header>

<main>
    <?php if ($message !== ''): ?>
        <div class="success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total Reports</div>
            <div class="stat-number"><?= $totalReports ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Overdue Books</div>
            <div class="stat-number"><?= $totalOverdue ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Returned Books</div>
            <div class="stat-number"><?= $totalReturned ?></div>
        </div>
    </div>

    <section class="panel">
        <h2><?= $isAdmin ? 'All Borrowing Records' : 'Your Borrowing Records' ?></h2>

        <?php if (!$isAdmin && $borrowerId <= 0): ?>
            <p class="error">Your account is not linked to a client record in the borrowers table.</p>

        <?php elseif (empty($reports)): ?>
            <p class="empty">No report records have been saved yet.</p>

        <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ID</th>

                <?php if ($isAdmin): ?>
                    <th>Borrower</th>
                    <th>Email</th>
                <?php endif; ?>

                <th>Book</th>
                <th>Borrow Date</th>
                <th>Due Date</th>
                <th>Return Date</th>
                <th>Status</th>
                <th>Days Overdue</th>
                <th>Penalty</th>
                <th>Admin Note</th>

                <?php if ($isAdmin): ?>
                    <th>Action</th>
                <?php endif; ?>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($reports as $report): ?>
            <tr>
                <td><?= (int) $report['id'] ?></td>

                <?php if ($isAdmin): ?>
                    <td><?= htmlspecialchars($report['full_name'] ?? 'Client unavailable') ?></td>
                    <td><?= htmlspecialchars($report['email'] ?? 'N/A') ?></td>
                <?php endif; ?>

                <td><?= htmlspecialchars($report['book_title']) ?></td>
                <td><?= htmlspecialchars($report['borrow_date']) ?></td>
                <td><?= htmlspecialchars($report['due_date']) ?></td>
                <td><?= htmlspecialchars($report['return_date'] ?? 'Not returned') ?></td>

                <td>
                    <?php if ((int) $report['days_overdue'] > 0): ?>
                        <span class="overdue">Overdue</span>
                    <?php elseif (
                        $report['return_date'] !== null ||
                        strtolower((string) $report['status']) === 'returned'
                    ): ?>
                        <span class="returned">Returned</span>
                    <?php else: ?>
                        <span class="pending"><?= htmlspecialchars($report['status']) ?></span>
                    <?php endif; ?>
                </td>

                <td><?= (int) $report['days_overdue'] ?></td>
                <td><?= htmlspecialchars($report['penalty'] ?? 'None') ?></td>
                <td><?= nl2br(htmlspecialchars($report['admin_note'] ?? '')) ?></td>

                <?php if ($isAdmin): ?>
                <td>
                    <form method="post" action="reports.php">
                        <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">

                        <label for="penalty<?= (int) $report['id'] ?>">Penalty</label>
                        <input
                            id="penalty<?= (int) $report['id'] ?>"
                            type="text"
                            name="penalty"
                            maxlength="255"
                            value="<?= htmlspecialchars($report['penalty'] ?? 'None') ?>"
                            required
                        >

                        <label for="note<?= (int) $report['id'] ?>">Admin Note</label>
                        <textarea
                            id="note<?= (int) $report['id'] ?>"
                            name="admin_note"
                        ><?= htmlspecialchars($report['admin_note'] ?? '') ?></textarea>

                        <button type="submit">Save</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
```