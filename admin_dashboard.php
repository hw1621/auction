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

$errors = [];

$totalUsers = 0;
$result = $conn->query("SELECT COUNT(*) AS c FROM users");
if ($result) {
    $row = $result->fetch_assoc();
    $totalUsers = (int)$row['c'];
    $result->close();
} else {
    $errors[] = "Error counting users: " . $conn->error;
}

$userRoles = [
    'buyer'  => 0,
    'seller' => 0,
    'admin'  => 0
];
$result = $conn->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $role = strtolower($row['role']);
        if (isset($userRoles[$role])) {
            $userRoles[$role] = (int)$row['c'];
        }
    }
    $result->close();
} else {
    $errors[] = "Error counting users by role: " . $conn->error;
}

$totalAuctions = 0;
$result = $conn->query("SELECT COUNT(*) AS c FROM auction");
if ($result) {
    $row = $result->fetch_assoc();
    $totalAuctions = (int)$row['c'];
    $result->close();
} else {
    $errors[] = "Error counting auctions: " . $conn->error;
}

$auctionStatuses = [
    AuctionStatus::ACTIVE    => 0,
    AuctionStatus::SOLD      => 0,
    AuctionStatus::UNSOLD    => 0,
    AuctionStatus::CANCELLED => 0,
];

$result = $conn->query("SELECT status, COUNT(*) AS c FROM auction GROUP BY status");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'];
        if (isset($auctionStatuses[$status])) {
            $auctionStatuses[$status] = (int)$row['c'];
        }
    }
    $result->close();
} else {
    $errors[] = "Error counting auctions by status: " . $conn->error;
}

$totalVolume = 0.0;
$statusSold  = AuctionStatus::SOLD;
$stmt = $conn->prepare("SELECT SUM(final_price) AS s FROM auction WHERE status = ?");
if ($stmt) {
    $stmt->bind_param("s", $statusSold);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if (!is_null($row['s'])) {
        $totalVolume = (float)$row['s'];
    }
    $stmt->close();
} else {
    $errors[] = "Error calculating total volume: " . $conn->error;
}

$totalBids = 0;
$result = $conn->query("SELECT COUNT(*) AS c FROM bid");
if ($result) {
    $row = $result->fetch_assoc();
    $totalBids = (int)$row['c'];
    $result->close();
} else {
    $errors[] = "Error counting bids: " . $conn->error;
}

$topSellers = [];
$sql = "
    SELECT 
        u.user_id,
        u.username,
        u.email,
        COUNT(*) AS auction_count
    FROM auction a
    JOIN item i ON a.item_id = i.id
    JOIN users u ON i.seller_id = u.user_id
    GROUP BY u.user_id, u.username, u.email
    ORDER BY auction_count DESC
    LIMIT 5
";
$result = $conn->query($sql);
if ($result) {
    $topSellers = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
} else {
    $errors[] = "Error loading top sellers: " . $conn->error;
}

$topBidders = [];
$sql = "
    SELECT
        u.user_id,
        u.username,
        u.email,
        COUNT(*) AS bid_count,
        MAX(b.amount) AS max_bid
    FROM bid b
    JOIN users u ON b.bidder_id = u.user_id
    GROUP BY u.user_id, u.username, u.email
    ORDER BY bid_count DESC
    LIMIT 5
";
$result = $conn->query($sql);
if ($result) {
    $topBidders = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
} else {
    $errors[] = "Error loading top bidders: " . $conn->error;
}

include_once 'header.php';
?>

<div class="container my-5">
  <h2 class="mb-4">Admin: Dashboard</h2>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-warning">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="row mb-4">
    <div class="col-md-3 mb-3">
      <div class="card border-primary h-100">
        <div class="card-body">
          <h5 class="card-title">Users</h5>
          <p class="display-6 mb-2"><?= $totalUsers ?></p>
          <p class="mb-0 small text-muted">
            Buyers: <?= $userRoles['buyer'] ?? 0 ?>,
            Sellers: <?= $userRoles['seller'] ?? 0 ?>,
            Admins: <?= $userRoles['admin'] ?? 0 ?>
          </p>
        </div>
      </div>
    </div>

    <div class="col-md-3 mb-3">
      <div class="card border-success h-100">
        <div class="card-body">
          <h5 class="card-title">Auctions</h5>
          <p class="display-6 mb-2"><?= $totalAuctions ?></p>
          <p class="mb-0 small text-muted">
            Active: <?= $auctionStatuses[AuctionStatus::ACTIVE] ?? 0 ?><br>
            Sold: <?= $auctionStatuses[AuctionStatus::SOLD] ?? 0 ?>,
            Unsold: <?= $auctionStatuses[AuctionStatus::UNSOLD] ?? 0 ?>,
            Cancelled: <?= $auctionStatuses[AuctionStatus::CANCELLED] ?? 0 ?>
          </p>
        </div>
      </div>
    </div>

    <div class="col-md-3 mb-3">
      <div class="card border-info h-100">
        <div class="card-body">
          <h5 class="card-title">Bids</h5>
          <p class="display-6 mb-2"><?= $totalBids ?></p>
          <p class="mb-0 small text-muted">Total number of bids placed.</p>
        </div>
      </div>
    </div>

    <div class="col-md-3 mb-3">
      <div class="card border-warning h-100">
        <div class="card-body">
          <h5 class="card-title">Total Volume</h5>
          <p class="display-6 mb-2">£<?= number_format($totalVolume, 2) ?></p>
          <p class="mb-0 small text-muted">Sum of final prices of sold auctions.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6 mb-4">
      <div class="card h-100">
        <div class="card-header">
          Top 5 Sellers (by number of auctions)
        </div>
        <div class="card-body p-0">
          <?php if (!$topSellers): ?>
            <p class="p-3 mb-0">No sellers data.</p>
          <?php else: ?>
          <table class="table mb-0 table-sm">
            <thead>
              <tr>
                <th>User</th>
                <th>Email</th>
                <th class="text-right">Auctions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($topSellers as $s): ?>
              <tr>
                <td><?= htmlspecialchars($s['username']) ?> (ID: <?= (int)$s['user_id'] ?>)</td>
                <td><?= htmlspecialchars($s['email']) ?></td>
                <td class="text-right"><?= (int)$s['auction_count'] ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-md-6 mb-4">
      <div class="card h-100">
        <div class="card-header">
          Top 5 Bidders (by number of bids)
        </div>
        <div class="card-body p-0">
          <?php if (!$topBidders): ?>
            <p class="p-3 mb-0">No bidders data.</p>
          <?php else: ?>
          <table class="table mb-0 table-sm">
            <thead>
              <tr>
                <th>User</th>
                <th>Email</th>
                <th class="text-right">Bids</th>
                <th class="text-right">Max bid</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($topBidders as $b): ?>
              <tr>
                <td><?= htmlspecialchars($b['username']) ?> (ID: <?= (int)$b['user_id'] ?>)</td>
                <td><?= htmlspecialchars($b['email']) ?></td>
                <td class="text-right"><?= (int)$b['bid_count'] ?></td>
                <td class="text-right">£<?= htmlspecialchars($b['max_bid']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<?php include_once 'footer.php'; ?>
