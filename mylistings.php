<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'utilities.php';

$conn = get_database_connection();

if (empty($_SESSION['user_id'])) {
    $_SESSION['login_error'] = 'Please log in to view your listings.';
    header('Location: browse.php');
    exit;
}

if (($_SESSION['account_type'] ?? '') !== 'seller') {
    $_SESSION['login_error'] = 'Only sellers can view their listings.';
    header('Location: browse.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$sql = "
    SELECT 
        i.id AS item_id,
        i.title AS item_title,
        i.description,
        c.name AS category_name,
        a.id AS auction_id,
        a.start_price,
        a.reserve_price,
        a.end_date,
        a.status,
        a.is_anonymous
    FROM item i
    JOIN auction a ON a.item_id = i.id
    LEFT JOIN categories c ON c.id = i.category_id
    WHERE i.seller_id = ?
    ORDER BY a.end_date DESC
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Database error (prepare): ' . $conn->error);
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

include_once("header.php");
?>

<div class="container">

  <h2 class="my-3">My listings</h2>

  <?php if ($result->num_rows === 0): ?>
    <p>You have not created any auctions yet.</p>
  <?php else: ?>
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Title</th>
          <th>Category</th>
          <th>Start price</th>
          <th>End date</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
          $title = $row['item_title'];
          if (!empty($row['is_anonymous'])) {
              $title = '[Anonymous] ' . $title;
          }
          ?>
          <tr>
            <td><?= htmlspecialchars($title) ?></td>
            <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorised') ?></td>
            <td>£<?= htmlspecialchars($row['start_price']) ?></td>
            <td><?= htmlspecialchars($row['end_date']) ?></td>
            <td><?= htmlspecialchars($row['status']) ?></td>
            <td>
              <?php
                $auctionId = (int)$row['auction_id'];
                $status    = $row['status'];
              ?>

              <?php if ($status === 'active'): ?>
                <a 
                  href="edit_auction.php?auction_id=<?= $auctionId ?>" 
                  class="btn btn-sm btn-primary mb-1">
                  Edit
                </a>
                <a 
                  href="cancel_mylisting.php?auction_id=<?= $auctionId ?>" 
                  class="btn btn-sm btn-outline-danger"
                  onclick="return confirm('Are you sure you want to cancel this listing?');">
                  Cancel
                </a>
              <?php else: ?>
                <span class="text-muted">No actions</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>

<?php include_once("footer.php")?>
