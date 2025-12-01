<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'utilities.php';

$conn = get_database_connection();

$errors  = [];
$success = '';

if (empty($_SESSION['logged_in']) || $_SESSION['account_type'] !== 'buyer') {
    $_SESSION['login_error'] = 'You must be logged in as a buyer to cancel bids.';
    header('Location: browse.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$bidId = isset($_GET['bid_id']) ? (int)$_GET['bid_id'] : 0;

if ($bidId <= 0) {
    $errors[] = 'Invalid bid ID.';
} else {
    $sql = "
        SELECT 
            b.id         AS bid_id,
            b.amount     AS bid_amount,
            b.auction_id,
            b.bidder_id,
            a.title      AS auction_title,
            a.status,
            a.end_date
        FROM bid b
        JOIN auction a ON b.auction_id = a.id
        WHERE b.id = ? AND b.bidder_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $errors[] = 'Database error (prepare): ' . $conn->error;
    } else {
        $stmt->bind_param("ii", $bidId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $bid = $result->fetch_assoc();
        $stmt->close();

        if (!$bid) {
            $errors[] = 'Bid not found or you are not the owner of this bid.';
        } else {
            if ($bid['status'] !== AuctionStatus::ACTIVE) {
                $errors[] = 'This auction is not active. You cannot cancel your bid.';
            }
        }
    }

    if (empty($errors)) {
        $sqlDel = "DELETE FROM bid WHERE id = ? AND bidder_id = ?";
        $stmtDel = $conn->prepare($sqlDel);
        if ($stmtDel === false) {
            $errors[] = 'Database error (prepare delete): ' . $conn->error;
        } else {
            $stmtDel->bind_param("ii", $bidId, $userId);
            if ($stmtDel->execute()) {
                $success = 'Your bid has been cancelled successfully.';
            } else {
                $errors[] = 'Failed to cancel bid: ' . $stmtDel->error;
            }
            $stmtDel->close();
        }
    }
}
?>

<?php include_once("header.php"); ?>

<div class="container">
  <h2>Cancel bid</h2>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
      <a href="mybids.php" class="btn btn-secondary mt-2">Back to My Bids</a>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <?= htmlspecialchars($success) ?>
      <br>
      <a href="mybids.php" class="btn btn-primary mt-2">Back to My Bids</a>
    </div>
  <?php endif; ?>
</div>

<?php include_once("footer.php"); ?>
