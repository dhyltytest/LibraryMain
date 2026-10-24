<?php

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_login();

if (session_user_type() !== 'admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('User deletion must be submitted from the master list.');
}

$userId = filter_var(
    $_POST['id'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (
    $userId === false ||
    $userId === null ||
    $csrfToken === '' ||
    empty($_SESSION['master_list_csrf']) ||
    !hash_equals($_SESSION['master_list_csrf'], $csrfToken)
) {
    http_response_code(400);
    exit('Invalid deletion request. Return to the master list and try again.');
}

if ((int) $userId === (int) ($_SESSION['user_id'] ?? 0)) {
    http_response_code(400);
    exit('You cannot delete your own administrator account.');
}

$user = find_user_by_id($userId);
if (!$user) {
    header('Location: master_list.php?error=User+not+found');
    exit();
}

$conn = connect_db();

try {
    $conn->begin_transaction();

    if ($user['user_type'] === 'client') {
        $stmt = $conn->prepare('DELETE FROM borrowers WHERE email = ?');
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare the borrower deletion.');
        }

        $stmt->bind_param('s', $user['email']);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the user deletion.');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();

    if ($deleted !== 1) {
        throw new RuntimeException('The user record was not deleted.');
    }

    $conn->commit();

    header('Location: master_list.php?success=User+deleted+successfully');
    exit();
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(409);
    $message = 'Unable to delete this user. Related records may still depend on the account.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unable to Delete User - BookShare</title>
    <style>
        body { margin: 0; padding: 30px 16px; background: #f4f7fb; color: #20242b; font-family: Arial, sans-serif; }
        main { width: min(100%, 560px); margin: 0 auto; padding: 24px; border-radius: 10px; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,.08); }
        h1 { color: #b91c1c; }
        a { display: inline-block; margin-top: 12px; color: #1d4ed8; font-weight: bold; }
    </style>
</head>
<body>
    <main>
        <h1>Unable to delete user</h1>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <a href="master_list.php">Return to Master List</a>
    </main>
</body>
</html>
