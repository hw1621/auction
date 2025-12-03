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
        winner.username   AS winner_name,
        winner.email      AS winner_email,
        i.image_path      AS item_image
    FROM auction a
    JOIN item  i            ON a.item_id     = i.id
    JOIN users seller       ON i.seller_id   = seller.user_id
    LEFT JOIN users winner  ON a.winner_id   = winner.user_id
    WHERE a.end_date < NOW()
      AND a.status = 'sold'
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
    Explore a curated archive of items that found their new owners through our marketplace.
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
      No past auctions found.
    </div>
  <?php else: ?>

    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped table-bordered mb-0 align-middle">
            <thead class="thead-light">
              <tr>
                <th style="width: 240px;">Auction title</th>
                <th style="width: 180px;">Seller</th>
                <th style="width: 180px;">Winner</th>
                <th class="text-right" style="width: 110px;">Final price</th>
                <th class="text-right" style="width: 150px;">End date</th>
                <th class="text-center" style="width: 80px;">Result</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($auctions as $a): ?>

              <?php
              $isSold     = ($a['status'] === 'sold');
              $badgeClass = $isSold ? 'badge-success' : 'badge-secondary';
              $resultText = $isSold ? 'Sold' : 'Unsold';

              if ($a['is_anonymous']) {
                  $displaySellerName  = 'Anonymous Seller';
                  $displaySellerEmail = 'Hidden';
              } else {
                  $displaySellerName  = $a['seller_name'];
                  $displaySellerEmail = $a['seller_email'];
              }

              if ($a['winner_id'] === null) {
                  $displayWinner = '—';
              } else {
                  if ($a['hide_bidders']) {
                      $displayWinner = 'Anonymous Winner';
                  } else {
                      $displayWinner = htmlspecialchars($a['winner_email']);
                  }
              }

              $itemImage = $a['item_image'] ?? '';
              ?>

              <tr>

                <td>
                  <div class="d-flex align-items-center">
                    <?php if (!empty($itemImage)): ?>
                      <img
                        src="uploads/<?= htmlspecialchars($itemImage) ?>"
                        style="width: 60px; height: 60px; object-fit: cover; margin-right: 10px;"
                      >
                    <?php endif; ?>

                    <div class="font-weight-semibold">
                      <?= htmlspecialchars($a['auction_title']) ?>
                    </div>
                  </div>
                </td>

                <td>
                  <?= htmlspecialchars($displaySellerName) ?><br>
                  <small class="text-muted"><?= htmlspecialchars($displaySellerEmail) ?></small>
                </td>

                <td>
                  <?= htmlspecialchars($displayWinner) ?>
                </td>

                <td class="text-right">
                  £<?= number_format((float)$a['final_price'], 2) ?>
                </td>

                <td class="text-right">
                  <small class="text-monospace">
                    <?= htmlspecialchars($a['end_date']) ?>
                  </small>
                </td>

                <td class="text-center">
                  <span class="badge badge-pill <?= $badgeClass ?>"><?= $resultText ?></span>
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
