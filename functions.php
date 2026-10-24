
<?php

function session_user_type(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $type = $_SESSION['user_type'] ?? $_SESSION['role'] ?? '';
    return strtolower(trim((string) $type));
}

function require_login(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['user_id'])) {
        header('Location: login.html');
        exit();
    }
}

function current_user()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $userType = session_user_type();

    return [
        'id'        => $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'] ?? '',
        'email'     => $_SESSION['email'] ?? '',
        'user_type' => $userType,
        'role'      => $userType,
    ];
}

function find_user_by_id(int $user_id): ?array
{
    $conn = connect_db();
    $stmt = $conn->prepare(
        'SELECT id, full_name, email, phone, organization, user_type
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the user lookup.');
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

function register_user($full_name, $email, $password, $phone, $organization, $user_type)
{
    $conn = connect_db();

    try {
        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );
        $check->bind_param("s", $email);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $check->close();

            return [
                'success' => false,
                'message' => 'This email is already registered.'
            ];
        }

        $check->close();

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users
            (full_name, email, password_hash, phone, organization, user_type)
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssssss",
            $full_name,
            $email,
            $password_hash,
            $phone,
            $organization,
            $user_type
        );

        $stmt->execute();
        $stmt->close();

        return [
            'success' => true,
            'message' => 'Registration successful.'
        ];

    } catch (Throwable $e) {
        return [
            'success' => false,
            'message' => 'Unable to register the account. Check your database columns.'
        ];
    }
}
