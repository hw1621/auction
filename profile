<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['login_error'] = 'Please log in to view your profile.';
    header('Location: browse.php');
    exit;
}

require_once 'config.php';
$conn = get_database_connection();

$userId  = (int)$_SESSION['user_id'];
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['profile_update'])) {
        $newUsername = trim($_POST['username'] ?? '');
        $newEmail    = trim($_POST['email'] ?? '');

        if ($newUsername === '') {
            $errors[] = 'Username cannot be empty.';
        }

        if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            if ($stmt) {
                $stmt->bind_param("si", $newEmail, $userId);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $errors[] = 'This email is already in use by another account.';
                } else {
                    $stmt->close();
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("ssi", $newUsername, $newEmail, $userId);
                        if ($stmt->execute()) {
                            $_SESSION['username'] = $newUsername;
                            $success = 'Profile updated successfully.';
                        } else {
                            $errors[] = 'Database error while updating profile.';
                        }
                    } else {
                        $errors[] = 'Database error while preparing update.';
                    }
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error.';
            }
        }
    }

    if (isset($_POST['password_change'])) {
        $oldPassword     = $_POST['old_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $errors[] = 'All password fields are required.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        } else {
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();

                if (!$row || !password_verify($oldPassword, $row['password_hash'])) {
                    $errors[] = 'Old password is incorrect.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("si", $newHash, $userId);
                        if ($stmt->execute()) {
                            $success = 'Password changed successfully.';
                        } else {
                            $errors[] = 'Database error while updating password.';
                        }
                        $stmt->close();
                    } else {
                        $errors[] = 'Database error while preparing password update.';
                    }
                }
            } else {
                $errors[] = 'Database error.';
            }
        }
    }
}

$stmt = $conn->prepare("SELECT username, email, role, created_at FROM users WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
} else {
    $user = false;
}

if (!$user) {
    $user = [
        'username'   => $_SESSION['username'] ?? 'Unknown',
        'email'      => '',
        'role'       => $_SESSION['account_type'] ?? '',
        'created_at' => ''
    ];
}

include_once 'header.php';
?>

<div class="container my-5" style="max-width: 700px;">
    <h2 class="mb-4">My Account</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
      <div class="card-header">Basic Information</div>
      <div class="card-body">
        <form method="post" action="profile.php">
          <div class="form-group">
            <label>Username</label>
            <input
              type="text"
              name="username"
              class="form-control"
              value="<?= htmlspecialchars($user['username']) ?>"
              required
            >
          </div>
          <div class="form-group mt-3">
            <label>Email</label>
            <input
              type="email"
              name="email"
              class="form-control"
              value="<?= htmlspecialchars($user['email']) ?>"
              required
            >
          </div>
          <div class="form-group mt-3">
            <label>Role</label>
            <input
              type="text"
              class="form-control"
              value="<?= htmlspecialchars($user['role']) ?>"
              disabled
            >
          </div>
          <div class="form-group mt-3">
            <label>Member since</label>
            <input
              type="text"
              class="form-control"
              value="<?= htmlspecialchars($user['created_at']) ?>"
              disabled
            >
          </div>

          <button
            type="submit"
            name="profile_update"
            class="btn btn-primary mt-4"
          >
            Save changes
          </button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Change Password</div>
      <div class="card-body">
        <form method="post" action="profile.php">
          <div class="form-group">
            <label>Old password</label>
            <input
              type="password"
              name="old_password"
              class="form-control"
              required
            >
          </div>
          <div class="form-group mt-3">
            <label>New password</label>
            <input
              type="password"
              name="new_password"
              class="form-control"
              required
            >
          </div>
          <div class="form-group mt-3">
            <label>Confirm new password</label>
            <input
              type="password"
              name="confirm_password"
              class="form-control"
              required
            >
          </div>

          <button
            type="submit"
            name="password_change"
            class="btn btn-warning mt-4"
          >
            Change password
          </button>
        </form>
      </div>
    </div>
</div>

<?php include_once 'footer.php'; ?>
