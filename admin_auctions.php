<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    empty($_SESSION['account_type']) || $_SESSION['account_type'] !== 'admin') {
    $_SESSION['login_error'] = 'Admin access only.';
    header('Location: browse.php');
    exit;
}

require_once 'config.php';
$conn = get_database_connection();

$errors  = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['force_finish'])) {
    $auctionId = (int)($_POST['auction_id'] ?? 0);

    if ($auctionId <= 0) {
        $errors[] = "Invalid auction ID for force finish.";
    } else {
        $stmt = $conn->prepare("SELECT status FROM auction WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $auctionId);
            $stmt->execute();
            $result = $stmt->get_result();
            $auctionRow = $result->fetch_assoc();
            $stmt->close();

            if ($auctionRow && $auctionRow['status'] === AuctionStatus::ACTIVE) {
                $stmt = $conn->prepare("
                    SELECT bidder_id, amount
                    FROM bid
                    WHERE auction_id = ?
                    ORDER BY amount DESC, id DESC
                    LIMIT 1
                ");
                if ($stmt) {
                    $stmt->bind_param("i", $auctionId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $topBid = $result->fetch_assoc();
                    $stmt->close();

                    if ($topBid) {
                        $winnerId   = (int)$topBid['bidder_id'];
                        $finalPrice = (float)$topBid['amount'];
                        $statusSold = AuctionStatus::SOLD;

                        $stmt = $conn->prepare("
                            UPDATE auction
                            SET status = ?, winner_id = ?, final_price = ?, end_date = NOW()
                            WHERE id = ?
                        ");
                        if ($stmt) {
                            $stmt->bind_param("sidi", $statusSold, $winnerId, $finalPrice, $auctionId);
                            if ($stmt->execute()) {
                                $success = "Auction #{$auctionId} force-finished as SOLD.";
                            }
                            $stmt->close();
                        }
                    } else {
                        $statusUnsold = AuctionStatus::UNSOLD;

                        $stmt = $conn->prepare("
                            UPDATE auction
                            SET status = ?, winner_id = NULL, final_price = NULL, end_date = NOW()
                            WHERE id = ?
                        ");
                        if ($stmt) {
                            $stmt->bind_param("si", $statusUnsold, $auctionId);
                            if ($stmt->execute()) {
                                $success = "Auction #{$auctionId} force-finished as UNSOLD.";
                            }
                            $stmt->close();
                        }
                    }
                }
            } else {
                $errors[] = "Auction cannot be force-finished.";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_auction'])) {
    $auctionId = (int)($_POST['auction_id'] ?? 0);

    if ($auctionId <= 0) {
        $errors[] = 'Invalid auction ID.';
    } else {
        $statusCancelled = AuctionStatus::CANCELLED;
        $statusActive    = AuctionStatus::ACTIVE;

        $stmt = $conn->prepare("
            UPDATE auction
            SET status = ?
            WHERE id = ? AND status = ?
        ");
        if ($stmt) {
            $stmt->bind_param("sis", $statusCancelled, $auctionId, $statusActive);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $success = "Auction #{$auctionId} has been cancelled.";
                } else {
                    $errors[] = "Only active auctions can be cancelled.";
                }
            } else {
                $errors[] = "Database error cancelling auction: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Database prepare() error (cancel auction): " . $conn->error;
        }
    }
}

$auctions = [];

$query = "
    SELECT 
        a.id,
        a.title,
        a.start_price,
        a.reserve_price,
        a.end_date,
        a.status,
        a.final_price,
        a.winner_id,
        u.username AS seller_name,
        u.email    AS seller_email
    FROM auction a
    JOIN item  i ON a.item_id   = i.id
    JOIN users u ON i.seller_id = u.user_id
    ORDER BY a.id DESC
";

$result = $conn->query($query);

if ($result) {
    $auctions = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
} else {
    $errors[] = "Database error loading auctions: " . $conn->error;
}

include_once 'header.php';
?>

<div class="container my-5">
  <h2 class="mb-4">Admin: Manage Auctions</h2>

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

  <?php if (!$auctions): ?>
    <p>No auctions found.</p>
  <?php else: ?>
    <table class="table table-striped table-bordered mt-3">
      <thead class="thead-light">
        <tr>
          <th>ID</th>
          <th>Title</th>
          <th>Seller</th>
          <th>Start price</th>
          <th>Reserve price</th>
          <th>End date</th>
          <th>Status</th>
          <th>Final price</th>
          <th>Winner ID</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($auctions as $a): ?>
        <tr>
          <td><?= (int)$a['id'] ?></td>
          <td><?= htmlspecialchars($a['title']) ?></td>
          <td>
            <?= htmlspecialchars($a['seller_name']) ?><br>
            <small class="text-muted"><?= htmlspecialchars($a['seller_email']) ?></small>
          </td>
          <td>£<?= htmlspecialchars($a['start_price']) ?></td>
          <td>
            <?= $a['reserve_price'] === null ? '—' : '£' . htmlspecialchars($a['reserve_price']) ?>
          </td>
          <td><?= htmlspecialchars($a['end_date']) ?></td>
          <td><?= htmlspecialchars($a['status']) ?></td>
          <td>
            <?= $a['final_price'] === null ? '—' : '£' . htmlspecialchars($a['final_price']) ?>
          </td>
          <td>
            <?= $a['winner_id'] === null ? '—' : (int)$a['winner_id'] ?>
          </td>
          <td>
            <?php if ($a['status'] === AuctionStatus::ACTIVE): ?>
              <form method="post" action="admin_auctions.php" style="display:inline-block;">
                <input type="hidden" name="auction_id" value="<?= (int)$a['id'] ?>">
                <button type="submit" name="force_finish" class="btn btn-sm btn-warning">
                  Force finish
                </button>
              </form>
              <form method="post" action="admin_auctions.php" style="display:inline-block;">
                <input type="hidden" name="auction_id" value="<?= (int)$a['id'] ?>">
                <button type="submit" name="delete_auction" class="btn btn-sm btn-danger">
                  Cancel
                </button>
              </form>
            <?php else: ?>
              <span class="text-muted">No actions</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include_once 'footer.php'; ?>
