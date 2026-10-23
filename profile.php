<?php

session_start();

require_once 'config.php';
require_once 'functions.php';

require_login();

$email = trim((string) ($_SESSION['email'] ?? ''));
if ($email === '') {
    header('Location: login.html');
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('Database connection failed.');
}

$stmt = $conn->prepare(
    'SELECT id, full_name, email, phone, organization, user_type
     FROM users
     WHERE email = ?
     LIMIT 1'
);

if (!$stmt) {
    die('Unable to load your profile.');
}

$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $stmt = $conn->prepare(
        'SELECT id, full_name, email, phone, organization
         FROM borrowers
         WHERE email = ?
         LIMIT 1'
    );

    if (!$stmt) {
        die('Unable to load your profile.');
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$conn->close();

if (!$user) {
    die('User account not found. Please verify your login session and account email.');
}

$fullName = trim((string) ($user['full_name'] ?? ''));
$accountType = strtolower(trim((string) ($user['user_type'] ?? session_user_type())));
$accountTypeLabel = in_array($accountType, ['admin', 'admin user'], true)
    ? 'Admin User'
    : (in_array($accountType, ['client', 'client user'], true) ? 'Client User' : 'Account');
$initial = $fullName !== '' ? strtoupper(substr($fullName, 0, 1)) : '?';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - BookShare</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f4f7fb;
            color: #20242b;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 22px max(24px, calc((100% - 980px) / 2));
            background: #2563eb;
            color: #fff;
        }

        .header h1 {
            margin: 0;
            font-size: 25px;
        }

        .back-link {
            padding: 10px 15px;
            border-radius: 6px;
            background: #fff;
            color: #1d4ed8;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            white-space: nowrap;
        }

        .back-link:hover {
            background: #eff6ff;
        }

        main {
            width: min(100% - 32px, 760px);
            margin: 38px auto;
        }

        .profile-card {
            overflow: hidden;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.08);
        }

        .profile-summary {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 28px;
            border-bottom: 1px solid #e9edf3;
        }

        .avatar {
            display: grid;
            width: 68px;
            height: 68px;
            flex: 0 0 68px;
            place-items: center;
            border-radius: 50%;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 27px;
            font-weight: bold;
        }

        .profile-summary h2 {
            margin: 0 0 6px;
            font-size: 22px;
        }

        .profile-summary p {
            margin: 0;
            color: #697586;
            font-size: 14px;
        }

        .details {
            padding: 8px 28px;
        }

        .detail-row {
            display: grid;
            grid-template-columns: minmax(130px, 1fr) 2fr;
            gap: 16px;
            padding: 17px 0;
            border-bottom: 1px solid #eef1f5;
        }

        .detail-row:last-child {
            border-bottom: 0;
        }

        .label {
            color: #697586;
            font-size: 14px;
        }

        .value {
            overflow-wrap: anywhere;
            font-size: 14px;
            font-weight: bold;
        }

        @media (max-width: 520px) {
            .header {
                padding: 18px 16px;
            }

            .header h1 {
                font-size: 21px;
            }

            main {
                margin-top: 22px;
            }

            .profile-summary {
                padding: 22px 18px;
            }

            .details {
                padding: 4px 18px;
            }

            .detail-row {
                grid-template-columns: 1fr;
                gap: 6px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>My Profile</h1>
        <a class="back-link" href="index.php">Back to Dashboard</a>
    </header>

    <main>
        <section class="profile-card" aria-labelledby="profile-name">
            <div class="profile-summary">
                <div class="avatar" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></div>
                <div>
                    <h2 id="profile-name"><?php echo htmlspecialchars($fullName !== '' ? $fullName : 'BookShare User', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p><?php echo htmlspecialchars($accountTypeLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>

            <div class="details">
                <div class="detail-row">
                    <span class="label">Full Name</span>
                    <span class="value"><?php echo htmlspecialchars($fullName !== '' ? $fullName : 'Not provided', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Email</span>
                    <span class="value"><?php echo htmlspecialchars((string) ($user['email'] ?? $email), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Phone</span>
                    <span class="value"><?php echo htmlspecialchars(trim((string) ($user['phone'] ?? '')) ?: 'Not provided', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Organization</span>
                    <span class="value"><?php echo htmlspecialchars(trim((string) ($user['organization'] ?? '')) ?: 'Not provided', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Account Type</span>
                    <span class="value"><?php echo htmlspecialchars($accountTypeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
