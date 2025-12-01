<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'utilities.php';

$conn = get_database_connection();

$errors  = [];
$success = '';

if (empty($_SESSION['user_id'])) {
    $_SESSION['login_error'] = 'Please log in as a seller.';
    header('Location: browse.php');
    exit;
}

if (($_SESSION['account_type'] ?? '') !== 'seller') {
    $_SESSION['login_error'] = 'Only sellers can cancel their listings.';
    header('Location: browse.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$auctionId = isset($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;

if ($auctionId <= 0) {
    $errors[] = 'Invalid auction ID.';
}

if (empty($errors)) {

    $sql = "
        SELECT 
            a.id,
            a.status,
            a.title,
            a.end_date,
            i.seller_id
        FROM auction a
        JOIN item i ON i.id = a.item_id
        WHERE a.id = ? AND i.seller_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $errors[] = 'Database error: ' . $conn->error;
    } else {
        $stmt->bind_param("ii", $auctionId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $auction = $res->fetch_assoc();
        $stmt->close();

        if (!$auction) {
            $errors[] = 'Listing not found or you are not the owner.';
        } else {
            if ($auction['status'] !== 'active') {
                $errors[] = 'This listing is not active and cannot be cancelled.';
            }
        }
    }
}

if (empty($errors) && $auction) {

    $now = date('Y-m-d H:i:s');

    $sqlUpdate = "
        UPDATE auction
        SET status = 'cancelled', end_date = ?
        WHERE id = ?
    ";

    $stmtU = $conn->prepare($sqlUpdate);
    if (!$stmtU) {
        $errors[] = 'Database error: ' . $conn->error;
    } else {
        $stmtU->bind_param("si", $now, $auctionId);

        if ($stmtU->execute()) {
            $success = 'Listing has been cancelled successfully.';
        } else {
            $errors[] = 'Failed to cancel listing: ' . $stmtU->error;
        }

        $stmtU->close();
    }
}

include_once("header.php");
?>

<div class="container my-4">
  <h2>Cancel listing</h2>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
      <a href="mylistings.php" class="btn btn-secondary mt-2">Back to My Listings</a>
    </div>
  <?php else: ?>
    <div class="alert alert-success">
      <?= htmlspecialchars($success) ?><br>
      <a href="mylistings.php" class="btn btn-primary mt-2">Back to My Listings</a>
    </div>
  <?php endif; ?>
</div>

<?php include_once("footer.php"); ?>
