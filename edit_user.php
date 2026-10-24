<?php

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_login();

if (session_user_type() !== 'admin') {
    header('Location: index.php');
    exit();
}

$userId = filter_var(
    $_GET['id'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($userId === false || $userId === null) {
    http_response_code(400);
    die('Invalid user ID.');
}

$user = find_user_by_id($userId);

if (!$user) {
    http_response_code(404);
    die('User not found.');
}

if (empty($_SESSION['edit_user_csrf'])) {
    $_SESSION['edit_user_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$fullName = (string) $user['full_name'];
$email = (string) $user['email'];
$phone = (string) ($user['phone'] ?? '');
$organization = (string) ($user['organization'] ?? '');
$userType = (string) $user['user_type'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if (
        $csrfToken === '' ||
        !hash_equals($_SESSION['edit_user_csrf'], $csrfToken)
    ) {
        http_response_code(400);
        $error = 'Your form session expired. Reload the page and try again.';
    } else {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $organization = trim((string) ($_POST['organization'] ?? ''));
        $userType = trim((string) ($_POST['user_type'] ?? ''));

        if ($fullName === '' || $email === '' || $userType === '') {
            $error = 'Please complete all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!in_array($userType, ['admin', 'client'], true)) {
            $error = 'Invalid user type.';
        } elseif (
            (int) $user['id'] === (int) ($_SESSION['user_id'] ?? 0) &&
            $userType !== 'admin'
        ) {
            $error = 'You cannot change your own account from admin to client.';
        } else {
            $conn = connect_db();

            try {
                $conn->begin_transaction();

                $stmt = $conn->prepare(
                    'SELECT id
                     FROM users
                     WHERE email = ? AND id <> ?
                     LIMIT 1'
                );
                if (!$stmt) {
                    throw new RuntimeException('Unable to check the email address.');
                }

                $stmt->bind_param('si', $email, $userId);
                $stmt->execute();
                $duplicate = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($duplicate) {
                    throw new DomainException('That email address is already being used.');
                }

                $stmt = $conn->prepare(
                    'UPDATE users
                     SET full_name = ?, email = ?, phone = ?, organization = ?, user_type = ?
                     WHERE id = ?'
                );
                if (!$stmt) {
                    throw new RuntimeException('Unable to prepare the user update.');
                }

                $stmt->bind_param(
                    'sssssi',
                    $fullName,
                    $email,
                    $phone,
                    $organization,
                    $userType,
                    $userId
                );
                $stmt->execute();
                $stmt->close();

                $oldEmail = (string) $user['email'];
                $stmt = $conn->prepare(
                    'SELECT id FROM borrowers WHERE email = ? LIMIT 1 FOR UPDATE'
                );
                if (!$stmt) {
                    throw new RuntimeException('Unable to look up the borrower profile.');
                }

                $stmt->bind_param('s', $oldEmail);
                $stmt->execute();
                $borrower = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($borrower && $userType === 'client') {
                    $borrowerId = (int) $borrower['id'];
                    $stmt = $conn->prepare(
                        'UPDATE borrowers
                         SET full_name = ?, email = ?, phone = ?, organization = ?
                         WHERE id = ?'
                    );
                    if (!$stmt) {
                        throw new RuntimeException('Unable to prepare the borrower update.');
                    }

                    $stmt->bind_param(
                        'ssssi',
                        $fullName,
                        $email,
                        $phone,
                        $organization,
                        $borrowerId
                    );
                    $stmt->execute();
                    $stmt->close();
                } elseif ($borrower && $userType === 'admin') {
                    $borrowerId = (int) $borrower['id'];
                    $stmt = $conn->prepare('DELETE FROM borrowers WHERE id = ?');
                    if (!$stmt) {
                        throw new RuntimeException('Unable to prepare the borrower update.');
                    }

                    $stmt->bind_param('i', $borrowerId);
                    $stmt->execute();
                    $stmt->close();
                } elseif (!$borrower && $userType === 'client') {
                    $passwordHash = password_hash(
                        bin2hex(random_bytes(32)),
                        PASSWORD_DEFAULT
                    );
                    $borrowerRole = 'recipient';
                    $stmt = $conn->prepare(
                        'INSERT INTO borrowers
                            (full_name, email, password, role, phone, organization)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    if (!$stmt) {
                        throw new RuntimeException('Unable to prepare the borrower profile.');
                    }

                    $stmt->bind_param(
                        'ssssss',
                        $fullName,
                        $email,
                        $passwordHash,
                        $borrowerRole,
                        $phone,
                        $organization
                    );
                    $stmt->execute();
                    $stmt->close();
                }

                $conn->commit();
                $_SESSION['edit_user_csrf'] = bin2hex(random_bytes(32));

                if ((int) $user['id'] === (int) ($_SESSION['user_id'] ?? 0)) {
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['email'] = $email;
                }

                header('Location: master_list.php?success=User+updated+successfully');
                exit();
            } catch (DomainException $e) {
                $conn->rollback();
                $error = $e->getMessage();
            } catch (Throwable $e) {
                $conn->rollback();
                $error = 'Unable to update this user. The database rejected the change.';
            }
        }

        if ($error !== '') {
            $user['full_name'] = $fullName;
            $user['email'] = $email;
            $user['phone'] = $phone;
            $user['organization'] = $organization;
            $user['user_type'] = $userType;
        }
    }
}

function edit_user_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - BookShare</title>
    <style>
        * { box-sizing: border-box; font-family: Arial, sans-serif; }
        body { margin: 0; padding: 30px 16px; background: #f4f7fb; color: #20242b; }
        main { width: min(100%, 620px); margin: 0 auto; }
        .card { padding: 28px; border-radius: 10px; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,.08); }
        h1 { margin-top: 0; color: #1d4ed8; }
        label { display: block; margin: 16px 0 6px; font-weight: bold; }
        input, select { width: 100%; padding: 11px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 15px; }
        .actions { display: flex; gap: 10px; margin-top: 22px; }
        button, .cancel { padding: 11px 16px; border: 0; border-radius: 5px; background: #2563eb; color: #fff; font-weight: bold; text-decoration: none; cursor: pointer; }
        .cancel { background: #4b5563; }
        .error { padding: 12px; border-radius: 5px; background: #fee2e2; color: #991b1b; }
        @media (max-width: 480px) { .actions { flex-direction: column; } }
    </style>
</head>
<body>
    <main>
        <section class="card">
            <h1>Edit User</h1>
            <?php if ($error !== ''): ?>
                <p class="error" role="alert"><?= edit_user_escape($error) ?></p>
            <?php endif; ?>
            <form method="post" action="edit_user.php?id=<?= (int) $user['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= edit_user_escape($_SESSION['edit_user_csrf']) ?>">

                <label for="full_name">Full name *</label>
                <input id="full_name" name="full_name" type="text" required value="<?= edit_user_escape($fullName) ?>">

                <label for="email">Email *</label>
                <input id="email" name="email" type="email" required value="<?= edit_user_escape($email) ?>">

                <label for="phone">Phone</label>
                <input id="phone" name="phone" type="text" value="<?= edit_user_escape($phone) ?>">

                <label for="organization">School / Organization</label>
                <input id="organization" name="organization" type="text" value="<?= edit_user_escape($organization) ?>">

                <label for="user_type">User type *</label>
                <select id="user_type" name="user_type" required>
                    <option value="admin" <?= $userType === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="client" <?= $userType === 'client' ? 'selected' : '' ?>>Client</option>
                </select>

                <div class="actions">
                    <button type="submit">Save Changes</button>
                    <a class="cancel" href="master_list.php">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
