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