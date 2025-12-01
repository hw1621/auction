<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'utilities.php';

$conn = get_database_connection();

if (empty($_SESSION['logged_in']) || $_SESSION['account_type'] !== 'buyer') {
    $_SESSION['login_error'] = 'You must be logged in as a buyer to edit bids.';
    header('Location: browse.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$errors  = [];
$success = '';
$bid     = null;

$bidId = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bidId = isset($_POST['bid_id']) ? (int)$_POST['bid_id'] : 0;
} else {
    $bidId = isset($_GET['bid_id']) ? (int)$_GET['bid_id'] : 0;
}

if ($bidId <= 0) {
    $errors[] = 'Invalid bid ID.';
} else {
    $sql = "
        SELECT 
            b.id         AS bid_id,
            b.amount     AS bid_amount,
            b.bid_time,
            b.auction_id,
            b.bidder_id,
            a.title      AS auction_title,
            a.end_date,
            a.status
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
                $errors[] = 'This auction is not active. You cannot change your bid.';
            }
        }
    }
}

$maxOtherBid = null;
if ($bid && empty($errors)) {
    $sqlMax = "
        SELECT MAX(amount) AS max_amount
        FROM bid
        WHERE auction_id = ? AND bidder_id <> ?
    ";
    $stmtMax = $conn->prepare($sqlMax);
    if ($stmtMax === false) {
        $errors[] = 'Database error (prepare max): ' . $conn->error;
    } else {
        $stmtMax->bind_param("ii", $bid['auction_id'], $userId);
        $stmtMax->execute();
        $resMax = $stmtMax->get_result();
        $rowMax = $resMax->fetch_assoc();
        $stmtMax->close();

        if ($rowMax && $rowMax['max_amount'] !== null) {
            $maxOtherBid = (float)$rowMax['max_amount'];
        } else {
            $maxOtherBid = null;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bid && empty($errors)) {
    $newAmountRaw = $_POST['new_amount'] ?? '';
    $newAmount    = floatval($newAmountRaw);

    if ($newAmount <= 0) {
        $errors[] = 'New bid amount must be greater than 0.';
    }

    if ($maxOtherBid !== null && $newAmount < $maxOtherBid) {
        $errors[] = 'Your new bid must be at least £' . number_format($maxOtherBid, 2) .
                    ', which is the highest bid from other users.';
    }

    if ($bid['status'] !== AuctionStatus::ACTIVE) {
        $errors[] = 'This auction just became inactive. You cannot change your bid.';
    }

    if (empty($errors)) {
        $now = date('Y-m-d H:i:s');

        $sqlUpdate = "
            UPDATE bid
            SET amount = ?, bid_time = ?
            WHERE id = ? AND bidder_id = ?
        ";
        $stmtU = $conn->prepare($sqlUpdate);
        if ($stmtU === false) {
            $errors[] = 'Database error (prepare update): ' . $conn->error;
        } else {
            $stmtU->bind_param("dsii", $newAmount, $now, $bidId, $userId);
            if ($stmtU->execute()) {
                $success = 'Your bid has been updated successfully.';
                $bid['bid_amount'] = $newAmount;
                $bid['bid_time']   = $now;
            } else {
                $errors[] = 'Failed to update bid: ' . $stmtU->error;
            }
            $stmtU->close();
        }
    }
}
?>

<?php include_once("header.php"); ?>

<div class="container">
  <h2>Edit your bid</h2>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <?= htmlspecialchars($success) ?>
      <br>
      <a href="mybids.php" class="btn btn-sm btn-secondary mt-2">Back to My Bids</a>
    </div>
  <?php endif; ?>

  <?php if ($bid && empty($success)): ?>
    <div class="card mt-3">
      <div class="card-body">
        <h5 class="card-title">
          Auction: <?= htmlspecialchars($bid['auction_title']) ?>
        </h5>
        <p>
          Current bid: <strong>£<?= htmlspecialchars(number_format($bid['bid_amount'], 2)) ?></strong><br>
          Your bid time: <?= htmlspecialchars($bid['bid_time']) ?><br>
          Auction ends: <?= htmlspecialchars($bid['end_date']) ?>
        </p>

        <?php if ($maxOtherBid !== null): ?>
          <p>
            Highest bid from other users:
            <strong>£<?= htmlspecialchars(number_format($maxOtherBid, 2)) ?></strong>
          </p>
          <p class="text-muted">
            Your new bid must be <strong>not lower than</strong> this value.
          </p>
        <?php else: ?>
          <p class="text-muted">
            Currently, you are the only bidder. You can change your bid to any positive amount.
          </p>
        <?php endif; ?>

        <form method="post" action="edit_bid.php">
          <input type="hidden" name="bid_id" value="<?= (int)$bid['bid_id'] ?>">
          <div class="mb-3">
            <label for="new_amount" class="form-label">New bid amount (£)</label>
            <input
              type="number"
              step="0.01"
              min="0"
              class="form-control"
              id="new_amount"
              name="new_amount"
              value="<?= htmlspecialchars($bid['bid_amount']) ?>"
              required
            >
          </div>
          <button type="submit" class="btn btn-primary">Update bid</button>
          <a href="mybids.php" class="btn btn-secondary">Cancel</a>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include_once("footer.php"); ?>
