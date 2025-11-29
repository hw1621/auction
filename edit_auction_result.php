<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
$conn = get_database_connection();

$errors = [];
$success = false;

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; //测试
    $_SESSION['account_type'] = 'seller';
    $_SESSION['logged_in'] = true;
}
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $auctionId   = $_POST['auction_id'] ?? '';
    $itemId      = $_POST['item_id'] ?? '';
    $title       = trim($_POST['title'] ?? '');
    $details     = trim($_POST['details'] ?? '');
    $categoryId  = $_POST['category_id'] ?? '';
    $startPrice  = $_POST['start_price'] ?? '';
    $reserve     = $_POST['reserve_price'] ?? '';
    $endTimeRaw  = $_POST['end_time'] ?? '';

    if ($auctionId === '' || !ctype_digit($auctionId)) {
        $errors[] = 'Invalid auction id.';
    }
    if ($itemId === '' || !ctype_digit($itemId)) {
        $errors[] = 'Invalid item id.';
    }

    $auctionIdInt = (int)$auctionId;
    $itemIdInt    = (int)$itemId;

    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    if ($categoryId === '') {
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
            $now    = date('Y-m-d H:i:s');
            if ($endTime <= $now) {
                $errors[] = 'End date/time must be in the future.';
            }
        }
    }

    $reserveOrNull   = ($reserve === '') ? null : (float)$reserve;
    $startPriceFloat = (float)$startPrice;
    $categoryIdInt   = (int)$categoryId;

    if (empty($errors)) {
        $checkSql = "
            SELECT i.seller_id
            FROM auction a
            JOIN item i ON a.item_id = i.id
            WHERE a.id = ?
        ";
        $stmtCheck = $conn->prepare($checkSql);
        if ($stmtCheck === false) {
            $errors[] = 'Database error (prepare permission check).';
        } else {
            $stmtCheck->bind_param("i", $auctionIdInt);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();
            if ($rowCheck = $resCheck->fetch_assoc()) {
                if ((int)$rowCheck['seller_id'] !== $userId) {
                    $errors[] = 'You do not have permission to edit this auction.';
                }
            } else {
                $errors[] = 'Auction not found.';
            }
            $stmtCheck->close();
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            $sqlItem = "
                UPDATE item
                SET title = ?, description = ?, category_id = ?
                WHERE id = ?
            ";
            $stmtItem = $conn->prepare($sqlItem);
            if ($stmtItem === false) {
                throw new Exception('Failed to prepare item update: ' . $conn->error);
            }
            $stmtItem->bind_param(
                "ssii",
                $title,
                $details,
                $categoryIdInt,
                $itemIdInt
            );
            if (!$stmtItem->execute()) {
                throw new Exception('Failed to execute item update: ' . $stmtItem->error);
            }
            $stmtItem->close();

            $sqlAuction = "
                UPDATE auction
                SET title = ?, start_price = ?, reserve_price = ?, end_date = ?
                WHERE id = ?
            ";
            $stmtAuction = $conn->prepare($sqlAuction);
            if ($stmtAuction === false) {
                throw new Exception('Failed to prepare auction update: ' . $conn->error);
            }
            $stmtAuction->bind_param(
                "sddsi",
                $title,
                $startPriceFloat,
                $reserveOrNull,
                $endTime,
                $auctionIdInt
            );
            if (!$stmtAuction->execute()) {
                throw new Exception('Failed to execute auction update: ' . $stmtAuction->error);
            }
            $stmtAuction->close();

            $conn->commit();
            $success = true;

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }

} else {
    $errors[] = 'Invalid request method.';
}
?>

<?php include_once("header.php"); ?>

<div class="container my-5">
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <h4 class="alert-heading">Failed to update auction:</h4>
      <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <a href="mylistings.php" class="btn btn-secondary">Back to My Listings</a>
  <?php elseif ($success): ?>
    <div class="alert alert-success">
      <h4 class="alert-heading">Auction updated successfully!</h4>
      <a href="mylistings.php" class="btn btn-primary">Back to My Listings</a>
    </div>
  <?php endif; ?>
</div>

<?php include_once("footer.php"); ?>
