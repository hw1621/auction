<?php include_once("header.php"); ?>
<?php require_once("utilities.php"); ?>
<?php require_once("config.php"); ?>

<div class="container mt-5">

    <div class="text-center mb-5">
        <h2 class="browse-serif-title display-4">Auction Collection</h2>
        <p class="text-muted text-uppercase small" style="letter-spacing: 2px;">Curated Listings for You</p>
    </div>

    <div class="browse-search-container">
        <form method="get" action="browse.php">
          <div class="row align-items-end">
            
            <div class="col-md-5 mb-3 mb-md-0">
              <label for="keyword" class="text-muted small text-uppercase mb-1">Search</label>
              <input type="text" class="form-control browse-input" id="keyword" name="keyword" 
                     placeholder="e.g. Rolex, Painting..." 
                     value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>">
            </div>
            
            <div class="col-md-3 mb-3 mb-md-0">
              <label for="cat" class="text-muted small text-uppercase mb-1">Category</label>
              <select class="form-control browse-select" id="cat" name="cat">
                <option value="all">All Categories</option>
                <?php
                  $conn = get_database_connection();
                  $cat_sql = "SELECT id, name FROM categories ORDER BY name ASC";
                  $cat_result = $conn->query($cat_sql);
                  $current_cat = $_GET['cat'] ?? 'all';
                  
                  $category_names = []; 

                  if ($cat_result) {
                      while ($cat_row = $cat_result->fetch_assoc()) {
                          $category_names[$cat_row['id']] = $cat_row['name'];
                          $selected = ($cat_row['id'] == $current_cat) ? 'selected' : '';
                          echo "<option value='{$cat_row['id']}' {$selected}>{$cat_row['name']}</option>";
                      }
                  }
                ?>
              </select>
            </div>
            
            <div class="col-md-2 mb-3 mb-md-0">
              <label for="order_by" class="text-muted small text-uppercase mb-1">Sort By</label>
              <select class="form-control browse-select" id="order_by" name="order_by">
                <option value="date" <?php echo (!isset($_GET['order_by']) || $_GET['order_by'] == 'date') ? 'selected' : ''; ?>>Ending Soon</option>
                <option value="pricelow" <?php echo (isset($_GET['order_by']) && $_GET['order_by'] == 'pricelow') ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="pricehigh" <?php echo (isset($_GET['order_by']) && $_GET['order_by'] == 'pricehigh') ? 'selected' : ''; ?>>Price: High to Low</option>
              </select>
            </div>
            
            <div class="col-md-2">
              <button type="submit" class="btn browse-btn-search btn-block">Search</button>
            </div>
          </div>
        </form>
    </div> 

</div>

