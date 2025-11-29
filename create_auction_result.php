<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
$conn = get_database_connection();

$errors = [];
$success = false;
$newAuctionId = null;
$title = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title      = trim($_POST['title'] ?? '');
    $details    = trim($_POST['details'] ?? '');
    $category   = trim($_POST['category'] ?? '');
    $startPrice = $_POST['start_price'] ?? '';
    $reserve    = $_POST['reserve_price'] ?? '';
    $endTimeRaw = $_POST['end_time'] ?? '';

    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    if ($category === '') {
        $errors[] = 'Category is required.';
    }

    if ($startPrice === '' || !is_numeric($startPrice) || $startPrice < 0) {
        $errors[] = 'Starting price must be a non-negative number.';
    }

    if ($reserve !== '' && (!is_numeric($reserve) || $reserve < 0)) {
        $errors[] = 'Reserve price must be a non-negative number (or leave it blank).';
    }

    $endTime = null;
    if ($endTimeRaw === '') {
        $errors[] = 'End date/time is required.';
    } else {
        $endTimestamp = strtotime($endTimeRaw);
        if ($endTimestamp === false) {
            $errors[] = 'Invalid end date/time format.';
        } else {
            $endTime = date('Y-m-d H:i:s', $endTimestamp);
            $now     = date('Y-m-d H:i:s');
            if ($endTime <= $now) {
                $errors[] = 'End date/time must be in the future.';
            }
        }
    }

    $reserveOrNull    = ($reserve === '') ? null : (float)$reserve;
    $startPriceFloat  = (float)$startPrice;

    if (empty($errors)) {
        $sellerId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

        $stmt = $conn->prepare("
            INSERT INTO auctions
                (seller_id, title, description, category, start_price, reserve_price, end_time, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        if ($stmt === false) {
            $errors[] = 'Database error: failed to prepare statement.';
        } else {
            $stmt->bind_param(
                "isssdds",
                $sellerId,
                $title,
                $details,
                $category,
                $startPriceFloat,
                $reserveOrNull,
                $endTime
            );

            if ($stmt->execute()) {
                $newAuctionId = $conn->insert_id;
                $success = true;
            } else {
                $errors[] = 'Database error: ' . $stmt->error;
            }

            $stmt->close();
        }
    }

} else {
    $errors[] = 'Invalid request method.';
}
?>

<?php include_once("header.php")?>

<div class="container my-5">

<?php

/* TODO #1: Connect to MySQL database (perhaps by requiring a file that
            already does this). */


/* TODO #2: Extract form data into variables. Because the form was a 'post'
            form, its data can be accessed via $POST['auctionTitle'], 
            $POST['auctionDetails'], etc. Perform checking on the data to
            make sure it can be inserted into the database. If there is an
            issue, give some semi-helpful feedback to user. */


/* TODO #3: If everything looks good, make the appropriate call to insert
            data into the database. */

?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h4 class="alert-heading">There was a problem creating your auction:</h4>
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <a href="create_auction.php" class="btn btn-secondary">Go back to form</a>

<?php elseif ($success): ?>
    <div class="text-center">
        Auction successfully created!
        <?php if ($newAuctionId): ?>
            <a href="listing.php?auction_id=<?= $newAuctionId ?>">View your new listing.</a>
        <?php else: ?>
            <a href="mylistings.php">Back to My Listings.</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

</div>

<?php include_once("footer.php")?>
