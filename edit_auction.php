<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'utilities.php';

$conn = get_database_connection();

if (empty($_SESSION['logged_in']) || $_SESSION['account_type'] !== 'seller') {
    $_SESSION['login_error'] = 'Only sellers can edit their listings.';
    header('Location: browse.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

if (empty($_GET['auction_id']) || !ctype_digit($_GET['auction_id'])) {
    die("Invalid auction id.");
}
$auctionId = (int)$_GET['auction_id'];

$sql = "
    SELECT 
        a.id AS auction_id,
        a.item_id,
        a.title AS auction_title,
        a.start_price,
        a.reserve_price,
        a.end_date,
        i.title AS item_title,
        i.description,
        i.category_id,
        i.seller_id,
        i.image_path
    FROM auction a
    JOIN item i ON a.item_id = i.id
    WHERE a.id = ? AND i.seller_id = ?
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Database error (prepare): ' . $conn->error);
}
$stmt->bind_param("ii", $auctionId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Auction not found or you do not have permission to edit it.");
}

$auction = $result->fetch_assoc();
$stmt->close();

$categories = [];
$resCat = $conn->query("SELECT id, name FROM categories ORDER BY name");
if ($resCat) {
    while ($row = $resCat->fetch_assoc()) {
        $categories[] = $row;
    }
}

$endDateValue = '';
if (!empty($auction['end_date'])) {
    $ts = strtotime($auction['end_date']);
    if ($ts !== false) {
        $endDateValue = date('Y-m-d\TH:i', $ts);
    }
}

include_once("header.php");
?>

<div class="container">

  <h2 class="my-3">Edit auction</h2>

  <div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-body">
      <form method="post" action="edit_auction_result.php" enctype="multipart/form-data">
        <input type="hidden" name="auction_id" value="<?= htmlspecialchars($auction['auction_id']) ?>">
        <input type="hidden" name="item_id" value="<?= htmlspecialchars($auction['item_id']) ?>">
        <div class="form-group row">
          <label for="auctionTitle" class="col-sm-2 col-form-label text-right">Title</label>
          <div class="col-sm-10">
            <input type="text" class="form-control" id="auctionTitle" name="title"
                   value="<?= htmlspecialchars($auction['item_title']) ?>">
            <small class="form-text text-muted">
              <span class="text-danger">*</span> Short description shown in listings.
            </small>
          </div>
        </div>

        <div class="form-group row">
          <label for="auctionDetails" class="col-sm-2 col-form-label text-right">Details</label>
          <div class="col-sm-10">
            <textarea class="form-control" id="auctionDetails" name="details" rows="4"><?= htmlspecialchars($auction['description']) ?></textarea>
            <small class="form-text text-muted">
              More detailed description of the item.
            </small>
          </div>
        </div>

        <div class="form-group row">
          <label class="col-sm-2 col-form-label text-right">Image</label>
          <div class="col-sm-10">
            <?php 
              $imagePath = $auction['image_path'];
              if (!empty($imagePath)) {
            ?>
              <div class="mb-3 p-2 bg-light border rounded">
                  <p class="small text-muted mb-1">Current Image:</p>
                  <img src="images/<?= htmlspecialchars($imagePath) ?>" alt="Current Item Image" style="max-height: 150px; max-width: 100%;">
              </div>
            <?php 
                }
            ?>
            <div class="custom-file">
              <input type="file" class="custom-file-input" id="auctionImage" name="auctionImage" accept="image/*">
              <label class="custom-file-label" for="auctionImage">Change image...</label>
            </div>
            <small class="form-text text-muted">
              Upload a new file to replace the current image. Leave blank to keep current image.
            </small>
            <div id="previewContainer" style="display: none; margin-top: 10px;">
              <div class="card" style="width: 12rem;">
                <img id="imagePreview" src="#" class="card-img-top" alt="New Image Preview" style="height: 150px; object-fit: cover;">
                <div class="card-body p-1 text-center">
                    <small class="text-success font-weight-bold">New Selection</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="form-group row">
          <label for="auctionCategory" class="col-sm-2 col-form-label text-right">Category</label>
          <div class="col-sm-10">
            <select class="form-control" id="auctionCategory" name="category_id">
              <option value="">Choose...</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['id']) ?>"
                    <?= ($cat['id'] == $auction['category_id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">
              <span class="text-danger">*</span> Select a category for this item.
            </small>
          </div>
        </div>

        <div class="form-group row">
          <label for="auctionStartPrice" class="col-sm-2 col-form-label text-right">Starting price</label>
          <div class="col-sm-10">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">£</span>
              </div>
              <input type="number" step="0.01" class="form-control" id="auctionStartPrice" name="start_price"
                     value="<?= htmlspecialchars($auction['start_price']) ?>">
            </div>
            <small class="form-text text-muted">
              <span class="text-danger">*</span> Initial bid amount.
            </small>
          </div>
        </div>

        <div class="form-group row">
          <label for="auctionReservePrice" class="col-sm-2 col-form-label text-right">Reserve price</label>
          <div class="col-sm-10">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">£</span>
              </div>
              <input type="number" step="0.01" class="form-control" id="auctionReservePrice" name="reserve_price"
                     value="<?= htmlspecialchars($auction['reserve_price']) ?>">
            </div>
            <small class="form-text text-muted">
              Optional minimum selling price (not shown to bidders).
            </small>
          </div>
        </div>

        <div class="form-group row">
          <label for="auctionEndDate" class="col-sm-2 col-form-label text-right">End date</label>
          <div class="col-sm-10">
            <input type="datetime-local" class="form-control" id="auctionEndDate" name="end_time"
                   value="<?= htmlspecialchars($endDateValue) ?>">
            <small class="form-text text-muted">
              <span class="text-danger">*</span> Auction closing date/time.
            </small>
          </div>
        </div>

        <button type="submit" class="btn btn-primary form-control">Save changes</button>
      </form>
    </div>
  </div>
</div>

<?php include_once("footer.php") ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const fileInput = document.getElementById('auctionImage');
    const previewContainer = document.getElementById('previewContainer');
    const previewImage = document.getElementById('imagePreview');
    const label = fileInput.nextElementSibling;

    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            label.innerText = file.name;
            label.classList.add("selected");

            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                previewContainer.style.display = 'block';
            }
            reader.readAsDataURL(file);
        }
    });
});
</script>