<?php
  $keyword = $_GET['keyword'] ?? '';
  $category = $_GET['cat'] ?? 'all';
  $ordering = $_GET['order_by'] ?? 'date';
  $curr_page = $_GET['page'] ?? 1;
  $results_per_page = 10;

  $common_from = " FROM auction a JOIN item i ON a.item_id = i.id ";
  $common_where = " WHERE a.status = '" . AuctionStatus::ACTIVE . "' ";
  $params = [];
  $types = "";

  if (!empty($keyword)) {
      $common_where .= " AND (a.title LIKE ? OR i.description LIKE ?) ";
      $search_term = "%" . $keyword . "%";
      $params[] = $search_term;
      $params[] = $search_term;
      $types .= "ss";
  }

  if ($category != 'all') {
      $common_where .= " AND i.category_id = ? ";
      $params[] = $category;
      $types .= "i";
  }

  $count_sql = "SELECT COUNT(DISTINCT a.id) " . $common_from . $common_where;
  
  $stmt = $conn->prepare($count_sql);
  if (!empty($params)) {
      $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $stmt->bind_result($num_results);
  $stmt->fetch();
  $stmt->close();

  $max_page = ceil($num_results / $results_per_page);
  if ($max_page == 0) $max_page = 1;
  if ($curr_page > $max_page) $curr_page = $max_page;
  if ($curr_page < 1) $curr_page = 1;
  
  $data_from = $common_from . " LEFT JOIN bid b ON a.id = b.auction_id ";
  $group_by_sql = " GROUP BY a.id ";
  
  switch ($ordering) {
      case 'pricelow': $order_sql = " ORDER BY a.start_price ASC "; break;
      case 'pricehigh': $order_sql = " ORDER BY a.start_price DESC "; break;
      case 'date': default: $order_sql = " ORDER BY a.end_date ASC "; break;
  }

  $data_sql = "SELECT 
                a.id, 
                a.title, 
                a.start_price, 
                i.description, 
                a.end_date, 
                i.category_id,
                a.is_anonymous,
                COUNT(b.id) as num_bids, 
                COALESCE(MAX(b.amount), a.start_price) as current_price
               " . $data_from . $common_where . $group_by_sql . $order_sql . " LIMIT ? OFFSET ?";

  $offset = ($curr_page - 1) * $results_per_page;
  $params[] = $results_per_page;
  $params[] = $offset;
  $types .= "ii";

  $stmt = $conn->prepare($data_sql);
  if (!empty($params)) {
      $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $result = $stmt->get_result();
?>

<div class="container mb-5">

    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <span class="text-muted text-uppercase small" style="letter-spacing: 1px;">
            Showing <?php echo $num_results; ?> Lots
        </span>
        <span class="text-muted small">Page <?php echo $curr_page; ?> of <?php echo $max_page; ?></span>
    </div>

    <?php if ($num_results == 0): ?>
        <div class="alert alert-light text-center py-5 border">
            <h4 class="browse-serif-title">No results found</h4>
            <p class="text-muted">Try adjusting your search criteria.</p>
        </div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
        <?php
          while ($row = $result->fetch_assoc()) {
              $auction_id = $row['id'];
              $title = $row['title'];
              if (!empty($row['is_anonymous'])) {
                $title_to_show = '[Anonymous] ' . $title;
              } else {
                $title_to_show = $title;
              }
              $description = mb_strimwidth($row['description'], 0, 100, "...");
              $display_price = $row['current_price']; 
              $num_bids = $row['num_bids'];
              $end_date = new DateTime($row['end_date']);

              $cat_id = $row['category_id'];
              $category_name = $category_names[$cat_id] ?? 'General';

              print_listing_li($auction_id, $title_to_show, $description, $display_price, $num_bids, $end_date, $category_name);
          }
        ?>
      </ul>
    <?php endif; ?>

    <?php $stmt->close(); ?>

    <nav aria-label="Search results pages" class="mt-5">
      <ul class="pagination browse-pagination justify-content-center">
    <?php
      $querystring = "";
      foreach ($_GET as $key => $value) {
        if ($key != "page") {
          $querystring .= "$key=" . urlencode($value) . "&amp;";
        }
      }
      
      if ($num_results > 0) {
          $high_page_boost = max(3 - $curr_page, 0);
          $low_page_boost = max(2 - ($max_page - $curr_page), 0);
          $low_page = max(1, $curr_page - 2 - $low_page_boost);
          $high_page = min($max_page, $curr_page + 2 + $high_page_boost);
          
          if ($curr_page != 1) {
            echo('<li class="page-item"><a class="page-link" href="browse.php?' . $querystring . 'page=' . ($curr_page - 1) . '">&larr; Prev</a></li>');
          }
          
          for ($i = $low_page; $i <= $high_page; $i++) {
            if ($i == $curr_page) {
              echo('<li class="page-item active"><span class="page-link">' . $i . '</span></li>');
            } else {
              echo('<li class="page-item"><a class="page-link" href="browse.php?' . $querystring . 'page=' . $i . '">' . $i . '</a></li>');
            }
          }
          
          if ($curr_page != $max_page) {
            echo('<li class="page-item"><a class="page-link" href="browse.php?' . $querystring . 'page=' . ($curr_page + 1) . '">Next &rarr;</a></li>');
          }
      }
    ?>
      </ul>
    </nav>

</div>

<?php include_once("footer.php")?>
