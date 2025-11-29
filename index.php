<?php
include_once("header.php");
require_once("utilities.php");

$host = "auction.c78qcak427mc.eu-north-1.rds.amazonaws.com";
$user = "admin";
$pass = "useradmin123";
$db   = "db_coursework";

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_errno) {
    echo '<div class="container my-5">';
    echo '<div class="alert alert-danger">Failed to connect to MySQL: '
        . htmlspecialchars($mysqli->connect_error) . '</div>';
    echo '</div>';
    include_once("footer.php");
    exit();
}

$cat_result = $mysqli->query(
    "SELECT id, name
     FROM categories
     ORDER BY name ASC"
);
?>

<div class="container mt-5">
    <h2 class="my-3">Browse auctions by category</h2>
    <p class="text-muted">All auction items are grouped by their category.</p>

<?php

if (!$cat_result || $cat_result->num_rows === 0) {
    echo '<div class="alert alert-info">No categories found.</div>';
} else {


    while ($cat = $cat_result->fetch_assoc()) {
        $catNo   = (int)$cat['id'];      // categories.id
        $catName = $cat['name'];        // categories.name

        echo '<h3 class="mt-4 mb-3">' . htmlspecialchars($catName) . '</h3>';

        $sql_items = "
            SELECT
                i.id          AS item_id,
                i.title       AS title,
                i.description AS description,
                a.start_price AS start_price,
                a.end_date    AS end_date,
                COUNT(b.id)   AS num_bids,
                COALESCE(MAX(b.amount), a.start_price) AS current_price
            FROM item AS i
            JOIN auction AS a
                ON i.id = a.item_id
            LEFT JOIN bid AS b
                ON a.id = b.auction_id
            WHERE i.category_id = $catNo
            GROUP BY
                i.id,
                i.title,
                i.description,
                a.start_price,
                a.end_date
            ORDER BY a.end_date ASC
        ";

        $result_items = $mysqli->query($sql_items);

        if ($result_items && $result_items->num_rows > 0) {
            echo '<ul class="list-group mb-4">';

            while ($row = $result_items->fetch_assoc()) {
                $item_id       = (int)$row['item_id'];
                $title         = $row['title'];
                $description   = $row['description'];
                $current_price = (float)$row['current_price'];
                $num_bids      = (int)$row['num_bids'];
                $end_date      = $row['end_date'];

                print_listing_li(
                    $item_id,
                    $title,
                    $description,
                    $current_price,
                    $num_bids,
                    $end_date
                );

            }

            echo '</ul>';
            $result_items->free();
        } else {
            echo '<p class="text-muted">No auctions in this category.</p>';
        }
    }
}

// 清理资源
if ($cat_result) {
    $cat_result->free();
}
$mysqli->close();
?>
</div>

<?php include_once("footer.php"); ?>
