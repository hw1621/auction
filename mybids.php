<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'utilities.php';

$conn = get_database_connection();

if (empty($_SESSION['logged_in']) || $_SESSION['account_type'] !== 'buyer') {
    $_SESSION['login_error'] = 'Please log in as a buyer to view your bids.';
    header('Location: browse.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$sql = "
    SELECT 
        b.id         AS bid_id,
        b.amount     AS bid_amount,
        b.bid_time,
        a.id         AS auction_id,
        a.end_date,
        a.status,
        a.winner_id,
        i.title      AS item_title
    FROM bid b
    JOIN auction a ON b.auction_id = a.id
    JOIN item i    ON a.item_id    = i.id
    WHERE b.bidder_id = ?
    ORDER BY b.bid_time DESC
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Database error (prepare mybids): ' . $conn->error);
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

include_once("header.php");
?>

<div class="container my-4">
  <h2 class="my-3">My bids</h2>

  <?php if ($result->num_rows === 0): ?>
    <div class="alert alert-light border text-center p-5">
        <h4>You have not placed any bids yet.</h4>
        <a href="browse.php" class="btn btn-primary mt-3">Browse Auctions</a>
    </div>
  <?php else: ?>
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th scope="col">Item</th>
          <th scope="col">My Bid</th>
          <th scope="col">Date Placed</th>
          <th scope="col">Auction Ends</th>
          <th scope="col">Result</th>
          <th scope="col" style="width: 200px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
          $title = $row['item_title'];
          $auction_id = $row['auction_id'];
          $bid_id = $row['bid_id'];
          $auction_status = $row['status']; 
          $winner_id = $row['winner_id'];
          
          $displayStatus = '';
          $badgeClass = '';

          $statusUpper = strtoupper($auction_status);

          if ($statusUpper === 'ACTIVE') {
              $displayStatus = 'Active';
              $badgeClass = 'badge badge-primary'; 
          } 
          elseif ($statusUpper === 'CANCELLED') {
              $displayStatus = 'Auction Cancelled';
              $badgeClass = 'badge badge-dark';
          }
          elseif ($statusUpper === 'SOLD') {
              if ($winner_id == $userId) {
                  $displayStatus = 'Won!';
                  $badgeClass = 'badge badge-success';
              } else {
                  $displayStatus = 'Lost';
                  $badgeClass = 'badge badge-danger';
              }
          } 
          elseif ($statusUpper === 'UNSOLD') {
              $displayStatus = 'Ended (Unsold)';
              $badgeClass = 'badge badge-secondary';
          } 
          else {
              $displayStatus = $auction_status;
              $badgeClass = 'badge badge-light';
          }
          ?>
          
          <tr>
            <td class="align-middle">
              <a href="listing.php?item_id=<?= $auction_id ?>" class="font-weight-bold text-dark" style="text-decoration:none;">
                  <?= htmlspecialchars($title) ?>
              </a>
            </td>
            <td class="align-middle font-weight-bold">£<?= htmlspecialchars(number_format($row['bid_amount'], 2)) ?></td>
            <td class="align-middle"><?= htmlspecialchars($row['bid_time']) ?></td>
            
            <td class="align-middle">
                <?= htmlspecialchars($row['end_date']) ?>
                <?php if ($statusUpper !== 'ACTIVE'): ?>
                    <span class="text-muted small" style="font-weight: 600; margin-left: 5px;">(Ended)</span>
                <?php endif; ?>
            </td>
            
            <td class="align-middle">
                <span class="<?= $badgeClass ?>" style="font-size: 0.85rem; padding: 6px 10px;">
                    <?= $displayStatus ?>
                </span>
            </td>
            
            <td class="align-middle">
              <?php if ($statusUpper === 'ACTIVE'): ?>
                
                <div class="d-inline-flex align-items-center">
                    <a href="listing.php?auction_id=<?= $auction_id ?>" 
                       class="text-primary mr-3" 
                       style="font-weight: 600; text-decoration: none; font-size: 0.9rem;">
                       Update
                    </a>
                    
                    <a href="cancel_bid.php?bid_id=<?= $bid_id ?>" 
                       class="text-danger"
                       style="font-weight: 600; text-decoration: none; font-size: 0.9rem;"
                       onclick="return confirm('Are you sure you want to cancel this bid? This cannot be undone.');">
                      Cancel
                    </a>
                </div>

              <?php else: ?>
                <span class="text-muted small" style="opacity: 0.6;">No actions</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include_once("footer.php"); ?>