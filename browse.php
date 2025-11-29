<?php include_once("header.php")?>
<?php require("utilities.php")?>

<div class="container">

<h2 class="my-3">Browse listings</h2>

<div id="searchSpecs">
<form method="get" action="browse.php">
  <div class="row">
    <div class="col-md-5 pr-0">
      <div class="form-group">
        <label for="keyword" class="sr-only">Search keyword:</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text bg-transparent pr-0 text-muted">
              <i class="fa fa-search"></i>
            </span>
          </div>
          <input type="text" class="form-control border-left-0" id="keyword" name="keyword" 
                 placeholder="Search for anything" 
                 value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>">
        </div>
      </div>
    </div>
    <div class="col-md-3 pr-0">
      <div class="form-group">
        <label for="cat" class="sr-only">Search within:</label>
        <select class="form-control" id="cat" name="cat">
          <option value="all">All categories</option>
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
    </div>
    <div class="col-md-3 pr-0">
      <div class="input-group">
        <div class="input-group-prepend">
          <label class="input-group-text" for="order_by">Sort by</label>
        </div>
        <select class="custom-select" id="order_by" name="order_by">
          <option value="pricelow" <?php echo (isset($_GET['order_by']) && $_GET['order_by'] == 'pricelow') ? 'selected' : ''; ?>>Start Price (low to high)</option>
          <option value="pricehigh" <?php echo (isset($_GET['order_by']) && $_GET['order_by'] == 'pricehigh') ? 'selected' : ''; ?>>Start Price (high to low)</option>
          <option value="date" <?php echo (!isset($_GET['order_by']) || $_GET['order_by'] == 'date') ? 'selected' : ''; ?>>Soonest expiry</option>
        </select>
      </div>
    </div>
    <div class="col-md-1 px-0">
      <button type="submit" class="btn btn-primary">Search</button>
    </div>
  </div>
</form>
</div> 

</div>

<?php
  // 1. Get search parameters
  $keyword = $_GET['keyword'] ?? '';
  $category = $_GET['cat'] ?? 'all';
  $ordering = $_GET['order_by'] ?? 'date';
  $curr_page = $_GET['page'] ?? 1;
  $results_per_page = 10;

  $common_from = " FROM auction a JOIN item i ON a.item_id = i.id ";
  $common_where = " WHERE a.status = 'ACTIVE' ";

  // Search parameters
  $params = [];
  $types = "";

  // Keyword
  if (!empty($keyword)) {
      $common_where .= " AND (a.title LIKE ?) ";
      $search_term = "%" . $keyword . "%";
      $params[] = $search_term;
      $types .= "s";
  }

  // Category filter
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

  // Page calculation
  $max_page = ceil($num_results / $results_per_page);
  if ($max_page == 0) $max_page = 1;
  if ($curr_page > $max_page) $curr_page = $max_page;
  if ($curr_page < 1) $curr_page = 1;
  
  // 1. Left Join bids table to get bid counts and current price
  $data_from = $common_from . " LEFT JOIN bid b ON a.id = b.auction_id ";
  
  // 2. Ordering and Grouping
  $group_by_sql = " GROUP BY a.id ";
  switch ($ordering) {
      case 'pricelow':
          $order_sql = " ORDER BY a.start_price ASC ";
          break;
      case 'pricehigh':
          $order_sql = " ORDER BY a.start_price DESC ";
          break;
      case 'date':
      default:
          $order_sql = " ORDER BY a.end_date ASC ";
          break;
  }

  // 3. Final SQL
  $data_sql = "SELECT 
                a.id, 
                a.title, 
                a.start_price, 
                i.description, 
                a.end_date, 
                i.category_id,
                COUNT(b.id) as num_bids, 
                COALESCE(MAX(b.amount), a.start_price) as current_price
               " . $data_from . $common_where . $group_by_sql . $order_sql . " LIMIT ? OFFSET ?";

  // 4. Pageination params
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

<div class="container mt-5">

<?php if ($num_results == 0): ?>
    <div class="alert alert-light text-center">
        <h4>No results found</h4>
        <p>Try changing your search keywords or category.</p>
    </div>
<?php else: ?>
    <p class="text-muted mb-4">Found <?php echo $num_results; ?> auctions</p>

    <ul class="list-group">
    <?php
      while ($row = $result->fetch_assoc()) {
          $item_id = $row['id'];
          $title = $row['title'];
          $description = mb_strimwidth($row['description'], 0, 100, "...");
          $display_price = $row['current_price']; 
          $num_bids = $row['num_bids'];
          $end_date = new DateTime($row['end_date']);

          $cat_id = $row['category_id'];
          $category_name = $category_names[$cat_id];

          print_listing_li($item_id, $title, $description, $display_price, $num_bids, $end_date, $category_name);
      }
    ?>
    </ul>
<?php endif; ?>

<?php $stmt->close(); ?>

<nav aria-label="Search results pages" class="mt-5">
  <ul class="pagination justify-content-center">
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
        echo('<li class="page-item"><a class="page-link" href="browse.php?' . $querystring . 'page=' . ($curr_page - 1) . '"><i class="fa fa-arrow-left"></i></a></li>');
      }
      for ($i = $low_page; $i <= $high_page; $i++) {
        if ($i == $curr_page) {
          echo('<li class="page-item active"><span class="page-link">' . $i . '</span></li>');
        } else {
          echo('<li class="page-item"><a class="page-link" href="browse.php?' . $querystring . 'page=' . $i . '">' . $i . '</a></li>');
        }
      }
      if ($curr_page != $max_page) {
        echo('<li class="page-item"><a class="page-link" href="browse.php?' . $querystring . 'page=' . ($curr_page + 1) . '"><i class="fa fa-arrow-right"></i></a></li>');
      }
  }
?>
  </ul>
</nav>

</div>

<?php include_once("footer.php")?>