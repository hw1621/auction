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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bid'])) {
    $bidId = (int)($_POST['bid_id'] ?? 0);

    if ($bidId <= 0) {
        $errors[] = "Invalid bid ID.";
    } else {
        $stmt = $conn->prepare("DELETE FROM bid WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $bidId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $success = "Bid #{$bidId} deleted.";
                } else {
                    $errors[] = "Bid not found or already deleted.";
                }
            } else {
                $errors[] = "Database error deleting bid: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Database prepare() error: " . $conn->error;
        }
    }
}

$filterAuctionId = isset($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;

$bids = [];

if ($filterAuctionId > 0) {
    $stmt = $conn->prepare("
        SELECT 
            b.id,
            b.amount,
            b.bid_time,
            b.auction_id,
            b.bidder_id,
            a.title       AS auction_title,
            u.username    AS bidder_name,
            u.email       AS bidder_email
        FROM bid b
        JOIN auction a ON b.auction_id = a.id
        JOIN users   u ON b.bidder_id  = u.user_id
        WHERE b.auction_id = ?
        ORDER BY b.bid_time DESC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $filterAuctionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $bids   = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $errors[] = "Database prepare() error (filtered): " . $conn->error;
    }
} else {
    $sql = "
        SELECT 
            b.id,
            b.amount,
            b.bid_time,
            b.auction_id,
            b.bidder_id,
            a.title       AS auction_title,
            u.username    AS bidder_name,
            u.email       AS bidder_email
        FROM bid b
        JOIN auction a ON b.auction_id = a.id
        JOIN users   u ON b.bidder_id  = u.user_id
        ORDER BY b.bid_time DESC
    ";
    $result = $conn->query($sql);
    if ($result) {
        $bids = $result->fetch_all(MYSQLI_ASSOC);
        $result->close();
    } else {
        $errors[] = "Database error loading bids: " . $conn->error;
    }
}

include_once 'header.php';
?>

<div class="container my-5">
  <h2 class="mb-4">Admin: Bids Management</h2>

  <form class="form-inline mb-3" method="get" action="admin_bids.php">
    <div class="form-group mr-2">
      <label class="mr-1" for="auction_id">Filter by auction ID:</label>
      <input
        type="number"
        id="auction_id"
        name="auction_id"
        class="form-control form-control-sm"
        value="<?= $filterAuctionId > 0 ? (int)$filterAuctionId : '' ?>"
        min="1"
      >
    </div>
    <button type="submit" class="btn btn-sm btn-primary mr-2">Apply</button>
    <a href="admin_bids.php" class="btn btn-sm btn-secondary">Clear</a>
  </form>

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

  <?php if (!$bids): ?>
    <p>No bids found<?= $filterAuctionId > 0 ? " for auction #".(int)$filterAuctionId : "" ?>.</p>
  <?php else: ?>
    <table class="table table-striped table-bordered mt-3">
      <thead class="thead-light">
        <tr>
          <th>Bid ID</th>
          <th>Auction</th>
          <th>Bidder</th>
          <th>Amount</th>
          <th>Bid time</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($bids as $b): ?>
        <tr>
          <td><?= (int)$b['id'] ?></td>
          <td>
            #<?= (int)$b['auction_id'] ?><br>
            <small><?= htmlspecialchars($b['auction_title']) ?></small>
          </td>
          <td>
            <?= htmlspecialchars($b['bidder_name']) ?><br>
            <small class="text-muted"><?= htmlspecialchars($b['bidder_email']) ?></small>
          </td>
          <td>£<?= htmlspecialchars($b['amount']) ?></td>
          <td><?= htmlspecialchars($b['bid_time']) ?></td>
          <td>
            <form method="post" action="admin_bids.php<?= $filterAuctionId>0 ? '?auction_id='.(int)$filterAuctionId : '' ?>"
                  onsubmit="return confirm('Delete bid #<?= (int)$b['id'] ?> ?');"
                  style="display:inline-block;">
              <input type="hidden" name="bid_id" value="<?= (int)$b['id'] ?>">
              <button type="submit" name="delete_bid" class="btn btn-sm btn-danger">
                Delete
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include_once 'footer.php'; ?>
