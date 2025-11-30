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
        b.is_anonymous AS bid_is_anonymous,
        a.id         AS auction_id,
        a.end_date,
        a.status,
        a.is_anonymous AS auction_is_anonymous,
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
    <p>You have not placed any bids yet.</p>
  <?php else: ?>
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Item</th>
          <th>My bid</th>
          <th>Bid time</th>
          <th>Auction ends</th>
          <th>Status</th>
          <th>Anonymity</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
          $title = $row['item_title'];
          if (!empty($row['auction_is_anonymous'])) {
              $title = '[Anonymous] ' . $title;
          }

          if (!empty($row['bid_is_anonymous'])) {
              $anonymityLabel = 'Anonymous bid';
          } else {
              $anonymityLabel = 'Public bid';
          }
          ?>
          <tr>
            <td><?= htmlspecialchars($title) ?></td>
            <td>£<?= htmlspecialchars(number_format($row['bid_amount'], 2)) ?></td>
            <td><?= htmlspecialchars($row['bid_time']) ?></td>
            <td><?= htmlspecialchars($row['end_date']) ?></td>
            <td><?= htmlspecialchars($row['status']) ?></td>
            <td><?= htmlspecialchars($anonymityLabel) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include_once("footer.php"); ?>
