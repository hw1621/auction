<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  $_SESSION['login_error'] = 'Please log in as a seller to create auctions.';
  header('Location: browse.php');
  exit;
}

if (empty($_SESSION['account_type']) || $_SESSION['account_type'] !== 'seller') {
  $_SESSION['login_error'] = 'Only sellers can create auctions.';
  header('Location: browse.php');
  exit;
}
?>

<?php include_once("header.php")?>
<?php
$DB_HOST = "auction.c78qcak427mc.eu-north-1.rds.amazonaws.com";
$DB_USER = "admin";
$DB_PASS = "useradmin123";
$DB_NAME = "db_coursework"; 
$DB_PORT = 3306;


$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

if ($mysqli->connect_errno) {
    $cat_result = false;
} else {
    $sql_cat = "SELECT id, name FROM categories ORDER BY name ASC";
    $cat_result = $mysqli->query($sql_cat);
}
?>

<?php
/* (Uncomment this block to redirect people without selling privileges away from this page)
  // If user is not logged in or not a seller, they should not be able to
  // use this page.
  if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] != 'seller') {
    header('Location: browse.php');
  }
*/
?>

<div class="container">

<!-- Create auction form -->
<div style="max-width: 800px; margin: 10px auto">
  <h2 class="my-3">Create new auction</h2>
  <div class="card">
    <div class="card-body">
      <!-- Note: This form does not do any dynamic / client-side / 
      JavaScript-based validation of data. It only performs checking after 
      the form has been submitted, and only allows users to try once. You 
      can make this fancier using JavaScript to alert users of invalid data
      before they try to send it, but that kind of functionality should be
      extremely low-priority / only done after all database functions are
      complete. -->
      <form method="post" action="create_auction_result.php">
        <div class="form-group row">
          <label for="auctionTitle" class="col-sm-2 col-form-label text-right">Title of auction</label>
          <div class="col-sm-10">
            <input type="text" class="form-control" id="auctionTitle" name="title" placeholder="e.g. Black mountain bike">
            <small id="titleHelp" class="form-text text-muted"><span class="text-danger">* Required.</span> A short description of the item you're selling, which will display in listings.</small>
          </div>
        </div>
        <div class="form-group row">
          <label for="auctionDetails" class="col-sm-2 col-form-label text-right">Details</label>
          <div class="col-sm-10">
            <textarea class="form-control" id="auctionDetails" name="details" rows="4"></textarea>
            <small id="detailsHelp" class="form-text text-muted">Full details of the listing to help bidders decide if it's what they're looking for.</small>
          </div>
        </div>
        <div class="form-group row">
          <label for="auctionCategory" class="col-sm-2 col-form-label text-right">Category</label>
          <div class="col-sm-10">
            <select class="form-control" id="auctionCategory" name="category">
              <option value="">Choose...</option>
              <?php
                if ($cat_result && $cat_result->num_rows > 0) {
                  while ($cat = $cat_result->fetch_assoc()) {
                    echo '<option value="' . htmlspecialchars($cat['id']) . '">'
                      . htmlspecialchars($cat['name'])
                      . '</option>';
                  }
                } else {
                  echo '<option value="">(No categories found)</option>';
                }
              ?>
            </select>
            <small id="categoryHelp" class="form-text text-muted"><span class="text-danger">* Required.</span> Select a category for this item.</small>
          </div>
        </div>
        <div class="form-group row">
          <label for="auctionStartPrice" class="col-sm-2 col-form-label text-right">Starting price</label>
          <div class="col-sm-10">
	        <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">£</span>
              </div>
              <input type="number" class="form-control" id="auctionStartPrice" name="start_price">
            </div>
            <small id="startBidHelp" class="form-text text-muted"><span class="text-danger">* Required.</span> Initial bid amount.</small>
          </div>
        </div>
        <div class="form-group row">
          <label for="auctionReservePrice" class="col-sm-2 col-form-label text-right">Reserve price</label>
          <div class="col-sm-10">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">£</span>
              </div>
              <input type="number" class="form-control" id="auctionReservePrice" name="reserve_price">
            </div>
            <small id="reservePriceHelp" class="form-text text-muted">Optional. Auctions that end below this price will not go through. This value is not displayed in the auction listing.</small>
          </div>
        </div>
        <div class="form-group row">
          <label for="auctionEndDate" class="col-sm-2 col-form-label text-right">End date</label>
          <div class="col-sm-10">
            <input type="datetime-local" class="form-control" id="auctionEndDate" name="end_date">
            <small id="endDateHelp" class="form-text text-muted"><span class="text-danger">* Required.</span> Day for the auction to end.</small>
          </div>
        </div>
		  
        <div class="form-group row">
          <label class="col-sm-2 col-form-label text-right">Anonymity</label>
          <div class="col-sm-10">
            <div class="form-check">
              <input
                type="checkbox"
                class="form-check-input"
                id="auctionIsAnonymous"
                name="is_anonymous"
                value="1"
              >
              <label class="form-check-label" for="auctionIsAnonymous">
                Make this auction anonymous
              </label>
            </div>
            <small class="form-text text-muted">
              If checked, your seller identity will be hidden on public listings for this auction.
            </small>
          </div>
        </div>
		  
        <button type="submit" class="btn btn-primary form-control">Create Auction</button>
      </form>
    </div>
  </div>
</div>

</div>
<?php
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>

<?php include_once("footer.php")?>

