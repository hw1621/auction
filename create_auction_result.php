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

    $title       = trim($_POST['title'] ?? '');
    $details     = trim($_POST['details'] ?? '');
    $categoryId  = (int)($_POST['category'] ?? 0);
    $startPrice  = trim($_POST['start_price'] ?? '');
    $reserve     = trim($_POST['reserve_price'] ?? '');
    $endDateRaw  = trim($_POST['end_time'] ?? '');
    $isAnonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($details === '') {
        $errors[] = 'Details are required.';
    }
    if ($categoryId <= 0) {
        $errors[] = 'Category is required.';
    }

    if ($startPrice === '' || !is_numeric($startPrice) || $startPrice < 0) {
        $errors[] = 'Starting price must be a non-negative number.';
    }

    $reserveOrNull = null;
    if ($reserve !== '') {
        if (!is_numeric($reserve) || $reserve < 0) {
            $errors[] = 'Reserve price must be a non-negative number (or left blank).';
        } else {
            $reserveOrNull = (float)$reserve;
        }
    }

    $endDateObj = null;
    if ($endDateRaw === '') {
        $errors[] = 'End date is required.';
    } else {
        $endDateObj = DateTime::createFromFormat('Y-m-d\TH:i', $endDateRaw);
        if (!$endDateObj) {
            $errors[] = 'End date is invalid.';
        } elseif ($endDateObj <= new DateTime()) {
            $errors[] = 'End date must be in the future.';
        }
    }

    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            $sellerId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

            $itemSql = "
                INSERT INTO item (title, description, category_id, seller_id)
                VALUES (?, ?, ?, ?)
            ";
            $itemStmt = $conn->prepare($itemSql);
            if (!$itemStmt) {
                throw new Exception('Failed to prepare item insert: ' . $conn->error);
            }

            $itemStmt->bind_param('ssii', $title, $details, $categoryId, $sellerId);

            if (!$itemStmt->execute()) {
                throw new Exception('Failed to insert item: ' . $itemStmt->error);
            }

            $itemId = $itemStmt->insert_id;
            $itemStmt->close();

            $startPriceFloat = (float)$startPrice;
            $endDateMysql    = $endDateObj->format('Y-m-d H:i:s');

            $auctionSql = "
                INSERT INTO auction (
                    item_id, user_id, title, start_price, reserve_price, end_date, status, is_anonymous
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, 'open', ?
                )
            ";
            $auctionStmt = $conn->prepare($auctionSql);
            if (!$auctionStmt) {
                throw new Exception('Failed to prepare auction insert: ' . $conn->error);
            }

            $auctionStmt->bind_param(
                'iisddsi',
                $itemId,
                $sellerId,
                $title,
                $startPriceFloat,
                $reserveOrNull,
                $endDateMysql,
                $isAnonymous
            );

            if (!$auctionStmt->execute()) {
                throw new Exception('Failed to insert auction: ' . $auctionStmt->error);
            }

            $newAuctionId = $auctionStmt->insert_id;
            $auctionStmt->close();

            $conn->commit();
            $success = true;

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

} else {
    $errors[] = 'Invalid request method.';
}

?>

<?php include_once("header.php")?>

<div class="container my-5">

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
            <a href="listing.php?item_id=<?= $newAuctionId ?>">View your new listing.</a>
        <?php else: ?>
            <a href="mylistings.php">Back to My Listings.</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

</div>

<?php include_once("footer.php")?>
