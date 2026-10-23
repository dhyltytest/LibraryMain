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
        admin_note
    FROM reports
    ORDER BY due_date ASC
";

$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Reports - BookShare</title>

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

.header h1 {
    margin: 0;
}

.container {
    width: 95%;
    margin: 30px auto;
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.back-button {
    background: #333;
    color: white;
    padding: 10px 15px;
    text-decoration: none;
    border-radius: 5px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #2563eb;
    color: white;
    padding: 12px;
    text-align: left;
}

td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
}

.overdue {
    color: #dc2626;
    font-weight: bold;
}

.returned {
    color: #16a34a;
    font-weight: bold;
}

.action-button {
    background: #dc2626;
    color: white;
    text-decoration: none;
    padding: 7px 10px;
    border-radius: 5px;
}

</style>

</head>

<body>

<div class="header">

    <h1>BookShare</h1>

    <p>Book Return Reports</p>

</div>

<div class="container">

<div class="top-bar">

    <h2>Reports</h2>

    <a href="index.php" class="back-button">
        Back to Dashboard
    </a>

</div>

<table>

<tr>

    <th>ID</th>

    <th>Borrower ID</th>

    <th>Book</th>

    <th>Borrow Date</th>

    <th>Due Date</th>

    <th>Return Date</th>

    <th>Status</th>

    <th>Penalty</th>

    <th>Action</th>

</tr>

<?php

if ($result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {

        $today = date('Y-m-d');

        if (
            $row['return_date'] == NULL &&
            $row['due_date'] < $today
        ) {
            $status = "Overdue";
        } else {
            $status = $row['status'];
        }

?>

<tr>

<td>
    <?php echo htmlspecialchars($row['id']); ?>
</td>

<td>
    <?php echo htmlspecialchars($row['borrower_id']); ?>
</td>

<td>
    <?php echo htmlspecialchars($row['book_title']); ?>
</td>

<td>
    <?php echo htmlspecialchars($row['borrow_date']); ?>
</td>

<td>
    <?php echo htmlspecialchars($row['due_date']); ?>
</td>

<td>
    <?php echo htmlspecialchars($row['return_date'] ?? 'Not Returned'); ?>
</td>

<td>

<?php if ($status == "Overdue") { ?>

<span class="overdue">
    OVERDUE
</span>

<?php } else { ?>

<span class="returned">
    <?php echo htmlspecialchars($status); ?>
</span>

<?php } ?>

</td>

<td>
    <?php echo htmlspecialchars($row['penalty']); ?>
</td>

<td>

<a
    href="update_penalty.php?id=<?php echo $row['id']; ?>"
    class="action-button"
>
    Manage
</a>

</td>

</tr>

<?php

    }

} else {

?>

<tr>

<td colspan="9" style="text-align:center; padding:30px;">

No reports available.

</td>

</tr>

<?php

}

?>

</table>

</div>

</body>

</html>

<?php

$conn->close();

?>