<?php
include_once("header.php");
require_once("utilities.php");
?>

<?php
// 链接数据库
$host = "auction.c78qcak427mc.eu-north-1.rds.amazonaws.com";
$port = 3306;
$user = "admin";
$pass = "useradmin123";
$db   = "auction";

$mysqli = new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    echo '<div class="container my-5">';
    echo '<div class="alert alert-danger">Failed to connect to MySQL: '
         . htmlspecialchars($mysqli->connect_error) . '</div>';
    echo '</div>';
    include_once("footer.php");
    exit();
}

// 读取分类
$cat_result = $mysqli->query(
    "SELECT categoryNo, categoryName
     FROM category
     ORDER BY categoryName ASC"
);
?>

<div class="container mt-5">
    <h2 class="my-3">Browse auctions by category</h2>
    <p class="text-muted">
        All auction items are grouped by their category.
    </p>

<?php

// 按分类分组展示
if (!$cat_result || $cat_result->num_rows === 0) {
    echo '<div class="alert alert-info">No categories found.</div>';
} else {

    while ($cat = $cat_result->fetch_assoc()) {
        $catNo   = (int)$cat['categoryNo'];
        $catName = $cat['categoryName'];

        // 对于当前分类，查询该分类下的所有拍品
        $sql_items = "
            SELECT
                i.itemNo,
                i.title,
                i.description,
                a.startPrice,
                a.endTime,
                COUNT(b.bidNo) AS num_bids,
                COALESCE(MAX(b.amount), a.startPrice) AS current_price
            FROM item AS i
            JOIN auction AS a ON i.itemNo = a.itemNo
            LEFT JOIN bid AS b ON a.auctionNo = b.auctionNo
            WHERE i.categoryNo = $catNo
            GROUP BY
                i.itemNo,
                i.title,
                i.description,
                a.startPrice,
                a.endTime
            ORDER BY a.endTime ASC
        ";

        $items_result = $mysqli->query($sql_items);

        // 只有这个分类下有拍品时才显示这一块
        if ($items_result && $items_result->num_rows > 0) {
            echo '<h3 class="mt-4 mb-3">' . htmlspecialchars($catName) . '</h3>';
            echo '<ul class="list-group mb-4">';

            while ($row = $items_result->fetch_assoc()) {
                $item_id       = $row['itemNo'];
                $title         = $row['title'];
                $description   = $row['description'];
                $current_price = (float)$row['current_price'];
                $num_bids      = (int)$row['num_bids'];
                $end_date      = new DateTime($row['endTime']);

                // 用 utilities.php 里的函数渲染每一条拍品
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
        }
    }
}

$mysqli->close();
?>

</div>

<?php include_once("footer.php"); ?>

</div>

<?php include_once("footer.php"); ?>
