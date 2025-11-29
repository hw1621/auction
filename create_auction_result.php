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
    $categoryId = $_POST['category_id'] ?? '';
    $startPrice = $_POST['start_price'] ?? '';
    $reserve    = $_POST['reserve_price'] ?? '';
    $endTimeRaw = $_POST['end_time'] ?? '';

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
        if ($sellerId === 0) {
            $errors[] = 'You must be logged in to create an auction.';
        } else {
            $stmtItem = $conn->prepare("
                INSERT INTO item (title, description, category_id, seller_id)
                VALUES (?, ?, ?, ?)
            ");
            if ($stmtItem === false) {
                $errors[] = 'Database error (prepare item): ' . $conn->error;
            } else {
                $categoryIdInt = (int)$categoryId;
                $stmtItem->bind_param("ssii", $title, $details, $categoryIdInt, $sellerId);

                if ($stmtItem->execute()) {
                    $itemId = $conn->insert_id;
                    $stmtItem->close();

                    $stmtAuction = $conn->prepare("
                        INSERT INTO auction
                            (item_id, user_id, title, start_price, reserve_price, end_date, status, final_price, winner_id)
                        VALUES
                            (?, ?, ?, ?, ?, ?, 'active', NULL, NULL)
                    ");
                    if ($stmtAuction === false) {
                        $errors[] = 'Database error (prepare auction): ' . $conn->error;
                    } else {
                        $stmtAuction->bind_param(
                            "iisdds",
                            $itemId,
                            $sellerId,
                            $title,
                            $startPriceFloat,
                            $reserveOrNull,
                            $endTime         
                        );

                        if ($stmtAuction->execute()) {
                            $newAuctionId = $conn->insert_id;
                            $success = true;
                        } else {
                            $errors[] = 'Database error (execute auction): ' . $stmtAuction->error;
                        }
                        $stmtAuction->close();
                    }
                } else {
                    $errors[] = 'Database error (execute item): ' . $stmtItem->error;
                    $stmtItem->close();
                }
            }
        }
    }

} else {
    $errors[] = 'Invalid request method.';
}
?>


<?php 
if ($success) {
    header("Location: mylistings.php");
    exit;
}
include_once("header.php")?>

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
<?php endif; ?> 

</div>

<?php include_once("footer.php")?>