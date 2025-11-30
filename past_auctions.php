<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
$conn = get_database_connection();

$errors   = [];
$auctions = [];

$sql = "
    SELECT
        a.id,
        a.title           AS auction_title,
        a.start_price,
        a.reserve_price,
        a.final_price,
        a.end_date,
        a.status,
        a.winner_id,
        a.is_anonymous,
        a.hide_bidders,
        seller.username   AS seller_name,
        seller.email      AS seller_email,
        i.title           AS item_title
    FROM auction a
    JOIN users seller ON a.user_id = seller.user_id
    JOIN item  i      ON a.item_id = i.id
    WHERE a.end_date < NOW()
    ORDER BY a.end_date DESC
";

$result = $conn->query($sql);

if ($result) {
    $auctions = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
} else {
    $errors[] = "Database error loading past auctions: " . $conn->error;
}

include_once 'header.php';
?>

<div class="container my-5">
  <h2 class="mb-4">
    <i class="fa fa-history mr-2"></i>Past Auctions
  </h2>
  <p class="text-muted">
    Below are auctions that have already ended on the site.
  </p>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (!$auctions): ?>
    <div class="alert alert-info mt-3">
      There are no past auctions yet.
    </div>
  <?php else: ?>

    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped table-bordered mb-0 align-middle">
            <thead class="thead-light">
              <tr>
                <th>ID</th>
                <th>Auction title</th>
                <th>Item</th>
                <th>Seller</th>
                <th>Winner</th>
                <th class="text-right">Final price</th>
                <th class="text-right">End date</th>
                <th class="text-center">Result</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($auctions as $a): ?>
              <?php
              $isSold        = ($a['status'] === AuctionStatus::SOLD);
              $badgeClass    = $isSold ? 'badge-success' : 'badge-secondary';
              $resultText    = $isSold ? 'Sold' : 'Unsold';

              $auctionIsAnon = !empty($a['is_anonymous']);
              $hideBidders   = !empty($a['hide_bidders']);

              if ($auctionIsAnon) {
                  $displaySellerName  = 'Anonymous Seller #' . (int)$a['id'];
                  $displaySellerEmail = 'Hidden';
              } else {
                  $displaySellerName  = $a['seller_name'];
                  $displaySellerEmail = $a['seller_email'];
              }

              if ($a['winner_id'] === null) {
                  $displayWinner = '—';
              } else {
                  if ($hideBidders) {
                      $displayWinner = 'Anonymous Winner';
                  } else {
                      $displayWinner = 'ID: ' . (int)$a['winner_id'];
                  }
              }
              ?>
              <tr>
                <td><?= (int)$a['id'] ?></td>
                <td>
                  <div class="font-weight-semibold">
                    <?= htmlspecialchars($a['auction_title']) ?>
                  </div>
                </td>
                <td><?= htmlspecialchars($a['item_title']) ?></td>
                <td>
                  <?= htmlspecialchars($displaySellerName) ?><br>
                  <small class="text-muted">
                    <?= htmlspecialchars($displaySellerEmail) ?>
                  </small>
                </td>
                <td><?= htmlspecialchars($displayWinner) ?></td>
                <td class="text-right">
                  <?php if ($a['final_price'] === null): ?>
                    <span class="text-muted">—</span>
                  <?php else: ?>
                    £<?= number_format((float)$a['final_price'], 2) ?>
                  <?php endif; ?>
                </td>
                <td class="text-right">
                  <small class="text-monospace">
                    <?= htmlspecialchars($a['end_date']) ?>
                  </small>
                </td>
                <td class="text-center">
                  <span class="badge badge-pill <?= $badgeClass ?>">
                    <?= $resultText ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  <?php endif; ?>
</div>

<?php include_once 'footer.php'; ?>
