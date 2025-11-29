<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
$conn = get_database_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $_SESSION['login_error'] = 'Email and password are required.';
        unset($_SESSION['login_success']);
        header('Location: browse.php');
        exit;
    }

    $stmt = $conn->prepare("
        SELECT user_id, username, email, password_hash, role, status
        FROM users
        WHERE email = ?
    ");
    if ($stmt === false) {
        $_SESSION['login_error'] = 'Database error.';
        unset($_SESSION['login_success']);
        header('Location: browse.php');
        exit;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password_hash'])) {

        if (isset($user['status']) && $user['status'] === 'banned') {
            $_SESSION['login_error'] = 'Your account has been banned. Please contact support.';
            unset($_SESSION['login_success']);
            header('Location: browse.php');
            exit;
        }

        $_SESSION['logged_in']    = true;
        $_SESSION['user_id']      = $user['user_id'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['account_type'] = strtolower($user['role']);
        $_SESSION['user_status']  = $user['status'];

        $_SESSION['login_success'] = 'Welcome back, ' . $user['username'] . '!';
        unset($_SESSION['login_error']);

        header('Location: browse.php');
        exit;
    } else {
        $_SESSION['login_error'] = 'Incorrect email or password.';
        unset($_SESSION['login_success']);
        header('Location: browse.php');
        exit;
    }
} else {
    header('Location: browse.php');
    exit;
}
