<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
$conn = get_database_connection();

if (empty($_SESSION['logged_in']) || $_SESSION['account_type'] !== 'buyer') {
    $_SESSION['login_error'] = 'You must be logged in as a buyer to place bids.';
    header('Location: browse.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $auctionId = $_POST['auction_id'] ?? '';
    $amount    = $_POST['amount'] ?? '';
    $bidIsAnonymous = isset($_POST['bid_is_anonymous']) ? 1 : 0;

    if ($auctionId === '' || !ctype_digit($auctionId)) {
        $errors[] = 'Invalid auction id.';
    }
    if ($amount === '' || !is_numeric($amount) || $amount <= 0) {
        $errors[] = 'Bid must be a positive number.';
    }

    $auctionIdInt = (int)$auctionId;
    $amountFloat  = (float)$amount;
    $auctionRow   = null;

    if (empty($errors)) {
        $sqlAuction = "
            SELECT id, start_price, end_date, status
            FROM auction
            WHERE id = ?
        ";
        $stmtA = $conn->prepare($sqlAuction);
        if ($stmtA === false) {
            $errors[] = 'Database error (prepare auction): ' . $conn->error;
        } else {
            $stmtA->bind_param("i", $auctionIdInt);
            $stmtA->execute();
            $resA = $stmtA->get_result();

            if ($resA->num_rows === 0) {
                $errors[] = 'Auction not found.';
            } else {
                $auctionRow = $resA->fetch_assoc();
                $now = date('Y-m-d H:i:s');

                if ($auctionRow['end_date'] <= $now) {
                    $errors[] = 'This auction has already ended.';
                }
                if (!empty($auctionRow['status']) && $auctionRow['status'] !== AuctionStatus::ACTIVE) {
                    $errors[] = 'This auction is not active.';
                }
            }
            $stmtA->close();
        }
    }

    if (empty($errors)) {
        $sqlMax = "
            SELECT MAX(amount) AS max_amount
            FROM bid
            WHERE auction_id = ?
        ";
        $stmtMax = $conn->prepare($sqlMax);
        if ($stmtMax === false) {
            $errors[] = 'Database error (prepare max bid): ' . $conn->error;
        } else {
            $stmtMax->bind_param("i", $auctionIdInt);
            $stmtMax->execute();
            $resMax = $stmtMax->get_result();
            $rowMax = $resMax->fetch_assoc();
            $stmtMax->close();

            $maxAmount = $rowMax['max_amount'];
            $minAllowed = (float)$auctionRow['start_price'];

            if ($maxAmount !== null) {
                $minAllowed = max($minAllowed, (float)$maxAmount);
            }

            if ($amountFloat <= $minAllowed) {
                $errors[] = 'Your bid must be higher than the current highest bid (£' . $minAllowed . ').';
            }
        }
    }

    $existingBid = null;
    if (empty($errors)) {
        $sqlMyBid = "
            SELECT id, amount
            FROM bid
            WHERE auction_id = ? AND bidder_id = ?
            ORDER BY amount DESC
            LIMIT 1
        ";
        $stmtMy = $conn->prepare($sqlMyBid);
        if ($stmtMy === false) {
            $errors[] = 'Database error (prepare my bid): ' . $conn->error;
        } else {
            $stmtMy->bind_param("ii", $auctionIdInt, $userId);
            $stmtMy->execute();
            $resMy = $stmtMy->get_result();
            $existingBid = $resMy->fetch_assoc();
            $stmtMy->close();
        }
    }

    if (empty($errors)) {
        $now = date('Y-m-d H:i:s');

        if ($existingBid) {
            if ($amountFloat <= (float)$existingBid['amount']) {
                $errors[] = 'Your new bid must be higher than your previous bid (£' . $existingBid['amount'] . ').';
            } else {
                $sqlU = "
                    UPDATE bid
                    SET amount = ?, bid_time = ?, is_anonymous = ?
                    WHERE id = ?
                ";
                $stmtU = $conn->prepare($sqlU);
                if ($stmtU === false) {
                    $errors[] = 'Database error (prepare update bid): ' . $conn->error;
                } else {
                    $bidId = (int)$existingBid['id'];
                $stmtU->bind_param("dsii", $amountFloat, $now, $bidIsAnonymous, $bidId);
                    if ($stmtU->execute()) {
                        $success = true;
                    } else {
                        $errors[] = 'Database error (execute update bid): ' . $stmtU->error;
                    }
                    $stmtU->close();
                }
            }
        } else {
            $sqlI = "
                INSERT INTO bid (amount, bidder_id, auction_id, bid_time, is_anonymous)
                VALUES (?, ?, ?, ?, ?)
            ";
            $stmtI = $conn->prepare($sqlI);
            if ($stmtI === false) {
                $errors[] = 'Database error (prepare insert bid): ' . $conn->error;
            } else {
            $stmtI->bind_param("diisi", $amountFloat, $userId, $auctionIdInt, $now, $bidIsAnonymous);
                if ($stmtI->execute()) {
                    $success = true;
                } else {
                    $errors[] = 'Database error (execute insert bid): ' . $stmtI->error;
                }
                $stmtI->close();
            }
        }
    }

} else {
    $errors[] = 'Invalid request method.';
}
?>

<?php include_once("header.php"); ?>

<div class="container my-5">
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <h4 class="alert-heading">Failed to place bid:</h4>
      <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <a href="mybids.php" class="btn btn-secondary">Back to My Bids</a>

  <?php elseif ($success): ?>
    <div class="alert alert-success">
      <h4 class="alert-heading">Your bid has been recorded!</h4>
      <a href="mybids.php" class="btn btn-primary">View My Bids</a>
    </div>
  <?php endif; ?>
</div>

<?php include_once("footer.php"); ?>
