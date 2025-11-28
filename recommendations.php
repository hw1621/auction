<?php include_once("header.php")?>
<?php require("utilities.php")?>

<style>
    /* 顶部 Hero 区域 */
    .rec-hero {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px 20px;
        border-radius: 10px;
        margin-bottom: 30px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .rec-hero h2 { font-weight: 700; margin-bottom: 10px; }
    .rec-hero p { opacity: 0.9; font-size: 1.1rem; }

    /* 卡片交互效果 */
    .auction-card {
        transition: all 0.3s ease;
        border: none;
        border-radius: 12px;
        overflow: hidden;
    }
    .auction-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.15);
    }

    /* 模拟图片区域 */
    .card-img-placeholder {
        height: 180px;
        background-color: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #adb5bd;
        font-size: 3rem;
        position: relative;
    }
    
    /* 价格高亮 */
    .price-tag {
        color: #2c3e50;
        font-weight: 800;
        font-size: 1.25rem;
    }

    /* 推荐匹配度徽章 */
    .match-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(255, 255, 255, 0.9);
        color: #e84393;
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.85rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        z-index: 2;
    }
</style>

<?php
function get_user_recommendations($conn, $userId, $limit = 10) {
    $recommendations = [];
    
    $sql = "
        SELECT 
            a.id, 
            a.title, 
            a.start_price, 
            a.end_date,
            a.item_id,
            COUNT(DISTINCT other_bids.bidder_id) AS relevance_score
        FROM bid AS my_bids
        JOIN bid AS peer_bids 
            ON my_bids.auction_id = peer_bids.auction_id 
        JOIN bid AS other_bids 
            ON peer_bids.bidder_id = other_bids.bidder_id 
        JOIN auction AS a 
            ON other_bids.auction_id = a.id
        WHERE 
            my_bids.bidder_id = ?           
            AND peer_bids.bidder_id != ?    
            AND a.status = ?                
            AND a.id NOT IN (               
                SELECT auction_id FROM bid WHERE bidder_id = ?
            )
        GROUP BY a.id
        ORDER BY relevance_score DESC, a.end_date ASC
        LIMIT ?                             
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log('get_user_recommendations prepare failed: ' . $conn->error);
        return []; 
    }

    $statusActive = AuctionStatus::ACTIVE; 

    $stmt->bind_param('iisii', $userId, $userId, $statusActive, $userId, $limit);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recommendations[] = $row;
        }
        $result->free();
    } else {
        error_log('get_user_recommendations execute failed: ' . $stmt->error);
    }

    $stmt->close();
    return $recommendations;
}
?>


<div class="container mt-4">
    <?php
      if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !$_SESSION['user_id']) {
        echo '
        <div class="jumbotron text-center bg-light">
            <h1 class="display-4"><i class="fa fa-lock text-muted"></i></h1>
            <p class="lead">Unlock your personalized collection.</p>
            <hr class="my-4">
            <p>Sign in to see auctions curated just for you based on your bidding history.</p>
            <a class="btn btn-primary btn-lg" href="#" role="button" data-toggle="modal" data-target="#loginModal">
                Login / Register
            </a>
        </div>';
        echo '</div>'; // close container
        include_once("footer.php"); 
        exit();
      }

      //TODO: 需要确认这个变量被放到session里面了
      $current_user_id = $_SESSION['user_id'];
      
      // ==========================================
      // MOCK DATA 开关 (测试完成后，把这里改成 false)
      // ==========================================
      $use_mock_data = true; 
      
      if ($use_mock_data) {
          $recommendations = [
              [
                  'id' => 101,
                  'title' => 'Vintage Rolex Submariner 1985',
                  'start_price' => 4500.00,
                  'end_date' => date('Y-m-d H:i:s', strtotime('+2 days 5 hours')),
                  'relevance_score' => 12
              ],
              [
                  'id' => 102,
                  'title' => 'Sony PlayStation 5 - Digital Edition',
                  'start_price' => 350.00,
                  'end_date' => date('Y-m-d H:i:s', strtotime('+4 hours')),
                  'relevance_score' => 45
              ],
              [
                  'id' => 103,
                  'title' => 'Rare Pokémon Card - Charizard Holo',
                  'start_price' => 120.50,
                  'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
                  'relevance_score' => 8
              ],
              [
                  'id' => 104,
                  'title' => 'Herman Miller Aeron Chair',
                  'start_price' => 300.00,
                  'end_date' => date('Y-m-d H:i:s', strtotime('+5 days')),
                  'relevance_score' => 5
              ],
              [
                  'id' => 105,
                  'title' => 'MacBook Pro M2 16-inch',
                  'start_price' => 1800.00,
                  'end_date' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
                  'relevance_score' => 22
              ]
          ];
      } else {
          $conn = get_database_connection();
          $recommendations = get_user_recommendations($conn, $current_user_id);
          close_database_connection($conn);
      }
    ?>

    <div class="rec-hero">
        <h2><i class="fa fa-star"></i> Curated For You</h2>
        <p>Based on your unique taste and bidding history</p>
    </div>

    <?php if (empty($recommendations)): ?>
        <div class="text-center py-5">
            <div style="font-size: 4rem; color: #dee2e6; margin-bottom: 20px;">
                <i class="fa fa-folder-open-o"></i>
            </div>
            <h4 class="text-muted">No recommendations yet!</h4>
            <p class="text-muted mb-4">
                We need a bit more data to understand your taste. <br>
                Start by bidding on items you love.
            </p>
            <a href="browse.php" class="btn btn-outline-primary px-4 rounded-pill">Explore Auctions</a>
        </div>

    <?php else: ?>
        <div class="row">
            <?php foreach ($recommendations as $item): ?>
                <?php 
                    $item_id = $item['id'];
                    $title = htmlspecialchars($item['title']);
                    $price = number_format($item['start_price'], 2);
                    $end_date = new DateTime($item['end_date']);
                    
                    $now = new DateTime();
                    $interval = $now->diff($end_date);
                    $is_urgent = ($interval->d == 0 && $interval->h < 6);
                    
                    $display_time = $end_date->format('M j, g:i A');
                    if ($interval->d > 0) {
                        $remaining = $interval->d . " days left";
                    } else {
                        $remaining = $interval->h . "h " . $interval->i . "m left";
                    }
                    
                    $relevance = $item['relevance_score'];
                ?>
                
                <div class="col-md-4 col-sm-6 mb-4">
                    <div class="card auction-card h-100 shadow-sm">
                        <div class="match-badge">
                            <i class="fa fa-fire"></i> <?php echo $relevance; ?> Interested
                        </div>

                        <div class="card-img-placeholder">
                            <i class="fa fa-image"></i>
                        </div>

                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title text-truncate" title="<?php echo $title; ?>">
                                <a href="listing.php?item_id=<?php echo $item_id; ?>" class="text-dark text-decoration-none">
                                    <?php echo $title; ?>
                                </a>
                            </h5>
                            
                            <div class="mt-auto">
                                <p class="card-text mb-2">
                                    <span class="text-muted small">Current Bid</span><br>
                                    <span class="price-tag">£<?php echo $price; ?></span>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="<?php echo $is_urgent ? 'text-danger font-weight-bold' : 'text-muted'; ?>">
                                        <i class="fa fa-clock-o"></i> <?php echo $remaining; ?>
                                    </small>
                                    <a href="listing.php?item_id=<?php echo $item_id; ?>" class="btn btn-sm btn-primary px-3 rounded-pill">
                                        Bid Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>

<?php include_once("footer.php")?>