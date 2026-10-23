<?php

session_start();

require_once 'config.php';
require_once 'functions.php';

$conn = new mysqli(
    DB_HOST,
    DB_USER,
    DB_PASS,
    DB_NAME
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if (session_user_type() !== 'admin') {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Report ID not found.");
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT *
    FROM reports
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

$report = $result->fetch_assoc();

if (!$report) {
    die("Report not found.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $penalty = $_POST['penalty'];
    $admin_note = $_POST['admin_note'];
    $status = $_POST['status'];

    $update = $conn->prepare("
        UPDATE reports
        SET penalty = ?,
            admin_note = ?,
            status = ?
        WHERE id = ?
    ");

    $update->bind_param(
        "sssi",
        $penalty,
        $admin_note,
        $status,
        $id
    );

    if ($update->execute()) {

        header("Location: reports.php");
        exit();

    } else {

        echo "Failed to update report.";

    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Report - BookShare</title>

<style>

* {
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    margin: 0;
    background: #f4f7fb;
}

.header {
    background: #2563eb;
    color: white;
    padding: 25px 40px;
}

.container {
    width: 90%;
    max-width: 650px;
    margin: 40px auto;
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.info {
    margin-bottom: 18px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

input,
select,
textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #ccc;
    border-radius: 6px;
}

textarea {
    height: 100px;
    resize: vertical;
}

.save-button {
    width: 100%;
    padding: 13px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
}

.back-button {
    display: block;
    text-align: center;
    margin-top: 15px;
    color: #333;
    text-decoration: none;
}

</style>

</head>

<body>

<div class="header">

<h1>BookShare</h1>

<p>Manage Book Return Report</p>

</div>

<div class="container">

<h2>Report Details</h2>

<div class="info">

<label>Borrower ID</label>

<input
    type="text"
    value="<?php echo htmlspecialchars($report['borrower_id']); ?>"
    readonly
>

</div>

<div class="info">

<label>Book</label>

<input
    type="text"
    value="<?php echo htmlspecialchars($report['book_title']); ?>"
    readonly
>

</div>

<div class="info">

<label>Due Date</label>

<input
    type="text"
    value="<?php echo htmlspecialchars($report['due_date']); ?>"
    readonly
>

</div>

<form method="POST">

<div class="info">

<label>Penalty / Consequence</label>

<select name="penalty" required>

<option value="None">
None
</option>

<option value="Warning">
Warning
</option>

<option value="Borrowing Restriction">
Temporary Borrowing Restriction
</option>

<option value="Account Suspension">
Temporary Account Suspension
</option>

<option value="Admin Review">
Admin Review
</option>

</select>

</div>

<div class="info">

<label>Status</label>

<select name="status" required>

<option value="Borrowed">
Borrowed
</option>

<option value="Overdue">
Overdue
</option>

<option value="Returned">
Returned
</option>

<option value="Resolved">
Resolved
</option>

</select>

</div>

<div class="info">

<label>Admin Note</label>

<textarea
    name="admin_note"
    placeholder="Enter a note about this report..."
></textarea>

</div>

<button
    type="submit"
    class="save-button"
>
    Save Report
</button>

</form>

<a href="reports.php" class="back-button">
    Back to Reports
</a>

</div>

</body>

</html>

<?php

$conn->close();

?>