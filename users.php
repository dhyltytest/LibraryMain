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

$admins = $conn->query("
    SELECT id, full_name, email, phone, organization, user_type
    FROM users
    ORDER BY id DESC
");

$clients = $conn->query("
    SELECT id, full_name, email, phone, organization
    FROM borrowers
    ORDER BY id DESC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Users - BookShare</title>

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
    margin-bottom: 35px;
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

tr:hover {
    background: #f5f5f5;
}

.role {
    font-weight: bold;
}

</style>

</head>

<body>

<div class="header">

    <h1>BookShare</h1>

    <p>User Management</p>

</div>

<div class="container">

<div class="top-bar">

    <h2>Users</h2>

    <a href="index.php" class="back-button">
        Back to Dashboard
    </a>

</div>

<h3>Admin Users</h3>

<table>

<tr>
    <th>ID</th>
    <th>Full Name</th>
    <th>Email</th>
    <th>Phone</th>
    <th>Organization</th>
    <th>User Type</th>
</tr>

<?php while ($row = $admins->fetch_assoc()) { ?>

<tr>

<td><?php echo htmlspecialchars($row['id']); ?></td>

<td><?php echo htmlspecialchars($row['full_name']); ?></td>

<td><?php echo htmlspecialchars($row['email']); ?></td>

<td><?php echo htmlspecialchars($row['phone']); ?></td>

<td><?php echo htmlspecialchars($row['organization']); ?></td>

<td class="role">Admin User</td>

</tr>

<?php } ?>

</table>


<h3>Client Users</h3>

<table>

<tr>
    <th>ID</th>
    <th>Full Name</th>
    <th>Email</th>
    <th>Phone</th>
    <th>Organization</th>
    <th>User Type</th>
</tr>

<?php while ($row = $clients->fetch_assoc()) { ?>

<tr>

<td><?php echo htmlspecialchars($row['id']); ?></td>

<td><?php echo htmlspecialchars($row['full_name']); ?></td>

<td><?php echo htmlspecialchars($row['email']); ?></td>

<td><?php echo htmlspecialchars($row['phone']); ?></td>

<td><?php echo htmlspecialchars($row['organization']); ?></td>

<td class="role">Client User</td>

</tr>

<?php } ?>

</table>

</div>

</body>

</html>

<?php

$conn->close();

?>