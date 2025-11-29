<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
$conn = get_database_connection();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountType          = $_POST['accountType'] ?? '';
    $email                = trim($_POST['email'] ?? '');
    $password             = $_POST['password'] ?? '';
    $passwordConfirmation = $_POST['passwordConfirmation'] ?? '';

    if ($accountType === '' || $email === '' || $password === '' || $passwordConfirmation === '') {
        $errors[] = 'All fields are required.';
    }

    if (!in_array($accountType, ['buyer', 'seller'], true)) {
        $errors[] = 'Invalid account type.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $errors[] = 'This email is already registered.';
            }
            $stmt->close();
        } else {
            $errors[] = 'Database error.';
        }
    }

    if (empty($errors)) {
        $username     = $email;
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $insert = $conn->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
        if ($insert) {
            $insert->bind_param("ssss", $username, $email, $passwordHash, $accountType);
            if ($insert->execute()) {
                $success = 'Registration successful. You can now log in.';
            } else {
                $errors[] = 'Database error while creating account.';
            }
            $insert->close();
        } else {
            $errors[] = 'Database error while preparing insert.';
        }
    }
} else {
    $errors[] = 'Invalid request method.';
}

include_once("header.php");
?>

<div class="container my-5" style="max-width: 600px;">
    <h2 class="mb-4">Registration result</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <h4 class="alert-heading">There was a problem creating your account:</h4>
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <a href="register.php" class="btn btn-secondary mt-3">Back to registration</a>
    <?php elseif ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
        <a href="browse.php" class="btn btn-primary mt-3">Go to browse page</a>
    <?php endif; ?>
</div>

<?php include_once("footer.php"); ?>
