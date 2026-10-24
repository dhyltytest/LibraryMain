```php
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

$conn->set_charset("utf8mb4");

/* Admin accounts only */
$admins = $conn->query("
    SELECT id, full_name, email, phone, organization, user_type
    FROM users
    WHERE LOWER(TRIM(user_type)) IN ('admin', 'admin user')
    ORDER BY id DESC
");

if (!$admins) {
    die("Admin query failed: " . $conn->error);
}

/* Client accounts only */
$clients = $conn->query("
    SELECT id, full_name, email, phone, organization
    FROM borrowers
    ORDER BY id DESC
");

if (!$clients) {
    die("Client query failed: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Users - BookBridge</title>

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
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.back-button {
    background: #333;
    color: white;
    padding: 10px 15px;
    text-decoration: none;
    border-radius: 5px;
}

.section {
    margin-top: 30px;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 650px;
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

tr:hover td {
    background: #f5f5f5;
}

.role {
    font-weight: bold;
}

.empty {
    padding: 15px;
    background: #f4f7fb;
    border-radius: 5px;
    margin-bottom: 25px;
}

@media (max-width: 600px) {
    .header {
        padding: 20px;
    }

    .container {
        padding: 15px;
        margin: 15px auto;
    }
}
</style>
</head>

<body>

<div class="header">
    <h1>BookBridge</h1>
    <p>User Management</p>
</div>

<div class="container">

    <div class="top-bar">
        <h2>Users</h2>
        <a href="index.php" class="back-button">Back to Dashboard</a>
    </div>

    <div class="section">
        <h3>Admin Users</h3>

        <?php if ($admins->num_rows > 0) { ?>
        <div class="table-wrapper">
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
                    <td><?= htmlspecialchars($row['id'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['full_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['phone'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['organization'] ?? '') ?></td>
                    <td class="role">Admin User</td>
                </tr>
                <?php } ?>
            </table>
        </div>
        <?php } else { ?>
            <p class="empty">No admin accounts found.</p>
        <?php } ?>
    </div>

    <div class="section">
        <h3>Client Users</h3>

        <?php if ($clients->num_rows > 0) { ?>
        <div class="table-wrapper">
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
                    <td><?= htmlspecialchars($row['id'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['full_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['phone'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['organization'] ?? '') ?></td>
                    <td class="role">Client User</td>
                </tr>
                <?php } ?>
            </table>
        </div>
        <?php } else { ?>
            <p class="empty">No client accounts found.</p>
        <?php } ?>
    </div>

</div>

</body>
</html>

<?php
$conn->close();
?>
```