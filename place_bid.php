<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'utilities.php';
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

    if ($auctionId === '' || !ctype_digit($auctionId)) { $errors[] = 'Invalid auction id.'; }
    if ($amount === '' || !is_numeric($amount) || $amount <= 0) { $errors[] = 'Bid must be a positive number.'; }

    $auctionIdInt = (int)$auctionId;
    $amountFloat  = (float)$amount;
    $auctionRow   = null;
    $auctionTitle = '';

    if (empty($errors)) {
        $sqlAuction = "SELECT id, title, start_price, end_date, status FROM auction WHERE id = ?";
        $stmtA = $conn->prepare($sqlAuction);
        $stmtA->bind_param("i", $auctionIdInt);
        $stmtA->execute();
        $resA = $stmtA->get_result();
        
        if ($resA->num_rows === 0) {
            $errors[] = 'Auction not found.';
        } else {
            $auctionRow = $resA->fetch_assoc();
            $auctionTitle = $auctionRow['title'];
            
            $now = date('Y-m-d H:i:s');
            if ($auctionRow['end_date'] <= $now) {
                $errors[] = 'This auction has already ended.';
            }
            $statusActive = defined('AuctionStatus::ACTIVE') ? AuctionStatus::ACTIVE : 'ACTIVE';
            
            if (!empty($auctionRow['status']) && $auctionRow['status'] !== $statusActive) {
                $errors[] = 'This auction is not active.';
            }
        }
        $stmtA->close();
    }

    if (empty($errors)) {
        $sqlMax = "SELECT MAX(amount) AS max_amount FROM bid WHERE auction_id = ?";
        $stmtMax = $conn->prepare($sqlMax);
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
            $errors[] = 'Your bid must be higher than the current highest bid (£' . number_format($minAllowed, 2) . ').';
        }
    }

    $existingBid = null;
    if (empty($errors)) {
        $sqlMyBid = "SELECT id, amount FROM bid WHERE auction_id = ? AND bidder_id = ? LIMIT 1";
        $stmtMy = $conn->prepare($sqlMyBid);
        $stmtMy->bind_param("ii", $auctionIdInt, $userId);
        $stmtMy->execute();
        $resMy = $stmtMy->get_result();
        $existingBid = $resMy->fetch_assoc();
        $stmtMy->close();
    }

    if (empty($errors)) {
        $now = date('Y-m-d H:i:s');
        $bidSuccess = false;

        if ($existingBid) {
            if ($amountFloat <= (float)$existingBid['amount']) {
                $errors[] = 'Your new bid must be higher than your previous bid (£' . number_format($existingBid['amount'], 2) . ').';
            } else {
                $sqlU = "UPDATE bid SET amount = ?, bid_time = ?, is_anonymous = ? WHERE id = ?";
                $stmtU = $conn->prepare($sqlU);
                $bidId = (int)$existingBid['id'];
                $stmtU->bind_param("dsii", $amountFloat, $now, $bidIsAnonymous, $bidId);
                if ($stmtU->execute()) {
                    $bidSuccess = true;
                } else {
                    $errors[] = 'Database error (update): ' . $stmtU->error;
                }
                $stmtU->close();
            }
        } else {
            $sqlI = "INSERT INTO bid (amount, bidder_id, auction_id, bid_time, is_anonymous) VALUES (?, ?, ?, ?, ?)";
            $stmtI = $conn->prepare($sqlI);
            $stmtI->bind_param("diisi", $amountFloat, $userId, $auctionIdInt, $now, $bidIsAnonymous);
            if ($stmtI->execute()) {
                $bidSuccess = true;
            } else {
                $errors[] = 'Database error (insert): ' . $stmtI->error;
            }
            $stmtI->close();
        }

        if ($bidSuccess) {
            $success = true;
            $emailed_users = [];

            $sqlNotify = "
                SELECT u.email, b.amount
                FROM bid b
                JOIN users u ON b.bidder_id = u.user_id 
                WHERE b.auction_id = ? 
                AND b.bidder_id != ?
            ";
            
            $stmtNotify = $conn->prepare($sqlNotify);
            if ($stmtNotify) {
                $stmtNotify->bind_param("ii", $auctionIdInt, $userId);
                $stmtNotify->execute();
                $resNotify = $stmtNotify->get_result();
                
                $host = $_SERVER['HTTP_HOST']; 
                $link = "http://$host/listing.php?auction_id=$auctionIdInt";                
                $subject = "Outbid Alert: " . $auctionTitle;
                
                while ($user = $resNotify->fetch_assoc()) {
                    $recipientEmail = $user['email'];
                    $theirBid = $user['amount'];
                    $message = "
                        <h3>You have been outbid!</h3>
                        <p>A new highest bid of <strong style='color:#d9534f; font-size:1.2em;'>£" . number_format($amountFloat, 2) . "</strong> has been placed on item:</p>
                        <p style='font-size:1.1em'><strong>" . htmlspecialchars($auctionTitle) . "</strong></p>
                        
                        <div style='background-color:#f8f9fa; padding:10px; border-left: 4px solid #d9534f; margin: 15px 0;'>
                            <p style='margin:0; color:#555;'>Your current bid is: <strong>£" . number_format($theirBid, 2) . "</strong></p>
                        </div>

                        <hr>
                        <p>Don't lose out! <a href='$link' style='background:#007bff;color:#fff;padding:8px 15px;text-decoration:none;border-radius:4px;display:inline-block;'>Place a New Bid</a></p>
                    ";
                    @send_email($recipientEmail, $subject, $message);
                    $emailed_users[] = $recipientEmail;
                }
                $stmtNotify->close();
            }

            // Notify watchers who haven't bid
            $sqlWatch = "
                SELECT DISTINCT u.email 
                FROM watchlist w
                JOIN users u ON w.user_id = u.user_id
                WHERE w.auction_id = ?
                AND w.user_id != ? 
            ";

            $stmtWatch = $conn->prepare($sqlWatch);
            if ($stmtWatch) {
                $stmtWatch->bind_param("ii", $auctionIdInt, $userId);
                $stmtWatch->execute();
                $resWatch = $stmtWatch->get_result();

                $subjectWatch = "Price Update: " . $auctionTitle;

                while ($watcher = $resWatch->fetch_assoc()) {
                    $recipientEmail = $watcher['email'];

                    if (in_array($recipientEmail, $emailed_users)) {
                        continue; 
                    }

                    $message = "
                        <h3>Price Update</h3>
                        <p>An item on your watchlist has a new bid.</p>
                        <p>Item: <strong>" . htmlspecialchars($auctionTitle) . "</strong></p>
                        <p>New Price: <strong style='color:#007bff; font-size:1.2em;'>£" . number_format($amountFloat, 2) . "</strong></p>
                        <hr>
                        <p><a href='$link' style='background:#007bff;color:#fff;padding:8px 15px;text-decoration:none;border-radius:4px;'>View Auction</a></p>
                    ";

                    @send_email($recipientEmail, $subjectWatch, $message);
                    
                    $emailed_users[] = $recipientEmail;
                }
                $stmtWatch->close();
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
    <div class="alert alert-danger shadow-sm">
      <h4 class="alert-heading"><i class="fa fa-exclamation-triangle"></i> Failed to place bid:</h4>
      <ul class="mb-0 mt-2">
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="mt-4">
        <a href="listing.php?item_id=<?= htmlspecialchars($auctionId) ?>" class="btn btn-secondary px-4">Try Again</a>
    </div>

  <?php elseif ($success): ?>
    <div class="card text-center p-5 border-0 shadow-sm bg-light">
      <div class="card-body">
          <h1 class="text-success mb-3"><i class="fa fa-check-circle"></i></h1>
          <h2 class="text-success mb-3">Bid Placed Successfully!</h2>
          <p class="lead text-muted">
              You are now the highest bidder for <br>
              <strong><?= htmlspecialchars($auctionTitle) ?></strong>
          </p>
          <h3 class="font-weight-bold mt-3">£<?= number_format($amountFloat, 2) ?></h3>
          
          <div class="mt-5">
              <a href="listing.php?auction_id=<?= $auctionIdInt ?>" class="btn btn-primary btn-lg mr-2 shadow-sm">Back to Auction</a>
              <a href="mybids.php" class="btn btn-outline-secondary btn-lg shadow-sm">View My Bids</a>
          </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php include_once("footer.php"); ?>