<?php include_once("header.php")?>
<?php require("utilities.php")?>

<?php
  $auction_id = isset($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;

  if ($auction_id == 0) {
      echo "<div class='container my-5'><div class='alert alert-danger'>Invalid Auction ID.</div></div>";
      include_once("footer.php");
      exit();
  }

  $conn = get_database_connection();

  $sql = "
    SELECT 
      i.title,
      i.description,
      i.seller_id,
      u.email AS seller_email,
      a.start_price,
      a.end_date,
      a.status,
      COALESCE(MAX(b.amount), a.start_price) AS current_price,
      COUNT(b.id) AS num_bids
    FROM auction a
    JOIN item i ON a.item_id = i.id
    JOIN users u ON i.seller_id = u.user_id
    LEFT JOIN bid b ON a.id = b.auction_id
    WHERE a.id = ?
    GROUP BY a.id
  ";

  $stmt = $conn->prepare($sql);
  if (!$stmt) { die('Prepare failed: ' . $conn->error); }
  $stmt->bind_param("i", $auction_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $auction_data = $result->fetch_assoc();
  $stmt->close();

  if (!$auction_data) {
    echo "<div class='container my-5 text-center'><h3>Lot not found.</h3></div>";
    include_once("footer.php");
    exit();
  }

  $title = $auction_data['title'];
  $description = $auction_data['description'];
  $current_price = $auction_data['current_price'];
  $num_bids = $auction_data['num_bids'];
  $seller_id = $auction_data['seller_id'];
  $seller_email = $auction_data['seller_email'];
  $end_time = new DateTime($auction_data['end_date']);
  $now = new DateTime();

  $is_ended = ($now >= $end_time);
  $time_remaining = $is_ended ? 'Lot Closed' : display_time_remaining(date_diff($now, $end_time));
  $deadline_str = $end_time->format('F j, Y • g:i A');

  // Check if user has added this auction to watch-list
  $has_session = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true;
  $watching = false; 
  if ($has_session) {
      $watch_sql = "SELECT COUNT(*) FROM watchlist WHERE user_id = ? AND auction_id = ?";
      $stmt_watch = $conn->prepare($watch_sql);
      $stmt_watch->bind_param("ii", $_SESSION['user_id'], $auction_id);
      $stmt_watch->execute();
      $stmt_watch->bind_result($watch_count);
      $stmt_watch->fetch();
      $stmt_watch->close();

      if ($watch_count > 0) {
          $watching = true;
      }
  }
?>

<div class="container mt-5 mb-5">
    
    <nav aria-label="breadcrumb" class="mb-4">
        <small class="text-muted text-uppercase" style="letter-spacing: 1px;">
            <a href="browse.php" class="text-muted text-decoration-none">Auctions</a> 
            &nbsp;/&nbsp; Lot #<?php echo $auction_id; ?>
        </small>
    </nav>

    <div class="row">
        
        <div class="col-lg-7 mb-4">
            <div class="lot-image-container">
                <i class="fa fa-image fa-5x text-black-50" style="opacity: 0.2;"></i>
            </div>
            
            <div class="mt-5">
                <h4 class="noble-serif mb-4">Lot Description</h4>
                <div class="text-muted" style="line-height: 1.8; font-size: 1.05rem;">
                    <?php echo nl2br(htmlspecialchars($description)); ?>
                </div>
                
                <hr class="my-5">
                
                <h5 class="noble-serif mb-3">Seller Information</h5>
                <p class="text-muted">
                    Seller Email: <strong><?php echo $seller_email; ?></strong><br>
                    <small>Verified Seller</small>
                </p>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="lot-info-panel sticky-top" style="top: 20px; z-index: 1;">
                
                <h1 class="noble-serif mb-3" style="line-height: 1.2;"><?php echo htmlspecialchars($title); ?></h1>

                <div class="mb-4 pb-4 border-bottom">
                    <span class="price-label d-block mb-1 text-danger" style="letter-spacing: 1px;">
                        <i class="fa fa-clock-o"></i> Auction Deadline
                    </span>
                    <div class="d-flex align-items-center flex-wrap">
                        <span style="font-size: 1.4rem; font-family: 'Times New Roman', serif; font-weight: bold; color: #222; margin-right: 15px;">
                            <?php echo $deadline_str; ?>
                        </span>
                        <span class="badge badge-light border text-muted p-2" style="font-weight: normal;">
                            <?php if ($is_ended): ?>
                                Closed
                            <?php else: ?>
                                <?php echo $time_remaining; ?> left
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="mb-4">
                    <span class="price-label d-block mb-1">Current Highest Bid</span>
                    
                    <div class="d-flex align-items-center">
                        
                        <div class="mr-3">
                            <span class="noble-serif" style="font-size: 2.8rem; line-height: 1; color: #111;">
                                £<?php echo number_format($current_price, 2); ?>
                            </span>
                        </div>

                        <div style="height: 40px; border-left: 1px solid #ddd; margin-right: 15px;"></div>

                        <div class="text-muted small" style="line-height: 1.4;">
                            <div>
                                <strong class="text-dark"><?php echo $num_bids; ?></strong> Bids placed
                            </div>
                            <div>
                                Start Price: <strong class="text-dark">£<?php echo number_format($auction_data['start_price'], 2); ?></strong>
                            </div>
                        </div>

                    </div>
                </div>

                <?php if (!$is_ended): ?>
                    <?php if ($has_session): ?>
                        <form method="POST" action="place_bid.php" class="mb-4">
                            <div class="form-group mb-4">
                                <label for="bid" class="price-label text-dark">Your Maximum Bid</label>
                                <div class="d-flex align-items-end">
                                    <span class="h4 mb-2 mr-2">£</span>
                                    <input type="number" class="form-control noble-input" id="bid" name="bid_amount" 
                                           min="<?php echo $current_price + 0.01; ?>" step="0.01" required placeholder="<?php echo number_format($current_price + 10, 2); ?>">
                                </div>
                                <small class="text-muted mt-2 d-block">Minimum bid: £<?php echo number_format($current_price + 0.01, 2); ?></small>
                            </div>
                            
                            <input type="hidden" name="auction_id" value="<?php echo $auction_id; ?>">
                            <button type="submit" class="btn btn-noble btn-block">Place Bid</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-light border text-center mb-4">
                            Please <a href="#" data-toggle="modal" data-target="#loginModal" class="text-dark font-weight-bold">Log In</a> to bid.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-secondary text-center mb-4 p-4" style="background: #f0f0f0; border:none;">
                        <h5 class="noble-serif mb-0">Auction Closed</h5>
                        <small>Final Price: £<?php echo number_format($current_price, 2); ?></small>
                    </div>
                <?php endif; ?>

                <div class="text-center mb-5">
                    <?php if ($has_session): ?>
                        <div id="watch_nowatch" <?php if ($watching) echo('style="display: none"');?> >
                            <button class="btn btn-outline-noble btn-block" onclick="addToWatchlist()">
                                <i class="fa fa-star-o"></i> &nbsp; Add to Watchlist
                            </button>
                        </div>
                        <div id="watch_watching" <?php if (!$watching) echo('style="display: none"');?> >
                            <button class="btn btn-outline-noble btn-block active" onclick="removeFromWatchlist()">
                                <i class="fa fa-star" style="color: #ffc107;"></i> &nbsp; Watching
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pt-4 border-top">
                    <h5 class="noble-serif mb-3" style="font-size: 1.1rem;">Bidding History</h5>
                    <table class="table table-borderless rank-table">
                        <thead>
                            <tr>
                                <th scope="col">Rank</th>
                                <th scope="col">Bidder</th>
                                <th scope="col" class="text-right">Amount</th>
                                <th scope="col" class="text-right">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $history_sql = "SELECT b.amount, b.bid_time, u.username, u.email 
                                            FROM bid b 
                                            JOIN users u ON b.bidder_id = u.user_id 
                                            WHERE b.auction_id = ? 
                                            ORDER BY b.amount DESC 
                                            LIMIT 5";                            
                            $stmt_hist = $conn->prepare($history_sql);
                            $stmt_hist->bind_param("i", $auction_id);
                            $stmt_hist->execute();
                            $res_hist = $stmt_hist->get_result();
                            
                            $rank = 1;
                            if ($res_hist->num_rows > 0) {
                                while ($bid_row = $res_hist->fetch_assoc()) {
                                    $badge_class = 'rank-badge';
                                    if ($rank == 1) $badge_class .= ' rank-gold';
                                    elseif ($rank == 2) $badge_class .= ' rank-silver';
                                    elseif ($rank == 3) $badge_class .= ' rank-bronze';
                                    else $badge_class = 'text-muted small pl-2';
                                    
                                    $rank_display = ($rank <= 3) ? "<span class='$badge_class'>$rank</span>" : $rank;
                                    $bid_time = new DateTime($bid_row['bid_time']);
                                    $email = $bid_row['email'];
                                    $parts = explode("@", $email);
                                    if (count($parts) == 2) {
                                        $display_email = substr($parts[0], 0, 2) . "***@" . $parts[1];
                                    } else {
                                        $display_email = "Unknown";
                                    }
                                    
                                    echo "<tr>";
                                    echo "<td>$rank_display</td>";
                                    echo "<td style='font-family: sans-serif; color: #555;'>$display_email</td>";                                    
                                    echo "<td class='text-right font-weight-bold'>£" . number_format($bid_row['amount'], 2) . "</td>";
                                    echo "<td class='text-right text-muted'>" . $bid_time->format('H:i') . "</td>";
                                    echo "</tr>";
                                    $rank++;
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center text-muted'>No bids yet. Start the bidding!</td></tr>";
                            }
                            $stmt_hist->close();
                            ?>
                        </tbody>
                    </table>
                </div>

            </div> 
        </div>
    </div>
</div>

<?php 
  $conn->close();
  include_once("footer.php");
?>

<script> 
function addToWatchlist() {
  $.ajax('watchlist_funcs.php', {
    type: "POST",
    data: {
        functionname: 'add_to_watchlist', 
        auction_id: <?php echo($auction_id);?>
    },
    success: function(obj, textstatus) {
        if (obj.trim() == "success") {
          $("#watch_nowatch").hide();
          $("#watch_watching").show();
        }
    }
  });
}

function removeFromWatchlist() {
  $.ajax('watchlist_funcs.php', {
    type: "POST",
    data: {
        functionname: 'remove_from_watchlist', 
        auction_id: <?php echo($auction_id);?>
    },
    success: function(obj, textstatus) {
        if (obj.trim() == "success") {
          $("#watch_watching").hide();
          $("#watch_nowatch").show();
        }
    }
  });
}
</script>