<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'utilities.php';

$conn = get_database_connection();

if (empty($_SESSION['logged_in'])) {
    $_SESSION['login_error'] = 'Please log in to view your watchlist.';
    header('Location: browse.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_auction_id'])) {
    $removeId = (int)$_POST['remove_auction_id'];
    
    $deleteSql = "DELETE FROM watchlist WHERE user_id = ? AND auction_id = ?";
    $stmtDelete = $conn->prepare($deleteSql);
    
    if ($stmtDelete) {
        $stmtDelete->bind_param("ii", $userId, $removeId);
        if ($stmtDelete->execute()) {
            header("Location: mywatchlist.php");
            exit;
        } else {
            $message = '<div class="alert alert-danger">Error removing item.</div>';
        }
        $stmtDelete->close();
    }
}

$sql = "
    SELECT 
        w.id AS watchlist_id,
        w.created_at AS date_added,
        a.id AS auction_id,
        a.end_date,
        a.status,
        a.winner_id,
        a.start_price,
        i.title AS item_title,
        (SELECT MAX(amount) FROM bid WHERE auction_id = a.id) AS current_bid
    FROM watchlist w
    JOIN auction a ON w.auction_id = a.id
    JOIN item i    ON a.item_id    = i.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Database error (prepare mywatchlist): ' . $conn->error);
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

include_once("header.php");
?>

<div class="container my-4">
  <h2 class="my-3">My Watchlist</h2>
  
  <?= $message ?>

  <?php if ($result->num_rows === 0): ?>
    <div class="alert alert-light border text-center p-5">
        <h4>Your watchlist is empty.</h4>
        <p class="text-muted">Keep track of auctions you are interested in by adding them to your watchlist.</p>
        <a href="browse.php" class="btn btn-primary mt-3">Browse Auctions</a>
    </div>
  <?php else: ?>
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th scope="col">Item</th>
          <th scope="col">Current / Start Price</th>
          <th scope="col">Date Added</th>
          <th scope="col">Auction Ends</th>
          <th scope="col">Status</th>
          <th scope="col" style="width: 150px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
          $title = $row['item_title'];
          $auction_id = $row['auction_id'];
          $auction_status = $row['status'];
          
          $current_bid = $row['current_bid'];
          $start_price = $row['start_price'];
          $display_price = $current_bid ? $current_bid : $start_price;
          $price_label = $current_bid ? "Current Bid" : "Start Price";
          
          $displayStatus = '';
          $badgeClass = '';
          $statusUpper = strtoupper($auction_status);

          if ($statusUpper === 'ACTIVE') {
              $displayStatus = 'Active';
              $badgeClass = 'badge badge-primary'; 
          } 
          elseif ($statusUpper === 'SOLD') {
              $displayStatus = 'Sold';
              $badgeClass = 'badge badge-success';
          } 
          elseif ($statusUpper === 'UNSOLD') {
              $displayStatus = 'Unsold';
              $badgeClass = 'badge badge-secondary';
          } 
          else {
              $displayStatus = $auction_status;
              $badgeClass = 'badge badge-dark';
          }
          ?>
          
          <tr>
            <td class="align-middle">
              <a href="listing.php?item_id=<?= $auction_id ?>" class="font-weight-bold text-dark" style="text-decoration:none;">
                  <?= htmlspecialchars($title) ?>
              </a>
            </td>

            <td class="align-middle">
                <span class="font-weight-bold">£<?= htmlspecialchars(number_format($display_price, 2)) ?></span>
                <br>
                <small class="text-muted"><?= $price_label ?></small>
            </td>

            <td class="align-middle"><?= date('j M Y, g:i a', strtotime($row['date_added'])) ?></td>
            
            <td class="align-middle">
                <?= htmlspecialchars($row['end_date']) ?>
                <?php if ($statusUpper !== 'ACTIVE'): ?>
                    <br><span class="text-muted small">(Ended)</span>
                <?php endif; ?>
            </td>
            
            <td class="align-middle">
                <span class="<?= $badgeClass ?>" style="font-size: 0.85rem; padding: 6px 10px;">
                    <?= $displayStatus ?>
                </span>
            </td>
            
            <td class="align-middle">
                <div class="d-flex align-items-center">
                    <a href="listing.php?auction_id=<?= $auction_id ?>" class="btn btn-sm btn-outline-primary mr-2">View</a>
                    
                    <form method="POST" action="mywatchlist.php" style="display:inline;" onsubmit="return confirm('Remove this item from your watchlist?');">
                        <input type="hidden" name="remove_auction_id" value="<?= $auction_id ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="fa fa-trash"></i> Remove
                        </button>
                    </form>
                </div>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include_once("footer.php"); ?>