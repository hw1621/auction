<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'utilities.php';

$conn = get_database_connection();

$_SESSION['user_id']=1;

if (empty($_SESSION['user_id'])) {
    $_SESSION['login_error'] = 'Please log in to view your listings.';
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
        a.status
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
          <th>Edit</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['item_title']) ?></td>
            <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorised') ?></td>
            <td>£<?= htmlspecialchars($row['start_price']) ?></td>
            <td><?= htmlspecialchars($row['end_date']) ?></td>
            <td><?= htmlspecialchars($row['status']) ?></td>
            <td>
              <a 
                href="edit_auction.php?auction_id=<?= $row['auction_id'] ?>" 
                class="btn btn-sm btn-primary">
                Edit
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>

<?php include_once("footer.php")?>
